# Independent review and resolution

A fresh read-only GPT-6 Astra review examined the actual combined uncommitted
working tree against f0bbfc03, the approved plans, authoritative docs and the
source-to-public propagation assessment. It did not mutate the checkout or run
fixtures. This is source/calculation evidence; browser evidence is separate.

The review identified one P2: newly mutable `--brand` fills in Messages still
used fixed foregrounds. `--dm-on-brand` was parchment, and `.dm-newpill:hover`
used the raised surface as ink. White primary produced 1.08:1 selected-filter
contrast; a black primary in Dark mode made the new-message hover label weak.

Resolution: both consumers now use `--brand-contrast`, retaining their existing
fallback when no custom primary is present. Focused browser regressions first
reproduced 1.079:1 on the Light selected filter and hover pill, and 1.388:1 on the
Dark hover pill. After the fix, all four desktop/mobile cases passed. The tests
also run in the final Chromium/WebKit coverage. The hover fixture reveals the
real server-rendered new-message element to qualify its colour; it does not
claim new-message polling or delivery proof.

The reviewer found no additional concrete introduced defect in the remaining
brand fills/soft states, reset separation, preview token clearing, private
preview/public-review boundary, no-JavaScript flow or independent status families.

Declined judgments, retained as limits: completion of the then-running test
suites; physical-device/screen-reader behaviour; arbitrary custom CSS and package
styling outside validated tokens. Final suite results are in README.md. Device,
screen-reader and arbitrary custom CSS/package qualification remain outside the
local evidence.

After this review, the author's final built-in-default check found that Retro
dark inherited Classic's parchment menu ink. The final two-line palette change
gives Retro dark explicit dark ink and preserves it through preview defaults.
This later correction is qualified by a failing/passing unit regression, eight
focused browser cases in each engine and the final full PHP suite; it was not
part of the earlier independent review.
