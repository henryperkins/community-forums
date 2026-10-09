#!/usr/bin/env python3
"""Require an outer Docker success receipt and a complete readable archive."""
import argparse
from contextlib import contextmanager
import gzip
import json
from pathlib import Path
import tarfile
import tempfile

REQUIRED = {
    'unified-notifications-and-settings/browser-results.json',
    'image-upload-reliability/runtime-limits.json',
    *[f'image-upload-reliability/{project}-published.png'
      for project in ('desktop', 'mobile', 'webkit-mobile')],
}
ROOTS = {'browser', 'unified-notifications-and-settings', 'image-upload-reliability'}
MAX_MEMBERS = 1024
MAX_MEMBER_BYTES = 20 * 1024 * 1024
MAX_ARCHIVE_BYTES = 256 * 1024 * 1024


def archive_members(archive):
    """Bound work before materializing or decompressing an untrusted bundle."""
    members = []
    total = 0
    for member in archive:
        total += member.size
        if (len(members) >= MAX_MEMBERS or not member.isfile()
                or member.size < 0 or member.size > MAX_MEMBER_BYTES
                or total > MAX_ARCHIVE_BYTES):
            return None
        members.append(member)
    return members


@contextmanager
def bounded_archive(path):
    # Bound the entire inflated stream, including PAX/long-name headers that
    # tarfile parses before yielding an ordinary member to archive_members().
    with tempfile.TemporaryFile() as plain:
        total = 0
        with gzip.open(path, 'rb') as compressed:
            while True:
                chunk = compressed.read(min(1024 * 1024, MAX_ARCHIVE_BYTES - total + 1))
                if not chunk:
                    break
                total += len(chunk)
                if total > MAX_ARCHIVE_BYTES:
                    raise ValueError('Inflated archive exceeds verification budget')
                plain.write(chunk)
        plain.seek(0)
        with tarfile.open(fileobj=plain, mode='r:') as archive:
            yield archive


def verify(results, outer_result, source_sha, control_sha):
    results = Path(results)
    outer = json.loads(Path(outer_result).read_text())
    if (not isinstance(outer, dict) or type(outer.get('docker_exit_code')) is not int
            or outer['docker_exit_code'] != 0):
        return False
    record = json.loads((results / 'result.json').read_text())
    if (not isinstance(record, dict) or record.get('passed') is not True or type(record.get('exit_code')) is not int
            or record['exit_code'] != 0 or record.get('last_stage') != 'complete'
            or record.get('source_sha') != source_sha or record.get('control_sha') != control_sha):
        return False
    names = record.get('artifacts')
    if (not isinstance(names, list) or not names or len(names) >= MAX_MEMBERS
            or any(not isinstance(name, str) or not name for name in names)
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
    if archive_path.is_symlink() or archive_path.stat().st_size > MAX_ARCHIVE_BYTES:
        return False
    with bounded_archive(archive_path) as archive:
        members = archive_members(archive)
        if (members is None or {member.name for member in members} != {*names, 'result.json'}
                or len(members) != len(names) + 1):
            return False
        for member in members:
            data = archive.extractfile(member).read()
            if member.name.endswith('.json'):
                value = json.loads(data)
                if member.name == 'result.json' and value != record:
                    return False
                if (member.name == 'unified-notifications-and-settings/browser-results.json'
                        and (not isinstance(value, dict) or value.get('completed') is not True)):
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
