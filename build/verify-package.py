#!/usr/bin/env python3
"""Check phpBB directory identity, source bytes, licenses and bundled SDK."""
from pathlib import Path
import hashlib
import json
import zipfile

root = Path(__file__).resolve().parent.parent
metadata = json.loads((root / 'composer.json').read_text())
version = metadata['version']
archive = root / f'dist/spamtroll_phpbb_{version}.zip'
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
assert Path(str(archive) + '.sha256').read_text() == f'{digest}  {archive.name}\n'
prefix = 'spamtroll/phpbb/'
with zipfile.ZipFile(archive) as package:
    assert package.testzip() is None
    for entry in package.namelist():
        assert entry == 'spamtroll/' or entry.startswith(prefix), entry
        assert '/../' not in entry and not entry.startswith('/')
        assert not entry.startswith(prefix + 'tests/') or entry.startswith(prefix + 'vendor/')
    for directory in ('acp', 'adm', 'config', 'cron', 'event', 'language', 'migrations', 'service'):
        for source in (root / directory).rglob('*'):
            if source.is_file():
                assert package.read(prefix + source.relative_to(root).as_posix()) == source.read_bytes()
    for source in ('ext.php', 'composer.json', 'composer.lock', 'README.md', 'CHANGELOG.md', 'PUBLICATION.md'):
        assert package.read(prefix + source) == (root / source).read_bytes()
    assert package.read(prefix + 'license.txt') == (root / 'LICENSE').read_bytes()
    for required in ('vendor/autoload.php', 'vendor/spamtroll/php-sdk/src/Client.php',
                     'vendor/spamtroll/php-sdk/src/Http/HttpClientInterface.php',
                     'vendor/spamtroll/php-sdk/LICENSE', 'vendor/composer/LICENSE'):
        assert package.read(prefix + required)
    installed = json.loads(package.read(prefix + 'vendor/composer/installed.json'))
    assert [(p['name'], p['version']) for p in installed['packages']] == [('spamtroll/php-sdk', 'v0.9.3')]
print(f'phpBB package identity, runtime/source bytes, licenses and pinned SDK verified: {archive.name}; SHA256 {digest}')
