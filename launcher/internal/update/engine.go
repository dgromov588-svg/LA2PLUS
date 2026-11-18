package update

import (
	"compress/flate"
	"context"
	"crypto/ed25519"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"la2plus/launcher/internal/release"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"sync/atomic"
	"time"
)

type Event struct {
	Phase, File       string
	Done, Total       int
	Bytes, TotalBytes int64
}
type Stamp struct {
	Size, Modified int64
	SHA256         string
}
type State struct {
	Sequence int64
	Version  string
	Files    map[string]Stamp
}
type Plan struct {
	Manifest                 release.Manifest
	Missing                  []release.File
	DownloadBytes, DiskBytes int64
	State                    State
}
type Engine struct {
	Root      string
	Client    *http.Client
	Notify    func(Event)
	mu        sync.Mutex
	last      time.Time
	lastPhase string
}

func New(root string) *Engine {
	return &Engine{Root: root, Client: &http.Client{Timeout: 30 * time.Minute, Transport: &http.Transport{Proxy: http.ProxyFromEnvironment, TLSHandshakeTimeout: 15 * time.Second, ResponseHeaderTimeout: 30 * time.Second, IdleConnTimeout: 90 * time.Second}, CheckRedirect: func(r *http.Request, via []*http.Request) error {
		if !release.ValidURL(r.URL.String()) || len(via) > 5 {
			return errors.New("недопустимое перенаправление обновления")
		}
		return nil
	}}}
}
func (e *Engine) emit(v Event) {
	if e.Notify == nil {
		return
	}
	e.mu.Lock()
	defer e.mu.Unlock()
	if v.Phase != e.lastPhase || time.Since(e.last) > 150*time.Millisecond || v.Done == v.Total {
		e.last = time.Now()
		e.lastPhase = v.Phase
		e.Notify(v)
	}
}
func writeJSON(name string, value any) error {
	b, err := json.Marshal(value)
	if err != nil {
		return err
	}
	if err = os.MkdirAll(filepath.Dir(name), 0700); err != nil {
		return err
	}
	f, err := os.OpenFile(name+".tmp", os.O_CREATE|os.O_TRUNC|os.O_WRONLY, 0600)
	if err != nil {
		return err
	}
	_, err = f.Write(b)
	if err == nil {
		err = f.Sync()
	}
	closeErr := f.Close()
	if err != nil {
		return err
	}
	if closeErr != nil {
		return closeErr
	}
	return os.Rename(name+".tmp", name)
}
func readJSON(name string, value any) error {
	f, err := os.Open(name)
	if err != nil {
		return err
	}
	defer f.Close()
	return json.NewDecoder(io.LimitReader(f, 32<<20)).Decode(value)
}
func NoLinks(name string) error {
	abs, err := filepath.Abs(name)
	if err != nil {
		return err
	}
	for p := abs; ; p = filepath.Dir(p) {
		info, err := os.Lstat(p)
		if err == nil && info.Mode()&os.ModeSymlink != 0 {
			return errors.New("выберите папку без символических ссылок и junction")
		}
		if err != nil && !os.IsNotExist(err) {
			return err
		}
		if filepath.Dir(p) == p {
			break
		}
	}
	return nil
}
func (e *Engine) target(rel string) (string, error) {
	if err := release.SafePath(rel); err != nil {
		return "", err
	}
	name := filepath.Join(e.Root, filepath.FromSlash(rel))
	if err := NoLinks(name); err != nil {
		return "", err
	}
	return name, nil
}
func (e *Engine) work() string { return filepath.Join(e.Root, ".la2plus") }
func (e *Engine) ValidateRoot() error {
	root, err := filepath.Abs(e.Root)
	if err != nil {
		return err
	}
	if filepath.Dir(root) == root {
		return errors.New("выберите отдельную папку игры")
	}
	home, _ := os.UserHomeDir()
	if strings.EqualFold(root, home) {
		return errors.New("выберите отдельную папку игры")
	}
	if win := os.Getenv("WINDIR"); win != "" && (strings.EqualFold(root, win) || strings.HasPrefix(strings.ToLower(root), strings.ToLower(win)+string(os.PathSeparator))) {
		return errors.New("нельзя устанавливать игру в системную папку")
	}
	e.Root = root
	return NoLinks(root)
}
func HashFile(ctx context.Context, name string) (string, error) {
	f, err := os.Open(name)
	if err != nil {
		return "", err
	}
	defer f.Close()
	h := sha256.New()
	buffer := make([]byte, 128*1024)
	for {
		if err = ctx.Err(); err != nil {
			return "", err
		}
		n, er := f.Read(buffer)
		if n > 0 {
			_, _ = h.Write(buffer[:n])
		}
		if er == io.EOF {
			break
		}
		if er != nil {
			return "", er
		}
	}
	return hex.EncodeToString(h.Sum(nil)), nil
}
func (e *Engine) Fetch(ctx context.Context) (release.Manifest, error) {
	var zero release.Manifest
	request, err := http.NewRequestWithContext(ctx, "GET", release.ManifestURL, nil)
	if err != nil {
		return zero, err
	}
	request.Header.Set("Cache-Control", "no-cache")
	response, err := e.Client.Do(request)
	if err != nil {
		return zero, fmt.Errorf("сервер обновлений недоступен: %w", err)
	}
	defer response.Body.Close()
	if response.StatusCode != 200 {
		return zero, fmt.Errorf("сервер обновлений: HTTP %d", response.StatusCode)
	}
	data, err := io.ReadAll(io.LimitReader(response.Body, (32<<20)+1))
	if err != nil {
		return zero, err
	}
	key, err := base64.StdEncoding.DecodeString(release.PublicKey)
	if err != nil {
		return zero, err
	}
	return release.Verify(data, ed25519.PublicKey(key))
}
func (e *Engine) Scan(ctx context.Context, m release.Manifest, full bool) (Plan, error) {
	p := Plan{Manifest: m, State: State{Files: map[string]Stamp{}}}
	if err := e.ValidateRoot(); err != nil {
		return p, err
	}
	if err := m.Validate(); err != nil {
		return p, err
	}
	if err := NoLinks(e.work()); err != nil {
		return p, err
	}
	if err := e.Recover(ctx); err != nil {
		return p, err
	}
	_ = readJSON(filepath.Join(e.work(), "state.json"), &p.State)
	if p.State.Files == nil {
		p.State.Files = map[string]Stamp{}
	}
	if m.Sequence < p.State.Sequence {
		return p, errors.New("отклонена устаревшая версия обновления")
	}
	var peak int64
	for i, f := range m.Files {
		if err := ctx.Err(); err != nil {
			return p, err
		}
		name, err := e.target(f.Path)
		if err != nil {
			return p, err
		}
		info, err := os.Stat(name)
		if err == nil && !info.Mode().IsRegular() {
			return p, fmt.Errorf("ожидался файл: %s", f.Path)
		}
		valid := false
		if err == nil && info.Mode().IsRegular() {
			if f.Mutable {
				valid = true
			} else if info.Size() == f.Size {
				stamp := p.State.Files[f.Path]
				if !full && stamp.SHA256 == f.SHA256 && stamp.Size == info.Size() && stamp.Modified == info.ModTime().UnixNano() {
					valid = true
				} else {
					hash, er := HashFile(ctx, name)
					if er != nil {
						return p, er
					}
					valid = hash == f.SHA256
				}
				if valid {
					p.State.Files[f.Path] = Stamp{info.Size(), info.ModTime().UnixNano(), f.SHA256}
				}
			}
		} else if err != nil && !os.IsNotExist(err) {
			return p, err
		}
		if !valid {
			p.Missing = append(p.Missing, f)
			p.DownloadBytes += f.Packed
			p.DiskBytes += f.Size
			if f.Packed > peak {
				peak = f.Packed
			}
		}
		e.emit(Event{Phase: "Проверка файлов", File: f.Path, Done: i + 1, Total: len(m.Files)})
	}
	p.DiskBytes += peak*3 + (512 << 20)
	if len(p.Missing) == 0 {
		p.State.Sequence = m.Sequence
		p.State.Version = m.Version
	}
	if err := writeJSON(filepath.Join(e.work(), "state.json"), p.State); err != nil {
		return p, err
	}
	return p, nil
}
func (e *Engine) stage(f release.File) string {
	return filepath.Join(e.work(), "staging", f.SHA256+"-"+strconv.FormatInt(f.Offset, 10))
}
func (e *Engine) packed(f release.File) string {
	return filepath.Join(e.work(), "packed", f.SHA256+"-"+strconv.FormatInt(f.Offset, 10))
}
func validLocal(ctx context.Context, name string, f release.File) bool {
	if NoLinks(name) != nil {
		return false
	}
	info, err := os.Stat(name)
	if err != nil || !info.Mode().IsRegular() || info.Size() != f.Size {
		return false
	}
	h, err := HashFile(ctx, name)
	return err == nil && h == f.SHA256
}

type counterWriter struct {
	Writer io.Writer
	add    func(int64)
}

func (w counterWriter) Write(p []byte) (int, error) {
	n, err := w.Writer.Write(p)
	if n > 0 {
		w.add(int64(n))
	}
	return n, err
}
func (e *Engine) download(ctx context.Context, m release.Manifest, f release.File, add func(int64)) error {
	packed := e.packed(f)
	if err := NoLinks(packed); err != nil {
		return err
	}
	if err := NoLinks(e.stage(f) + ".tmp"); err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(packed), 0700); err != nil {
		return err
	}
	var have int64
	if info, err := os.Stat(packed); err == nil {
		have = info.Size()
		if have > f.Packed {
			if err = os.Remove(packed); err != nil {
				return err
			}
			have = 0
		}
	}
	add(have)
	if have < f.Packed {
		request, err := http.NewRequestWithContext(ctx, "GET", m.Archive.URL, nil)
		if err != nil {
			return err
		}
		request.Header.Set("Range", fmt.Sprintf("bytes=%d-%d", f.Offset+have, f.Offset+f.Packed-1))
		request.Header.Set("Accept-Encoding", "identity")
		response, err := e.Client.Do(request)
		if err != nil {
			return err
		}
		defer response.Body.Close()
		expected := fmt.Sprintf("bytes %d-%d/%d", f.Offset+have, f.Offset+f.Packed-1, m.Archive.Size)
		if response.StatusCode != 206 || response.Header.Get("Content-Range") != expected {
			return fmt.Errorf("сервер не подтвердил диапазон загрузки для %s", f.Path)
		}
		file, err := os.OpenFile(packed, os.O_WRONLY|os.O_CREATE|os.O_APPEND, 0600)
		if err != nil {
			return err
		}
		n, copyErr := io.Copy(counterWriter{file, add}, io.LimitReader(response.Body, f.Packed-have+1))
		syncErr := file.Sync()
		closeErr := file.Close()
		if copyErr != nil {
			return copyErr
		}
		if syncErr != nil {
			return syncErr
		}
		if closeErr != nil {
			return closeErr
		}
		if n != f.Packed-have {
			return errors.New("загрузка прервана: размер файла не совпал")
		}
	}
	if f.Packed == 0 {
		if err := os.WriteFile(packed, nil, 0600); err != nil {
			return err
		}
	}
	source, err := os.Open(packed)
	if err != nil {
		return err
	}
	defer source.Close()
	var reader io.Reader = source
	var deflate io.ReadCloser
	if f.Method == 8 {
		deflate = flate.NewReader(source)
		defer deflate.Close()
		reader = deflate
	}
	stage := e.stage(f)
	if err = os.MkdirAll(filepath.Dir(stage), 0700); err != nil {
		return err
	}
	out, err := os.OpenFile(stage+".tmp", os.O_CREATE|os.O_TRUNC|os.O_WRONLY, 0600)
	if err != nil {
		return err
	}
	h := sha256.New()
	n, err := io.Copy(io.MultiWriter(out, h), io.LimitReader(reader, f.Size+1))
	if err == nil {
		err = out.Sync()
	}
	closeErr := out.Close()
	source.Close()
	if err != nil {
		_ = os.Remove(packed)
		_ = os.Remove(stage + ".tmp")
		return err
	}
	if closeErr != nil {
		return closeErr
	}
	if n != f.Size || hex.EncodeToString(h.Sum(nil)) != f.SHA256 {
		_ = os.Remove(packed)
		_ = os.Remove(stage + ".tmp")
		return fmt.Errorf("контрольная сумма %s не совпала; повторите проверку", f.Path)
	}
	if err = os.Rename(stage+".tmp", stage); err != nil {
		return err
	}
	source.Close()
	_ = os.Remove(packed)
	return nil
}
func (e *Engine) Download(ctx context.Context, p Plan) error {
	ctx, cancel := context.WithCancel(ctx)
	defer cancel()
	var downloaded atomic.Int64
	var completed atomic.Int64
	jobs := make(chan release.File)
	errs := make(chan error, 1)
	var wg sync.WaitGroup
	for i := 0; i < 3; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			for f := range jobs {
				if ctx.Err() != nil {
					return
				}
				if validLocal(ctx, e.stage(f), f) {
					downloaded.Add(f.Packed)
					completed.Add(1)
					continue
				}
				add := func(n int64) {
					value := downloaded.Add(n)
					e.emit(Event{Phase: "Загрузка клиента", File: f.Path, Done: int(completed.Load()), Total: len(p.Missing), Bytes: value, TotalBytes: p.DownloadBytes})
				}
				err := e.download(ctx, p.Manifest, f, add)
				if err != nil {
					select {
					case errs <- err:
						cancel()
					default:
					}
					return
				}
				completed.Add(1)
			}
		}()
	}
send:
	for _, f := range p.Missing {
		select {
		case jobs <- f:
		case <-ctx.Done():
			break send
		}
	}
	close(jobs)
	wg.Wait()
	select {
	case err := <-errs:
		return err
	default:
	}
	if err := ctx.Err(); err != nil {
		return err
	}
	return nil
}
func (e *Engine) SaveInstalled(p Plan) error {
	state := State{Sequence: p.Manifest.Sequence, Version: p.Manifest.Version, Files: map[string]Stamp{}}
	for _, f := range p.Manifest.Files {
		target, err := e.target(f.Path)
		if err != nil {
			return err
		}
		info, err := os.Stat(target)
		if err != nil {
			return err
		}
		state.Files[f.Path] = Stamp{info.Size(), info.ModTime().UnixNano(), f.SHA256}
	}
	return writeJSON(filepath.Join(e.work(), "state.json"), state)
}
