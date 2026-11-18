"""Build a reviewable local overlay archive. This script never contacts hosting."""
import hashlib
import json
from pathlib import Path
import zipfile

repo = Path(__file__).resolve().parents[1]
root = repo / 'implementation/public_html'
manifest_path = repo / 'implementation/release-manifest.json'
prior = json.loads(manifest_path.read_text()) if manifest_path.exists() else {'files': []}
previous = {row['path']: row.get('previous_sha256') for row in prior['files']}
files = []
for path in sorted(root.rglob('*')):
    if not path.is_file():
        continue
    rel = path.relative_to(root).as_posix()
    old = repo / 'implementation/baseline' / rel
    old_hash = hashlib.sha256(old.read_bytes()).hexdigest() if old.exists() else previous.get(rel)
    files.append({'path': rel, 'previous_sha256': old_hash, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()})
manifest_path.write_text(json.dumps({'schema': 1, 'files': files}, indent=2) + '\n')
output = repo / 'work/releases'
output.mkdir(parents=True, exist_ok=True)
archive = output / 'la2plus-portal.zip'
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as bundle:
    for row in files:
        bundle.write(root / row['path'], 'public_html/' + row['path'])
    for name in ['README.md', 'GAME-BRIDGE.md', 'release-manifest.json']:
        bundle.write(repo / 'implementation' / name, name)
checksum = hashlib.sha256(archive.read_bytes()).hexdigest()
(output / 'la2plus-portal.zip.sha256').write_text(checksum + '  la2plus-portal.zip\n')
print(f'Prepared {len(files)} overlay files: {archive}')
print('No hosting connection or deployment performed.')
