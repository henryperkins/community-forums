/* @ds-bundle: {"format":3,"namespace":"ImladrisDesignSystem_89e0d2","components":[{"name":"Button","sourcePath":"components/actions/Button.jsx"},{"name":"IconButton","sourcePath":"components/actions/IconButton.jsx"},{"name":"ArticleCard","sourcePath":"components/content/ArticleCard.jsx"},{"name":"Avatar","sourcePath":"components/content/Avatar.jsx"},{"name":"Badge","sourcePath":"components/content/Badge.jsx"},{"name":"Callout","sourcePath":"components/content/Callout.jsx"},{"name":"PullQuote","sourcePath":"components/content/PullQuote.jsx"},{"name":"Tag","sourcePath":"components/content/Tag.jsx"},{"name":"ArtifactRow","sourcePath":"components/evidence/ArtifactRow.jsx"},{"name":"EvidenceBoard","sourcePath":"components/evidence/EvidenceBoard.jsx"},{"name":"OperationalStory","sourcePath":"components/evidence/OperationalStory.jsx"},{"name":"ProductHero","sourcePath":"components/evidence/ProductHero.jsx"},{"name":"ProofBar","sourcePath":"components/evidence/ProofBar.jsx"},{"name":"WorkEntry","sourcePath":"components/evidence/WorkEntry.jsx"},{"name":"Input","sourcePath":"components/forms/Input.jsx"},{"name":"Subscribe","sourcePath":"components/forms/Subscribe.jsx"},{"name":"RingCard","sourcePath":"components/framework/RingCard.jsx"},{"name":"SiteFooter","sourcePath":"components/site/SiteFooter.jsx"},{"name":"SiteHeader","sourcePath":"components/site/SiteHeader.jsx"}],"sourceHashes":{"components/actions/Button.jsx":"49b281912a29","components/actions/IconButton.jsx":"f849d7844bbd","components/content/ArticleCard.jsx":"6d9f42261023","components/content/Avatar.jsx":"17c72d8c1fb9","components/content/Badge.jsx":"09d1b57500dd","components/content/Callout.jsx":"757326d58e0f","components/content/PullQuote.jsx":"16ec6cf952d9","components/content/Tag.jsx":"01b57ec9d04f","components/evidence/ArtifactRow.jsx":"5c77c4e63549","components/evidence/EvidenceBoard.jsx":"0d19662506e6","components/evidence/OperationalStory.jsx":"6a127c5fe42c","components/evidence/ProductHero.jsx":"2c09bd94eccf","components/evidence/ProofBar.jsx":"8fead9d5415f","components/evidence/WorkEntry.jsx":"5084bf440221","components/forms/Input.jsx":"2de83c9a1854","components/forms/Subscribe.jsx":"351c8a5aca2b","components/framework/RingCard.jsx":"3954ccbb7365","components/site/SiteFooter.jsx":"989aac460351","components/site/SiteHeader.jsx":"feda61135417","decks/expose-govern-attest/deck-stage.js":"208980974db4","decks/expose-govern-attest/tweaks-panel.jsx":"6591467622ed","ui_kits/blog/data.js":"9067d77e1fd1","ui_kits/blog/parts/App.compiled.js":"7717c8bd3225","ui_kits/blog/parts/App.jsx":"6d56a58a226c","ui_kits/blog/parts/CoverPlate.compiled.js":"32f6e5a18c68","ui_kits/blog/parts/CoverPlate.jsx":"b91d25ff1b4a","ui_kits/blog/parts/HomeView.compiled.js":"3e6dc30c5051","ui_kits/blog/parts/HomeView.jsx":"a65cdf12f300","ui_kits/blog/parts/PostCard.compiled.js":"ae378d052f20","ui_kits/blog/parts/PostCard.jsx":"b8d6dc7f3c33","ui_kits/blog/parts/ReaderView.compiled.js":"a1ba201c6c3b","ui_kits/blog/parts/ReaderView.jsx":"a41843a416f8","ui_kits/blog/parts/SiteChrome.compiled.js":"12d37a09fbcb","ui_kits/blog/parts/SiteChrome.jsx":"69be007a202b"},"inlinedExternals":[],"unexposedExports":[{"name":"resolveStatus","sourcePath":"components/evidence/ProofBar.jsx"}]} */

(() => {

const __ds_ns = (window.ImladrisDesignSystem_89e0d2 = window.ImladrisDesignSystem_89e0d2 || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/actions/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — Button
 * Serif-labelled action. Variants: primary (evergreen), secondary (outline),
 * ghost (quiet), accent (gilt), link. Sizes sm / md / lg.
 */

const sizes = {
  sm: {
    padding: '6px 14px',
    fontSize: 'var(--text-sm)'
  },
  md: {
    padding: '9px 20px',
    fontSize: 'var(--text-base)'
  },
  lg: {
    padding: '13px 28px',
    fontSize: 'var(--text-md)'
  }
};
const variants = {
  primary: {
    background: 'var(--brand)',
    color: 'var(--text-inverse)',
    border: '1.5px solid var(--brand)'
  },
  secondary: {
    background: 'transparent',
    color: 'var(--brand)',
    border: '1.5px solid var(--border-brand)'
  },
  ghost: {
    background: 'transparent',
    color: 'var(--text-body)',
    border: '1.5px solid transparent'
  },
  accent: {
    background: 'var(--accent)',
    color: 'var(--ink-900)',
    border: '1.5px solid var(--accent)'
  },
  link: {
    background: 'transparent',
    color: 'var(--text-link)',
    border: '1.5px solid transparent',
    padding: 0,
    textDecoration: 'underline',
    textDecorationColor: 'color-mix(in srgb, var(--gold-500) 60%, transparent)',
    textUnderlineOffset: '0.18em'
  }
};
function Button({
  children,
  variant = 'primary',
  size = 'md',
  disabled = false,
  iconLeft = null,
  iconRight = null,
  fullWidth = false,
  type = 'button',
  onClick,
  style = {},
  ...rest
}) {
  const base = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '0.5em',
    fontFamily: 'var(--font-label)',
    letterSpacing: '0.06em',
    lineHeight: 1,
    borderRadius: 'var(--radius-md)',
    cursor: disabled ? 'not-allowed' : 'pointer',
    opacity: disabled ? 0.45 : 1,
    width: fullWidth ? '100%' : 'auto',
    transition: 'background var(--dur-fast) var(--ease-calm), color var(--dur-fast) var(--ease-calm), border-color var(--dur-fast) var(--ease-calm), transform var(--dur-fast) var(--ease-calm)',
    ...sizes[size],
    ...variants[variant],
    ...style
  };
  const hoverFor = {
    primary: (e, on) => {
      e.currentTarget.style.background = on ? 'var(--brand-hover)' : 'var(--brand)';
      e.currentTarget.style.borderColor = on ? 'var(--brand-hover)' : 'var(--brand)';
    },
    secondary: (e, on) => {
      e.currentTarget.style.background = on ? 'var(--brand-subtle)' : 'transparent';
    },
    ghost: (e, on) => {
      e.currentTarget.style.background = on ? 'var(--surface-sunken)' : 'transparent';
    },
    accent: (e, on) => {
      e.currentTarget.style.background = on ? 'var(--accent-hover)' : 'var(--accent)';
      e.currentTarget.style.borderColor = on ? 'var(--accent-hover)' : 'var(--accent)';
    },
    link: (e, on) => {
      e.currentTarget.style.color = on ? 'var(--accent-press)' : 'var(--text-link)';
    }
  };

  // Press feedback — deepen filled variants to their -press token while held.
  const pressFor = {
    primary: e => {
      e.currentTarget.style.background = 'var(--brand-press)';
      e.currentTarget.style.borderColor = 'var(--brand-press)';
    },
    accent: e => {
      e.currentTarget.style.background = 'var(--accent-press)';
      e.currentTarget.style.borderColor = 'var(--accent-press)';
    },
    secondary: e => {
      e.currentTarget.style.background = 'var(--brand-subtle)';
    }
  };
  return /*#__PURE__*/React.createElement("button", _extends({
    type: type,
    disabled: disabled,
    onClick: onClick,
    style: base,
    onMouseEnter: e => !disabled && hoverFor[variant]?.(e, true),
    onMouseLeave: e => !disabled && hoverFor[variant]?.(e, false),
    onMouseDown: e => !disabled && (e.currentTarget.style.transform = 'translateY(0.5px) scale(0.99)'),
    onMouseUp: e => !disabled && (e.currentTarget.style.transform = 'none')
  }, rest), iconLeft, children, iconRight);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/actions/Button.jsx", error: String((e && e.message) || e) }); }

// components/actions/IconButton.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — IconButton
 * A square, quiet control for a single glyph (Lucide icon recommended).
 * Variants: ghost (quiet), outline (bordered), solid (evergreen).
 */
const styleFor = {
  ghost: {
    bg: 'transparent',
    bd: 'transparent',
    fg: 'var(--text-body)',
    hoverBg: 'var(--surface-sunken)',
    hoverBd: 'transparent',
    pressBg: 'var(--border-hair)'
  },
  outline: {
    bg: 'var(--surface-card)',
    bd: 'var(--border-soft)',
    fg: 'var(--text-body)',
    hoverBg: 'var(--surface-sunken)',
    hoverBd: 'var(--border-strong)',
    pressBg: 'var(--border-hair)'
  },
  solid: {
    bg: 'var(--brand)',
    bd: 'var(--brand)',
    fg: 'var(--text-inverse)',
    hoverBg: 'var(--brand-hover)',
    hoverBd: 'var(--brand-hover)',
    pressBg: 'var(--brand-press)'
  }
};
function IconButton({
  children,
  label,
  size = 'md',
  variant = 'ghost',
  disabled = false,
  onClick,
  style = {},
  ...rest
}) {
  const dim = size === 'sm' ? 32 : size === 'lg' ? 48 : 40;
  const s = styleFor[variant] || styleFor.ghost;
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    "aria-label": label,
    disabled: disabled,
    onClick: onClick,
    style: {
      width: dim,
      height: dim,
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      borderRadius: 'var(--radius-md)',
      background: s.bg,
      color: s.fg,
      border: `1.5px solid ${s.bd}`,
      cursor: disabled ? 'not-allowed' : 'pointer',
      opacity: disabled ? 0.45 : 1,
      transition: 'background var(--dur-fast) var(--ease-calm), color var(--dur-fast) var(--ease-calm), border-color var(--dur-fast) var(--ease-calm), transform var(--dur-fast) var(--ease-calm)',
      ...style
    },
    onMouseEnter: e => {
      if (disabled) return;
      e.currentTarget.style.background = s.hoverBg;
      e.currentTarget.style.borderColor = s.hoverBd;
    },
    onMouseLeave: e => {
      if (disabled) return;
      e.currentTarget.style.background = s.bg;
      e.currentTarget.style.borderColor = s.bd;
      e.currentTarget.style.transform = 'none';
    },
    onMouseDown: e => {
      if (disabled) return;
      e.currentTarget.style.transform = 'scale(0.94)';
      e.currentTarget.style.background = s.pressBg;
    },
    onMouseUp: e => {
      if (disabled) return;
      e.currentTarget.style.transform = 'none';
      e.currentTarget.style.background = s.hoverBg;
    }
  }, rest), children);
}
Object.assign(__ds_scope, { IconButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/actions/IconButton.jsx", error: String((e && e.message) || e) }); }

// components/content/Avatar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — Avatar
 * Author portrait. Falls back to initials on parchment with a gilt ring.
 */
function Avatar({
  src,
  name = '',
  size = 40,
  ring = false,
  style = {},
  ...rest
}) {
  const initials = name.split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
  const [failed, setFailed] = React.useState(false);
  const showImg = src && !failed;
  return /*#__PURE__*/React.createElement("span", _extends({
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      width: size,
      height: size,
      flex: 'none',
      borderRadius: '50%',
      overflow: 'hidden',
      background: 'var(--green-100)',
      color: 'var(--green-800)',
      fontFamily: 'var(--font-label)',
      fontSize: size * 0.38,
      letterSpacing: '0.03em',
      boxShadow: ring ? 'var(--gilt)' : 'none',
      ...style
    }
  }, rest), showImg ? /*#__PURE__*/React.createElement("img", {
    src: src,
    alt: name,
    onError: () => setFailed(true),
    style: {
      width: '100%',
      height: '100%',
      objectFit: 'cover'
    }
  }) : initials || '·');
}
Object.assign(__ds_scope, { Avatar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/content/Avatar.jsx", error: String((e && e.message) || e) }); }

// components/content/Badge.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — Badge
 * A small status marker. Tones map to the semantic palette.
 */
const tones = {
  neutral: {
    bg: 'var(--surface-sunken)',
    fg: 'var(--text-muted)',
    bd: 'var(--border-hair)'
  },
  brand: {
    bg: 'var(--brand-subtle)',
    fg: 'var(--green-800)',
    bd: 'var(--green-200)'
  },
  accent: {
    bg: 'var(--accent-subtle)',
    fg: 'var(--gold-700)',
    bd: 'var(--gold-200)'
  },
  success: {
    bg: 'var(--green-050)',
    fg: 'var(--success)',
    bd: 'var(--green-200)'
  },
  warning: {
    bg: 'var(--gold-100)',
    fg: 'var(--warning)',
    bd: 'var(--gold-200)'
  },
  danger: {
    bg: 'color-mix(in srgb, var(--rust) 12%, var(--parchment-50))',
    fg: 'var(--danger)',
    bd: 'color-mix(in srgb, var(--rust) 30%, transparent)'
  }
};
function Badge({
  children,
  tone = 'neutral',
  dot = false,
  style = {},
  ...rest
}) {
  const t = tones[tone] || tones.neutral;
  return /*#__PURE__*/React.createElement("span", _extends({
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: '0.4em',
      padding: '2px 10px',
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-2xs)',
      letterSpacing: '0.1em',
      textTransform: 'uppercase',
      color: t.fg,
      background: t.bg,
      border: `1px solid ${t.bd}`,
      borderRadius: 'var(--radius-pill)',
      lineHeight: 1.6,
      whiteSpace: 'nowrap',
      ...style
    }
  }, rest), dot && /*#__PURE__*/React.createElement("span", {
    style: {
      width: 5,
      height: 5,
      borderRadius: '50%',
      background: t.fg
    }
  }), children);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/content/Badge.jsx", error: String((e && e.message) || e) }); }

// components/content/ArticleCard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — ArticleCard
 * The primary blog unit: optional cover image, topic eyebrow, serif title,
 * standfirst, and an author/meta footer. Lifts gently on hover.
 */
function ArticleCard({
  title,
  standfirst,
  topic,
  href = '#',
  image,
  authorName,
  authorSrc,
  readTime,
  date,
  featured = false,
  style = {},
  ...rest
}) {
  return /*#__PURE__*/React.createElement("a", _extends({
    href: href,
    style: {
      display: 'flex',
      flexDirection: 'column',
      background: 'var(--surface-card)',
      border: '1px solid var(--border-hair)',
      borderRadius: 'var(--radius-lg)',
      boxShadow: 'var(--shadow-sm)',
      overflow: 'hidden',
      textDecoration: 'none',
      color: 'inherit',
      transition: 'box-shadow var(--dur-base) var(--ease-calm), transform var(--dur-base) var(--ease-calm), border-color var(--dur-base) var(--ease-calm)',
      ...style
    },
    onMouseEnter: e => {
      e.currentTarget.style.boxShadow = 'var(--shadow-lg)';
      e.currentTarget.style.transform = 'translateY(-3px)';
      e.currentTarget.style.borderColor = 'var(--green-200)';
    },
    onMouseLeave: e => {
      e.currentTarget.style.boxShadow = 'var(--shadow-sm)';
      e.currentTarget.style.transform = 'none';
      e.currentTarget.style.borderColor = 'var(--border-hair)';
    }
  }, rest), image && /*#__PURE__*/React.createElement("div", {
    style: {
      aspectRatio: featured ? '16 / 7' : '16 / 9',
      overflow: 'hidden',
      background: 'var(--surface-sunken)'
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: image,
    alt: "",
    style: {
      width: '100%',
      height: '100%',
      objectFit: 'cover'
    }
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: featured ? 'var(--space-6)' : 'var(--space-5)',
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-3)',
      flex: 1
    }
  }, topic && /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(__ds_scope.Badge, {
    tone: "brand"
  }, topic)), /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: 0,
      fontFamily: 'var(--font-display)',
      fontWeight: 500,
      color: 'var(--text-strong)',
      letterSpacing: '-0.01em',
      lineHeight: 1.12,
      fontSize: featured ? 'var(--text-3xl)' : 'var(--text-xl)'
    }
  }, title), standfirst && /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0,
      font: 'var(--type-body)',
      color: 'var(--text-muted)',
      display: '-webkit-box',
      WebkitLineClamp: featured ? 3 : 2,
      WebkitBoxOrient: 'vertical',
      overflow: 'hidden'
    }
  }, standfirst), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 'auto',
      paddingTop: 'var(--space-3)',
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Avatar, {
    name: authorName,
    src: authorSrc,
    size: 32
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      lineHeight: 1.3
    }
  }, authorName && /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-xs)',
      letterSpacing: '0.04em',
      color: 'var(--text-body)'
    }
  }, authorName), /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-mono)',
      fontSize: '11px',
      color: 'var(--text-faint)'
    }
  }, [date, readTime].filter(Boolean).join('  ·  '))))));
}
Object.assign(__ds_scope, { ArticleCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/content/ArticleCard.jsx", error: String((e && e.message) || e) }); }

// components/content/Callout.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — Callout
 * A bordered aside for governance notes, warnings, and quoted charters.
 * The left rule and icon take the tone colour; body stays readable ink.
 */
const tones = {
  note: {
    rule: 'var(--rule-gold)',
    fg: 'var(--gold-700)',
    bg: 'var(--gold-100)'
  },
  insight: {
    rule: 'var(--green-600)',
    fg: 'var(--green-800)',
    bg: 'var(--green-050)'
  },
  caution: {
    rule: 'var(--amber)',
    fg: 'var(--amber)',
    bg: 'var(--gold-100)'
  },
  risk: {
    rule: 'var(--rust)',
    fg: 'var(--rust)',
    bg: 'color-mix(in srgb, var(--rust) 8%, var(--parchment-50))'
  }
};
function Callout({
  children,
  title,
  tone = 'note',
  icon = null,
  style = {},
  ...rest
}) {
  const t = tones[tone] || tones.note;
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "note",
    style: {
      display: 'flex',
      gap: 'var(--space-4)',
      padding: 'var(--space-5)',
      background: t.bg,
      borderRadius: 'var(--radius-md)',
      borderLeft: `3px solid ${t.rule}`,
      ...style
    }
  }, rest), icon && /*#__PURE__*/React.createElement("div", {
    style: {
      color: t.fg,
      flex: 'none',
      marginTop: 2
    }
  }, icon), /*#__PURE__*/React.createElement("div", null, title && /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-xs)',
      letterSpacing: 'var(--tracking-caps)',
      textTransform: 'uppercase',
      color: t.fg,
      marginBottom: 'var(--space-2)'
    }
  }, title), /*#__PURE__*/React.createElement("div", {
    style: {
      font: 'var(--type-ui)',
      color: 'var(--text-body)',
      lineHeight: 'var(--leading-relaxed)'
    }
  }, children)));
}
Object.assign(__ds_scope, { Callout });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/content/Callout.jsx", error: String((e && e.message) || e) }); }

// components/content/PullQuote.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — PullQuote
 * A client / stakeholder quotation. The system's ranking rule made literal:
 * testimony is CAPPED AT BODY SCALE and never larger — the ceiling is the
 * ranking. A quote may be persuasive, but it must not outrank the work it
 * sits beside, so it never reaches display sizes. Gold rule, italic serif,
 * quiet lapidary attribution.
 *
 *   children     — the quotation text (no surrounding quote marks needed)
 *   name         — attributed person
 *   role         — their role / title
 *   organization — their organisation
 *   cite         — optional single-string attribution (overrides the three)
 */
function PullQuote({
  children,
  name,
  role,
  organization,
  cite,
  style = {},
  ...rest
}) {
  const attribution = cite || [name, role, organization].filter(Boolean).join(', ');
  return /*#__PURE__*/React.createElement("figure", _extends({
    style: {
      margin: 0,
      padding: 'var(--space-5) var(--space-6)',
      borderLeft: '3px solid var(--rule-gold)',
      background: 'var(--surface-raised)',
      borderRadius: '0 var(--radius-md) var(--radius-md) 0',
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("blockquote", {
    style: {
      margin: 0,
      position: 'relative',
      /* Ceiling = body scale. Never display sizes. */
      font: 'var(--type-body)',
      fontStyle: 'italic',
      color: 'var(--text-strong)',
      lineHeight: 'var(--leading-relaxed)',
      maxWidth: 'var(--measure-prose)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    "aria-hidden": "true",
    style: {
      fontFamily: 'var(--font-display)',
      fontStyle: 'normal',
      fontSize: 'var(--text-2xl)',
      lineHeight: 0,
      color: 'var(--gold-400)',
      marginRight: '0.15em',
      verticalAlign: '-0.35em'
    }
  }, "\u201C"), children), attribution && /*#__PURE__*/React.createElement("figcaption", {
    style: {
      marginTop: 'var(--space-4)',
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-xs)',
      letterSpacing: 'var(--tracking-wide)',
      color: 'var(--text-muted)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    "aria-hidden": "true",
    style: {
      color: 'var(--text-faint)'
    }
  }, "\u2014\xA0"), attribution));
}
Object.assign(__ds_scope, { PullQuote });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/content/PullQuote.jsx", error: String((e && e.message) || e) }); }

// components/content/Tag.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — Tag
 * A topic / subject tag for the blog taxonomy (Alignment, Policy, Oversight…).
 * Renders as a link-like chip; pass `as="a"` and `href` to navigate.
 */
function Tag({
  children,
  as = 'span',
  active = false,
  onClick,
  href,
  style = {},
  ...rest
}) {
  const Comp = as;
  const interactive = !!(onClick || href);
  return /*#__PURE__*/React.createElement(Comp, _extends({
    href: href,
    onClick: onClick,
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: '0.35em',
      padding: '4px 12px',
      fontFamily: 'var(--font-body)',
      fontSize: 'var(--text-sm)',
      color: active ? 'var(--text-inverse)' : 'var(--text-link)',
      background: active ? 'var(--brand)' : 'transparent',
      border: `1px solid ${active ? 'var(--brand)' : 'var(--border-soft)'}`,
      borderRadius: 'var(--radius-pill)',
      textDecoration: 'none',
      cursor: interactive ? 'pointer' : 'default',
      transition: 'background var(--dur-fast) var(--ease-calm), border-color var(--dur-fast) var(--ease-calm)',
      ...style
    },
    onMouseEnter: e => {
      if (interactive && !active) {
        e.currentTarget.style.background = 'var(--brand-subtle)';
        e.currentTarget.style.borderColor = 'var(--green-200)';
      }
    },
    onMouseLeave: e => {
      if (interactive && !active) {
        e.currentTarget.style.background = 'transparent';
        e.currentTarget.style.borderColor = 'var(--border-soft)';
      }
    }
  }, rest), /*#__PURE__*/React.createElement("span", {
    style: {
      color: active ? 'var(--gold-200)' : 'var(--accent)'
    }
  }, "#"), children);
}
Object.assign(__ds_scope, { Tag });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/content/Tag.jsx", error: String((e && e.message) || e) }); }

// components/evidence/ArtifactRow.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — ArtifactRow
 * The terminus of a Work entry. A labelled group of cells, each naming what
 * it verifies and linking the thing that proves it. Outcomes resolve into
 * artifacts a reader can open — a release, a diff, a live surface.
 *
 * artifacts: { label, value, href? }[]
 *   label — what the cell verifies ("release", "diff", "live", "source")
 *   value — the artifact reference ("v0.7.0", "PR #142", "view page")
 *   href  — optional link; renders the value as a gold-underlined anchor
 */
function ArtifactRow({
  artifacts = [],
  heading = 'Artifacts',
  style = {},
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      border: '1px solid var(--border-hair)',
      borderRadius: 'var(--radius-md)',
      background: 'var(--surface-raised)',
      overflow: 'hidden',
      ...style
    }
  }, rest), heading && /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '8px var(--space-4)',
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-2xs)',
      letterSpacing: 'var(--tracking-caps)',
      textTransform: 'uppercase',
      color: 'var(--text-faint)',
      borderBottom: '1px solid var(--border-hair)',
      background: 'var(--surface-sunken)'
    }
  }, heading), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexWrap: 'wrap'
    }
  }, artifacts.map((cell, i) => {
    const ValueTag = cell.href ? 'a' : 'span';
    return /*#__PURE__*/React.createElement("div", {
      key: i,
      style: {
        flex: '1 1 0',
        minWidth: 132,
        padding: 'var(--space-4)',
        borderLeft: i === 0 ? 'none' : '1px solid var(--border-hair)',
        display: 'flex',
        flexDirection: 'column',
        gap: 6
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        fontFamily: 'var(--font-mono)',
        fontSize: 'var(--text-2xs)',
        letterSpacing: '0.04em',
        textTransform: 'uppercase',
        color: 'var(--text-faint)'
      }
    }, cell.label), /*#__PURE__*/React.createElement(ValueTag, _extends({}, cell.href ? {
      href: cell.href
    } : {}, {
      style: {
        fontFamily: 'var(--font-mono)',
        fontSize: 'var(--text-sm)',
        color: cell.href ? 'var(--artifact-link)' : 'var(--text-strong)',
        textDecoration: cell.href ? 'underline' : 'none',
        textDecorationColor: cell.href ? 'color-mix(in srgb, var(--gold-500) 60%, transparent)' : 'none',
        textUnderlineOffset: '3px',
        textDecorationThickness: '1.5px'
      }
    }), cell.value));
  })));
}
Object.assign(__ds_scope, { ArtifactRow });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/evidence/ArtifactRow.jsx", error: String((e && e.message) || e) }); }

// components/evidence/EvidenceBoard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — EvidenceBoard
 * The Evidence-First case board. For work best verified by releases, issues,
 * pull requests, reviews, docs or policy records rather than screenshots — a
 * site-owned operations surface, not a picture of one. Each row is named by
 * the kind of proof it links, with a quiet marker in that kind's colour.
 *
 *   eyebrow — small overline label (default "Evidence first")
 *   title   — the board heading
 *   intro   — optional standfirst beneath the title
 *   rows    — { kind, title, description }[]
 *     kind  — "release" | "source" | "review" (others fall back to neutral)
 */
const KIND = {
  release: {
    color: 'var(--status-done)',
    on: 'var(--on-done)'
  },
  source: {
    color: 'var(--artifact-link)',
    on: 'var(--river-700)'
  },
  review: {
    color: 'var(--status-review)',
    on: 'var(--on-review)'
  },
  issue: {
    color: 'var(--rust)',
    on: 'var(--rust)'
  },
  docs: {
    color: 'var(--ink-400)',
    on: 'var(--ink-500)'
  }
};
function EvidenceBoard({
  eyebrow = 'Evidence first',
  title,
  intro,
  rows = [],
  style = {},
  ...rest
}) {
  return /*#__PURE__*/React.createElement("section", _extends({
    style: {
      border: '1px solid var(--border-hair)',
      borderRadius: 'var(--radius-lg)',
      background: 'var(--surface-card)',
      boxShadow: 'var(--shadow-sm)',
      overflow: 'hidden',
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("header", {
    style: {
      padding: 'var(--space-5) var(--space-5) var(--space-4)',
      background: 'var(--surface-cool)',
      borderBottom: '1px solid var(--border-hair)'
    }
  }, eyebrow && /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-2xs)',
      letterSpacing: 'var(--tracking-caps)',
      textTransform: 'uppercase',
      color: 'var(--text-accent)'
    }
  }, eyebrow), title && /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: '6px 0 0',
      font: 'var(--type-h4)',
      color: 'var(--text-strong)'
    }
  }, title), intro && /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 'var(--space-2) 0 0',
      font: 'var(--type-ui)',
      color: 'var(--text-muted)',
      lineHeight: 'var(--leading-relaxed)',
      maxWidth: '62ch'
    }
  }, intro)), /*#__PURE__*/React.createElement("ul", {
    style: {
      listStyle: 'none',
      margin: 0,
      padding: 0
    }
  }, rows.map((row, i) => {
    const k = KIND[String(row.kind || '').toLowerCase()] || KIND.docs;
    return /*#__PURE__*/React.createElement("li", {
      key: i,
      style: {
        display: 'grid',
        gridTemplateColumns: 'auto 1fr',
        columnGap: 'var(--space-4)',
        padding: 'var(--space-4) var(--space-5)',
        borderTop: i === 0 ? 'none' : '1px solid var(--border-hair)'
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: '0.5em',
        minWidth: 84
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        width: 7,
        height: 7,
        borderRadius: '50%',
        background: k.color,
        flex: 'none',
        boxShadow: `0 0 0 3px color-mix(in srgb, ${k.color} 16%, transparent)`
      }
    }), /*#__PURE__*/React.createElement("span", {
      style: {
        fontFamily: 'var(--font-mono)',
        fontSize: 'var(--text-2xs)',
        letterSpacing: '0.04em',
        textTransform: 'uppercase',
        color: k.on
      }
    }, row.kind)), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
      style: {
        fontFamily: 'var(--font-display)',
        fontWeight: 'var(--weight-semibold)',
        fontSize: 'var(--text-lg)',
        lineHeight: 1.25,
        color: 'var(--text-strong)'
      }
    }, row.title), row.description && /*#__PURE__*/React.createElement("p", {
      style: {
        margin: '4px 0 0',
        font: 'var(--type-ui)',
        color: 'var(--text-muted)',
        lineHeight: 'var(--leading-relaxed)',
        maxWidth: '58ch'
      }
    }, row.description)));
  })));
}
Object.assign(__ds_scope, { EvidenceBoard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/evidence/EvidenceBoard.jsx", error: String((e && e.message) || e) }); }

// components/evidence/OperationalStory.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — OperationalStory
 * The featured narrative where evidence, signals, and diagrams recur as one
 * demonstration — reserved for cases like the Flavor Agent governance + demo
 * pages, where several pages describe a single operating surface. Three parts:
 * a header, a "feature state / operational checks" panel, and a numbered path
 * index that ties the pages back to one plugin demonstration.
 *
 *   eyebrow     — overline (default "Operational story")
 *   title       — feature heading
 *   intro       — standfirst beneath the title
 *   stateLabel  — left column label (default "Feature state")
 *   checksLabel — right column label (default "Operational checks")
 *   checks      — { kind, title, description }[]  (kind: governance | demo | …)
 *   index       — { marker, label, emphasis? }[]  the numbered path (01, 02, FA)
 */
const KIND = {
  governance: {
    color: 'var(--green-600)',
    on: 'var(--green-800)'
  },
  demo: {
    color: 'var(--artifact-link)',
    on: 'var(--river-700)'
  },
  policy: {
    color: 'var(--green-600)',
    on: 'var(--green-800)'
  },
  signal: {
    color: 'var(--status-review)',
    on: 'var(--on-review)'
  }
};
function OperationalStory({
  eyebrow = 'Operational story',
  title,
  intro,
  stateLabel = 'Feature state',
  checksLabel = 'Operational checks',
  checks = [],
  index = [],
  style = {},
  ...rest
}) {
  return /*#__PURE__*/React.createElement("section", _extends({
    style: {
      border: '1px solid var(--border-hair)',
      borderRadius: 'var(--radius-lg)',
      background: 'var(--surface-card)',
      boxShadow: 'var(--shadow-sm)',
      overflow: 'hidden',
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("header", {
    style: {
      padding: 'var(--space-5) var(--space-6) var(--space-4)',
      background: 'var(--surface-cool)',
      borderBottom: '1px solid var(--border-hair)'
    }
  }, eyebrow && /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-2xs)',
      letterSpacing: 'var(--tracking-caps)',
      textTransform: 'uppercase',
      color: 'var(--text-accent)'
    }
  }, eyebrow), title && /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: '6px 0 0',
      font: 'var(--type-h4)',
      color: 'var(--text-strong)'
    }
  }, title), intro && /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 'var(--space-2) 0 0',
      font: 'var(--type-ui)',
      color: 'var(--text-muted)',
      lineHeight: 'var(--leading-relaxed)',
      maxWidth: '64ch'
    }
  }, intro)), checks.length > 0 && /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 'var(--space-4) var(--space-6) var(--space-5)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      gap: 'var(--space-4)',
      paddingBottom: 'var(--space-3)',
      borderBottom: '1px solid var(--border-hair)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-2xs)',
      letterSpacing: 'var(--tracking-caps)',
      textTransform: 'uppercase',
      color: 'var(--text-faint)'
    }
  }, stateLabel), /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-2xs)',
      letterSpacing: 'var(--tracking-caps)',
      textTransform: 'uppercase',
      color: 'var(--text-faint)'
    }
  }, checksLabel)), /*#__PURE__*/React.createElement("ul", {
    style: {
      listStyle: 'none',
      margin: 0,
      padding: 0
    }
  }, checks.map((row, i) => {
    const k = KIND[String(row.kind || '').toLowerCase()] || KIND.demo;
    return /*#__PURE__*/React.createElement("li", {
      key: i,
      style: {
        display: 'grid',
        gridTemplateColumns: '128px 1fr',
        columnGap: 'var(--space-5)',
        alignItems: 'baseline',
        padding: 'var(--space-4) 0',
        borderTop: i === 0 ? 'none' : '1px solid var(--border-hair)'
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: '0.5em'
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        width: 7,
        height: 7,
        borderRadius: '50%',
        background: k.color,
        flex: 'none',
        boxShadow: `0 0 0 3px color-mix(in srgb, ${k.color} 16%, transparent)`
      }
    }), /*#__PURE__*/React.createElement("span", {
      style: {
        fontFamily: 'var(--font-mono)',
        fontSize: 'var(--text-2xs)',
        letterSpacing: '0.04em',
        textTransform: 'uppercase',
        color: k.on
      }
    }, row.kind)), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
      style: {
        fontFamily: 'var(--font-display)',
        fontWeight: 'var(--weight-semibold)',
        fontSize: 'var(--text-lg)',
        lineHeight: 1.25,
        color: 'var(--text-strong)'
      }
    }, row.title), row.description && /*#__PURE__*/React.createElement("p", {
      style: {
        margin: '4px 0 0',
        font: 'var(--type-ui)',
        color: 'var(--text-muted)',
        lineHeight: 'var(--leading-relaxed)',
        maxWidth: '58ch'
      }
    }, row.description)));
  }))), index.length > 0 && /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-2)',
      padding: 'var(--space-4) var(--space-6)',
      background: 'var(--surface-sunken)',
      borderTop: '1px solid var(--border-hair)',
      flexWrap: 'wrap'
    }
  }, index.map((node, i) => /*#__PURE__*/React.createElement(React.Fragment, {
    key: i
  }, i > 0 && /*#__PURE__*/React.createElement("span", {
    "aria-hidden": "true",
    style: {
      flex: '1 0 16px',
      height: 1,
      minWidth: 16,
      background: 'var(--border-hair)'
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: '0.6em',
      flex: 'none'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 30,
      height: 30,
      flex: 'none',
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      borderRadius: 'var(--radius-sm)',
      fontFamily: 'var(--font-mono)',
      fontSize: 'var(--text-2xs)',
      fontWeight: 'var(--weight-medium)',
      background: node.emphasis ? 'var(--accent-subtle)' : 'var(--surface-card)',
      border: `1px solid ${node.emphasis ? 'color-mix(in srgb, var(--gold-500) 55%, transparent)' : 'var(--border-hair)'}`,
      color: node.emphasis ? 'var(--accent-press)' : 'var(--text-muted)',
      boxShadow: node.emphasis ? 'var(--gilt)' : 'none'
    }
  }, node.marker), /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-mono)',
      fontSize: 'var(--text-xs)',
      color: node.emphasis ? 'var(--text-accent)' : 'var(--text-faint)',
      whiteSpace: 'nowrap'
    }
  }, node.label))))));
}
Object.assign(__ds_scope, { OperationalStory });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/evidence/OperationalStory.jsx", error: String((e && e.message) || e) }); }

// components/evidence/ProductHero.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — ProductHero
 * "Proof + Product." For visual client work — plugin UI, frontend surfaces,
 * anything a screenshot materially proves. A compact evidence board paired
 * with a framed product-media slot, so client visuals prove the work WITHOUT
 * becoming the palette: the media is contained inside chrome, the page keeps
 * its parchment register.
 *
 *   eyebrow   — overline (default "Proof + Product")
 *   title     — board heading
 *   intro     — standfirst beneath the title
 *   rows      — { kind, title, description }[] (kind: live | source | …)
 *   frame     — "browser" | "phone"   media chrome
 *   image     — screenshot src; omit to show the fill-me placeholder
 *   imageAlt  — alt text for the screenshot
 *   children  — overrides the media body (e.g. an <image-slot>)
 *   artifacts — { label, value, href? }[] strip beneath the media
 *   mediaSide — "right" (default) | "left"
 */
const KIND = {
  live: {
    color: 'var(--status-done)',
    on: 'var(--on-done)'
  },
  source: {
    color: 'var(--artifact-link)',
    on: 'var(--river-700)'
  },
  release: {
    color: 'var(--status-done)',
    on: 'var(--on-done)'
  },
  review: {
    color: 'var(--status-review)',
    on: 'var(--on-review)'
  },
  src: {
    color: 'var(--artifact-link)',
    on: 'var(--river-700)'
  }
};
function Media({
  frame,
  image,
  imageAlt,
  children
}) {
  const body = children ? children : image ? /*#__PURE__*/React.createElement("img", {
    src: image,
    alt: imageAlt || '',
    style: {
      display: 'block',
      width: '100%',
      height: '100%',
      objectFit: 'cover'
    }
  }) : /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      inset: 0,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      padding: 'var(--space-5)',
      textAlign: 'center',
      font: 'var(--type-meta)',
      color: 'var(--text-faint)',
      background: 'repeating-linear-gradient(135deg, var(--parchment-100) 0 10px, var(--parchment-200) 10px 20px)'
    }
  }, "Replace with a real screenshot before publishing.");
  if (frame === 'phone') {
    return /*#__PURE__*/React.createElement("div", {
      style: {
        width: 200,
        margin: '0 auto',
        borderRadius: 26,
        padding: 8,
        background: 'var(--surface-sunken)',
        border: '1px solid var(--border-hair)',
        boxShadow: 'var(--shadow-md)'
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        position: 'relative',
        aspectRatio: '9 / 18',
        borderRadius: 18,
        overflow: 'hidden',
        background: 'var(--surface-cool)'
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        position: 'absolute',
        top: 6,
        left: '50%',
        transform: 'translateX(-50%)',
        width: 56,
        height: 5,
        borderRadius: 3,
        background: 'var(--ink-300)',
        zIndex: 2,
        opacity: 0.6
      }
    }), body));
  }
  // browser
  return /*#__PURE__*/React.createElement("div", {
    style: {
      borderRadius: 'var(--radius-md)',
      overflow: 'hidden',
      border: '1px solid var(--border-hair)',
      background: 'var(--surface-raised)',
      boxShadow: 'var(--shadow-md)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 6,
      padding: '8px 12px',
      borderBottom: '1px solid var(--border-hair)',
      background: 'var(--surface-sunken)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 9,
      height: 9,
      borderRadius: '50%',
      background: 'var(--rust)',
      opacity: 0.55
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      width: 9,
      height: 9,
      borderRadius: '50%',
      background: 'var(--amber)',
      opacity: 0.55
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      width: 9,
      height: 9,
      borderRadius: '50%',
      background: 'var(--leaf)',
      opacity: 0.55
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1,
      marginLeft: 8,
      height: 16,
      borderRadius: 'var(--radius-pill)',
      background: 'var(--surface-card)',
      border: '1px solid var(--border-hair)'
    }
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      aspectRatio: '16 / 10',
      background: 'var(--surface-cool)'
    }
  }, body));
}
function ProductHero({
  eyebrow = 'Proof + Product',
  title,
  intro,
  rows = [],
  frame = 'browser',
  image,
  imageAlt,
  children,
  artifacts = [],
  mediaSide = 'right',
  style = {},
  ...rest
}) {
  const board = /*#__PURE__*/React.createElement("div", {
    style: {
      flex: '1 1 260px',
      minWidth: 240,
      display: 'flex',
      flexDirection: 'column'
    }
  }, eyebrow && /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-2xs)',
      letterSpacing: 'var(--tracking-caps)',
      textTransform: 'uppercase',
      color: 'var(--text-accent)'
    }
  }, eyebrow), title && /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: '8px 0 0',
      font: 'var(--type-h4)',
      color: 'var(--text-strong)'
    }
  }, title), intro && /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 'var(--space-3) 0 0',
      font: 'var(--type-ui)',
      color: 'var(--text-muted)',
      lineHeight: 'var(--leading-relaxed)'
    }
  }, intro), rows.length > 0 && /*#__PURE__*/React.createElement("ul", {
    style: {
      listStyle: 'none',
      margin: 'var(--space-5) 0 0',
      padding: 0,
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-4)'
    }
  }, rows.map((row, i) => {
    const k = KIND[String(row.kind || '').toLowerCase()] || KIND.source;
    return /*#__PURE__*/React.createElement("li", {
      key: i,
      style: {
        display: 'grid',
        gridTemplateColumns: 'auto 1fr',
        columnGap: 'var(--space-3)'
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: '0.45em',
        minWidth: 64
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        width: 7,
        height: 7,
        borderRadius: '50%',
        background: k.color,
        flex: 'none',
        boxShadow: `0 0 0 3px color-mix(in srgb, ${k.color} 16%, transparent)`
      }
    }), /*#__PURE__*/React.createElement("span", {
      style: {
        fontFamily: 'var(--font-mono)',
        fontSize: 'var(--text-2xs)',
        letterSpacing: '0.04em',
        textTransform: 'uppercase',
        color: k.on
      }
    }, row.kind)), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
      style: {
        fontFamily: 'var(--font-display)',
        fontWeight: 'var(--weight-semibold)',
        fontSize: 'var(--text-base)',
        lineHeight: 1.3,
        color: 'var(--text-strong)'
      }
    }, row.title), row.description && /*#__PURE__*/React.createElement("p", {
      style: {
        margin: '2px 0 0',
        font: 'var(--type-ui)',
        fontSize: 'var(--text-sm)',
        color: 'var(--text-muted)',
        lineHeight: 'var(--leading-normal)'
      }
    }, row.description)));
  })));
  const media = /*#__PURE__*/React.createElement("div", {
    style: {
      flex: '1 1 320px',
      minWidth: 260,
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement(Media, {
    frame: frame,
    image: image,
    imageAlt: imageAlt
  }, children), artifacts.length > 0 && /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexWrap: 'wrap',
      gap: 'var(--space-2)'
    }
  }, artifacts.map((a, i) => {
    const ValueTag = a.href ? 'a' : 'span';
    return /*#__PURE__*/React.createElement("span", {
      key: i,
      style: {
        display: 'inline-flex',
        alignItems: 'baseline',
        gap: '0.5em',
        padding: '4px 10px',
        background: 'var(--surface-raised)',
        border: '1px solid var(--border-hair)',
        borderRadius: 'var(--radius-sm)'
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        fontFamily: 'var(--font-mono)',
        fontSize: 'var(--text-2xs)',
        textTransform: 'uppercase',
        letterSpacing: '0.04em',
        color: 'var(--text-faint)'
      }
    }, a.label), /*#__PURE__*/React.createElement(ValueTag, _extends({}, a.href ? {
      href: a.href
    } : {}, {
      style: {
        fontFamily: 'var(--font-mono)',
        fontSize: 'var(--text-xs)',
        color: a.href ? 'var(--artifact-link)' : 'var(--text-strong)',
        textDecoration: a.href ? 'underline' : 'none',
        textDecorationColor: 'color-mix(in srgb, var(--gold-500) 60%, transparent)',
        textUnderlineOffset: '3px'
      }
    }), a.value));
  })));
  return /*#__PURE__*/React.createElement("section", _extends({
    style: {
      display: 'flex',
      flexWrap: 'wrap',
      gap: 'var(--space-7)',
      alignItems: 'center',
      padding: 'var(--space-6)',
      border: '1px solid var(--border-hair)',
      borderRadius: 'var(--radius-lg)',
      background: 'var(--surface-card)',
      boxShadow: 'var(--shadow-sm)',
      ...style
    }
  }, rest), mediaSide === 'left' ? /*#__PURE__*/React.createElement(React.Fragment, null, media, board) : /*#__PURE__*/React.createElement(React.Fragment, null, board, media));
}
Object.assign(__ds_scope, { ProductHero });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/evidence/ProductHero.jsx", error: String((e && e.message) || e) }); }

// components/evidence/ProofBar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — ProofBar
 * One anatomy, three states. A strip of status chips that record where a
 * claim stands. Only the rule colour and dot change between states; the
 * state word is always present, so colour is reinforcement, never the
 * sole signal. "Status is verified, not asserted."
 *
 * Each item: { kind, state, status }.
 *   kind   — what is being proven ("release", "defect", "proposal")
 *   state  — the plain-word state ("tagged", "fixed", "in review")
 *   status — one of done | review | pending (defaults from `state`)
 */
const STATUS = {
  done: {
    rule: 'var(--status-done)',
    on: 'var(--on-done)'
  },
  review: {
    rule: 'var(--status-review)',
    on: 'var(--on-review)'
  },
  pending: {
    rule: 'var(--status-pending)',
    on: 'var(--on-pending)'
  }
};
const SYN = {
  merged: 'done',
  shipped: 'done',
  delivered: 'done',
  released: 'done',
  release: 'done',
  tagged: 'done',
  fixed: 'done',
  done: 'done',
  live: 'done',
  resolved: 'done',
  review: 'review',
  'in review': 'review',
  proposal: 'review',
  proposed: 'review',
  open: 'review',
  'awaiting sign-off': 'review',
  pending: 'pending',
  scheduled: 'pending',
  queued: 'pending',
  planned: 'pending',
  reserved: 'pending'
};
function resolveStatus(s) {
  const key = STATUS[s] ? s : SYN[String(s || '').toLowerCase()] || 'pending';
  return {
    key,
    ...(STATUS[key] || STATUS.pending)
  };
}
function ProofBar({
  items = [],
  style = {},
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "list",
    style: {
      display: 'flex',
      flexWrap: 'wrap',
      gap: 'var(--space-3)',
      ...style
    }
  }, rest), items.map((it, i) => {
    const s = resolveStatus(it.status || it.state);
    return /*#__PURE__*/React.createElement("span", {
      role: "listitem",
      key: i,
      style: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: '0.5em',
        padding: '5px 12px 5px 11px',
        background: 'var(--surface-card)',
        border: '1px solid var(--border-hair)',
        borderLeft: `3px solid ${s.rule}`,
        borderRadius: 'var(--radius-sm)',
        boxShadow: 'var(--shadow-xs)',
        whiteSpace: 'nowrap'
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        width: 6,
        height: 6,
        borderRadius: '50%',
        background: s.rule,
        flex: 'none'
      }
    }), it.kind && /*#__PURE__*/React.createElement("span", {
      style: {
        fontFamily: 'var(--font-mono)',
        fontSize: 'var(--text-2xs)',
        letterSpacing: '0.01em',
        color: 'var(--text-faint)'
      }
    }, it.kind, /*#__PURE__*/React.createElement("span", {
      "aria-hidden": "true",
      style: {
        opacity: 0.6
      }
    }, ":")), /*#__PURE__*/React.createElement("span", {
      style: {
        fontFamily: 'var(--font-label)',
        fontSize: 'var(--text-xs)',
        letterSpacing: '0.07em',
        textTransform: 'uppercase',
        color: s.on
      }
    }, it.state));
  }));
}
Object.assign(__ds_scope, { resolveStatus, ProofBar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/evidence/ProofBar.jsx", error: String((e && e.message) || e) }); }

// components/evidence/WorkEntry.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — WorkEntry
 * The spine the other evidence components attach to. One row anatomy: the
 * state is carried by the left rule AND a redundant status word — never
 * colour alone. A title, a plain account of what landed, and (when the work
 * is done) the artifacts that prove it. Testimony never outranks the work.
 *
 *   status      — done | review | pending (synonyms accepted: merged,
 *                 shipped, in review, proposal, scheduled, queued, …)
 *   statusLabel — override the displayed word (e.g. "Shipped", "Delivered")
 *   title       — the entry heading
 *   children    — the account / description
 *   artifacts   — optional Artifact[] rendered as the terminus row
 *   meta        — optional trailing line (mono): dates, sign-off note
 *   href        — optional link on the title
 */
const WORD = {
  done: 'Merged',
  review: 'In review',
  pending: 'Pending'
};
function WorkEntry({
  status = 'done',
  statusLabel,
  title,
  children,
  artifacts,
  meta,
  href,
  style = {},
  ...rest
}) {
  const s = __ds_scope.resolveStatus(status);
  const word = statusLabel || WORD[s.key] || 'Pending';
  const bg = `var(--surface-${s.key})`;
  const TitleTag = href ? 'a' : 'span';
  return /*#__PURE__*/React.createElement("article", _extends({
    style: {
      position: 'relative',
      background: 'var(--surface-card)',
      border: '1px solid var(--border-hair)',
      borderLeft: `3px solid ${s.rule}`,
      borderRadius: 'var(--radius-md)',
      boxShadow: 'var(--shadow-sm)',
      padding: 'var(--space-5)',
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'baseline',
      justifyContent: 'space-between',
      gap: 'var(--space-4)',
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: '0.5em',
      padding: '2px 10px',
      background: bg,
      border: `1px solid color-mix(in srgb, ${s.rule} 32%, transparent)`,
      borderRadius: 'var(--radius-pill)',
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-2xs)',
      letterSpacing: '0.1em',
      textTransform: 'uppercase',
      color: s.on,
      whiteSpace: 'nowrap'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 5,
      height: 5,
      borderRadius: '50%',
      background: s.rule
    }
  }), word), meta && /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-mono)',
      fontSize: 'var(--text-2xs)',
      color: 'var(--text-faint)'
    }
  }, meta)), /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: 'var(--space-3) 0 0',
      font: 'var(--type-h4)',
      color: 'var(--text-strong)',
      letterSpacing: '-0.005em'
    }
  }, /*#__PURE__*/React.createElement(TitleTag, _extends({}, href ? {
    href
  } : {}, {
    style: {
      color: 'inherit',
      textDecoration: 'none'
    }
  }), title)), children && /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 'var(--space-3) 0 0',
      font: 'var(--type-ui)',
      color: 'var(--text-muted)',
      lineHeight: 'var(--leading-relaxed)',
      maxWidth: '60ch'
    }
  }, children), artifacts && artifacts.length > 0 && /*#__PURE__*/React.createElement(__ds_scope.ArtifactRow, {
    artifacts: artifacts,
    style: {
      marginTop: 'var(--space-5)'
    }
  }));
}
Object.assign(__ds_scope, { WorkEntry });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/evidence/WorkEntry.jsx", error: String((e && e.message) || e) }); }

// components/forms/Input.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — Input
 * A bordered text field on parchment with a gold focus ring. Supports a
 * label, helper/error text, and leading/trailing adornments.
 */
function Input({
  label,
  helper,
  error,
  leading = null,
  trailing = null,
  id,
  style = {},
  ...rest
}) {
  const fieldId = id || (label ? `f-${label.replace(/\s+/g, '-').toLowerCase()}` : undefined);
  const [focus, setFocus] = React.useState(false);
  const borderColor = error ? 'var(--danger)' : focus ? 'var(--accent)' : 'var(--border-strong)';
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-2)',
      ...style
    }
  }, label && /*#__PURE__*/React.createElement("label", {
    htmlFor: fieldId,
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-xs)',
      letterSpacing: 'var(--tracking-wide)',
      textTransform: 'uppercase',
      color: 'var(--text-muted)'
    }
  }, label), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-2)',
      background: 'var(--surface-card)',
      border: `1.5px solid ${borderColor}`,
      borderRadius: 'var(--radius-md)',
      padding: '0 var(--space-3)',
      boxShadow: focus ? `0 0 0 3px var(--focus-ring)` : 'var(--shadow-inset)',
      transition: 'border-color var(--dur-fast) var(--ease-calm), box-shadow var(--dur-fast) var(--ease-calm)'
    }
  }, leading && /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--text-faint)',
      display: 'inline-flex'
    }
  }, leading), /*#__PURE__*/React.createElement("input", _extends({
    id: fieldId,
    onFocus: e => {
      setFocus(true);
      rest.onFocus?.(e);
    },
    onBlur: e => {
      setFocus(false);
      rest.onBlur?.(e);
    }
  }, rest, {
    style: {
      flex: 1,
      border: 'none',
      outline: 'none',
      background: 'transparent',
      font: 'var(--type-ui)',
      color: 'var(--text-strong)',
      padding: '10px 0'
    }
  })), trailing && /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--text-faint)',
      display: 'inline-flex'
    }
  }, trailing)), (helper || error) && /*#__PURE__*/React.createElement("span", {
    style: {
      font: 'var(--type-meta)',
      fontFamily: 'var(--font-body)',
      fontSize: 'var(--text-xs)',
      color: error ? 'var(--danger)' : 'var(--text-faint)'
    }
  }, error || helper));
}
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Input.jsx", error: String((e && e.message) || e) }); }

// components/forms/Subscribe.jsx
try { (() => {
/**
 * Imladris — Subscribe
 * A newsletter sign-up unit: title, blurb, and an inline email + button.
 * Self-contained fake submit that swaps to a confirmation state.
 */
function Subscribe({
  title = 'Join the council',
  blurb = 'Essays on AI governance, oversight, and the long stewardship of power — once a fortnight.',
  cta = 'Subscribe',
  onSubmit,
  style = {}
}) {
  const [done, setDone] = React.useState(false);
  const [email, setEmail] = React.useState('');
  const submit = e => {
    e.preventDefault();
    onSubmit ? onSubmit(email) : setDone(true);
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--surface-inverse)',
      color: 'var(--text-inverse)',
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-7)',
      boxShadow: 'var(--gilt)',
      ...style
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-xs)',
      letterSpacing: 'var(--tracking-caps)',
      textTransform: 'uppercase',
      color: 'var(--gold-400)',
      marginBottom: 'var(--space-3)'
    }
  }, "The fortnightly dispatch"), /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: '0 0 var(--space-3)',
      fontFamily: 'var(--font-display)',
      fontWeight: 500,
      fontSize: 'var(--text-2xl)',
      color: 'var(--parchment-50)',
      lineHeight: 1.1
    }
  }, title), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '0 0 var(--space-5)',
      font: 'var(--type-ui)',
      color: 'var(--green-200)',
      maxWidth: '46ch'
    }
  }, blurb), done ? /*#__PURE__*/React.createElement("div", {
    style: {
      font: 'var(--type-ui)',
      color: 'var(--gold-200)',
      fontStyle: 'italic'
    }
  }, "Your name is entered. Watch for the first dispatch.") : /*#__PURE__*/React.createElement("form", {
    onSubmit: submit,
    style: {
      display: 'flex',
      gap: 'var(--space-3)',
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement("input", {
    type: "email",
    required: true,
    placeholder: "you@domain.com",
    value: email,
    onChange: e => setEmail(e.target.value),
    style: {
      flex: '1 1 220px',
      border: '1.5px solid var(--twilight-700)',
      background: 'var(--twilight-900)',
      color: 'var(--parchment-50)',
      borderRadius: 'var(--radius-md)',
      padding: '11px 14px',
      font: 'var(--type-ui)',
      outline: 'none'
    }
  }), /*#__PURE__*/React.createElement(__ds_scope.Button, {
    type: "submit",
    variant: "accent"
  }, cta)));
}
Object.assign(__ds_scope, { Subscribe });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Subscribe.jsx", error: String((e && e.message) || e) }); }

// components/framework/RingCard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — RingCard
 * One pillar of the Three Rings governance framework. Each ring binds an
 * element, a triad of virtues, and a single governing action:
 *   Vilya (Air) → EXPOSE · Narya (Fire) → GOVERN · Nenya (Water) → ATTEST
 *
 * Artwork is passed in, not baked in, so the component stays portable:
 *   figure — the Ring-bearer character image (shown as a cover under the
 *            element-tint scrim, per the brand's "tonal scrim" cover rule)
 *   badge  — the ring image, shown in a circular frame as the element marker
 * With neither, the card falls back to the gradient plate + elven-star
 * watermark and an abstract element glyph.
 */

const RINGS = {
  air: {
    ring: 'Vilya',
    element: 'Ring of Air',
    plate: 'radial-gradient(130% 130% at 70% -10%, #2C4D63 0%, #1E3040 50%, #161D24 100%)',
    scrim: '22,29,36',
    tint: 'var(--river-400)',
    verbColor: '#8FB6CE',
    glyphBg: 'var(--river-700)',
    glyph: c => /*#__PURE__*/React.createElement("path", {
      d: "M3 8h9a2.4 2.4 0 1 0-2.4-2.4M3 12h13a2.6 2.6 0 1 1-2.6 2.6M3 16h8a2.2 2.2 0 1 0-2.2 2.2",
      stroke: c,
      strokeWidth: "1.6",
      fill: "none",
      strokeLinecap: "round"
    })
  },
  fire: {
    ring: 'Narya',
    element: 'Ring of Fire',
    plate: 'radial-gradient(130% 130% at 70% -10%, #6B3A2A 0%, #3A2A24 48%, #1B1614 100%)',
    scrim: '27,22,20',
    tint: 'var(--gold-400)',
    verbColor: '#D99B5A',
    glyphBg: 'var(--rust)',
    glyph: c => /*#__PURE__*/React.createElement("path", {
      d: "M12 2c1.5 4-2.5 5-2.5 8.5A2.5 2.5 0 0 0 12 13a2.5 2.5 0 0 0 2.5-2.5C14.5 13 17 14 17 17a5 5 0 0 1-10 0c0-4.5 5-6 5-15Z",
      stroke: c,
      strokeWidth: "1.5",
      fill: "none",
      strokeLinejoin: "round"
    })
  },
  water: {
    ring: 'Nenya',
    element: 'Ring of Water',
    plate: 'radial-gradient(130% 130% at 70% -10%, #3A5C49 0%, #2E4A3A 50%, #1C2E24 100%)',
    scrim: '28,46,36',
    tint: 'var(--green-200)',
    verbColor: '#9DBFA5',
    glyphBg: 'var(--green-600)',
    glyph: c => /*#__PURE__*/React.createElement("path", {
      d: "M12 3c4 5 6 8 6 11a6 6 0 0 1-12 0c0-3 2-6 6-11Z",
      stroke: c,
      strokeWidth: "1.5",
      fill: "none",
      strokeLinejoin: "round"
    })
  }
};
function ElvenStar({
  color,
  opacity = 1,
  style
}) {
  return /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 100 100",
    fill: "none",
    style: style,
    "aria-hidden": "true"
  }, /*#__PURE__*/React.createElement("g", {
    stroke: color,
    strokeWidth: "1.4",
    strokeLinejoin: "round",
    strokeLinecap: "round",
    opacity: opacity
  }, /*#__PURE__*/React.createElement("path", {
    d: "M50 3 L63.8 16.7 L83.2 16.8 L83.3 36.2 L97 50 L83.3 63.8 L83.2 83.2 L63.8 83.3 L50 97 L36.2 83.3 L16.8 83.2 L16.7 63.8 L3 50 L16.7 36.2 L16.8 16.8 L36.2 16.7 Z"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M50 21 L57.5 42.5 L79 50 L57.5 57.5 L50 79 L42.5 57.5 L21 50 L42.5 42.5 Z",
    opacity: "0.5"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "50",
    cy: "50",
    r: "4.5",
    fill: color,
    stroke: "none"
  })));
}

/**
 * The CTA line — the natural "instrument" slot beneath each ring. Reads as a
 * caps link an outsider acts on (inspect the schema, audit the log, verify the
 * signature). When `ctaState` is "in-review" it renders status instead of a
 * link, so a ring still in flight (Attest / provenance) shows its state rather
 * than implying a signature you cannot yet hand someone.
 */
function RingCTA({
  cta,
  href = '#',
  state = 'ready',
  tint,
  verbColor,
  scrim
}) {
  const [hover, setHover] = React.useState(false);
  const base = {
    marginTop: 'auto',
    alignSelf: 'flex-start',
    display: 'inline-flex',
    alignItems: 'center',
    gap: 10,
    fontFamily: 'var(--font-label)',
    fontSize: 'var(--text-xs)',
    fontWeight: 600,
    letterSpacing: 'var(--tracking-caps)',
    textTransform: 'uppercase',
    paddingTop: 'var(--space-4)',
    borderTop: `1px solid rgba(${scrim},0.55)`,
    width: '100%'
  };
  if (state === 'in-review') {
    return /*#__PURE__*/React.createElement("span", {
      style: {
        ...base,
        color: 'var(--green-300)',
        justifyContent: 'space-between'
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        opacity: 0.85
      }
    }, cta), /*#__PURE__*/React.createElement("span", {
      style: {
        flex: 'none',
        display: 'inline-flex',
        alignItems: 'center',
        gap: 6,
        padding: '3px 9px',
        borderRadius: 'var(--radius-pill)',
        border: `1px solid rgba(${scrim},0.9)`,
        background: 'rgba(255,255,255,0.04)',
        fontSize: 'var(--text-2xs)',
        letterSpacing: '0.12em',
        color: tint
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        width: 5,
        height: 5,
        borderRadius: '50%',
        background: tint,
        opacity: 0.9
      }
    }), "In\xA0review"));
  }
  return /*#__PURE__*/React.createElement("a", {
    href: href,
    onMouseEnter: () => setHover(true),
    onMouseLeave: () => setHover(false),
    style: {
      ...base,
      color: verbColor,
      textDecoration: 'none',
      justifyContent: 'space-between',
      transition: 'color 140ms ease'
    }
  }, /*#__PURE__*/React.createElement("span", null, cta), /*#__PURE__*/React.createElement("span", {
    "aria-hidden": "true",
    style: {
      flex: 'none',
      transition: 'transform 160ms ease',
      transform: hover ? 'translateX(4px)' : 'none'
    }
  }, "\u2192"));
}
function RingCard({
  element = 'air',
  action,
  virtues,
  description,
  cta,
  href,
  ctaState = 'ready',
  figure,
  badge,
  style = {},
  ...rest
}) {
  const r = RINGS[element] || RINGS.air;
  const v = virtues || [];
  const s = r.scrim;
  return /*#__PURE__*/React.createElement("div", _extends({
    style: {
      display: 'flex',
      flexDirection: 'column',
      borderRadius: 'var(--radius-lg)',
      overflow: 'hidden',
      background: 'var(--surface-inverse)',
      color: 'var(--parchment-50)',
      boxShadow: 'var(--shadow-md), var(--gilt)',
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      minHeight: 300,
      display: 'flex',
      flexDirection: 'column',
      justifyContent: 'space-between',
      padding: 'var(--space-5)',
      background: r.plate,
      overflow: 'hidden'
    }
  }, figure ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("img", {
    src: figure,
    alt: "",
    "aria-hidden": "true",
    style: {
      position: 'absolute',
      inset: 0,
      width: '100%',
      height: '100%',
      objectFit: 'cover',
      objectPosition: 'center 18%'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      inset: 0,
      background: `linear-gradient(180deg, rgba(${s},0.78) 0%, rgba(${s},0.10) 30%, rgba(${s},0.12) 52%, rgba(${s},0.86) 88%, rgba(${s},0.96) 100%)`
    }
  })) : /*#__PURE__*/React.createElement(ElvenStar, {
    color: r.tint,
    opacity: 0.45,
    style: {
      position: 'absolute',
      right: '-13%',
      top: '50%',
      transform: 'translateY(-50%)',
      width: '52%',
      height: 'auto',
      WebkitMaskImage: 'linear-gradient(105deg, transparent 6%, #000 52%)',
      maskImage: 'linear-gradient(105deg, transparent 6%, #000 52%)'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)'
    }
  }, badge ? /*#__PURE__*/React.createElement("span", {
    style: {
      width: 46,
      height: 46,
      flex: 'none',
      borderRadius: '50%',
      overflow: 'hidden',
      boxShadow: 'var(--gilt), var(--shadow-sm)',
      border: '1px solid rgba(255,255,255,0.18)'
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: badge,
    alt: "",
    "aria-hidden": "true",
    style: {
      width: '100%',
      height: '100%',
      objectFit: 'cover'
    }
  })) : /*#__PURE__*/React.createElement("span", {
    style: {
      width: 34,
      height: 34,
      flex: 'none',
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      borderRadius: '50%',
      background: r.glyphBg,
      boxShadow: 'var(--gilt)'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 24 24",
    width: "18",
    height: "18"
  }, r.glyph('var(--parchment-50)'))), /*#__PURE__*/React.createElement("div", {
    style: {
      lineHeight: 1.15,
      textShadow: figure ? `0 1px 8px rgba(${s},0.7)` : 'none'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 600,
      fontSize: 'var(--text-xl)',
      color: 'var(--parchment-50)'
    }
  }, r.ring), /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-xs)',
      letterSpacing: 'var(--tracking-caps)',
      textTransform: 'uppercase',
      color: r.tint,
      whiteSpace: 'nowrap'
    }
  }, r.element))), v.length > 0 && /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      display: 'flex',
      gap: 8,
      flexWrap: 'wrap',
      textShadow: figure ? `0 1px 8px rgba(${s},0.85)` : 'none',
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-2xs)',
      letterSpacing: '0.14em',
      textTransform: 'uppercase',
      color: figure ? 'var(--parchment-100)' : 'var(--green-200)'
    }
  }, v.map((x, i) => /*#__PURE__*/React.createElement(React.Fragment, {
    key: x
  }, i > 0 && /*#__PURE__*/React.createElement("span", {
    style: {
      color: r.tint,
      opacity: 0.8
    }
  }, "\u2022"), /*#__PURE__*/React.createElement("span", null, x))))), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-5)',
      padding: 'var(--space-6) var(--space-5) var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 500,
      fontSize: 'var(--text-3xl)',
      lineHeight: 1,
      letterSpacing: '0.01em',
      color: r.verbColor,
      textTransform: 'uppercase'
    }
  }, action), description && /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 'var(--space-3) 0 0',
      font: 'var(--type-ui)',
      color: 'var(--green-200)',
      lineHeight: 'var(--leading-relaxed)'
    }
  }, description)), cta && /*#__PURE__*/React.createElement(RingCTA, {
    cta: cta,
    href: href,
    state: ctaState,
    tint: r.tint,
    verbColor: r.verbColor,
    scrim: s
  })));
}
Object.assign(__ds_scope, { RingCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/framework/RingCard.jsx", error: String((e && e.message) || e) }); }

// components/site/SiteFooter.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — SiteFooter
 * The site's closing chord. Ported from the henrys-digital-canvas "cinematic"
 * footer into the Imladris twilight register: an inverse plate over faint
 * valley-at-dusk imagery, a gold hairline, the identity + discipline meta,
 * social links, and a quiet colophon. Layout chrome, not page content.
 *
 *   name      — the identity line (default "Henry Perkins")
 *   meta      — discipline tags shown beneath the name
 *   links     — { label, href, icon }[]  icon: github | linkedin | mail
 *   colophon  — the bottom line (a {year} token is replaced with the year)
 *   backdrop  — twilight image URL (omit for a flat plate)
 */
const ICONS = {
  github: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
    d: "M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M9 18c-4.51 2-5-2-7-2"
  })),
  linkedin: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
    d: "M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"
  }), /*#__PURE__*/React.createElement("rect", {
    width: "4",
    height: "12",
    x: "2",
    y: "9"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "4",
    cy: "4",
    r: "2"
  })),
  mail: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
    d: "m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"
  }), /*#__PURE__*/React.createElement("rect", {
    x: "2",
    y: "4",
    width: "20",
    height: "16",
    rx: "2"
  }))
};
function SiteFooter({
  name = 'Henry Perkins',
  meta = ['Support Enablement', 'WordPress Delivery', 'AI Workflows'],
  links = [{
    label: 'GitHub',
    href: 'https://github.com/henryperkins',
    icon: 'github'
  }, {
    label: 'LinkedIn',
    href: 'https://linkedin.com/in/henryperkins',
    icon: 'linkedin'
  }, {
    label: 'Email',
    href: 'mailto:henry@lakefrontdigital.io',
    icon: 'mail'
  }],
  colophon = '© {year} Henry Perkins. Tended in Imladris, built with WordPress.',
  backdrop,
  style = {},
  ...rest
}) {
  const year = new Date().getFullYear();
  return /*#__PURE__*/React.createElement("footer", _extends({
    style: {
      position: 'relative',
      overflow: 'hidden',
      background: 'var(--surface-inverse)',
      color: 'var(--parchment-100)',
      borderTop: '1px solid var(--rule-gold)',
      ...style
    }
  }, rest), backdrop && /*#__PURE__*/React.createElement("div", {
    "aria-hidden": "true",
    style: {
      position: 'absolute',
      inset: 0
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: backdrop,
    alt: "",
    style: {
      width: '100%',
      height: '100%',
      objectFit: 'cover',
      opacity: 0.22
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      inset: 0,
      background: 'linear-gradient(180deg, rgba(20,28,24,0.72) 0%, rgba(20,28,24,0.92) 100%)'
    }
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      maxWidth: 'var(--container-wide)',
      margin: '0 auto',
      padding: 'var(--space-8) var(--space-7) var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexWrap: 'wrap',
      alignItems: 'flex-start',
      justifyContent: 'space-between',
      gap: 'var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 100 100",
    fill: "none",
    "aria-hidden": "true",
    style: {
      width: 22,
      height: 22,
      color: 'var(--gold-400)'
    }
  }, /*#__PURE__*/React.createElement("g", {
    stroke: "currentColor",
    strokeWidth: "3",
    strokeLinejoin: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M50 6 L59 41 L94 50 L59 59 L50 94 L41 59 L6 50 L41 41 Z"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "50",
    cy: "50",
    r: "6",
    fill: "currentColor",
    stroke: "none"
  }))), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0,
      fontFamily: 'var(--font-display)',
      fontSize: 'var(--text-2xl)',
      fontWeight: 'var(--weight-semibold)',
      color: 'var(--parchment-50)',
      letterSpacing: '-0.01em'
    }
  }, name)), /*#__PURE__*/React.createElement("p", {
    style: {
      display: 'flex',
      flexWrap: 'wrap',
      alignItems: 'center',
      gap: '0.6em',
      margin: 'var(--space-4) 0 0',
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-xs)',
      letterSpacing: 'var(--tracking-wide)',
      textTransform: 'uppercase',
      color: 'var(--parchment-300)'
    }
  }, meta.map((m, i) => /*#__PURE__*/React.createElement(React.Fragment, {
    key: m
  }, i > 0 && /*#__PURE__*/React.createElement("span", {
    "aria-hidden": "true",
    style: {
      width: 4,
      height: 4,
      borderRadius: '50%',
      background: 'var(--gold-500)',
      opacity: 0.7
    }
  }), /*#__PURE__*/React.createElement("span", null, m))))), /*#__PURE__*/React.createElement("ul", {
    style: {
      listStyle: 'none',
      display: 'flex',
      flexWrap: 'wrap',
      gap: 'var(--space-3)',
      margin: 0,
      padding: 0
    }
  }, links.map(l => /*#__PURE__*/React.createElement("li", {
    key: l.label
  }, /*#__PURE__*/React.createElement("a", _extends({
    href: l.href
  }, l.href && l.href.startsWith('http') ? {
    target: '_blank',
    rel: 'noopener noreferrer'
  } : {}, {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: '0.55em',
      padding: '9px 16px',
      borderRadius: 'var(--radius-pill)',
      border: '1px solid color-mix(in srgb, var(--parchment-50) 18%, transparent)',
      color: 'var(--parchment-100)',
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-sm)',
      textDecoration: 'none',
      transition: 'border-color var(--dur-fast) var(--ease-out), color var(--dur-fast) var(--ease-out)'
    },
    onMouseEnter: e => {
      e.currentTarget.style.borderColor = 'var(--gold-400)';
      e.currentTarget.style.color = 'var(--gold-400)';
    },
    onMouseLeave: e => {
      e.currentTarget.style.borderColor = 'color-mix(in srgb, var(--parchment-50) 18%, transparent)';
      e.currentTarget.style.color = 'var(--parchment-100)';
    }
  }), /*#__PURE__*/React.createElement("svg", {
    xmlns: "http://www.w3.org/2000/svg",
    viewBox: "0 0 24 24",
    width: "16",
    height: "16",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2",
    strokeLinecap: "round",
    strokeLinejoin: "round",
    "aria-hidden": "true"
  }, ICONS[l.icon] || ICONS.mail), /*#__PURE__*/React.createElement("span", null, l.label)))))), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 'var(--space-7) 0 0',
      paddingTop: 'var(--space-5)',
      borderTop: '1px solid color-mix(in srgb, var(--parchment-50) 14%, transparent)',
      fontFamily: 'var(--font-mono)',
      fontSize: 'var(--text-2xs)',
      color: 'var(--parchment-300)'
    }
  }, colophon.replace('{year}', String(year)))));
}
Object.assign(__ds_scope, { SiteFooter });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/site/SiteFooter.jsx", error: String((e && e.message) || e) }); }

// components/site/SiteHeader.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Imladris — SiteHeader
 * The site's opening chord and the footer's counterpart. A sticky masthead:
 * the star-emblem + IMLADRIS lockup, the primary nav in tracked Marcellus,
 * and an optional search + subscribe action cluster. Parchment at ~88% with a
 * blur backdrop (the system's one deliberate use of transparency), or an
 * inverse twilight register for hero bands. Layout chrome, not page content.
 *
 *   brand    — the wordmark text (default "IMLADRIS")
 *   links    — { label, href, active? }[]  primary nav
 *   cta      — { label, href } subscribe/primary action (omit to hide)
 *   search   — show the search IconButton (default false)
 *   inverse  — twilight register for placing over dark hero bands
 *   sticky   — stick to the top on scroll (default true)
 *   onBrandClick — optional handler for the lockup (home)
 */
function StarMark({
  color,
  size = 26
}) {
  return /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 100 100",
    fill: "none",
    "aria-hidden": "true",
    style: {
      width: size,
      height: size,
      flex: 'none',
      color
    }
  }, /*#__PURE__*/React.createElement("g", {
    stroke: "currentColor",
    strokeWidth: "3",
    strokeLinejoin: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M50 6 L59 41 L94 50 L59 59 L50 94 L41 59 L6 50 L41 41 Z"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "50",
    cy: "50",
    r: "6",
    fill: "currentColor",
    stroke: "none"
  })));
}
function SiteHeader({
  brand = 'IMLADRIS',
  links = [{
    label: 'Essays',
    href: '#',
    active: true
  }, {
    label: 'The Charter',
    href: '#'
  }, {
    label: 'Work',
    href: '#'
  }, {
    label: 'About',
    href: '#'
  }],
  cta = {
    label: 'Subscribe',
    href: '#'
  },
  search = false,
  inverse = false,
  sticky = true,
  mascot,
  onBrandClick,
  style = {},
  ...rest
}) {
  const markColor = inverse ? 'var(--gold-400)' : 'var(--green-700)';
  const wordColor = inverse ? 'var(--parchment-50)' : 'var(--ink-900)';
  const linkColor = inverse ? 'var(--parchment-100)' : 'var(--text-body)';
  const linkActive = inverse ? 'var(--gold-400)' : 'var(--text-strong)';
  return /*#__PURE__*/React.createElement("header", _extends({
    style: {
      position: sticky ? 'sticky' : 'static',
      top: 0,
      zIndex: 30,
      background: inverse ? 'color-mix(in srgb, var(--twilight-900) 86%, transparent)' : 'color-mix(in srgb, var(--parchment-100) 88%, transparent)',
      backdropFilter: 'saturate(140%) blur(10px)',
      WebkitBackdropFilter: 'saturate(140%) blur(10px)',
      borderBottom: `1px solid ${inverse ? 'var(--rule-gold)' : 'var(--border-hair)'}`,
      ...style
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--container-full)',
      margin: '0 auto',
      padding: 'var(--space-3) var(--space-6)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: 'var(--space-6)',
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => {
      if (onBrandClick) {
        e.preventDefault();
        onBrandClick();
      }
    },
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 'var(--space-3)',
      textDecoration: 'none',
      flex: 'none'
    }
  }, mascot ? /*#__PURE__*/React.createElement("img", {
    src: mascot,
    alt: "",
    "aria-hidden": "true",
    style: {
      width: 38,
      height: 38,
      objectFit: 'contain',
      flex: 'none',
      filter: 'drop-shadow(0 1px 2px rgba(0,0,0,0.18))'
    }
  }) : /*#__PURE__*/React.createElement(StarMark, {
    color: markColor
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-lg)',
      letterSpacing: '0.3em',
      color: wordColor,
      lineHeight: 1,
      paddingLeft: '0.06em'
    }
  }, brand)), /*#__PURE__*/React.createElement("nav", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-6)',
      flexWrap: 'wrap'
    }
  }, links.map(l => /*#__PURE__*/React.createElement("a", _extends({
    key: l.label,
    href: l.href || '#'
  }, l.href && l.href.startsWith('http') ? {
    target: '_blank',
    rel: 'noopener noreferrer'
  } : {}, {
    style: {
      position: 'relative',
      fontFamily: 'var(--font-label)',
      fontSize: 'var(--text-xs)',
      letterSpacing: '0.08em',
      textTransform: 'uppercase',
      color: l.active ? linkActive : linkColor,
      textDecoration: 'none',
      paddingBottom: 3,
      borderBottom: `1.5px solid ${l.active ? 'var(--gold-500)' : 'transparent'}`,
      transition: 'color var(--dur-fast) var(--ease-calm), border-color var(--dur-fast) var(--ease-calm)'
    },
    onMouseEnter: e => {
      e.currentTarget.style.color = linkActive;
      e.currentTarget.style.borderBottomColor = 'color-mix(in srgb, var(--gold-500) 55%, transparent)';
    },
    onMouseLeave: e => {
      e.currentTarget.style.color = l.active ? linkActive : linkColor;
      e.currentTarget.style.borderBottomColor = l.active ? 'var(--gold-500)' : 'transparent';
    }
  }), l.label))), (search || cta) && /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-2)',
      flex: 'none'
    }
  }, search && /*#__PURE__*/React.createElement(__ds_scope.IconButton, {
    label: "Search",
    variant: "ghost"
  }, /*#__PURE__*/React.createElement("svg", {
    xmlns: "http://www.w3.org/2000/svg",
    viewBox: "0 0 24 24",
    width: "18",
    height: "18",
    fill: "none",
    stroke: inverse ? 'var(--parchment-100)' : 'currentColor',
    strokeWidth: "2",
    strokeLinecap: "round",
    strokeLinejoin: "round",
    "aria-hidden": "true"
  }, /*#__PURE__*/React.createElement("circle", {
    cx: "11",
    cy: "11",
    r: "8"
  }), /*#__PURE__*/React.createElement("path", {
    d: "m21 21-4.3-4.3"
  }))), cta && /*#__PURE__*/React.createElement(__ds_scope.Button, {
    size: "sm",
    variant: inverse ? 'accent' : 'primary',
    onClick: e => {
      if (cta.href === '#') e.preventDefault?.();
    }
  }, cta.label))));
}
Object.assign(__ds_scope, { SiteHeader });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/site/SiteHeader.jsx", error: String((e && e.message) || e) }); }

// decks/expose-govern-attest/deck-stage.js
try { (() => {
// @ds-adherence-ignore -- omelette starter scaffold (raw elements/hex/px by design)
/* ═══ THIS PROJECT USES DESIGN COMPONENTS (.dc.html) ═══
 * Reference this stage from your <x-dc> template as an import — NEVER as a
 * raw <deck-stage> tag plus a <script src> (that hides the whole deck until
 * the stream finishes):
 *
 *   <x-import component-from-global-scope="deck-stage" from="./deck-stage.js"
 *             width="1920" height="1080" hint-size="100%,100%">
 *     <section data-label="Title" style="...">…</section>
 *     <section data-label="Agenda" style="...">…</section>
 *   </x-import>
 *
 * Slides are inline-styled <section> siblings; do not add a stylesheet or a
 * deck-stage:not(:defined) rule. The plain-HTML "Usage" block in the comment
 * below does NOT apply to .dc.html templates.
 */
/* BEGIN USAGE */
/**
 * <deck-stage> — reusable web component for HTML decks.
 *
 * Handles:
 *  (a) speaker notes — reads <script type="application/json" id="speaker-notes">
 *      and posts {slideIndexChanged: N} to the parent window on nav.
 *  (b) keyboard navigation — ←/→, PgUp/PgDn, Space, Home/End, number keys.
 *      On touch devices, tapping the left/right half of the stage goes
 *      prev/next — taps on links, buttons and other interactive slide
 *      content are left alone.
 *  (c) press R to reset to slide 0 (with a tasteful keyboard hint).
 *  (d) bottom-center overlay showing slide count + hints, fades out on idle.
 *  (e) auto-scaling — inner canvas is a fixed design size (default 1920×1080)
 *      scaled with `transform: scale()` to fit the viewport, letterboxed.
 *      Set the `noscale` attribute to render at authored size (1:1) — the
 *      PPTX exporter sets this so its DOM capture sees unscaled geometry.
 *  (f) print — `@media print` lays every slide out as its own page at the
 *      design size, so the browser's Print → Save as PDF produces a clean
 *      one-page-per-slide PDF with no extra setup.
 *  (g) thumbnail rail — resizable left-hand column of per-slide thumbnails
 *      (static clones). Click to navigate; ↑/↓ with a thumbnail focused to
 *      step between slides; drag to reorder; right-click for
 *      Skip / Move up / Move down / Duplicate / Delete (Delete opens a
 *      Cancel/Delete confirm dialog). Drag the rail's right edge to resize;
 *      width persists to
 *      localStorage. Skipped slides carry `data-deck-skip`, are dimmed in
 *      the rail, omitted from prev/next navigation, and hidden at print.
 *      The rail is suppressed in presenting mode, in the host's Preview
 *      mode (ViewerMode='none'), on `noscale`, on narrow viewports
 *      (≤640px), and via the `no-rail` attribute. Rail mutations dispatch
 *      a `dc-op` CustomEvent on the element (see docs/dc-ops.md) and do
 *      NOT touch the DOM: the host applies the op and re-renders;
 *      structural rail input is locked until the host posts
 *      {__dc_op_ack: true, applied}.
 *
 * Slides are HIDDEN, not unmounted. Non-active slides stay in the DOM with
 * `visibility: hidden` + `opacity: 0`, so their state (videos, iframes,
 * form inputs, React trees) is preserved across navigation.
 *
 * Lifecycle event — the component dispatches a `slidechange` CustomEvent on
 * itself whenever the active slide changes (including the initial mount).
 * The event bubbles and composes out of shadow DOM, so you can listen on
 * the <deck-stage> element or on document:
 *
 *   document.querySelector('deck-stage').addEventListener('slidechange', (e) => {
 *     e.detail.index         // new 0-based index
 *     e.detail.previousIndex // previous index, or -1 on init
 *     e.detail.total         // total slide count
 *     e.detail.slide         // the new active slide element
 *     e.detail.previousSlide // the prior slide element, or null on init
 *     e.detail.reason        // 'init' | 'keyboard' | 'click' | 'tap' | 'api'
 *   });
 *
 * Persistence: none at the deck level. The host app keeps the current slide
 * in its own URL (?slide=) and re-delivers it via location.hash on load, so a
 * bare load with no hash always starts at slide 1.
 *
 * Usage:
 *   <style>deck-stage:not(:defined){visibility:hidden}</style>
 *   <deck-stage width="1920" height="1080">
 *     <section data-label="Title">...</section>
 *     <section data-label="Agenda">...</section>
 *   </deck-stage>
 *   <script src="deck-stage.js"></script>
 *
 * The :not(:defined) rule prevents a flash of the first slide at its
 * authored styles before this script runs and attaches the shadow root.
 *
 * Slides are the direct element children of <deck-stage>. Each slide is
 * automatically tagged with:
 *   - data-screen-label="NN Label"   (1-indexed, for comment flow)
 *   - data-om-validate="no_overflowing_text,no_overlapping_text,slide_sized_text"
 *
 * Speaker notes stay in sync because the component posts {slideIndexChanged: N}
 * to the parent — just include the #speaker-notes script tag if asked for notes.
 *
 * Authoring guidance:
 *   - Write slide bodies as static HTML inside <deck-stage>, with sizing via
 *     CSS custom properties in a <style> block rather than JS constants.
 *     Static slide markup is what lets the user click a heading in edit mode
 *     and retype it directly; a slide rendered through <script type="text/babel">,
 *     React, or a loop over a JS array has to round-trip every tweak through a
 *     chat message instead. Reach for script-generated slides only when the
 *     content genuinely needs interactive behaviour static HTML can't express.
 *   - Do NOT set position/inset/width/height on the slide <section> elements —
 *     the component absolutely positions every slotted child for you.
 *   - Entrance animations: make the visible end-state the base style and
 *     animate *from* hidden, so print and reduced-motion show content.
 *     Gate the animation on [data-deck-active] and the motion query, e.g.
 *     `@media (prefers-reduced-motion:no-preference){ [data-deck-active] .x{animation:fade-in .5s both} }`.
 *     Avoid infinite decorative loops on slide content.
 */
/* END USAGE */

(() => {
  const DESIGN_W_DEFAULT = 1920;
  const DESIGN_H_DEFAULT = 1080;
  const OVERLAY_HIDE_MS = 1800;
  const VALIDATE_ATTR = 'no_overflowing_text,no_overlapping_text,slide_sized_text';
  const FINE_POINTER_MQ = matchMedia('(hover: hover) and (pointer: fine)');
  const NARROW_MQ = matchMedia('(max-width: 640px)');
  // Slide-authored controls that should keep a tap instead of it navigating.
  const INTERACTIVE_SEL = 'a[href], button, input, select, textarea, summary, label, video[controls], audio[controls], [role="button"], [onclick], [tabindex]:not([tabindex^="-"]), [contenteditable]:not([contenteditable="false" i])';
  const pad2 = n => String(n).padStart(2, '0');

  // Label precedence: data-label → data-screen-label (number stripped) → first heading → "Slide".
  const getSlideLabel = el => {
    const explicit = el.getAttribute('data-label');
    if (explicit) return explicit;
    const existing = el.getAttribute('data-screen-label');
    if (existing) return existing.replace(/^\s*\d+\s*/, '').trim() || existing;
    const h = el.querySelector('h1, h2, h3, [data-title]');
    const t = h && (h.textContent || '').trim().slice(0, 40);
    if (t) return t;
    return 'Slide';
  };
  const stylesheet = `
    :host {
      position: fixed;
      inset: 0;
      display: block;
      background: #000;
      color: #fff;
      font-family: -apple-system, BlinkMacSystemFont, "Helvetica Neue", Helvetica, Arial, sans-serif;
      overflow: hidden;
      -webkit-tap-highlight-color: transparent;
    }
    /* connectedCallback holds this until document.fonts.ready (capped 2s) so
     * the first visible paint has the deck's real typography + final rail
     * layout. opacity (not visibility) so the active slide can't un-hide
     * itself via the ::slotted([data-deck-active]) visibility:visible rule.
     * Only the stage/rail hide — the black :host background stays, so the
     * iframe doesn't flash the page's default white. */
    :host([data-fonts-pending]) .stage,
    :host([data-fonts-pending]) .rail { opacity: 0; pointer-events: none; }

    .stage {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .canvas {
      position: relative;
      transform-origin: center center;
      flex-shrink: 0;
      background: #fff;
      will-change: transform;
    }

    /* Slides live in light DOM (via <slot>) so authored CSS still applies.
       We absolutely position each slotted child to stack them. */
    ::slotted(*) {
      position: absolute !important;
      inset: 0 !important;
      width: 100% !important;
      height: 100% !important;
      box-sizing: border-box !important;
      overflow: hidden;
      opacity: 0;
      pointer-events: none;
      visibility: hidden;
    }
    ::slotted([data-deck-active]) {
      opacity: 1;
      pointer-events: auto;
      visibility: visible;
    }

    .overlay {
      position: fixed;
      left: 50%;
      bottom: 22px;
      transform: translate(-50%, 6px) scale(0.92);
      filter: blur(6px);
      display: flex;
      align-items: center;
      gap: 4px;
      padding: 4px;
      background: #000;
      color: #fff;
      border-radius: 999px;
      font-size: 12px;
      font-feature-settings: "tnum" 1;
      letter-spacing: 0.01em;
      opacity: 0;
      pointer-events: none;
      transition: opacity 260ms ease, transform 260ms cubic-bezier(.2,.8,.2,1), filter 260ms ease;
      transform-origin: center bottom;
      z-index: 2147483000;
      user-select: none;
    }
    .overlay[data-visible] {
      opacity: 1;
      pointer-events: auto;
      transform: translate(-50%, 0) scale(1);
      filter: blur(0);
    }

    .btn {
      appearance: none;
      -webkit-appearance: none;
      background: transparent;
      border: 0;
      margin: 0;
      padding: 0;
      color: inherit;
      font: inherit;
      cursor: default;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      height: 28px;
      min-width: 28px;
      border-radius: 999px;
      color: rgba(255,255,255,0.72);
      transition: background 140ms ease, color 140ms ease;
      -webkit-tap-highlight-color: transparent;
    }
    .btn:hover { background: rgba(255,255,255,0.12); color: #fff; }
    .btn:active { background: rgba(255,255,255,0.18); }
    .btn:focus { outline: none; }
    .btn:focus-visible { outline: none; }
    .btn::-moz-focus-inner { border: 0; }
    .btn svg { width: 14px; height: 14px; display: block; }
    .btn.reset {
      font-size: 11px;
      font-weight: 500;
      letter-spacing: 0.02em;
      padding: 0 10px 0 12px;
      gap: 6px;
      color: rgba(255,255,255,0.72);
    }
    .btn.reset .kbd {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 16px;
      height: 16px;
      padding: 0 4px;
      font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
      font-size: 10px;
      line-height: 1;
      color: rgba(255,255,255,0.88);
      background: rgba(255,255,255,0.12);
      border-radius: 4px;
    }

    .count {
      font-variant-numeric: tabular-nums;
      color: #fff;
      font-weight: 500;
      padding: 0 8px;
      min-width: 42px;
      text-align: center;
      font-size: 12px;
    }
    .count .sep { color: rgba(255,255,255,0.45); margin: 0 3px; font-weight: 400; }
    .count .total { color: rgba(255,255,255,0.55); }

    .divider {
      width: 1px;
      height: 14px;
      background: rgba(255,255,255,0.18);
      margin: 0 2px;
    }

    /* ── Thumbnail rail ──────────────────────────────────────────────────
       Fixed column on the left; each thumbnail is a static deep-clone of
       the light-DOM slide scaled into a 16:9 (or design-aspect) frame. The
       stage re-fits around it (see _fit); hidden during present / noscale
       / print so capture geometry and fullscreen output are unchanged. */
    .rail {
      position: fixed;
      left: 0;
      top: 0;
      bottom: 0;
      width: var(--deck-rail-w, 188px);
      background: #141414;
      border-right: 1px solid rgba(255,255,255,0.08);
      overflow-y: auto;
      overflow-x: hidden;
      padding: 12px 10px;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      gap: 12px;
      z-index: 2147482500;
      scrollbar-width: thin;
      scrollbar-color: rgba(255,255,255,0.18) transparent;
    }
    .rail::-webkit-scrollbar { width: 8px; }
    .rail::-webkit-scrollbar-track { background: transparent; margin: 2px; }
    .rail::-webkit-scrollbar-thumb {
      background: rgba(255,255,255,0.18);
      border-radius: 4px;
      border: 2px solid transparent;
      background-clip: content-box;
    }
    .rail::-webkit-scrollbar-thumb:hover {
      background: rgba(255,255,255,0.28);
      border: 2px solid transparent;
      background-clip: content-box;
    }
    :host([no-rail]) .rail,
    :host([noscale]) .rail { display: none; }
    .rail[data-presenting] { display: none; }
    @media (max-width: 640px) {
      .rail, .rail-resize { display: none; }
    }
    /* User-driven show/hide (the TweaksPanel toggle) slides instead of
       popping. Transitions are gated on :host([data-rail-anim]) — set only
       for the 200ms around the toggle — so window-resize and rail-width
       drag (which also call _fit) don't lag behind the cursor. */
    .rail[data-user-hidden] { transform: translateX(-100%); }
    :host([data-rail-anim]) .rail { transition: transform 200ms cubic-bezier(.3,.7,.4,1); }
    :host([data-rail-anim]) .stage { transition: left 200ms cubic-bezier(.3,.7,.4,1); }
    :host([data-rail-anim]) .canvas { transition: transform 200ms cubic-bezier(.3,.7,.4,1); }
    /* transition shorthand replaces rather than merges — repeat the base
       .overlay opacity/transform/filter transitions so visibility changes
       during the 200ms toggle window still fade instead of popping. */
    :host([data-rail-anim]) .overlay {
      transition: margin-left 200ms cubic-bezier(.3,.7,.4,1),
                  opacity 260ms ease,
                  transform 260ms cubic-bezier(.2,.8,.2,1),
                  filter 260ms ease;
    }

    .thumb {
      position: relative;
      display: flex;
      align-items: flex-start;
      gap: 8px;
      cursor: pointer;
      user-select: none;
    }
    .thumb .num {
      width: 16px;
      flex-shrink: 0;
      font-size: 11px;
      font-weight: 500;
      text-align: right;
      color: rgba(255,255,255,0.55);
      padding-top: 2px;
      font-variant-numeric: tabular-nums;
    }
    .thumb .frame {
      position: relative;
      flex: 1;
      min-width: 0;
      aspect-ratio: var(--deck-aspect);
      background: #fff;
      border-radius: 4px;
      outline: 2px solid transparent;
      outline-offset: 0;
      overflow: hidden;
      transition: outline-color 120ms ease;
    }
    .thumb:hover .frame { outline-color: rgba(255,255,255,0.25); }
    .thumb { outline: none; }
    .thumb:focus-visible .frame { outline-color: rgba(255,255,255,0.5); }
    .thumb[data-current] .num { color: #fff; }
    .thumb[data-current] .frame { outline-color: #D97757; }
    .thumb[data-dragging] { opacity: 0.35; }
    .thumb::before {
      content: '';
      position: absolute;
      left: 24px;
      right: 0;
      height: 3px;
      border-radius: 2px;
      background: #D97757;
      opacity: 0;
      pointer-events: none;
    }
    .thumb[data-drop="before"]::before { top: -8px; opacity: 1; }
    .thumb[data-drop="after"]::before { bottom: -8px; opacity: 1; }
    .thumb[data-skip] .frame { opacity: 0.35; }
    .thumb[data-skip] .frame::after {
      content: 'Skipped';
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(0,0,0,0.45);
      color: #fff;
      font-size: 10px;
      font-weight: 500;
      letter-spacing: 0.04em;
    }

    .ctxmenu {
      position: fixed;
      min-width: 150px;
      padding: 4px;
      background: #242424;
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 7px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.45);
      z-index: 2147483100;
      display: none;
      font-size: 12px;
    }
    .ctxmenu[data-open] { display: block; }
    .ctxmenu button {
      display: block;
      width: 100%;
      appearance: none;
      border: 0;
      background: transparent;
      color: #e8e8e8;
      font: inherit;
      text-align: left;
      padding: 6px 10px;
      border-radius: 4px;
      cursor: pointer;
    }
    .ctxmenu button:hover:not(:disabled) { background: rgba(255,255,255,0.08); }
    .ctxmenu button:disabled { opacity: 0.35; cursor: default; }
    .ctxmenu hr {
      border: 0;
      border-top: 1px solid rgba(255,255,255,0.1);
      margin: 4px 2px;
    }

    .rail-resize {
      position: fixed;
      left: calc(var(--deck-rail-w, 188px) - 3px);
      top: 0;
      bottom: 0;
      width: 6px;
      cursor: col-resize;
      z-index: 2147482600;
      touch-action: none;
    }
    .rail-resize:hover,
    .rail-resize[data-dragging] { background: rgba(255,255,255,0.12); }
    :host([no-rail]) .rail-resize,
    :host([noscale]) .rail-resize,
    .rail[data-presenting] + .rail-resize,
    .rail[data-user-hidden] + .rail-resize { display: none; }

    /* Delete-confirm popup — matches the SPA's ConfirmDialog layout
       (title + message body, depressed footer with Cancel / Delete). */
    .confirm-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.45);
      z-index: 2147483200;
      display: none;
      align-items: center;
      justify-content: center;
    }
    .confirm-backdrop[data-open] { display: flex; }
    .confirm {
      width: 320px;
      max-width: calc(100vw - 32px);
      background: #2a2a2a;
      color: #e8e8e8;
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 12px;
      box-shadow: 0 12px 32px rgba(0,0,0,0.5);
      overflow: hidden;
      font-family: inherit;
      animation: deck-confirm-in 0.18s ease;
    }
    @keyframes deck-confirm-in {
      from { opacity: 0; transform: scale(0.96); }
      to { opacity: 1; transform: scale(1); }
    }
    .confirm .body { padding: 20px 20px 16px; }
    .confirm .title { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
    .confirm .msg { font-size: 13px; line-height: 1.5; color: rgba(255,255,255,0.65); }
    .confirm .footer {
      padding: 14px 20px;
      background: #1f1f1f;
      border-top: 1px solid rgba(255,255,255,0.08);
      display: flex;
      justify-content: flex-end;
      gap: 8px;
    }
    .confirm button {
      appearance: none;
      font: inherit;
      font-size: 13px;
      font-weight: 500;
      padding: 8px 16px;
      border-radius: 8px;
      cursor: pointer;
    }
    .confirm .cancel {
      background: transparent;
      border: 0;
      color: rgba(255,255,255,0.8);
    }
    .confirm .cancel:hover { background: rgba(255,255,255,0.08); }
    .confirm .danger {
      background: #c96442;
      border: 1px solid rgba(0,0,0,0.15);
      color: #fff;
      box-shadow: 0 1px 3px rgba(166,50,68,0.3), 0 2px 6px rgba(166,50,68,0.18);
    }
    .confirm .danger:hover { background: #b5563a; }

    /* ── Print: one page per slide, no chrome ────────────────────────────
       The screen layout stacks every slide at inset:0 inside a scaled
       canvas; for print we want them in document flow at the authored
       design size so the browser paginates one slide per sheet. The
       @page size is set from the width/height attributes via the inline
       <style id="deck-stage-print-page"> that _syncPrintPageRule appends
       to the document (the @page at-rule has no effect inside shadow DOM). */
    @media print {
      :host {
        position: static;
        inset: auto;
        background: none;
        overflow: visible;
        color: inherit;
      }
      .stage { position: static; display: block; }
      .canvas {
        transform: none !important;
        width: auto !important;
        height: auto !important;
        background: none;
        will-change: auto;
      }
      ::slotted(*) {
        position: relative !important;
        inset: auto !important;
        width: var(--deck-design-w) !important;
        height: var(--deck-design-h) !important;
        box-sizing: border-box !important;
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto;
        break-after: page;
        page-break-after: always;
        break-inside: avoid;
        overflow: hidden;
      }
      /* :last-child alone isn't enough once data-deck-skip hides the
         trailing slide(s) — the last *visible* slide still carries
         break-after:page and prints a blank sheet. _markLastVisible()
         maintains data-deck-last-visible on the last non-skipped slide. */
      ::slotted(*:last-child),
      ::slotted([data-deck-last-visible]) {
        break-after: auto;
        page-break-after: auto;
      }
      ::slotted([data-deck-skip]) { display: none !important; }
      .overlay, .rail, .rail-resize, .ctxmenu, .confirm-backdrop { display: none !important; }
    }
  `;
  class DeckStage extends HTMLElement {
    static get observedAttributes() {
      return ['width', 'height', 'noscale', 'no-rail'];
    }
    constructor() {
      super();
      this._root = this.attachShadow({
        mode: 'open'
      });
      this._index = 0;
      this._slides = [];
      this._notes = [];
      this._hideTimer = null;
      this._mouseIdleTimer = null;
      this._menuIndex = -1;
      this._onKey = this._onKey.bind(this);
      this._onResize = this._onResize.bind(this);
      this._onSlotChange = this._onSlotChange.bind(this);
      this._onMouseMove = this._onMouseMove.bind(this);
      this._onTap = this._onTap.bind(this);
      this._onMessage = this._onMessage.bind(this);
      // Capture-phase close so a click anywhere dismisses the menu, but
      // ignore clicks that land inside the menu itself — otherwise the
      // capture handler runs before the menu's own (bubble) handler and
      // clears _menuIndex out from under it.
      this._onDocClick = e => {
        if (this._menu && e.composedPath && e.composedPath().includes(this._menu)) return;
        this._closeMenu();
      };
    }
    get designWidth() {
      return parseInt(this.getAttribute('width'), 10) || DESIGN_W_DEFAULT;
    }
    get designHeight() {
      return parseInt(this.getAttribute('height'), 10) || DESIGN_H_DEFAULT;
    }
    connectedCallback() {
      // Presenter-view popup loads deckUrl?_snthumb=...#N for its prev/cur/
      // next thumbnails — the rail has no business rendering inside those
      // (wrong scale, and it offsets the stage so the thumb shows a gutter).
      if (/[?&]_snthumb=/.test(location.search)) this.setAttribute('no-rail', '');
      this._render();
      this._loadNotes();
      this._syncPrintPageRule();
      window.addEventListener('keydown', this._onKey);
      window.addEventListener('resize', this._onResize);
      window.addEventListener('mousemove', this._onMouseMove, {
        passive: true
      });
      window.addEventListener('message', this._onMessage);
      window.addEventListener('click', this._onDocClick, true);
      this.addEventListener('click', this._onTap);
      // Print lays every slide out as its own page, so [data-deck-active]-
      // gated entrance styles need the attribute on every slide (not just
      // the current one) or their content prints at the hidden base style.
      // The transient freeze style lands BEFORE the attributes so any
      // attribute-keyed transition fires at 0s (changing transition-
      // duration after a transition has started doesn't affect it).
      this._onBeforePrint = () => {
        this._syncPrintPageRule();
        if (this._freezeStyle) this._freezeStyle.remove();
        this._freezeStyle = document.createElement('style');
        this._freezeStyle.textContent = '*,*::before,*::after{transition-duration:0s !important}';
        document.head.appendChild(this._freezeStyle);
        this._slides.forEach(s => s.setAttribute('data-deck-active', ''));
      };
      this._onAfterPrint = () => {
        this._applyIndex({
          showOverlay: false,
          broadcast: false
        });
        if (this._freezeStyle) {
          this._freezeStyle.remove();
          this._freezeStyle = null;
        }
      };
      window.addEventListener('beforeprint', this._onBeforePrint);
      window.addEventListener('afterprint', this._onAfterPrint);
      // Initial collection + layout happens via slotchange, which fires on mount.
      this._enableRail();
      // Hold the stage hidden until webfonts are ready so the first visible
      // paint has the deck's real typography — the :not(:defined) guard in
      // the page HTML only covers custom-element upgrade, not font load.
      // Capped so a 404'd font URL can't blank the deck indefinitely.
      this.setAttribute('data-fonts-pending', '');
      const reveal = () => this.removeAttribute('data-fonts-pending');
      // rAF first: fonts.ready is a pre-resolved promise until layout has
      // resolved the slotted text's font-family and pushed a FontFace into
      // 'loading'. Reading it here in connectedCallback (parse-time) would
      // settle the race in a microtask before any font fetch starts.
      requestAnimationFrame(() => {
        Promise.race([document.fonts ? document.fonts.ready : Promise.resolve(), new Promise(r => setTimeout(r, 2000))]).then(reveal, reveal);
      });
    }
    _enableRail() {
      // Idempotent — older host builds still post __omelette_rail_enabled.
      // no-rail guard keeps the observers/stylesheet walk off the cheap path
      // for presenter-popup thumbnail iframes (up to 9 per view).
      if (this._railEnabled || this.hasAttribute('no-rail')) return;
      this._railEnabled = true;
      // Per-viewer preference — restored alongside rail width. Default on;
      // only a stored '0' (from the TweaksPanel toggle) hides it.
      this._railVisible = true;
      try {
        if (localStorage.getItem('deck-stage.railVisible') === '0') this._railVisible = false;
      } catch (e) {}
      // Live thumbnail updates: watch the light-DOM slides for content
      // edits and re-clone just the affected thumb(s), debounced. Ignore
      // the data-deck-* / data-screen-label / data-om-validate attributes
      // this component itself writes so nav doesn't trigger spurious
      // refreshes — except data-deck-skip, which now arrives from the host
      // re-render and is what updates the rail badge, print bookkeeping,
      // and deckSkipped re-broadcast.
      const OWN_ATTRS = /^data-(deck-(?!skip$)|screen-label$|om-validate$)/;
      this._liveDirty = new Set();
      this._liveObserver = new MutationObserver(records => {
        for (const r of records) {
          if (r.type === 'attributes' && OWN_ATTRS.test(r.attributeName || '')) continue;
          let n = r.target;
          while (n && n.parentElement !== this) n = n.parentElement;
          // Skip/unskip is handled below without re-cloning (the badge sits
          // on the thumb wrapper, not the clone) — don't mark the slide
          // dirty for an attr change whose only visible effect is the badge.
          if (n && this._slideSet && this._slideSet.has(n) && !(r.type === 'attributes' && r.attributeName === 'data-deck-skip')) {
            this._liveDirty.add(n);
          }
          // Host-driven skip toggle: sync the rail badge + print + presenter
          // skipped-list the way _toggleSkip used to do locally.
          if (r.type === 'attributes' && r.attributeName === 'data-deck-skip' && n && this._slideSet && this._slideSet.has(n)) {
            const i = this._slides.indexOf(n);
            if (this._thumbs && this._thumbs[i]) {
              if (n.hasAttribute('data-deck-skip')) this._thumbs[i].thumb.setAttribute('data-skip', '');else this._thumbs[i].thumb.removeAttribute('data-skip');
            }
            this._markLastVisible();
            try {
              window.postMessage({
                slideIndexChanged: this._index,
                deckTotal: this._slides.length,
                deckSkipped: this._skippedIndices()
              }, '*');
            } catch (e) {}
          }
        }
        if (this._liveDirty.size && !this._liveTimer) {
          this._liveTimer = setTimeout(() => {
            this._liveTimer = null;
            this._liveDirty.forEach(s => this._refreshThumb(s));
            this._liveDirty.clear();
          }, 200);
        }
      });
      this._liveObserver.observe(this, {
        subtree: true,
        childList: true,
        characterData: true,
        attributes: true
      });
      // Lazy thumbnail materialization — clone the slide only when its
      // frame scrolls into (or near) the rail viewport. rootMargin gives
      // ~4 thumbs of pre-load so fast scrolling doesn't flash blanks.
      this._railObserver = new IntersectionObserver(entries => {
        entries.forEach(e => {
          if (e.isIntersecting && e.target.__deckThumb) {
            this._materialize(e.target.__deckThumb);
          }
        });
      }, {
        root: this._rail,
        rootMargin: '400px 0px'
      });
      // Tweaks typically change CSS vars / attrs OUTSIDE <deck-stage>
      // (on <html>, <body>, a wrapper div, or a <style> tag), which
      // _liveObserver can't see. Re-snapshot author CSS (constructable
      // sheet is shared by reference, so one replaceSync updates every
      // thumb shadow root) and re-sync each thumb host's attrs + custom
      // properties. In-slide DOM mutations are _liveObserver's job.
      // Debounced so slider drags don't thrash.
      this._onTweakChange = () => {
        clearTimeout(this._tweakTimer);
        this._tweakTimer = setTimeout(() => {
          this._snapshotAuthorCss();
          // One getComputedStyle for the whole batch — each
          // getPropertyValue read below reuses the same computed style
          // as long as nothing invalidates layout between thumbs.
          const cs = getComputedStyle(this);
          (this._thumbs || []).forEach(t => {
            if (t.host) this._syncThumbHostAttrs(t.host, cs);
          });
        }, 120);
      };
      window.addEventListener('tweakchange', this._onTweakChange);
      this._snapshotAuthorCss();
      // Build the rail now that it's enabled — slotchange already fired,
      // so _renderRail's early-return skipped the initial build.
      this._syncRailHidden();
      this._renderRail();
      this._fit();
    }

    /** Snapshot document stylesheets into a constructable sheet that each
     *  thumbnail's nested shadow root adopts — so author CSS styles the
     *  cloned slide content without touching this component's chrome.
     *  Cross-origin sheets throw on .cssRules — skip them. Re-callable:
     *  the existing constructable sheet is reused via replaceSync so every
     *  already-adopted shadow root picks up the fresh CSS without re-adopt. */
    _snapshotAuthorCss() {
      // :root in an adopted sheet inside a shadow root matches nothing
      // (only the document root qualifies), so author rules like
      // `:root[data-voice="modern"] .serif` never reach the clones.
      // Rewrite :root → :host and mirror <html>'s data-*/class/lang onto
      // each thumb host (see _syncThumbHostAttrs) so the same selectors
      // match inside the thumbnail's shadow tree.
      const authorCss = Array.from(document.styleSheets).map(sh => {
        try {
          return Array.from(sh.cssRules).map(r => r.cssText).join('\n');
        } catch (e) {
          return '';
        }
      }).join('\n')
      // The shadow host is featureless outside the functional :host(...)
      // form, so any compound on :root — [attr], .class, #id, :pseudo —
      // must become :host(<compound>) not :host<compound>. Same for the
      // html type selector (Tailwind class-strategy dark mode emits
      // html.dark; Pico uses html[data-theme]), which has nothing to
      // match inside the thumb's shadow tree.
      .replace(/:root((?:\[[^\]]*\]|[.#][-\w]+|:[-\w]+(?:\([^)]*\))?)+)/g, ':host($1)').replace(/:root\b/g, ':host').replace(/(^|[\s,>~+(}])html((?:\[[^\]]*\]|[.#][-\w]+|:[-\w]+(?:\([^)]*\))?)+)(?![-\w])/g, '$1:host($2)').replace(/(^|[\s,>~+(}])html(?![-\w])/g, '$1:host');
      // Every custom property the author references. _syncThumbHostAttrs
      // mirrors each one's *computed* value at <deck-stage> onto the
      // thumb host so the live value wins over the :host default above
      // regardless of which ancestor the tweak wrote to (<html>, <body>,
      // a wrapper div, or the deck-stage element itself all inherit
      // down to getComputedStyle(this)).
      this._authorVars = new Set(authorCss.match(/--[\w-]+/g) || []);
      try {
        if (!this._adoptedSheet) this._adoptedSheet = new CSSStyleSheet();
        this._adoptedSheet.replaceSync(authorCss);
      } catch (e) {
        this._adoptedSheet = null;
        this._authorCss = authorCss;
      }
    }
    _syncThumbHostAttrs(host, cs) {
      const de = document.documentElement;
      // setAttribute overwrites but can't delete — an attr removed from
      // <html> (toggleAttribute off, classList emptied) would linger on
      // the host and :host([data-*]) / :host(.foo) rules would keep
      // matching. Remove stale mirrored attrs first; iterate backward
      // because removeAttribute mutates the live NamedNodeMap.
      for (let i = host.attributes.length - 1; i >= 0; i--) {
        const n = host.attributes[i].name;
        if ((n.startsWith('data-') || n === 'class' || n === 'lang') && !de.hasAttribute(n)) {
          host.removeAttribute(n);
        }
      }
      for (const a of de.attributes) {
        if (a.name.startsWith('data-') || a.name === 'class' || a.name === 'lang') {
          host.setAttribute(a.name, a.value);
        }
      }
      // The :root→:host rewrite in _snapshotAuthorCss pins each custom
      // property to its stylesheet default on the thumb host, shadowing
      // the live value that would otherwise inherit. Tweaks can write the
      // live value on any ancestor — <html>, <body>, a wrapper div, the
      // deck-stage element — so read it as the *computed* value at
      // <deck-stage> (which sees the whole inheritance chain) rather than
      // trying to guess which element the author wrote to. Inline on the
      // host beats the :host{} rule. remove-stale covers vars dropped
      // from the stylesheet between snapshots.
      const vars = this._authorVars || new Set();
      for (let i = host.style.length - 1; i >= 0; i--) {
        const p = host.style[i];
        if (p.startsWith('--') && !vars.has(p)) host.style.removeProperty(p);
      }
      const live = cs || getComputedStyle(this);
      vars.forEach(p => {
        const v = live.getPropertyValue(p);
        if (v) host.style.setProperty(p, v.trim());else host.style.removeProperty(p);
      });
    }
    disconnectedCallback() {
      window.removeEventListener('keydown', this._onKey);
      window.removeEventListener('resize', this._onResize);
      window.removeEventListener('mousemove', this._onMouseMove);
      window.removeEventListener('message', this._onMessage);
      window.removeEventListener('click', this._onDocClick, true);
      window.removeEventListener('beforeprint', this._onBeforePrint);
      window.removeEventListener('afterprint', this._onAfterPrint);
      if (this._freezeStyle) {
        this._freezeStyle.remove();
        this._freezeStyle = null;
      }
      this.removeEventListener('click', this._onTap);
      if (this._hideTimer) clearTimeout(this._hideTimer);
      if (this._mouseIdleTimer) clearTimeout(this._mouseIdleTimer);
      if (this._liveTimer) clearTimeout(this._liveTimer);
      if (this._tweakTimer) clearTimeout(this._tweakTimer);
      if (this._railAnimTimer) clearTimeout(this._railAnimTimer);
      if (this._scaleRaf) cancelAnimationFrame(this._scaleRaf);
      if (this._liveObserver) this._liveObserver.disconnect();
      if (this._railObserver) this._railObserver.disconnect();
      if (this._onTweakChange) window.removeEventListener('tweakchange', this._onTweakChange);
    }
    attributeChangedCallback() {
      if (this._canvas) {
        this._canvas.style.width = this.designWidth + 'px';
        this._canvas.style.height = this.designHeight + 'px';
        this._canvas.style.setProperty('--deck-design-w', this.designWidth + 'px');
        this._canvas.style.setProperty('--deck-design-h', this.designHeight + 'px');
        if (this._rail) {
          this._rail.style.setProperty('--deck-aspect', this.designWidth + '/' + this.designHeight);
        }
        this._fit();
        this._scaleThumbs();
        this._syncPrintPageRule();
      }
    }
    _render() {
      const style = document.createElement('style');
      style.textContent = stylesheet;
      const stage = document.createElement('div');
      stage.className = 'stage';
      const canvas = document.createElement('div');
      canvas.className = 'canvas';
      canvas.style.width = this.designWidth + 'px';
      canvas.style.height = this.designHeight + 'px';
      canvas.style.setProperty('--deck-design-w', this.designWidth + 'px');
      canvas.style.setProperty('--deck-design-h', this.designHeight + 'px');
      const slot = document.createElement('slot');
      slot.addEventListener('slotchange', this._onSlotChange);
      canvas.appendChild(slot);
      stage.appendChild(canvas);

      // Overlay: compact, solid black, with clickable controls.
      const overlay = document.createElement('div');
      overlay.className = 'overlay export-hidden';
      overlay.setAttribute('role', 'toolbar');
      overlay.setAttribute('aria-label', 'Deck controls');
      overlay.setAttribute('data-omelette-chrome', '');
      overlay.innerHTML = `
        <button class="btn prev" type="button" aria-label="Previous slide" title="Previous (←)">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3L5 8l5 5"/></svg>
        </button>
        <span class="count" aria-live="polite"><span class="current">1</span><span class="sep">/</span><span class="total">1</span></span>
        <button class="btn next" type="button" aria-label="Next slide" title="Next (→)">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3l5 5-5 5"/></svg>
        </button>
        <span class="divider"></span>
        <button class="btn reset" type="button" aria-label="Reset to first slide" title="Reset (R)">Reset<span class="kbd">R</span></button>
      `;
      overlay.querySelector('.prev').addEventListener('click', () => this._advance(-1, 'click'));
      overlay.querySelector('.next').addEventListener('click', () => this._advance(1, 'click'));
      overlay.querySelector('.reset').addEventListener('click', () => this._go(0, 'click'));

      // Thumbnail rail + context menu. Thumbnails are populated in
      // _renderRail() after _collectSlides().
      const rail = document.createElement('div');
      rail.className = 'rail export-hidden';
      rail.setAttribute('data-omelette-chrome', '');
      // Edit mode hooks wheel to pan the canvas; this opts the rail's own
      // scrollview out so thumbnails stay scrollable while editing.
      rail.setAttribute('data-dc-wheel-passthru', '');
      rail.style.setProperty('--deck-aspect', this.designWidth + '/' + this.designHeight);
      // Edge auto-scroll while dragging a thumb near the rail's top/bottom
      // so off-screen drop targets are reachable. Native dragover fires
      // continuously while the pointer is stationary, so a per-event nudge
      // (ramped by edge proximity) is enough — no rAF loop needed.
      rail.addEventListener('dragover', e => {
        if (this._dragFrom == null) return;
        const r = rail.getBoundingClientRect();
        const EDGE = 40;
        const dt = e.clientY - r.top;
        const db = r.bottom - e.clientY;
        if (dt < EDGE) rail.scrollTop -= Math.ceil((EDGE - dt) / 3);else if (db < EDGE) rail.scrollTop += Math.ceil((EDGE - db) / 3);
      });
      const menu = document.createElement('div');
      menu.className = 'ctxmenu export-hidden';
      menu.setAttribute('data-omelette-chrome', '');
      menu.innerHTML = `
        <button type="button" data-act="skip">Skip slide</button>
        <button type="button" data-act="up">Move up</button>
        <button type="button" data-act="down">Move down</button>
        <button type="button" data-act="duplicate">Duplicate slide</button>
        <hr>
        <button type="button" data-act="delete">Delete slide</button>
      `;
      menu.addEventListener('click', e => {
        const act = e.target && e.target.getAttribute && e.target.getAttribute('data-act');
        if (!act) return;
        const i = this._menuIndex;
        this._closeMenu();
        if (act === 'skip') this._toggleSkip(i);else if (act === 'up') this._moveSlide(i, i - 1);else if (act === 'down') this._moveSlide(i, i + 1);else if (act === 'duplicate') this._duplicateSlide(i);else if (act === 'delete') this._openConfirm(i);
      });
      menu.addEventListener('contextmenu', e => e.preventDefault());

      // Rail resize handle — drag to set --deck-rail-w, persisted to
      // localStorage so the width survives reloads.
      const resize = document.createElement('div');
      resize.className = 'rail-resize export-hidden';
      resize.setAttribute('data-omelette-chrome', '');
      resize.addEventListener('pointerdown', e => {
        e.preventDefault();
        resize.setPointerCapture(e.pointerId);
        resize.setAttribute('data-dragging', '');
        const move = ev => this._setRailWidth(ev.clientX);
        const up = () => {
          resize.removeEventListener('pointermove', move);
          resize.removeEventListener('pointerup', up);
          resize.removeEventListener('pointercancel', up);
          resize.removeAttribute('data-dragging');
          try {
            localStorage.setItem('deck-stage.railWidth', String(this._railPx));
          } catch (err) {}
        };
        resize.addEventListener('pointermove', move);
        resize.addEventListener('pointerup', up);
        resize.addEventListener('pointercancel', up);
      });

      // Delete-confirm dialog — mirrors the SPA's ConfirmDialog layout.
      const confirm = document.createElement('div');
      confirm.className = 'confirm-backdrop export-hidden';
      confirm.setAttribute('data-omelette-chrome', '');
      confirm.innerHTML = `
        <div class="confirm" role="dialog" aria-modal="true">
          <div class="body">
            <div class="title">Delete slide?</div>
            <div class="msg">This slide will be removed from the deck.</div>
          </div>
          <div class="footer">
            <button type="button" class="cancel">Cancel</button>
            <button type="button" class="danger">Delete</button>
          </div>
        </div>
      `;
      confirm.addEventListener('click', e => {
        if (e.target === confirm) this._closeConfirm();
      });
      confirm.querySelector('.cancel').addEventListener('click', () => this._closeConfirm());
      confirm.querySelector('.danger').addEventListener('click', () => {
        const i = this._confirmIndex;
        this._closeConfirm();
        this._deleteSlide(i);
      });
      this._root.append(style, rail, resize, stage, overlay, menu, confirm);
      this._canvas = canvas;
      this._stage = stage;
      this._slot = slot;
      this._overlay = overlay;
      this._rail = rail;
      this._resize = resize;
      this._menu = menu;
      this._confirm = confirm;
      this._countEl = overlay.querySelector('.current');
      this._totalEl = overlay.querySelector('.total');

      // Restore persisted rail width.
      let rw = 188;
      try {
        const s = localStorage.getItem('deck-stage.railWidth');
        if (s) rw = parseInt(s, 10) || rw;
      } catch (err) {}
      this._setRailWidth(rw);
      this._syncRailHidden();
    }
    _setRailWidth(px) {
      const w = Math.max(120, Math.min(360, Math.round(px)));
      this._railPx = w;
      this.style.setProperty('--deck-rail-w', w + 'px');
      this._fit();
      // _scaleThumbs forces a sync layout (frame.offsetWidth) then writes
      // N transforms. During a resize drag this runs per-pointermove;
      // coalesce to one per frame.
      if (!this._scaleRaf) {
        this._scaleRaf = requestAnimationFrame(() => {
          this._scaleRaf = null;
          this._scaleThumbs();
        });
      }
    }

    /** @page must live in the document stylesheet — it's a no-op inside
     *  shadow DOM. (Re-)append so any author @page landing later in
     *  source order can't reintroduce a margin and push each slide onto
     *  two sheets; called again from beforeprint. */
    _syncPrintPageRule() {
      const id = 'deck-stage-print-page';
      let tag = document.getElementById(id);
      if (!tag) {
        tag = document.createElement('style');
        tag.id = id;
      }
      (document.body || document.head).appendChild(tag);
      tag.textContent = '@page { size: ' + this.designWidth + 'px ' + this.designHeight + 'px; margin: 0; } ' + '@media print { html, body { margin: 0 !important; padding: 0 !important; background: none !important; overflow: visible !important; height: auto !important; } ' + '* { -webkit-print-color-adjust: exact; print-color-adjust: exact; } ' +
      // Jump authored animations/transitions to their end state so print
      // never captures mid-entrance — pairs with the beforeprint handler
      // in connectedCallback that sets data-deck-active on every slide.
      '*, *::before, *::after { animation-delay: -99s !important; animation-duration: .001s !important; ' + 'animation-iteration-count: 1 !important; animation-fill-mode: both !important; ' + 'animation-play-state: running !important; transition-duration: 0s !important; } }';
    }
    _onSlotChange() {
      // Self-mutate path already reconciled synchronously and emitted
      // slidechange; skip the async slotchange it caused.
      if (this._squelchSlotChange) {
        this._squelchSlotChange = false;
        return;
      }
      // Primary lock-clear is the host's __deck_rail_ack; this clears on a
      // dropped ack so the rail can't stay dead.
      this._railLock = false;
      this._collectSlides();
      this._restoreIndex();
      this._applyIndex({
        showOverlay: false,
        broadcast: true,
        reason: 'init'
      });
      this._fit();
    }
    _collectSlides() {
      const assigned = this._slot.assignedElements({
        flatten: true
      });
      this._slides = assigned.filter(el => {
        // Skip template/style/script nodes even if someone slots them.
        const tag = el.tagName;
        return tag !== 'TEMPLATE' && tag !== 'SCRIPT' && tag !== 'STYLE';
      });
      this._slideSet = new Set(this._slides);
      this._slides.forEach((slide, i) => {
        const n = i + 1;
        slide.setAttribute('data-screen-label', `${pad2(n)} ${getSlideLabel(slide)}`);

        // Validation attribute for comment flow / auto-checks.
        if (!slide.hasAttribute('data-om-validate')) {
          slide.setAttribute('data-om-validate', VALIDATE_ATTR);
        }
        slide.setAttribute('data-deck-slide', String(i));
      });
      if (this._totalEl) this._totalEl.textContent = String(this._slides.length || 1);
      if (this._index >= this._slides.length) this._index = Math.max(0, this._slides.length - 1);
      this._markLastVisible();
      this._renderRail();
    }

    /** Tag the last non-skipped slide so print CSS can drop its
     *  break-after (see the @media print comment above — :last-child
     *  alone matches a hidden skipped slide). */
    _markLastVisible() {
      let last = null;
      this._slides.forEach(s => {
        s.removeAttribute('data-deck-last-visible');
        if (!s.hasAttribute('data-deck-skip')) last = s;
      });
      if (last) last.setAttribute('data-deck-last-visible', '');
    }
    _loadNotes() {
      // Per-slide data-speaker-notes is authoritative when present (attrs
      // travel with the element on reorder/dup/delete); a slide without
      // the attr falls through to the legacy #speaker-notes JSON array
      // PER SLIDE so a single attr on a JSON-authored deck doesn't blank
      // the rest.
      const tag = document.getElementById('speaker-notes');
      let json = null;
      if (tag) try {
        const p = JSON.parse(tag.textContent || '[]');
        if (Array.isArray(p)) json = p;
      } catch (e) {
        console.warn('[deck-stage] Failed to parse #speaker-notes JSON:', e);
      }
      this._notes = this._slides.map((s, i) => {
        const a = s.getAttribute('data-speaker-notes');
        return a !== null ? a : json && typeof json[i] === 'string' ? json[i] : '';
      });
    }
    _restoreIndex() {
      // The host's ?slide= param is delivered as a #<int> hash (1-indexed) on
      // the iframe src. No hash → slide 1; the deck itself keeps no position
      // state across loads.
      const h = (location.hash || '').match(/^#(\d+)$/);
      if (h) {
        const n = parseInt(h[1], 10) - 1;
        if (n >= 0 && n < this._slides.length) this._index = n;
      }
    }
    _applyIndex({
      showOverlay = true,
      broadcast = true,
      reason = 'init'
    } = {}) {
      if (!this._slides.length) return;
      const prev = this._prevIndex == null ? -1 : this._prevIndex;
      const curr = this._index;
      // Keep the iframe's own hash in sync so an in-iframe location.reload()
      // (reload banner path in viewer-handle.ts) lands on the current slide,
      // not the stale deep-link hash from initial load.
      try {
        history.replaceState(null, '', '#' + (curr + 1));
      } catch (e) {}
      this._slides.forEach((s, i) => {
        if (i === curr) s.setAttribute('data-deck-active', '');else s.removeAttribute('data-deck-active');
      });
      if (this._countEl) this._countEl.textContent = String(curr + 1);
      // Follow-scroll on every navigation (init deep-link, keyboard, click,
      // tap, external goTo) — the only time we *don't* want the rail to
      // track current is after a rail-internal mutation, where _renderRail
      // has already restored the user's scroll position and yanking back to
      // current would undo it.
      this._syncRail(reason !== 'mutation');
      if (broadcast) {
        // (1) Legacy: host-window postMessage for speaker-notes renderers.
        try {
          window.postMessage({
            slideIndexChanged: curr,
            deckTotal: this._slides.length,
            deckSkipped: this._skippedIndices()
          }, '*');
        } catch (e) {}

        // (2) In-page CustomEvent on the <deck-stage> element itself.
        //     Bubbles and composes out of shadow DOM so slide code can listen:
        //       document.querySelector('deck-stage').addEventListener('slidechange', e => {
        //         e.detail.index, e.detail.previousIndex, e.detail.total, e.detail.slide, e.detail.reason
        //       });
        const detail = {
          index: curr,
          previousIndex: prev,
          total: this._slides.length,
          slide: this._slides[curr] || null,
          previousSlide: prev >= 0 ? this._slides[prev] || null : null,
          reason: reason // 'init' | 'keyboard' | 'click' | 'tap' | 'api'
        };
        this.dispatchEvent(new CustomEvent('slidechange', {
          detail,
          bubbles: true,
          composed: true
        }));
      }
      this._prevIndex = curr;
      if (showOverlay) this._flashOverlay();
    }
    _flashOverlay() {
      // Host posts __omelette_presenting while in fullscreen/tab presentation
      // mode — suppress the nav footer entirely (both hover and slide-change
      // flash) so the audience sees clean slides.
      if (!this._overlay || this._presenting) return;
      this._overlay.setAttribute('data-visible', '');
      if (this._hideTimer) clearTimeout(this._hideTimer);
      this._hideTimer = setTimeout(() => {
        this._overlay.removeAttribute('data-visible');
      }, OVERLAY_HIDE_MS);
    }
    _railWidth() {
      // State-based, no offsetWidth: the first _fit() can run before the
      // rail has had layout on some load paths, and a 0 there paints the
      // slide full-width for one frame before the post-slotchange _fit()
      // corrects it.
      if (!this._railEnabled || !this._railVisible || this.hasAttribute('no-rail') || this.hasAttribute('noscale') || this._presenting || this._previewMode || NARROW_MQ.matches) return 0;
      return this._railPx || 0;
    }
    _fit() {
      if (!this._canvas) return;
      const stage = this._canvas.parentElement;
      // PPTX export sets noscale so the DOM capture sees authored-size
      // geometry — the scaled canvas is in shadow DOM, so the exporter's
      // resetTransformSelector can't reach .canvas.style.transform directly.
      if (this.hasAttribute('noscale')) {
        this._canvas.style.transform = 'none';
        if (stage) stage.style.left = '0';
        if (this._overlay) this._overlay.style.marginLeft = '0';
        return;
      }
      const rw = this._railWidth();
      if (stage) stage.style.left = rw + 'px';
      // Overlay is centred on the viewport via left:50% + translate(-50%);
      // marginLeft shifts the centre by rw/2 so it lands in the middle of
      // the [rw, innerWidth] stage region.
      if (this._overlay) this._overlay.style.marginLeft = rw / 2 + 'px';
      const vw = window.innerWidth - rw;
      const vh = window.innerHeight;
      const s = Math.min(vw / this.designWidth, vh / this.designHeight);
      this._canvas.style.transform = `scale(${s})`;
    }
    _onResize() {
      this._fit();
      // Crossing the narrow-viewport breakpoint reveals the rail — rerun the
      // thumbnail scale the same way _setRailWidth does.
      if (!this._scaleRaf) {
        this._scaleRaf = requestAnimationFrame(() => {
          this._scaleRaf = null;
          this._scaleThumbs();
        });
      }
    }
    _onMouseMove() {
      // Keep overlay visible while mouse moves; hide after idle.
      this._flashOverlay();
    }
    _onMessage(e) {
      const d = e.data;
      if (d && typeof d.__omelette_presenting === 'boolean') {
        this._presenting = d.__omelette_presenting;
        if (this._presenting && this._overlay) {
          this._overlay.removeAttribute('data-visible');
          if (this._hideTimer) clearTimeout(this._hideTimer);
        }
        this._syncRailHidden();
        this._closeMenu();
        this._closeConfirm();
        this._fit();
        this._scaleThumbs();
      }
      // Host's Preview segment (ViewerMode='none'): the rail's drag-reorder /
      // right-click skip-delete affordances are editing chrome, so hide it
      // while the user is just looking at the deck. Same hard-hide path as
      // presenting; independent of the user's _railVisible preference so
      // returning to Edit restores whatever they had.
      if (d && typeof d.__omelette_preview_mode === 'boolean') {
        if (d.__omelette_preview_mode === this._previewMode) return;
        this._previewMode = d.__omelette_preview_mode;
        this._syncRailHidden();
        this._closeMenu();
        this._closeConfirm();
        this._fit();
        this._scaleThumbs();
      }
      // Host has processed a dc-op; rail input is safe again. Not tied to
      // slotchange — setAttr and refusal don't fire one. On refusal,
      // revert the optimistic _index/hash adjustment so the next nav
      // starts from what's actually on screen.
      if (d && d.__dc_op_ack) {
        this._railLock = false;
        if (d.applied === false && this._indexBeforeEmit != null) {
          this._index = this._indexBeforeEmit;
          try {
            history.replaceState(null, '', '#' + (this._index + 1));
          } catch (e) {}
        }
        this._indexBeforeEmit = null;
      }
      // Per-viewer show/hide, driven by the TweaksPanel's auto-injected
      // "Thumbnail rail" toggle (or any author script). Independent of
      // whether the Tweaks panel itself is open — closing the panel
      // doesn't change rail visibility. Persists alongside rail width.
      if (d && d.type === '__deck_rail_visible' && typeof d.on === 'boolean') {
        if (d.on === this._railVisible) return;
        this._railVisible = d.on;
        try {
          localStorage.setItem('deck-stage.railVisible', d.on ? '1' : '0');
        } catch (e) {}
        // Arm the transition, commit it, then flip state — otherwise the
        // browser coalesces both writes and nothing animates on show.
        this.setAttribute('data-rail-anim', '');
        void (this._rail && this._rail.offsetHeight);
        this._syncRailHidden();
        this._fit();
        this._scaleThumbs();
        clearTimeout(this._railAnimTimer);
        this._railAnimTimer = setTimeout(() => this.removeAttribute('data-rail-anim'), 220);
      }
      if (d && d.type === '__omelette_rail_enabled') this._enableRail();
    }
    _syncRailHidden() {
      if (!this._rail) return;
      // data-presenting is the hard hide (display:none) for flag-off,
      // presentation mode, and the host's Preview segment — instant, no
      // transition. data-user-hidden is the soft hide (translateX(-100%))
      // for the viewer's rail toggle, so show/hide slides under
      // :host([data-rail-anim]).
      const hard = !this._railEnabled || this._presenting || this._previewMode;
      if (hard) this._rail.setAttribute('data-presenting', '');else this._rail.removeAttribute('data-presenting');
      if (!this._railVisible) this._rail.setAttribute('data-user-hidden', '');else this._rail.removeAttribute('data-user-hidden');
      // translateX hide leaves thumbs (tabIndex=0) in the tab order —
      // inert keeps them unfocusable while the rail is off-screen.
      this._rail.inert = hard || !this._railVisible;
    }
    _onTap(e) {
      // Touch-only — keyboard + the overlay toolbar cover nav on desktop.
      if (FINE_POINTER_MQ.matches) return;
      // Only taps that land on the stage (slide content or letterbox); the
      // overlay / rail / menus are siblings with their own click handlers.
      const path = e.composedPath();
      if (!this._stage || !path.includes(this._stage)) return;
      // Let interactive slide content keep the tap. composedPath (not
      // e.target.closest) so we see through open shadow roots — a <button>
      // inside a slide-authored custom element retargets e.target to the
      // host but still appears in the composed path.
      if (e.defaultPrevented) return;
      for (const n of path) {
        if (n === this._stage) break;
        if (n.matches && n.matches(INTERACTIVE_SEL)) return;
      }
      e.preventDefault();
      const rw = this._railWidth();
      const mid = rw + (window.innerWidth - rw) / 2;
      this._advance(e.clientX < mid ? -1 : 1, 'tap');
    }
    _onKey(e) {
      // Ignore when the user is typing.
      const t = e.target;
      if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
      // Confirm dialog swallows nav keys while open; Escape cancels. Enter
      // is left to the focused button's native activation so Tab→Cancel
      // →Enter activates Cancel, not the window-level confirm path.
      if (this._confirm && this._confirm.hasAttribute('data-open')) {
        if (e.key === 'Escape') {
          this._closeConfirm();
          e.preventDefault();
        }
        return;
      }
      if (e.key === 'Escape' && this._menu && this._menu.hasAttribute('data-open')) {
        this._closeMenu();
        e.preventDefault();
        return;
      }
      if (e.metaKey || e.ctrlKey || e.altKey) return;
      const key = e.key;
      let handled = true;
      if (key === 'ArrowRight' || key === 'PageDown' || key === ' ' || key === 'Spacebar') {
        this._advance(1, 'keyboard');
      } else if (key === 'ArrowLeft' || key === 'PageUp') {
        this._advance(-1, 'keyboard');
      } else if (key === 'Home') {
        this._go(0, 'keyboard');
      } else if (key === 'End') {
        this._go(this._slides.length - 1, 'keyboard');
      } else if (key === 'r' || key === 'R') {
        this._go(0, 'keyboard');
      } else if (/^[0-9]$/.test(key)) {
        // 1..9 jump to that slide; 0 jumps to 10.
        const n = key === '0' ? 9 : parseInt(key, 10) - 1;
        if (n < this._slides.length) this._go(n, 'keyboard');
      } else {
        handled = false;
      }
      if (handled) {
        e.preventDefault();
        this._flashOverlay();
      }
    }
    _go(i, reason = 'api') {
      if (!this._slides.length) return;
      const clamped = Math.max(0, Math.min(this._slides.length - 1, i));
      if (clamped === this._index) {
        this._flashOverlay();
        return;
      }
      this._index = clamped;
      this._applyIndex({
        showOverlay: true,
        broadcast: true,
        reason
      });
    }

    /** Step forward/back skipping any slide marked data-deck-skip. Falls
     *  back to _go's clamp-at-ends behaviour (flash overlay) when there's
     *  nothing further in that direction. */
    _advance(dir, reason) {
      if (!this._slides.length) return;
      let i = this._index + dir;
      while (i >= 0 && i < this._slides.length && this._slides[i].hasAttribute('data-deck-skip')) {
        i += dir;
      }
      if (i < 0 || i >= this._slides.length) {
        this._flashOverlay();
        return;
      }
      this._go(i, reason);
    }

    // ── Thumbnail rail ────────────────────────────────────────────────────
    //
    // Thumbs are keyed by slide element and reused across _renderRail()
    // calls, so a reorder/delete is an O(changed) DOM shuffle instead of an
    // O(N) teardown-and-re-clone. Each thumb starts as a lightweight shell
    // (num + empty frame); the clone is materialized lazily by an
    // IntersectionObserver when the frame scrolls into (or near) view, so
    // only visible-ish slides pay the clone + image-decode cost.

    _renderRail() {
      if (!this._rail || !this._railEnabled) {
        this._thumbs = [];
        return;
      }
      // FLIP: record each *materialized* thumb's top before the reconcile.
      // Off-screen (non-materialized) thumbs don't need the animation and
      // skipping their getBoundingClientRect saves a forced layout per
      // off-screen thumb on large decks.
      const prevTops = new Map();
      (this._thumbs || []).forEach(({
        thumb,
        slide,
        host
      }) => {
        if (host) prevTops.set(slide, thumb.getBoundingClientRect().top);
      });
      const st = this._rail.scrollTop;

      // Reconcile: reuse thumbs that already exist for a slide, create
      // shells for new slides, drop thumbs for removed slides.
      const bySlide = new Map();
      (this._thumbs || []).forEach(t => bySlide.set(t.slide, t));
      const next = [];
      this._slides.forEach(slide => {
        let t = bySlide.get(slide);
        if (t) bySlide.delete(slide);else t = this._makeThumb(slide);
        next.push(t);
      });
      // Orphans — slides removed since last render.
      bySlide.forEach(t => {
        if (this._railObserver) this._railObserver.unobserve(t.frame);
        t.thumb.remove();
      });
      // Put thumbs into document order to match _slides. insertBefore on
      // an already-correctly-placed node is a no-op, so this is cheap
      // when nothing moved.
      next.forEach((t, i) => {
        const want = t.thumb;
        const at = this._rail.children[i];
        if (at !== want) this._rail.insertBefore(want, at || null);
        t.i = i;
        t.num.textContent = String(i + 1);
        if (t.slide.hasAttribute('data-deck-skip')) t.thumb.setAttribute('data-skip', '');else t.thumb.removeAttribute('data-skip');
      });
      this._thumbs = next;
      this._rail.scrollTop = st;
      if (prevTops.size) {
        const moved = [];
        this._thumbs.forEach(({
          thumb,
          slide
        }) => {
          const old = prevTops.get(slide);
          if (old == null) return;
          const dy = old - thumb.getBoundingClientRect().top;
          if (Math.abs(dy) < 1) return;
          thumb.style.transition = 'none';
          thumb.style.transform = `translateY(${dy}px)`;
          moved.push(thumb);
        });
        if (moved.length) {
          // Commit the inverted positions before flipping the transition
          // on — otherwise the browser coalesces both style writes and
          // nothing animates.
          void this._rail.offsetHeight;
          moved.forEach(t => {
            t.style.transition = 'transform 180ms cubic-bezier(.2,.7,.3,1)';
            t.style.transform = '';
          });
          setTimeout(() => moved.forEach(t => {
            t.style.transition = '';
          }), 220);
        }
      }
      requestAnimationFrame(() => this._scaleThumbs());
      this._syncRail(false);
    }

    /** Create a lightweight thumb shell for one slide. The clone is
     *  materialized later by the IntersectionObserver. Event handlers
     *  look up the thumb's *current* index (via _thumbs.indexOf) so the
     *  same element can be reused across reorders. */
    _makeThumb(slide) {
      const thumb = document.createElement('div');
      thumb.className = 'thumb';
      thumb.tabIndex = 0;
      const num = document.createElement('div');
      num.className = 'num';
      const frame = document.createElement('div');
      frame.className = 'frame';
      thumb.append(num, frame);
      const entry = {
        thumb,
        num,
        frame,
        slide,
        clone: null,
        host: null,
        i: -1
      };
      // entry.i is refreshed on every _renderRail reconcile pass, so
      // handlers read the thumb's current position without an O(N) scan.
      const idx = () => entry.i;
      thumb.addEventListener('click', () => this._go(idx(), 'click'));
      // ↑/↓ step through the rail when a thumb has focus. _go clamps at the
      // ends and _applyIndex→_syncRail scrolls the new current thumb into
      // view; we move focus to it (preventScroll — _syncRail already
      // scrolled) so a held key walks the whole list. stopPropagation keeps
      // this out of the window-level _onKey nav handler.
      thumb.addEventListener('keydown', e => {
        if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
        if (e.metaKey || e.ctrlKey || e.altKey) return;
        e.preventDefault();
        e.stopPropagation();
        this._go(idx() + (e.key === 'ArrowDown' ? 1 : -1), 'keyboard');
        const cur = this._thumbs && this._thumbs[this._index];
        if (cur) cur.thumb.focus({
          preventScroll: true
        });
      });
      thumb.addEventListener('contextmenu', e => {
        e.preventDefault();
        this._openMenu(idx(), e.clientX, e.clientY);
      });
      thumb.draggable = true;
      thumb.addEventListener('dragstart', e => {
        this._dragFrom = idx();
        thumb.setAttribute('data-dragging', '');
        e.dataTransfer.effectAllowed = 'move';
        try {
          e.dataTransfer.setData('text/plain', String(this._dragFrom));
        } catch (err) {}
      });
      thumb.addEventListener('dragend', () => {
        thumb.removeAttribute('data-dragging');
        this._clearDrop();
        this._dragFrom = null;
      });
      thumb.addEventListener('dragover', e => {
        if (this._dragFrom == null) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const r = thumb.getBoundingClientRect();
        this._setDrop(idx(), e.clientY < r.top + r.height / 2 ? 'before' : 'after');
      });
      thumb.addEventListener('drop', e => {
        if (this._dragFrom == null) return;
        e.preventDefault();
        const i = idx();
        const r = thumb.getBoundingClientRect();
        let to = e.clientY >= r.top + r.height / 2 ? i + 1 : i;
        if (this._dragFrom < to) to--;
        const from = this._dragFrom;
        this._clearDrop();
        this._dragFrom = null;
        if (to !== from) this._moveSlide(from, to);
      });
      if (this._railObserver) this._railObserver.observe(frame);
      frame.__deckThumb = entry;
      return entry;
    }

    /** Lazily build the clone for a thumb that has scrolled into view. */
    _materialize(entry) {
      if (entry.host) return;
      const dw = this.designWidth,
        dh = this.designHeight;
      let clone = entry.slide.cloneNode(true);
      clone.removeAttribute('id');
      clone.removeAttribute('data-deck-active');
      clone.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));
      // Neuter heavy media; replace <video> with its poster so the box
      // keeps a visual. <iframe>/<audio> become empty placeholders.
      clone.querySelectorAll('iframe, audio, object, embed').forEach(el => {
        el.removeAttribute('src');
        el.removeAttribute('srcdoc');
        el.removeAttribute('data');
        el.innerHTML = '';
      });
      clone.querySelectorAll('video').forEach(el => {
        if (!el.poster) {
          el.removeAttribute('src');
          el.innerHTML = '';
          return;
        }
        const img = document.createElement('img');
        img.src = el.poster;
        img.alt = '';
        img.style.cssText = el.style.cssText + ';object-fit:cover;width:100%;height:100%;';
        img.className = el.className;
        el.replaceWith(img);
      });
      // Images: defer decode and let the browser pick the smallest
      // srcset candidate for the ~140px thumb. Same-URL clones reuse the
      // slide's decoded bitmap (URL-keyed cache), so the remaining cost
      // is paint/composite — lazy+async keeps that off the main thread.
      clone.querySelectorAll('img').forEach(el => {
        el.loading = 'lazy';
        el.decoding = 'async';
        if (el.srcset) el.sizes = (this._railPx || 188) + 'px';
      });
      // Custom elements inside the slide would have their
      // connectedCallback fire when the clone is appended. Replace them
      // with inert boxes so a component-heavy deck doesn't run N copies
      // of each component's mount logic in the rail. Children are
      // preserved so layout-wrapper elements (<my-column><h2>…</h2>)
      // still show their authored content; the querySelectorAll NodeList
      // is static, so nested custom elements in the moved subtree are
      // still visited on later iterations.
      const neuter = el => {
        const box = document.createElement('div');
        box.style.cssText = (el.getAttribute('style') || '') + ';background:rgba(0,0,0,0.06);border:1px dashed rgba(0,0,0,0.15);';
        box.className = el.className;
        // Preserve theming/i18n hooks so [data-*] / :lang() / [dir]
        // descendant selectors still match the neutered root.
        for (const a of el.attributes) {
          const n = a.name;
          if (n.startsWith('data-') || n.startsWith('aria-') || n === 'lang' || n === 'dir' || n === 'role' || n === 'title') {
            box.setAttribute(n, a.value);
          }
        }
        while (el.firstChild) box.appendChild(el.firstChild);
        return box;
      };
      // querySelectorAll('*') returns descendants only — a custom-element
      // slide root (<my-slide>…</my-slide>) would slip through and upgrade
      // on append. Swap the root first.
      if (clone.tagName.includes('-')) clone = neuter(clone);
      clone.querySelectorAll('*').forEach(el => {
        if (el.tagName.includes('-')) el.replaceWith(neuter(el));
      });
      clone.style.cssText += ';position:absolute;top:0;left:0;transform-origin:0 0;' + 'pointer-events:none;width:' + dw + 'px;height:' + dh + 'px;' + 'box-sizing:border-box;overflow:hidden;visibility:visible;opacity:1;';
      const host = document.createElement('div');
      host.style.cssText = 'position:absolute;inset:0;';
      this._syncThumbHostAttrs(host);
      const sr = host.attachShadow({
        mode: 'open'
      });
      if (this._adoptedSheet) sr.adoptedStyleSheets = [this._adoptedSheet];else {
        const st = document.createElement('style');
        st.textContent = this._authorCss || '';
        sr.appendChild(st);
      }
      sr.appendChild(clone);
      entry.frame.appendChild(host);
      entry.host = host;
      entry.clone = clone;
      if (this._thumbScale) clone.style.transform = 'scale(' + this._thumbScale + ')';
      // Once materialized the IO callback is a no-op early-return —
      // unobserve so scroll doesn't keep firing it.
      if (this._railObserver) this._railObserver.unobserve(entry.frame);
    }

    /** Re-clone a single thumb (live-update path). No-op if the thumb
     *  hasn't been materialized yet — it'll pick up current content when
     *  it scrolls into view. */
    _refreshThumb(slide) {
      const entry = (this._thumbs || []).find(t => t.slide === slide);
      if (!entry || !entry.host) return;
      entry.host.remove();
      entry.host = entry.clone = null;
      this._materialize(entry);
    }
    _scaleThumbs() {
      if (!this._thumbs || !this._thumbs.length) return;
      // Every frame is the same width; if it reads 0 the rail is
      // display:none (noscale / no-rail / presenting / print) — leave the
      // clones as-is and re-run when the rail is revealed.
      const fw = this._thumbs[0].frame.offsetWidth;
      if (!fw) return;
      this._thumbScale = fw / this.designWidth;
      this._thumbs.forEach(({
        clone
      }) => {
        if (clone) clone.style.transform = 'scale(' + this._thumbScale + ')';
      });
    }
    _setDrop(i, where) {
      // dragover fires at pointer-event rate; touch only the previous
      // and new target rather than sweeping all N thumbs.
      const t = this._thumbs && this._thumbs[i];
      if (this._dropOn && this._dropOn !== t) {
        this._dropOn.thumb.removeAttribute('data-drop');
      }
      if (t) t.thumb.setAttribute('data-drop', where);
      this._dropOn = t || null;
    }
    _clearDrop() {
      if (this._dropOn) this._dropOn.thumb.removeAttribute('data-drop');
      this._dropOn = null;
    }
    _syncRail(follow) {
      if (!this._thumbs) return;
      this._thumbs.forEach(({
        thumb
      }, i) => {
        if (i === this._index) {
          thumb.setAttribute('data-current', '');
          if (follow && typeof thumb.scrollIntoView === 'function') {
            thumb.scrollIntoView({
              block: 'nearest'
            });
          }
        } else {
          thumb.removeAttribute('data-current');
        }
      });
    }
    _openMenu(i, x, y) {
      if (!this._menu) return;
      this._menuIndex = i;
      const slide = this._slides[i];
      const skip = slide && slide.hasAttribute('data-deck-skip');
      this._menu.querySelector('[data-act="skip"]').textContent = skip ? 'Unskip slide' : 'Skip slide';
      this._menu.querySelector('[data-act="up"]').disabled = i <= 0;
      this._menu.querySelector('[data-act="down"]').disabled = i >= this._slides.length - 1;
      this._menu.querySelector('[data-act="delete"]').disabled = this._slides.length <= 1;
      // Place, then clamp to viewport after it's measurable.
      this._menu.style.left = x + 'px';
      this._menu.style.top = y + 'px';
      this._menu.setAttribute('data-open', '');
      const r = this._menu.getBoundingClientRect();
      const nx = Math.min(x, window.innerWidth - r.width - 4);
      const ny = Math.min(y, window.innerHeight - r.height - 4);
      this._menu.style.left = Math.max(4, nx) + 'px';
      this._menu.style.top = Math.max(4, ny) + 'px';
    }
    _closeMenu() {
      if (this._menu) this._menu.removeAttribute('data-open');
      this._menuIndex = -1;
    }
    _openConfirm(i) {
      if (!this._confirm) return;
      this._confirmIndex = i;
      this._confirm.querySelector('.title').textContent = 'Delete slide ' + (i + 1) + '?';
      this._confirm.setAttribute('data-open', '');
      const btn = this._confirm.querySelector('.danger');
      if (btn && btn.focus) btn.focus();
    }
    _closeConfirm() {
      if (this._confirm) this._confirm.removeAttribute('data-open');
      this._confirmIndex = -1;
    }

    /** Rail mutations. When a dc-runtime is present (`window.__dcUpdate`)
     *  the host owns the light DOM — handlers emit a dc-op only and the
     *  host applies it (to the editor's model or to the source file) and
     *  re-renders via dc-runtime; slotchange catches the rail up.
     *  Structural ops lock rail input until the host acks so a rapid second
     *  click can't address a stale index; setAttr/removeAttr respect the
     *  lock but don't set it (indices unchanged; the host serializes).
     *  `newIndex` is written to location.hash so slotchange's
     *  _restoreIndex lands on the right slide.
     *
     *  With NO dc-runtime (a raw .html deck), there's no re-render path,
     *  so handlers self-mutate locally for an instant update and emit
     *  `emitOnly: false`; the host persists to disk without
     *  re-rendering over the already-mutated DOM.
     *
     *  See docs/dc-ops.md for the contract. */
    _emitDcOp(op, slide, lock, newIndex) {
      // Slide index (template/script/style filtered — same as
      // _collectSlides). deck-stage is a filtered-index dc-op emitter;
      // the host resolves against findDeckStage().slideTids. Callers
      // already pass `to` as a slide index.
      op.at = this._slides.indexOf(slide);
      op.witness = {
        childCount: this._slides.length
      };
      // dc-runtime wraps an <x-import>-mounted component in a
      // <div class="sc-host-x" data-dc-tpl="N"> host — the stamp is on the
      // WRAPPER, not this element. closest() finds it (or this element's
      // own stamp when directly templated).
      const host = this.closest('[data-dc-tpl]');
      const tid = host && host.getAttribute('data-dc-tpl');
      op.mount = {
        tid: tid !== null ? parseInt(tid, 10) : null,
        tag: 'deck-stage'
      };
      op.emitOnly = !!window.__dcUpdate;
      if (op.emitOnly) {
        if (lock) this._railLock = true;
        if (newIndex != null && newIndex !== this._index) {
          this._indexBeforeEmit = this._index;
          this._index = newIndex;
          try {
            history.replaceState(null, '', '#' + (newIndex + 1));
          } catch (e) {}
        }
      }
      this.dispatchEvent(new CustomEvent('dc-op', {
        detail: op,
        bubbles: true,
        composed: true
      }));
      return op.emitOnly;
    }
    _deleteSlide(i) {
      if (this._railLock) return;
      const slide = this._slides[i];
      if (!slide || this._slides.length <= 1) return;
      const cur = this._index;
      const ni = i < cur || i === cur && i === this._slides.length - 1 ? cur - 1 : cur;
      if (this._emitDcOp({
        op: 'remove'
      }, slide, true, ni)) return;
      this._index = ni;
      this._squelchSlotChange = true;
      slide.remove();
      this._collectSlides();
      this._applyIndex({
        showOverlay: true,
        broadcast: true,
        reason: 'mutation'
      });
    }
    _duplicateSlide(i) {
      if (this._railLock) return;
      const slide = this._slides[i];
      if (!slide) return;
      if (this._emitDcOp({
        op: 'duplicate'
      }, slide, true, i + 1)) return;
      const copy = slide.cloneNode(true);
      copy.removeAttribute('id');
      copy.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));
      this._index = i + 1;
      this._squelchSlotChange = true;
      this.insertBefore(copy, slide.nextSibling);
      this._collectSlides();
      this._applyIndex({
        showOverlay: true,
        broadcast: true,
        reason: 'mutation'
      });
    }
    _toggleSkip(i) {
      if (this._railLock) return;
      const slide = this._slides[i];
      if (!slide) return;
      const on = !slide.hasAttribute('data-deck-skip');
      if (this._emitDcOp(on ? {
        op: 'setAttr',
        attr: 'data-deck-skip',
        value: ''
      } : {
        op: 'removeAttr',
        attr: 'data-deck-skip'
      }, slide, false)) return;
      if (on) slide.setAttribute('data-deck-skip', '');else slide.removeAttribute('data-deck-skip');
    }
    _skippedIndices() {
      const out = [];
      for (let i = 0; i < this._slides.length; i++) {
        if (this._slides[i].hasAttribute('data-deck-skip')) out.push(i);
      }
      return out;
    }
    _moveSlide(i, j) {
      if (this._railLock || j < 0 || j >= this._slides.length || j === i) return;
      const cur = this._index;
      const ni = cur === i ? j : i < cur && j >= cur ? cur - 1 : i > cur && j <= cur ? cur + 1 : cur;
      const slide = this._slides[i];
      if (this._emitDcOp({
        op: 'move',
        to: j
      }, slide, true, ni)) return;
      const ref = j < i ? this._slides[j] : this._slides[j].nextSibling;
      this._index = ni;
      this._squelchSlotChange = true;
      this.insertBefore(slide, ref);
      this._collectSlides();
      this._applyIndex({
        showOverlay: false,
        broadcast: true,
        reason: 'mutation'
      });
    }

    // Public API ------------------------------------------------------------

    /** Current slide index (0-based). */
    get index() {
      return this._index;
    }
    /** Total slide count. */
    get length() {
      return this._slides.length;
    }
    /** Programmatically navigate. */
    goTo(i) {
      this._go(i, 'api');
    }
    next() {
      this._advance(1, 'api');
    }
    prev() {
      this._advance(-1, 'api');
    }
    reset() {
      this._go(0, 'api');
    }
  }
  if (!customElements.get('deck-stage')) {
    customElements.define('deck-stage', DeckStage);
  }
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "decks/expose-govern-attest/deck-stage.js", error: String((e && e.message) || e) }); }

// decks/expose-govern-attest/tweaks-panel.jsx
try { (() => {
// @ds-adherence-ignore -- omelette starter scaffold (raw elements/hex/px by design)

/* BEGIN USAGE */
// tweaks-panel.jsx
// Reusable Tweaks shell + form-control helpers.
// Exports (to window): useTweaks, TweaksPanel, TweakSection, TweakRow, TweakSlider,
//   TweakToggle, TweakRadio, TweakSelect, TweakText, TweakNumber, TweakColor, TweakButton.
//
// Owns the host protocol (listens for __activate_edit_mode / __deactivate_edit_mode,
// posts __edit_mode_available / __edit_mode_set_keys / __edit_mode_dismissed) so
// individual prototypes don't re-roll it. Ships a consistent set of controls so you
// don't hand-draw <input type="range">, segmented radios, steppers, etc.
//
// Usage (in an HTML file that loads React + Babel):
//
//   const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
//     "primaryColor": "#D97757",
//     "palette": ["#D97757", "#29261b", "#f6f4ef"],
//     "fontSize": 16,
//     "density": "regular",
//     "dark": false
//   }/*EDITMODE-END*/;
//
//   function App() {
//     const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
//     return (
//       <div style={{ fontSize: t.fontSize, color: t.primaryColor }}>
//         Hello
//         <TweaksPanel>
//           <TweakSection label="Typography" />
//           <TweakSlider label="Font size" value={t.fontSize} min={10} max={32} unit="px"
//                        onChange={(v) => setTweak('fontSize', v)} />
//           <TweakRadio  label="Density" value={t.density}
//                        options={['compact', 'regular', 'comfy']}
//                        onChange={(v) => setTweak('density', v)} />
//           <TweakSection label="Theme" />
//           <TweakColor  label="Primary" value={t.primaryColor}
//                        options={['#D97757', '#2A6FDB', '#1F8A5B', '#7A5AE0']}
//                        onChange={(v) => setTweak('primaryColor', v)} />
//           <TweakColor  label="Palette" value={t.palette}
//                        options={[['#D97757', '#29261b', '#f6f4ef'],
//                                  ['#475569', '#0f172a', '#f1f5f9']]}
//                        onChange={(v) => setTweak('palette', v)} />
//           <TweakToggle label="Dark mode" value={t.dark}
//                        onChange={(v) => setTweak('dark', v)} />
//         </TweaksPanel>
//       </div>
//     );
//   }
//
// TweakRadio is the segmented control for 2–3 short options (auto-falls-back to
// TweakSelect past ~16/~10 chars per label); reach for TweakSelect directly when
// options are many or long. For color tweaks always curate 3-4 options rather than
// a free picker; an option can also be a whole 2–5 color palette (the stored value
// is the array). The Tweak* controls are a floor, not a ceiling — build custom
// controls inside the panel if a tweak calls for UI they don't cover.
/* END USAGE */
// ─────────────────────────────────────────────────────────────────────────────

const __TWEAKS_STYLE = `
  .twk-panel{position:fixed;right:16px;bottom:16px;z-index:2147483646;width:280px;
    max-height:calc(100vh - 32px);display:flex;flex-direction:column;
    transform:scale(var(--dc-inv-zoom,1));transform-origin:bottom right;
    background:rgba(250,249,247,.78);color:#29261b;
    -webkit-backdrop-filter:blur(24px) saturate(160%);backdrop-filter:blur(24px) saturate(160%);
    border:.5px solid rgba(255,255,255,.6);border-radius:14px;
    box-shadow:0 1px 0 rgba(255,255,255,.5) inset,0 12px 40px rgba(0,0,0,.18);
    font:11.5px/1.4 ui-sans-serif,system-ui,-apple-system,sans-serif;overflow:hidden}
  .twk-hd{display:flex;align-items:center;justify-content:space-between;
    padding:10px 8px 10px 14px;cursor:move;user-select:none}
  .twk-hd b{font-size:12px;font-weight:600;letter-spacing:.01em}
  .twk-x{appearance:none;border:0;background:transparent;color:rgba(41,38,27,.55);
    width:22px;height:22px;border-radius:6px;cursor:default;font-size:13px;line-height:1}
  .twk-x:hover{background:rgba(0,0,0,.06);color:#29261b}
  .twk-body{padding:2px 14px 14px;display:flex;flex-direction:column;gap:10px;
    overflow-y:auto;overflow-x:hidden;min-height:0;
    scrollbar-width:thin;scrollbar-color:rgba(0,0,0,.15) transparent}
  .twk-body::-webkit-scrollbar{width:8px}
  .twk-body::-webkit-scrollbar-track{background:transparent;margin:2px}
  .twk-body::-webkit-scrollbar-thumb{background:rgba(0,0,0,.15);border-radius:4px;
    border:2px solid transparent;background-clip:content-box}
  .twk-body::-webkit-scrollbar-thumb:hover{background:rgba(0,0,0,.25);
    border:2px solid transparent;background-clip:content-box}
  .twk-row{display:flex;flex-direction:column;gap:5px}
  .twk-row-h{flex-direction:row;align-items:center;justify-content:space-between;gap:10px}
  .twk-lbl{display:flex;justify-content:space-between;align-items:baseline;
    color:rgba(41,38,27,.72)}
  .twk-lbl>span:first-child{font-weight:500}
  .twk-val{color:rgba(41,38,27,.5);font-variant-numeric:tabular-nums}

  .twk-sect{font-size:10px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;
    color:rgba(41,38,27,.45);padding:10px 0 0}
  .twk-sect:first-child{padding-top:0}

  .twk-field{appearance:none;box-sizing:border-box;width:100%;min-width:0;height:26px;padding:0 8px;
    border:.5px solid rgba(0,0,0,.1);border-radius:7px;
    background:rgba(255,255,255,.6);color:inherit;font:inherit;outline:none}
  .twk-field:focus{border-color:rgba(0,0,0,.25);background:rgba(255,255,255,.85)}
  select.twk-field{padding-right:22px;
    background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'><path fill='rgba(0,0,0,.5)' d='M0 0h10L5 6z'/></svg>");
    background-repeat:no-repeat;background-position:right 8px center}

  .twk-slider{appearance:none;-webkit-appearance:none;width:100%;height:4px;margin:6px 0;
    border-radius:999px;background:rgba(0,0,0,.12);outline:none}
  .twk-slider::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;
    width:14px;height:14px;border-radius:50%;background:#fff;
    border:.5px solid rgba(0,0,0,.12);box-shadow:0 1px 3px rgba(0,0,0,.2);cursor:default}
  .twk-slider::-moz-range-thumb{width:14px;height:14px;border-radius:50%;
    background:#fff;border:.5px solid rgba(0,0,0,.12);box-shadow:0 1px 3px rgba(0,0,0,.2);cursor:default}

  .twk-seg{position:relative;display:flex;padding:2px;border-radius:8px;
    background:rgba(0,0,0,.06);user-select:none}
  .twk-seg-thumb{position:absolute;top:2px;bottom:2px;border-radius:6px;
    background:rgba(255,255,255,.9);box-shadow:0 1px 2px rgba(0,0,0,.12);
    transition:left .15s cubic-bezier(.3,.7,.4,1),width .15s}
  .twk-seg.dragging .twk-seg-thumb{transition:none}
  .twk-seg button{appearance:none;position:relative;z-index:1;flex:1;border:0;
    background:transparent;color:inherit;font:inherit;font-weight:500;min-height:22px;
    border-radius:6px;cursor:default;padding:4px 6px;line-height:1.2;
    overflow-wrap:anywhere}

  .twk-toggle{position:relative;width:32px;height:18px;border:0;border-radius:999px;
    background:rgba(0,0,0,.15);transition:background .15s;cursor:default;padding:0}
  .twk-toggle[data-on="1"]{background:#34c759}
  .twk-toggle i{position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;
    background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform .15s}
  .twk-toggle[data-on="1"] i{transform:translateX(14px)}

  .twk-num{display:flex;align-items:center;box-sizing:border-box;min-width:0;height:26px;padding:0 0 0 8px;
    border:.5px solid rgba(0,0,0,.1);border-radius:7px;background:rgba(255,255,255,.6)}
  .twk-num-lbl{font-weight:500;color:rgba(41,38,27,.6);cursor:ew-resize;
    user-select:none;padding-right:8px}
  .twk-num input{flex:1;min-width:0;height:100%;border:0;background:transparent;
    font:inherit;font-variant-numeric:tabular-nums;text-align:right;padding:0 8px 0 0;
    outline:none;color:inherit;-moz-appearance:textfield}
  .twk-num input::-webkit-inner-spin-button,.twk-num input::-webkit-outer-spin-button{
    -webkit-appearance:none;margin:0}
  .twk-num-unit{padding-right:8px;color:rgba(41,38,27,.45)}

  .twk-btn{appearance:none;height:26px;padding:0 12px;border:0;border-radius:7px;
    background:rgba(0,0,0,.78);color:#fff;font:inherit;font-weight:500;cursor:default}
  .twk-btn:hover{background:rgba(0,0,0,.88)}
  .twk-btn.secondary{background:rgba(0,0,0,.06);color:inherit}
  .twk-btn.secondary:hover{background:rgba(0,0,0,.1)}

  .twk-swatch{appearance:none;-webkit-appearance:none;width:56px;height:22px;
    border:.5px solid rgba(0,0,0,.1);border-radius:6px;padding:0;cursor:default;
    background:transparent;flex-shrink:0}
  .twk-swatch::-webkit-color-swatch-wrapper{padding:0}
  .twk-swatch::-webkit-color-swatch{border:0;border-radius:5.5px}
  .twk-swatch::-moz-color-swatch{border:0;border-radius:5.5px}

  .twk-chips{display:flex;gap:6px}
  .twk-chip{position:relative;appearance:none;flex:1;min-width:0;height:46px;
    padding:0;border:0;border-radius:6px;overflow:hidden;cursor:default;
    box-shadow:0 0 0 .5px rgba(0,0,0,.12),0 1px 2px rgba(0,0,0,.06);
    transition:transform .12s cubic-bezier(.3,.7,.4,1),box-shadow .12s}
  .twk-chip:hover{transform:translateY(-1px);
    box-shadow:0 0 0 .5px rgba(0,0,0,.18),0 4px 10px rgba(0,0,0,.12)}
  .twk-chip[data-on="1"]{box-shadow:0 0 0 1.5px rgba(0,0,0,.85),
    0 2px 6px rgba(0,0,0,.15)}
  .twk-chip>span{position:absolute;top:0;bottom:0;right:0;width:34%;
    display:flex;flex-direction:column;box-shadow:-1px 0 0 rgba(0,0,0,.1)}
  .twk-chip>span>i{flex:1;box-shadow:0 -1px 0 rgba(0,0,0,.1)}
  .twk-chip>span>i:first-child{box-shadow:none}
  .twk-chip svg{position:absolute;top:6px;left:6px;width:13px;height:13px;
    filter:drop-shadow(0 1px 1px rgba(0,0,0,.3))}
`;

// ── useTweaks ───────────────────────────────────────────────────────────────
// Single source of truth for tweak values. setTweak persists via the host
// (__edit_mode_set_keys → host rewrites the EDITMODE block on disk).
function useTweaks(defaults) {
  const [values, setValues] = React.useState(defaults);
  // Accepts either setTweak('key', value) or setTweak({ key: value, ... }) so a
  // useState-style call doesn't write a "[object Object]" key into the persisted
  // JSON block.
  const setTweak = React.useCallback((keyOrEdits, val) => {
    const edits = typeof keyOrEdits === 'object' && keyOrEdits !== null ? keyOrEdits : {
      [keyOrEdits]: val
    };
    setValues(prev => ({
      ...prev,
      ...edits
    }));
    window.parent.postMessage({
      type: '__edit_mode_set_keys',
      edits
    }, '*');
    // Same-window signal so in-page listeners (deck-stage rail thumbnails)
    // can react — the parent message only reaches the host, not peers.
    window.dispatchEvent(new CustomEvent('tweakchange', {
      detail: edits
    }));
  }, []);
  return [values, setTweak];
}

// ── TweaksPanel ─────────────────────────────────────────────────────────────
// Floating shell. Registers the protocol listener BEFORE announcing
// availability — if the announce ran first, the host's activate could land
// before our handler exists and the toolbar toggle would silently no-op.
// The close button posts __edit_mode_dismissed so the host's toolbar toggle
// flips off in lockstep; the host echoes __deactivate_edit_mode back which
// is what actually hides the panel.
function TweaksPanel({
  title = 'Tweaks',
  children
}) {
  const [open, setOpen] = React.useState(false);
  const dragRef = React.useRef(null);
  const offsetRef = React.useRef({
    x: 16,
    y: 16
  });
  const PAD = 16;
  const clampToViewport = React.useCallback(() => {
    const panel = dragRef.current;
    if (!panel) return;
    const w = panel.offsetWidth,
      h = panel.offsetHeight;
    const maxRight = Math.max(PAD, window.innerWidth - w - PAD);
    const maxBottom = Math.max(PAD, window.innerHeight - h - PAD);
    offsetRef.current = {
      x: Math.min(maxRight, Math.max(PAD, offsetRef.current.x)),
      y: Math.min(maxBottom, Math.max(PAD, offsetRef.current.y))
    };
    panel.style.right = offsetRef.current.x + 'px';
    panel.style.bottom = offsetRef.current.y + 'px';
  }, []);
  React.useEffect(() => {
    if (!open) return;
    clampToViewport();
    if (typeof ResizeObserver === 'undefined') {
      window.addEventListener('resize', clampToViewport);
      return () => window.removeEventListener('resize', clampToViewport);
    }
    const ro = new ResizeObserver(clampToViewport);
    ro.observe(document.documentElement);
    return () => ro.disconnect();
  }, [open, clampToViewport]);
  React.useEffect(() => {
    const onMsg = e => {
      const t = e?.data?.type;
      if (t === '__activate_edit_mode') setOpen(true);else if (t === '__deactivate_edit_mode') setOpen(false);
    };
    window.addEventListener('message', onMsg);
    window.parent.postMessage({
      type: '__edit_mode_available'
    }, '*');
    return () => window.removeEventListener('message', onMsg);
  }, []);
  const dismiss = () => {
    setOpen(false);
    window.parent.postMessage({
      type: '__edit_mode_dismissed'
    }, '*');
  };
  const onDragStart = e => {
    const panel = dragRef.current;
    if (!panel) return;
    const r = panel.getBoundingClientRect();
    const sx = e.clientX,
      sy = e.clientY;
    const startRight = window.innerWidth - r.right;
    const startBottom = window.innerHeight - r.bottom;
    const move = ev => {
      offsetRef.current = {
        x: startRight - (ev.clientX - sx),
        y: startBottom - (ev.clientY - sy)
      };
      clampToViewport();
    };
    const up = () => {
      window.removeEventListener('mousemove', move);
      window.removeEventListener('mouseup', up);
    };
    window.addEventListener('mousemove', move);
    window.addEventListener('mouseup', up);
  };
  if (!open) return null;
  return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("style", null, __TWEAKS_STYLE), /*#__PURE__*/React.createElement("div", {
    ref: dragRef,
    className: "twk-panel",
    "data-omelette-chrome": "",
    style: {
      right: offsetRef.current.x,
      bottom: offsetRef.current.y
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "twk-hd",
    onMouseDown: onDragStart
  }, /*#__PURE__*/React.createElement("b", null, title), /*#__PURE__*/React.createElement("button", {
    className: "twk-x",
    "aria-label": "Close tweaks",
    onMouseDown: e => e.stopPropagation(),
    onClick: dismiss
  }, "\u2715")), /*#__PURE__*/React.createElement("div", {
    className: "twk-body"
  }, children)));
}

// ── Layout helpers ──────────────────────────────────────────────────────────

function TweakSection({
  label,
  children
}) {
  return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
    className: "twk-sect"
  }, label), children);
}
function TweakRow({
  label,
  value,
  children,
  inline = false
}) {
  return /*#__PURE__*/React.createElement("div", {
    className: inline ? 'twk-row twk-row-h' : 'twk-row'
  }, /*#__PURE__*/React.createElement("div", {
    className: "twk-lbl"
  }, /*#__PURE__*/React.createElement("span", null, label), value != null && /*#__PURE__*/React.createElement("span", {
    className: "twk-val"
  }, value)), children);
}

// ── Controls ────────────────────────────────────────────────────────────────

function TweakSlider({
  label,
  value,
  min = 0,
  max = 100,
  step = 1,
  unit = '',
  onChange
}) {
  return /*#__PURE__*/React.createElement(TweakRow, {
    label: label,
    value: `${value}${unit}`
  }, /*#__PURE__*/React.createElement("input", {
    type: "range",
    className: "twk-slider",
    min: min,
    max: max,
    step: step,
    value: value,
    onChange: e => onChange(Number(e.target.value))
  }));
}
function TweakToggle({
  label,
  value,
  onChange
}) {
  return /*#__PURE__*/React.createElement("div", {
    className: "twk-row twk-row-h"
  }, /*#__PURE__*/React.createElement("div", {
    className: "twk-lbl"
  }, /*#__PURE__*/React.createElement("span", null, label)), /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "twk-toggle",
    "data-on": value ? '1' : '0',
    role: "switch",
    "aria-checked": !!value,
    onClick: () => onChange(!value)
  }, /*#__PURE__*/React.createElement("i", null)));
}
function TweakRadio({
  label,
  value,
  options,
  onChange
}) {
  const trackRef = React.useRef(null);
  const [dragging, setDragging] = React.useState(false);
  // The active value is read by pointer-move handlers attached for the lifetime
  // of a drag — ref it so a stale closure doesn't fire onChange for every move.
  const valueRef = React.useRef(value);
  valueRef.current = value;

  // Segments wrap mid-word once per-segment width runs out. The track is
  // ~248px (280 panel − 28 body pad − 4 seg pad), each button loses 12px
  // to its own padding, and 11.5px system-ui averages ~6.3px/char — so 2
  // options fit ~16 chars each, 3 fit ~10. Past that (or >3 options), fall
  // back to a dropdown rather than wrap.
  const labelLen = o => String(typeof o === 'object' ? o.label : o).length;
  const maxLen = options.reduce((m, o) => Math.max(m, labelLen(o)), 0);
  const fitsAsSegments = maxLen <= ({
    2: 16,
    3: 10
  }[options.length] ?? 0);
  if (!fitsAsSegments) {
    // <select> emits strings — map back to the original option value so the
    // fallback stays type-preserving (numbers, booleans) like the segment path.
    const resolve = s => {
      const m = options.find(o => String(typeof o === 'object' ? o.value : o) === s);
      return m === undefined ? s : typeof m === 'object' ? m.value : m;
    };
    return /*#__PURE__*/React.createElement(TweakSelect, {
      label: label,
      value: value,
      options: options,
      onChange: s => onChange(resolve(s))
    });
  }
  const opts = options.map(o => typeof o === 'object' ? o : {
    value: o,
    label: o
  });
  const idx = Math.max(0, opts.findIndex(o => o.value === value));
  const n = opts.length;
  const segAt = clientX => {
    const r = trackRef.current.getBoundingClientRect();
    const inner = r.width - 4;
    const i = Math.floor((clientX - r.left - 2) / inner * n);
    return opts[Math.max(0, Math.min(n - 1, i))].value;
  };
  const onPointerDown = e => {
    setDragging(true);
    const v0 = segAt(e.clientX);
    if (v0 !== valueRef.current) onChange(v0);
    const move = ev => {
      if (!trackRef.current) return;
      const v = segAt(ev.clientX);
      if (v !== valueRef.current) onChange(v);
    };
    const up = () => {
      setDragging(false);
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
  };
  return /*#__PURE__*/React.createElement(TweakRow, {
    label: label
  }, /*#__PURE__*/React.createElement("div", {
    ref: trackRef,
    role: "radiogroup",
    onPointerDown: onPointerDown,
    className: dragging ? 'twk-seg dragging' : 'twk-seg'
  }, /*#__PURE__*/React.createElement("div", {
    className: "twk-seg-thumb",
    style: {
      left: `calc(2px + ${idx} * (100% - 4px) / ${n})`,
      width: `calc((100% - 4px) / ${n})`
    }
  }), opts.map(o => /*#__PURE__*/React.createElement("button", {
    key: o.value,
    type: "button",
    role: "radio",
    "aria-checked": o.value === value
  }, o.label))));
}
function TweakSelect({
  label,
  value,
  options,
  onChange
}) {
  return /*#__PURE__*/React.createElement(TweakRow, {
    label: label
  }, /*#__PURE__*/React.createElement("select", {
    className: "twk-field",
    value: value,
    onChange: e => onChange(e.target.value)
  }, options.map(o => {
    const v = typeof o === 'object' ? o.value : o;
    const l = typeof o === 'object' ? o.label : o;
    return /*#__PURE__*/React.createElement("option", {
      key: v,
      value: v
    }, l);
  })));
}
function TweakText({
  label,
  value,
  placeholder,
  onChange
}) {
  return /*#__PURE__*/React.createElement(TweakRow, {
    label: label
  }, /*#__PURE__*/React.createElement("input", {
    className: "twk-field",
    type: "text",
    value: value,
    placeholder: placeholder,
    onChange: e => onChange(e.target.value)
  }));
}
function TweakNumber({
  label,
  value,
  min,
  max,
  step = 1,
  unit = '',
  onChange
}) {
  const clamp = n => {
    if (min != null && n < min) return min;
    if (max != null && n > max) return max;
    return n;
  };
  const startRef = React.useRef({
    x: 0,
    val: 0
  });
  const onScrubStart = e => {
    e.preventDefault();
    startRef.current = {
      x: e.clientX,
      val: value
    };
    const decimals = (String(step).split('.')[1] || '').length;
    const move = ev => {
      const dx = ev.clientX - startRef.current.x;
      const raw = startRef.current.val + dx * step;
      const snapped = Math.round(raw / step) * step;
      onChange(clamp(Number(snapped.toFixed(decimals))));
    };
    const up = () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "twk-num"
  }, /*#__PURE__*/React.createElement("span", {
    className: "twk-num-lbl",
    onPointerDown: onScrubStart
  }, label), /*#__PURE__*/React.createElement("input", {
    type: "number",
    value: value,
    min: min,
    max: max,
    step: step,
    onChange: e => onChange(clamp(Number(e.target.value)))
  }), unit && /*#__PURE__*/React.createElement("span", {
    className: "twk-num-unit"
  }, unit));
}

// Relative-luminance contrast pick — checkmarks drawn over a swatch need to
// read on both #111 and #fafafa without per-option configuration. Hex input
// only (#rgb / #rrggbb); named or rgb()/hsl() colors fall through to "light".
function __twkIsLight(hex) {
  const h = String(hex).replace('#', '');
  const x = h.length === 3 ? h.replace(/./g, c => c + c) : h.padEnd(6, '0');
  const n = parseInt(x.slice(0, 6), 16);
  if (Number.isNaN(n)) return true;
  const r = n >> 16 & 255,
    g = n >> 8 & 255,
    b = n & 255;
  return r * 299 + g * 587 + b * 114 > 148000;
}
const __TwkCheck = ({
  light
}) => /*#__PURE__*/React.createElement("svg", {
  viewBox: "0 0 14 14",
  "aria-hidden": "true"
}, /*#__PURE__*/React.createElement("path", {
  d: "M3 7.2 5.8 10 11 4.2",
  fill: "none",
  strokeWidth: "2.2",
  strokeLinecap: "round",
  strokeLinejoin: "round",
  stroke: light ? 'rgba(0,0,0,.78)' : '#fff'
}));

// TweakColor — curated color/palette picker. Each option is either a single
// hex string or an array of 1-5 hex strings; the card adapts — a lone color
// renders solid, a palette renders colors[0] as the hero (left ~2/3) with the
// rest stacked in a sharp column on the right. onChange emits the
// option in the shape it was passed (string stays string, array stays array).
// Without options it falls back to the native color input for back-compat.
function TweakColor({
  label,
  value,
  options,
  onChange
}) {
  if (!options || !options.length) {
    return /*#__PURE__*/React.createElement("div", {
      className: "twk-row twk-row-h"
    }, /*#__PURE__*/React.createElement("div", {
      className: "twk-lbl"
    }, /*#__PURE__*/React.createElement("span", null, label)), /*#__PURE__*/React.createElement("input", {
      type: "color",
      className: "twk-swatch",
      value: value,
      onChange: e => onChange(e.target.value)
    }));
  }
  // Native <input type=color> emits lowercase hex per the HTML spec, so
  // compare case-insensitively. String() guards JSON.stringify(undefined),
  // which returns the primitive undefined (no .toLowerCase).
  const key = o => String(JSON.stringify(o)).toLowerCase();
  const cur = key(value);
  return /*#__PURE__*/React.createElement(TweakRow, {
    label: label
  }, /*#__PURE__*/React.createElement("div", {
    className: "twk-chips",
    role: "radiogroup"
  }, options.map((o, i) => {
    const colors = Array.isArray(o) ? o : [o];
    const [hero, ...rest] = colors;
    const sup = rest.slice(0, 4);
    const on = key(o) === cur;
    return /*#__PURE__*/React.createElement("button", {
      key: i,
      type: "button",
      className: "twk-chip",
      role: "radio",
      "aria-checked": on,
      "data-on": on ? '1' : '0',
      "aria-label": colors.join(', '),
      title: colors.join(' · '),
      style: {
        background: hero
      },
      onClick: () => onChange(o)
    }, sup.length > 0 && /*#__PURE__*/React.createElement("span", null, sup.map((c, j) => /*#__PURE__*/React.createElement("i", {
      key: j,
      style: {
        background: c
      }
    }))), on && /*#__PURE__*/React.createElement(__TwkCheck, {
      light: __twkIsLight(hero)
    }));
  })));
}
function TweakButton({
  label,
  onClick,
  secondary = false
}) {
  return /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: secondary ? 'twk-btn secondary' : 'twk-btn',
    onClick: onClick
  }, label);
}
Object.assign(window, {
  useTweaks,
  TweaksPanel,
  TweakSection,
  TweakRow,
  TweakSlider,
  TweakToggle,
  TweakRadio,
  TweakSelect,
  TweakText,
  TweakNumber,
  TweakColor,
  TweakButton
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "decks/expose-govern-attest/tweaks-panel.jsx", error: String((e && e.message) || e) }); }

// ui_kits/blog/data.js
try { (() => {
// Imladris blog — sample content (fictional, on-brand)
window.IMLADRIS_POSTS = [{
  id: 'council-convenes',
  topic: 'Oversight',
  title: 'The Council Convenes: a charter for model oversight',
  standfirst: 'What a thousand-year-old institution teaches us about governing systems we do not fully understand — and why patience is a capability.',
  author: 'Elrond Peredhel',
  role: 'Steward, the Inner Council',
  date: 'Jun 14, 2026',
  readTime: '11 min read',
  plate: 'twilight',
  featured: true,
  image: '../../assets/imagery/rivendell-third-age.webp',
  body: [{
    t: 'lead',
    c: 'A governing body is only as wise as the questions it dares to ask of itself. We begin, then, not with answers, but with the shape of our ignorance.'
  }, {
    t: 'p',
    c: 'When a system can act faster than any council can deliberate, the old instinct is to slow the system. We propose the opposite discipline: to widen the council, to make deliberation continuous, and to grant authority only where it can be revoked.'
  }, {
    t: 'h2',
    c: 'Three principles of the charter'
  }, {
    t: 'p',
    c: 'First, that every grant of authority be answerable. Second, that the reasons behind a decision be recorded as carefully as the decision itself. Third, that no capability outpace the means to evaluate it.'
  }, {
    t: 'callout',
    tone: 'note',
    title: 'From the charter',
    c: 'Every model granted authority must be answerable to a body that can revoke it.'
  }, {
    t: 'p',
    c: 'These are not constraints upon progress; they are the conditions under which progress can be trusted. The valley endured because its keepers preferred a slow certainty to a swift mistake.'
  }, {
    t: 'h2',
    c: 'On the cadence of evaluation'
  }, {
    t: 'p',
    c: 'We hold that evaluation must keep pace with capability, and where it cannot, that capability must wait. This is the least popular sentence we will write this year. It is also the most important.'
  }, {
    t: 'callout',
    tone: 'risk',
    title: 'Open risk',
    c: 'Capability gains are outpacing our evaluation cadence. We name it plainly so that we may answer for it.'
  }]
}, {
  id: 'interpretability-lantern',
  topic: 'Interpretability',
  title: 'A lantern in the deep: reading what a model believes',
  standfirst: 'Interpretability is not a dashboard. It is the slow craft of learning to read a mind that was not written for us.',
  author: 'Gilraen Anor',
  role: 'Reader of Signals',
  date: 'Jun 2, 2026',
  readTime: '8 min read',
  plate: 'green'
}, {
  id: 'measure-of-power',
  topic: 'Policy',
  title: 'The measure of power is the ease of its return',
  standfirst: 'Why every delegation of authority should be designed, from the first day, to be handed back.',
  author: 'Círdan',
  role: 'Keeper of the Grey Havens',
  date: 'May 21, 2026',
  readTime: '6 min read',
  plate: 'parchment',
  image: '../../assets/imagery/rivendell-second-age.webp'
}, {
  id: 'evaluations-as-ritual',
  topic: 'Evaluations',
  title: 'Evaluations as ritual, not gate',
  standfirst: 'A practice repeated until it becomes culture outlasts any checkpoint bolted on at the end.',
  author: 'Glorfindel',
  role: 'Warden of Trials',
  date: 'May 9, 2026',
  readTime: '9 min read',
  plate: 'gold'
}, {
  id: 'long-stewardship',
  topic: 'Stewardship',
  title: 'The long stewardship: governing across generations',
  standfirst: 'Institutions that plan in decades make different choices than those that plan in quarters.',
  author: 'Elrond Peredhel',
  role: 'Steward, the Inner Council',
  date: 'Apr 28, 2026',
  readTime: '12 min read',
  plate: 'green'
}, {
  id: 'consent-of-the-governed',
  topic: 'Policy',
  title: 'On the consent of the governed — and the governed who cannot speak',
  standfirst: 'When the affected parties are future people, who holds their proxy at the table?',
  author: 'Gilraen Anor',
  role: 'Reader of Signals',
  date: 'Apr 15, 2026',
  readTime: '7 min read',
  plate: 'twilight',
  image: '../../assets/imagery/rivendell-fourth-age.webp'
}];
window.IMLADRIS_TOPICS = ['All', 'Oversight', 'Alignment', 'Interpretability', 'Policy', 'Evaluations', 'Stewardship'];
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/data.js", error: String((e && e.message) || e) }); }

// ui_kits/blog/parts/App.compiled.js
try { (() => {
// App — compiled from App.jsx (no runtime Babel).
(function () {
  const e = React.createElement;
  function App() {
    const all = window.IMLADRIS_POSTS;
    const topics = window.IMLADRIS_TOPICS;
    const [view, setView] = React.useState('home');
    const [active, setActive] = React.useState('All');
    const [current, setCurrent] = React.useState(null);
    React.useEffect(() => {
      const id = setTimeout(() => window.lucide && window.lucide.createIcons(), 40);
      return () => clearTimeout(id);
    });
    const filtered = active === 'All' ? all : all.filter(p => p.topic === active);
    const open = post => {
      setCurrent(post);
      setView('reader');
      window.scrollTo({
        top: 0
      });
    };
    const home = () => {
      setView('home');
      window.scrollTo({
        top: 0
      });
    };
    return e('div', {
      style: {
        minHeight: '100vh',
        display: 'flex',
        flexDirection: 'column'
      }
    }, e(window.SiteHeader, {
      onHome: home,
      onSubscribe: () => {
        home();
      }
    }), e('div', {
      style: {
        flex: 1
      }
    }, view === 'home' ? e(window.HomeView, {
      posts: filtered,
      topics: topics,
      active: active,
      onTopic: setActive,
      onOpen: open
    }) : e(window.ReaderView, {
      post: current,
      onBack: home,
      onOpen: open,
      related: all.filter(p => p.id !== current.id)
    })), e(window.SiteFooter, null));
  }
  window.BlogApp = App;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/parts/App.compiled.js", error: String((e && e.message) || e) }); }

// ui_kits/blog/parts/App.jsx
try { (() => {
// App — routes between the home journal and the reader; manages topic filter.
function App() {
  const all = window.IMLADRIS_POSTS;
  const topics = window.IMLADRIS_TOPICS;
  const [view, setView] = React.useState('home');
  const [active, setActive] = React.useState('All');
  const [current, setCurrent] = React.useState(null);
  React.useEffect(() => {
    const id = setTimeout(() => window.lucide && window.lucide.createIcons(), 40);
    return () => clearTimeout(id);
  });
  const filtered = active === 'All' ? all : all.filter(p => p.topic === active);
  const open = post => {
    setCurrent(post);
    setView('reader');
    window.scrollTo({
      top: 0
    });
  };
  const home = () => {
    setView('home');
    window.scrollTo({
      top: 0
    });
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: '100vh',
      display: 'flex',
      flexDirection: 'column'
    }
  }, /*#__PURE__*/React.createElement(window.SiteHeader, {
    onHome: home,
    onSubscribe: () => {
      home();
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, view === 'home' ? /*#__PURE__*/React.createElement(window.HomeView, {
    posts: filtered,
    topics: topics,
    active: active,
    onTopic: setActive,
    onOpen: open
  }) : /*#__PURE__*/React.createElement(window.ReaderView, {
    post: current,
    onBack: home,
    onOpen: open,
    related: all.filter(p => p.id !== current.id)
  })), /*#__PURE__*/React.createElement(window.SiteFooter, null));
}
window.BlogApp = App;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/parts/App.jsx", error: String((e && e.message) || e) }); }

// ui_kits/blog/parts/CoverPlate.compiled.js
try { (() => {
// CoverPlate — compiled from CoverPlate.jsx (no runtime Babel).
(function () {
  const e = React.createElement;
  const PLATES = {
    twilight: {
      bg: 'radial-gradient(120% 140% at 80% -10%, #283440 0%, #1E2730 45%, #161D24 100%)',
      star: '#C29A44',
      star2: 'rgba(194,154,68,.16)',
      topic: '#EAD9A8'
    },
    green: {
      bg: 'radial-gradient(120% 140% at 80% -10%, #3A5C49 0%, #2E4A3A 45%, #1C2E24 100%)',
      star: '#D2B062',
      star2: 'rgba(220,232,221,.12)',
      topic: '#DCE8DD'
    },
    parchment: {
      bg: 'linear-gradient(160deg, #FAF6EC 0%, #ECE4D2 100%)',
      star: '#2E4A3A',
      star2: 'rgba(46,74,58,.10)',
      topic: '#9A7530',
      ink: true
    },
    gold: {
      bg: 'linear-gradient(160deg, #F4EBCF 0%, #EAD9A8 100%)',
      star: '#9A7530',
      star2: 'rgba(154,117,48,.18)',
      topic: '#9A7530',
      ink: true
    }
  };
  function EmblemMark({
    color,
    opacity = 1,
    style
  }) {
    return e('svg', {
      viewBox: '0 0 100 100',
      fill: 'none',
      style: style,
      'aria-hidden': 'true'
    }, e('g', {
      stroke: color,
      strokeWidth: '1.4',
      strokeLinejoin: 'round',
      strokeLinecap: 'round',
      opacity: opacity
    }, e('path', {
      d: 'M50 3 L63.8 16.7 L83.2 16.8 L83.3 36.2 L97 50 L83.3 63.8 L83.2 83.2 L63.8 83.3 L50 97 L36.2 83.3 L16.8 83.2 L16.7 63.8 L3 50 L16.7 36.2 L16.8 16.8 L36.2 16.7 Z'
    }), e('path', {
      d: 'M50 21 L57.5 42.5 L79 50 L57.5 57.5 L50 79 L42.5 57.5 L21 50 L42.5 42.5 Z',
      opacity: '0.5'
    }), e('circle', {
      cx: '50',
      cy: '50',
      r: '4.5',
      fill: color,
      stroke: 'none'
    })));
  }
  function CoverPlate({
    plate = 'twilight',
    topic,
    ratio = '16 / 9',
    image
  }) {
    const p = PLATES[plate] || PLATES.twilight;
    if (image) {
      return e('div', {
        style: {
          position: 'relative',
          aspectRatio: ratio,
          overflow: 'hidden',
          background: p.bg
        }
      }, e('img', {
        src: image,
        alt: '',
        style: {
          position: 'absolute',
          inset: 0,
          width: '100%',
          height: '100%',
          objectFit: 'cover'
        }
      }), e('div', {
        style: {
          position: 'absolute',
          inset: 0,
          background: 'linear-gradient(180deg, rgba(22,29,36,0) 38%, rgba(22,29,36,.72) 100%)'
        }
      }), e('div', {
        style: {
          position: 'absolute',
          inset: 14,
          border: '1px solid rgba(244,235,207,.22)',
          borderRadius: 4
        }
      }), topic && e('div', {
        style: {
          position: 'absolute',
          left: 22,
          bottom: 18,
          fontFamily: 'var(--font-label)',
          fontSize: 12,
          letterSpacing: '0.22em',
          textTransform: 'uppercase',
          color: 'var(--gold-200)'
        }
      }, topic));
    }
    return e('div', {
      style: {
        position: 'relative',
        aspectRatio: ratio,
        background: p.bg,
        overflow: 'hidden'
      }
    }, e(EmblemMark, {
      color: p.star,
      opacity: 0.85,
      style: {
        position: 'absolute',
        right: '-12%',
        top: '50%',
        transform: 'translateY(-50%)',
        width: '58%',
        height: 'auto',
        WebkitMaskImage: 'linear-gradient(105deg, transparent 4%, #000 50%)',
        maskImage: 'linear-gradient(105deg, transparent 4%, #000 50%)'
      }
    }), e('div', {
      style: {
        position: 'absolute',
        inset: 14,
        border: '1px solid ' + p.star2,
        borderRadius: 4
      }
    }), topic && e('div', {
      style: {
        position: 'absolute',
        left: 22,
        bottom: 18,
        fontFamily: 'var(--font-label)',
        fontSize: 12,
        letterSpacing: '0.22em',
        textTransform: 'uppercase',
        color: p.topic
      }
    }, topic));
  }
  window.CoverPlate = CoverPlate;
  window.EmblemMark = EmblemMark;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/parts/CoverPlate.compiled.js", error: String((e && e.message) || e) }); }

// ui_kits/blog/parts/CoverPlate.jsx
try { (() => {
// CoverPlate — an on-brand, typographic/geometric cover ground (no photography).
// Palette + emblem watermark vary by topic; used in place of cover images.
const PLATES = {
  twilight: {
    bg: 'radial-gradient(120% 140% at 80% -10%, #283440 0%, #1E2730 45%, #161D24 100%)',
    star: '#C29A44',
    star2: 'rgba(194,154,68,.16)',
    topic: '#EAD9A8'
  },
  green: {
    bg: 'radial-gradient(120% 140% at 80% -10%, #3A5C49 0%, #2E4A3A 45%, #1C2E24 100%)',
    star: '#D2B062',
    star2: 'rgba(220,232,221,.12)',
    topic: '#DCE8DD'
  },
  parchment: {
    bg: 'linear-gradient(160deg, #FAF6EC 0%, #ECE4D2 100%)',
    star: '#2E4A3A',
    star2: 'rgba(46,74,58,.10)',
    topic: '#9A7530',
    ink: true
  },
  gold: {
    bg: 'linear-gradient(160deg, #F4EBCF 0%, #EAD9A8 100%)',
    star: '#9A7530',
    star2: 'rgba(154,117,48,.18)',
    topic: '#9A7530',
    ink: true
  }
};
function EmblemMark({
  color,
  opacity = 1,
  style
}) {
  return /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 100 100",
    fill: "none",
    style: style,
    "aria-hidden": "true"
  }, /*#__PURE__*/React.createElement("g", {
    stroke: color,
    strokeWidth: "1.4",
    strokeLinejoin: "round",
    strokeLinecap: "round",
    opacity: opacity
  }, /*#__PURE__*/React.createElement("path", {
    d: "M50 3 L63.8 16.7 L83.2 16.8 L83.3 36.2 L97 50 L83.3 63.8 L83.2 83.2 L63.8 83.3 L50 97 L36.2 83.3 L16.8 83.2 L16.7 63.8 L3 50 L16.7 36.2 L16.8 16.8 L36.2 16.7 Z"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M50 21 L57.5 42.5 L79 50 L57.5 57.5 L50 79 L42.5 57.5 L21 50 L42.5 42.5 Z",
    opacity: "0.5"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "50",
    cy: "50",
    r: "4.5",
    fill: color,
    stroke: "none"
  })));
}
function CoverPlate({
  plate = 'twilight',
  topic,
  ratio = '16 / 9',
  image
}) {
  const p = PLATES[plate] || PLATES.twilight;
  // Photographic cover: real imagery with a tonal scrim so the topic label stays legible.
  if (image) {
    return /*#__PURE__*/React.createElement("div", {
      style: {
        position: 'relative',
        aspectRatio: ratio,
        overflow: 'hidden',
        background: p.bg
      }
    }, /*#__PURE__*/React.createElement("img", {
      src: image,
      alt: "",
      style: {
        position: 'absolute',
        inset: 0,
        width: '100%',
        height: '100%',
        objectFit: 'cover'
      }
    }), /*#__PURE__*/React.createElement("div", {
      style: {
        position: 'absolute',
        inset: 0,
        background: 'linear-gradient(180deg, rgba(22,29,36,0) 38%, rgba(22,29,36,.72) 100%)'
      }
    }), /*#__PURE__*/React.createElement("div", {
      style: {
        position: 'absolute',
        inset: 14,
        border: '1px solid rgba(244,235,207,.22)',
        borderRadius: 4
      }
    }), topic && /*#__PURE__*/React.createElement("div", {
      style: {
        position: 'absolute',
        left: 22,
        bottom: 18,
        fontFamily: 'var(--font-label)',
        fontSize: 12,
        letterSpacing: '0.22em',
        textTransform: 'uppercase',
        color: 'var(--gold-200)'
      }
    }, topic));
  }
  return /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      aspectRatio: ratio,
      background: p.bg,
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement(EmblemMark, {
    color: p.star,
    opacity: 0.85,
    style: {
      position: 'absolute',
      right: '-12%',
      top: '50%',
      transform: 'translateY(-50%)',
      width: '58%',
      height: 'auto',
      WebkitMaskImage: 'linear-gradient(105deg, transparent 4%, #000 50%)',
      maskImage: 'linear-gradient(105deg, transparent 4%, #000 50%)'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      inset: 14,
      border: `1px solid ${p.star2}`,
      borderRadius: 4
    }
  }), topic && /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      left: 22,
      bottom: 18,
      fontFamily: 'var(--font-label)',
      fontSize: 12,
      letterSpacing: '0.22em',
      textTransform: 'uppercase',
      color: p.topic
    }
  }, topic));
}
window.CoverPlate = CoverPlate;
window.EmblemMark = EmblemMark;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/parts/CoverPlate.jsx", error: String((e && e.message) || e) }); }

// ui_kits/blog/parts/HomeView.compiled.js
try { (() => {
// HomeView — compiled from HomeView.jsx (no runtime Babel).
(function () {
  const e = React.createElement;
  const {
    Tag,
    Subscribe,
    RingCard
  } = window.ImladrisDesignSystem_89e0d2;
  function ThreeRings() {
    return e('section', {
      style: {
        padding: 'var(--space-10) 0 var(--space-8)'
      }
    }, e('div', {
      style: {
        textAlign: 'center',
        maxWidth: '56ch',
        margin: '0 auto var(--space-7)'
      }
    }, e('div', {
      className: 'eyebrow',
      style: {
        marginBottom: 12
      }
    }, 'The framework of the council'), e('h2', {
      style: {
        margin: 0,
        fontFamily: 'var(--font-display)',
        fontWeight: 500,
        fontSize: 'var(--text-3xl)',
        lineHeight: 1.1,
        color: 'var(--text-strong)'
      }
    }, 'The Three Rings of governance'), e('p', {
      style: {
        margin: '14px auto 0',
        font: 'var(--type-lead)',
        color: 'var(--text-muted)',
        maxWidth: '46ch'
      }
    }, 'Every essay returns to one of three duties — to expose, to govern, to attest.')), e('div', {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(3, 1fr)',
        gap: 'var(--space-6)'
      }
    }, e(RingCard, {
      element: 'air',
      action: 'Expose',
      virtues: ['Clarity', 'Vision', 'Insight'],
      description: 'Expose capability openly so the system can be seen.'
    }), e(RingCard, {
      element: 'fire',
      action: 'Govern',
      virtues: ['Warmth', 'Courage', 'Renewal'],
      description: 'Govern usage and access so the system can be trusted.'
    }), e(RingCard, {
      element: 'water',
      action: 'Attest',
      virtues: ['Depth', 'Wisdom', 'Protection'],
      description: 'Attest actions and provenance so the system can be verified.'
    })));
  }
  function Masthead() {
    return e('div', {
      style: {
        textAlign: 'center',
        padding: 'var(--space-10) var(--space-6) var(--space-7)',
        maxWidth: '64ch',
        margin: '0 auto'
      }
    }, e('div', {
      className: 'eyebrow',
      style: {
        marginBottom: 16
      }
    }, 'A journal of AI governance'), e('h1', {
      style: {
        margin: 0,
        fontFamily: 'var(--font-display)',
        fontWeight: 500,
        fontSize: 'var(--text-5xl)',
        lineHeight: 1.02,
        letterSpacing: '-0.015em',
        color: 'var(--text-strong)'
      }
    }, 'On the stewardship', e('br'), 'of artificial minds'), e('p', {
      style: {
        margin: '20px auto 0',
        font: 'var(--type-lead)',
        color: 'var(--text-muted)',
        maxWidth: '52ch'
      }
    }, 'Essays on oversight, alignment, and the long work of governing systems we do not yet fully understand.'));
  }
  function HomeView({
    posts,
    topics,
    active,
    onTopic,
    onOpen,
    onSubscribe
  }) {
    const featured = posts.find(p => p.featured) || posts[0];
    const rest = posts.filter(p => p.id !== featured.id);
    return e('main', null, e(Masthead, null), e('div', {
      style: {
        maxWidth: 'var(--container-full)',
        margin: '0 auto',
        padding: '0 var(--space-6)'
      }
    }, e('div', {
      style: {
        display: 'flex',
        gap: 10,
        flexWrap: 'wrap',
        justifyContent: 'center',
        paddingBottom: 'var(--space-7)',
        borderBottom: '1px solid var(--border-hair)'
      }
    }, topics.map(t => e(Tag, {
      key: t,
      as: 'button',
      active: active === t,
      onClick: () => onTopic(t)
    }, t))), e('div', {
      style: {
        padding: 'var(--space-8) 0'
      }
    }, e(window.PostCard, {
      post: featured,
      onOpen: onOpen,
      featured: true
    })), e('div', {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(3, 1fr)',
        gap: 'var(--space-6)'
      }
    }, rest.map(p => e(window.PostCard, {
      key: p.id,
      post: p,
      onOpen: onOpen
    }))), e(ThreeRings, null), e('div', {
      style: {
        padding: 'var(--space-10) 0 var(--space-6)'
      }
    }, e(Subscribe, {
      onSubmit: () => onSubscribe && onSubscribe()
    }))));
  }
  window.HomeView = HomeView;
  window.Masthead = Masthead;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/parts/HomeView.compiled.js", error: String((e && e.message) || e) }); }

// ui_kits/blog/parts/HomeView.jsx
try { (() => {
// HomeView — masthead, topic filter, featured lead, and the essay grid.
const {
  Tag,
  Subscribe,
  RingCard
} = window.ImladrisDesignSystem_89e0d2;
function ThreeRings() {
  return /*#__PURE__*/React.createElement("section", {
    style: {
      padding: 'var(--space-10) 0 var(--space-8)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'center',
      maxWidth: '56ch',
      margin: '0 auto var(--space-7)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "eyebrow",
    style: {
      marginBottom: 12
    }
  }, "The framework of the council"), /*#__PURE__*/React.createElement("h2", {
    style: {
      margin: 0,
      fontFamily: 'var(--font-display)',
      fontWeight: 500,
      fontSize: 'var(--text-3xl)',
      lineHeight: 1.1,
      color: 'var(--text-strong)'
    }
  }, "The Three Rings of governance"), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '14px auto 0',
      font: 'var(--type-lead)',
      color: 'var(--text-muted)',
      maxWidth: '46ch'
    }
  }, "Every essay returns to one of three duties \u2014 to expose, to govern, to attest.")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(3, 1fr)',
      gap: 'var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement(RingCard, {
    element: "air",
    action: "Expose",
    virtues: ['Clarity', 'Vision', 'Insight'],
    description: "Expose capability so an agent can discover it \u2014 inspectable, not asserted.",
    cta: "Inspect the schema",
    href: "#expose"
  }), /*#__PURE__*/React.createElement(RingCard, {
    element: "fire",
    action: "Govern",
    virtues: ['Warmth', 'Courage', 'Renewal'],
    description: "Govern usage and access so the owner can audit it \u2014 read, not trusted.",
    cta: "Audit the log",
    href: "#govern"
  }), /*#__PURE__*/React.createElement(RingCard, {
    element: "water",
    action: "Attest",
    virtues: ['Depth', 'Wisdom', 'Protection'],
    description: "Attest the output's provenance so a stranger can verify it \u2014 signed, not claimed.",
    cta: "Verify the signature",
    ctaState: "in-review"
  })));
}
function Masthead() {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'center',
      padding: 'var(--space-10) var(--space-6) var(--space-7)',
      maxWidth: '64ch',
      margin: '0 auto'
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "eyebrow",
    style: {
      marginBottom: 16
    }
  }, "A journal of AI governance"), /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: 0,
      fontFamily: 'var(--font-display)',
      fontWeight: 500,
      fontSize: 'var(--text-5xl)',
      lineHeight: 1.02,
      letterSpacing: '-0.015em',
      color: 'var(--text-strong)'
    }
  }, "On the stewardship", /*#__PURE__*/React.createElement("br", null), "of artificial minds"), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '20px auto 0',
      font: 'var(--type-lead)',
      color: 'var(--text-muted)',
      maxWidth: '52ch'
    }
  }, "Essays on oversight, alignment, and the long work of governing systems we do not yet fully understand."));
}
function HomeView({
  posts,
  topics,
  active,
  onTopic,
  onOpen,
  onSubscribe
}) {
  const featured = posts.find(p => p.featured) || posts[0];
  const rest = posts.filter(p => p.id !== featured.id);
  return /*#__PURE__*/React.createElement("main", null, /*#__PURE__*/React.createElement(Masthead, null), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--container-full)',
      margin: '0 auto',
      padding: '0 var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 10,
      flexWrap: 'wrap',
      justifyContent: 'center',
      paddingBottom: 'var(--space-7)',
      borderBottom: '1px solid var(--border-hair)'
    }
  }, topics.map(t => /*#__PURE__*/React.createElement(Tag, {
    key: t,
    as: "button",
    active: active === t,
    onClick: () => onTopic(t)
  }, t))), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 'var(--space-8) 0'
    }
  }, /*#__PURE__*/React.createElement(window.PostCard, {
    post: featured,
    onOpen: onOpen,
    featured: true
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(3, 1fr)',
      gap: 'var(--space-6)'
    }
  }, rest.map(p => /*#__PURE__*/React.createElement(window.PostCard, {
    key: p.id,
    post: p,
    onOpen: onOpen
  }))), /*#__PURE__*/React.createElement(ThreeRings, null), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 'var(--space-10) 0 var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement(Subscribe, {
    onSubmit: () => onSubscribe && onSubscribe()
  }))));
}
window.HomeView = HomeView;
window.Masthead = Masthead;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/parts/HomeView.jsx", error: String((e && e.message) || e) }); }

// ui_kits/blog/parts/PostCard.compiled.js
try { (() => {
// PostCard — compiled from PostCard.jsx (no runtime Babel).
(function () {
  const e = React.createElement;
  const {
    Badge,
    Avatar
  } = window.ImladrisDesignSystem_89e0d2;
  function PostCard({
    post,
    onOpen,
    featured
  }) {
    return e('a', {
      href: '#',
      onClick: ev => {
        ev.preventDefault();
        onOpen(post);
      },
      style: {
        display: 'flex',
        flexDirection: featured ? 'row' : 'column',
        background: 'var(--surface-card)',
        border: '1px solid var(--border-hair)',
        borderRadius: 'var(--radius-lg)',
        overflow: 'hidden',
        textDecoration: 'none',
        color: 'inherit',
        boxShadow: 'var(--shadow-sm)',
        transition: 'box-shadow var(--dur-base) var(--ease-calm), transform var(--dur-base) var(--ease-calm), border-color var(--dur-base) var(--ease-calm)'
      },
      onMouseEnter: ev => {
        ev.currentTarget.style.boxShadow = 'var(--shadow-lg)';
        ev.currentTarget.style.transform = 'translateY(-3px)';
        ev.currentTarget.style.borderColor = 'var(--green-200)';
      },
      onMouseLeave: ev => {
        ev.currentTarget.style.boxShadow = 'var(--shadow-sm)';
        ev.currentTarget.style.transform = 'none';
        ev.currentTarget.style.borderColor = 'var(--border-hair)';
      }
    }, e('div', {
      style: {
        flex: featured ? '1 1 52%' : 'none'
      }
    }, e(window.CoverPlate, {
      plate: post.plate,
      topic: post.topic,
      image: post.image,
      ratio: featured ? '16 / 11' : '16 / 9'
    })), e('div', {
      style: {
        padding: featured ? 'var(--space-7)' : 'var(--space-5)',
        display: 'flex',
        flexDirection: 'column',
        gap: 'var(--space-3)',
        flex: 1
      }
    }, e('div', null, e(Badge, {
      tone: 'brand'
    }, post.topic), featured && e(Badge, {
      tone: 'accent',
      style: {
        marginLeft: 8
      }
    }, 'Lead essay')), e('h3', {
      style: {
        margin: 0,
        fontFamily: 'var(--font-display)',
        fontWeight: 500,
        color: 'var(--text-strong)',
        letterSpacing: '-0.01em',
        lineHeight: 1.1,
        fontSize: featured ? 'var(--text-3xl)' : 'var(--text-xl)'
      }
    }, post.title), e('p', {
      style: {
        margin: 0,
        font: 'var(--type-body)',
        color: 'var(--text-muted)',
        display: '-webkit-box',
        WebkitLineClamp: featured ? 4 : 2,
        WebkitBoxOrient: 'vertical',
        overflow: 'hidden'
      }
    }, post.standfirst), e('div', {
      style: {
        marginTop: 'auto',
        paddingTop: 'var(--space-3)',
        display: 'flex',
        alignItems: 'center',
        gap: 12
      }
    }, e(Avatar, {
      name: post.author,
      size: featured ? 38 : 30,
      ring: featured
    }), e('div', {
      style: {
        display: 'flex',
        flexDirection: 'column',
        lineHeight: 1.3
      }
    }, e('span', {
      style: {
        fontFamily: 'var(--font-label)',
        fontSize: 13,
        letterSpacing: '0.04em',
        color: 'var(--text-body)'
      }
    }, post.author), e('span', {
      style: {
        fontFamily: 'var(--font-mono)',
        fontSize: 11,
        color: 'var(--text-faint)'
      }
    }, post.date, ' · ', post.readTime)))));
  }
  window.PostCard = PostCard;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/parts/PostCard.compiled.js", error: String((e && e.message) || e) }); }

// ui_kits/blog/parts/PostCard.jsx
try { (() => {
// PostCard — blog unit built from CoverPlate + DS Badge/Avatar. Two scales.
const {
  Badge,
  Avatar
} = window.ImladrisDesignSystem_89e0d2;
function PostCard({
  post,
  onOpen,
  featured
}) {
  return /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      onOpen(post);
    },
    style: {
      display: 'flex',
      flexDirection: featured ? 'row' : 'column',
      background: 'var(--surface-card)',
      border: '1px solid var(--border-hair)',
      borderRadius: 'var(--radius-lg)',
      overflow: 'hidden',
      textDecoration: 'none',
      color: 'inherit',
      boxShadow: 'var(--shadow-sm)',
      transition: 'box-shadow var(--dur-base) var(--ease-calm), transform var(--dur-base) var(--ease-calm), border-color var(--dur-base) var(--ease-calm)'
    },
    onMouseEnter: e => {
      e.currentTarget.style.boxShadow = 'var(--shadow-lg)';
      e.currentTarget.style.transform = 'translateY(-3px)';
      e.currentTarget.style.borderColor = 'var(--green-200)';
    },
    onMouseLeave: e => {
      e.currentTarget.style.boxShadow = 'var(--shadow-sm)';
      e.currentTarget.style.transform = 'none';
      e.currentTarget.style.borderColor = 'var(--border-hair)';
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: featured ? '1 1 52%' : 'none'
    }
  }, /*#__PURE__*/React.createElement(window.CoverPlate, {
    plate: post.plate,
    topic: post.topic,
    image: post.image,
    ratio: featured ? '16 / 11' : '16 / 9'
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: featured ? 'var(--space-7)' : 'var(--space-5)',
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-3)',
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(Badge, {
    tone: "brand"
  }, post.topic), featured && /*#__PURE__*/React.createElement(Badge, {
    tone: "accent",
    style: {
      marginLeft: 8
    }
  }, "Lead essay")), /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: 0,
      fontFamily: 'var(--font-display)',
      fontWeight: 500,
      color: 'var(--text-strong)',
      letterSpacing: '-0.01em',
      lineHeight: 1.1,
      fontSize: featured ? 'var(--text-3xl)' : 'var(--text-xl)'
    }
  }, post.title), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0,
      font: 'var(--type-body)',
      color: 'var(--text-muted)',
      display: '-webkit-box',
      WebkitLineClamp: featured ? 4 : 2,
      WebkitBoxOrient: 'vertical',
      overflow: 'hidden'
    }
  }, post.standfirst), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 'auto',
      paddingTop: 'var(--space-3)',
      display: 'flex',
      alignItems: 'center',
      gap: 12
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    name: post.author,
    size: featured ? 38 : 30,
    ring: featured
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      lineHeight: 1.3
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 13,
      letterSpacing: '0.04em',
      color: 'var(--text-body)'
    }
  }, post.author), /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-mono)',
      fontSize: 11,
      color: 'var(--text-faint)'
    }
  }, post.date, " \xB7 ", post.readTime)))));
}
window.PostCard = PostCard;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/parts/PostCard.jsx", error: String((e && e.message) || e) }); }

// ui_kits/blog/parts/ReaderView.compiled.js
try { (() => {
// ReaderView — compiled from ReaderView.jsx (no runtime Babel).
(function () {
  const e = React.createElement;
  const {
    Avatar,
    Badge,
    Callout,
    Tag,
    Button,
    Subscribe
  } = window.ImladrisDesignSystem_89e0d2;
  function ReaderHero({
    post
  }) {
    return e('div', {
      style: {
        position: 'relative',
        background: 'var(--surface-inverse)',
        color: 'var(--parchment-50)',
        overflow: 'hidden'
      }
    }, e('img', {
      src: post.image || '../../assets/imagery/rivendell-fourth-age.webp',
      alt: '',
      style: {
        position: 'absolute',
        inset: 0,
        width: '100%',
        height: '100%',
        objectFit: 'cover',
        opacity: 0.34
      }
    }), e('div', {
      style: {
        position: 'absolute',
        inset: 0,
        background: 'linear-gradient(180deg, rgba(22,29,36,.62) 0%, rgba(22,29,36,.82) 100%)'
      }
    }), e('div', {
      style: {
        position: 'relative',
        maxWidth: 'var(--container-text)',
        margin: '0 auto',
        padding: 'var(--space-9) var(--space-6) var(--space-8)',
        textAlign: 'center'
      }
    }, e(Badge, {
      tone: 'accent'
    }, post.topic), e('h1', {
      style: {
        margin: '18px 0 0',
        fontFamily: 'var(--font-display)',
        fontWeight: 500,
        fontSize: 'var(--text-4xl)',
        lineHeight: 1.06,
        letterSpacing: '-0.015em',
        color: 'var(--parchment-50)'
      }
    }, post.title), e('p', {
      style: {
        margin: '20px auto 0',
        font: 'var(--type-lead)',
        color: 'var(--green-200)',
        maxWidth: '46ch'
      }
    }, post.standfirst), e('div', {
      style: {
        marginTop: 26,
        display: 'inline-flex',
        alignItems: 'center',
        gap: 12
      }
    }, e(Avatar, {
      name: post.author,
      size: 40,
      ring: true
    }), e('div', {
      style: {
        textAlign: 'left',
        lineHeight: 1.35
      }
    }, e('div', {
      style: {
        fontFamily: 'var(--font-label)',
        fontSize: 14,
        letterSpacing: '0.04em',
        color: 'var(--parchment-50)'
      }
    }, post.author), e('div', {
      style: {
        fontFamily: 'var(--font-mono)',
        fontSize: 11,
        color: 'var(--ink-300)'
      }
    }, post.role, ' · ', post.date, ' · ', post.readTime)))));
  }
  function Block({
    b
  }) {
    if (b.t === 'lead') return e('p', {
      className: 'lead'
    }, b.c);
    if (b.t === 'h2') return e('h2', null, b.c);
    if (b.t === 'callout') return e('div', {
      style: {
        margin: 'var(--space-6) 0'
      }
    }, e(Callout, {
      tone: b.tone,
      title: b.title,
      icon: e('i', {
        'data-lucide': b.tone === 'risk' ? 'triangle-alert' : 'scroll',
        style: {
          width: 18,
          height: 18
        }
      })
    }, b.c));
    return e('p', null, b.c);
  }
  function ReaderView({
    post,
    onBack,
    related,
    onOpen
  }) {
    const body = post.body || [{
      t: 'lead',
      c: post.standfirst
    }, {
      t: 'p',
      c: 'This essay is part of the Imladris archive. The full text continues in the published edition; what follows is a representative excerpt of the journal\u2019s long-form rhythm.'
    }, {
      t: 'h2',
      c: 'On measured judgement'
    }, {
      t: 'p',
      c: 'We prefer a slow certainty to a swift mistake. The reading column is set for patience — a comfortable measure, generous leading, and emphasis that never raises its voice.'
    }, {
      t: 'callout',
      tone: 'insight',
      title: 'In short',
      c: 'Govern for the long term, and design every grant of authority to be returned.'
    }];
    return e('main', null, e(ReaderHero, {
      post: post
    }), e('article', {
      style: {
        maxWidth: 'var(--container-text)',
        margin: '0 auto',
        padding: 'var(--space-8) var(--space-6) 0'
      }
    }, e('button', {
      onClick: onBack,
      style: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: 8,
        background: 'none',
        border: 'none',
        cursor: 'pointer',
        fontFamily: 'var(--font-label)',
        fontSize: 12,
        letterSpacing: '0.1em',
        textTransform: 'uppercase',
        color: 'var(--text-muted)',
        marginBottom: 'var(--space-6)'
      }
    }, e('i', {
      'data-lucide': 'arrow-left',
      style: {
        width: 15,
        height: 15
      }
    }), ' All essays'), e('div', {
      className: 'prose',
      style: {
        margin: '0 auto'
      }
    }, body.map((b, i) => e(Block, {
      key: i,
      b: b
    }))), e('div', {
      style: {
        display: 'flex',
        gap: 8,
        flexWrap: 'wrap',
        margin: 'var(--space-8) auto 0',
        maxWidth: 'var(--measure-prose)'
      }
    }, e(Tag, {
      as: 'a',
      href: '#'
    }, post.topic), e(Tag, {
      as: 'a',
      href: '#'
    }, 'Charter'), e(Tag, {
      as: 'a',
      href: '#'
    }, 'Council')), e('div', {
      style: {
        maxWidth: 'var(--measure-prose)',
        margin: 'var(--space-8) auto 0'
      }
    }, e(Subscribe, null))), related && related.length > 0 && e('section', {
      style: {
        maxWidth: 'var(--container-full)',
        margin: '0 auto',
        padding: 'var(--space-10) var(--space-6) 0'
      }
    }, e('h3', {
      style: {
        fontFamily: 'var(--font-display)',
        fontWeight: 500,
        fontSize: 'var(--text-2xl)',
        marginBottom: 'var(--space-5)'
      }
    }, 'Continue reading'), e('div', {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(3, 1fr)',
        gap: 'var(--space-6)'
      }
    }, related.slice(0, 3).map(p => e(window.PostCard, {
      key: p.id,
      post: p,
      onOpen: onOpen
    })))));
  }
  window.ReaderView = ReaderView;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/parts/ReaderView.compiled.js", error: String((e && e.message) || e) }); }

// ui_kits/blog/parts/ReaderView.jsx
try { (() => {
// ReaderView — the long-form article page.
const {
  Avatar,
  Badge,
  Callout,
  Tag,
  Button,
  Subscribe
} = window.ImladrisDesignSystem_89e0d2;
function ReaderHero({
  post
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      background: 'var(--surface-inverse)',
      color: 'var(--parchment-50)',
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: post.image || "../../assets/imagery/rivendell-fourth-age.webp",
    alt: "",
    style: {
      position: 'absolute',
      inset: 0,
      width: '100%',
      height: '100%',
      objectFit: 'cover',
      opacity: 0.34
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      inset: 0,
      background: 'linear-gradient(180deg, rgba(22,29,36,.62) 0%, rgba(22,29,36,.82) 100%)'
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      maxWidth: 'var(--container-text)',
      margin: '0 auto',
      padding: 'var(--space-9) var(--space-6) var(--space-8)',
      textAlign: 'center'
    }
  }, /*#__PURE__*/React.createElement(Badge, {
    tone: "accent"
  }, post.topic), /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: '18px 0 0',
      fontFamily: 'var(--font-display)',
      fontWeight: 500,
      fontSize: 'var(--text-4xl)',
      lineHeight: 1.06,
      letterSpacing: '-0.015em',
      color: 'var(--parchment-50)'
    }
  }, post.title), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '20px auto 0',
      font: 'var(--type-lead)',
      color: 'var(--green-200)',
      maxWidth: '46ch'
    }
  }, post.standfirst), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 26,
      display: 'inline-flex',
      alignItems: 'center',
      gap: 12
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    name: post.author,
    size: 40,
    ring: true
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'left',
      lineHeight: 1.35
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 14,
      letterSpacing: '0.04em',
      color: 'var(--parchment-50)'
    }
  }, post.author), /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-mono)',
      fontSize: 11,
      color: 'var(--ink-300)'
    }
  }, post.role, " \xB7 ", post.date, " \xB7 ", post.readTime)))));
}
function Block({
  b
}) {
  if (b.t === 'lead') return /*#__PURE__*/React.createElement("p", {
    className: "lead"
  }, b.c);
  if (b.t === 'h2') return /*#__PURE__*/React.createElement("h2", null, b.c);
  if (b.t === 'callout') return /*#__PURE__*/React.createElement("div", {
    style: {
      margin: 'var(--space-6) 0'
    }
  }, /*#__PURE__*/React.createElement(Callout, {
    tone: b.tone,
    title: b.title,
    icon: /*#__PURE__*/React.createElement("i", {
      "data-lucide": b.tone === 'risk' ? 'triangle-alert' : 'scroll',
      style: {
        width: 18,
        height: 18
      }
    })
  }, b.c));
  return /*#__PURE__*/React.createElement("p", null, b.c);
}
function ReaderView({
  post,
  onBack,
  related,
  onOpen
}) {
  const body = post.body || [{
    t: 'lead',
    c: post.standfirst
  }, {
    t: 'p',
    c: 'This essay is part of the Imladris archive. The full text continues in the published edition; what follows is a representative excerpt of the journal’s long-form rhythm.'
  }, {
    t: 'h2',
    c: 'On measured judgement'
  }, {
    t: 'p',
    c: 'We prefer a slow certainty to a swift mistake. The reading column is set for patience — a comfortable measure, generous leading, and emphasis that never raises its voice.'
  }, {
    t: 'callout',
    tone: 'insight',
    title: 'In short',
    c: 'Govern for the long term, and design every grant of authority to be returned.'
  }];
  return /*#__PURE__*/React.createElement("main", null, /*#__PURE__*/React.createElement(ReaderHero, {
    post: post
  }), /*#__PURE__*/React.createElement("article", {
    style: {
      maxWidth: 'var(--container-text)',
      margin: '0 auto',
      padding: 'var(--space-8) var(--space-6) 0'
    }
  }, /*#__PURE__*/React.createElement("button", {
    onClick: onBack,
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 8,
      background: 'none',
      border: 'none',
      cursor: 'pointer',
      fontFamily: 'var(--font-label)',
      fontSize: 12,
      letterSpacing: '0.1em',
      textTransform: 'uppercase',
      color: 'var(--text-muted)',
      marginBottom: 'var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement("i", {
    "data-lucide": "arrow-left",
    style: {
      width: 15,
      height: 15
    }
  }), " All essays"), /*#__PURE__*/React.createElement("div", {
    className: "prose",
    style: {
      margin: '0 auto'
    }
  }, body.map((b, i) => /*#__PURE__*/React.createElement(Block, {
    key: i,
    b: b
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8,
      flexWrap: 'wrap',
      margin: 'var(--space-8) auto 0',
      maxWidth: 'var(--measure-prose)'
    }
  }, /*#__PURE__*/React.createElement(Tag, {
    as: "a",
    href: "#"
  }, post.topic), /*#__PURE__*/React.createElement(Tag, {
    as: "a",
    href: "#"
  }, "Charter"), /*#__PURE__*/React.createElement(Tag, {
    as: "a",
    href: "#"
  }, "Council")), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--measure-prose)',
      margin: 'var(--space-8) auto 0'
    }
  }, /*#__PURE__*/React.createElement(Subscribe, null))), related && related.length > 0 && /*#__PURE__*/React.createElement("section", {
    style: {
      maxWidth: 'var(--container-full)',
      margin: '0 auto',
      padding: 'var(--space-10) var(--space-6) 0'
    }
  }, /*#__PURE__*/React.createElement("h3", {
    style: {
      fontFamily: 'var(--font-display)',
      fontWeight: 500,
      fontSize: 'var(--text-2xl)',
      marginBottom: 'var(--space-5)'
    }
  }, "Continue reading"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(3, 1fr)',
      gap: 'var(--space-6)'
    }
  }, related.slice(0, 3).map(p => /*#__PURE__*/React.createElement(window.PostCard, {
    key: p.id,
    post: p,
    onOpen: onOpen
  })))));
}
window.ReaderView = ReaderView;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/parts/ReaderView.jsx", error: String((e && e.message) || e) }); }

// ui_kits/blog/parts/SiteChrome.compiled.js
try { (() => {
// SiteChrome — compiled from SiteChrome.jsx (no runtime Babel).
(function () {
  const e = React.createElement;
  const {
    Button,
    IconButton
  } = window.ImladrisDesignSystem_89e0d2;
  function Logo({
    onClick,
    inverse
  }) {
    return e('a', {
      href: '#',
      onClick: ev => {
        ev.preventDefault();
        onClick && onClick();
      },
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        textDecoration: 'none'
      }
    }, e(window.EmblemMark, {
      color: inverse ? 'var(--gold-400)' : 'var(--green-700)',
      style: {
        width: 30,
        height: 30
      }
    }), e('span', {
      style: {
        fontFamily: 'var(--font-label)',
        letterSpacing: '0.32em',
        fontSize: 20,
        color: inverse ? 'var(--parchment-50)' : 'var(--ink-900)'
      }
    }, 'IMLADRIS'));
  }
  function SiteHeader({
    onHome,
    onSubscribe
  }) {
    const nav = ['Essays', 'The Charter', 'Council', 'Archive'];
    return e('header', {
      style: {
        position: 'sticky',
        top: 0,
        zIndex: 20,
        background: 'color-mix(in srgb, var(--parchment-100) 88%, transparent)',
        backdropFilter: 'saturate(140%) blur(10px)',
        borderBottom: '1px solid var(--border-hair)'
      }
    }, e('div', {
      style: {
        maxWidth: 'var(--container-full)',
        margin: '0 auto',
        padding: '14px var(--space-6)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 24
      }
    }, e(Logo, {
      onClick: onHome
    }), e('nav', {
      style: {
        display: 'flex',
        gap: 28,
        alignItems: 'center'
      }
    }, nav.map(n => e('a', {
      key: n,
      href: '#',
      onClick: ev => ev.preventDefault(),
      style: {
        fontFamily: 'var(--font-label)',
        fontSize: 13,
        letterSpacing: '0.06em',
        color: 'var(--text-body)',
        textDecoration: 'none'
      }
    }, n))), e('div', {
      style: {
        display: 'flex',
        gap: 10,
        alignItems: 'center'
      }
    }, e(IconButton, {
      label: 'Search',
      variant: 'ghost'
    }, e('i', {
      'data-lucide': 'search',
      style: {
        width: 18,
        height: 18
      }
    })), e(Button, {
      size: 'sm',
      onClick: onSubscribe
    }, 'Subscribe'))));
  }
  function SiteFooter() {
    const cols = [{
      h: 'The Journal',
      items: ['Essays', 'The Charter', 'Editorial standards', 'Archive']
    }, {
      h: 'The Council',
      items: ['Members', 'How we deliberate', 'Open questions', 'Contact']
    }, {
      h: 'Follow',
      items: ['Fortnightly dispatch', 'RSS', 'Mastodon']
    }];
    return e('footer', {
      style: {
        background: 'var(--surface-inverse)',
        color: 'var(--green-200)',
        marginTop: 'var(--space-12)'
      }
    }, e('div', {
      style: {
        maxWidth: 'var(--container-full)',
        margin: '0 auto',
        padding: 'var(--space-9) var(--space-6) var(--space-7)',
        display: 'grid',
        gridTemplateColumns: '1.4fr 1fr 1fr 1fr',
        gap: 40
      }
    }, e('div', null, e(Logo, {
      inverse: true
    }), e('p', {
      style: {
        marginTop: 16,
        font: 'var(--type-ui)',
        color: 'var(--green-200)',
        maxWidth: '34ch'
      }
    }, 'A journal on the governance of artificial minds. Measured, plural, and built to last.')), cols.map(c => e('div', {
      key: c.h
    }, e('div', {
      style: {
        fontFamily: 'var(--font-label)',
        fontSize: 11,
        letterSpacing: '0.18em',
        textTransform: 'uppercase',
        color: 'var(--gold-400)',
        marginBottom: 14
      }
    }, c.h), e('ul', {
      style: {
        listStyle: 'none',
        margin: 0,
        padding: 0,
        display: 'flex',
        flexDirection: 'column',
        gap: 9
      }
    }, c.items.map(i => e('li', {
      key: i
    }, e('a', {
      href: '#',
      onClick: ev => ev.preventDefault(),
      style: {
        color: 'var(--green-200)',
        textDecoration: 'none',
        fontSize: 15
      }
    }, i))))))), e('div', {
      style: {
        borderTop: '1px solid var(--twilight-700)',
        padding: '18px var(--space-6)',
        maxWidth: 'var(--container-full)',
        margin: '0 auto',
        display: 'flex',
        justifyContent: 'space-between',
        fontFamily: 'var(--font-mono)',
        fontSize: 11,
        color: 'var(--ink-300)'
      }
    }, e('span', null, '© Third Age 26 · The Imladris Journal'), e('span', null, 'Et Eärello Endorenna utúlien.')));
  }
  Object.assign(window, {
    Logo,
    SiteHeader,
    SiteFooter
  });
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/parts/SiteChrome.compiled.js", error: String((e && e.message) || e) }); }

// ui_kits/blog/parts/SiteChrome.jsx
try { (() => {
// SiteHeader & SiteFooter — the persistent chrome of the Imladris journal.
const {
  Button,
  IconButton
} = window.ImladrisDesignSystem_89e0d2;
function Logo({
  onClick,
  inverse
}) {
  return /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      onClick && onClick();
    },
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 12,
      textDecoration: 'none'
    }
  }, /*#__PURE__*/React.createElement(window.EmblemMark, {
    color: inverse ? 'var(--gold-400)' : 'var(--green-700)',
    style: {
      width: 30,
      height: 30
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: 'var(--font-label)',
      letterSpacing: '0.32em',
      fontSize: 20,
      color: inverse ? 'var(--parchment-50)' : 'var(--ink-900)'
    }
  }, "IMLADRIS"));
}
function SiteHeader({
  onHome,
  onSubscribe
}) {
  const nav = ['Essays', 'The Charter', 'Council', 'Archive'];
  return /*#__PURE__*/React.createElement("header", {
    style: {
      position: 'sticky',
      top: 0,
      zIndex: 20,
      background: 'color-mix(in srgb, var(--parchment-100) 88%, transparent)',
      backdropFilter: 'saturate(140%) blur(10px)',
      borderBottom: '1px solid var(--border-hair)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--container-full)',
      margin: '0 auto',
      padding: '14px var(--space-6)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: 24
    }
  }, /*#__PURE__*/React.createElement(Logo, {
    onClick: onHome
  }), /*#__PURE__*/React.createElement("nav", {
    style: {
      display: 'flex',
      gap: 28,
      alignItems: 'center'
    }
  }, nav.map(n => /*#__PURE__*/React.createElement("a", {
    key: n,
    href: "#",
    onClick: e => e.preventDefault(),
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 13,
      letterSpacing: '0.06em',
      color: 'var(--text-body)',
      textDecoration: 'none'
    }
  }, n))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 10,
      alignItems: 'center'
    }
  }, /*#__PURE__*/React.createElement(IconButton, {
    label: "Search",
    variant: "ghost"
  }, /*#__PURE__*/React.createElement("i", {
    "data-lucide": "search",
    style: {
      width: 18,
      height: 18
    }
  })), /*#__PURE__*/React.createElement(Button, {
    size: "sm",
    onClick: onSubscribe
  }, "Subscribe"))));
}
function SiteFooter() {
  const cols = [{
    h: 'The Journal',
    items: ['Essays', 'The Charter', 'Editorial standards', 'Archive']
  }, {
    h: 'The Council',
    items: ['Members', 'How we deliberate', 'Open questions', 'Contact']
  }, {
    h: 'Follow',
    items: ['Fortnightly dispatch', 'RSS', 'Mastodon']
  }];
  return /*#__PURE__*/React.createElement("footer", {
    style: {
      background: 'var(--surface-inverse)',
      color: 'var(--green-200)',
      marginTop: 'var(--space-12)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--container-full)',
      margin: '0 auto',
      padding: 'var(--space-9) var(--space-6) var(--space-7)',
      display: 'grid',
      gridTemplateColumns: '1.4fr 1fr 1fr 1fr',
      gap: 40
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(Logo, {
    inverse: true
  }), /*#__PURE__*/React.createElement("p", {
    style: {
      marginTop: 16,
      font: 'var(--type-ui)',
      color: 'var(--green-200)',
      maxWidth: '34ch'
    }
  }, "A journal on the governance of artificial minds. Measured, plural, and built to last.")), cols.map(c => /*#__PURE__*/React.createElement("div", {
    key: c.h
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontFamily: 'var(--font-label)',
      fontSize: 11,
      letterSpacing: '0.18em',
      textTransform: 'uppercase',
      color: 'var(--gold-400)',
      marginBottom: 14
    }
  }, c.h), /*#__PURE__*/React.createElement("ul", {
    style: {
      listStyle: 'none',
      margin: 0,
      padding: 0,
      display: 'flex',
      flexDirection: 'column',
      gap: 9
    }
  }, c.items.map(i => /*#__PURE__*/React.createElement("li", {
    key: i
  }, /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => e.preventDefault(),
    style: {
      color: 'var(--green-200)',
      textDecoration: 'none',
      fontSize: 15
    }
  }, i))))))), /*#__PURE__*/React.createElement("div", {
    style: {
      borderTop: '1px solid var(--twilight-700)',
      padding: '18px var(--space-6)',
      maxWidth: 'var(--container-full)',
      margin: '0 auto',
      display: 'flex',
      justifyContent: 'space-between',
      fontFamily: 'var(--font-mono)',
      fontSize: 11,
      color: 'var(--ink-300)'
    }
  }, /*#__PURE__*/React.createElement("span", null, "\xA9 Third Age 26 \xB7 The Imladris Journal"), /*#__PURE__*/React.createElement("span", null, "Et E\xE4rello Endorenna ut\xFAlien.")));
}
Object.assign(window, {
  Logo,
  SiteHeader,
  SiteFooter
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/blog/parts/SiteChrome.jsx", error: String((e && e.message) || e) }); }

__ds_ns.Button = __ds_scope.Button;

__ds_ns.IconButton = __ds_scope.IconButton;

__ds_ns.ArticleCard = __ds_scope.ArticleCard;

__ds_ns.Avatar = __ds_scope.Avatar;

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Callout = __ds_scope.Callout;

__ds_ns.PullQuote = __ds_scope.PullQuote;

__ds_ns.Tag = __ds_scope.Tag;

__ds_ns.ArtifactRow = __ds_scope.ArtifactRow;

__ds_ns.EvidenceBoard = __ds_scope.EvidenceBoard;

__ds_ns.OperationalStory = __ds_scope.OperationalStory;

__ds_ns.ProductHero = __ds_scope.ProductHero;

__ds_ns.ProofBar = __ds_scope.ProofBar;

__ds_ns.WorkEntry = __ds_scope.WorkEntry;

__ds_ns.Input = __ds_scope.Input;

__ds_ns.Subscribe = __ds_scope.Subscribe;

__ds_ns.RingCard = __ds_scope.RingCard;

__ds_ns.SiteFooter = __ds_scope.SiteFooter;

__ds_ns.SiteHeader = __ds_scope.SiteHeader;

})();
