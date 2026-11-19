package update

import (
	"context"
	"errors"
	"fmt"
	"la2plus/launcher/internal/release"
	"os"
	"path/filepath"
)

type Change struct {
	File        release.File
	HadOriginal bool
}
type Journal struct {
	Committed bool
	Changes   []Change
}

func (e *Engine) Apply(ctx context.Context, p Plan) error {
	journal := Journal{}
	for index, f := range p.Missing {
		if !validLocal(ctx, e.stage(f), f) {
			return fmt.Errorf("файл %s не проверен", f.Path)
		}
		name, err := e.target(f.Path)
		if err != nil {
			return err
		}
		info, err := os.Stat(name)
		if err == nil && !info.Mode().IsRegular() {
			return errors.New("на месте файла находится папка")
		}
		if err != nil && !os.IsNotExist(err) {
			return err
		}
		journal.Changes = append(journal.Changes, Change{f, err == nil})
		e.emit(Event{Phase: "Проверка загрузки", File: f.Path, Done: index + 1, Total: len(p.Missing)})
	}
	if err := writeJSON(filepath.Join(e.work(), "journal.json"), journal); err != nil {
		return err
	}
	fail := func(err error) error {
		if recovery := e.Recover(context.Background()); recovery != nil {
			return fmt.Errorf("%v; восстановление: %w", err, recovery)
		}
		return err
	}
	for i, change := range journal.Changes {
		if err := ctx.Err(); err != nil {
			return fail(err)
		}
		name, err := e.target(change.File.Path)
		if err != nil {
			return fail(err)
		}
		backup := filepath.Join(e.work(), "backup", filepath.FromSlash(change.File.Path))
		if err = os.MkdirAll(filepath.Dir(name), 0755); err != nil {
			return fail(err)
		}
		if change.HadOriginal {
			if err = os.MkdirAll(filepath.Dir(backup), 0700); err != nil {
				return fail(err)
			}
			if err = os.Rename(name, backup); err != nil {
				return fail(errors.New("закройте игру и программы, использующие её файлы"))
			}
		}
		if err = os.Rename(e.stage(change.File), name); err != nil {
			return fail(err)
		}
		e.emit(Event{Phase: "Установка обновления", File: change.File.Path, Done: i + 1, Total: len(journal.Changes)})
	}
	if err := e.SaveInstalled(p); err != nil {
		return fail(err)
	}
	journal.Committed = true
	if err := writeJSON(filepath.Join(e.work(), "journal.json"), journal); err != nil {
		return fail(err)
	}
	return e.Recover(ctx)
}
func (e *Engine) Recover(ctx context.Context) error {
	var journal Journal
	name := filepath.Join(e.work(), "journal.json")
	if err := readJSON(name, &journal); err != nil {
		if os.IsNotExist(err) {
			return nil
		}
		return fmt.Errorf("не удалось прочитать журнал восстановления: %w", err)
	}
	if !journal.Committed {
		for i := len(journal.Changes) - 1; i >= 0; i-- {
			change := journal.Changes[i]
			target, err := e.target(change.File.Path)
			if err != nil {
				return err
			}
			backup := filepath.Join(e.work(), "backup", filepath.FromSlash(change.File.Path))
			if err := NoLinks(backup); err != nil {
				return err
			}
			_, backupErr := os.Stat(backup)
			_, targetErr := os.Stat(target)
			if change.HadOriginal && backupErr == nil {
				if targetErr == nil {
					if !validLocal(ctx, target, change.File) {
						return errors.New("файл изменён после сбоя; резервная копия сохранена")
					}
					if err = os.Remove(target); err != nil {
						return err
					}
				}
				if err = os.Rename(backup, target); err != nil {
					return err
				}
			} else if !change.HadOriginal && targetErr == nil {
				if !validLocal(ctx, target, change.File) {
					return errors.New("новый файл изменён после сбоя; требуется проверка")
				}
				if err = os.Remove(target); err != nil {
					return err
				}
			}
		}
	}
	if err := os.RemoveAll(filepath.Join(e.work(), "backup")); err != nil {
		return err
	}
	return os.Remove(name)
}
