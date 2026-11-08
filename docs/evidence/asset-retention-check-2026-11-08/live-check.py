"""Read-only public check of the asset release a host is serving.

Usage: python3 -I live-check.py <config/assets.json> <host> <version> > live-check.json

Issues only GET requests: the home page, /healthz/, every file of <version>,
every other allowlisted immutable file (the retained releases), and one
unlisted control path. It never writes anything remote.
"""
import hashlib
import json
import re
import sys
import urllib.error
import urllib.request
from datetime import datetime, timezone

manifest_path, host, version = sys.argv[1], sys.argv[2].rstrip('/'), sys.argv[3]
manifest = json.load(open(manifest_path))
UA = {'User-Agent': 'retroboards-asset-check/1.0'}


def get(path):
    request = urllib.request.Request(host + path, headers=UA)
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            return response.status, dict(response.headers), response.read()
    except urllib.error.HTTPError as error:
        return error.code, dict(error.headers), error.read()


release = next(r for r in manifest['releases'] if r['version'] == version)
retained = sorted(u for u, meta in manifest['files'].items()
                  if meta['immutable'] and u not in release['files'])

status, headers, body = get('/')
html = body.decode('utf-8', 'replace')
home_refs = sorted(set(re.findall(r'/assets/dist/[A-Za-z0-9_-]+\.(?:js|css|woff2)', html)))
preload_refs = sorted(set(re.findall(r'/assets/dist/[A-Za-z0-9_-]+\.(?:js|css|woff2)', headers.get('Link', headers.get('link', '')))))
current_urls = set(manifest['urls'].values())


def check(url):
    code, hdrs, content = get(url)
    digest = hashlib.sha256(content).hexdigest() if code == 200 else None
    expected = manifest['files'][url]['sha256']
    return {
        'url': url, 'status': code, 'sha256': digest, 'expected': expected,
        'match': digest == expected,
        'cache_control': hdrs.get('Cache-Control', hdrs.get('cache-control')),
        'x_retroboards_cache': hdrs.get('X-RetroBoards-Cache', hdrs.get('x-retroboards-cache')),
    }


files = [check(u) for u in release['files']]
retained_checks = [check(u) for u in retained]
control_status, control_headers, _ = get('/assets/dist/app-style-rbProbe0.css')
health_status, _, health_body = get('/healthz/')

print(json.dumps({
    'version': version,
    'checked_at_utc': datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
    'host': host,
    'home_status': status,
    'home_references': home_refs,
    'home_references_all_in_release': set(home_refs) <= set(release['files']),
    'home_references_subset_of_manifest_urls': set(home_refs) <= current_urls,
    'preload_link_references': preload_refs,
    'release_files_checked': len(files),
    'release_files_matched': sum(f['match'] for f in files),
    'release_files': files,
    'retained_files_checked': len(retained_checks),
    'retained_files_matched': sum(f['match'] for f in retained_checks),
    'retained_files_mismatched': [f for f in retained_checks if not f['match']],
    'unlisted_control': {'url': '/assets/dist/app-style-rbProbe0.css', 'status': control_status,
                         'cache_control': control_headers.get('Cache-Control', control_headers.get('cache-control'))},
    'health': {'status': health_status, 'body': health_body.decode('utf-8', 'replace')[:200]},
}, indent=2))
