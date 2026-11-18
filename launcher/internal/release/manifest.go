package release

import (
	"crypto/ed25519"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"net/url"
	"path"
	"regexp"
	"strings"
)

const ManifestURL = "https://la2plus.com/launcher/stable.json"
const AppVersion = "1.0.1"

type Archive struct {
	URL    string `json:"url"`
	Size   int64  `json:"size"`
	SHA256 string `json:"sha256"`
}
type File struct {
	Path    string `json:"path"`
	Size    int64  `json:"size"`
	Packed  int64  `json:"packed"`
	Offset  int64  `json:"offset"`
	Method  uint16 `json:"method"`
	SHA256  string `json:"sha256"`
	Mutable bool   `json:"mutable,omitempty"`
}
type Manifest struct {
	Schema      int               `json:"schema"`
	Version     string            `json:"version"`
	Sequence    int64             `json:"sequence"`
	Archive     Archive           `json:"archive"`
	Entrypoints map[string]string `json:"entrypoints"`
	Files       []File            `json:"files"`
}
type Envelope struct {
	Payload   string `json:"payload"`
	Signature string `json:"signature"`
}

var versionPattern = regexp.MustCompile(`^[A-Za-z0-9][A-Za-z0-9._-]{0,79}$`)

func SafePath(name string) error {
	if name == "" || strings.Contains(name, `\`) || strings.HasPrefix(name, "/") || path.Clean(name) != name || len(name) > 220 {
		return errors.New("недопустимый путь файла")
	}
	for _, part := range strings.Split(name, "/") {
		if part == "" || part == "." || part == ".." || strings.HasSuffix(part, ".") || strings.HasSuffix(part, " ") || strings.ContainsAny(part, ":<>\"|?*\x00\r\n") {
			return errors.New("недопустимый путь Windows")
		}
		upper := strings.ToUpper(strings.SplitN(part, ".", 2)[0])
		if upper == "CON" || upper == "PRN" || upper == "AUX" || upper == "NUL" || upper == "CONIN$" || upper == "CONOUT$" || (len(upper) == 4 && (strings.HasPrefix(upper, "COM") || strings.HasPrefix(upper, "LPT")) && upper[3] >= '1' && upper[3] <= '9') {
			return errors.New("зарезервированное имя Windows")
		}
	}
	if strings.HasPrefix(strings.ToLower(name), ".la2plus") {
		return errors.New("служебный каталог лаунчера")
	}
	return nil
}
func MutablePath(name string) bool {
	name = strings.ToLower(name)
	return name == "system_ru/option.ini" || name == "system_ru/user.ini" || name == "system_en/option.ini" || name == "system_en/user.ini"
}
func ValidURL(raw string) bool {
	u, e := url.Parse(raw)
	return e == nil && u.Scheme == "https" && u.User == nil && (u.Host == "la2plus.com" || u.Host == "www.la2plus.com") && u.Fragment == ""
}
func validHash(hash string) bool { b, e := hex.DecodeString(hash); return e == nil && len(b) == 32 }
func (m Manifest) Validate() error {
	if m.Schema != 1 || !versionPattern.MatchString(m.Version) || m.Sequence < 1 || !ValidURL(m.Archive.URL) || m.Archive.Size < 1 || !validHash(m.Archive.SHA256) || len(m.Files) == 0 || len(m.Files) > 100000 {
		return errors.New("некорректный манифест обновления")
	}
	seen := map[string]bool{}
	exact := map[string]bool{}
	var total int64
	for _, f := range m.Files {
		if e := SafePath(f.Path); e != nil {
			return fmt.Errorf("%s: %w", f.Path, e)
		}
		key := strings.ToLower(f.Path)
		if seen[key] {
			return errors.New("повторяющийся путь файла")
		}
		seen[key] = true
		exact[f.Path] = true
		if f.Size < 0 || f.Size > 100<<30 || f.Packed < 0 || f.Offset < 0 || f.Offset > m.Archive.Size || f.Packed > m.Archive.Size-f.Offset || !validHash(f.SHA256) || (f.Method != 0 && f.Method != 8) || (f.Mutable && !MutablePath(f.Path)) {
			return errors.New("некорректные данные файла")
		}
		total += f.Size
		if total > 150<<30 {
			return errors.New("слишком большой клиент")
		}
	}
	if len(m.Entrypoints) == 0 {
		return errors.New("не задан запуск игры")
	}
	for lang, p := range m.Entrypoints {
		if (lang != "ru" && lang != "en") || !exact[p] || !strings.EqualFold(path.Base(p), "l2.exe") {
			return errors.New("некорректный запуск игры")
		}
	}
	return nil
}
func Sign(m Manifest, key ed25519.PrivateKey) ([]byte, error) {
	if e := m.Validate(); e != nil {
		return nil, e
	}
	payload, e := json.Marshal(m)
	if e != nil {
		return nil, e
	}
	return json.Marshal(Envelope{base64.StdEncoding.EncodeToString(payload), base64.StdEncoding.EncodeToString(ed25519.Sign(key, payload))})
}
func Verify(data []byte, key ed25519.PublicKey) (Manifest, error) {
	var m Manifest
	var env Envelope
	if len(data) > 32<<20 {
		return m, errors.New("слишком большой манифест")
	}
	if e := json.Unmarshal(data, &env); e != nil {
		return m, e
	}
	payload, e := base64.StdEncoding.DecodeString(env.Payload)
	if e != nil {
		return m, e
	}
	sig, e := base64.StdEncoding.DecodeString(env.Signature)
	if e != nil || !ed25519.Verify(key, payload, sig) {
		return m, errors.New("подпись обновления не подтверждена")
	}
	if e = json.Unmarshal(payload, &m); e != nil {
		return m, e
	}
	return m, m.Validate()
}
