#!/usr/bin/env python3
"""Converge restricted browser CI. Private webhook URLs stay in process memory."""
import argparse
import json
from pathlib import Path
import re
import subprocess
import tempfile
from urllib.parse import parse_qsl, urlencode, urlsplit, urlunsplit

PROJECT = 'rising-woods-449718-v6'
REGION = 'us-east4'
REPO = 'henryperkins/community-forums'
SA = f'retroboards-browser-ci@{PROJECT}.iam.gserviceaccount.com'
BUCKET = f'{PROJECT}-retroboards-browser-evidence'
VERIFIER_SA = f'retroboards-browser-verifier@{PROJECT}.iam.gserviceaccount.com'
VERIFY_BUCKET = f'{PROJECT}-retroboards-browser-verification'
VERIFIER_ROLE_ID = 'retroboardsBrowserVerifier'
VERIFIER_ROLE = f'projects/{PROJECT}/roles/{VERIFIER_ROLE_ID}'
VERIFIER_PERMISSIONS = ['cloudbuild.builds.create', 'cloudbuild.builds.get']
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


def build_config(control_sha):
    if not re.fullmatch(r'[0-9a-f]{40}', control_sha):
        raise ValueError('A full reviewed, pushed control commit SHA is required')
    config = json.loads((HERE / 'cloudbuild.json').read_text())
    if config.get('serviceAccount') != f'projects/{PROJECT}/serviceAccounts/{VERIFIER_SA}':
        raise ValueError('The parent recipe must use the trusted verifier account')
    if config.get('substitutions', {}).get('_BUCKET') != BUCKET or config.get('substitutions', {}).get('_VERIFY_BUCKET') != VERIFY_BUCKET:
        raise ValueError('The parent recipe must use the reviewed child and verification buckets')
    config['substitutions']['_CONTROL_SHA'] = control_sha
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


def hook_target(old_url, old_id, new_id):
    parsed = urlsplit(old_url)
    if not hook_matches(old_url, (old_id, 'retroboards-main')):
        raise ValueError('Existing deploy hook is not the expected Cloud Build endpoint')
    path = f'/v1/projects/{PROJECT}/locations/{REGION}/triggers/{new_id}:webhook'
    query = parse_qsl(parsed.query, keep_blank_values=True)
    query = [(key, new_id if key == 'trigger' else value) for key, value in query]
    return urlunsplit((parsed.scheme, parsed.netloc, path, urlencode(query), ''))


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


def configure(control_sha, apply=False):
    config = build_config(control_sha)
    plan = {'service_account': SA, 'project_roles': ['roles/logging.logWriter'],
            'bucket': BUCKET, 'bucket_role': 'roles/storage.objectCreator',
            'artifact_expiry_days': 14, 'public_access_prevention': 'enforced',
            'trigger': TRIGGER, 'control_sha': control_sha,
            'scope': 'Owner-repository branch pushes and manual exact SHA; child has no production permissions',
            'parent': {'service_account': VERIFIER_SA,
                       'project_roles': ['roles/logging.logWriter', VERIFIER_ROLE],
                       'custom_role_permissions': VERIFIER_PERMISSIONS,
                       'act_as_service_account': SA,
                       'child_bucket': BUCKET, 'child_bucket_role': 'roles/storage.objectViewer',
                       'verification_bucket': VERIFY_BUCKET,
                       'verification_bucket_role': 'roles/storage.objectCreator',
                       'branch_source_execution': False,
                       'orchestration_limit': 'Build creation includes indirect trigger and legacy-build-account capability; only reviewed controller code executes here'},
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
    gc('projects', 'add-iam-policy-binding', PROJECT, f'--member=serviceAccount:{SA}', '--role=roles/logging.logWriter')
    if gc('iam', 'service-accounts', 'describe', VERIFIER_SA, '--format=json', optional=True) is None:
        gc('iam', 'service-accounts', 'create', 'retroboards-browser-verifier', '--display-name=RetroBoards trusted browser verifier')
    gc('projects', 'add-iam-policy-binding', PROJECT, f'--member=serviceAccount:{VERIFIER_SA}', '--role=roles/logging.logWriter')
    role_exists = gc('iam', 'roles', 'describe', VERIFIER_ROLE_ID, '--format=json', optional=True) is not None
    gc('iam', 'roles', 'update' if role_exists else 'create', VERIFIER_ROLE_ID,
       '--title=RetroBoards trusted browser orchestration', '--stage=GA',
       f'--permissions={",".join(VERIFIER_PERMISSIONS)}')
    gc('projects', 'add-iam-policy-binding', PROJECT,
       f'--member=serviceAccount:{VERIFIER_SA}', f'--role={VERIFIER_ROLE}')
    gc('iam', 'service-accounts', 'add-iam-policy-binding', SA,
       f'--member=serviceAccount:{VERIFIER_SA}', '--role=roles/iam.serviceAccountUser')
    with tempfile.TemporaryDirectory(prefix='retroboards-browser-ci-config-') as scratch:
        scratch = Path(scratch)
        lifecycle = scratch / 'lifecycle.json'
        lifecycle.write_text(json.dumps({'rule': [{'action': {'type': 'Delete'}, 'condition': {'age': 14}}]}))
        for bucket in (BUCKET, VERIFY_BUCKET):
            if gc('storage', 'buckets', 'describe', f'gs://{bucket}', '--format=json', optional=True) is None:
                gc('storage', 'buckets', 'create', f'gs://{bucket}', '--location=US-EAST4',
                   '--uniform-bucket-level-access', '--public-access-prevention', '--soft-delete-duration=0', f'--lifecycle-file={lifecycle}')
            else:
                gc('storage', 'buckets', 'update', f'gs://{bucket}', '--uniform-bucket-level-access',
                   '--public-access-prevention', '--soft-delete-duration=0', f'--lifecycle-file={lifecycle}')
        gc('storage', 'buckets', 'add-iam-policy-binding', f'gs://{BUCKET}',
           f'--member=serviceAccount:{SA}', '--role=roles/storage.objectCreator')
        gc('storage', 'buckets', 'add-iam-policy-binding', f'gs://{BUCKET}',
           f'--member=serviceAccount:{VERIFIER_SA}', '--role=roles/storage.objectViewer')
        gc('storage', 'buckets', 'add-iam-policy-binding', f'gs://{VERIFY_BUCKET}',
           f'--member=serviceAccount:{VERIFIER_SA}', '--role=roles/storage.objectCreator')
        trigger = {'name': TRIGGER, 'description': 'Restricted synthetic browser CI for owner-repository branch pushes',
                   'webhookConfig': {'secret': deploy['webhookConfig']['secret']},
                   'substitutions': {'_SHA': '$(body.after)', '_REF': '$(body.ref)',
                                     '_REPO': '$(body.repository.full_name)', '_CONTROL_SHA': control_sha},
                   'filter': f'_REPO == "{REPO}" && _REF.matches("^refs/heads/.*") && _SHA != "{"0" * 40}"',
                   'serviceAccount': config['serviceAccount'], 'build': config}
        path = scratch / 'trigger.json'
        path.write_text(json.dumps(trigger))
        gc('builds', 'triggers', 'import', f'--source={path}', f'--region={REGION}')
        current = json.loads(gc('builds', 'triggers', 'describe', TRIGGER, f'--region={REGION}', '--format=json'))
        # Webhook receive routing needs the NAME in both path/query. UUIDs
        # returned HTTP 200 without creating a build in the hosted diagnostic.
        target = hook_target(deploy_hooks[0]['config']['url'], deploy['id'], TRIGGER)
        matches = [h for h in hooks if hook_matches(h.get('config', {}).get('url', ''), (current['id'], TRIGGER))]
        if len(matches) > 1:
            raise RuntimeError('Duplicate browser CI webhooks; inspect privately before changing them')
        payload = {'name': 'web', 'active': True, 'events': ['push'], 'config': {'url': target, 'content_type': 'json', 'insecure_ssl': '0'}}
        private_payload = scratch / 'hook.json'
        private_payload.write_text(json.dumps(payload))
        private_payload.chmod(0o600)
        endpoint = f'repos/{REPO}/hooks' + (f"/{matches[0]['id']}" if matches else '')
        result = json.loads(invoke(['gh', 'api', endpoint, '--method', 'PATCH' if matches else 'POST', '--input', str(private_payload)]))
        plan.update({'trigger_id': current['id'], 'github_hook_id': result['id'], 'applied': True})
    return plan


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--control-sha', required=True)
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    try:
        print(json.dumps(configure(args.control_sha, args.apply), indent=2))
    except (RuntimeError, ValueError) as error:
        raise SystemExit(str(error))
