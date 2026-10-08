"""Read-only anonymous developer delivery verification; no fixture writes."""
import concurrent.futures
import datetime
import hashlib
import json
from pathlib import Path
import subprocess
import urllib.parse
import urllib.request

root = Path(__file__).resolve().parents[3]
manifest = json.loads((root / 'config/assets.json').read_text())
bases = ['http://127.0.0.1:8000',
         'https://obituaries-lucky-accordance-king.trycloudflare.com']
routes = ['/healthz', '/?pane=connections'] + [
    manifest['urls'][name] for name in ('app.css', 'app.js')]


def check(item):
    base, route = item
    with urllib.request.urlopen(base + route, timeout=25) as response:
        body = response.read()
        result = {'base': base, 'route': route, 'status': response.status,
                  'final_path': urllib.parse.urlparse(response.url).path}
    if route == '/healthz':
        result['health'] = json.loads(body)
    elif route.startswith('/?'):
        page = body.decode()
        result['current_assets_in_shell'] = all(
            manifest['urls'][name] in page for name in ('app.css', 'app.js'))
        result['guest_nav_links_present'] = all(
            f'data-directory-link="{name}"' in page
            for name in ('boards', 'tags', 'connections'))
        result['horizontal_pane_strip_absent'] = 'Board index panes' not in page
        result['private_connections'] = 'anonymous existing sign-in state only'
    else:
        result['sha256'] = hashlib.sha256(body).hexdigest()
        result['matches_delivery'] = result['sha256'] == manifest['files'][route]['sha256']
    return result


with concurrent.futures.ThreadPoolExecutor(max_workers=8) as executor:
    checks = list(executor.map(check, [(b, r) for b in bases for r in routes]))
result = {
    'checked_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
    'revision': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root, text=True).strip(),
    'working_tree': 'uncommitted changes',
    'asset_build': manifest['version'], 'checks': checks,
}
Path(__file__).with_name('developer-delivery-repair.json').write_text(
    json.dumps(result, indent=2) + '\n')
assert all(c['status'] == 200 for c in checks)
assert all(c.get('matches_delivery', True) for c in checks)
assert all(c.get('current_assets_in_shell', True) for c in checks)
assert all(c.get('guest_nav_links_present', True) for c in checks)
assert all(c.get('horizontal_pane_strip_absent', True) for c in checks)
assert all(c.get('health', {}).get('status', 'ok') == 'ok' for c in checks)
assert all(c.get('health', {}).get('database', 'ok') == 'ok' for c in checks)
print(f'{len(checks)} delivery checks pass; build {manifest["version"]}')
