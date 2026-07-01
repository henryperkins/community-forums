**CommendStar** — the filled four-point esteem star. Use wherever "Commends" (reputation) appear: reaction glyphs, a member's commend count, the star button, the accepted-answer mark. Colour it gold (`var(--star)`).

```jsx
<span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, color: 'var(--star)' }}>
  <CommendStar size={14} /> 3,985
</span>
```

Defaults to 14px and `currentColor`. It is the *commend/esteem* mark (four points); the EightPointStar is the *house* mark (eight points). Don't swap them.
