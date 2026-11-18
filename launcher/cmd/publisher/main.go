package main

import (
	"archive/zip"
	"crypto/ed25519"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"la2plus/launcher/internal/release"
	"os"
	"path/filepath"
	"strings"
	"time"
)

func atomic(name string, b []byte) error {
	if e := os.MkdirAll(filepath.Dir(name), 0755); e != nil {
		return e
	}
	tmp := name + ".tmp"
	if e := os.WriteFile(tmp, b, 0600); e != nil {
		return e
	}
	return os.Rename(tmp, name)
}
func main() {
	src := flag.String("archive", "", "source game ZIP")
	out := flag.String("out", "", "public launcher directory")
	keyFile := flag.String("key", "", "private base64 seed file")
	version := flag.String("version", "", "release version")
	status := flag.String("status", "", "private progress JSON")
	gen := flag.Bool("generate-key", false, "create a private seed")
	flag.Parse()
	if *gen {
		seed := make([]byte, ed25519.SeedSize)
		if _, e := rand.Read(seed); e != nil {
			panic(e)
		}
		if e := os.WriteFile(*keyFile, []byte(base64.StdEncoding.EncodeToString(seed)), 0600); e != nil {
			panic(e)
		}
		fmt.Println(base64.StdEncoding.EncodeToString(ed25519.NewKeyFromSeed(seed).Public().(ed25519.PublicKey)))
		return
	}
	progress := func(phase string, done, total int, detail string) {
		if *status != "" {
			b, _ := json.Marshal(map[string]any{"phase": phase, "done": done, "total": total, "detail": detail, "time": time.Now().Unix()})
			_ = atomic(*status, b)
		}
	}
	run := func() error {
		seedText, e := os.ReadFile(*keyFile)
		if e != nil {
			return e
		}
		seed, e := base64.StdEncoding.DecodeString(strings.TrimSpace(string(seedText)))
		if e != nil || len(seed) != 32 {
			return fmt.Errorf("invalid signing seed")
		}
		key := ed25519.NewKeyFromSeed(seed)
		if base64.StdEncoding.EncodeToString(key.Public().(ed25519.PublicKey)) != release.PublicKey {
			return fmt.Errorf("signing key does not match launcher")
		}
		info, e := os.Stat(*src)
		if e != nil {
			return e
		}
		z, e := zip.OpenReader(*src)
		if e != nil {
			return e
		}
		defer z.Close()
		progress("hashing_archive", 0, len(z.File), "")
		a, e := os.Open(*src)
		if e != nil {
			return e
		}
		h := sha256.New()
		_, e = io.CopyBuffer(h, a, make([]byte, 1024*1024))
		a.Close()
		if e != nil {
			return e
		}
		digest := hex.EncodeToString(h.Sum(nil))
		m := release.Manifest{Schema: 1, Version: *version, Sequence: time.Now().Unix(), Archive: release.Archive{URL: "https://la2plus.com/launcher/releases/" + *version + "/client.zip", Size: info.Size(), SHA256: digest}, Entrypoints: map[string]string{}}
		for i, f := range z.File {
			name := strings.ReplaceAll(f.Name, `\`, "/")
			low := strings.ToLower(name)
			if f.FileInfo().IsDir() || strings.HasPrefix(low, "_backup") || strings.HasPrefix(low, "_safe_stage/") || strings.HasPrefix(low, "meta-inf/") || strings.Contains(low, ".bak") || low == "l2plus-updater.exe" {
				continue
			}
			if f.Flags&1 != 0 {
				return fmt.Errorf("encrypted file %s", name)
			}
			if e := release.SafePath(name); e != nil {
				return e
			}
			r, e := f.Open()
			if e != nil {
				return e
			}
			fh := sha256.New()
			n, e := io.CopyBuffer(fh, r, make([]byte, 128*1024))
			r.Close()
			if e != nil {
				return fmt.Errorf("%s: %w", name, e)
			}
			offset, e := f.DataOffset()
			if e != nil {
				return e
			}
			m.Files = append(m.Files, release.File{Path: name, Size: n, Packed: int64(f.CompressedSize64), Offset: offset, Method: f.Method, SHA256: hex.EncodeToString(fh.Sum(nil)), Mutable: release.MutablePath(name)})
			if low == "system_ru/l2.exe" {
				m.Entrypoints["ru"] = name
			}
			if low == "system_en/l2.exe" {
				m.Entrypoints["en"] = name
			}
			if i%10 == 0 {
				progress("hashing_files", i, len(z.File), name)
			}
		}
		now, e := os.Stat(*src)
		if e != nil || now.Size() != info.Size() || !now.ModTime().Equal(info.ModTime()) {
			return fmt.Errorf("source archive changed while publishing")
		}
		signed, e := release.Sign(m, key)
		if e != nil {
			return e
		}
		folder := filepath.Join(*out, "releases", *version)
		if e = os.MkdirAll(folder, 0755); e != nil {
			return e
		}
		dest := filepath.Join(folder, "client.zip")
		if _, e = os.Lstat(dest); !os.IsNotExist(e) {
			return fmt.Errorf("release already exists")
		}
		if e = os.Link(*src, dest); e != nil {
			return fmt.Errorf("cannot link release archive: %w", e)
		}
		if e = atomic(filepath.Join(folder, "manifest.json"), signed); e != nil {
			return e
		}
		if e = os.Chmod(filepath.Join(folder, "manifest.json"), 0644); e != nil {
			return e
		}
		if e = atomic(filepath.Join(*out, "stable.json"), signed); e != nil {
			return e
		}
		if e = os.Chmod(filepath.Join(*out, "stable.json"), 0644); e != nil {
			return e
		}
		progress("complete", len(m.Files), len(m.Files), m.Version)
		fmt.Printf("Published %s: %d verified files\n", m.Version, len(m.Files))
		return nil
	}
	if e := run(); e != nil {
		progress("failed", 0, 0, e.Error())
		fmt.Fprintln(os.Stderr, e)
		os.Exit(1)
	}
}
