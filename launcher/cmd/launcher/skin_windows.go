//go:build windows

package main

import (
	"bytes"
	"fmt"
	"image"
	"image/color"
	_ "image/jpeg"
	"image/png"
	"math"
	"os"
	"path/filepath"
	"strings"
	"unsafe"

	"github.com/lxn/walk"
	. "github.com/lxn/walk/declarative"
	"github.com/lxn/win"
	"golang.org/x/sys/windows"
	"la2plus/launcher/assets"
	"la2plus/launcher/internal/release"
)

// State is shared with the updater; only the UI goroutine mutates these values.
type textValue struct {
	value   string
	changed func()
	tooltip func(string)
}

func (v *textValue) Text() string { return v.value }
func (v *textValue) SetText(s string) {
	v.value = s
	if v.tooltip != nil {
		v.tooltip(s)
	}
	if v.changed != nil {
		v.changed()
	}
}
func (v *textValue) SetToolTipText(s string) {
	if v.tooltip != nil {
		v.tooltip(s)
	}
}

type languageValue struct {
	index   int
	enabled bool
}

func (v *languageValue) CurrentIndex() int { return v.index }
func (v *languageValue) SetEnabled(b bool) { v.enabled = b }

type progressValue struct {
	value   int
	changed func()
}

func (v *progressValue) SetValue(n int) {
	v.value = n
	if v.changed != nil {
		v.changed()
	}
}

type skinButton struct {
	w                                       *walk.CustomWidget
	text                                    string
	enabled, hover, primary, plain, pressed bool
	bounds                                  walk.Rectangle
	click                                   func()
	skin                                    *launcherSkin
}

func (b *skinButton) SetText(s string) {
	b.text = s
	if b.w != nil {
		b.w.Accessibility().SetName(s)
		b.w.Invalidate()
	}
}
func (b *skinButton) SetEnabled(v bool) {
	b.enabled = v
	if !v {
		b.pressed = false
	}
	if b.w != nil {
		b.w.SetEnabled(v)
		b.w.Invalidate()
	}
}
func (b *skinButton) activate() {
	if b.enabled && b.click != nil {
		b.click()
	}
}

type launcherSkin struct {
	a                      *client
	canvas                 *walk.CustomWidget
	background, logo, dark *walk.Bitmap
	buttons                []*skinButton
	fonts                  map[string]*walk.Font
	scale                  float64
	ox, oy                 int
}

var (
	ink   = walk.RGB(10, 15, 23)
	panel = walk.RGB(15, 21, 30)
	gold  = walk.RGB(225, 194, 132)
	muted = walk.RGB(149, 157, 169)
	white = walk.RGB(239, 233, 219)
	edge  = walk.RGB(93, 80, 59)
)

func (s *launcherSkin) invalidate() {
	if s.canvas != nil {
		s.canvas.Invalidate()
	}
}
func (s *launcherSkin) dispose() {
	for _, f := range s.fonts {
		f.Dispose()
	}
	if s.background != nil {
		s.background.Dispose()
	}
	if s.logo != nil {
		s.logo.Dispose()
	}
	if s.dark != nil {
		s.dark.Dispose()
	}
}
func (s *launcherSkin) rect(x, y, w, h int) walk.Rectangle {
	return walk.Rectangle{X: s.ox + int(float64(x)*s.scale), Y: s.oy + int(float64(y)*s.scale), Width: int(float64(w) * s.scale), Height: int(float64(h) * s.scale)}
}
func fill(c *walk.Canvas, r walk.Rectangle, color walk.Color) {
	b, e := walk.NewSolidColorBrush(color)
	if e == nil {
		defer b.Dispose()
		c.FillRectangle(b, r)
	}
}
func line(c *walk.Canvas, r walk.Rectangle, color walk.Color) {
	p, e := walk.NewCosmeticPen(walk.PenSolid, color)
	if e == nil {
		defer p.Dispose()
		c.DrawLine(p, walk.Point{X: r.X, Y: r.Y}, walk.Point{X: r.X + r.Width, Y: r.Y + r.Height})
	}
}
func (s *launcherSkin) font(family string, points int, bold bool) *walk.Font {
	points = int(math.Max(7, math.Round(float64(points)*s.scale)))
	key := fmt.Sprintf("%s/%d/%t", family, points, bold)
	if f := s.fonts[key]; f != nil {
		return f
	}
	style := walk.FontStyle(0)
	if bold {
		style = walk.FontBold
	}
	f, _ := walk.NewFont(family, points, style)
	s.fonts[key] = f
	return f
}
func (s *launcherSkin) text(c *walk.Canvas, text string, x, y, w, h, pt int, color walk.Color, center, bold bool) {
	flags := walk.TextSingleLine | walk.TextVCenter | walk.TextEndEllipsis
	if center {
		flags |= walk.TextCenter
	}
	family := "Segoe UI"
	if bold {
		family = "Georgia"
	}
	c.DrawText(text, s.font(family, pt, bold), color, s.rect(x, y, w, h), flags)
}
func (s *launcherSkin) paint(c *walk.Canvas, _ walk.Rectangle) error {
	b := s.canvas.ClientBounds()
	fill(c, b, ink)
	// The same landscape as the project's main page, without third-party branding.
	c.DrawBitmapWithOpacity(s.background, s.rect(0, 70, 1120, 470), 225)
	fill(c, s.rect(0, 0, 1120, 70), ink)
	fill(c, s.rect(0, 510, 1120, 250), ink)
	// Softly darken the scene at the lower edge without changing the source artwork.
	for i := 0; i < 80; i++ {
		opacity := byte(i * 3)
		c.DrawBitmapWithOpacity(s.dark, s.rect(0, 430+i, 1120, 1), opacity)
	}
	line(c, s.rect(30, 70, 1060, 0), edge)
	c.DrawImageStretched(s.logo, s.rect(365, 108, 390, 130))
	s.text(c, "INTERLUDE  ×20  /  CLASSIC", 350, 247, 420, 25, 10, gold, true, false)
	s.text(c, "Твой мир. Твои правила.", 40, 314, 700, 60, 30, white, false, true)
	s.text(c, "Возвращайся туда, где начинается твоя история.", 43, 380, 670, 27, 11, white, false, false)
	// One real distribution: the project uses Classic for Interlude, not two builds.
	fill(c, s.rect(40, 446, 1040, 72), panel)
	line(c, s.rect(40, 446, 1040, 0), edge)
	s.text(c, "КЛИЕНТ CLASSIC", 58, 455, 360, 29, 14, gold, false, true)
	lang := "Русский"
	if s.a.language.index == 1 {
		lang = "English"
	}
	s.text(c, "Interlude ×20  ·  "+lang, 58, 486, 360, 20, 9, muted, false, false)
	s.text(c, s.a.folder.Text(), 446, 455, 465, 27, 10, white, false, false)
	s.text(c, "Папка игры  ·  настройки игрока сохраняются", 446, 485, 485, 21, 8, muted, false, false)
	s.text(c, s.a.status.Text(), 40, 535, 975, 30, 12, gold, false, false)
	s.text(c, s.a.detail.Text(), 40, 566, 1038, 26, 9, muted, false, false)
	fill(c, s.rect(40, 607, 1040, 5), walk.RGB(48, 53, 63))
	v := s.a.progress.value
	if v < 0 {
		v = 0
	}
	if v > 100 {
		v = 100
	}
	fill(c, s.rect(40, 607, 1040*v/100, 5), gold)
	s.text(c, fmt.Sprintf("%d%%", v), 1010, 530, 70, 30, 10, gold, true, false)
	s.text(c, "LA2PLUS  v"+release.AppVersion+"  ·  "+s.a.version.Text(), 40, 729, 800, 20, 8, muted, false, false)
	return nil
}
func (b *skinButton) paint(c *walk.Canvas, _ walk.Rectangle) error {
	s := b.skin

	q := b.w.ClientBounds()
	fill(c, q, ink)
	active := b.hover || b.w.Focused() || b.pressed
	color := gold
	if !b.enabled {
		color = walk.RGB(93, 96, 103)
	}
	if !b.plain {
		bg := panel
		if b.primary && b.enabled {
			bg = walk.RGB(93, 71, 36)
		}
		if active && b.enabled {
			bg = walk.RGB(66, 52, 34)
		}
		cut := int(15 * s.scale)
		h := q.Height
		w := q.Width
		brush, _ := walk.NewSolidColorBrush(bg)
		if brush != nil {
			defer brush.Dispose()
		}
		for y := 2; brush != nil && y < h-2; y++ {
			dx := cut * int(math.Abs(float64(2*y-h))) / int(math.Max(1, float64(h)))
			c.FillRectangle(brush, walk.Rectangle{X: dx + 2, Y: y, Width: w - 2*dx - 4, Height: 1})
		}
		pen, _ := walk.NewCosmeticPen(walk.PenSolid, color)
		if pen != nil {
			defer pen.Dispose()
			c.DrawPolyline(pen, []walk.Point{{X: cut, Y: 1}, {X: w - cut - 1, Y: 1}, {X: w - 2, Y: h / 2}, {X: w - cut - 1, Y: h - 2}, {X: cut, Y: h - 2}, {X: 1, Y: h / 2}, {X: cut, Y: 1}})
		}
	}
	pt := 11
	if b.primary {
		pt = 18
	}
	if b.plain {
		pt = 10
	}
	c.DrawText(b.text, s.font("Georgia", pt, true), color, q, walk.TextCenter|walk.TextVCenter|walk.TextSingleLine|walk.TextEndEllipsis)
	if b.w.Focused() {
		line(c, walk.Rectangle{X: 12, Y: q.Height - 5, Width: q.Width - 24, Height: 0}, gold)
	}
	return nil
}
func (s *launcherSkin) button(label string, r walk.Rectangle, primary, plain bool, fn func()) (*skinButton, error) {
	b := &skinButton{text: label, enabled: true, primary: primary, plain: plain, bounds: r, click: fn, skin: s}
	err := (CustomWidget{AssignTo: &b.w, Name: label, Style: win.WS_TABSTOP | win.WS_CLIPSIBLINGS, PaintMode: PaintBuffered, InvalidatesOnResize: true,
		Accessibility: Accessibility{Name: label, Role: AccRolePushbutton, DefaultAction: "Нажать"},
		Paint:         b.paint, OnMouseDown: func(x, y int, button walk.MouseButton) {
			if button == walk.LeftButton {
				b.w.SetFocus()
				b.pressed = true
				win.SetCapture(b.w.Handle())
				b.w.Invalidate()
			}
		},
		OnMouseUp: func(x, y int, button walk.MouseButton) {
			q := b.w.ClientBounds()
			pressed := b.pressed
			b.pressed = false
			win.ReleaseCapture()
			b.w.Invalidate()
			if pressed && button == walk.LeftButton && x >= 0 && y >= 0 && x < q.Width && y < q.Height {
				b.activate()
			}
		},
		OnKeyDown: func(key walk.Key) {
			if b.enabled && (key == walk.KeySpace || key == walk.KeyReturn) {
				b.pressed = true
				b.w.Invalidate()
			}
		}, OnKeyUp: func(key walk.Key) {
			if b.pressed && (key == walk.KeySpace || key == walk.KeyReturn) {
				b.pressed = false
				b.w.Invalidate()
				b.activate()
			}
		},
		OnMouseMove: func(x, y int, button walk.MouseButton) {
			s.setHover(b)
		},
	}).Create(NewBuilder(s.a.mw))
	if err != nil {
		return nil, err
	}

	if ok, _, e := setButtonSubclass.Call(uintptr(b.w.Handle()), buttonSubclassProc, 1, 0); ok == 0 {
		return nil, fmt.Errorf("keyboard subclass: %v", e)
	}
	b.w.FocusedChanged().Attach(func() {
		if !b.w.Focused() {
			b.pressed = false
		}
		b.w.Invalidate()
	})
	s.buttons = append(s.buttons, b)
	return b, nil
}
func (s *launcherSkin) setHover(active *skinButton) {
	for _, b := range s.buttons {
		v := b == active
		if b.hover != v {
			b.hover = v
			b.w.Invalidate()
		}
	}
}
func (s *launcherSkin) layout() {
	if s.canvas == nil {
		return
	}
	b := s.a.mw.ClientBounds()
	s.scale = math.Min(float64(b.Width)/1120, float64(b.Height)/760)
	s.ox = (b.Width - int(1120*s.scale)) / 2
	s.oy = (b.Height - int(760*s.scale)) / 2
	s.canvas.SetBounds(b)
	for _, button := range s.buttons {
		r := button.bounds
		button.w.SetBounds(s.rect(r.X, r.Y, r.Width, r.Height))
	}
	s.invalidate()
}
func openURL(owner *walk.MainWindow, url string) {
	if !win.ShellExecute(owner.Handle(), windows.StringToUTF16Ptr("open"), windows.StringToUTF16Ptr(url), nil, nil, win.SW_SHOWNORMAL) {
		walk.MsgBox(owner, "LA2PLUS", "Не удалось открыть браузер.", walk.MsgBoxIconError)
	}
}
func (a *client) buildSkin() error {
	s := &launcherSkin{a: a, fonts: map[string]*walk.Font{}, scale: 1}
	a.skin = s
	decode := func(data []byte) (*walk.Bitmap, error) {
		img, _, e := image.Decode(bytes.NewReader(data))
		if e != nil {
			return nil, e
		}
		return walk.NewBitmapFromImage(img)
	}
	var err error
	if s.background, err = decode(assets.Background); err != nil {
		return err
	}
	if s.logo, err = decode(assets.Logo); err != nil {
		return err
	}
	pixel := image.NewRGBA(image.Rect(0, 0, 1, 1))
	pixel.Set(0, 0, color.RGBA{10, 15, 23, 255})
	s.dark, err = walk.NewBitmapFromImage(pixel)
	if err != nil {
		return err
	}
	a.folder = &textValue{value: a.prefs.Folder, changed: s.invalidate}
	a.status = &textValue{value: "Готов к проверке", changed: s.invalidate}
	a.detail = &textValue{value: "Выберите папку и загрузите сборку LA2PLUS", changed: s.invalidate, tooltip: func(t string) {
		if s.canvas != nil {
			s.canvas.SetToolTipText(t)
		}
	}}
	a.version = &textValue{value: "Windows 10/11 x64", changed: s.invalidate}
	a.progress = &progressValue{changed: s.invalidate}
	a.language = &languageValue{enabled: true}
	if a.prefs.Language == "en" {
		a.language.index = 1
	}
	err = (MainWindow{AssignTo: &a.mw, Title: "LA2PLUS — игровой клиент", Layout: absoluteLayoutFactory{}, Visible: false, Size: Size{Width: 1120, Height: 760}, MinSize: Size{Width: 960, Height: 650}, Background: SolidColorBrush{Color: ink}, Font: Font{Family: "Segoe UI", PointSize: 10}, OnSizeChanged: s.layout}).Create()
	if err != nil {
		return err
	}
	// Keep native system menu/Alt+F4 and a resize border; use a project-drawn title bar.
	style := win.GetWindowLong(a.mw.Handle(), win.GWL_STYLE)
	win.SetWindowLong(a.mw.Handle(), win.GWL_STYLE, style&^win.WS_CAPTION)
	win.SetWindowPos(a.mw.Handle(), 0, 0, 0, 0, 0, win.SWP_NOMOVE|win.SWP_NOSIZE|win.SWP_NOZORDER|win.SWP_FRAMECHANGED)
	err = (CustomWidget{AssignTo: &s.canvas, Style: win.WS_CLIPSIBLINGS, Paint: s.paint, PaintMode: PaintBuffered, InvalidatesOnResize: true, OnMouseMove: func(x, y int, b walk.MouseButton) { s.setHover(nil) }, OnMouseDown: func(x, y int, b walk.MouseButton) {
		if b == walk.LeftButton && y < s.oy+int(70*s.scale) {
			win.ReleaseCapture()
			win.SendMessage(a.mw.Handle(), win.WM_NCLBUTTONDOWN, win.HTCAPTION, 0)
		}
	}}).Create(NewBuilder(a.mw))
	if err != nil {
		return err
	}
	add := func(text string, x, y, w, h int, primary, plain bool, fn func()) *skinButton {
		if err != nil {
			return nil
		}
		var b *skinButton
		b, err = s.button(text, walk.Rectangle{X: x, Y: y, Width: w, Height: h}, primary, plain, fn)
		return b
	}
	add("ГЛАВНАЯ", 30, 17, 110, 36, false, true, func() { openURL(a.mw, "https://la2plus.com/") })
	add("ФОРУМ", 150, 17, 100, 36, false, true, func() { openURL(a.mw, "https://la2plus.com/forum/") })
	add("КАБИНЕТ", 265, 17, 120, 36, false, true, func() { openURL(a.mw, "https://la2plus.com/cabinet/") })
	add("ЯЗЫК ИГРЫ: RU / EN", 725, 17, 215, 36, false, true, a.settingsDialog)
	add("—", 968, 15, 48, 40, false, false, func() { win.ShowWindow(a.mw.Handle(), win.SW_MINIMIZE) })
	add("×", 1028, 15, 48, 40, false, false, func() { a.mw.Close() })
	a.browse = add("ПАПКА", 944, 463, 116, 35, false, false, a.choose)
	a.repair = add("ПОЛНОЕ ОБНОВЛЕНИЕ", 40, 638, 285, 62, false, false, func() { a.check(true, true) })
	a.play = add("НАЧАТЬ ИГРУ", 345, 628, 430, 82, true, false, a.start)
	a.action = add("БЫСТРОЕ ОБНОВЛЕНИЕ", 795, 638, 285, 62, false, false, func() { a.check(true, false) })
	a.stop = add("ПАУЗА", 830, 725, 100, 26, false, true, func() {
		if a.cancel != nil {
			a.cancel()
		}
	})
	add("НАСТРОЙКИ", 942, 725, 138, 26, false, true, a.settingsDialog)
	if err != nil {
		return err
	}
	a.action.w.SetToolTipText("Проверить изменённые файлы и скачать обновления. Продолжает прерванную загрузку.")
	a.repair.w.SetToolTipText("Проверить SHA-256 всех файлов и восстановить повреждённые. Настройки игрока сохраняются.")
	a.controls(false)
	s.fitMonitor()
	s.layout()
	return nil
}
func (a *client) settingsDialog() {
	if a.busy {
		walk.MsgBox(a.mw, "Настройки", "Сначала приостановите загрузку.", walk.MsgBoxIconInformation)
		return
	}
	var d *walk.Dialog
	var lang *walk.ComboBox
	var auto *walk.CheckBox
	automatic := a.prefs.AutoUpdate == nil || *a.prefs.AutoUpdate
	_, err := (Dialog{AssignTo: &d, Title: "Настройки LA2PLUS", MinSize: Size{Width: 510, Height: 280}, Layout: VBox{Spacing: 12, Margins: Margins{Left: 20, Top: 20, Right: 20, Bottom: 20}}, Children: []Widget{
		Label{Text: "Язык игрового клиента"}, ComboBox{AssignTo: &lang, Model: []string{"Русский", "English"}, CurrentIndex: a.language.index},
		CheckBox{AssignTo: &auto, Text: "Автоматически обновлять установленную игру при запуске", Checked: automatic},
		Label{Text: "Папка игры: " + a.folder.Text()},
		Label{Text: "Полное обновление проверяет все файлы. Быстрое — только изменения.\r\nOption.ini и User.ini сохраняются. Для обновления закройте игру."},
		Composite{Layout: HBox{}, Children: []Widget{PushButton{Text: "Открыть папку", OnClicked: func() {
			folder := a.folder.Text()
			if _, e := os.Stat(folder); e != nil {
				walk.MsgBox(d, "Папка игры", "Папка пока не создана. Выберите её на главном экране.", walk.MsgBoxIconInformation)
				return
			}
			win.ShellExecute(d.Handle(), windows.StringToUTF16Ptr("open"), windows.StringToUTF16Ptr(filepath.Clean(folder)), nil, nil, win.SW_SHOWNORMAL)
		}}, HSpacer{}, PushButton{Text: "Сохранить", OnClicked: func() {
			a.language.index = lang.CurrentIndex()
			v := auto.Checked()
			a.prefs.AutoUpdate = &v
			a.save()
			a.skin.invalidate()
			d.Accept()
		}}, PushButton{Text: "Отмена", OnClicked: func() { d.Cancel() }}}},
	}}).Run(a.mw)
	if err != nil {
		walk.MsgBox(a.mw, "Настройки", err.Error(), walk.MsgBoxIconError)
	}
}
func (s *launcherSkin) proof(path string) error {
	if s.a.play.enabled || s.a.stop.enabled {
		return fmt.Errorf("unsafe initial action state")
	}
	count := 0
	b := s.a.browse
	if win.SendMessage(b.w.Handle(), win.WM_GETDLGCODE, win.VK_RETURN, 0)&win.DLGC_WANTMESSAGE == 0 {
		return fmt.Errorf("Enter not delivered to button")
	}
	if win.SendMessage(b.w.Handle(), win.WM_GETDLGCODE, win.VK_TAB, 0)&win.DLGC_WANTMESSAGE != 0 {
		return fmt.Errorf("Tab navigation swallowed")
	}
	original := b.click
	b.click = func() { count++ }
	win.SendMessage(b.w.Handle(), win.WM_KEYDOWN, win.VK_SPACE, 0)
	win.SendMessage(b.w.Handle(), win.WM_KEYUP, win.VK_SPACE, 0)
	win.SendMessage(b.w.Handle(), win.WM_KEYDOWN, win.VK_RETURN, 0)
	win.SendMessage(b.w.Handle(), win.WM_KEYUP, win.VK_RETURN, 0)
	b.SetEnabled(false)
	win.SendMessage(b.w.Handle(), win.WM_KEYDOWN, win.VK_SPACE, 0)
	win.SendMessage(b.w.Handle(), win.WM_KEYUP, win.VK_SPACE, 0)
	b.SetEnabled(true)
	b.click = original
	if count != 2 {
		return fmt.Errorf("keyboard/disabled activation: %d", count)
	}
	// Hosted Windows runners have no interactive desktop: screen BitBlt is black.
	// Render the same native GDI paint callbacks into a bitmap for visual inspection.
	img, err := s.renderPreview()
	if err != nil {
		return err
	}
	out, err := os.Create(path + ".png")
	if err != nil {
		return err
	}
	err = png.Encode(out, img)
	out.Close()
	if err != nil {
		return err
	}
	names := []string{}
	for _, b := range s.buttons {
		if b.w == nil || b.w.Bounds().Width <= 0 {
			return fmt.Errorf("unlaid-out control: %s", b.text)
		}
		names = append(names, b.text)
	}
	return os.WriteFile(path, []byte("LA2PLUS native skinned GUI initialized\n"+strings.Join(names, "\n")), 0600)
}

// Walk requires a layout object even when the scene positions its children itself.
// It must not asynchronously move the controls after a resize.
type absoluteLayoutFactory struct{}

func (absoluteLayoutFactory) Create() (walk.Layout, error) { return &absoluteLayout{}, nil }

type absoluteLayout struct {
	walk.LayoutBase
	container walk.Container
}

func (l *absoluteLayout) Container() walk.Container     { return l.container }
func (l *absoluteLayout) SetContainer(c walk.Container) { l.container = c }
func (l *absoluteLayout) CreateLayoutItem(*walk.LayoutContext) walk.ContainerLayoutItem {
	return &absoluteLayoutItem{}
}

type absoluteLayoutItem struct{ walk.ContainerLayoutItemBase }

func (*absoluteLayoutItem) LayoutFlags() walk.LayoutFlags {
	return walk.GrowableHorz | walk.GrowableVert | walk.GreedyHorz | walk.GreedyVert
}
func (*absoluteLayoutItem) MinSize() walk.Size                     { return walk.Size{} }
func (*absoluteLayoutItem) MinSizeForSize(walk.Size) walk.Size     { return walk.Size{} }
func (*absoluteLayoutItem) HeightForWidth(int) int                 { return 0 }
func (*absoluteLayoutItem) PerformLayout() []walk.LayoutResultItem { return nil }
func (s *launcherSkin) fitMonitor() {
	info := win.MONITORINFO{}
	info.CbSize = uint32(unsafe.Sizeof(info))
	if !win.GetMonitorInfo(win.MonitorFromWindow(s.a.mw.Handle(), win.MONITOR_DEFAULTTONEAREST), &info) {
		return
	}
	area := info.RcWork
	r := s.a.mw.BoundsPixels()
	w, h := r.Width, r.Height
	availableW, availableH := int(area.Right-area.Left)-24, int(area.Bottom-area.Top)-24
	factor := math.Min(1, math.Min(float64(availableW)/float64(w), float64(availableH)/float64(h)))
	if factor < 1 {
		w = int(float64(w) * factor)
		h = int(float64(h) * factor)
		s.a.mw.SetMinMaxSizePixels(walk.Size{Width: w, Height: h}, walk.Size{})
	}
	s.a.mw.SetBoundsPixels(walk.Rectangle{X: int(area.Left) + (int(area.Right-area.Left)-w)/2, Y: int(area.Top) + (int(area.Bottom-area.Top)-h)/2, Width: w, Height: h})
}

var (
	buttonControls       = windows.NewLazySystemDLL("comctl32.dll")
	setButtonSubclass    = buttonControls.NewProc("SetWindowSubclass")
	defButtonSubclass    = buttonControls.NewProc("DefSubclassProc")
	removeButtonSubclass = buttonControls.NewProc("RemoveWindowSubclass")
	buttonSubclassProc   uintptr
)

func init() {
	buttonSubclassProc = windows.NewCallback(func(hwnd, msg, w, l, id, data uintptr) uintptr {
		if msg == win.WM_GETDLGCODE && (w == win.VK_RETURN || w == win.VK_SPACE) {
			return win.DLGC_WANTMESSAGE | win.DLGC_BUTTON
		}
		if msg == win.WM_NCDESTROY {
			removeButtonSubclass.Call(hwnd, buttonSubclassProc, id)
		}
		r, _, _ := defButtonSubclass.Call(hwnd, msg, w, l)
		return r
	})
}

func (s *launcherSkin) renderPreview() (*image.RGBA, error) {
	bounds := s.canvas.ClientBounds()
	bmp, err := walk.NewBitmap(walk.Size{Width: bounds.Width, Height: bounds.Height})
	if err != nil {
		return nil, err
	}
	defer bmp.Dispose()
	c, err := walk.NewCanvasFromImage(bmp)
	if err != nil {
		return nil, err
	}
	defer c.Dispose()
	if err = s.paint(c, bounds); err != nil {
		return nil, err
	}
	for _, b := range s.buttons {
		r := b.w.ClientBounds()
		part, e := walk.NewBitmap(walk.Size{Width: r.Width, Height: r.Height})
		if e != nil {
			return nil, e
		}
		bc, e := walk.NewCanvasFromImage(part)
		if e != nil {
			part.Dispose()
			return nil, e
		}
		e = b.paint(bc, r)
		bc.Dispose()
		if e == nil {
			e = c.DrawImage(part, walk.Point{X: b.w.Bounds().X, Y: b.w.Bounds().Y})
		}
		part.Dispose()
		if e != nil {
			return nil, e
		}
	}
	return bmp.ToImage()
}
