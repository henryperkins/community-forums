#!/usr/bin/env python3
"""Require an outer Docker success receipt and a complete readable archive."""
import argparse
import json
from pathlib import Path
import tarfile

REQUIRED = {
    'unified-notifications-and-settings/browser-results.json',
    'image-upload-reliability/runtime-limits.json',
    *[f'image-upload-reliability/{project}-published.png'
      for project in ('desktop', 'mobile', 'webkit-mobile')],
}
ROOTS = {'browser', 'unified-notifications-and-settings', 'image-upload-reliability'}


def verify(results, outer_result, source_sha, control_sha):
    results = Path(results)
    outer = json.loads(Path(outer_result).read_text())
    if type(outer.get('docker_exit_code')) is not int or outer['docker_exit_code'] != 0:
        return False
    record = json.loads((results / 'result.json').read_text())
    if (record.get('passed') is not True or type(record.get('exit_code')) is not int
            or record['exit_code'] != 0 or record.get('last_stage') != 'complete'
            or record.get('source_sha') != source_sha or record.get('control_sha') != control_sha):
        return False
    names = record.get('artifacts')
    if (not isinstance(names, list) or not names or any(not isinstance(name, str) or not name for name in names)
            or len(names) != len(set(names)) or record.get('artifact_count') != len(names)
            or not REQUIRED.issubset(names)
            or not any(name.startswith('browser/') and name.endswith('.png') for name in names)):
        return False
    for name in names:
        path = Path(name)
        if (path.is_absolute() or '..' in path.parts or path.parts[0] not in ROOTS
                or path.suffix not in ('.png', '.json')):
            return False
    archive_path = results / 'evidence.tar.gz'
    if archive_path.is_symlink():
        return False
    with tarfile.open(archive_path, 'r:gz') as archive:
        members = archive.getmembers()
        if ({member.name for member in members} != {*names, 'result.json'}
                or len(members) != len(names) + 1
                or any(not member.isfile() or member.size > 20 * 1024 * 1024 for member in members)):
            return False
        for member in members:
            data = archive.extractfile(member).read()
            if member.name.endswith('.json'):
                value = json.loads(data)
                if member.name == 'result.json' and value != record:
                    return False
                if member.name == 'unified-notifications-and-settings/browser-results.json' and value.get('completed') is not True:
                    return False
            elif not data.startswith(b'\x89PNG\r\n\x1a\n'):
                return False
    return True


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--results', required=True)
    parser.add_argument('--outer-result', required=True)
    parser.add_argument('--source-sha', required=True)
    parser.add_argument('--control-sha', required=True)
    args = parser.parse_args()
    try:
        passed = verify(args.results, args.outer_result, args.source_sha, args.control_sha)
    except (OSError, ValueError, TypeError, AttributeError, EOFError, tarfile.TarError):
        passed = False
    if not passed:
        raise SystemExit('Browser evidence failed: outer Docker success and complete archive are required')
