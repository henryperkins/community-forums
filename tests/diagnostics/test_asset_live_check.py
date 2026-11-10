"""Black-box checks of the asset evidence CLI against synthetic HTTP responses."""
import hashlib
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path
import socket
import subprocess
import sys
import tempfile
import threading
import unittest


ROOT = Path(__file__).resolve().parents[2]
CHECK = ROOT / 'docs/evidence/asset-retention-check-2026-11-08/live-check.py'
CURRENT = '/assets/dist/app-current.js'
RETAINED = '/assets/dist/app-retained.js'
CONTROL = '/assets/dist/app-style-rbProbe0.css'


class AssetLiveCheckTest(unittest.TestCase):
    def setUp(self):
        self.scratch = tempfile.TemporaryDirectory(prefix='rb-asset-live-check-')
        self.addCleanup(self.scratch.cleanup)
        self.manifest_path = Path(self.scratch.name) / 'assets.json'
        self.assets = {CURRENT: b'current asset', RETAINED: b'retained asset'}
        self.manifest_path.write_text(json.dumps({
            'version': 'current',
            'urls': {'app.js': CURRENT},
            'releases': [{'version': 'current', 'files': [CURRENT]}],
            'files': {url: {'immutable': True, 'sha256': hashlib.sha256(body).hexdigest()}
                      for url, body in self.assets.items()},
        }))
        self.responses = {
            '/': (200, [('Link', f'<{CURRENT}>; rel=preload')],
                  f'<script src="{CURRENT}"></script>'.encode()),
            '/healthz/': (200, [], b'{"status":"ok","database":"ok"}'),
            CONTROL: (404, [('cache-control', 'No-Store')], b'not found'),
            **{url: (200, [('cAcHe-CoNtRoL', 'Public, max-age=31536000, ImMuTaBlE'),
                          ('x-retroboards-cache', 'STATIC')], body)
               for url, body in self.assets.items()},
        }
        responses = self.responses

        class Handler(BaseHTTPRequestHandler):
            def do_GET(self):
                status, headers, body = responses[self.path]
                self.send_response(status)
                for name, value in headers:
                    self.send_header(name, value)
                self.end_headers()
                self.wfile.write(body)

            def log_message(self, *args):
                pass

        self.server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.addCleanup(self.stop_server)
        self.host = f'http://127.0.0.1:{self.server.server_port}'

    def stop_server(self):
        self.server.shutdown()
        self.thread.join()
        self.server.server_close()

    def run_check(self, *args):
        return subprocess.run(
            [sys.executable, '-I', str(CHECK), *map(str, args)],
            capture_output=True, text=True, timeout=10,
            env={**os.environ, 'NO_PROXY': '127.0.0.1', 'no_proxy': '127.0.0.1'},
        )

    def report(self, host=None):
        result = self.run_check(self.manifest_path, host or self.host, 'current')
        self.assertNotIn('Traceback', result.stderr)
        return result, json.loads(result.stdout)

    def assert_failure(self, message):
        result, report = self.report()
        self.assertEqual(result.returncode, 1)
        self.assertFalse(report['ok'])
        self.assertTrue(any(message in failure for failure in report['failures']), report)
        self.assertIn(message, result.stderr)
        return report

    def test_valid_release_and_retained_files_pass_with_case_insensitive_headers(self):
        result, report = self.report()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertTrue(report['ok'])
        self.assertEqual(report['failures'], [])
        self.assertEqual(report['release_files_matched'], 1)
        self.assertEqual(report['retained_files_matched'], 1)
        self.assertEqual(report['preload_link_references'], [CURRENT])

    def test_current_and_retained_assets_enforce_status_hash_and_caching(self):
        for url in self.assets:
            original = self.responses[url]
            cases = [
                ((404, [], b'missing'), 'expected 200'),
                ((200, original[1], b'wrong bytes'), 'SHA-256'),
                ((200, [('Cache-Control', 'immutable')], original[2]), 'expected STATIC'),
                ((200, [('X-RetroBoards-Cache', 'STATIC')], original[2]), 'not immutable'),
            ]
            for response, message in cases:
                with self.subTest(url=url, condition=message):
                    self.responses[url] = response
                    self.assert_failure(message)
            self.responses[url] = original

    def test_home_must_succeed_and_reference_the_requested_release(self):
        for response, message in [
            ((503, [], b'unavailable'), 'expected 200'),
            ((200, [], b'<h1>Empty</h1>'), 'references no /assets/dist/ file'),
            ((200, [], f'<script src="{RETAINED}"></script>'.encode()), 'page references files outside'),
        ]:
            with self.subTest(condition=message):
                self.responses['/'] = response
                self.assert_failure(message)

    def test_every_preload_header_is_checked(self):
        status, headers, body = self.responses['/']
        self.responses['/'] = (status, headers + [('lInK', f'<{RETAINED}>; rel=preload')], body)
        self.assert_failure('Link preload header references files outside')

    def test_unlisted_control_requires_404_and_no_store(self):
        for response in [(200, [('Cache-Control', 'no-store')], b''), (404, [], b'')]:
            with self.subTest(response=response):
                self.responses[CONTROL] = response
                self.assert_failure('expected 404 no-store')

    def test_health_requires_successful_json_status(self):
        for status, body in [(500, b'{"status":"ok"}'), (200, b'not JSON'),
                             (200, b'[]'), (200, b'{"status":"error"}')]:
            with self.subTest(status=status, body=body):
                self.responses['/healthz/'] = (status, [], body)
                self.assert_failure('expected 200 and "status": "ok"')

    def test_truncated_response_is_reported_without_losing_json(self):
        status, headers, body = self.responses[CURRENT]
        self.responses[CURRENT] = (status, headers + [('Content-Length', str(len(body) + 10))], body)
        report = self.assert_failure('IncompleteRead')
        self.assertFalse(report['release_files'][0]['match'])

    def test_connection_failure_is_reported_without_losing_json(self):
        # Reserve a local port without listening, so another process cannot take it.
        with socket.socket() as unavailable:
            unavailable.bind(('127.0.0.1', 0))
            result, report = self.report(f'http://127.0.0.1:{unavailable.getsockname()[1]}')
        self.assertEqual(result.returncode, 1)
        self.assertFalse(report['ok'])
        self.assertIsNone(report['home_status'])
        self.assertIn('URLError', result.stderr)

    def test_usage_errors_exit_two_without_network_requests(self):
        malformed = Path(self.scratch.name) / 'malformed.json'
        malformed.write_text('{')
        for args in [(), (self.manifest_path, self.host, 'unknown'),
                     (malformed, self.host, 'current'),
                     (malformed.with_name('missing.json'), self.host, 'current'),
                     (self.manifest_path, 'file:///local', 'current')]:
            with self.subTest(args=args):
                result = self.run_check(*args)
                self.assertEqual(result.returncode, 2, result.stderr)
                self.assertEqual(result.stdout, '')
                self.assertIn('Usage:', result.stderr)
                self.assertNotIn('Traceback', result.stderr)


if __name__ == '__main__':
    unittest.main()
