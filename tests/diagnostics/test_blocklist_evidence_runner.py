"""Exercise the evidence runner's isolation and runtime credential contract."""
import json
import os
from pathlib import Path
import secrets
import subprocess
import sys
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[2]
RUNNER = ROOT / 'docs/evidence/registry-blocklist-layout-2026-11-08/logs/run-env.py'


class EvidenceRunnerTest(unittest.TestCase):
    def setUp(self):
        self.scratch = tempfile.TemporaryDirectory(prefix='rb-evidence-runner-')
        self.addCleanup(self.scratch.cleanup)
        self.password_file = Path(self.scratch.name) / 'database-password'
        self.password_file.write_text(secrets.token_hex(32))
        self.password_file.chmod(0o600)
        self.marker = Path(self.scratch.name) / 'child-executed'
        self.env = {
            'PATH': os.environ.get('PATH', '/usr/bin:/bin'),
            'RB_BLOCKLIST_DB_USERNAME': 'runtime_fixture_user',
            'RB_BLOCKLIST_DB_PASSWORD_FILE': str(self.password_file),
            'RB_BLOCKLIST_BROWSER_DB': 'retroboards_e2e_blocklist_layout_runner',
            'RB_BLOCKLIST_TEST_DB': 'retroboards_test_blocklist_layout_runner',
            'DB_HOST': 'must-not-be-inherited.invalid',
            'OPENAI_API_KEY': 'must-not-be-inherited',
        }

    def run_child(self, code):
        return subprocess.run([sys.executable, str(RUNNER), sys.executable, '-c', code],
                              env=self.env, capture_output=True, text=True, check=False)

    def assert_refused_before_child(self):
        result = self.run_child(f'from pathlib import Path; Path({str(self.marker)!r}).touch()')
        self.assertEqual(result.returncode, 2)
        self.assertFalse(self.marker.exists())
        self.assertNotIn(self.password_file.read_text(), result.stdout + result.stderr)

    def test_missing_credential_input_refuses_before_child(self):
        self.env.pop('RB_BLOCKLIST_DB_PASSWORD_FILE')
        self.assert_refused_before_child()

    def test_unreadable_credential_input_refuses_before_child(self):
        self.password_file.unlink()
        result = self.run_child(f'from pathlib import Path; Path({str(self.marker)!r}).touch()')
        self.assertEqual(result.returncode, 2)
        self.assertFalse(self.marker.exists())

    def test_non_disposable_browser_or_php_database_refuses_before_child(self):
        for name in ['RB_BLOCKLIST_BROWSER_DB', 'RB_BLOCKLIST_TEST_DB']:
            with self.subTest(input=name):
                previous = self.env[name]
                self.env[name] = 'ordinary_database'
                self.assert_refused_before_child()
                self.env[name] = previous

    def test_runtime_credential_reaches_child_without_inheriting_provider_or_host(self):
        # Emit booleans, never credential values, even when an assertion fails.
        code = ('import json,os; from pathlib import Path; '
                f'expected=Path({str(self.password_file)!r}).read_text(); '
                'print(json.dumps({"credential_matches":os.environ.get("DB_PASSWORD")==expected, '
                '"user_matches":os.environ.get("DB_USERNAME")=="runtime_fixture_user", '
                '"browser_matches":os.environ.get("DB_DATABASE")=="retroboards_e2e_blocklist_layout_runner", '
                '"php_matches":os.environ.get("DB_TEST_DATABASE")=="retroboards_test_blocklist_layout_runner", '
                '"test_mode":os.environ.get("APP_ENV")=="test", '
                '"local_host":os.environ.get("DB_HOST")=="127.0.0.1", '
                '"provider_isolated":os.environ.get("OPENAI_API_KEY")!="must-not-be-inherited"}))')
        result = self.run_child(code)
        self.assertEqual(result.returncode, 0)
        self.assertEqual(json.loads(result.stdout), {
            'credential_matches': True, 'user_matches': True, 'browser_matches': True,
            'php_matches': True, 'test_mode': True, 'local_host': True, 'provider_isolated': True,
        })
        self.assertNotIn(self.password_file.read_text(), result.stdout + result.stderr)


if __name__ == '__main__':
    unittest.main()
