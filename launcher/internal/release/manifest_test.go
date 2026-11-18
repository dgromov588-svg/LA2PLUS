package release

import (
	"crypto/ed25519"
	"crypto/rand"
	"strings"
	"testing"
)

func fixture() Manifest {
	return Manifest{Schema: 1, Version: "2026.11.18.1", Sequence: 10, Archive: Archive{URL: "https://la2plus.com/client.zip", Size: 100, SHA256: strings.Repeat("a", 64)}, Entrypoints: map[string]string{"ru": "system_ru/L2.exe"}, Files: []File{{Path: "system_ru/L2.exe", Size: 10, Packed: 10, Offset: 10, Method: 0, SHA256: strings.Repeat("b", 64)}}}
}
func TestSignatureAndBinding(t *testing.T) {
	pub, key, _ := ed25519.GenerateKey(rand.Reader)
	m := fixture()
	b, e := Sign(m, key)
	if e != nil {
		t.Fatal(e)
	}
	got, e := Verify(b, pub)
	if e != nil || got.Version != m.Version {
		t.Fatal(e)
	}
	b[len(b)/2] ^= 1
	if _, e = Verify(b, pub); e == nil {
		t.Fatal("tampered update accepted")
	}
	other, _, _ := ed25519.GenerateKey(rand.Reader)
	b, _ = Sign(m, key)
	if _, e = Verify(b, other); e == nil {
		t.Fatal("wrong key accepted")
	}
}
func TestWindowsPaths(t *testing.T) {
	for _, p := range []string{"../system/L2.exe", "/L2.exe", `C:\Windows\bad.exe`, `system/../bad`, "system/L2.exe:stream", "system/CON.txt", "system/COM1.ini", "system/file.", "system/file ", ".la2plus/state.json", "system//file"} {
		if SafePath(p) == nil {
			t.Errorf("unsafe path accepted: %q", p)
		}
	}
	if e := SafePath("system_ru/L2.exe"); e != nil {
		t.Fatal(e)
	}
}
func TestManifestPolicy(t *testing.T) {
	m := fixture()
	m.Files = append(m.Files, m.Files[0])
	if m.Validate() == nil {
		t.Fatal("duplicate accepted")
	}
	m = fixture()
	m.Files[0].Offset = 99
	if m.Validate() == nil {
		t.Fatal("archive bounds ignored")
	}
	m = fixture()
	m.Files[0].Mutable = true
	if m.Validate() == nil {
		t.Fatal("mutable executable accepted")
	}
	m = fixture()
	m.Archive.URL = "http://la2plus.com/file"
	if m.Validate() == nil {
		t.Fatal("insecure update URL")
	}
}
