"""Behavior contracts for the create-only uploader, with no cloud credentials."""
import base64
from contextlib import redirect_stderr, redirect_stdout
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import subprocess
import ssl
import tempfile
import unittest
from unittest.mock import patch
from urllib.parse import parse_qs, urlsplit

SPEC = importlib.util.spec_from_file_location('ci_upload', Path(__file__).with_name('upload.py'))
UPLOAD = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(UPLOAD)


class Response:
    def __init__(self, connection):
        self.connection = connection
        self.status = connection.status

    def read(self, limit):
        self.connection.body_reads += 1
        if self.status not in (200, 201):
            raise AssertionError('Error response body must not be read')
        payload = b''.join(self.connection.chunks)
        return json.dumps({'size': str(len(payload)), 'md5Hash': base64.b64encode(
            hashlib.md5(payload, usedforsecurity=False).digest()).decode()}).encode()


class Connection:
    def __init__(self, status=200):
        self.status = status
        self.chunks = []
        self.headers = {}
        self.requests = []
        self.closed = False
        self.body_reads = 0

    def putrequest(self, method, path):
        self.requests.append((method, path))

    def putheader(self, name, value):
        self.headers[name] = value

    def endheaders(self):
        pass

    def send(self, chunk):
        self.chunks.append(chunk)

    def getresponse(self):
        return Response(self)

    def close(self):
        self.closed = True


class UploadContracts(unittest.TestCase):
    def setUp(self):
        self.scratch = tempfile.TemporaryDirectory()
        self.addCleanup(self.scratch.cleanup)
        self.root = Path(self.scratch.name)
        self.file = self.root / 'result.json'
        self.file.write_bytes(b'a' * (UPLOAD.CHUNK_BYTES * 2 + 19))

    def test_https_create_only_encoded_name_streaming_and_receipt_without_get(self):
        connection = Connection()
        with patch.object(UPLOAD.http.client, 'HTTPSConnection', return_value=connection) as factory:
            UPLOAD.upload_file(self.file, 'private-bucket', 'sha/build id/result.json', 'synthetic-token')
        factory.assert_called_once()
        self.assertEqual(factory.call_args.args, ('storage.googleapis.com',))
        self.assertEqual(factory.call_args.kwargs['timeout'], 120)
        self.assertEqual(factory.call_args.kwargs['context'].verify_mode, ssl.CERT_REQUIRED)
        self.assertTrue(factory.call_args.kwargs['context'].check_hostname)
        self.assertEqual(len(connection.requests), 1)
        method, target = connection.requests[0]
        self.assertEqual(method, 'POST')
        self.assertEqual(urlsplit(target).path, '/upload/storage/v1/b/private-bucket/o')
        self.assertEqual(parse_qs(urlsplit(target).query), {
            'uploadType': ['media'], 'name': ['sha/build id/result.json'], 'ifGenerationMatch': ['0']})
        self.assertEqual([len(c) for c in connection.chunks], [UPLOAD.CHUNK_BYTES, UPLOAD.CHUNK_BYTES, 19])
        self.assertEqual(connection.headers['Content-Length'], str(self.file.stat().st_size))
        self.assertNotIn('synthetic-token', target)
        self.assertTrue(connection.closed)

    def test_existing_object_denial_redirect_and_server_errors_never_read_bodies_or_retry(self):
        for status in (301, 403, 412, 500):
            connection = Connection(status)
            with patch.object(UPLOAD.http.client, 'HTTPSConnection', return_value=connection) as factory:
                with self.assertRaisesRegex(UPLOAD.UploadError, 'HTTP ' + str(status)):
                    UPLOAD.upload_file(self.file, 'private-bucket', 'sha/build/result.json', 'synthetic-token')
            self.assertEqual(factory.call_count, 1)
            self.assertEqual(connection.body_reads, 0)
            self.assertTrue(connection.closed)

    def test_receipt_checksum_mismatch_fails_without_metadata_get(self):
        connection = Connection()
        with patch.object(UPLOAD.http.client, 'HTTPSConnection', return_value=connection), \
             patch.object(Response, 'read', return_value=b'{"size":"0","md5Hash":"wrong"}'):
            with self.assertRaisesRegex(UPLOAD.UploadError, 'receipt did not match'):
                UPLOAD.upload_file(self.file, 'private-bucket', 'sha/build/result.json', 'synthetic-token')
        self.assertEqual(len(connection.requests), 1)
        self.assertTrue(connection.closed)

    def test_file_and_parent_symlinks_are_rejected_before_network_access(self):
        alias = self.root / 'alias.json'
        alias.symlink_to(self.file)
        directory = self.root / 'alias-dir'
        directory.symlink_to(self.root, target_is_directory=True)
        with patch.object(UPLOAD.http.client, 'HTTPSConnection') as factory:
            for candidate in (alias, directory / 'result.json'):
                with self.assertRaisesRegex(UPLOAD.UploadError, 'symlink'):
                    UPLOAD.upload_file(candidate, 'private-bucket', 'sha/build/result.json', 'synthetic-token')
        factory.assert_not_called()

    def test_token_is_captured_only_and_credential_diagnostics_are_not_forwarded(self):
        completed = subprocess.CompletedProcess([], 0, 'synthetic-token\n', 'private-diagnostic')
        with patch.object(UPLOAD.subprocess, 'run', return_value=completed) as command:
            self.assertEqual(UPLOAD.access_token(), 'synthetic-token')
        command.assert_called_once_with(['gcloud', 'auth', 'print-access-token'], capture_output=True, text=True)
        completed.returncode = 1
        with patch.object(UPLOAD.subprocess, 'run', return_value=completed):
            with self.assertRaisesRegex(UPLOAD.UploadError, '^Could not obtain build identity$'):
                UPLOAD.access_token()

    def test_required_result_and_optional_outer_receipt_archive_use_one_identity(self):
        (self.root / 'evidence.tar.gz').write_bytes(b'archive')
        outer = self.root / 'runner-exit.json'
        outer.write_text('{"docker_exit_code":7}')
        with patch.object(UPLOAD, 'access_token', return_value='synthetic-token') as identity, \
             patch.object(UPLOAD, 'upload_file') as create:
            self.assertEqual(UPLOAD.publish(self.root, outer, 'private-bucket', 'sha/build'), 3)
        identity.assert_called_once()
        self.assertEqual([c.args[2] for c in create.call_args_list], [
            'sha/build/result.json', 'sha/build/runner-exit.json', 'sha/build/evidence.tar.gz'])
        outer.unlink()
        (self.root / 'evidence.tar.gz').unlink()
        with patch.object(UPLOAD, 'access_token', return_value='synthetic-token'), \
             patch.object(UPLOAD, 'upload_file') as create:
            self.assertEqual(UPLOAD.publish(self.root, outer, 'private-bucket', 'sha/build/'), 1)
        self.assertEqual(create.call_args.args[2], 'sha/build/result.json')

    def test_main_suppresses_transport_credential_text_and_nonzero_failure(self):
        args = ['upload.py', '--results', str(self.root), '--outer-result', str(self.root / 'outer.json'),
                '--bucket', 'private-bucket', '--prefix', 'sha/build']
        stdout, stderr = io.StringIO(), io.StringIO()
        with patch.object(UPLOAD.sys, 'argv', args), \
             patch.object(UPLOAD, 'publish', side_effect=OSError('private-credential-text')), \
             redirect_stdout(stdout), redirect_stderr(stderr):
            self.assertEqual(UPLOAD.main(), 1)
        self.assertEqual(stdout.getvalue(), '')
        self.assertNotIn('private-credential-text', stderr.getvalue())
        self.assertEqual(stderr.getvalue(), 'Artifact publication failed; private diagnostics omitted\n')


if __name__ == '__main__':
    unittest.main()
