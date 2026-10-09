#!/usr/bin/env python3
"""Verify an isolated child build; branch artifacts remain untrusted data."""
import argparse
import hashlib
import http.client
import importlib.util
import json
from pathlib import Path
import re
import ssl
import subprocess
import sys
import time
from urllib.parse import quote, urlencode

PROJECT = 'rising-woods-449718-v6'
REGION = 'us-east4'
CHILD_SA = f'projects/{PROJECT}/serviceAccounts/retroboards-browser-ci@{PROJECT}.iam.gserviceaccount.com'
PARENT_SA = f'projects/{PROJECT}/serviceAccounts/retroboards-browser-verifier@{PROJECT}.iam.gserviceaccount.com'
BUCKET = f'{PROJECT}-retroboards-browser-evidence'
VERIFY_BUCKET = f'{PROJECT}-retroboards-browser-verification'
HERE = Path(__file__).resolve().parent
API_ROOT = f'/v1/projects/{PROJECT}/locations/{REGION}/builds'
JSON_LIMIT = 1024 * 1024
ARCHIVE_LIMIT = 256 * 1024 * 1024
CHUNK_BYTES = 1024 * 1024
CHILD_WAIT_SECONDS = 5600
TERMINAL = {'SUCCESS', 'FAILURE', 'INTERNAL_ERROR', 'TIMEOUT', 'CANCELLED', 'EXPIRED'}
ACTIVE = {'PENDING', 'QUEUED', 'WORKING'}
ID_PATTERN = r'[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'


class VerificationError(Exception):
    """Its stage is fixed by trusted code, never an API body or artifact value."""


def access_token():
    result = subprocess.run(['gcloud', 'auth', 'print-access-token'], capture_output=True, text=True)
    token = result.stdout.strip()
    if result.returncode or not token or '\n' in token or '\r' in token:
        raise VerificationError('identity')
    return token


def api_json(method, path, payload=None):
    body = json.dumps(payload).encode() if payload is not None else None
    connection = http.client.HTTPSConnection('cloudbuild.googleapis.com', timeout=60,
                                              context=ssl.create_default_context())
    try:
        connection.request(method, path, body=body, headers={
            'Authorization': 'Bearer ' + access_token(), 'Content-Type': 'application/json'})
        response = connection.getresponse()
        if response.status not in (200, 201):
            raise VerificationError('build-api')
        raw = response.read(JSON_LIMIT + 1)
        if len(raw) > JSON_LIMIT:
            raise VerificationError('build-api-size')
        result = json.loads(raw)
        if not isinstance(result, dict):
            raise VerificationError('build-api-shape')
        return result
    finally:
        connection.close()


def child_recipe(source_sha, control_sha):
    recipe = json.loads((HERE / 'child-cloudbuild.json').read_text())
    if not isinstance(recipe, dict):
        raise VerificationError('child-recipe')
    recipe['serviceAccount'] = CHILD_SA
    recipe['substitutions'] = {'_SHA': source_sha, '_CONTROL_SHA': control_sha, '_BUCKET': BUCKET}
    return recipe


def create_child(recipe):
    operation = api_json('POST', API_ROOT + '?' + urlencode({'projectId': PROJECT}), recipe)
    child_id = operation.get('metadata', {}).get('build', {}).get('id')
    if not isinstance(child_id, str) or not re.fullmatch(ID_PATTERN, child_id):
        raise VerificationError('child-id')
    return child_id


def child_metadata(child_id, source_sha, control_sha):
    build = api_json('GET', API_ROOT + '/' + child_id + '?' + urlencode({'projectId': PROJECT, 'id': child_id}))
    if (build.get('id') != child_id or build.get('projectId') != PROJECT
            or build.get('serviceAccount') != CHILD_SA
            or build.get('substitutions') != {'_SHA': source_sha, '_CONTROL_SHA': control_sha, '_BUCKET': BUCKET}):
        raise VerificationError('child-metadata')
    if build.get('status') not in TERMINAL | ACTIVE:
        raise VerificationError('child-status')
    return build


def await_child(child_id, source_sha, control_sha):
    deadline = time.monotonic() + CHILD_WAIT_SECONDS
    while time.monotonic() < deadline:
        build = child_metadata(child_id, source_sha, control_sha)
        if build['status'] in TERMINAL:
            return build
        time.sleep(10)
    raise VerificationError('child-wait-timeout')


def download_artifact(name, destination, limit):
    connection = http.client.HTTPSConnection('storage.googleapis.com', timeout=120,
                                              context=ssl.create_default_context())
    try:
        connection.request('GET', '/storage/v1/b/' + BUCKET + '/o/' + quote(name, safe='') + '?alt=media',
                           headers={'Authorization': 'Bearer ' + access_token(), 'Accept-Encoding': 'identity'})
        response = connection.getresponse()
        if response.status != 200:
            raise VerificationError('artifact-download')
        length = response.getheader('Content-Length')
        if length is not None and (not length.isdigit() or int(length) > limit):
            raise VerificationError('artifact-size')
        size = 0
        digest = hashlib.sha256()
        with Path(destination).open('xb') as output:
            while True:
                chunk = response.read(min(CHUNK_BYTES, limit - size + 1))
                if not chunk:
                    break
                size += len(chunk)
                if size > limit:
                    raise VerificationError('artifact-size')
                output.write(chunk)
                digest.update(chunk)
        if length is not None and size != int(length):
            raise VerificationError('artifact-truncated')
        return {'name': name, 'bytes': size, 'sha256': digest.hexdigest()}
    finally:
        connection.close()


def enforce(results, outer_result, source_sha, control_sha):
    # Only this reviewed script executes on the parent VM. No archive extraction.
    result = subprocess.run([sys.executable, '-I', str(HERE / 'enforce.py'), '--results', str(results),
                             '--outer-result', str(outer_result), '--source-sha', source_sha,
                             '--control-sha', control_sha], capture_output=True, timeout=180, cwd=HERE)
    return result.returncode == 0


def publish_receipt(receipt, directory):
    path = Path(directory) / 'verification.json'
    path.write_text(json.dumps(receipt, sort_keys=True) + '\n')
    # Load the reviewed uploader by absolute path even when Python uses -I.
    spec = importlib.util.spec_from_file_location('trusted_upload', HERE / 'upload.py')
    uploader = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(uploader)
    uploader.upload_file(path, VERIFY_BUCKET,
                         receipt['source_sha'] + '/' + receipt['parent_build_id'] + '/verification.json',
                         access_token())


def verify(source_sha, control_sha, parent_id, bucket=BUCKET, verify_bucket=VERIFY_BUCKET,
           directory=Path('/workspace/verification')):
    receipt = {'event': 'browser_ci_parent_verification', 'passed': False,
               'parent_build_id': None, 'child_build_id': None, 'source_sha': None, 'control_sha': None,
               'parent_service_account': PARENT_SA, 'child_service_account': CHILD_SA,
               'child_provider_status': None, 'metadata_verified': False, 'enforcement_passed': False,
               'artifact_receipts': [], 'stage': 'input-validation',
               'trust_limit': 'Child test receipts are branch-reported; parent enforcement is isolated'}
    valid_inputs = (isinstance(source_sha, str) and re.fullmatch('[0-9a-f]{40}', source_sha)
                    and isinstance(control_sha, str) and re.fullmatch('[0-9a-f]{40}', control_sha)
                    and isinstance(parent_id, str) and re.fullmatch(ID_PATTERN, parent_id)
                    and bucket == BUCKET and verify_bucket == VERIFY_BUCKET)
    if not valid_inputs:
        return receipt
    receipt.update({'parent_build_id': parent_id, 'source_sha': source_sha, 'control_sha': control_sha})
    directory = Path(directory)
    try:
        directory.mkdir(parents=True, exist_ok=False)
        results = directory / 'results'
        results.mkdir()
        receipt['stage'] = 'child-create'
        child_id = create_child(child_recipe(source_sha, control_sha))
        receipt['child_build_id'] = child_id
        receipt['stage'] = 'child-await'
        child = await_child(child_id, source_sha, control_sha)
        receipt.update({'child_provider_status': child['status'], 'metadata_verified': True})
        if child['status'] != 'SUCCESS':
            raise VerificationError('child-provider-failure')
        receipt['stage'] = 'artifact-download'
        for filename, target, limit in [('result.json', results / 'result.json', JSON_LIMIT),
                                        ('runner-exit.json', directory / 'runner-exit.json', JSON_LIMIT),
                                        ('evidence.tar.gz', results / 'evidence.tar.gz', ARCHIVE_LIMIT)]:
            name = source_sha + '/' + child_id + '/' + filename
            receipt['artifact_receipts'].append(download_artifact(name, target, limit))
        receipt['stage'] = 'enforcement'
        receipt['enforcement_passed'] = enforce(results, directory / 'runner-exit.json', source_sha, control_sha)
        receipt['passed'] = receipt['enforcement_passed']
        receipt['stage'] = 'complete' if receipt['passed'] else 'enforcement-failed'
    except VerificationError as error:
        receipt['stage'] = str(error)
    except Exception:
        # Never print API errors, tokens, source-controlled contents or tracebacks.
        receipt['stage'] = 'verification-error'
    try:
        publish_receipt(receipt, directory)
    except Exception:
        receipt['passed'] = False
        receipt['stage'] = 'receipt-publication-failed'
        receipt['receipt_published'] = False
    else:
        receipt['receipt_published'] = True
    return receipt


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--source-sha', required=True)
    parser.add_argument('--control-sha', required=True)
    parser.add_argument('--parent-build-id', required=True)
    parser.add_argument('--bucket', default=BUCKET)
    parser.add_argument('--verify-bucket', default=VERIFY_BUCKET)
    args = parser.parse_args()
    receipt = verify(args.source_sha, args.control_sha, args.parent_build_id, args.bucket, args.verify_bucket)
    print(json.dumps(receipt, sort_keys=True))
    return 0 if receipt['passed'] else 1


if __name__ == '__main__':
    raise SystemExit(main())
