# Image upload production release — 2026-09-23

Historical release evidence for commit
`a26f1fd0a09a4abf9d646ec1252e95951ef85d02`, preserved from the reviewed local
release report and receipts on 2026-10-11. These checks ran on 2026-09-23;
preserving the results is not a new production test.

The source report and JSON receipts were retained under the ignored local
`.superpowers/releases/2026-09-23-image-upload-reliability/` directory. The three
JSON receipts below are preserved verbatim. They describe synthetic test
accounts, posts and media, and contain no session tokens or credentials.

The release used the Cloudflare container/R2 origin. That origin and its bucket
were retired on 2026-10-09; see the
[current Cloud Run runbook](../../../runbooks/deployment-cloud-run.md#9-retiring-the-cloudflare-container-and-pre-move-r2-uploads).
Deployment identifiers and storage receipts here are historical, not current
resources or supported rollback targets.

## Release

The commit was on `main` and `origin/main`, with clean working trees and
divergence `0 0`. Cloudflare Workers Build
`ba43b0cc-e0fd-4649-abf0-d3e7ec4609a7` completed successfully. Deployment
`e8f46993-a92b-4fb7-b628-4201b34c0d70` served Worker version
`6c63ddf3-f11d-4e1b-954e-fb25311b7d43` at 100%. The container application was
version 18 with image digest
`sha256:d3a97aeb67dc28cbd2768dd7e6f75f747474ec13465e164846e32e7da22d49d7`.

## Verification

- Production `/healthz` returned 200 with application and database healthy;
  `/login` returned 200. All 15 published CSS/JS hashes matched the release's
  `config/assets.json`: [asset receipt](live-assets.json).
- The normal production reply composer accepted a 3,151,914-byte PNG, showed
  its loaded preview, inserted canonical media Markdown, published it, and
  displayed it after reload in desktop Chromium and mobile WebKit at 390×844.
  No request targeted a temporary upload marker:
  [smoke receipt](production-smoke.json).
- A file above the application limit produced a visible failure, blocked Send,
  and recovered after Remove without an upload request. Real 9 MiB and 11 MiB
  multipart requests returned JSON 413 responses with `upload_too_large` and
  `upload_request_too_large`, respectively.
- The finalized attachment belonged to the synthetic published post. Its
  delivered SHA-256 and 6,523-byte size matched the database. The exact R2
  object existed with that size: [release receipt](release-verification.json).
- All three synthetic replies were deleted through the ordinary application
  route. All three temporary test sessions were revoked, their local credential
  file was removed, and no test draft remained. Guest requests for all three
  test media returned 404. Media under deleted posts remained subject to the
  normal attachment cleaner.
- The completed local evidence recorded PHPUnit 3,024 tests / 22,928 assertions
  with zero failures, six baseline deprecations and two skips; 141 upload
  browser cases; 118 existing composer/draft/group-DM cases with 40 declared
  viewport skips; 15 asset tests; and 24 Imladris tests / 296 assertions.
  Release asset and Imladris checks passed again. See the
  [original local evidence](../README.md).

## Limits

GitHub Actions run `35848679286` was blocked before startup by account billing.
Cloudflare Workers Builds and the live production checks succeeded
independently. The mobile check used Playwright WebKit, not an actual iPhone.
Production was not restarted after the upload, so R2 object presence and
current readback were verified without claiming restart durability. Real
multipart boundaries were tested; no direct production PHP INI probe was
performed. Historical accepted-image disappearance was not reproduced or
rewritten.

The original local verification and its later release checks remain separate
dated records. This report closes the missing release-evidence record without
extending the tested scope or asserting that the retired resources still exist.
