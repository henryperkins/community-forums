#!/usr/bin/env python3
"""Explicit, resumable browser run. All waiting happens on the operator's host."""
import argparse
import fcntl
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import time

PROJECT = 'rising-woods-449718-v6'
REGION = 'us-east4'
BUCKET = PROJECT + '-retroboards-browser-evidence'
VERIFY_BUCKET = PROJECT + '-retroboards-browser-verification'
ROOT = Path(__file__).resolve().parents[3]
TERMINAL = {'SUCCESS', 'FAILURE', 'INTERNAL_ERROR', 'TIMEOUT', 'CANCELLED', 'EXPIRED'}
ACTIVE = {'PENDING', 'QUEUED', 'WORKING'}
ID = r'[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'


def cloud_json(*args):
    result = subprocess.run(['gcloud', *args, '--project=' + PROJECT],
                            capture_output=True, text=True, timeout=120)
    if result.returncode:
        raise RuntimeError('Cloud operation failed; private response omitted')
    return json.loads(result.stdout)


def read_recipe(control, filename):
    result = subprocess.run(['git', 'show', control + ':deploy/cloudrun/browser-ci/' + filename],
                            cwd=ROOT, capture_output=True, text=True, timeout=30)
    if result.returncode:
        raise ValueError('Reviewed control commit is not available locally')
    return json.loads(result.stdout)


def recipe(kind, source, control, child=None):
    if kind not in ('child', 'verifier'):
        raise ValueError('Unknown build kind')
    data = read_recipe(control, 'child-cloudbuild.json' if kind == 'child' else 'cloudbuild.json')
    account = 'retroboards-browser-ci' if kind == 'child' else 'retroboards-browser-verifier'
    expected = f'projects/{PROJECT}/serviceAccounts/{account}@{PROJECT}.iam.gserviceaccount.com'
    if data.get('serviceAccount') != expected:
        raise ValueError('Reviewed recipe has an unexpected service account')
    values = {'_SHA': source, '_CONTROL_SHA': control, '_BUCKET': BUCKET}
    if kind == 'verifier':
        if not isinstance(child, str) or not re.fullmatch(ID, child):
            raise ValueError('Exact completed child build ID is required')
        values.update({'_CHILD_BUILD_ID': child, '_VERIFY_BUCKET': VERIFY_BUCKET})
    data['substitutions'] = values
    return data


def matching(build, expected):
    return (isinstance(build, dict) and isinstance(build.get('id'), str)
            and re.fullmatch(ID, build['id']) is not None
            and build.get('serviceAccount') == expected['serviceAccount']
            and build.get('substitutions') == expected['substitutions'])


def find_existing(kind, source, control, child=None, *, reuse_success=True):
    expected = recipe(kind, source, control, child)
    builds = cloud_json('builds', 'list', '--region=' + REGION, '--limit=100', '--format=json')
    reusable = ACTIVE | ({'SUCCESS'} if reuse_success else set())
    for build in builds:
        if matching(build, expected) and build.get('status') in reusable:
            return build['id']
    return None


def create_build(kind, source, control, child=None):
    config = recipe(kind, source, control, child)
    with tempfile.TemporaryDirectory(prefix='retroboards-browser-submit-') as scratch:
        path = Path(scratch) / 'build.json'
        path.write_text(json.dumps(config))
        result = cloud_json('builds', 'submit', '--no-source', '--async', '--region=' + REGION,
                            '--config=' + str(path), '--format=json')
    build_id = result.get('id')
    if not isinstance(build_id, str) or not re.fullmatch(ID, build_id):
        raise ValueError('Cloud Build did not return an exact build ID')
    return build_id


def progress(data):
    print(json.dumps(data, sort_keys=True), flush=True)


def wait_for_build(build_id, kind, source, control, child=None):
    expected = recipe(kind, source, control, child)
    deadline, previous = time.monotonic() + 9000, None
    while time.monotonic() < deadline:
        build = cloud_json('builds', 'describe', build_id, '--region=' + REGION, '--format=json')
        if build.get('id') != build_id or not matching(build, expected):
            raise ValueError('Provider build identity does not match this run')
        status = build.get('status')
        if status not in TERMINAL | ACTIVE:
            raise ValueError('Unknown provider build status')
        if status != previous:
            progress({'kind': kind, 'build_id': build_id, 'status': status})
            previous = status
        if status in TERMINAL:
            return status
        time.sleep(20)
    raise RuntimeError('Operator wait timed out; resume with the same state file')


def read_receipt(source, control, child, verifier):
    data = cloud_json('storage', 'cat', f'gs://{VERIFY_BUCKET}/{source}/{verifier}/verification.json')
    expected = {'source_sha': source, 'control_sha': control,
                'child_build_id': child, 'parent_build_id': verifier}
    if any(data.get(key) != value for key, value in expected.items()):
        raise ValueError('Trusted receipt identity does not match this run')
    if any(data.get(key) is not True for key in
           ('passed', 'metadata_verified', 'provider_steps_verified', 'enforcement_passed')):
        raise ValueError('Trusted receipt does not establish acceptance')
    return data


def save_state(path, state):
    path.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
    with tempfile.NamedTemporaryFile(mode='w', dir=path.parent, delete=False) as output:
        json.dump(state, output, indent=2)
        output.write('\n')
        temporary = output.name
    os.replace(temporary, path)


def coordinate(source, control, state_file, *, fresh=False):
    if any(not isinstance(value, str) or not re.fullmatch('[0-9a-f]{40}', value)
           for value in (source, control)):
        raise ValueError('Exact source and reviewed control commit SHAs are required')
    path = Path(state_file)
    if path.is_symlink():
        raise ValueError('State file cannot be a symlink')
    state = json.loads(path.read_text()) if path.exists() else {'source_sha': source, 'control_sha': control, 'fresh': fresh}
    if fresh and not state.get('fresh'):
        raise ValueError('Use a new state file for a fresh run')
    fresh = state.get('fresh') is True
    if state.get('source_sha') != source or state.get('control_sha') != control:
        raise ValueError('Resume state belongs to another source or control commit')
    for key in ('child_build_id', 'verifier_build_id'):
        if key in state and (not isinstance(state[key], str) or not re.fullmatch(ID, state[key])):
            raise ValueError('Resume state has an invalid build ID')
    state['passed'] = False
    save_state(path, state)
    if 'child_build_id' not in state:
        state['child_build_id'] = find_existing('child', source, control, reuse_success=not fresh) or create_build('child', source, control)
        save_state(path, state)
    child = state['child_build_id']
    state['child_status'] = wait_for_build(child, 'child', source, control)
    save_state(path, state)
    # There is no verifier VM until the child has finished, even if it failed.
    if 'verifier_build_id' not in state:
        state['verifier_build_id'] = (find_existing('verifier', source, control, child, reuse_success=not fresh)
                                      or create_build('verifier', source, control, child))
        save_state(path, state)
    verifier = state['verifier_build_id']
    state['verifier_status'] = wait_for_build(verifier, 'verifier', source, control, child)
    save_state(path, state)
    if state['child_status'] == state['verifier_status'] == 'SUCCESS':
        read_receipt(source, control, child, verifier)
        state['passed'] = True
        save_state(path, state)
    progress(state)
    return state


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source-sha', required=True)
    parser.add_argument('--control-sha', required=True)
    parser.add_argument('--state-file', required=True)
    parser.add_argument('--fresh', action='store_true', help='Ignore completed builds; reuse active work and resume this state normally')
    args = parser.parse_args()
    # A per-commit local lock also protects concurrent invocations with different state paths.
    if any(not re.fullmatch('[0-9a-f]{40}', value) for value in (args.source_sha, args.control_sha)):
        parser.error('Exact lowercase commit SHAs are required')
    locks = Path.home() / '.local/state/retroboards-browser-ci'
    locks.mkdir(parents=True, exist_ok=True, mode=0o700)
    lock_path = locks / (args.source_sha + '-' + args.control_sha + '.lock')
    descriptor = os.open(lock_path, os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
    with os.fdopen(descriptor, 'w') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            raise SystemExit('A coordinator for these commits is already running on this host')
        try:
            state = coordinate(args.source_sha, args.control_sha, args.state_file, fresh=args.fresh)
            return 0 if state['passed'] else 1
        except (RuntimeError, ValueError) as error:
            raise SystemExit(str(error))
        except Exception:
            raise SystemExit('Coordination failed; private diagnostics omitted. Resume using the state file.')


if __name__ == '__main__':
    raise SystemExit(main())
