## Что изменено

## Проверки

- [ ] `php -l` по изменённым PHP / `go test -race ./internal/...` (если трогал `launcher/`)
- [ ] Тесты из `implementation/tests/` прогнаны (или указано, почему нет)
- [ ] `python3 tools/build_portal_release.py` + `release-manifest.json` обновлён (если трогал `implementation/public_html/`)
- [ ] Правила `AGENTS.md` соблюдены (бренд, без PIN/скидки/PWA-промптов, текущая вкладка)
- [ ] Секреты не попали в diff (`.env`, ключи, `credentials.json`, `work/`)
