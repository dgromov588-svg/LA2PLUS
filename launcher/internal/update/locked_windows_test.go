//go:build windows

package update

import (
	"context"
	"golang.org/x/sys/windows"
	"os"
	"path/filepath"
	"testing"
)

func TestLockedGameFileRollsBackEarlierReplacements(t *testing.T) {
	e, m, _ := setup(t)
	first := filepath.Join(e.Root, "system_ru", "L2.exe")
	second := filepath.Join(e.Root, "Textures", "test.utx")
	os.MkdirAll(filepath.Dir(first), 0755)
	os.MkdirAll(filepath.Dir(second), 0755)
	os.WriteFile(first, []byte("old-executable"), 0600)
	os.WriteFile(second, []byte("old-texture"), 0600)
	p, err := e.Scan(context.Background(), m, false)
	if err != nil {
		t.Fatal(err)
	}
	if err = e.Download(context.Background(), p); err != nil {
		t.Fatal(err)
	}
	name, _ := windows.UTF16PtrFromString(second)
	handle, err := windows.CreateFile(name, windows.GENERIC_READ, windows.FILE_SHARE_READ, nil, windows.OPEN_EXISTING, 0, 0)
	if err != nil {
		t.Fatal(err)
	}
	defer windows.CloseHandle(handle)
	if e.Apply(context.Background(), p) == nil {
		t.Fatal("locked file was replaced")
	}
	b, _ := os.ReadFile(first)
	if string(b) != "old-executable" {
		t.Fatal("earlier replacement not rolled back")
	}
	b, _ = os.ReadFile(second)
	if string(b) != "old-texture" {
		t.Fatal("locked file altered")
	}
}
