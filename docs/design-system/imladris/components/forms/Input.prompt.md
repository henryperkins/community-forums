**Input / Textarea / Switch / ChoiceCard** — the form set. All serif, all with the gold focus halo.

```jsx
<Input pill icon="search" type="search" placeholder="Search the council…" aria-label="Search" />
<Input label="Display name" defaultValue="Erestor" />
<Textarea label="Your counsel" rows={4} placeholder="Add to the discussion…" />
<Switch label="Show my online presence" defaultChecked />
<ChoiceCard name="theme" value="parchment" title="Parchment" desc="The day register"
  swatch={<span className="theme-swatch swatch-parchment"><span className="sw-bg" /><span className="sw-card" /><span className="sw-accent" /></span>} defaultChecked />
```

`Input pill` is the rounded search style; `icon="search"` sets the system lens in its leading padding (any node works — it is decorative, so the input keeps its own label). ChoiceCards group by `name` (theme: Parchment/Twilight/Auto; density: Comfortable/Compact). The Switch track turns evergreen when on.
