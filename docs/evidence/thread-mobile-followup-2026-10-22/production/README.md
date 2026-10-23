# Production publication — 2026-10-22

Fix commit `de967b3001aca20051fa88efdf392156863debb3` was pushed directly to
`main`. Local, tracking and live remote SHAs matched, with zero divergence.
The preserved checkout included 7,001 unrelated tracked paths and 13 unrelated
untracked files; their bytes and deletion states were unchanged.

Both automatic push deployments completed successfully:

| Component | Exact result |
| --- | --- |
| Workers Builds | `6ca769ed-6bd1-4a80-bb24-242447b607e5`, success for the fix commit |
| Worker deployment | `262733ed-158a-493e-9275-4921726bafc9`, version `2df38fbc-1991-489b-aff0-7de20f9af2fb` at 100% |
| Cloud Build | `1690ab5f-9631-4b48-a9c5-278609c06d87`, all five production steps successful |
| Cloud Run | Ready revision `retroboards-00049-jx4`, exact fix image at 100% traffic |
| Scheduled workloads | All four cron jobs and the monitoring job ready with the exact fix image |
| Public reads | Health/database OK; homepage and thread return 200 and reference the current assets |
| Static bytes | All 59 manifest files match; all 32 immutable files pass GET, HEAD and conditional 304 checks |

The verified asset version is **`1c24a3765cdd43f9`**. The isolated canonical Git
candidate reproduces the earlier local delivery bytes. Asset integrity, all 35
Worker/retention tests and Imladris checks passed before publication.

Following deployment-cloudflare §14, the release record pins this verified
version and predecessors `cd261fb5782f8107` and `adc1494dbfa0cd3d`. Only the oldest
exclusive stylesheet `app-style-DkLhYoyf.css` retires. Current delivery URLs and
bytes are unchanged. The recorded candidate passed asset integrity, all 35 asset
tests and Imladris checks again.

This folder records the verified fix deployment and the release-retention
follow-up. The automatic deployment of the subsequent receipt commit is checked
separately in `output/mobile-ux-publication-2026-10-22/final-main-deployment.json`.
No third commit is needed merely to describe that commit's own deployment.

These are provider metadata and public read-only checks. Physical-device checks,
authenticated production writes and independent full hosted browser acceptance
are not claimed. The earlier native Windows full-suite failures remain documented
in the parent evidence report. Original audit artifacts and their checksums are
preserved.
