#!/usr/bin/env python3
"""
LA2PLUS • сборка и деплой (расширенная версия)

Что делает:
  1) Lint: py_compile для .py, php -l для .php (если доступен)
  2) Собирает deploy/files в ZIP-пакет dist/la2plus-panel-v2.zip
  3) Включает PWA-файлы (service-worker.js, manifest.json, offline.html, pwa.css, иконки)
  4) Генерирует checksums.sha256 для всех файлов пакета
  5) Подставляет base64 пакета в шаблон pull-packages.php → panel-deploy-all.php
  6) Копирует курьер в deploy/ и dist/
  7) Пишет build_report.txt с кратким итогом

Запуск:
  python3 tools/build-deploy-all.py [--skip-lint] [--verbose]

Переменные среды (опционально):
  LA2PLUS_PHP  — путь к php-исполнителю (по умолчанию «php»)
  LA2PLUS_DIST — папка вывода (по умолчанию «dist/»)
"""
import argparse
import base64
import hashlib
import os
import pathlib
import py_compile
import subprocess
import sys
import zipfile
import datetime

# ── Пути ──────────────────────────────────────────────────────────────────────
LIVE     = pathlib.Path(__file__).resolve().parent.parent   # …/live
FILES    = LIVE / 'deploy' / 'files'
TEMPLATE = LIVE / 'deploy' / 'pull-packages.php'
DIST     = pathlib.Path(os.environ.get('LA2PLUS_DIST', str(LIVE / 'dist')))
PKG      = DIST / 'la2plus-panel-v2.zip'
TARGETS  = [LIVE / 'deploy' / 'panel-deploy-all.php', DIST / 'panel-deploy-all.php']
PLACEHOLDER  = '@@PAYLOAD@@'
SHA_ANCHOR   = '7f1391d33d684b449ccfac54d942a7d109290d1435a0d4b4846c60914a7e239a'
PHP_BIN      = os.environ.get('LA2PLUS_PHP', 'php')

# PWA-файлы, которые нужно включить в пакет дополнительно к deploy/files
# Пути — относительно LIVE.  Если файл не найден, сборка продолжается (с warn).
PWA_EXTRAS = [
    'service-worker.js',
    'manifest.json',
    'offline.html',
    'themes/la2plus/css/pwa.css',
    'themes/la2plus/img/icon-192.png',
    'themes/la2plus/img/icon-512.png',
]


# ── Helpers ───────────────────────────────────────────────────────────────────
def log(msg: str, *, v: bool = False, verbose: bool = False):
    if v and not verbose:
        return
    print(msg)


def sha256_file(p: pathlib.Path) -> str:
    h = hashlib.sha256()
    with p.open('rb') as f:
        for chunk in iter(lambda: f.read(65536), b''):
            h.update(chunk)
    return h.hexdigest()


def sha256_bytes(b: bytes) -> str:
    return hashlib.sha256(b).hexdigest()


# ── Lint ──────────────────────────────────────────────────────────────────────
def lint_python(root: pathlib.Path, verbose: bool) -> list:
    """py_compile все .py в root; возвращает список ошибок."""
    errors = []
    for f in sorted(root.rglob('*.py')):
        try:
            py_compile.compile(str(f), doraise=True)
            log(f'  py_compile OK  {f.relative_to(root)}', v=True, verbose=verbose)
        except py_compile.PyCompileError as e:
            errors.append(str(e))
            print(f'  [LINT ERROR] {e}')
    return errors


def lint_php(root: pathlib.Path, verbose: bool) -> list:
    """php -l все .php в root; возвращает список ошибок."""
    errors = []
    try:
        subprocess.run([PHP_BIN, '--version'], capture_output=True, check=True)
    except (FileNotFoundError, subprocess.CalledProcessError):
        log(f'  php не найден ({PHP_BIN!r}) — проверка PHP пропущена', v=True, verbose=verbose)
        return []
    for f in sorted(root.rglob('*.php')):
        r = subprocess.run([PHP_BIN, '-l', str(f)], capture_output=True, text=True)
        if r.returncode != 0:
            errors.append(r.stderr.strip() or r.stdout.strip())
            print(f'  [LINT PHP] {f.relative_to(root)}: {r.stdout.strip()}')
        else:
            log(f'  php -l OK  {f.relative_to(root)}', v=True, verbose=verbose)
    return errors


# ── Build ─────────────────────────────────────────────────────────────────────
def collect_entries(files_dir: pathlib.Path, pwa_extras: list, verbose: bool) -> list:
    """Возвращает [(arcname, path)] для zip."""
    entries = []

    if files_dir.is_dir():
        for p in sorted(files_dir.rglob('*')):
            if p.is_file():
                entries.append((str(p.relative_to(files_dir)), p))
        log(f'  deploy/files: {len(entries)} файл(ов)', verbose=verbose)
    else:
        log(f'  [WARN] каталог deploy/files не найден ({files_dir})')

    # PWA extras
    added_pwa = 0
    for rel in pwa_extras:
        p = LIVE / rel
        if p.exists():
            entries.append((rel, p))
            added_pwa += 1
            log(f'  PWA+  {rel}', v=True, verbose=verbose)
        else:
            log(f'  [WARN] PWA файл не найден: {rel}')
    log(f'  PWA-файлов добавлено: {added_pwa}/{len(pwa_extras)}', verbose=verbose)

    return entries


def build_zip(entries: list, pkg: pathlib.Path, verbose: bool) -> bytes:
    pkg.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(pkg, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for arcname, fpath in entries:
            z.write(fpath, arcname)
            log(f'  zip+  {arcname}', v=True, verbose=verbose)
    return pkg.read_bytes()


def write_checksums(entries: list, dist: pathlib.Path):
    """Пишет checksums.sha256 рядом с ZIP."""
    out_lines = []
    for arcname, fpath in entries:
        h = sha256_file(fpath)
        out_lines.append(f'{h}  {arcname}')
    chk_path = dist / 'checksums.sha256'
    chk_path.write_text('\n'.join(out_lines) + '\n', encoding='utf-8')
    print(f'  checksums: {chk_path.name} ({len(out_lines)} строк)')
    return chk_path


def build_courier(pkg_bytes: bytes, verbose: bool) -> list:
    """Подставляет base64 пакета в шаблон, пишет courier-файлы."""
    if not TEMPLATE.exists():
        log(f'  [WARN] шаблон не найден: {TEMPLATE} — курьер не создан')
        return []

    sha = sha256_bytes(pkg_bytes)
    template = TEMPLATE.read_text(encoding='utf-8')

    if PLACEHOLDER not in template:
        print(f'  [ERR] в шаблоне нет {PLACEHOLDER}', file=sys.stderr)
        return []

    out = template.replace(PLACEHOLDER, base64.b64encode(pkg_bytes).decode())

    # Подменяем SHA-якорь (прежнее значение)
    current = [line for line in out.splitlines() if 'PACKAGE_SHA256\'' in line]
    if current:
        old_sha = current[0].split("'")[3]
        out = out.replace(old_sha, sha)
        log(f'  SHA256 обновлён: {sha[:16]}…', verbose=verbose)
    else:
        log('  [WARN] PACKAGE_SHA256 не найден в шаблоне — SHA не подставлен')

    written = []
    for target in TARGETS:
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(out, encoding='utf-8')
        written.append(target)
        log(f'  courier → {target.name} ({len(out)} байт)', verbose=verbose)

    return written


# ── Report ────────────────────────────────────────────────────────────────────
def write_report(entries, pkg_bytes, courier_files, lint_errors, dist):
    now = datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    lines = [
        f'LA2PLUS Build Report — {now}',
        '=' * 48,
        f'Файлов в пакете : {len(entries)}',
        f'Размер ZIP      : {len(pkg_bytes):,} байт',
        f'SHA-256 (ZIP)   : {sha256_bytes(pkg_bytes)}',
        '',
        'Курьер-файлы:',
    ]
    for c in courier_files:
        lines.append(f'  {c}')
    if lint_errors:
        lines.append('')
        lines.append(f'Ошибки lint ({len(lint_errors)}):')
        for e in lint_errors:
            lines.append(f'  {e}')
    else:
        lines.append('')
        lines.append('Lint: ОК (ошибок нет)')
    report = dist / 'build_report.txt'
    report.write_text('\n'.join(lines) + '\n', encoding='utf-8')
    return report


# ── Main ──────────────────────────────────────────────────────────────────────
def main() -> int:
    ap = argparse.ArgumentParser(description='LA2PLUS сборщик')
    ap.add_argument('--skip-lint', action='store_true', help='Пропустить lint-проверки')
    ap.add_argument('--verbose', '-v', action='store_true', help='Подробный вывод')
    args = ap.parse_args()

    print('=== LA2PLUS Build & Deploy ===')
    print(f'  LIVE: {LIVE}')
    print(f'  DIST: {DIST}')

    lint_errors = []

    # 1) Lint
    if not args.skip_lint:
        print('\n[1] Lint Python…')
        lint_errors += lint_python(FILES, args.verbose)
        print('[2] Lint PHP…')
        lint_errors += lint_php(FILES, args.verbose)
        if lint_errors:
            print(f'\n⚠  Lint: {len(lint_errors)} ошибок. Сборка продолжается.')
        else:
            print('  Lint: ОК')
    else:
        print('\n[1-2] Lint пропущен (--skip-lint)')

    # 2) Собираем файлы
    print('\n[3] Сборка файлов…')
    if not FILES.is_dir() and not any((LIVE / p).exists() for p in PWA_EXTRAS):
        print('Нет каталога deploy/files и PWA-файлов — нечего паковать', file=sys.stderr)
        return 1
    entries = collect_entries(FILES, PWA_EXTRAS, args.verbose)

    # 3) ZIP
    print('\n[4] Создание ZIP…')
    pkg_bytes = build_zip(entries, PKG, args.verbose)
    print(f'  Пакет: {PKG.name}  ({len(entries)} файл(ов), {len(pkg_bytes):,} байт)')

    # 4) Checksums
    print('\n[5] Контрольные суммы…')
    write_checksums(entries, DIST)

    # 5) Курьер
    print('\n[6] Сборка курьера…')
    courier_files = build_courier(pkg_bytes, args.verbose)
    if courier_files:
        (LIVE / 'deploy' / 'pkg.zip').write_bytes(pkg_bytes)

    # 6) Отчёт
    print('\n[7] Отчёт…')
    report = write_report(entries, pkg_bytes, courier_files, lint_errors, DIST)
    print(f'  {report}')

    # 7) Итог
    sha_pkg = sha256_bytes(pkg_bytes)
    print('\n' + '=' * 48)
    print(f'Пакет  : {len(entries)} файлов, {len(pkg_bytes):,} байт')
    print(f'SHA256 : {sha_pkg}')
    if courier_files:
        print(f'Курьер : {", ".join(f.name for f in courier_files)}')
    if lint_errors:
        print(f'Lint   : {len(lint_errors)} ошибок (см. build_report.txt)')
        return 2  # warning exit (не критично)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
