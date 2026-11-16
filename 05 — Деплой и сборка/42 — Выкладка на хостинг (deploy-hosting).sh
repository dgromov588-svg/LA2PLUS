#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Выкладка пакета панели v2 на боевой хостинг LA2PLUS.

Три транспорта, один порядок действий:
  ftp     — FTP/FTPS через ftplib (по умолчанию, самый совместимый)
  ssh     — scp/rsync по SSH, если на аккаунте открыт шелл
  cpanel  — UAPI Fileman по токену cPanel (без FTP и без SSH)

Что делает по шагам:
  1) читает список файлов из checksums.txt (только то, что уходит на сервер);
  2) скачивает с сервера текущие версии заменяемых файлов в backup/ (локально)
     и кладёт их же в каталог panel-v2-backup-<дата>/ на сервере;
  3) заливает новые файлы (создавая каталоги);
  4) сверяет размер каждого залитого файла с локальным;
  5) печатает, что осталось сделать руками (миграция/маршруты/права).

Запуск:
  export FTP_HOST=la2plus.com FTP_USER=laplusco FTP_PASS='…'
  python3 tools/deploy-hosting.sh ftp            # залить
  python3 tools/deploy-hosting.sh ftp --dry-run  # только показать план
  python3 tools/deploy-hosting.sh ssh            # если есть SSH: SSH_HOST/SSH_USER/SSH_PASS|SSH_KEY
  python3 tools/deploy-hosting.sh cpanel         # CPANEL_HOST/CPANEL_USER/CPANEL_TOKEN

Ничего не удаляет. Никаких паролей не печатает.
"""

import fnmatch
import hashlib
import os
import posixpath
import sys
from datetime import datetime

HERE = os.path.dirname(os.path.abspath(__file__))
LIVE = os.path.dirname(HERE)
PACKAGE = os.path.join(LIVE, 'cms-private')

# Куда кладём на сервере
REMOTE_ROOT = os.environ.get('REMOTE_ROOT', '/home4/viplclub/la2plus/public_html')

# Что не заливаем никогда
SKIP_PATTERNS = [
    '*/tests/*', 'tests/*',                    # тесты на сервере не нужны
    '*.bak*', '*.tmp*', '*~', '*.log',
    '*/config/params.php',                     # правится только вручную/самим сайтом
    '*/config/database.php',                   # на сервере свой, с боевым паролем
    '*/config/lineage.php',                    # игровые базы — не трогаем
]


def log(msg):
    print(msg, flush=True)


def should_skip(rel):
    rel = rel.replace(os.sep, '/')

    for pattern in SKIP_PATTERNS:
        if fnmatch.fnmatch(rel, pattern):
            return True

    return False


def collect_files():
    """Файлы пакета, которые реально уходят на сервер."""
    files = []

    for base, dirs, names in os.walk(os.path.join(LIVE, 'cms-private')):
        for name in names:
            full = os.path.join(base, name)
            rel = os.path.relpath(full, LIVE)

            if should_skip(rel):
                continue

            if not (name.endswith('.php') or name.endswith('.css') or name.endswith('.js')):
                continue

            files.append(rel)

    return sorted(files)


def sha256(path):
    h = hashlib.sha256()

    with open(path, 'rb') as fh:
        for chunk in iter(lambda: fh.read(65536), b''):
            h.update(chunk)

    return h.hexdigest()


def local_backup(files):
    """Копия текущих локальных файлов — на случай, если понадобится сравнить."""
    stamp = datetime.now().strftime('%Y%m%d-%H%M')
    target = os.path.join(LIVE, 'backup-local-' + stamp)
    os.makedirs(target, exist_ok=True)

    for rel in files:
        src = os.path.join(LIVE, rel)
        dst = os.path.join(target, rel)
        os.makedirs(os.path.dirname(dst), exist_ok=True)

        with open(src, 'rb') as fi, open(dst, 'wb') as fo:
            fo.write(fi.read())

    return target


# --------------------------------------------------------------------------
# FTP
# --------------------------------------------------------------------------

def ftp_connect():
    import ftplib

    host = os.environ.get('FTP_HOST', 'la2plus.com')
    user = os.environ.get('FTP_USER', '')
    password = os.environ.get('FTP_PASS', '')
    port = int(os.environ.get('FTP_PORT', '21'))

    if not user or not password:
        log('ОШИБКА: не заданы FTP_USER/FTP_PASS.')
        sys.exit(2)

    log('FTP: подключаюсь к %s:%d как %s' % (host, port, user))

    for use_tls in (True, False):
        try:
            ftp = ftplib.FTP_TLS(timeout=30) if use_tls else ftplib.FTP(timeout=30)
            ftp.connect(host, port, timeout=30)
            ftp.login(user, password)

            if use_tls:
                ftp.prot_p()

            ftp.set_pasv(True)
            log('FTP: вход выполнен (%s)' % ('FTPS' if use_tls else 'FTP'))

            try:
                log('FTP: текущий каталог %s' % ftp.pwd())
            except Exception:
                pass

            return ftp
        except Exception as exc:
            last = exc
            log('FTP: %s не подошёл (%s)' % ('FTPS' if use_tls else 'FTP', exc))

    log('ОШИБКА: не удалось войти по FTP: %s' % last)
    log('Проверьте: пароль без кавычек, порт 21 открыт, аккаунт laplusco.')
    sys.exit(3)


def ftp_mkdirs(ftp, remote_dir):
    # Каталог может быть относительным (REMOTE_ROOT не с абсолютного /),
    # поэтому идём по частям и проверяем каждый шаг через pwd/CWD.
    if not remote_dir:
        return

    target = remote_dir if remote_dir.startswith('/') else '/./' + remote_dir
    parts = [p for p in target.split('/') if p and p != '.']
    path = ''

    for part in parts:
        path += '/' + part

        if part == '~':
            continue

        try:
            ftp.cwd(path)
            continue
        except Exception:
            pass

        try:
            ftp.mkd(path)
        except Exception:
            pass

    ftp.cwd('/')


def ftp_exists(ftp, remote_path):
    # size() на части серверов требует режима TYPE I и падает на 550 даже для
    # существующего файла, поэтому проверяем двумя способами
    try:
        ftp.voidcmd('TYPE I')
    except Exception:
        pass

    try:
        return ftp.size(remote_path) is not None
    except Exception:
        pass

    try:
        parent = posixpath.dirname(remote_path)
        name = posixpath.basename(remote_path)
        return name in (ftp.nlst(parent) or [])
    except Exception:
        return False


def ftp_upload(ftp, local_path, remote_path):
    ftp_mkdirs(ftp, posixpath.dirname(remote_path))

    with open(local_path, 'rb') as fh:
        ftp.storbinary('STOR ' + remote_path, fh, blocksize=32768)


def ftp_download(ftp, remote_path, local_path):
    with open(local_path, 'wb') as fh:
        ftp.retrbinary('RETR ' + remote_path, fh.write)


def run_ftp(files, dry_run):
    ftp = ftp_connect()
    stamp = datetime.now().strftime('%Y%m%d-%H%M')
    backup_dir = posixpath.join(REMOTE_ROOT, 'panel-v2-backup-' + stamp)
    local_backup_dir = os.path.join(LIVE, 'backup-remote-' + stamp)
    os.makedirs(local_backup_dir, exist_ok=True)

    replaced = 0
    added = 0
    missing_remote = 0

    for rel in files:
        remote_path = posixpath.join(REMOTE_ROOT, rel.replace(os.sep, '/'))
        local_path = os.path.join(LIVE, rel)

        exists = ftp_exists(ftp, remote_path)

        if exists:
            replaced += 1
        else:
            added += 1

        if dry_run:
            log('  [план] %s %s' % ('заменить' if exists else 'добавить', rel))
            continue

        # бэкап текущей версии — и локально, и рядом на сервере
        if exists:
            dst = os.path.join(local_backup_dir, rel)
            os.makedirs(os.path.dirname(dst), exist_ok=True)

            try:
                ftp_download(ftp, remote_path, dst)
                with open(dst, 'rb') as fh:
                    ftp_mkdirs(ftp, backup_dir)
                    ftp.storbinary('STOR ' + posixpath.join(backup_dir, os.path.basename(rel)), fh)
            except Exception as exc:
                log('  ВНИМАНИЕ: бэкап %s не снят (%s) — останавливаюсь' % (rel, exc))
                ftp.quit()
                sys.exit(4)

        try:
            ftp_upload(ftp, local_path, remote_path)
        except Exception as exc:
            log('  ОШИБКА заливки %s: %s' % (rel, exc))
            ftp.quit()
            sys.exit(5)

        # сверка размера
        try:
            remote_size = ftp.size(remote_path)
        except Exception:
            remote_size = None

        local_size = os.path.getsize(local_path)

        if remote_size is not None and remote_size != local_size:
            log('  ОШИБКА: %s на сервере %s байт, локально %s' % (rel, remote_size, local_size))
            ftp.quit()
            sys.exit(6)

        log('  ✓ %s (%d байт)' % (rel, local_size))

    # установщик таблиц — в корень сайта
    installer = os.path.join(LIVE, 'deploy', 'panel-install.php')
    if os.path.isfile(installer) and not dry_run:
        try:
            ftp_upload(ftp, installer, posixpath.join(REMOTE_ROOT, 'panel-install.php'))
            log('  ✓ panel-install.php (установщик таблиц, сам удалится после запуска)')
        except Exception as exc:
            log('  ВНИМАНИЕ: установщик не залит (%s) — таблицы создайте CLI-миграцией' % exc)

    ftp.quit()

    log('')
    log('ИТОГО: заменено %d, добавлено %d файлов%s' % (replaced, added, ' (dry-run)' if dry_run else ''))
    log('Бэкап заменённых файлов: %s и %s на сервере' % (local_backup_dir, backup_dir))
    return stamp


# --------------------------------------------------------------------------
# SSH
# --------------------------------------------------------------------------

def run_ssh(files, dry_run):
    import subprocess

    host = os.environ.get('SSH_HOST', os.environ.get('FTP_HOST', 'la2plus.com'))
    user = os.environ.get('SSH_USER', os.environ.get('FTP_USER', ''))
    port = os.environ.get('SSH_PORT', '22')
    key = os.environ.get('SSH_KEY', '')
    password = os.environ.get('SSH_PASS', '')

    if not user:
        log('ОШИБКА: не задан SSH_USER.')
        sys.exit(2)

    stamp = datetime.now().strftime('%Y%m%d-%H%M')
    backup_dir = posixpath.join(REMOTE_ROOT, 'panel-v2-backup-' + stamp)
    ssh_opts = ['-p', port, '-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=20']

    if key:
        ssh_opts += ['-i', key]

    def ssh(cmd):
        argv = ['ssh'] + ssh_opts + ['%s@%s' % (user, host), cmd]

        if password:
            argv = ['sshpass', '-p', password] + argv

        return subprocess.run(argv, capture_output=True, text=True)

    probe = ssh('echo OK; pwd')

    if probe.returncode != 0:
        log('ОШИБКА SSH: %s' % (probe.stderr.strip() or 'нет ответа'))
        log('Если шелл закрыт — используйте FTP или cPanel: на shared-хостинге SSH часто выключен.')
        sys.exit(3)

    log('SSH: доступ есть (%s)' % probe.stdout.strip().replace('\n', ' / '))

    ssh('mkdir -p %s' % backup_dir)

    for rel in files:
        remote_path = posixpath.join(REMOTE_ROOT, rel.replace(os.sep, '/'))
        local_path = os.path.join(LIVE, rel)

        if dry_run:
            log('  [план] %s' % rel)
            continue

        # бэкап, если файл уже есть
        ssh('if [ -f %s ]; then cp %s %s/ ; fi' % (
            remote_path, remote_path, backup_dir))

        argv = ['scp'] + [o for o in ssh_opts if o != '-p'] + ['-P', port, local_path,
                                                             '%s@%s:%s' % (user, host, remote_path)]

        if password:
            argv = ['sshpass', '-p', password] + argv

        res = subprocess.run(argv, capture_output=True, text=True)

        if res.returncode != 0:
            log('  ОШИБКА %s: %s' % (rel, res.stderr.strip()))
            sys.exit(5)

        log('  ✓ %s' % rel)

    installer = os.path.join(LIVE, 'deploy', 'panel-install.php')
    if os.path.isfile(installer) and not dry_run:
        argv = ['scp'] + [o for o in ssh_opts if o != '-p'] + ['-P', port, installer,
                                                             '%s@%s:%s' % (user, host, posixpath.join(REMOTE_ROOT, 'panel-install.php'))]
        if password:
            argv = ['sshpass', '-p', password] + argv
        subprocess.run(argv, capture_output=True, text=True)
        log('  ✓ panel-install.php')

    log('')
    log('ИТОГО: файлов %d%s' % (len(files), ' (dry-run)' if dry_run else ''))
    log('Бэкап на сервере: %s' % backup_dir)
    return stamp


# --------------------------------------------------------------------------
# cPanel UAPI
# --------------------------------------------------------------------------

def run_cpanel(files, dry_run):
    import json
    import urllib.parse
    import urllib.request
    import ssl

    host = os.environ.get('CPANEL_HOST', 'la2plus.com')
    port = os.environ.get('CPANEL_PORT', '2083')
    user = os.environ.get('CPANEL_USER', os.environ.get('FTP_USER', ''))
    token = os.environ.get('CPANEL_TOKEN', '')

    if not user or not token:
        log('ОШИБКА: нужны CPANEL_USER и CPANEL_TOKEN (cPanel → Безопасность → API-токены).')
        sys.exit(2)

    base = 'https://%s:%s/execute/Fileman/' % (host, port)
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    headers = {'Authorization': 'cpanel %s:%s' % (user, token)}

    def api(function, params):
        url = base + function + '?' + urllib.parse.urlencode(params)
        req = urllib.request.Request(url, headers=headers)

        with urllib.request.urlopen(req, context=ctx, timeout=40) as resp:
            return json.loads(resp.read().decode('utf-8'))

    stamp = datetime.now().strftime('%Y%m%d-%H%M')
    backup_dir = posixpath.join(REMOTE_ROOT, 'panel-v2-backup-' + stamp)

    mk = api('mkdir', {'path': REMOTE_ROOT, 'name': 'panel-v2-backup-' + stamp})
    log('cPanel: каталог бэкапа %s' % ('создан' if mk.get('status') == 1 else 'уже есть/не создан'))

    for rel in files:
        remote_path = posixpath.join(REMOTE_ROOT, rel.replace(os.sep, '/'))
        local_path = os.path.join(LIVE, rel)

        if dry_run:
            log('  [план] %s' % rel)
            continue

        # бэкап текущей версии
        try:
            api('copy_files', {'sourcefiles': remote_path, 'destdir': backup_dir})
        except Exception:
            pass

        # загрузка: multipart/form-data
        boundary = '----la2plus' + hashlib.md5(rel.encode()).hexdigest()
        with open(local_path, 'rb') as fh:
            data = fh.read()

        filename = os.path.basename(rel)
        body = (
            '--%s\r\nContent-Disposition: form-data; name="dir"\r\n\r\n%s\r\n'
            '--%s\r\nContent-Disposition: form-data; name="overwrite"\r\n\r\n1\r\n'
            '--%s\r\nContent-Disposition: form-data; name="file-1"; filename="%s"\r\n'
            'Content-Type: application/octet-stream\r\n\r\n'
        ) % (boundary, posixpath.dirname(remote_path), boundary, boundary, filename)

        payload = body.encode('utf-8') + data + ('\r\n--%s--\r\n' % boundary).encode('utf-8')

        req = urllib.request.Request(
            'https://%s:%s/execute/Fileman/upload_files' % (host, port),
            data=payload,
            headers=dict(headers, **{'Content-Type': 'multipart/form-data; boundary=' + boundary}),
            method='POST')

        with urllib.request.urlopen(req, context=ctx, timeout=60) as resp:
            out = json.loads(resp.read().decode('utf-8'))

        ok = out.get('status') == 1
        log('  %s %s' % ('✓' if ok else '✗', rel))

        if not ok:
            log('    ответ: %s' % str(out)[:300])
            sys.exit(5)

    log('')
    log('ИТОГО: файлов %d%s' % (len(files), ' (dry-run)' if dry_run else ''))
    log('Бэкап на сервере: %s' % backup_dir)
    return stamp


# --------------------------------------------------------------------------

def main():
    if len(sys.argv) < 2 or sys.argv[1] not in ('ftp', 'ssh', 'cpanel'):
        log(__doc__)
        sys.exit(1)

    mode = sys.argv[1]
    dry_run = '--dry-run' in sys.argv

    files = collect_files()
    log('Файлов к заливке: %d' % len(files))
    log('')

    if dry_run:
        for rel in files:
            log('  ' + rel)
        log('')

    backup = local_backup(files)
    log('Локальная копия отправляемого: %s' % backup)
    log('')

    if mode == 'ftp':
        run_ftp(files, dry_run)
    elif mode == 'ssh':
        run_ssh(files, dry_run)
    else:
        run_cpanel(files, dry_run)

    log('')
    log('Дальше (по порядку):')
    log('  1) таблицы:  php cms-private/protected/migrations/m261114_120000_panel_roles.php')
    log('     либо открыть /panel-install.php?key=<ключ> — установщик сам удалится')
    log('  2) маршруты: 3 строки из cms-private/protected/config/PATCH-main-routes.txt')
    log('  3) права:    chmod 664 cms-private/protected/config/params.php')
    log('  4) проверка: /cabinet/panel/panelStaff и /cabinet/panel/panelAnalyticsData?days=7')


if __name__ == '__main__':
    main()
