# Forum theme: published asset retention

Date: 2026-11-07
Integrated base: `409532f55fd5a3e72e062cdf1ef94ba0a444edfb`

## Retention baseline

The replacement retains the release pins already recorded by upstream commit
`1975bfcecdf8c04ef14ba754e9f38e6d7231fb12`:

1. `3630440af0d45598`
2. `67ba8434286ff7e6`
3. `0abd713b0ebf7bca`

That upstream change records the prior Inbox-shell release against its
[production asset receipt](../inbox-critique-2026-11-07/production/live-verify-3630440af0d45598.json).
The receipt reports matching hashes for all 25 delivery files. This is upstream
release evidence, not a deployment performed by the Forum-theme work.

An earlier read-only inspection in this session independently observed
`app-style-D7aglA01.css` and `imladris-style-DmYWEV0N.css` in the production page.
Direct byte retrieval from that environment returned HTTP 403, so that browser
observation alone was not treated as complete release verification. The final
candidate now builds on upstream's recorded retention baseline rather than
introducing a separate historical release correction.

## Candidate behavior

The new Forum entry points and the latest upstream Inbox behavior are rebuilt
as a candidate. Its version is reported in `README.md` and is **not** added to
`deployedReleases`. No deployment or release-pin advancement is performed here.

The former Imladris URLs remain available for pages opened before an eventual
deployment. The oldest pin, `545bd84edf0dc37c`, was already retired upstream
under the existing three-release policy. The retention algorithm and limit are
unchanged.

## Verification

After upstream integration, all 27 unique files in the three pinned releases
matched their manifest SHA-256 values in both `public/assets/dist` and
`.build/static/assets/dist`, including the observed D7 and Dm stylesheets.
The final build/drift and Worker results are recorded in `README.md`.

Rollback must pair the chosen application revision with its generated manifest
and matching assets. Retained historical files are compatibility support; their
presence does not select the old theme for the active application.
