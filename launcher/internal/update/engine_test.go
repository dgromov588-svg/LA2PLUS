package update

import (
	"archive/zip"
	"bytes"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"la2plus/launcher/internal/release"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"testing"
)

type ranges struct {
	body     []byte
	mu       sync.Mutex
	requests []string
	wrong    bool
}

func (r *ranges) RoundTrip(q *http.Request) (*http.Response, error) {
	if err := q.Context().Err(); err != nil {
		return nil, err
	}
	r.mu.Lock()
	r.requests = append(r.requests, q.Header.Get("Range"))
	r.mu.Unlock()
	var start, end int64
	_, err := fmt.Sscanf(q.Header.Get("Range"), "bytes=%d-%d", &start, &end)
	if err != nil || start < 0 || end >= int64(len(r.body)) {
		return nil, fmt.Errorf("bad test range")
	}
	code := 206
	if r.wrong {
		code = 200
	}
	return &http.Response{StatusCode: code, Header: http.Header{"Content-Range": []string{fmt.Sprintf("bytes %d-%d/%d", start, end, len(r.body))}}, Body: io.NopCloser(bytes.NewReader(r.body[start : end+1])), Request: q}, nil
}
func fixture(t *testing.T) (release.Manifest, []byte) {
	t.Helper()
	var b bytes.Buffer
	writer := zip.NewWriter(&b)
	files := []struct{ path, data string }{{"system_ru/L2.exe", "actual-game-executable"}, {"Textures/test.utx", strings.Repeat("texture", 2000)}, {"system_ru/Option.ini", "default-settings"}, {"empty.dat", ""}}
	for _, f := range files {
		w, e := writer.Create(f.path)
		if e != nil {
			t.Fatal(e)
		}
		w.Write([]byte(f.data))
	}
	writer.Close()
	data := b.Bytes()
	archiveHash := sha256.Sum256(data)
	m := release.Manifest{Schema: 1, Version: "v1", Sequence: 20, Archive: release.Archive{URL: "https://la2plus.com/test.zip", Size: int64(len(data)), SHA256: hex.EncodeToString(archiveHash[:])}, Entrypoints: map[string]string{"ru": "system_ru/L2.exe"}}
	reader, e := zip.NewReader(bytes.NewReader(data), int64(len(data)))
	if e != nil {
		t.Fatal(e)
	}
	for _, f := range reader.File {
		stream, _ := f.Open()
		raw, _ := io.ReadAll(stream)
		stream.Close()
		hash := sha256.Sum256(raw)
		offset, _ := f.DataOffset()
		m.Files = append(m.Files, release.File{Path: f.Name, Size: int64(len(raw)), Packed: int64(f.CompressedSize64), Offset: offset, Method: f.Method, SHA256: hex.EncodeToString(hash[:]), Mutable: release.MutablePath(f.Name)})
	}
	return m, data
}
func setup(t *testing.T) (*Engine, release.Manifest, *ranges) {
	t.Helper()
	m, data := fixture(t)
	r := &ranges{body: data}
	e := New(filepath.Join(t.TempDir(), "game"))
	e.Client = &http.Client{Transport: r}
	return e, m, r
}
func TestInstallRepairAndPreferences(t *testing.T) {
	e, m, _ := setup(t)
	ctx := context.Background()
	p, err := e.Scan(ctx, m, false)
	if err != nil {
		t.Fatal(err)
	}
	if len(p.Missing) != 4 {
		t.Fatal("missing files not found")
	}
	if err = e.Download(ctx, p); err != nil {
		t.Fatal(err)
	}
	if err = e.Apply(ctx, p); err != nil {
		t.Fatal(err)
	}
	p, err = e.Scan(ctx, m, false)
	if err != nil || len(p.Missing) != 0 {
		t.Fatal("installed files not recognized", err)
	}
	option := filepath.Join(e.Root, "system_ru", "Option.ini")
	os.WriteFile(option, []byte("my-personal-settings"), 0600)
	texture := filepath.Join(e.Root, "Textures", "test.utx")
	os.WriteFile(texture, []byte("broken"), 0600)
	p, err = e.Scan(ctx, m, true)
	if err != nil || len(p.Missing) != 1 || p.Missing[0].Path != "Textures/test.utx" {
		t.Fatal("repair overwrote settings or missed corruption", err)
	}
	if err = e.Download(ctx, p); err != nil {
		t.Fatal(err)
	}
	if err = e.Apply(ctx, p); err != nil {
		t.Fatal(err)
	}
	own, _ := os.ReadFile(option)
	if string(own) != "my-personal-settings" {
		t.Fatal("personal settings overwritten")
	}
}
func TestResumeAndRangeVerification(t *testing.T) {
	e, m, r := setup(t)
	ctx := context.Background()
	p, err := e.Scan(ctx, m, false)
	if err != nil {
		t.Fatal(err)
	}
	f := p.Missing[0]
	have := f.Packed / 2
	name := e.packed(f)
	os.MkdirAll(filepath.Dir(name), 0700)
	os.WriteFile(name, r.body[f.Offset:f.Offset+have], 0600)
	if err = e.Download(ctx, p); err != nil {
		t.Fatal(err)
	}
	expected := fmt.Sprintf("bytes=%d-%d", f.Offset+have, f.Offset+f.Packed-1)
	found := false
	for _, v := range r.requests {
		if v == expected {
			found = true
		}
	}
	if !found {
		t.Fatal("partial download not resumed")
	}
	e2, m2, r2 := setup(t)
	r2.wrong = true
	p2, _ := e2.Scan(ctx, m2, false)
	if e2.Download(ctx, p2) == nil {
		t.Fatal("full archive response accepted for range")
	}
	if _, err = os.Stat(filepath.Join(e2.Root, "system_ru", "L2.exe")); !os.IsNotExist(err) {
		t.Fatal("unverified game installed")
	}
}
func TestHashMismatchAndCancel(t *testing.T) {
	e, m, _ := setup(t)
	m.Files[0].SHA256 = strings.Repeat("0", 64)
	p, err := e.Scan(context.Background(), m, false)
	if err != nil {
		t.Fatal(err)
	}
	if e.Download(context.Background(), p) == nil {
		t.Fatal("corrupt hash accepted")
	}
	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	if _, err = e.Scan(ctx, m, false); err == nil {
		t.Fatal("cancel ignored")
	}
}
func TestInterruptedCommitRecovers(t *testing.T) {
	e, m, _ := setup(t)
	ctx := context.Background()
	p, err := e.Scan(ctx, m, false)
	if err != nil {
		t.Fatal(err)
	}
	if err = e.Download(ctx, p); err != nil {
		t.Fatal(err)
	}
	f := m.Files[0]
	target, _ := e.target(f.Path)
	os.MkdirAll(filepath.Dir(target), 0755)
	os.WriteFile(target, []byte("original-game"), 0600)
	backup := filepath.Join(e.work(), "backup", filepath.FromSlash(f.Path))
	os.MkdirAll(filepath.Dir(backup), 0700)
	writeJSON(filepath.Join(e.work(), "journal.json"), Journal{Changes: []Change{{f, true}}})
	os.Rename(target, backup)
	os.Rename(e.stage(f), target)
	if err = e.Recover(ctx); err != nil {
		t.Fatal(err)
	}
	b, _ := os.ReadFile(target)
	if string(b) != "original-game" {
		t.Fatal("original not restored")
	}
}
func TestSymlinkCannotEscape(t *testing.T) {
	e, m, _ := setup(t)
	outside := t.TempDir()
	os.MkdirAll(e.Root, 0700)
	if err := os.Symlink(outside, filepath.Join(e.Root, "system_ru")); err != nil {
		t.Skip("symlinks unavailable")
	}
	if _, err := e.Scan(context.Background(), m, false); err == nil {
		t.Fatal("symlink accepted")
	}
	if files, _ := os.ReadDir(outside); len(files) != 0 {
		t.Fatal("outside path written")
	}
}
func TestRollbackManifest(t *testing.T) {
	e, m, _ := setup(t)
	writeJSON(filepath.Join(e.work(), "state.json"), State{Sequence: 30})
	if _, err := e.Scan(context.Background(), m, false); err == nil {
		t.Fatal("old signed release accepted")
	}
}
