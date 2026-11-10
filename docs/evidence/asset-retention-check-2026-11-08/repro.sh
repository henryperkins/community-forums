#!/usr/bin/env bash
# Bounded reproduction: does the next asset-changing build drop the asset
# release that is currently live, and does recording it first retain it?
#
# Runs ONLY inside throwaway `git archive` copies under a mktemp directory, so
# the source checkout's public/assets/dist, .build/static and config/assets.json
# are never written. node_modules is hard-link copied into each scratch tree.
#
# Usage: docs/evidence/asset-retention-check-2026-11-08/repro.sh <checkout> <out-dir> [revision]
#
# <revision> defaults to eeceed78, the reviewed revision before the record. At a
# revision that already records the release, the hazard no longer reproduces and
# the step 1 expectations fail by design.
#
# Exits non-zero when a build or record step fails, or when a scenario's outcome
# differs from the expectation stated beside it. Each step's log ends in exit=<status>.
set -euo pipefail

if [ $# -lt 2 ] || [ $# -gt 3 ]; then
  echo "Usage: $0 <checkout> <out-dir> [revision]" >&2
  exit 2
fi
CHECKOUT=$(cd "$1" && pwd)
OUT=$(mkdir -p "$2" && cd "$2" && pwd)
REV=$(git -C "$CHECKOUT" rev-parse --verify "${3:-eeceed780aa9ec92e2a85e9554020a6c6d85d503}^{commit}")
LIVE=0ab71f5ca65e7fa0
# Same filesystem as the checkout so node_modules can be hard-link copied.
SCRATCH=$(mktemp -d "$(dirname "$CHECKOUT")/.rb-asset-repro.XXXXXX")
trap 'rm -rf "$SCRATCH"' EXIT
COMMITTED="$SCRATCH/committed-assets.json"
git -C "$CHECKOUT" show "$REV:config/assets.json" > "$COMMITTED"

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
  python3 -I - "$1" "$2" "$LIVE" "$COMMITTED" <<'PY'
import json, os, sys
tree, label, live, committed_path = sys.argv[1:5]
with open(os.path.join(tree, 'config/assets.json')) as handle:
    m = json.load(handle)
with open(committed_path) as handle:
    committed = json.load(handle)
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

# <summary.json> <version: same|new> <live files missing: none|some|exclusive>
#   same/new   the build's version is / is not the live release's version;
#   none       all of the live release's files remain, and the release is pinned;
#   some       at least one is gone, and only files no pinned release shares;
#   exclusive  exactly the files no pinned release shares are gone.
expect() {
  python3 -I - "$1" "$2" "$3" "$LIVE" "$COMMITTED" <<'PY'
import json, sys
summary_path, version_want, missing_want, live, committed_path = sys.argv[1:6]
with open(summary_path) as handle:
    s = json.load(handle)
with open(committed_path) as handle:
    committed = json.load(handle)
live_files = next(r['files'] for r in committed['releases'] if r['version'] == live)
pinned = {u for r in committed['deployedReleases'] for u in r['files']}
exclusive = sorted(set(live_files) - pinned)
missing = sorted(s['live_release_files_missing'])
problems = []
if missing_want != 'none' and not exclusive:
    problems.append(f'{live} is already pinned at this revision, so no build can drop its files')
if (s['version'] == live) != (version_want == 'same'):
    problems.append(f"version {s['version']} should be {'the' if version_want == 'same' else 'a version other than the'} live release {live}")
if missing_want == 'none' and (missing or live not in s['deployedReleases']):
    problems.append(f'expected every file kept and {live} pinned; missing {missing}, pins {s["deployedReleases"]}')
if missing_want == 'some' and not (missing and set(missing) <= set(exclusive)):
    problems.append(f'expected some of the exclusive files {exclusive} missing, and nothing else; missing {missing}')
if missing_want == 'exclusive' and missing != exclusive:
    problems.append(f'expected exactly the exclusive files {exclusive} missing; missing {missing}')
if problems:
    sys.exit(f"repro.sh: {s['label']}: " + '; '.join(problems))
print(f"repro.sh: {s['label']}: as expected (version {s['version']}, {len(missing)} of {live}'s files missing)", file=sys.stderr)
PY
}

run() { # <tree> <log-name> <args...>: logs and returns the builder's exit status
  local tree=$1 log=$2 status=0; shift 2
  (cd "$tree" && node bin/build-assets.mjs "$@") > "$OUT/$log.log" 2>&1 || status=$?
  echo "exit=$status" >> "$OUT/$log.log"
  if [ "$status" -ne 0 ]; then
    echo "repro.sh: $log failed with exit $status; see $OUT/$log.log" >&2
  fi
  return "$status"
}

# 0. The scratch copy reproduces the committed outputs and version.
A=$(fresh_copy a)
run "$A" 0-check --check

# 1a. No record, then one asset-changing edit (app.css only): the build drops
#     the live release's app stylesheet.
edit_app_css "$A"
run "$A" 1a-build-unrecorded-app-css-edit
summarize "$A" 1a-unrecorded-app-css-edit > "$OUT/1a-unrecorded-app-css-edit.json"
expect "$OUT/1a-unrecorded-app-css-edit.json" new some

# 1b. No record, then edits to all four exclusive outputs: all four are dropped.
B=$(fresh_copy b)
edit_all_four "$B"
run "$B" 1b-build-unrecorded-all-four
summarize "$B" 1b-unrecorded-all-four > "$OUT/1b-unrecorded-all-four.json"
expect "$OUT/1b-unrecorded-all-four.json" new exclusive

# 2. Record the live release first, then the same all-four edit: nothing is dropped.
C=$(fresh_copy c)
run "$C" 2-record --record-release "$LIVE"
summarize "$C" 2-after-record > "$OUT/2-after-record.json"
expect "$OUT/2-after-record.json" same none
cp "$C/config/assets.json" "$OUT/2-after-record-manifest.json"
run "$C" 2-check-after-record --check
edit_all_four "$C"
run "$C" 2-build-recorded-all-four
summarize "$C" 2-recorded-then-all-four > "$OUT/2-recorded-then-all-four.json"
expect "$OUT/2-recorded-then-all-four.json" new none

# 3. Files the record step itself retires: only files exclusive to the pin it drops.
python3 -I - "$COMMITTED" "$OUT/2-after-record-manifest.json" > "$OUT/3-retired-by-record.json" <<'PY'
import json, sys
def load(path):
    with open(path) as handle:
        return json.load(handle)
before, after = load(sys.argv[1]), load(sys.argv[2])
gone = sorted(set(before['files']) - set(after['files']))
kept_versions = {r['version'] for r in after['deployedReleases']}
dropped = [r for r in before['deployedReleases'] if r['version'] not in kept_versions]
dropped_files = {u for r in dropped for u in r['files']}
if not gone or not set(gone) <= dropped_files:
    sys.exit(f'repro.sh: 3: expected only files of the dropped pin(s) {[r["version"] for r in dropped]} to leave the allowlist; removed {gone}')
print(json.dumps({
    'deployedReleases_before': [r['version'] for r in before['deployedReleases']],
    'deployedReleases_after': [r['version'] for r in after['deployedReleases']],
    'files_removed_from_allowlist': gone,
}, indent=2))
PY
echo "done: $OUT"
