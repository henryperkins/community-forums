"""Bounded verification of branch-provided artifacts, without cloud access."""
import importlib.util
import gzip
import io
import json
from pathlib import Path
import tarfile
import tempfile
import unittest
from unittest.mock import patch

SPEC = importlib.util.spec_from_file_location('ci_enforce', Path(__file__).with_name('enforce.py'))
ENFORCE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(ENFORCE)


def member(name, size=0, kind=tarfile.REGTYPE):
    item = tarfile.TarInfo(name)
    item.size, item.type = size, kind
    return item


class ArchiveContracts(unittest.TestCase):
    def test_entire_inflated_stream_is_bounded_before_tar_metadata_parsing(self):
        with tempfile.TemporaryDirectory() as scratch:
            path = Path(scratch) / 'metadata-bomb.tar.gz'
            with gzip.open(path, 'wb') as compressed:
                compressed.write(b'\0' * 2049)
            with patch.object(ENFORCE, 'MAX_ARCHIVE_BYTES', 2048), \
                 patch.object(ENFORCE.tarfile, 'open') as parse:
                with self.assertRaisesRegex(ValueError, 'Inflated archive'):
                    with ENFORCE.bounded_archive(path):
                        self.fail('Inflation budget must reject before yielding')
                parse.assert_not_called()

    def test_individual_and_total_uncompressed_limits(self):
        self.assertIsNone(ENFORCE.archive_members([member('oversized', ENFORCE.MAX_MEMBER_BYTES + 1)]))
        allowed = [member(str(i), ENFORCE.MAX_MEMBER_BYTES) for i in range(12)]
        self.assertEqual(len(ENFORCE.archive_members(allowed)), 12)
        self.assertIsNone(ENFORCE.archive_members(allowed + [member('13', ENFORCE.MAX_MEMBER_BYTES)]))

    def test_member_limit_stops_reading_the_untrusted_iterator(self):
        def stream():
            for i in range(ENFORCE.MAX_MEMBERS + 1):
                yield member(str(i))
            self.fail('Verifier consumed beyond the member limit')
        self.assertIsNone(ENFORCE.archive_members(stream()))
        self.assertEqual(len(ENFORCE.archive_members([member(str(i)) for i in range(ENFORCE.MAX_MEMBERS)])),
                         ENFORCE.MAX_MEMBERS)

    def test_symlinks_hardlinks_directories_and_negative_sizes_are_rejected(self):
        for kind in (tarfile.SYMTYPE, tarfile.LNKTYPE, tarfile.DIRTYPE):
            with self.subTest(kind=kind):
                self.assertIsNone(ENFORCE.archive_members([member('unsafe', kind=kind)]))
        self.assertIsNone(ENFORCE.archive_members([member('invalid', -1)]))

    def fixture(self, root, unified=None, extra_member=None):
        names = sorted(ENFORCE.REQUIRED | {'browser/desktop/home.png'})
        record = {'passed': True, 'exit_code': 0, 'last_stage': 'complete',
                  'source_sha': 'a' * 40, 'control_sha': 'b' * 40,
                  'artifact_count': len(names), 'artifacts': names}
        (root / 'result.json').write_text(json.dumps(record))
        (root / 'outer.json').write_text('{"docker_exit_code":0}')
        with tarfile.open(root / 'evidence.tar.gz', 'w:gz') as archive:
            for name in [*names, 'result.json']:
                if name == 'result.json':
                    data = json.dumps(record).encode()
                elif name == 'unified-notifications-and-settings/browser-results.json':
                    data = json.dumps({'completed': True} if unified is None else unified).encode()
                elif name.endswith('.json'):
                    data = b'{"php":"8.2.34"}'
                else:
                    data = b'\x89PNG\r\n\x1a\nsynthetic'
                archive.addfile(member(name, len(data)), io.BytesIO(data))
            if extra_member:
                archive.addfile(extra_member)

    def test_valid_bundle_passes_and_non_object_unified_receipts_fail(self):
        with tempfile.TemporaryDirectory() as scratch:
            root = Path(scratch)
            self.fixture(root)
            self.assertTrue(ENFORCE.verify(root, root / 'outer.json', 'a' * 40, 'b' * 40))
            for payload in ([], 'complete', True, 1):
                self.fixture(root, unified=payload)
                self.assertFalse(ENFORCE.verify(root, root / 'outer.json', 'a' * 40, 'b' * 40))

    def test_non_object_outer_or_manifest_cannot_pass(self):
        with tempfile.TemporaryDirectory() as scratch:
            root = Path(scratch)
            self.fixture(root)
            (root / 'outer.json').write_text('[]')
            self.assertFalse(ENFORCE.verify(root, root / 'outer.json', 'a' * 40, 'b' * 40))
            (root / 'outer.json').write_text('{"docker_exit_code":0}')
            (root / 'result.json').write_text('true')
            self.assertFalse(ENFORCE.verify(root, root / 'outer.json', 'a' * 40, 'b' * 40))

    def test_archive_path_traversal_remains_data_and_is_rejected_without_extraction(self):
        with tempfile.TemporaryDirectory() as scratch:
            root = Path(scratch)
            sentinel = root / 'escaped'
            self.fixture(root, extra_member=member('../escaped'))
            self.assertFalse(ENFORCE.verify(root, root / 'outer.json', 'a' * 40, 'b' * 40))
            self.assertFalse(sentinel.exists())


if __name__ == '__main__':
    unittest.main()
