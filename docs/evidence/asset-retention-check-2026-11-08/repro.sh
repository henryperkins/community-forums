#!/usr/bin/env bash
# Bounded reproduction: does the next asset-changing build drop the asset
# release that is currently live, and does recording it first retain it?
#
# Runs ONLY inside throwaway `git archive` copies under a mktemp directory, so
# the source checkout's public/assets/dist, .build/static and config/assets.json
# are never written. node_modules is hard-link copied into each scratch tree.
#
# Usage: docs/evidence/asset-retention-check-2026-11-08/repro.sh <checkout> <out-dir>
set -euo pipefail

CHECKOUT=$(cd "$1" && pwd)
OUT=$(mkdir -p "$2" && cd "$2" && pwd)
LIVE=0ab71f5ca65e7fa0
# Same filesystem as the checkout so node_modules can be hard-link copied.
SCRATCH=$(mktemp -d "$(dirname "$CHECKOUT")/.rb-asset-repro.XXXXXX")
trap 'rm -rf "$SCRATCH"' EXIT

REV=$(git -C "$CHECKOUT" rev-parse HEAD)
{
  echo "revision=$REV"
  echo "node=$(node --version)"
  echo "vite=$(node -p "require('$CHECKOUT/node_modules/vite/package.json').version")"
  echo "scratch=$SCRATCH"
} > "$OUT/environment.txt"

fresh_copy() {
  local dir="$SCRATCH/$1"
  mkdir -p "$dir"
  git -C "$CHECKOUT" archive "$REV" | tar -x -C "$dir"
  cp -al "$CHECKOUT/node_modules" "$dir/node_modules"
  echo "$dir"
}

# Asset-changing edits that survive minification, one per exclusive output.
edit_app_css()   { printf '\n.rb-repro-probe{order:1}\n' >> "$1/public/assets/app.css"; }
edit_all_four()  {
  edit_app_css "$1"
  printf '\n.rb-repro-probe-base{order:2}\n'  >> "$1/public/assets/forum-base.css"
  printf '\n.rb-repro-probe-theme{order:3}\n' >> "$1/public/assets/forum-theme.css"
  printf '\nwindow.__rbReproProbe = 1;\n'       >> "$1/public/assets/app.js"
}

summarize() { # <tree> <label>
  python3 - "$1" "$2" "$LIVE" <<'PY'
import json, os, sys
tree, label, live = sys.argv[1], sys.argv[2], sys.argv[3]
m = json.load(open(os.path.join(tree, 'config/assets.json')))
committed = json.load(open(os.path.join(os.environ['CHECKOUT'], 'config/assets.json')))
live_files = next(r['files'] for r in committed['releases'] if r['version'] == live)
rows = []
for url in live_files:
    rel = url[len('/assets/dist/'):]
    rows.append({
        'url': url,
        'in_manifest_files': url in m['files'],
        'in_public_dist': os.path.isfile(os.path.join(tree, 'public/assets/dist', rel)),
        'in_build_static': os.path.isfile(os.path.join(tree, '.build/static/assets/dist', rel)),
    })
print(json.dumps({
    'label': label,
    'version': m['version'],
    'releases': [r['version'] for r in m['releases']],
    'deployedReleases': [r['version'] for r in m['deployedReleases']],
    'manifest_file_count': len(m['files']),
    'live_release_files_missing': [r['url'] for r in rows if not (r['in_manifest_files'] and r['in_public_dist'] and r['in_build_static'])],
    'live_release_files': rows,
}, indent=2))
PY
}
export CHECKOUT

run() { # <tree> <log-name> <args...>
  local tree=$1 log=$2; shift 2
  (cd "$tree" && node bin/build-assets.mjs "$@") > "$OUT/$log.log" 2>&1 \
    && echo "exit=0" >> "$OUT/$log.log" || { echo "exit=$?" >> "$OUT/$log.log"; }
}

# 0. The scratch copy reproduces the committed outputs and version.
A=$(fresh_copy a)
run "$A" 0-check --check

# 1a. No record, then one asset-changing edit (app.css only).
edit_app_css "$A"
run "$A" 1a-build-unrecorded-app-css-edit
summarize "$A" 1a-unrecorded-app-css-edit > "$OUT/1a-unrecorded-app-css-edit.json"

# 1b. No record, then edits to all four exclusive outputs.
B=$(fresh_copy b)
edit_all_four "$B"
run "$B" 1b-build-unrecorded-all-four
summarize "$B" 1b-unrecorded-all-four > "$OUT/1b-unrecorded-all-four.json"

# 2. Record the live release first, then the same all-four edit.
C=$(fresh_copy c)
run "$C" 2-record --record-release "$LIVE"
summarize "$C" 2-after-record > "$OUT/2-after-record.json"
cp "$C/config/assets.json" "$OUT/2-after-record-manifest.json"
run "$C" 2-check-after-record --check
edit_all_four "$C"
run "$C" 2-build-recorded-all-four
summarize "$C" 2-recorded-then-all-four > "$OUT/2-recorded-then-all-four.json"

# 3. Files the record step itself retires (exclusive to the oldest pin).
python3 - "$CHECKOUT/config/assets.json" "$OUT/2-after-record-manifest.json" > "$OUT/3-retired-by-record.json" <<'PY'
import json, sys
before, after = (json.load(open(p)) for p in sys.argv[1:3])
gone = sorted(set(before['files']) - set(after['files']))
print(json.dumps({
    'deployedReleases_before': [r['version'] for r in before['deployedReleases']],
    'deployedReleases_after': [r['version'] for r in after['deployedReleases']],
    'files_removed_from_allowlist': gone,
}, indent=2))
PY
echo "done: $OUT"
