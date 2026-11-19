// Verifies one complete production installation with injected recoverable network faults.
package main

import (
	"context"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"la2plus/launcher/internal/update"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"sync/atomic"
	"time"
)

type faults struct {
	base            http.RoundTripper
	requests, count atomic.Int64
}

func (f *faults) RoundTrip(q *http.Request) (*http.Response, error) {
	n := f.requests.Add(1)
	r, e := f.base.RoundTrip(q)
	if e != nil {
		return r, e
	}
	if n%97 == 0 && n <= 970 && r.StatusCode == 206 {
		f.count.Add(1)
		if n%2 == 0 || r.ContentLength < 4 {
			r.Body.Close()
			r.StatusCode = 503
			r.Header.Set("Retry-After", "1")
			r.Body = io.NopCloser(&emptyReader{})
			return r, nil
		}
		remaining := int64(16 << 10)
		if r.ContentLength/2 < remaining {
			remaining = r.ContentLength / 2
		}
		r.Body = &resetBody{ReadCloser: r.Body, remaining: remaining}
	}
	return r, nil
}

type emptyReader struct{}

func (*emptyReader) Read([]byte) (int, error) { return 0, io.EOF }

type resetBody struct {
	io.ReadCloser
	remaining int64
}

func (b *resetBody) Read(p []byte) (int, error) {
	if b.remaining == 0 {
		return 0, &net.OpError{Op: "read", Net: "tcp", Err: errors.New("injected connection reset")}
	}
	if int64(len(p)) > b.remaining {
		p = p[:b.remaining]
	}
	n, e := b.ReadCloser.Read(p)
	b.remaining -= int64(n)
	return n, e
}
func main() {
	folder := flag.String("folder", "", "isolated empty test folder")
	report := flag.String("report", "", "proof JSON written only after complete verification")
	inject := flag.Bool("inject-faults", false, "inject resets/503 while downloading real files")
	flag.Parse()
	if *folder == "" || *report == "" {
		panic("folder and report required")
	}
	if list, e := os.ReadDir(*folder); e == nil && len(list) > 0 {
		panic("full check requires an empty isolated folder")
	}
	ctx, cancel := context.WithTimeout(context.Background(), 100*time.Minute)
	defer cancel()
	e := update.New(*folder)
	transport := &faults{base: e.Client.Transport}
	if *inject {
		e.Client.Transport = transport
	}
	var last time.Time
	var reconnects int
	started := time.Now()
	e.Notify = func(v update.Event) {
		if v.Retrying {
			reconnects++
		}
		if v.Retrying || time.Since(last) > 10*time.Second || v.Done == v.Total {
			json.NewEncoder(os.Stdout).Encode(map[string]any{"phase": v.Phase, "file": v.File, "done": v.Done, "total": v.Total, "bytes": v.Bytes, "total_bytes": v.TotalBytes, "elapsed_seconds": int(time.Since(started).Seconds())})
			last = time.Now()
		}
	}
	m, err := e.Fetch(ctx)
	must(err)
	p, err := e.Scan(ctx, m, false)
	must(err)
	fmt.Printf("Full release: %s, %d files, %d compressed bytes, %d required disk bytes\n", m.Version, len(m.Files), p.DownloadBytes, p.DiskBytes)
	must(e.Download(ctx, p))
	must(e.Apply(ctx, p))
	verified, err := e.Scan(ctx, m, true)
	must(err)
	if len(verified.Missing) != 0 {
		panic(fmt.Sprintf("full hash verification still missing %d files", len(verified.Missing)))
	}
	if *inject && (transport.count.Load() < 3 || reconnects < 3) {
		panic("insufficient real-download fault coverage")
	}
	result := map[string]any{"verified": true, "version": m.Version, "files": len(m.Files), "compressed_bytes": p.DownloadBytes, "all_files_sha256_verified": true, "injected_faults": transport.count.Load(), "automatic_reconnects": reconnects, "seconds": int(time.Since(started).Seconds()), "game_not_executed": true}
	data, _ := json.MarshalIndent(result, "", "  ")
	must(os.MkdirAll(filepath.Dir(*report), 0700))
	must(os.WriteFile(*report, data, 0600))
	fmt.Println(string(data))
}
func must(e error) {
	if e != nil {
		panic(e)
	}
}
