// Verifies real hosting ranges against signed hashes without installing or launching the game.
package main

import (
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"la2plus/launcher/internal/update"
	"os"
	"time"
)

func main() {
	folder := flag.String("folder", "", "empty temporary smoke folder")
	flag.Parse()
	if *folder == "" {
		panic("folder required")
	}
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Minute)
	defer cancel()
	e := update.New(*folder)
	m, err := e.Fetch(ctx)
	if err != nil {
		panic(err)
	}
	p, err := e.Scan(ctx, m, false)
	if err != nil {
		panic(err)
	}
	p.Missing = nil
	p.DownloadBytes = 0
	for _, f := range m.Files {
		if f.Path == m.Entrypoints["ru"] || f.Path == m.Entrypoints["en"] || f.Path == "system_ru/Option.ini" {
			p.Missing = append(p.Missing, f)
			p.DownloadBytes += f.Packed
		}
	}
	if len(p.Missing) < 2 {
		panic("missing game entrypoints")
	}
	if err = e.Download(ctx, p); err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
	files := []string{}
	for _, f := range p.Missing {
		files = append(files, f.Path)
	}
	json.NewEncoder(os.Stdout).Encode(map[string]any{"verified": true, "version": m.Version, "total_files": len(m.Files), "archive_size": m.Archive.Size, "verified_samples": files, "game_not_executed": true})
}
