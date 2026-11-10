"""Read-only public check of the asset release a host is serving.

Usage: python3 -I live-check.py <config/assets.json> <host> <version> > live-check.json

Issues only GET requests: the home page, /healthz/, every file of <version>,
every other allowlisted immutable file (the retained releases), and one
unlisted control path. It never writes anything remote.

The JSON report goes to stdout and failures are also listed on stderr. Exit status:
  0  every check passed;
  1  a check failed (listed under "failures"), including any network error;
  2  usage error: wrong arguments, an unreadable manifest, a host that is not
     http(s), or a version the manifest does not list.

A check fails when:
  - the home page is not 200, references no /assets/dist/ file, or references
    (in its HTML or its Link preload header) a file outside <version>;
  - any file of <version> or of a retained release is not 200, differs from the
    manifest SHA-256, or lacks the Worker's X-RetroBoards-Cache: STATIC and
    immutable caching;
  - the unlisted control path is not a 404 with no-store caching;
  - /healthz/ is not 200 with "status": "ok".
"""
import hashlib
import http.client
import json
import re
import sys
import urllib.error
import urllib.request
from datetime import datetime, timezone

USAGE = 'Usage: python3 -I live-check.py <config/assets.json> <host> <version> > live-check.json'
UA = {'User-Agent': 'retroboards-asset-check/1.0'}
CONTROL = '/assets/dist/app-style-rbProbe0.css'
ASSET_REF = re.compile(r'/assets/dist/[A-Za-z0-9_-]+\.(?:js|css|woff2)')


def usage_error(message):
    print(f'live-check.py: {message}\n{USAGE}', file=sys.stderr)
    sys.exit(2)


if len(sys.argv) != 4:
    usage_error('expected exactly three arguments')
manifest_path, host, version = sys.argv[1], sys.argv[2].rstrip('/'), sys.argv[3]
if not host.startswith(('https://', 'http://')):
    usage_error(f'host must start with https:// or http://, not {host!r}')
try:
    with open(manifest_path, encoding='utf-8') as handle:
        manifest = json.load(handle)
except (OSError, ValueError) as error:
    usage_error(f'cannot read {manifest_path}: {error}')
release = next((r for r in manifest.get('releases', []) if r.get('version') == version), None)
if release is None:
    usage_error(f'version {version!r} is not a release in {manifest_path}')


def describe(error):
    return f'{type(error).__name__}: {getattr(error, "reason", error)}'


def get(path):
    """Return (status, headers, body, error). Status is None when no response arrived.

    Headers stay an http.client.HTTPMessage, so lookups are case-insensitive.
    """
    request = urllib.request.Request(host + path, headers=UA)
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            return response.status, response.headers, response.read(), None
    except urllib.error.HTTPError as error:
        try:
            return error.code, error.headers, error.read(), None
        except (OSError, http.client.HTTPException) as read_error:
            return error.code, error.headers, b'', describe(read_error)
    except (OSError, ValueError, http.client.HTTPException) as error:
        # URLError (DNS, refused, TLS) and timeouts are OSErrors.
        return None, http.client.HTTPMessage(), b'', describe(error)


failures = []
release_files = set(release['files'])

status, headers, body, error = get('/')
html = body.decode('utf-8', 'replace')
home_refs = sorted(set(ASSET_REF.findall(html)))
preload_refs = sorted(set(ASSET_REF.findall(' '.join(headers.get_all('Link') or []))))
if error:
    failures.append(f'/: {error}')
elif status != 200:
    failures.append(f'/: status {status}, expected 200')
elif not home_refs:
    failures.append('/: the page references no /assets/dist/ file')
for label, refs in (('page', home_refs), ('Link preload header', preload_refs)):
    outside = sorted(set(refs) - release_files)
    if outside:
        failures.append(f'/: the {label} references files outside release {version}: {", ".join(outside)}')


def check(url):
    code, hdrs, content, problem = get(url)
    expected = manifest.get('files', {}).get(url, {}).get('sha256')
    digest = hashlib.sha256(content).hexdigest() if code == 200 and problem is None else None
    row = {
        'url': url, 'status': code, 'sha256': digest, 'expected': expected,
        'match': digest is not None and digest == expected,
        'cache_control': hdrs.get('Cache-Control'),
        'x_retroboards_cache': hdrs.get('X-RetroBoards-Cache'),
    }
    if problem:
        row['error'] = problem
        failures.append(f'{url}: {problem}')
    elif code != 200:
        failures.append(f'{url}: status {code}, expected 200')
    else:
        if not row['match']:
            failures.append(f'{url}: SHA-256 {digest} differs from the manifest ({expected})')
        if row['x_retroboards_cache'] != 'STATIC':
            failures.append(f'{url}: X-RetroBoards-Cache is {row["x_retroboards_cache"]!r}, expected STATIC')
        if 'immutable' not in (row['cache_control'] or ''):
            failures.append(f'{url}: Cache-Control {row["cache_control"]!r} is not immutable')
    return row


retained = sorted(u for u, meta in manifest['files'].items()
                  if meta['immutable'] and u not in release_files)
files = [check(u) for u in release['files']]
retained_checks = [check(u) for u in retained]

control_status, control_headers, _, control_error = get(CONTROL)
control_cache = control_headers.get('Cache-Control')
if control_error:
    failures.append(f'{CONTROL}: {control_error}')
elif control_status != 404 or 'no-store' not in (control_cache or ''):
    failures.append(f'{CONTROL}: status {control_status} with Cache-Control {control_cache!r}, '
                    'expected 404 no-store for an unlisted path')

health_status, _, health_body, health_error = get('/healthz/')
health_text = health_body.decode('utf-8', 'replace')
try:
    health = json.loads(health_text)
except ValueError:
    health = None
if health_error:
    failures.append(f'/healthz/: {health_error}')
elif health_status != 200 or not isinstance(health, dict) or health.get('status') != 'ok':
    failures.append(f'/healthz/: status {health_status} with body {health_text[:200]!r}, expected 200 and "status": "ok"')

print(json.dumps({
    'version': version,
    'checked_at_utc': datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
    'host': host,
    'ok': not failures,
    'failures': failures,
    'home_status': status,
    'home_references': home_refs,
    'home_references_all_in_release': set(home_refs) <= release_files,
    'home_references_subset_of_manifest_urls': set(home_refs) <= set(manifest['urls'].values()),
    'preload_link_references': preload_refs,
    'release_files_checked': len(files),
    'release_files_matched': sum(f['match'] for f in files),
    'release_files': files,
    'retained_files_checked': len(retained_checks),
    'retained_files_matched': sum(f['match'] for f in retained_checks),
    'retained_files_mismatched': [f for f in retained_checks if not f['match']],
    'unlisted_control': {'url': CONTROL, 'status': control_status, 'cache_control': control_cache},
    'health': {'status': health_status, 'body': health_text[:200]},
}, indent=2))

if failures:
    print(f'live-check.py: {len(failures)} check(s) failed:', file=sys.stderr)
    for failure in failures:
        print(f'  - {failure}', file=sys.stderr)
    sys.exit(1)
print(f'live-check.py: release {version}: {len(files)}/{len(files)} files and '
      f'{len(retained_checks)}/{len(retained_checks)} retained files match; control 404; health ok.',
      file=sys.stderr)
