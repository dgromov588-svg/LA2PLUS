"""Read selected application source from cPanel; never download runtime secrets."""
import getpass
import json
from pathlib import Path
import sys
import urllib.parse
import urllib.request
import http.cookiejar
import time
import subprocess
import tempfile

host = 'https://eushared26.twinservers.net:2083'
root = '/home/laplusco/public_html'
out = Path(sys.argv[1])
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
login = input('cPanel login: ')
password = getpass.getpass('cPanel password: ')

def read(url, data=None):
    for attempt in range(5):
        try:
            args = ['curl', '--http1.1', '-fsS', '--max-time', '30', '-b', cookies.name, '-c', cookies.name]
            if data is not None:
                args += ['--data-binary', '@-']
            return subprocess.run(args + [url], input=data, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True).stdout
        except Exception:
            if attempt == 4:
                raise
            time.sleep(2)

cookies = tempfile.NamedTemporaryFile(prefix='la2plus-sync-')
session = json.loads(read(host + '/login/?login_only=1', urllib.parse.urlencode({'user': login, 'pass': password}).encode()))
token = session['security_token']
del password

def get(path):
    content = read(host + token + '/download?' + urllib.parse.urlencode({'file': root + '/' + path}))
    if b'cPanel - File Not Found' in content or b'<title>404' in content:
        raise RuntimeError('Missing source: ' + path)
    target = out / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(content)

def tree(path):
    response = json.loads(read(host + token + '/execute/Fileman/list_files?' + urllib.parse.urlencode({'dir': root + '/' + path})))
    for entry in response.get('data') or []:
        name = entry.get('file', '')
        if name in ('.', '..') or not name or '/' in name or '.bak' in name or name.startswith('.'):
            continue
        child = path + '/' + name
        if entry.get('type') == 'dir':
            tree(child)
        elif name.endswith(('.php', '.js', '.css')) and 'PaymentsSetup' not in name:
            get(child)

for path in ([] if len(sys.argv) > 2 else [
    'cms-private/protected/modules/cabinet/controllers',
    'cms-private/themes/la2plus/views/cabinet',
    'cms-private/protected/l2j',
]):
    tree(path)
    print('Downloaded:', path, flush=True)
for path in [
    'protected/components/AccountLinkService.php', 'protected/components/WalletService.php',
    'protected/components/CalendarService.php', 'protected/components/LiveRankings.php',
    'protected/components/ServerDataService.php', 'protected/components/Lineage.php',
    'protected/components/CabinetBaseController.php', 'protected/components/Notify.php',
    'protected/components/RoleService.php', 'protected/components/FrontendBaseController.php',
    'protected/components/WebUser.php', 'protected/components/UserIdentity.php',
    'protected/models/Users.php', 'protected/models/Gs.php', 'protected/models/ShopItems.php',
    'protected/models/Transactions.php', 'protected/models/UserProfiles.php',
    'protected/modules/index/controllers/DefaultController.php',
    'protected/modules/index/controllers/RatingsAction.php',
    'protected/modules/stats/controllers/DefaultController.php',
    'protected/modules/deposit/controllers/GatewayController.php',
    'protected/modules/deposit/extensions/Deposit/libs/Trybit.php',
    'protected/modules/cabinet/models/ChangePasswordForm.php',
    'themes/la2plus/views/layouts/master.php', 'themes/la2plus/views/layouts/landing.php',
    'themes/la2plus/views/layouts/_menu.php', 'themes/la2plus/views/rating.php',
    'protected/modules/adm/AdmBaseController.php', 'protected/modules/adm/AdmApiController.php',
    'protected/modules/adm/assets/js/views.js',
]:
    try:
        get('cms-private/' + path)
    except Exception as exc:
        print('Skipped:', path, str(exc)[:80], flush=True)
for path in ['design/js/app.js', 'design/js/config.js', 'design/css/pages/cms.css',
             'design/css/pages/wiki.css', 'design/css/pages/panel.css', 'design/css/components/menu.css']:
    get(path)
print('Source synchronization complete', flush=True)
