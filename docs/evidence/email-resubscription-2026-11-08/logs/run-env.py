#!/usr/bin/env python3
import os, subprocess, sys, pathlib
out=pathlib.Path(__file__).resolve().parent
keys=('PATH','HOME','LANG')
env={k:os.environ[k] for k in keys if k in os.environ}
env.update({
 'APP_ENV':'test','APP_DEBUG':'false','APP_KEY':'0'*64,'OPENAI_API_KEY':'browser-thread-intelligence-dummy-credential',
 'DB_HOST':'127.0.0.1','DB_PORT':'3306','DB_SOCKET':'','DB_USERNAME':'retro','DB_PASSWORD':'retropw','DB_SSL':'false',
 'DB_DATABASE':'retroboards_e2e_resubscription_20261108','DB_TEST_DATABASE':'retroboards_test_resubscription_20261108',
 'MAIL_DRIVER':'array','MAIL_FROM':'','SESSION_SECURE':'false','APP_URL':'http://127.0.0.1:8029',
 'RATELIMIT_PATH':str(out/'ratelimit'),'PACKAGES_STORAGE_PATH':str(out/'packages'), 'UPLOADS_PATH':str(out/'uploads'),
 'RB_EVIDENCE_DIR':str(out/'browser'),'E2E_BASE_URL':'http://127.0.0.1:8029','E2E_SKIP_WEBSERVER':'1',
})
sys.exit(subprocess.call(sys.argv[1:],env=env))
