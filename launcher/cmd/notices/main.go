package main

import (
	"encoding/json"
	"fmt"
	"io"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
)

func main() {
	out := os.Stdout
	if len(os.Args) > 1 {
		f, e := os.Create(os.Args[1])
		if e != nil {
			panic(e)
		}
		defer f.Close()
		out = f
	}
	fmt.Fprintln(out, "LA2PLUS Launcher — third-party notices")
	license, _ := os.ReadFile(filepath.Join(runtime.GOROOT(), "LICENSE"))
	fmt.Fprintln(out, "Go standard library\n", string(license))
	cmd := exec.Command("go", "list", "-m", "-json", "all")
	b, e := cmd.Output()
	if e != nil {
		panic(e)
	}
	decoder := json.NewDecoder(strings.NewReader(string(b)))
	for {
		var m struct {
			Path, Version, Dir string
			Main               bool
		}
		e = decoder.Decode(&m)
		if e == io.EOF {
			break
		}
		if e != nil {
			panic(e)
		}
		if m.Main {
			continue
		}
		fmt.Fprintf(out, "\n%s %s\n", m.Path, m.Version)
		for _, name := range []string{"LICENSE", "LICENSE.txt", "LICENSE.md", "COPYING"} {
			if b, e := os.ReadFile(filepath.Join(m.Dir, name)); e == nil {
				fmt.Fprintln(out, string(b))
				break
			}
		}
	}
}
