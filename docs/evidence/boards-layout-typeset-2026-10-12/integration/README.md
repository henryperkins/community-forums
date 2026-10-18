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

## Worker asset release and origin status

The Worker deployed the d928d6f2 asset set. All 55 published files match their
expected SHA-256 hashes. Public health returns database ok; the current stylesheet
passes GET 200, HEAD 200 and conditional GET 304 with immutable cache headers.
live-assets.json records this asset proof separately from backend UI proof:
the production login still names its previous stylesheet while Cloud Run build
9315b5e7-2888-4590-b593-2b77fecd6ad2 is queued behind existing hosted browser work.
No current production-origin or authenticated UI pass is claimed.

Record verified asset version 326ae04cd673029a under ADR 0041. The resulting
54-file manifest pins 326ae04cd673029a, be1d396948b3adcc and 2883bddb08c95fd1;
only the retired app-style-_B45kYl1.css leaves the delivery set. The post-retirement
application digest is
645bfcbfb0c49c7f9bfcb2e05f684bdeb4b8329f784b7e9183867111d2e0f7b1.

The dev server now serves the main checkout through an external local router,
which loads the isolated demo environment before the app can load the primary
environment. The primary .env is unchanged. Boards and health both return 200.
