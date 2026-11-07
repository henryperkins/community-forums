# Forum theme: published asset retention

Date: 2026-11-07
Integrated base: `8a028592a09d07c269be9eb9a17c3c5969c1dc37`

## Retention baseline

The merged candidate retains the release pins recorded by `8a028592` after
live verification of `409532f5`:

1. `c66e3b55dba6da68` (`409532f5`, inbox reading-pane and triage hardening)
2. `3630440af0d45598` (`a2789ba0`, inbox shared shell)
3. `67ba8434286ff7e6` (`d1ad5453`, verified email changes)

The production verification receipts in
[`../inbox-critique-2026-11-07/production/`](../inbox-critique-2026-11-07/production/)
report matching SHA-256 hashes for all 25 delivery files of `c66e3b55dba6da68`
and `3630440af0d45598` on `https://forum.candidary.online`. Recording
`c66e3b55dba6da68` retires `0abd713b0ebf7bca` and its one exclusive file
(`passkeys-16e7134417f0d6af.js`) under the three-release retention policy.

## Candidate behavior

The new Forum entry points and the latest upstream Inbox behavior are rebuilt
as candidate `5dc1b40f3b23d46d`, retaining all 30 unique files across the three
pinned deployed releases (`c66e3b55dba6da68`, `3630440af0d45598`,
`67ba8434286ff7e6`). The former Imladris URLs remain available for pages opened
before deployment.

## Verification

After integration with `8a028592`, all 30 unique files in the three pinned
releases matched their manifest SHA-256 values in both `public/assets/dist` and
`.build/static/assets/dist`. The final build/drift and Worker results are
recorded in `README.md`.

Rollback must pair the chosen application revision with its generated manifest
and matching assets. Retained historical files are compatibility support; their
presence does not select the old theme for the active application.
