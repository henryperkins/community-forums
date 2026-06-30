# Phase 3 Load Evidence

The production-like k6 closeout run writes its JSON summary here:

```bash
docker compose -f tests/prodlike/compose.yml run --rm k6 run /scripts/phase3-load.js
```

Default thresholds:

- no 5xx responses (`http_5xx count == 0`)
- `http_req_failed < 1%`
- read-path p95 under 750 ms
- write-path p95 under 1500 ms

The default scenario is 20 VUs for 15 minutes and covers anonymous reads,
authenticated reads, composer preview, server draft save/load/discard, and a
low-rate tiny image upload path.

## Current Artifact

`phase3-load-summary.json` is not final closeout evidence. It was written by an
interrupted 20-VU run after 301 seconds when the full 15-minute run was skipped
at operator direction on 2026-06-30. It is useful as diagnostic output only: no
5xx responses were recorded, `http_req_failed` was 0%, and read-path p95 was
694.99 ms, but write-path p95 was 1555.63 ms and therefore did not satisfy the
1500 ms threshold.

Before rerunning the final gate, reset the production-like database with dark
server-draft surfaces enabled:

```bash
cd tests/browser
RB_BROWSER_DARK_SURFACES=1 npm run prepare-db:prodlike
cd ../..
docker compose -f tests/prodlike/compose.yml run --rm k6 run /scripts/phase3-load.js
```
