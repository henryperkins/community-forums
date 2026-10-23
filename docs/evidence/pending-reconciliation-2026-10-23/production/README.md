# PR #91 production verification and release retention

PR #91 was updated to `71e3049d3687a94b92c7b07192f19e3cd1394c6f`
and merged as `fd76f5102c1fcbf32a5d53be47744e3d9ce9d927` on 2026-10-23.
The local qualification in [the parent record](../README.md) applies to this
merge's application tree. The design reconciliation was then replayed as
`1805cd70`; its complete tree matched the previously reviewed combined candidate
`cbbd2fba` before retention bookkeeping.

## Verified asset-release deployment

Both automatic production lanes completed successfully for the merge commit:

- Cloudflare Workers Build `ed7a008c-e199-4409-9aa3-9418e897c0af`:
  the GitHub check reports `completed/success` on the exact merge SHA. The active
  Worker deployment is `1cb6b37d-6e81-4af1-a6b3-a9dab948a5fc`, serving version
  `3929131e-81e8-4d3f-900b-3488dc2f56db` at 100%.
- Cloud Build `9e5f56e0-bb30-48db-b684-4fc37718f9e7`: `SUCCESS` on the exact
  merge SHA. Cloud Run revision `retroboards-00055-fdq` is Ready and serves 100%
  of traffic. All four cron jobs and the monitoring job use that commit's image.
- Public `https://forum.candidary.online/healthz` and `/login` return 200;
  health reports application and database `ok`. All 58 manifest files were
  fetched from the public host and matched their SHA-256 hashes, including the
  new Vite composer loader. Rendered login asset references match the manifest.

The JSON files in this directory preserve the build, Worker deployment,
Cloud Run service/revision/jobs, and live-byte receipts. They deliberately omit
runtime environment values and credentials. The Cloudflare connector required
reauthentication; existing CLI/API credentials and the exact GitHub Workers
Build check supplied the verification instead. The full hosted browser lane is
independent of these production deployment checks.

## Retention update after live verification

Only after the live checks above, `npm run assets:record-release --
432ce95c7cdbe032` advanced the deployed-release window to:

1. `432ce95c7cdbe032`
2. `d558418fe08d412c`
3. `1c24a3765cdd43f9`

The asset version itself is unchanged. Three files exclusive to the oldest
retired release were removed; all 55 remaining manifest files were locally
hash-verified. [retention.json](retention.json) records the exact set.

Removing the retired assets changes the application-surface digest, so the
runtime baseline and generated Imladris manifest were reconciled to
`0eb8e2edbf57ae2363c086752766372302da8cc51fdddf340d7607d1c3353337`.
No source CSS, source JavaScript, templates, migrations, or current asset bytes
changed during this bookkeeping step.

Post-retention checks passed: `check:assets`, all 35 asset/Worker tests, and
`composer verify:imladris` (24 tests, 305 assertions). Their logs are adjacent.
The complete PHPUnit suite and focused browser evidence remain in the parent
record. This file records the asset merge's completed production deployment;
the following design/retention publication receives its own deployment receipts.
