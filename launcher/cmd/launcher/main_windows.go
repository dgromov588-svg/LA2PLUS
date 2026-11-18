//go:build windows

package main

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"github.com/lxn/walk"
	. "github.com/lxn/walk/declarative"
	"golang.org/x/sys/windows"
	"image/png"
	"la2plus/launcher/assets"
	"la2plus/launcher/internal/platform"
	"la2plus/launcher/internal/release"
	"la2plus/launcher/internal/update"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"sync/atomic"
	"time"
)

type preferences struct{ Folder, Language string }
type client struct {
	mw                                 *walk.MainWindow
	folder                             *walk.LineEdit
	language                           *walk.ComboBox
	status, detail, version            *walk.Label
	progress                           *walk.ProgressBar
	action, repair, play, stop, browse *walk.PushButton
	cancel                             context.CancelFunc
	busy                               bool
	closing                            bool
	ready                              bool
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
				a.action.SetText("Продолжить / повторить")
				if errors.Is(err, context.Canceled) {
					a.status.SetText("Загрузка приостановлена")
					a.detail.SetText("Проверенные файлы сохранены. Нажмите «Продолжить».")
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
				a.action.SetText("Проверить обновления")
				a.status.SetText("Клиент готов к игре")
				a.detail.SetText("Проверка завершена. Выберите язык клиента и нажмите «Играть».")
				a.progress.SetValue(100)
			} else {
				a.action.SetText("Скачать / обновить клиент")
				a.status.SetText(fmt.Sprintf("Доступно файлов: %d", len(p.Missing)))
				a.detail.SetText("К загрузке: " + size(p.DownloadBytes) + " · требуется место: " + size(p.DiskBytes))
				if !install && !full && p.State.Version != "" && !platform.GameRunning(a.prefs.Folder) {
					a.check(true, false)
				}
			}
		})
	}()
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
	image, err := png.Decode(bytes.NewReader(assets.Logo))
	if err != nil {
		return
	}
	logo, err := walk.NewBitmapFromImage(image)
	if err != nil {
		return
	}
	defer logo.Dispose()
	dark := walk.RGB(11, 16, 24)
	gold := walk.RGB(222, 194, 134)
	light := walk.RGB(202, 209, 220)
	index := 0
	if a.prefs.Language == "en" {
		index = 1
	}
	err = (MainWindow{AssignTo: &a.mw, Title: "LA2PLUS Launcher", MinSize: Size{760, 530}, Size: Size{920, 650}, Font: Font{Family: "Segoe UI", PointSize: 10}, Background: SolidColorBrush{Color: dark}, Layout: VBox{Margins: Margins{Left: 30, Top: 22, Right: 30, Bottom: 24}, Spacing: 14}, Children: []Widget{
		Composite{Background: SolidColorBrush{Color: dark}, Layout: HBox{MarginsZero: true, Spacing: 24}, Children: []Widget{ImageView{Image: logo, Mode: ImageViewModeShrink, MinSize: Size{300, 100}, MaxSize: Size{350, 116}}, HSpacer{}, Label{Text: "INTERLUDE ×20\r\nКлиент Classic", TextColor: gold, Font: Font{Family: "Segoe UI", PointSize: 12}}}},
		Label{Text: "Твой мир. Твои правила.", TextColor: gold, Font: Font{Family: "Georgia", PointSize: 24}},
		Label{Text: "Скачивание сборки, проверка файлов и обновления проекта", TextColor: light},
		Composite{Background: SolidColorBrush{Color: dark}, Layout: HBox{MarginsZero: true, Spacing: 12}, Children: []Widget{LineEdit{AssignTo: &a.folder, Text: a.prefs.Folder, ReadOnly: true}, PushButton{AssignTo: &a.browse, Text: "Выбрать папку", OnClicked: a.choose}}},
		Composite{Background: SolidColorBrush{Color: dark}, Layout: HBox{MarginsZero: true, Spacing: 12}, Children: []Widget{Label{Text: "Язык игрового клиента", TextColor: light}, ComboBox{AssignTo: &a.language, Model: []string{"Русский", "English"}, CurrentIndex: index, MinSize: Size{145, 25}, OnCurrentIndexChanged: func() {
			if a.folder != nil {
				a.save()
			}
		}}, HSpacer{}, Label{AssignTo: &a.version, Text: "LA2PLUS Launcher " + release.AppVersion, TextColor: gold}}},
		VSpacer{}, Label{AssignTo: &a.status, Text: "Готов к проверке", TextColor: gold, Font: Font{Family: "Segoe UI", PointSize: 13}}, Label{AssignTo: &a.detail, Text: "", TextColor: light, EllipsisMode: EllipsisEnd, MinSize: Size{0, 24}}, ProgressBar{AssignTo: &a.progress, MinValue: 0, MaxValue: 100},
		Composite{Background: SolidColorBrush{Color: dark}, Layout: HBox{MarginsZero: true, Spacing: 12}, Children: []Widget{PushButton{AssignTo: &a.action, Text: "Проверить обновления", MinSize: Size{210, 38}, OnClicked: func() { a.check(true, false) }}, PushButton{AssignTo: &a.repair, Text: "Проверить все файлы", MinSize: Size{180, 38}, OnClicked: func() { a.check(false, true) }}, PushButton{AssignTo: &a.stop, Text: "Пауза", Enabled: false, OnClicked: func() {
			if a.cancel != nil {
				a.cancel()
			}
		}}, HSpacer{}, PushButton{AssignTo: &a.play, Text: "Играть", MinSize: Size{130, 42}, Enabled: false, OnClicked: a.start}}},
		Label{Text: "Windows 10/11 x64 · Обновления установленной сборки загружаются автоматически", TextColor: walk.RGB(143, 154, 172)},
	}}).Create()
	if err != nil {
		walk.MsgBox(nil, "LA2PLUS", err.Error(), walk.MsgBoxIconError)
		return
	}
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
		if len(os.Args) > 2 {
			_ = os.WriteFile(os.Args[2], []byte("LA2PLUS GUI initialized"), 0600)
		}
		a.mw.Dispose()
		return
	}
	a.mw.Show()
	go func() { time.Sleep(200 * time.Millisecond); a.mw.Synchronize(func() { a.check(false, false) }) }()
	a.mw.Run()
}
