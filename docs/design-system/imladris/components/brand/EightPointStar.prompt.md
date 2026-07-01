**EightPointStar** — the Imladris elven house mark; use as the brand glyph beside the wordmark, as a faint section watermark, or anywhere the brand needs a signature. Inherits `currentColor`.

```jsx
<a className="brand" style={{ color: 'var(--brand)', display: 'inline-flex', alignItems: 'center', gap: 11 }}>
  <EightPointStar size={26} />
  <span style={{ fontFamily: 'var(--font-display)', fontSize: '1.4rem' }}>RetroBoards</span>
</a>
```

Variants: `variant="mark"` (solid, default) vs `variant="watermark"` (thin + faint, for behind headings/covers — colour it gold). Set `title` to make it a labelled image; otherwise it is decorative (aria-hidden). Size with the `size` prop, colour with the parent's `color`.
