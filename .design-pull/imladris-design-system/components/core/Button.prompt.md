**Button** — the brand action control; use for any click action. Marcellus label, sentence case.

```jsx
<Button>New topic</Button>
<Button variant="accent">Follow</Button>
<Button variant="secondary">Message</Button>
<Button variant="ghost" size="sm">Cancel</Button>
<Button href="/login" variant="primary">Log in</Button>
```

Variants: `primary` (evergreen, default) · `accent` (mallorn-gold — one per view, the most-wanted action) · `secondary` (parchment outline) · `ghost` (quiet) · `danger` (rust). Sizes `md`/`sm`. Pass `href` to render an `<a>`. `icon`/`iconAfter` take an SVG node. Never use two `accent` buttons in one region — gold is the single accent.
