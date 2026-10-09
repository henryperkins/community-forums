#!/usr/bin/env python3
"""Create private CI artifacts with storage.objects.create only; never overwrite."""
import argparse
import base64
import hashlib
import http.client
import json
import os
from pathlib import Path
import stat
import ssl
import subprocess
import sys
from urllib.parse import quote, urlencode

HOST = 'storage.googleapis.com'
CHUNK_BYTES = 1024 * 1024


class UploadError(Exception):
    pass


def access_token():
    result = subprocess.run(['gcloud', 'auth', 'print-access-token'],
                            capture_output=True, text=True)
    token = result.stdout.strip()
    if result.returncode or not token or '\n' in token or '\r' in token:
        # Diagnostics may contain credentials; never forward captured output.
        raise UploadError('Could not obtain build identity')
    return token


def upload_file(path, bucket, name, token):
    path = Path(path)
    if any(parent.is_symlink() for parent in (path, *path.parents)):
        raise UploadError('Artifact path contains a symlink')
    descriptor = os.open(path, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK)
    with os.fdopen(descriptor, 'rb') as artifact:
        info = os.fstat(artifact.fileno())
        if not stat.S_ISREG(info.st_mode):
            raise UploadError('Artifact is not a regular file')
        query = urlencode({'uploadType': 'media', 'name': name,
                           'ifGenerationMatch': '0'})
        endpoint = '/upload/storage/v1/b/' + quote(bucket, safe='') + '/o?' + query
        # The fixed HTTPS host uses Python's verified TLS context. There is no
        # redirect handling, metadata GET, list, delete or overwrite request.
        connection = http.client.HTTPSConnection(HOST, timeout=120, context=ssl.create_default_context())
        try:
            connection.putrequest('POST', endpoint)
            connection.putheader('Authorization', 'Bearer ' + token)
            connection.putheader('Content-Type', 'application/json' if path.suffix == '.json' else 'application/gzip')
            connection.putheader('Content-Length', str(info.st_size))
            connection.endheaders()
            remaining = info.st_size
            digest = hashlib.md5(usedforsecurity=False)
            while remaining:
                chunk = artifact.read(min(CHUNK_BYTES, remaining))
                if not chunk:
                    raise UploadError('Artifact changed during upload')
                digest.update(chunk)
                connection.send(chunk)
                remaining -= len(chunk)
            response = connection.getresponse()
            if response.status not in (200, 201):
                raise UploadError('Artifact create failed (HTTP ' + str(response.status) + ')')
            # Creation response is enough; no storage.objects.get is required.
            # Never print its body, which can include operator metadata.
            receipt = json.loads(response.read(65536))
            expected_md5 = base64.b64encode(digest.digest()).decode()
            if str(receipt.get('size')) != str(info.st_size) or receipt.get('md5Hash') != expected_md5:
                raise UploadError('Artifact creation receipt did not match uploaded bytes')
        finally:
            connection.close()


def publish(results, outer_result, bucket, prefix):
    results, outer_result = Path(results), Path(outer_result)
    files = [(results / 'result.json', 'result.json')]
    if outer_result.exists():
        files.append((outer_result, 'runner-exit.json'))
    if (results / 'evidence.tar.gz').exists():
        files.append((results / 'evidence.tar.gz', 'evidence.tar.gz'))
    token = access_token()
    for path, name in files:
        upload_file(path, bucket, prefix.rstrip('/') + '/' + name, token)
    return len(files)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--results', required=True)
    parser.add_argument('--outer-result', required=True)
    parser.add_argument('--bucket', required=True)
    parser.add_argument('--prefix', required=True)
    args = parser.parse_args()
    try:
        count = publish(args.results, args.outer_result, args.bucket, args.prefix)
        print(json.dumps({'event': 'browser_ci_artifacts_created', 'artifact_count': count}))
        return 0
    except UploadError as error:
        print('Artifact publication: ' + str(error), file=sys.stderr)
    except Exception:
        # Network/subprocess/filesystem exceptions can include private values.
        print('Artifact publication failed; private diagnostics omitted', file=sys.stderr)
    return 1


if __name__ == '__main__':
    raise SystemExit(main())
