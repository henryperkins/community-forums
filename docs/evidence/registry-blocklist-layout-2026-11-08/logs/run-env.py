#!/usr/bin/env python3
"""Run local evidence with explicit credentials for disposable databases only."""
from pathlib import Path
import os
import re
import subprocess
import sys

out = Path(__file__).resolve().parent
browser_db = os.environ.get('RB_BLOCKLIST_BROWSER_DB', 'retroboards_e2e_blocklist_layout_20261108')
test_db = os.environ.get('RB_BLOCKLIST_TEST_DB', 'retroboards_test_blocklist_layout_20261108')
if (not re.fullmatch(r'retroboards_e2e_blocklist_layout(?:_[a-z0-9]+)*', browser_db)
        or not re.fullmatch(r'retroboards_test_blocklist_layout(?:_[a-z0-9]+)*', test_db)):
    print('Disposable blocklist browser and PHP database names are required.', file=sys.stderr)
    sys.exit(2)
username = os.environ.get('RB_BLOCKLIST_DB_USERNAME', '').strip()
password_path = os.environ.get('RB_BLOCKLIST_DB_PASSWORD_FILE', '')
if not username or not password_path:
    print('Set RB_BLOCKLIST_DB_USERNAME and RB_BLOCKLIST_DB_PASSWORD_FILE for a local disposable database account.', file=sys.stderr)
    sys.exit(2)
try:
    password = Path(password_path).read_text().rstrip('\r\n')
except (OSError, ValueError):
    print('Cannot read the runtime database password file.', file=sys.stderr)
    sys.exit(2)
if not password:
    print('The runtime database password file is empty.', file=sys.stderr)
    sys.exit(2)

env = {key: os.environ[key] for key in ('PATH', 'HOME', 'LANG') if key in os.environ}
env.update({
    'COMPOSER_PROCESS_TIMEOUT': '0', 'APP_ENV': 'test', 'APP_DEBUG': 'false', 'APP_KEY': '0' * 64,
    'OPENAI_API_KEY': 'browser-thread-intelligence-dummy-credential',
    'DB_HOST': '127.0.0.1', 'DB_PORT': '3306', 'DB_SOCKET': '',
    'DB_USERNAME': username, 'DB_PASSWORD': password, 'DB_SSL': 'false',
    'DB_DATABASE': browser_db, 'DB_TEST_DATABASE': test_db,
    'MAIL_DRIVER': 'array', 'MAIL_FROM': '', 'SESSION_SECURE': 'false', 'APP_URL': 'http://127.0.0.1:8032',
    'RATELIMIT_PATH': str(out / 'ratelimit'), 'PACKAGES_STORAGE_PATH': str(out / 'packages'),
    'UPLOADS_PATH': str(out / 'uploads'), 'RB_EVIDENCE_DIR': str(out / 'browser'),
    'E2E_BASE_URL': 'http://127.0.0.1:8032', 'E2E_SKIP_WEBSERVER': '1',
})
sys.exit(subprocess.call(sys.argv[1:], env=env))
