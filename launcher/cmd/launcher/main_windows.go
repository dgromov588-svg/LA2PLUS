//go:build windows

package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"github.com/lxn/walk"
	"golang.org/x/sys/windows"
	"la2plus/launcher/internal/platform"
	"la2plus/launcher/internal/update"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"sync/atomic"
	"time"
)

type preferences struct {
	Folder, Language string
	AutoUpdate       *bool `json:",omitempty"`
}
type client struct {
	mw                                 *walk.MainWindow
	skin                               *launcherSkin
	folder                             *textValue
	language                           *languageValue
	status, detail, version            *textValue
	progress                           *progressValue
	action, repair, play, stop, browse *skinButton
	cancel                             context.CancelFunc
	busy, closing, ready               bool
	engine                             *update.Engine
	plan                               update.Plan
	prefs                              preferences
	settings                           string
	lastUI                             atomic.Int64
}

func size(n int64) string {
	units := []string{"Б", "КБ", "МБ", "ГБ"}
	value := float64(n)
	i := 0
	for value >= 1024 && i < 3 {
		value /= 1024
		i++
	}
	return fmt.Sprintf("%.1f %s", value, units[i])
}
func (a *client) save() {
	a.prefs.Folder = a.folder.Text()
	if a.language.CurrentIndex() == 1 {
		a.prefs.Language = "en"
	} else {
		a.prefs.Language = "ru"
	}
	b, _ := json.Marshal(a.prefs)
	_ = os.MkdirAll(filepath.Dir(a.settings), 0700)
	_ = os.WriteFile(a.settings, b, 0600)
}
func (a *client) controls(busy bool) {
	a.busy = busy
	a.browse.SetEnabled(!busy)
	a.action.SetEnabled(!busy)
	a.repair.SetEnabled(!busy)
	a.language.SetEnabled(!busy)
	a.stop.SetEnabled(busy)
	a.play.SetEnabled(!busy && a.ready)
}
func (a *client) notice(v update.Event) {
	now := time.Now().UnixMilli()
	previous := a.lastUI.Load()
	if now-previous < 150 && v.Done != v.Total {
		return
	}
	a.lastUI.Store(now)
	a.mw.Synchronize(func() {
		a.status.SetText(v.Phase)
		if v.Retrying {
			a.detail.SetText(v.File + " · загрузка продолжится автоматически")
			return
		}
		a.detail.SetText(v.File)
		percent := 0
		if v.TotalBytes > 0 {
			percent = int(v.Bytes * 100 / v.TotalBytes)
			a.detail.SetText(fmt.Sprintf("%s из %s · %s", size(v.Bytes), size(v.TotalBytes), v.File))
		} else if v.Total > 0 {
			percent = v.Done * 100 / v.Total
		}
		if percent > 100 {
			percent = 100
		}
		a.progress.SetValue(percent)
	})
}
func (a *client) check(install, full bool) {
	if a.busy {
		return
	}
	a.save()
	a.ready = false
	a.controls(true)
	a.status.SetText("Получение списка обновлений…")
	a.detail.SetText("Проверяем защищённое соединение и подпись проекта")
	a.progress.SetValue(0)
	ctx, cancel := context.WithCancel(context.Background())
	a.cancel = cancel
	a.engine = update.New(a.prefs.Folder)
	a.engine.Notify = a.notice
	go func() {
		m, err := a.engine.Fetch(ctx)
		var p update.Plan
		if err == nil {
			p, err = a.engine.Scan(ctx, m, full)
		}
		if err == nil && install && len(p.Missing) > 0 {
			if platform.GameRunning(a.prefs.Folder) {
				err = errors.New("закройте игру перед обновлением")
			} else {
				free, er := platform.FreeBytes(a.prefs.Folder)
				if er != nil {
					err = er
				} else if free < uint64(p.DiskBytes) {
					err = fmt.Errorf("нужно свободное место: %s; доступно %s", size(p.DiskBytes), size(int64(free)))
				}
			}
			if err == nil {
				a.mw.Synchronize(func() { a.progress.SetValue(0); a.status.SetText("Загрузка клиента") })
				err = a.engine.Download(ctx, p)
			}
			if err == nil && platform.GameRunning(a.prefs.Folder) {
				err = errors.New("закройте игру и повторите обновление; файлы уже загружены")
			}
			if err == nil {
				err = a.engine.Apply(ctx, p)
			}
			if err == nil {
				p.Missing = nil
			}
		}
		a.mw.Synchronize(func() {
			defer cancel()
			a.plan = p
			a.controls(false)
			if a.closing {
				a.mw.Close()
				return
			}
			if err != nil {
				a.ready = false
				a.play.SetEnabled(false)
				a.action.SetText("ПРОДОЛЖИТЬ ЗАГРУЗКУ")
				if errors.Is(err, context.Canceled) {
					a.status.SetText("Загрузка приостановлена")
					a.detail.SetText("Проверенные файлы сохранены. Нажмите «Продолжить загрузку».")
				} else {
					a.status.SetText("Не удалось завершить операцию")
					a.detail.SetText(err.Error())
					a.detail.SetToolTipText(err.Error())
				}
				return
			}
			a.version.SetText("Версия сборки: " + m.Version)
			if len(p.Missing) == 0 {
				a.ready = true
				a.play.SetEnabled(true)
				a.action.SetText("БЫСТРОЕ ОБНОВЛЕНИЕ")
				a.status.SetText("Клиент готов к игре")
				a.detail.SetText("Проверка завершена. Выберите язык клиента и нажмите «Начать игру».")
				a.progress.SetValue(100)
			} else {
				a.showAvailable(p)
				if !install && !full && (a.prefs.AutoUpdate == nil || *a.prefs.AutoUpdate) && p.State.Version != "" && !platform.GameRunning(a.prefs.Folder) {
					a.check(true, false)
				}
			}
		})
	}()
}
func (a *client) showAvailable(p update.Plan) {
	a.ready = false
	a.play.SetEnabled(false)
	a.progress.SetValue(0)
	a.action.SetText("СКАЧАТЬ КЛИЕНТ")
	a.status.SetText(fmt.Sprintf("Готов к загрузке: %d файлов", len(p.Missing)))
	a.detail.SetText("К загрузке: " + size(p.DownloadBytes) + " · требуется место: " + size(p.DiskBytes))
}
func (a *client) choose() {
	if a.busy {
		return
	}
	dialog := walk.FileDialog{Title: "Выберите папку игры LA2PLUS", InitialDirPath: a.prefs.Folder}
	selected, err := dialog.ShowBrowseFolder(a.mw)
	if err != nil {
		walk.MsgBox(a.mw, "Папка игры", err.Error(), walk.MsgBoxIconError)
		return
	}
	if !selected {
		return
	}
	candidate := filepath.Clean(dialog.FilePath)
	exe, _ := os.Executable()
	if strings.EqualFold(candidate, filepath.Dir(exe)) {
		walk.MsgBox(a.mw, "Папка игры", "Выберите отдельную папку для игровых файлов.", walk.MsgBoxIconWarning)
		return
	}
	a.folder.SetText(candidate)
	a.save()
	a.check(false, false)
}
func (a *client) start() {
	if !a.ready || a.busy {
		return
	}
	lang := "ru"
	if a.language.CurrentIndex() == 1 {
		lang = "en"
	}
	entry, ok := a.plan.Manifest.Entrypoints[lang]
	if !ok {
		walk.MsgBox(a.mw, "Запуск", "Этот язык не входит в сборку.", walk.MsgBoxIconWarning)
		return
	}
	path := filepath.Join(a.prefs.Folder, filepath.FromSlash(entry))
	if err := update.NoLinks(path); err != nil {
		walk.MsgBox(a.mw, "Запуск", err.Error(), walk.MsgBoxIconError)
		return
	}
	expected := ""
	for _, f := range a.plan.Manifest.Files {
		if f.Path == entry {
			expected = f.SHA256
			break
		}
	}
	hash, err := update.HashFile(context.Background(), path)
	if err != nil || hash != expected {
		a.ready = false
		a.play.SetEnabled(false)
		a.check(true, true)
		return
	}
	command := exec.Command(path)
	command.Dir = filepath.Dir(path)
	if err = command.Start(); err != nil {
		walk.MsgBox(a.mw, "Не удалось запустить игру", err.Error(), walk.MsgBoxIconError)
		return
	}
	a.status.SetText("Игра запущена")
	a.save()
}
func main() {
	runtimeMutex, err := windows.CreateMutex(nil, false, windows.StringToUTF16Ptr("Local\\LA2PLUSLauncher-v1"))
	if err == windows.ERROR_ALREADY_EXISTS {
		return
	}
	if err == nil {
		defer windows.CloseHandle(runtimeMutex)
	}
	configDir, err := os.UserConfigDir()
	if err != nil {
		return
	}
	home, _ := os.UserHomeDir()
	a := &client{settings: filepath.Join(configDir, "LA2PLUS", "launcher.json"), prefs: preferences{Folder: filepath.Join(home, "Games", "LA2PLUS"), Language: "ru"}}
	if b, e := os.ReadFile(a.settings); e == nil {
		_ = json.Unmarshal(b, &a.prefs)
	}
	if a.prefs.Folder == "" {
		a.prefs.Folder = filepath.Join(home, "Games", "LA2PLUS")
	}
	if err = a.buildSkin(); err != nil {
		walk.MsgBox(nil, "LA2PLUS", err.Error(), walk.MsgBoxIconError)
		return
	}
	defer a.skin.dispose()
	if icon, e := walk.NewIconFromResourceId(2); e == nil {
		defer icon.Dispose()
		a.mw.SetIcon(icon)
	}
	a.mw.Closing().Attach(func(canceled *bool, reason walk.CloseReason) {
		if a.busy {
			*canceled = true
			if walk.MsgBox(a.mw, "LA2PLUS", "Приостановить загрузку и закрыть лаунчер?", walk.MsgBoxYesNo|walk.MsgBoxIconQuestion) == walk.DlgCmdYes {
				a.closing = true
				a.cancel()
			}
		} else {
			a.save()
		}
	})
	if len(os.Args) > 1 && os.Args[1] == "--self-test" {
		if len(os.Args) > 3 && os.Args[3] == "compact" {
			a.mw.SetSize(walk.Size{Width: 960, Height: 650})
		}
		a.mw.Show()
		go func() {
			time.Sleep(600 * time.Millisecond)
			a.mw.Synchronize(func() {
				if len(os.Args) > 2 {
					if e := a.skin.proof(os.Args[2]); e != nil {
						_ = os.WriteFile(os.Args[2]+".error", []byte(e.Error()), 0600)
					}
				}
				a.mw.Dispose()
			})
		}()
		a.mw.Run()
		return
	}
	a.mw.Show()
	go func() { time.Sleep(200 * time.Millisecond); a.mw.Synchronize(func() { a.check(false, false) }) }()
	a.mw.Run()
}
