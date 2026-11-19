package update

import (
	"bytes"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"fmt"
	"io"
	"la2plus/launcher/internal/release"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"sync/atomic"
	"testing"
	"time"
)

type tripFunc func(*http.Request) (*http.Response, error)

func (f tripFunc) RoundTrip(r *http.Request) (*http.Response, error) { return f(r) }

type brokenReader struct{}

func (brokenReader) Read([]byte) (int, error) { return 0, io.ErrUnexpectedEOF }
func noWait(e *Engine)                        { e.retryDelay = func(int) time.Duration { return 0 } }
func TestConnectionResetResumesWithinSingleDownload(t *testing.T) {
	e, m, source := setup(t)
	noWait(e)
	p, err := e.Scan(context.Background(), m, false)
	if err != nil {
		t.Fatal(err)
	}
	p.Missing = p.Missing[:1]
	f := p.Missing[0]
	p.DownloadBytes = f.Packed
	calls := 0
	cut := f.Packed / 2
	e.Client.Transport = tripFunc(func(q *http.Request) (*http.Response, error) {
		calls++
		switch calls {
		case 1:
			return &http.Response{StatusCode: 503, Header: http.Header{"Retry-After": []string{"1"}}, Body: io.NopCloser(bytes.NewReader(nil)), Request: q}, nil
		case 2:
			return nil, &net.OpError{Op: "read", Net: "tcp", Err: errors.New("connection forcibly closed")}
		}
		response, err := source.RoundTrip(q)
		if calls == 3 && err == nil {
			response.Body.Close()
			response.Body = io.NopCloser(io.MultiReader(bytes.NewReader(source.body[f.Offset:f.Offset+cut]), brokenReader{}))
		}
		return response, err
	})
	var retryEvents int
	e.Notify = func(v Event) {
		if v.Retrying {
			retryEvents++
		}
		if v.Bytes > p.DownloadBytes {
			t.Errorf("resume double-counted bytes: %d", v.Bytes)
		}
	}
	if err = e.Download(context.Background(), p); err != nil {
		t.Fatal(err)
	}
	if calls != 4 || retryEvents != 3 {
		t.Fatalf("did not recover automatically: calls=%d retries=%d", calls, retryEvents)
	}
	expected := fmtRange(f.Offset+cut, f.Offset+f.Packed-1)
	if source.requests[len(source.requests)-1] != expected {
		t.Fatal("did not resume from saved byte", source.requests)
	}
	if !validLocal(context.Background(), e.stage(f), f) {
		t.Fatal("unverified staged file")
	}
}
func fmtRange(start, end int64) string { return fmt.Sprintf("bytes=%d-%d", start, end) }
func TestCancelStopsReconnectWait(t *testing.T) {
	e, m, _ := setup(t)
	e.retryDelay = func(int) time.Duration { return time.Hour }
	p, _ := e.Scan(context.Background(), m, false)
	e.Client.Transport = tripFunc(func(q *http.Request) (*http.Response, error) { return nil, io.ErrUnexpectedEOF })
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	e.Notify = func(v Event) {
		if v.Retrying {
			cancel()
		}
	}
	start := time.Now()
	err := e.Download(ctx, p)
	if !errors.Is(err, context.Canceled) || time.Since(start) > time.Second {
		t.Fatal("pause blocked on retry", err)
	}
}
func TestForbiddenIsNotRetried(t *testing.T) {
	e, m, _ := setup(t)
	noWait(e)
	p, _ := e.Scan(context.Background(), m, false)
	p.Missing = p.Missing[:1]
	var calls atomic.Int64
	e.Client.Transport = tripFunc(func(q *http.Request) (*http.Response, error) {
		calls.Add(1)
		return &http.Response{StatusCode: 403, Header: http.Header{}, Body: io.NopCloser(bytes.NewReader(nil)), Request: q}, nil
	})
	if e.Download(context.Background(), p) == nil || calls.Load() != 1 {
		t.Fatal("permanent HTTP failure retried")
	}
}
func TestCorruptCachedPackedFileIsRefetched(t *testing.T) {
	e, m, _ := setup(t)
	noWait(e)
	p, _ := e.Scan(context.Background(), m, false)
	p.Missing = p.Missing[:1]
	f := p.Missing[0]
	p.DownloadBytes = f.Packed
	os.MkdirAll(filepath.Dir(e.packed(f)), 0700)
	os.WriteFile(e.packed(f), bytes.Repeat([]byte{0}, int(f.Packed)), 0600)
	if err := e.Download(context.Background(), p); err != nil {
		t.Fatal(err)
	}
	if !validLocal(context.Background(), e.stage(f), f) {
		t.Fatal("corrupt cache not repaired")
	}
}
func TestReconnectBudgetAndShortBodies(t *testing.T) {
	e, m, source := setup(t)
	noWait(e)
	p, _ := e.Scan(context.Background(), m, false)
	p.Missing = p.Missing[:1]
	var calls int
	e.Client.Transport = tripFunc(func(q *http.Request) (*http.Response, error) {
		calls++
		r, err := source.RoundTrip(q)
		if err == nil {
			r.Body.Close()
			r.Body = io.NopCloser(bytes.NewReader(nil))
		}
		return r, err
	})
	if err := e.Download(context.Background(), p); err == nil || calls != reconnectLimit+1 {
		t.Fatal("no-progress reconnect budget", calls, err)
	}
}

func TestLargeFileChunkAndInterruptedChunkResume(t *testing.T) {
	e, m, _ := setup(t)
	noWait(e)
	data := bytes.Repeat([]byte("chunk-data-"), int(downloadChunk/11)+100)
	hash := sha256.Sum256(data)
	f := m.Files[0]
	f.Offset = 0
	f.Packed = int64(len(data))
	f.Size = f.Packed
	f.Method = 0
	f.SHA256 = hex.EncodeToString(hash[:])
	m.Files = []release.File{f}
	m.Archive.Size = int64(len(data))
	m.Archive.SHA256 = f.SHA256
	source := &ranges{body: data}
	calls := 0
	e.Client.Transport = tripFunc(func(q *http.Request) (*http.Response, error) {
		calls++
		r, err := source.RoundTrip(q)
		if calls == 2 && err == nil {
			r.Body.Close()
			r.Body = io.NopCloser(io.MultiReader(bytes.NewReader(data[downloadChunk:downloadChunk+5]), brokenReader{}))
		}
		return r, err
	})
	p, err := e.Scan(context.Background(), m, false)
	if err != nil {
		t.Fatal(err)
	}
	var maximum int64
	e.Notify = func(v Event) {
		if v.Bytes > maximum {
			maximum = v.Bytes
		}
	}
	if err = e.Download(context.Background(), p); err != nil {
		t.Fatal(err)
	}
	if calls != 3 || source.requests[0] != fmtRange(0, downloadChunk-1) || source.requests[2] != fmtRange(downloadChunk+5, f.Packed-1) {
		t.Fatal("chunk boundaries not resumed", source.requests)
	}
	if maximum > f.Packed {
		t.Fatal("progress counted duplicate bytes")
	}
	if !validLocal(context.Background(), e.stage(f), f) {
		t.Fatal("large file not verified")
	}
}
