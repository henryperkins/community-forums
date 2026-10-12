# Boards publication integration

Date: 2026-10-12.

The owner's commit/push request required integrating four new main commits through
fbaed0e9 (the full fetched identity is recorded in
source-identity.json). The original Boards commit is fef118d5bbbd60ab607f76b6f41f638732788bfb.
Both histories are retained by a merge. All newer accessibility styles, template
changes, test changes and confirmed asset pins are preserved. The Boards adaptation
block is byte-for-byte the same as the reviewed original.

Current asset version: 326ae04cd673029a. Published files: 55.
Application digest: 53331f86fef1d291065bd5bd98e3f83ab9ca57f2fd4e6135595cf7695ee2c9a2.

| Evidence | Result |
| --- | --- |
| Focused Boards/Imladris/reaction/thread PHP | 110 tests, 1270 assertions passed |
| Directory and incoming mobile-controls Chromium | 25 passed, 2 viewport skips |
| WebKit directory | 6 passed; original Windows guest Tab limitation remains |
| Asset tests | 35 passed |
| Generated assets and Imladris contract checks | Passed |

The complete 3233-test PHP run initially recorded 10 failures, 1 warning and
2 skips. Eight failures involve Linux Cloud Run fixtures on native Windows.
Two reaction failures had the same private-database cause as eight browser
fixture failures: utf8mb4_general_ci considers different supplementary-plane
emojis equal. The private browser and PHP databases' reaction columns were
changed to utf8mb4_bin; all affected PHP classes and the browser checks then
passed. No application or migration code changed. This composed evidence is
reported separately from the earlier full run; no full-suite green claim is made.
private-collation-correction.json records the exact equality comparison and DDL.

During the merge, an older stylesheet that had become a confirmed deployment
pin was restored from origin/main. A manifest-byte restoration also copied its
mutable source CSS; the composed CSS was immediately restored from the merge
index before building and verification. Failed build logs are retained. The
final source identity confirms both the unchanged Boards block and incoming styles.

Raw log and XML files are archived losslessly with original hashes and byte
counts in diagnostic-archives.json. Earlier original-branch evidence remains
in the parent directory and describes its own d06a303a baseline.

The dev URL uses the isolated demo database on loopback. Publication receipts
are added after observing the automatic Worker and Cloud Run deployments.
