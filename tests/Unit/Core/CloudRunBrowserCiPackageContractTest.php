<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class CloudRunBrowserCiPackageContractTest extends TestCase
{
    public function test_non_object_unified_results_fail_completion_and_preserve_the_diagnostic_archive(): void
    {
        $root = dirname(__DIR__, 3);
        $script = <<<'PY'
import importlib.util, json, pathlib, sys, tarfile, tempfile
spec = importlib.util.spec_from_file_location('ci_package', sys.argv[1])
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
records = []
with tempfile.TemporaryDirectory(prefix='rb-package-shape-') as directory:
    root = pathlib.Path(directory)
    source, output = root / 'source', root / 'output'
    evidence = source / 'docs' / 'evidence'
    files = {
        'browser/desktop/home.png': b'\x89PNG\r\n\x1a\nsynthetic',
        'image-upload-reliability/runtime-limits.json': b'{"php":"8.2.34"}',
        **{f'image-upload-reliability/{project}-published.png': b'\x89PNG\r\n\x1a\nsynthetic'
           for project in ('desktop', 'mobile', 'webkit-mobile')},
    }
    for name, content in files.items():
        path = evidence / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(content)
    unified = evidence / 'unified-notifications-and-settings/browser-results.json'
    unified.parent.mkdir(parents=True)
    for value in ([], None):
        unified.write_text(json.dumps(value))
        record = module.package(source, output, 0, 'complete')
        with tarfile.open(output / 'evidence.tar.gz', 'r:gz') as archive:
            preserved = json.load(archive.extractfile('unified-notifications-and-settings/browser-results.json'))
            archive_record = json.load(archive.extractfile('result.json'))
        records.append({
            'passed': record['passed'],
            'missing': record['missing_required_artifacts'],
            'packaging_failed': record.get('packaging_failed', False),
            'artifact_count': record['artifact_count'],
            'preserved': preserved == value,
            'archive_matches_result': archive_record == record,
        })
print(json.dumps(records))
PY;
        $process = proc_open(['python3', '-B', '-c', $script, $root . '/deploy/cloudrun/browser-ci/package.py'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $root);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);
        self::assertSame('', $stderr);
        $records = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(2, $records);
        foreach ($records as $record) {
            self::assertFalse($record['passed']);
            self::assertFalse($record['packaging_failed']);
            self::assertSame(['unified-notifications-and-settings: completed=true'], $record['missing']);
            self::assertSame(6, $record['artifact_count']);
            self::assertTrue($record['preserved']);
            self::assertTrue($record['archive_matches_result']);
        }
    }
}
