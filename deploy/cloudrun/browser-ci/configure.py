#!/usr/bin/env python3
"""Converge restricted browser CI. Private webhook URLs stay in process memory."""
import argparse
import json
from pathlib import Path
import re
import subprocess
import tempfile
from urllib.parse import urlsplit

PROJECT = 'rising-woods-449718-v6'
REGION = 'us-east4'
REPO = 'henryperkins/community-forums'
SA = f'retroboards-browser-ci@{PROJECT}.iam.gserviceaccount.com'
BUCKET = f'{PROJECT}-retroboards-browser-evidence'
TRIGGER = 'retroboards-browser-evidence'
HERE = Path(__file__).resolve().parent


def invoke(args, optional=False):
    result = subprocess.run(args, text=True, capture_output=True)
    if result.returncode:
        if optional and re.search(r'NOT_FOUND|not found|does not exist|HTTPError 404', result.stderr, re.I):
            return None
        # gh responses can include the private callback URL. Never echo stderr/body.
        raise RuntimeError(f'{args[0]} operation {args[1]} failed ({result.returncode}); private response omitted')
    return result.stdout


def gc(*args, optional=False):
    return invoke(['gcloud', *args, f'--project={PROJECT}', '--quiet'], optional=optional)


def build_config():
    config = json.loads((HERE / 'cloudbuild.json').read_text())
    if config.get('serviceAccount') != f'projects/{PROJECT}/serviceAccounts/{SA}':
        raise ValueError('Browser recipe must use the restricted test account')
    if config.get('substitutions', {}).get('_BUCKET') != BUCKET:
        raise ValueError('Browser recipe must use the private evidence bucket')
    return config


def hook_matches(url, identifiers):
    """Do not select a same-named hook in another host, project or region."""
    if not isinstance(url, str):
        return False
    parsed = urlsplit(url)
    prefix = f'/v1/projects/{PROJECT}/locations/{REGION}/triggers/'
    expected = {f'{prefix}{identifier}:webhook' for identifier in identifiers}
    return (parsed.scheme == 'https' and parsed.netloc == 'cloudbuild.googleapis.com'
            and parsed.path in expected and not parsed.fragment)


def hook_pages(raw):
    """gh 2.46 paginates as consecutive JSON arrays; --slurp is newer."""
    decoder = json.JSONDecoder()
    hooks = []
    while raw.strip():
        page, end = decoder.raw_decode(raw.lstrip())
        if not isinstance(page, list) or any(not isinstance(hook, dict) for hook in page):
            raise ValueError('Unexpected webhook page shape; private response omitted')
        hooks.extend(page)
        raw = raw.lstrip()[end:]
    return hooks


def configure(apply=False):
    config = build_config()
    plan = {'service_account': SA, 'project_roles': ['roles/logging.logWriter'],
            'bucket': BUCKET, 'bucket_role': 'roles/storage.objectCreator',
            'artifact_expiry_days': 14, 'public_access_prevention': 'enforced',
            'trigger': TRIGGER,
            'scope': 'manual exact SHA only; no automatic browser builds',
            'github_status_publication': 'unavailable; Cloud Build and private GCS are authoritative'}
    if not apply:
        return {'plan': plan, 'build': config}
    deploy = json.loads(gc('builds', 'triggers', 'describe', 'retroboards-main', f'--region={REGION}', '--format=json'))
    hooks = hook_pages(invoke(['gh', 'api', f'repos/{REPO}/hooks', '--paginate']))
    deploy_hooks = [h for h in hooks if hook_matches(h.get('config', {}).get('url', ''), (deploy['id'], 'retroboards-main'))]
    if len(deploy_hooks) != 1:
        raise RuntimeError('Exactly one existing main deployment webhook is required')
    if gc('iam', 'service-accounts', 'describe', SA, '--format=json', optional=True) is None:
        gc('iam', 'service-accounts', 'create', 'retroboards-browser-ci', '--display-name=RetroBoards synthetic browser CI')
    gc('projects', 'add-iam-policy-binding', PROJECT, f'--member=serviceAccount:{SA}', '--role=roles/logging.logWriter', '--condition=None')
    with tempfile.TemporaryDirectory(prefix='retroboards-browser-ci-config-') as scratch:
        scratch = Path(scratch)
        lifecycle = scratch / 'lifecycle.json'
        lifecycle.write_text(json.dumps({'rule': [{'action': {'type': 'Delete'}, 'condition': {'age': 14}}]}))
        for bucket in (BUCKET,):
            if gc('storage', 'buckets', 'describe', f'gs://{bucket}', '--format=json', optional=True) is None:
                gc('storage', 'buckets', 'create', f'gs://{bucket}', '--location=US-EAST4',
                   '--uniform-bucket-level-access', '--public-access-prevention', '--soft-delete-duration=0', f'--lifecycle-file={lifecycle}')
            else:
                gc('storage', 'buckets', 'update', f'gs://{bucket}', '--uniform-bucket-level-access',
                   '--public-access-prevention', '--soft-delete-duration=0', f'--lifecycle-file={lifecycle}')
        gc('storage', 'buckets', 'add-iam-policy-binding', f'gs://{BUCKET}',
           f'--member=serviceAccount:{SA}', '--role=roles/storage.objectCreator')
        raw_trigger = gc('builds', 'triggers', 'describe', TRIGGER, f'--region={REGION}', '--format=json', optional=True)
        current = json.loads(raw_trigger) if raw_trigger else None
        identifiers = [TRIGGER]
        if current:
            identifiers.append(current['id'])
            # Preserve routing and callback secrets, but disable this obsolete entry point.
            current['disabled'] = True
            current['filter'] = 'false'
            current['description'] = 'Disabled: use explicit single-build browser evidence'
            current['build'] = config
            current['serviceAccount'] = f'projects/{PROJECT}/serviceAccounts/{SA}'
            current['substitutions'] = {'_SHA': '', '_BUCKET': BUCKET}
            path = scratch / 'trigger.json'
            path.write_text(json.dumps(current))
            path.chmod(0o600)
            gc('builds', 'triggers', 'import', f'--source={path}', f'--region={REGION}')
        matches = [h for h in hooks if hook_matches(h.get('config', {}).get('url', ''), identifiers)]
        disabled_hooks = []
        payload = scratch / 'hook.json'
        payload.write_text(json.dumps({'active': False}))
        payload.chmod(0o600)
        for hook in matches:
            invoke(['gh', 'api', f"repos/{REPO}/hooks/{hook['id']}", '--method', 'PATCH', '--input', str(payload)])
            disabled_hooks.append(hook['id'])
        plan.update({'trigger_id': current['id'] if current else None,
                     'automatic_trigger_disabled': True, 'disabled_github_hooks': disabled_hooks, 'applied': True})
    return plan


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    try:
        print(json.dumps(configure(args.apply), indent=2))
    except (RuntimeError, ValueError) as error:
        raise SystemExit(str(error))
