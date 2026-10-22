# ADR 0050: Thread mobile recovery and reading

**Date:** 2026-10-22
**Status:** Accepted

## Context

The thread critique found that an unrelated successful navigation could discard
an unconfirmed reply. Browser history could also restore its text with Send
disabled. The topic head lacked a persistent answer destination, enlarged labels
overflowed, and a landscape preview could put Minimize under the member header.
The owner approved all six fixes and chose a compact mobile byline with wider prose.

## Decision

Successful composer writes issue a short-lived, session-signed completion receipt
containing only context and submission identity. The next HTML response consumes
it. Local draft cleanup requires that receipt, a matching submission, and the
same saved content; a later edit in another tab survives. Unconfirmed attempts
remain recoverable. History entries retain their submission identity, and editing
an unconfirmed payload rotates that identity before another send. History restore
also resets the transient Sending state, including after a lazy rich-editor import.
The server trims only a matching submitted remote draft; there is no navigation
heuristic or client request to discard an unrelated remote draft.

The topic head always offers View answer when the accepted post is readable.
Its destination uses the current reader's page size and deleted-post stream.
Action labels wrap within the head instead of widening the document.

Below the 860px shell breakpoint, the author avatar and byline form one row;
post prose, references, signatures and reactions span the full column beneath.
Grouped posts keep their compact continuation, anonymous identity stays masked,
and removed-post stubs keep their existing layout. Reaction pickers show their
existing names beside icons before activation, with 44px targets.

The expanded reply dock shares one height budget below the member header, based
on the visual viewport when available. Formatting, Minimize and Send stay outside
the scrolling input/preview regions. Document scrolling remains the thread's
scroll owner. Physical keyboard/browser-bar behavior still requires the device
checks recorded with the evidence; desktop emulation is not device proof.

Fingerprinted generated assets are byte-preserved by Git. Five existing retained
assets had CRLF conversion; restoring LF reproduced their manifest digests exactly.

## Evidence

Focused kernel/browser regressions, review resolutions, test logs and the mobile
runner continuation are recorded in `docs/evidence/thread-mobile-ux-2026-10-22/`.
The original audit remains unchanged. The continuation distinguishes implemented
fixes, local proxy observations and outstanding physical-device checks.

## Follow-up: conflict saving and delayed rich recovery

The follow-up reproduced an older native textarea restored by Back before the
lazy rich editor initialized. Local storage and the pending-submit journal still
held the latest body; using the nonempty native value opened the wrong revision
and rotated its submission key. Initialize from the matching unconfirmed
user/context/history entry only when its body still matches the saved local
draft. Preserve any new typing while the optional editor module loads.

The conflict's Save local action reads the current body and title at activation,
rather than retaining the values captured when the conflict was displayed.
The populated open preview also exposes a named keyboard scroll region with
visible focus. Active gold text uses semantic theme tokens; the unlayered legacy
rules hand color ownership back to the component layer with `revert-layer`.

Reproduction traces, current local tests and the remaining device checks are in
`docs/evidence/thread-mobile-followup-2026-10-22/`. These are local changes and
do not amend the earlier deployment receipts.
