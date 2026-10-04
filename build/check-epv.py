#!/usr/bin/env python3
"""Run the official validator on the exact release layout and inspect its result."""
from pathlib import Path
import json
import re
import subprocess
import sys
import tempfile
import zipfile

root = Path(__file__).resolve().parent.parent
version = json.loads((root / 'composer.json').read_text())['version']
validator = Path(sys.argv[1]).resolve() / 'src/EPV.php'
with tempfile.TemporaryDirectory(prefix='spamtroll-phpbb-epv-') as fixture:
    with zipfile.ZipFile(root / f'dist/spamtroll_phpbb_{version}.zip') as package:
        package.extractall(fixture)
    result = subprocess.run(['php', str(validator), 'run', '--dir=' + fixture],
                            text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    output = re.sub(r'\x1b\[[0-9;]*m', '', result.stdout)
    print(output)
    # Some EPV exceptions incorrectly return exit 0; don't accept those as a pass.
    if result.returncode or 'PASSED:' not in output or 'Validation: FAILED' in output:
        raise SystemExit(1)
