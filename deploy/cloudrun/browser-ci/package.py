#!/usr/bin/env python3
"""Publish only fresh synthetic screenshots and redacted JSON, never host env/traces."""
import argparse
import json
import os
import re
from pathlib import Path
import tarfile
import tempfile
from datetime import datetime, timezone

ROOTS = ('browser', 'unified-notifications-and-settings', 'image-upload-reliability')
PRIVATE_KEYS = {'env', 'environment', 'headers', 'cookies', 'storagestate', 'auth', 'authentication'}


def private_key(key, item):
    normalized = re.sub(r'[^a-z0-9]', '', key.lower())
    return normalized in PRIVATE_KEYS or (any(word in normalized for word in
        ('password', 'secret', 'token', 'apikey', 'authorization', 'credential', 'accesskey', 'privatekey', 'bearer'))
        and not isinstance(item, bool))


def redact(value):
    if isinstance(value, dict):
        return {key: redact(item) for key, item in value.items()
                if not private_key(key, item)}
    if isinstance(value, list):
        return [redact(item) for item in value]
    return value


def build_package(source, output, exit_code, stage):
    source, output = Path(source).resolve(), Path(output).resolve()
    output.mkdir(parents=True, exist_ok=True)
    included = []
    invalid_json = 0
    with tempfile.TemporaryDirectory(prefix='retroboards-ci-publish-') as scratch:
        scratch = Path(scratch)
        for name in ROOTS:
            root = source / 'docs' / 'evidence' / name
            if not root.is_dir() or root.is_symlink():
                continue
            if any(parent.is_symlink() for parent in (root.parent, root.parent.parent)):
                raise ValueError('Evidence output has a symlinked parent')
            for path in sorted(root.rglob('*')):
                if not path.is_file() or path.is_symlink() or path.suffix not in ('.png', '.json'):
                    continue
                if not path.resolve().is_relative_to(root.resolve()):
                    raise ValueError('Evidence escaped its synthetic output directory')
                if path.stat().st_size > 20 * 1024 * 1024:
                    raise ValueError('Individual synthetic artifact exceeds 20 MiB')
                relative = Path(name) / path.relative_to(root)
                target = scratch / relative
                target.parent.mkdir(parents=True, exist_ok=True)
                if path.suffix == '.json':
                    try:
                        payload = redact(json.loads(path.read_text()))
                    except (ValueError, UnicodeError):
                        invalid_json += 1
                        continue
                    target.write_text(json.dumps(payload, indent=2) + '\n')
                else:
                    data = path.read_bytes()
                    if not data.startswith(b'\x89PNG\r\n\x1a\n'):
                        raise ValueError('Screenshot is not a PNG')
                    target.write_bytes(data)
                included.append(str(relative))
        required = [
            'unified-notifications-and-settings/browser-results.json',
            'image-upload-reliability/runtime-limits.json',
            *[f'image-upload-reliability/{project}-published.png'
              for project in ('desktop', 'mobile', 'webkit-mobile')],
        ]
        missing = [name for name in required if name not in included]
        if not any(name.startswith('browser/') and name.endswith('.png') for name in included):
            missing.append('browser/*.png')
        unified = scratch / 'unified-notifications-and-settings/browser-results.json'
        if unified.is_file():
            value = json.loads(unified.read_text())
            if not isinstance(value, dict) or value.get('completed') is not True:
                missing.append('unified-notifications-and-settings: completed=true')
        result = {'completed_at': datetime.now(timezone.utc).isoformat(),
                  'source_sha': os.environ.get('CI_SOURCE_SHA', ''),
                  'control_sha': os.environ.get('CI_CONTROL_SHA', ''),
                  'exit_code': exit_code, 'last_stage': stage,
                  'passed': exit_code == 0 and stage == 'complete' and not missing and invalid_json == 0,
                  'omitted_invalid_json_count': invalid_json,
                  'missing_required_artifacts': missing,
                  'artifact_count': len(included), 'artifacts': included,
                  'scope': 'Synthetic local databases and mailer; no production credentials',
                  'omitted': ['host environment', 'request headers/cookies', 'traces', 'raw logs']}
        # A successful public result may exist only after the complete archive.
        staged_result = scratch / 'result.json'
        staged_result.write_text(json.dumps(result, indent=2) + '\n')
        temporary_archive = output / '.evidence.tar.gz.tmp'
        with tarfile.open(temporary_archive, 'w:gz') as archive:
            archive.add(staged_result, arcname='result.json')
            for relative in included:
                archive.add(scratch / relative, arcname=relative)
        os.replace(temporary_archive, output / 'evidence.tar.gz')
        temporary_result = output / '.result.json.tmp'
        temporary_result.write_text(staged_result.read_text())
        os.replace(temporary_result, output / 'result.json')
    return result


def package(source, output, exit_code, stage):
    output = Path(output).resolve()
    output.mkdir(parents=True, exist_ok=True)
    for name in ('result.json', 'evidence.tar.gz', '.result.json.tmp', '.evidence.tar.gz.tmp'):
        (output / name).unlink(missing_ok=True)
    try:
        return build_package(source, output, exit_code, stage)
    except Exception:
        # Preserve the actual suite stage, never exception text or private values.
        for name in ('evidence.tar.gz', '.result.json.tmp', '.evidence.tar.gz.tmp'):
            (output / name).unlink(missing_ok=True)
        result = {'passed': False, 'exit_code': exit_code, 'last_stage': stage,
                  'packaging_failed': True, 'artifact_count': 0, 'artifacts': [],
                  'source_sha': os.environ.get('CI_SOURCE_SHA', ''),
                  'control_sha': os.environ.get('CI_CONTROL_SHA', '')}
        temporary = output / '.result.json.tmp'
        temporary.write_text(json.dumps(result, indent=2) + '\n')
        os.replace(temporary, output / 'result.json')
        return result


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', required=True)
    parser.add_argument('--output', required=True)
    parser.add_argument('--exit-code', type=int, required=True)
    parser.add_argument('--stage', required=True)
    args = parser.parse_args()
    result = package(args.source, args.output, args.exit_code, args.stage)
    raise SystemExit(1 if args.stage == 'complete' and not result['passed'] else 0)
