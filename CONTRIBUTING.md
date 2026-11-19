# Contributing to LA2PLUS

## Ветки и PR

- Работайте в отдельной ветке от `main`, открывайте PR в `main`.
- Перед PR: `php -l` по изменённым PHP, тесты из `implementation/tests/`,
  `go test -race ./internal/...` в `launcher/` при изменениях лаунчера.
- После изменений в `implementation/public_html/` перегенерируйте манифест:
  `python3 tools/build_portal_release.py` и закоммитьте `implementation/release-manifest.json`.

## Обязательные правила (см. `AGENTS.md`)

- Логотипы — только официальный `implementation/public_html/design/assets/images/logo.png`
  через `BrandLogo::html()`; аспект 3:1, поддержка reduced-motion.
- В кабинете нет PIN операций, карточки персональной скидки и предложений
  установки Android/iOS. Навигация сайта — в текущей вкладке (без `target="_blank"`).
- Новые разделы кабинета регистрируйте в `CabinetSections` (меню + серверная блокировка).
- Секреты — только из env/настроек, никогда в Git. Ключ подписи — только на хостинге.

## Стиль

- `.editorconfig`: UTF-8, LF, 4 пробела (PHP/Go), 2 пробела (JS/CSS/YAML).
- Новые файлы — ASCII-имена без пробелов; старые папки `00–08` не переименовывать.
