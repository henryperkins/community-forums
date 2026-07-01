/* @ds-bundle: {"format":3,"namespace":"ImladrisDesignSystem_c3e027","components":[{"name":"CommendStar","sourcePath":"components/brand/CommendStar.jsx"},{"name":"EightPointStar","sourcePath":"components/brand/EightPointStar.jsx"},{"name":"Badge","sourcePath":"components/core/Badge.jsx"},{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"Card","sourcePath":"components/core/Card.jsx"},{"name":"Chip","sourcePath":"components/core/Chip.jsx"},{"name":"Pill","sourcePath":"components/core/Pill.jsx"},{"name":"Tag","sourcePath":"components/core/Tag.jsx"},{"name":"Callout","sourcePath":"components/doc/Callout.jsx"},{"name":"DocCover","sourcePath":"components/doc/DocCover.jsx"},{"name":"Figure","sourcePath":"components/doc/Figure.jsx"},{"name":"SectionHeader","sourcePath":"components/doc/SectionHeader.jsx"},{"name":"SpecTable","sourcePath":"components/doc/SpecTable.jsx"},{"name":"ChoiceCard","sourcePath":"components/forms/ChoiceCard.jsx"},{"name":"Input","sourcePath":"components/forms/Input.jsx"},{"name":"Switch","sourcePath":"components/forms/Switch.jsx"},{"name":"Textarea","sourcePath":"components/forms/Textarea.jsx"},{"name":"Composer","sourcePath":"components/forum/Composer.jsx"},{"name":"JoinBar","sourcePath":"components/forum/JoinBar.jsx"},{"name":"ParticipantStack","sourcePath":"components/forum/ParticipantStack.jsx"},{"name":"Post","sourcePath":"components/forum/Post.jsx"},{"name":"Tabs","sourcePath":"components/forum/Tabs.jsx"},{"name":"ThreadRow","sourcePath":"components/forum/ThreadRow.jsx"},{"name":"Monogram","sourcePath":"components/identity/Monogram.jsx"},{"name":"Reaction","sourcePath":"components/identity/Reaction.jsx"},{"name":"StarButton","sourcePath":"components/identity/StarButton.jsx"}],"sourceHashes":{"components/brand/CommendStar.jsx":"2fdec638ecb6","components/brand/EightPointStar.jsx":"78e9e4f44d92","components/core/Badge.jsx":"dceb5116fea3","components/core/Button.jsx":"6d2696ea6302","components/core/Card.jsx":"36db3a574747","components/core/Chip.jsx":"506fbf1d2fe5","components/core/Pill.jsx":"c1f2c9ae1c51","components/core/Tag.jsx":"cf0c0c19f406","components/doc/Callout.jsx":"d81172950bf7","components/doc/DocCover.jsx":"18a6819b7965","components/doc/Figure.jsx":"0b0e23dd7055","components/doc/SectionHeader.jsx":"bace9f8cd863","components/doc/SpecTable.jsx":"ee0a3c3d869b","components/forms/ChoiceCard.jsx":"996f6b5363ed","components/forms/Input.jsx":"f678e1e24152","components/forms/Switch.jsx":"124d55994abc","components/forms/Textarea.jsx":"8a89777423e7","components/forum/Composer.jsx":"df4b5c20ac44","components/forum/JoinBar.jsx":"fe58e0c52b0c","components/forum/ParticipantStack.jsx":"206956583bdc","components/forum/Post.jsx":"8c5b7492401e","components/forum/Tabs.jsx":"a082051bec4a","components/forum/ThreadRow.jsx":"9e69f32282fa","components/identity/Monogram.jsx":"f31129a7e7ae","components/identity/Reaction.jsx":"456807636487","components/identity/StarButton.jsx":"3b65ec629ed5","feature-ui/organize/organize.jsx":"5afce4767810","feature-ui/shared/chrome.jsx":"36ebda32d49a","public/assets/app.js":"69deeb418ac8","public/assets/composer.js":"c2c3b354c9b7","public/assets/tour.js":"f21cd59416f6","ui_kits/admin/AdminApp.jsx":"931f69eb0239","ui_kits/admin/AdminSections.jsx":"956bd620ee9f","ui_kits/admin/data.js":"e51c0287f01d","ui_kits/auth/AuthApp.jsx":"d9a2d51a808e","ui_kits/dm/Compose.jsx":"61b561ca2cd7","ui_kits/dm/ConvoList.jsx":"4849fb82bc48","ui_kits/dm/DMApp.jsx":"89bbc189ec17","ui_kits/dm/DMTopbar.jsx":"42c439534c97","ui_kits/dm/Thread.jsx":"a2f01b4b3157","ui_kits/dm/data.js":"5c575b21e08a","ui_kits/mod/ModApp.jsx":"0989542838fc","ui_kits/mod/ModSections.jsx":"6f5ed1730587","ui_kits/mod/data.js":"e4dd249c62cf","ui_kits/reading/ReadingApp.jsx":"72d25dbaf619","ui_kits/reading/ReadingChrome.jsx":"5f89513e1b17","ui_kits/reading/ReadingExtras.jsx":"9d7ab8a18cf4","ui_kits/reading/ReadingSurfaces.jsx":"1dfe54c4faf5","ui_kits/reading/reading-data.js":"9e93b326c2c3","ui_kits/retroboards/App.jsx":"90a6ffa3d85c","ui_kits/retroboards/Conversation.jsx":"ab9e90384a1e","ui_kits/retroboards/Inbox.jsx":"8aece8676535","ui_kits/retroboards/Leaderboard.jsx":"019dde493247","ui_kits/retroboards/Profile.jsx":"39d2b38848d3","ui_kits/retroboards/Rail.jsx":"824ffa2bf89b","ui_kits/retroboards/Topbar.jsx":"70d697989cc1","ui_kits/retroboards/data.js":"3d5e91a4fabd","ui_kits/settings/Chrome.jsx":"1ac72a03b412","ui_kits/settings/SettingsApp.jsx":"916eb090d2f3","ui_kits/settings/SettingsSections.jsx":"eaac085bdeb3","ui_kits/settings/data.js":"8af210b00f80"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.ImladrisDesignSystem_c3e027 = window.ImladrisDesignSystem_c3e027 || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/brand/CommendStar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * CommendStar — the filled four-point star used for "Commends" (reputation /
 * esteem). It is the glyph on reaction chips, reputation counts, the star
 * button, and the accepted-answer mark. Inherits `currentColor` (gold by
 * convention).
 */
function CommendStar({
  size = 14,
  title,
  className = '',
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("svg", _extends({
    viewBox: "0 0 100 100",
    width: size,
    height: size,
    role: title ? 'img' : undefined,
    "aria-hidden": title ? undefined : 'true',
    "aria-label": title,
    className: className,
    style: {
      display: 'inline-block',
      verticalAlign: 'middle',
      flex: '0 0 auto',
      ...style
    }
  }, rest), title ? /*#__PURE__*/React.createElement("title", null, title) : null, /*#__PURE__*/React.createElement("path", {
    fill: "currentColor",
    d: "M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z"
  }));
}
Object.assign(__ds_scope, { CommendStar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/brand/CommendStar.jsx", error: String((e && e.message) || e) }); }

// components/brand/EightPointStar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * EightPointStar — the Imladris elven star, the house mark.
 * An eight-pointed star (outer + faint inner star + a center dot), drawn from
 * the brand's authoritative path data. Inherits `currentColor`, so colour it
 * with a wrapping `color` (evergreen for the wordmark, gold for esteem, faint
 * gold for a watermark).
 */
function EightPointStar({
  size = 26,
  strokeWidth = 3.4,
  variant = 'mark',
  // 'mark' | 'watermark'
  title,
  className = '',
  style,
  ...rest
}) {
  const isWatermark = variant === 'watermark';
  return /*#__PURE__*/React.createElement("svg", _extends({
    viewBox: "0 0 100 100",
    width: size,
    height: size,
    role: title ? 'img' : undefined,
    "aria-hidden": title ? undefined : 'true',
    "aria-label": title,
    className: className,
    style: {
      display: 'block',
      flex: '0 0 auto',
      opacity: isWatermark ? 0.12 : 1,
      ...style
    }
  }, rest), title ? /*#__PURE__*/React.createElement("title", null, title) : null, /*#__PURE__*/React.createElement("g", {
    fill: "none",
    stroke: "currentColor",
    strokeWidth: isWatermark ? 1.3 : strokeWidth,
    strokeLinejoin: "round",
    strokeLinecap: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z",
    opacity: "0.5"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "50",
    cy: "50",
    r: "5",
    fill: "currentColor",
    stroke: "none"
  })));
}
Object.assign(__ds_scope, { EightPointStar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/brand/EightPointStar.jsx", error: String((e && e.message) || e) }); }

// components/core/Badge.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
// Role/author badges. OP and Wiki use the base green badge; Staff is gold so it
// never reads as the green OP badge; muted is neutral; solved is the outlined
// accepted-answer marker.
const VARIANT = {
  op: {
    cls: '',
    label: 'OP'
  },
  wiki: {
    cls: '',
    label: 'Wiki'
  },
  staff: {
    cls: 'badge-staff',
    label: 'Staff'
  },
  muted: {
    cls: 'badge-muted',
    label: null
  },
  solved: {
    cls: 'badge-solved',
    label: 'Solved'
  }
};

/**
 * Badge — a role / author marker shown inline with a name (OP, Staff, Wiki) or
 * a small outlined status (solved). For topic-status pills in the inbox use
 * Chip; for identity/presence use Pill.
 */
function Badge({
  variant = 'op',
  className = '',
  children,
  ...rest
}) {
  const v = VARIANT[variant] || VARIANT.op;
  const cls = ['badge', v.cls, className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("span", _extends({
    className: cls
  }, rest), children != null ? children : v.label);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Badge.jsx", error: String((e && e.message) || e) }); }

// components/core/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const VARIANT_CLASS = {
  primary: '',
  secondary: 'btn-secondary',
  ghost: 'btn-ghost',
  accent: 'btn-accent',
  danger: 'btn-danger'
};

/**
 * Button — the Imladris action control. Lapidary Marcellus label, sentence
 * case. Primary is evergreen; accent is mallorn-gold (reserve for the single
 * most-wanted action, e.g. Follow); secondary is a parchment outline; ghost is
 * a quiet wash. Renders an <a> when `href` is given, otherwise a <button>.
 */
function Button({
  variant = 'primary',
  size = 'md',
  href,
  icon,
  // optional leading SVG/element
  iconAfter,
  // optional trailing element
  disabled = false,
  className = '',
  children,
  ...rest
}) {
  const cls = ['btn', VARIANT_CLASS[variant] || '', size === 'sm' ? 'btn-small' : '', className].filter(Boolean).join(' ');
  const content = /*#__PURE__*/React.createElement(React.Fragment, null, icon ? /*#__PURE__*/React.createElement("span", {
    className: "btn-icon-wrap",
    "aria-hidden": "true",
    style: {
      display: 'inline-flex'
    }
  }, icon) : null, children != null ? /*#__PURE__*/React.createElement("span", null, children) : null, iconAfter ? /*#__PURE__*/React.createElement("span", {
    "aria-hidden": "true",
    style: {
      display: 'inline-flex'
    }
  }, iconAfter) : null);
  if (href && !disabled) {
    return /*#__PURE__*/React.createElement("a", _extends({
      href: href,
      className: cls
    }, rest), content);
  }
  return /*#__PURE__*/React.createElement("button", _extends({
    type: rest.type || 'button',
    className: cls,
    disabled: disabled,
    "aria-disabled": disabled || undefined
  }, rest), content);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Button.jsx", error: String((e && e.message) || e) }); }

// components/core/Card.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Card — the base raised surface: parchment ground, hairline border, soft
 * shadow, large radius. The container most Imladris content sits on.
 */
function Card({
  as: Tag = 'div',
  className = '',
  children,
  ...rest
}) {
  return /*#__PURE__*/React.createElement(Tag, _extends({
    className: ['card', className].filter(Boolean).join(' ')
  }, rest), children);
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Card.jsx", error: String((e && e.message) || e) }); }

// components/core/Chip.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
// Topic-status chips. Each maps to a status class; the inbox row also carries a
// matching coloured left-rule (see ThreadRow). Text label is required so status
// is never carried by colour alone.
const STATUS = {
  solved: {
    cls: 'chip-solved',
    label: 'Solved'
  },
  needs: {
    cls: 'chip-needs',
    label: 'Needs answer'
  },
  needs_answer: {
    cls: 'chip-needs',
    label: 'Needs answer'
  },
  decision_made: {
    cls: 'chip-decision_made',
    label: 'Decision'
  },
  pinned: {
    cls: 'chip-pinned',
    label: 'Pinned'
  },
  locked: {
    cls: 'chip-locked',
    label: 'Locked'
  },
  archived: {
    cls: 'chip-archived',
    label: 'Archived'
  }
};

/**
 * Chip — a topic-status pill (Solved, Needs answer, Decision, Pinned, Locked,
 * Archived). Always carries a word, never colour alone. Pass `icon` to prepend
 * a glyph (e.g. a Lucide circle-check); the inbox usually shows text only.
 */
function Chip({
  status = 'solved',
  icon,
  className = '',
  children,
  ...rest
}) {
  const s = STATUS[status] || STATUS.solved;
  const cls = ['chip', s.cls, className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("span", _extends({
    className: cls
  }, rest), icon ? /*#__PURE__*/React.createElement("span", {
    "aria-hidden": "true",
    style: {
      display: 'inline-flex'
    }
  }, icon) : null, children != null ? children : s.label);
}
Object.assign(__ds_scope, { Chip });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Chip.jsx", error: String((e && e.message) || e) }); }

// components/core/Pill.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const TONE_CLASS = {
  default: '',
  admin: 'pill-admin',
  online: 'pill-online'
};

/**
 * Pill — a small lapidary-caps status token (e.g. "Guest", "Admin", "Online").
 * Distinct from Badge (role) and Chip (topic status); Pill is for identity /
 * presence states.
 */
function Pill({
  tone = 'default',
  className = '',
  children,
  ...rest
}) {
  const cls = ['pill', TONE_CLASS[tone] || '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("span", _extends({
    className: cls
  }, rest), children);
}
Object.assign(__ds_scope, { Pill });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Pill.jsx", error: String((e && e.message) || e) }); }

// components/core/Tag.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Tag — the smallest meta token: board visibility, a topic tag, a quiet label.
 * Lapidary micro-caps on a sunken pill.
 */
function Tag({
  className = '',
  children,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("span", _extends({
    className: ['tag', className].filter(Boolean).join(' ')
  }, rest), children);
}
Object.assign(__ds_scope, { Tag });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Tag.jsx", error: String((e && e.message) || e) }); }

// components/doc/Callout.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const TONE_CLASS = {
  note: '',
  // gold rule on a brand wash (the default)
  info: 'doc-callout-info',
  warn: 'doc-callout-warn',
  danger: 'doc-callout-danger',
  quiet: 'doc-callout-quiet'
};

/**
 * Callout — an aside for notes, acceptance criteria, flows, and warnings.
 * `tone` recolours the rule + wash (note · info · warn · danger · quiet);
 * `variant="panel"` swaps the gold left-rule for a full hairline box (for keyed
 * cards and flows). A tracked `label` and optional display `title` sit above
 * the body.
 */
function Callout({
  tone = 'note',
  variant = 'rule',
  label,
  title,
  className = '',
  children,
  ...rest
}) {
  const cls = ['doc-callout', TONE_CLASS[tone] || '', variant === 'panel' ? 'is-panel' : '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("aside", _extends({
    className: cls
  }, rest), label ? /*#__PURE__*/React.createElement("div", {
    className: "doc-callout-label"
  }, label) : null, title ? /*#__PURE__*/React.createElement("div", {
    className: "doc-callout-title"
  }, title) : null, /*#__PURE__*/React.createElement("div", {
    className: "doc-callout-body"
  }, children));
}
Object.assign(__ds_scope, { Callout });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/doc/Callout.jsx", error: String((e && e.message) || e) }); }

// components/doc/DocCover.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
// The Imladris eight-point mark (Eärendil's star), used as the cover device.
const DEFAULT_MARK = /*#__PURE__*/React.createElement("svg", {
  viewBox: "0 0 100 100",
  fill: "none",
  "aria-hidden": "true"
}, /*#__PURE__*/React.createElement("g", {
  stroke: "currentColor",
  strokeWidth: "3.2",
  strokeLinejoin: "round",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("path", {
  d: "M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z"
}), /*#__PURE__*/React.createElement("path", {
  d: "M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z",
  opacity: "0.5"
}), /*#__PURE__*/React.createElement("circle", {
  cx: "50",
  cy: "50",
  r: "5",
  fill: "currentColor",
  stroke: "none"
})));

/**
 * DocCover — the title page of a long-form Imladris document. A tracked-caps
 * kicker beside the eight-point mark, a large display title, an italic dek, a
 * gold rule, the lede, a two-column meta grid, and a contents rail of section
 * pills. Anything passed as children renders between the lede and the meta grid.
 */
function DocCover({
  kicker,
  title,
  subtitle,
  lede,
  meta = [],
  contents = [],
  mark,
  // pass null to hide the device
  className = '',
  children,
  ...rest
}) {
  const showMark = mark !== null;
  return /*#__PURE__*/React.createElement("header", _extends({
    className: ['doc-cover', className].filter(Boolean).join(' ')
  }, rest), kicker || showMark ? /*#__PURE__*/React.createElement("div", {
    className: "doc-cover-brand"
  }, showMark ? /*#__PURE__*/React.createElement("span", {
    className: "doc-cover-mark"
  }, mark || DEFAULT_MARK) : null, kicker ? /*#__PURE__*/React.createElement("span", {
    className: "doc-cover-kicker"
  }, kicker) : null) : null, title ? /*#__PURE__*/React.createElement("h1", {
    className: "doc-cover-title"
  }, title) : null, subtitle ? /*#__PURE__*/React.createElement("div", {
    className: "doc-cover-dek"
  }, subtitle) : null, /*#__PURE__*/React.createElement("div", {
    className: "doc-cover-rule",
    "aria-hidden": "true"
  }), lede ? /*#__PURE__*/React.createElement("p", {
    className: "doc-cover-lede"
  }, lede) : null, children, meta.length ? /*#__PURE__*/React.createElement("div", {
    className: "doc-cover-meta"
  }, meta.map((m, i) => /*#__PURE__*/React.createElement("div", {
    className: "doc-cover-meta-cell",
    key: i
  }, /*#__PURE__*/React.createElement("div", {
    className: "doc-cover-meta-label"
  }, m.label), /*#__PURE__*/React.createElement("div", {
    className: "doc-cover-meta-value"
  }, m.value)))) : null, contents.length ? /*#__PURE__*/React.createElement("nav", {
    className: "doc-cover-toc",
    "aria-label": "Contents"
  }, contents.map((c, i) => /*#__PURE__*/React.createElement("span", {
    className: "doc-cover-toc-item",
    key: i
  }, c))) : null);
}
Object.assign(__ds_scope, { DocCover });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/doc/DocCover.jsx", error: String((e && e.message) || e) }); }

// components/doc/Figure.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const SLOT_ICON = /*#__PURE__*/React.createElement("svg", {
  viewBox: "0 0 24 24",
  "aria-hidden": "true"
}, /*#__PURE__*/React.createElement("rect", {
  x: "3",
  y: "3",
  width: "18",
  height: "18",
  rx: "2"
}), /*#__PURE__*/React.createElement("circle", {
  cx: "8.5",
  cy: "8.5",
  r: "1.6"
}), /*#__PURE__*/React.createElement("path", {
  d: "M21 15l-5-5L5 21"
}));

/**
 * Figure — a framed image with a mono caption ("FIG 3 — …"). Supply the media
 * three ways: pass `src` for an <img>, pass children (e.g. an <image-slot> the
 * reader fills, or a strip of device frames), or pass nothing and it renders a
 * drop-in slot. `plain` removes the frame for edge-to-edge art.
 */
function Figure({
  src,
  alt = '',
  label,
  caption,
  plain = false,
  slotHint = 'Drop a screenshot here',
  className = '',
  children,
  ...rest
}) {
  let media;
  if (children != null) {
    media = children;
  } else if (src) {
    media = /*#__PURE__*/React.createElement("img", {
      className: "doc-figure-img",
      src: src,
      alt: alt
    });
  } else {
    media = /*#__PURE__*/React.createElement("div", {
      className: "doc-figure-slot",
      role: "img",
      "aria-label": alt || (typeof slotHint === 'string' ? slotHint : 'Image')
    }, SLOT_ICON, /*#__PURE__*/React.createElement("span", null, slotHint));
  }
  const cls = ['doc-figure', plain ? 'is-plain' : '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("figure", _extends({
    className: cls
  }, rest), /*#__PURE__*/React.createElement("div", {
    className: "doc-figure-frame"
  }, media), label || caption ? /*#__PURE__*/React.createElement("figcaption", {
    className: "doc-figure-cap"
  }, label ? /*#__PURE__*/React.createElement("span", {
    className: "doc-figure-cap-label"
  }, label) : null, caption ? /*#__PURE__*/React.createElement("span", {
    className: "doc-figure-cap-text"
  }, caption) : null) : null);
}
Object.assign(__ds_scope, { Figure });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/doc/Figure.jsx", error: String((e && e.message) || e) }); }

// components/doc/SectionHeader.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * SectionHeader — a numbered section (or sub-section) header. A tracked kicker
 * ("§5 · Screens & flows") over a display title, with an optional italic
 * standfirst. `level="section"` is the gold, h2 register that opens a chapter;
 * `level="sub"` is the quieter ink, h3 register for a sub-section.
 */
function SectionHeader({
  number,
  kicker,
  title,
  dek,
  level = 'section',
  as,
  className = '',
  ...rest
}) {
  const Tag = as || (level === 'sub' ? 'h3' : 'h2');
  const label = [number, kicker].filter(Boolean).join(' · ');
  const cls = ['doc-section', level === 'sub' ? 'is-sub' : '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("header", _extends({
    className: cls
  }, rest), label ? /*#__PURE__*/React.createElement("div", {
    className: "doc-section-kicker"
  }, label) : null, title ? /*#__PURE__*/React.createElement(Tag, {
    className: "doc-section-title"
  }, title) : null, dek ? /*#__PURE__*/React.createElement("p", {
    className: "doc-section-dek"
  }, dek) : null);
}
Object.assign(__ds_scope, { SectionHeader });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/doc/SectionHeader.jsx", error: String((e && e.message) || e) }); }

// components/doc/SpecTable.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * SpecTable — a bordered reference table with a sunken caps header, a serif
 * body, optional zebra rows, and node-capable cells (prose, mono, or a ✓ / —
 * mark via the .doc-yes / .doc-no / .doc-scoped utilities). `columns` carry an
 * optional `align` ('center' | 'right'); `rows` are objects keyed by
 * column.key, or arrays in column order.
 */
function SpecTable({
  columns = [],
  rows = [],
  caption,
  zebra = true,
  className = '',
  ...rest
}) {
  const hasHead = columns.some(c => c.label != null);
  const cellOf = (row, c, i) => Array.isArray(row) ? row[i] : row[c.key];
  const alignCls = c => c.align ? 'is-' + c.align : '';
  return /*#__PURE__*/React.createElement("div", {
    className: ['doc-table-wrap', className].filter(Boolean).join(' ')
  }, /*#__PURE__*/React.createElement("table", _extends({
    className: ['doc-table', zebra ? 'is-zebra' : ''].filter(Boolean).join(' ')
  }, rest), caption ? /*#__PURE__*/React.createElement("caption", null, caption) : null, hasHead ? /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, columns.map((c, i) => /*#__PURE__*/React.createElement("th", {
    key: c.key != null ? c.key : i,
    scope: "col",
    className: alignCls(c)
  }, c.label)))) : null, /*#__PURE__*/React.createElement("tbody", null, rows.map((row, ri) => /*#__PURE__*/React.createElement("tr", {
    key: ri
  }, columns.map((c, ci) => /*#__PURE__*/React.createElement("td", {
    key: c.key != null ? c.key : ci,
    className: alignCls(c)
  }, cellOf(row, c, ci))))))));
}
Object.assign(__ds_scope, { SpecTable });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/doc/SpecTable.jsx", error: String((e && e.message) || e) }); }

// components/forms/ChoiceCard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * ChoiceCard — a large radio "card" for picking one of a small set (theme,
 * density). Selecting fills it with the brand wash + an inner ring. Pass a
 * `swatch` node (e.g. a theme preview) above the title.
 */
function ChoiceCard({
  name,
  value,
  checked,
  defaultChecked,
  onChange,
  title,
  desc,
  swatch,
  className = '',
  ...rest
}) {
  return /*#__PURE__*/React.createElement("label", {
    className: ['choice-card', className].filter(Boolean).join(' ')
  }, /*#__PURE__*/React.createElement("input", _extends({
    type: "radio",
    name: name,
    value: value,
    checked: checked,
    defaultChecked: defaultChecked,
    onChange: onChange
  }, rest)), swatch ? /*#__PURE__*/React.createElement("span", {
    "aria-hidden": "true"
  }, swatch) : null, /*#__PURE__*/React.createElement("span", {
    className: "choice-card-title"
  }, title), desc ? /*#__PURE__*/React.createElement("span", {
    className: "choice-card-desc"
  }, desc) : null);
}
Object.assign(__ds_scope, { ChoiceCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/ChoiceCard.jsx", error: String((e && e.message) || e) }); }

// components/forms/Input.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Input — a serif text field with a gold focus halo. `pill` makes the rounded
 * search-bar style. Wraps in a labelled field when `label` is given.
 */
function Input({
  pill = false,
  label,
  id,
  className = '',
  ...rest
}) {
  const input = /*#__PURE__*/React.createElement("input", _extends({
    id: id,
    className: ['input', pill ? 'input-pill' : '', className].filter(Boolean).join(' ')
  }, rest));
  if (label) {
    return /*#__PURE__*/React.createElement("label", {
      className: "field",
      htmlFor: id
    }, /*#__PURE__*/React.createElement("span", {
      className: "field-label"
    }, label), input);
  }
  return input;
}
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Input.jsx", error: String((e && e.message) || e) }); }

// components/forms/Switch.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Switch — a preference toggle. Evergreen track + parchment knob when on.
 * Pass `label` for the inline text, or use the bare control via `children`-less
 * form inside your own row.
 */
function Switch({
  checked,
  defaultChecked,
  onChange,
  label,
  id,
  disabled,
  className = '',
  ...rest
}) {
  const control = /*#__PURE__*/React.createElement("input", _extends({
    type: "checkbox",
    id: id,
    className: ['switch', className].filter(Boolean).join(' '),
    checked: checked,
    defaultChecked: defaultChecked,
    onChange: onChange,
    disabled: disabled,
    role: "switch"
  }, rest));
  if (label) {
    return /*#__PURE__*/React.createElement("label", {
      className: "switchline",
      htmlFor: id
    }, control, /*#__PURE__*/React.createElement("span", {
      className: "switch-text"
    }, label));
  }
  return control;
}
Object.assign(__ds_scope, { Switch });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Switch.jsx", error: String((e && e.message) || e) }); }

// components/forms/Textarea.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Textarea — the serif multi-line field used by the composer and forms. Auto
 * gold focus halo; vertically resizable.
 */
function Textarea({
  label,
  id,
  rows = 4,
  className = '',
  ...rest
}) {
  const ta = /*#__PURE__*/React.createElement("textarea", _extends({
    id: id,
    rows: rows,
    className: ['textarea', className].filter(Boolean).join(' ')
  }, rest));
  if (label) {
    return /*#__PURE__*/React.createElement("label", {
      className: "field",
      htmlFor: id
    }, /*#__PURE__*/React.createElement("span", {
      className: "field-label"
    }, label), ta);
  }
  return ta;
}
Object.assign(__ds_scope, { Textarea });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Textarea.jsx", error: String((e && e.message) || e) }); }

// components/forum/Composer.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Composer — the reply / new-topic card. A "Posting as …" identity strip, an
 * optional Markdown toolbar, the serif Textarea, and an actions row with the
 * green send button (and a char counter). Pass `toolbar` to show the format
 * controls, or compose your own children.
 */
function Composer({
  postingAs,
  placeholder = 'Add to the discussion…',
  toolbar = true,
  sendLabel = 'Reply',
  value,
  defaultValue,
  onChange,
  count,
  // optional "n / max" string for the counter
  className = '',
  ...rest
}) {
  return /*#__PURE__*/React.createElement("form", _extends({
    className: ['composer', className].filter(Boolean).join(' ')
  }, rest), postingAs ? /*#__PURE__*/React.createElement("div", {
    className: "composer-id"
  }, "Posting as ", /*#__PURE__*/React.createElement("strong", {
    style: {
      color: 'var(--text-strong)',
      fontWeight: 'var(--weight-semibold)'
    }
  }, postingAs)) : null, toolbar ? /*#__PURE__*/React.createElement("div", {
    className: "composer-toolbar",
    role: "toolbar",
    "aria-label": "Formatting"
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Bold",
    style: {
      fontWeight: 700
    }
  }, "B"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Italic",
    style: {
      fontStyle: 'italic'
    }
  }, "I"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Strikethrough",
    style: {
      textDecoration: 'line-through'
    }
  }, "S"), /*#__PURE__*/React.createElement("span", {
    className: "composer-toolbar-sep",
    "aria-hidden": "true"
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "List"
  }, "List"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Quote"
  }, "Quote"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Code"
  }, "Code"), /*#__PURE__*/React.createElement("span", {
    className: "composer-toolbar-sep",
    "aria-hidden": "true"
  }), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Link"
  }, "Link"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Mention"
  }, "@")) : null, /*#__PURE__*/React.createElement("textarea", {
    className: "textarea",
    rows: 4,
    placeholder: placeholder,
    value: value,
    defaultValue: defaultValue,
    onChange: onChange
  }), /*#__PURE__*/React.createElement("div", {
    className: "composer-actions"
  }, /*#__PURE__*/React.createElement("button", {
    type: "submit",
    className: "btn"
  }, sendLabel), count != null ? /*#__PURE__*/React.createElement("span", {
    className: "composer-count"
  }, count) : null));
}
Object.assign(__ds_scope, { Composer });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forum/Composer.jsx", error: String((e && e.message) || e) }); }

// components/forum/JoinBar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * JoinBar — the guest's place at the table. Replaces the composer when signed
 * out: a brand-subtle card reading "You're browsing as a guest — log in to add
 * your counsel." with a primary Log in button. Use `archived` for the
 * locked/archived-topic variant.
 */
function JoinBar({
  message,
  cta = 'Log in',
  href = '/login',
  archived = false,
  className = '',
  ...rest
}) {
  const text = message || /*#__PURE__*/React.createElement(React.Fragment, null, "You're browsing as a guest \u2014 ", /*#__PURE__*/React.createElement("em", null, "log in to add your counsel."));
  return /*#__PURE__*/React.createElement("div", _extends({
    className: ['joinbar', archived ? 'joinbar-archived' : '', className].filter(Boolean).join(' ')
  }, rest), /*#__PURE__*/React.createElement("span", null, text), /*#__PURE__*/React.createElement("a", {
    className: "btn",
    href: href
  }, cta));
}
Object.assign(__ds_scope, { JoinBar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forum/JoinBar.jsx", error: String((e && e.message) || e) }); }

// components/forum/ParticipantStack.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function monoClass(seed) {
  const s = String(seed || '');
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h + s.charCodeAt(i)) % 10;
  return `mono-${h}`;
}
function initials(label) {
  const p = String(label || '').trim().split(/\s+/).filter(Boolean);
  if (!p.length) return '?';
  return (p.length === 1 ? p[0].slice(0, 2) : p[0][0] + p[1][0]).toUpperCase();
}

/**
 * ParticipantStack — overlapping small avatars for a topic's participants, with
 * an optional "+N" overflow. Used in the conversation header.
 */
function ParticipantStack({
  members = [],
  max = 5,
  extra,
  className = '',
  ...rest
}) {
  const shown = members.slice(0, max);
  const overflow = extra != null ? extra : Math.max(0, members.length - shown.length);
  return /*#__PURE__*/React.createElement("span", _extends({
    className: ['participant-stack', className].filter(Boolean).join(' ')
  }, rest), shown.map((m, i) => {
    const name = typeof m === 'string' ? m : m.name;
    const seed = typeof m === 'string' ? m : m.username || m.name;
    return /*#__PURE__*/React.createElement("span", {
      key: i,
      className: ['monogram', 'monogram-sm', monoClass(seed)].join(' '),
      "aria-hidden": "true"
    }, initials(name));
  }), overflow > 0 ? /*#__PURE__*/React.createElement("span", {
    className: "participant-more"
  }, "+", overflow) : null);
}
Object.assign(__ds_scope, { ParticipantStack });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forum/ParticipantStack.jsx", error: String((e && e.message) || e) }); }

// components/forum/Post.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function monoClass(seed) {
  const s = String(seed || '');
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h + s.charCodeAt(i)) % 10;
  return `mono-${h}`;
}
function initials(label) {
  const p = String(label || '').trim().split(/\s+/).filter(Boolean);
  if (!p.length) return '?';
  return (p.length === 1 ? p[0].slice(0, 2) : p[0][0] + p[1][0]).toUpperCase();
}
function tierClass(tier) {
  return 'tier-' + String(tier || 'member').toLowerCase();
}

/**
 * Post — one message in a conversation. A decorated identity column (gilt
 * avatar with presence + a stacked regard plinth) sits beside a head row
 * (name + tier + OP/Staff/Wiki badges + time), a signature line (handle ·
 * title), the body, and reactions. `op`/`accepted` gild the avatar; `accepted`
 * adds the green answer plate; `grouped` drops the repeated identity for a
 * consecutive same-author reply.
 */
function Post({
  author,
  authorSeed,
  authorHref,
  authorTier,
  // 'Member' | 'Veteran' | 'Loremaster' | 'Legend'
  handle,
  // @handle (signature line)
  authorTitle,
  // the member's title / signature (e.g. "Lady of the Wood")
  presence,
  // true | 'online' | 'away' | 'offline'
  time,
  edited = false,
  op = false,
  staff = false,
  wiki = false,
  accepted = false,
  grouped = false,
  rep,
  // regard (commends earned) — the avatar plinth
  reactions,
  children,
  className = '',
  ...rest
}) {
  const cls = ['post', op ? 'post-op' : '', accepted ? 'post-accepted' : '', grouped ? 'post-grouped' : '', className].filter(Boolean).join(' ');
  const seed = authorSeed || author;
  const gilt = op || accepted;
  const mono = /*#__PURE__*/React.createElement("span", {
    className: ['monogram', 'monogram-lg', monoClass(seed), gilt ? 'monogram-gilt' : ''].filter(Boolean).join(' '),
    "aria-hidden": "true"
  }, initials(author));
  const dotColor = presence === 'away' ? 'var(--amber)' : presence === 'offline' ? 'var(--ink-300)' : 'var(--presence)';
  const avatar = presence ? /*#__PURE__*/React.createElement("span", {
    className: "avatar-wrap"
  }, mono, /*#__PURE__*/React.createElement("span", {
    className: "presence-dot",
    style: {
      background: dotColor
    },
    "aria-hidden": "true"
  })) : mono;
  const hasSign = handle || authorTitle;
  return /*#__PURE__*/React.createElement("div", _extends({
    className: cls
  }, rest), grouped ? /*#__PURE__*/React.createElement("span", {
    className: "post-avatar-spacer",
    "aria-hidden": "true"
  }) : /*#__PURE__*/React.createElement("div", {
    className: "post-avatar"
  }, avatar, rep != null ? /*#__PURE__*/React.createElement("span", {
    className: "regard-block"
  }, /*#__PURE__*/React.createElement("span", {
    className: "regard-n"
  }, /*#__PURE__*/React.createElement("span", {
    className: "star-marker",
    "aria-hidden": "true"
  }, "\u2726"), rep), /*#__PURE__*/React.createElement("span", {
    className: "regard-label"
  }, "Commends")) : null), /*#__PURE__*/React.createElement("div", {
    className: "post-main"
  }, accepted ? /*#__PURE__*/React.createElement("p", {
    className: "accepted-flag"
  }, /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 24 24",
    "aria-hidden": "true"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M20 6L9 17l-5-5"
  })), "Marked as the answer", /*#__PURE__*/React.createElement("span", {
    className: "star-marker",
    "aria-hidden": "true",
    style: {
      marginLeft: 2
    }
  }, "\u2726")) : null, !grouped ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
    className: "post-head"
  }, authorHref ? /*#__PURE__*/React.createElement("a", {
    className: "post-author",
    href: authorHref
  }, author) : /*#__PURE__*/React.createElement("span", {
    className: "post-author"
  }, author), authorTier ? /*#__PURE__*/React.createElement("span", {
    className: `tier ${tierClass(authorTier)}`
  }, authorTier) : null, op ? /*#__PURE__*/React.createElement("span", {
    className: "badge"
  }, "OP") : null, wiki ? /*#__PURE__*/React.createElement("span", {
    className: "badge"
  }, "Wiki") : null, staff ? /*#__PURE__*/React.createElement("span", {
    className: "badge badge-staff"
  }, "Staff") : null, time ? /*#__PURE__*/React.createElement("span", {
    className: "post-time"
  }, time, edited ? ' · edited' : '') : null), hasSign ? /*#__PURE__*/React.createElement("p", {
    className: "post-sign"
  }, handle ? /*#__PURE__*/React.createElement("span", {
    className: "sign-handle"
  }, "@", handle) : null, handle && authorTitle ? ' · ' : null, authorTitle ? /*#__PURE__*/React.createElement("span", {
    className: "sign-title"
  }, authorTitle) : null) : null) : time ? /*#__PURE__*/React.createElement("div", {
    className: "post-head"
  }, /*#__PURE__*/React.createElement("span", {
    className: "post-time",
    style: {
      marginLeft: 0
    }
  }, time, edited ? ' · edited' : '')) : null, /*#__PURE__*/React.createElement("div", {
    className: "post-body"
  }, children), reactions ? /*#__PURE__*/React.createElement("div", {
    className: "reactions"
  }, reactions) : null));
}
Object.assign(__ds_scope, { Post });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forum/Post.jsx", error: String((e && e.message) || e) }); }

// components/forum/Tabs.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const VARIANT = {
  pill: {
    wrap: 'inbox-tabs',
    item: 'inbox-tab'
  },
  // filter pills (All / Unread / Starred / Mine)
  segment: {
    wrap: 'segmented',
    item: 'segmented-item'
  },
  // segmented control (Hall / Watch)
  underline: {
    wrap: 'text-tabs',
    item: 'text-tab'
  } // underline tabs (Active / Newest · profile tabs)
};

/**
 * Tabs — the Imladris tab set in three registers:
 *   · pill      — inbox filter pills (gold-fill active)
 *   · segment   — a segmented toggle (Hall / Watch density)
 *   · underline — quiet underline tabs (sort, profile sections)
 * Controlled via `value` + `onChange`.
 */
function Tabs({
  items = [],
  value,
  onChange,
  variant = 'pill',
  className = '',
  ...rest
}) {
  const v = VARIANT[variant] || VARIANT.pill;
  return /*#__PURE__*/React.createElement("div", _extends({
    className: [v.wrap, className].filter(Boolean).join(' '),
    role: "tablist"
  }, rest), items.map(it => {
    const val = typeof it === 'string' ? it : it.value;
    const label = typeof it === 'string' ? it : it.label;
    const active = val === value;
    return /*#__PURE__*/React.createElement("button", {
      key: val,
      type: "button",
      role: "tab",
      "aria-selected": active,
      className: [v.item, active ? 'is-active' : ''].filter(Boolean).join(' '),
      onClick: () => onChange && onChange(val)
    }, label);
  }));
}
Object.assign(__ds_scope, { Tabs });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forum/Tabs.jsx", error: String((e && e.message) || e) }); }

// components/forum/ThreadRow.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function monoClass(seed) {
  const s = String(seed || '');
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h + s.charCodeAt(i)) % 10;
  return `mono-${h}`;
}
function initials(label) {
  const p = String(label || '').trim().split(/\s+/).filter(Boolean);
  if (!p.length) return '?';
  return (p.length === 1 ? p[0].slice(0, 2) : p[0][0] + p[1][0]).toUpperCase();
}
function tierClass(tier) {
  return 'tier-' + String(tier || 'member').toLowerCase();
}
const Star = ({
  size = 11
}) => /*#__PURE__*/React.createElement("svg", {
  viewBox: "0 0 100 100",
  width: size,
  height: size,
  "aria-hidden": "true"
}, /*#__PURE__*/React.createElement("path", {
  fill: "currentColor",
  d: "M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z"
}));
const STATUS_LABEL = {
  solved: 'Solved',
  needs_answer: 'Needs answer',
  decision_made: 'Decision'
};
const STATUS_CHIP = {
  solved: 'chip-solved',
  needs_answer: 'chip-needs',
  decision_made: 'chip-decision_made'
};

/**
 * ThreadRow — one topic in the Council Inbox. The author is a prominent byline:
 * a gilt-ringed avatar (with presence) beside a name + tier pill + regard
 * (commends earned). Below sits the status chips, the topic title, an optional
 * snippet, and an activity meta line. In compact ("Watch") density the byline
 * folds into the meta line. Put inside a <ul className="thread-list">.
 */
function ThreadRow({
  title,
  href = '#',
  author,
  authorSeed,
  authorTier,
  // 'Member' | 'Veteran' | 'Loremaster' | 'Legend'
  authorRep,
  // the author's regard (commends earned) — shown in the byline
  authorHref,
  presence,
  // true | 'online' | 'away' | 'offline'
  giltAuthor = false,
  board,
  boardName,
  showBoard = false,
  replies = 0,
  time,
  snippet,
  commends,
  // commends on the topic (activity meta)
  status = 'open',
  pinned = false,
  locked = false,
  unread = false,
  starred = false,
  active = false,
  showAvatar = true,
  className = '',
  ...rest
}) {
  const statusSlug = status && status !== 'open' ? status : null;
  const cls = ['thread-row', unread ? 'thread-unread' : '', pinned ? 'thread-pinned' : '', locked ? 'thread-locked' : '', statusSlug ? `thread-status-${statusSlug}` : '', active ? 'is-active' : '', className].filter(Boolean).join(' ');
  const seed = authorSeed || author;
  const mono = /*#__PURE__*/React.createElement("span", {
    className: ['monogram', monoClass(seed), giltAuthor ? 'monogram-gilt' : ''].filter(Boolean).join(' '),
    "aria-hidden": "true"
  }, initials(author));
  const dotColor = presence === 'away' ? 'var(--amber)' : presence === 'offline' ? 'var(--ink-300)' : 'var(--presence)';
  const avatar = presence ? /*#__PURE__*/React.createElement("span", {
    className: "avatar-wrap"
  }, mono, /*#__PURE__*/React.createElement("span", {
    className: "presence-dot",
    style: {
      background: dotColor
    },
    "aria-hidden": "true"
  })) : mono;
  const AuthorName = authorHref ? 'a' : 'span';
  return /*#__PURE__*/React.createElement("li", _extends({
    className: cls
  }, rest), unread ? /*#__PURE__*/React.createElement("span", {
    className: "unread-dot",
    title: "Unread",
    "aria-label": "Unread"
  }) : null, showAvatar ? avatar : null, /*#__PURE__*/React.createElement("div", {
    className: "thread-row-main"
  }, author ? /*#__PURE__*/React.createElement("div", {
    className: "thread-byline"
  }, /*#__PURE__*/React.createElement(AuthorName, {
    className: "thread-author",
    href: authorHref || undefined
  }, author), authorTier ? /*#__PURE__*/React.createElement("span", {
    className: `tier ${tierClass(authorTier)}`
  }, authorTier) : null, authorRep != null ? /*#__PURE__*/React.createElement("span", {
    className: "regard"
  }, /*#__PURE__*/React.createElement(Star, null), " ", authorRep) : null) : null, /*#__PURE__*/React.createElement("div", {
    className: "thread-row-chips"
  }, pinned ? /*#__PURE__*/React.createElement("span", {
    className: "chip chip-pinned"
  }, "Pinned") : null, statusSlug ? /*#__PURE__*/React.createElement("span", {
    className: `chip ${STATUS_CHIP[statusSlug] || ''}`
  }, STATUS_LABEL[statusSlug] || statusSlug) : null, locked ? /*#__PURE__*/React.createElement("span", {
    className: "chip chip-locked"
  }, "Locked") : null), /*#__PURE__*/React.createElement("a", {
    className: "thread-title",
    href: href
  }, title), snippet ? /*#__PURE__*/React.createElement("p", {
    className: "thread-snippet"
  }, snippet) : null, /*#__PURE__*/React.createElement("span", {
    className: "thread-meta"
  }, showBoard && board ? /*#__PURE__*/React.createElement("a", {
    className: "thread-board",
    href: `/c/${board}`
  }, /*#__PURE__*/React.createElement("span", {
    className: "hash"
  }, "#"), boardName || board) : null, author ? /*#__PURE__*/React.createElement("span", {
    className: "thread-meta-author"
  }, author) : null, /*#__PURE__*/React.createElement("span", null, replies, " ", replies === 1 ? 'reply' : 'replies'), time ? /*#__PURE__*/React.createElement("span", null, time) : null, commends != null ? /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      gap: 4,
      color: 'var(--star)'
    }
  }, /*#__PURE__*/React.createElement(Star, null), /*#__PURE__*/React.createElement("span", {
    className: "reaction-n",
    style: {
      color: 'var(--text-faint)'
    }
  }, commends)) : null)), starred ? /*#__PURE__*/React.createElement("span", {
    className: "thread-star",
    title: "Starred",
    "aria-label": "Starred"
  }, "\u2605") : null);
}
Object.assign(__ds_scope, { ThreadRow });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forum/ThreadRow.jsx", error: String((e && e.message) || e) }); }

// components/identity/Monogram.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
// Deterministic avatar colour from a seed (username), 0–9 → .mono-0..9.
function monogramClass(seed) {
  const s = String(seed || '');
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h + s.charCodeAt(i)) % 10;
  return `mono-${h}`;
}

// 1–2 letter initials: first letters of the first two words, else first two
// letters of a single word. Uppercased.
function monogramInitials(label) {
  const parts = String(label || '').trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[1][0]).toUpperCase();
}
const SIZE_CLASS = {
  sm: 'monogram-sm',
  md: '',
  lg: 'monogram-lg',
  xl: 'monogram-xl'
};

/**
 * Monogram — the brand avatar. A tinted ground + dark ink initials, with the
 * colour chosen deterministically from `username`. Add `gilt` for "precious"
 * avatars (OP, accepted answer, profile, leaderboard top-3). Pass `presence`
 * for a leaf/away/offline dot, or `src` for a real image.
 */
function Monogram({
  name,
  username,
  size = 'md',
  gilt = false,
  presence,
  // true | 'online' | 'away' | 'offline'
  src,
  className = '',
  ...rest
}) {
  const sizeCls = SIZE_CLASS[size] || '';
  const seed = username || name;
  const avatar = src ? /*#__PURE__*/React.createElement("img", _extends({
    className: ['monogram', 'avatar-img', sizeCls, gilt ? 'monogram-gilt' : '', className].filter(Boolean).join(' '),
    src: src,
    alt: "",
    "aria-hidden": "true"
  }, rest)) : /*#__PURE__*/React.createElement("span", _extends({
    className: ['monogram', monogramClass(seed), sizeCls, gilt ? 'monogram-gilt' : '', className].filter(Boolean).join(' '),
    "aria-hidden": "true"
  }, rest), monogramInitials(name || username));
  if (presence) {
    const dotColor = presence === 'away' ? 'var(--amber)' : presence === 'offline' ? 'var(--ink-300)' : 'var(--presence)';
    return /*#__PURE__*/React.createElement("span", {
      className: "avatar-wrap"
    }, avatar, /*#__PURE__*/React.createElement("span", {
      className: "presence-dot",
      style: {
        background: dotColor
      },
      "aria-hidden": "true"
    }));
  }
  return avatar;
}
Object.assign(__ds_scope, { Monogram });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/identity/Monogram.jsx", error: String((e && e.message) || e) }); }

// components/identity/Reaction.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Reaction — a lightweight appreciation chip that reads "✦ Name · count".
 * The Imladris set: Commend (the gold star, default), Kindled (flame),
 * Seconded (check), Illuminating (sparkle) — pass the glyph via `icon` for the
 * non-Commend ones. `active` = the viewer reacted (warms to gold).
 */
function Reaction({
  name = 'Commend',
  count,
  active = false,
  icon,
  onClick,
  className = '',
  ...rest
}) {
  const cls = ['reaction', active ? 'reaction-on' : '', className].filter(Boolean).join(' ');
  const glyph = icon != null ? icon : /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 100 100",
    width: "12",
    height: "12",
    "aria-hidden": "true",
    style: {
      display: 'inline-block',
      flex: '0 0 auto'
    }
  }, /*#__PURE__*/React.createElement("path", {
    fill: "currentColor",
    d: "M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z"
  }));
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    className: cls,
    "aria-pressed": active,
    onClick: onClick
  }, rest), /*#__PURE__*/React.createElement("span", {
    className: "reaction-glyph",
    style: {
      display: 'inline-flex'
    }
  }, glyph), /*#__PURE__*/React.createElement("span", null, name), count != null ? /*#__PURE__*/React.createElement("span", {
    className: "reaction-n"
  }, count) : null);
}
Object.assign(__ds_scope, { Reaction });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/identity/Reaction.jsx", error: String((e && e.message) || e) }); }

// components/identity/StarButton.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * StarButton — the "Star this topic" pill (a personal bookmark). Off = quiet
 * parchment outline; on = warm gold. Uses the four-point commend star glyph.
 */
function StarButton({
  active = false,
  label,
  count,
  onClick,
  className = '',
  ...rest
}) {
  const cls = ['star-btn', active ? 'star-on' : '', className].filter(Boolean).join(' ');
  const text = label != null ? label : active ? 'Starred' : 'Star';
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    className: cls,
    "aria-pressed": active,
    onClick: onClick
  }, rest), /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 100 100",
    width: "13",
    height: "13",
    "aria-hidden": "true",
    style: {
      display: 'inline-block',
      flex: '0 0 auto',
      color: 'var(--star)'
    }
  }, /*#__PURE__*/React.createElement("path", {
    fill: "currentColor",
    d: "M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z"
  })), /*#__PURE__*/React.createElement("span", null, text), count != null ? /*#__PURE__*/React.createElement("span", {
    className: "reaction-n",
    style: {
      marginLeft: 2
    }
  }, count) : null);
}
Object.assign(__ds_scope, { StarButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/identity/StarButton.jsx", error: String((e && e.message) || e) }); }

// feature-ui/organize/organize.jsx
try { (() => {
/* Organizing the rail — board_folders · saved_feeds · bookmark_folders.
   Imladris feature-activation design. Loaded via Babel from index.html. */
const {
  useState,
  useEffect,
  useRef
} = React;
const DS = window.ImladrisDesignSystem_c3e027;
const {
  Button
} = DS;

/* ── icons ──────────────────────────────────────────────────── */
const stroke = (d, s) => /*#__PURE__*/React.createElement("svg", {
  viewBox: "0 0 24 24",
  width: s || 14,
  height: s || 14,
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round",
  strokeLinejoin: "round",
  "aria-hidden": "true"
}, d);
const Chevron = ({
  s
}) => stroke(/*#__PURE__*/React.createElement("path", {
  d: "M6 9l6 6 6-6"
}), s);
const Plus = ({
  s
}) => stroke(/*#__PURE__*/React.createElement("path", {
  d: "M12 5v14M5 12h14"
}), s);
const XIcon = ({
  s
}) => stroke(/*#__PURE__*/React.createElement("path", {
  d: "M18 6 6 18M6 6l12 12"
}), s);
const Check = ({
  s
}) => stroke(/*#__PURE__*/React.createElement("path", {
  d: "M20 6L9 17l-5-5"
}), s);
const Grip = () => /*#__PURE__*/React.createElement("svg", {
  viewBox: "0 0 16 16",
  width: "13",
  height: "13",
  fill: "currentColor",
  "aria-hidden": "true"
}, /*#__PURE__*/React.createElement("circle", {
  cx: "5",
  cy: "4",
  r: "1.25"
}), /*#__PURE__*/React.createElement("circle", {
  cx: "11",
  cy: "4",
  r: "1.25"
}), /*#__PURE__*/React.createElement("circle", {
  cx: "5",
  cy: "8",
  r: "1.25"
}), /*#__PURE__*/React.createElement("circle", {
  cx: "11",
  cy: "8",
  r: "1.25"
}), /*#__PURE__*/React.createElement("circle", {
  cx: "5",
  cy: "12",
  r: "1.25"
}), /*#__PURE__*/React.createElement("circle", {
  cx: "11",
  cy: "12",
  r: "1.25"
}));
const FeedGlyph = ({
  s
}) => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("path", {
  d: "M4 11a9 9 0 0 1 9 9"
}), /*#__PURE__*/React.createElement("path", {
  d: "M4 4a16 16 0 0 1 16 16"
}), /*#__PURE__*/React.createElement("circle", {
  cx: "5",
  cy: "19",
  r: "1.4",
  fill: "currentColor",
  stroke: "none"
})), s);
const FilterGlyph = ({
  s
}) => stroke(/*#__PURE__*/React.createElement("path", {
  d: "M3 5h18l-7 8v6l-4-2v-4z"
}), s);
const FolderGlyph = ({
  s
}) => stroke(/*#__PURE__*/React.createElement("path", {
  d: "M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"
}), s);
const EightStar = ({
  size
}) => /*#__PURE__*/React.createElement("svg", {
  viewBox: "0 0 100 100",
  width: size,
  height: size,
  fill: "currentColor",
  "aria-hidden": "true"
}, /*#__PURE__*/React.createElement("path", {
  d: "M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z"
}));
const SmallStar = ({
  s
}) => /*#__PURE__*/React.createElement("svg", {
  viewBox: "0 0 100 100",
  width: s || 13,
  height: s || 13,
  fill: "currentColor",
  "aria-hidden": "true"
}, /*#__PURE__*/React.createElement("path", {
  d: "M50 8 61 39 94 39 67 59 78 92 50 72 22 92 33 59 6 39 39 39Z"
}));
const Leaf = ({
  s
}) => stroke(/*#__PURE__*/React.createElement("path", {
  d: "M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"
}), s);
const FEED_COLORS = ['var(--gold-500)', 'var(--accent-2)', 'var(--success)', 'var(--info)', 'var(--violet-500, #7c6bb0)'];

/* dimmed canvas placeholder so the rail reads as part of the app */
function Canvas({
  children
}) {
  return /*#__PURE__*/React.createElement("div", {
    className: "shell-canvas"
  }, children, /*#__PURE__*/React.createElement("div", {
    className: "canvas-skeleton",
    "aria-hidden": "true"
  }, [88, 72, 64].map((w, i) => /*#__PURE__*/React.createElement("div", {
    className: "sk-row",
    key: i
  }, /*#__PURE__*/React.createElement("div", {
    className: "sk-av"
  }), /*#__PURE__*/React.createElement("div", {
    className: "sk-lines"
  }, /*#__PURE__*/React.createElement("div", {
    className: "sk-line",
    style: {
      width: '46%'
    }
  }), /*#__PURE__*/React.createElement("div", {
    className: "sk-line",
    style: {
      width: w + '%'
    }
  }), /*#__PURE__*/React.createElement("div", {
    className: "sk-line",
    style: {
      width: w - 18 + '%'
    }
  }))))));
}

/* ════════════════════════════════════════════════════════════
   01 · board_folders — collapsible, reorderable board folders
   ════════════════════════════════════════════════════════════ */
const SEED_FOLDERS = [{
  id: 'commons',
  name: 'The Commons',
  collapsed: false,
  boards: [{
    slug: 'announcements',
    name: 'announcements',
    count: 12
  }, {
    slug: 'introductions',
    name: 'introductions',
    count: 31
  }, {
    slug: 'the-valley',
    name: 'the-valley',
    count: 88
  }]
}, {
  id: 'vilya',
  name: 'Vilya · Expose',
  collapsed: false,
  boards: [{
    slug: 'interpretability',
    name: 'interpretability',
    count: 47
  }, {
    slug: 'evaluations',
    name: 'evaluations',
    count: 63
  }, {
    slug: 'capability-disclosure',
    name: 'capability-disclosure',
    count: 22
  }, {
    slug: 'audit-trails',
    name: 'audit-trails',
    count: 39
  }]
}];
function RailBoards() {
  const [folders, setFolders] = useState(SEED_FOLDERS);
  const [active, setActive] = useState('evaluations');
  const [organizing, setOrganizing] = useState(false);
  const seq = useRef(1);
  const toggle = id => setFolders(fs => fs.map(f => f.id === id ? {
    ...f,
    collapsed: !f.collapsed
  } : f));
  const rename = (id, name) => setFolders(fs => fs.map(f => f.id === id ? {
    ...f,
    name
  } : f));
  const removeFolder = id => setFolders(fs => fs.filter(f => f.id !== id));
  const addFolder = () => setFolders(fs => [...fs, {
    id: 'new' + seq.current++,
    name: 'New folder',
    collapsed: false,
    boards: []
  }]);
  return /*#__PURE__*/React.createElement("div", {
    className: "shell"
  }, /*#__PURE__*/React.createElement("nav", {
    className: "rail",
    "aria-label": "Boards"
  }, /*#__PURE__*/React.createElement("div", {
    className: "rail-head"
  }, /*#__PURE__*/React.createElement("span", {
    className: "rail-head-title"
  }, "Boards"), /*#__PURE__*/React.createElement("button", {
    className: 'rail-organize' + (organizing ? ' is-on' : ''),
    onClick: () => setOrganizing(v => !v)
  }, organizing ? 'Done' : 'Organize')), folders.map(f => /*#__PURE__*/React.createElement("div", {
    className: 'folder' + (f.collapsed ? ' is-collapsed' : ''),
    key: f.id
  }, /*#__PURE__*/React.createElement("div", {
    className: "folder-head",
    style: {
      cursor: organizing ? 'default' : 'pointer'
    }
  }, organizing ? /*#__PURE__*/React.createElement("span", {
    className: "folder-grip",
    title: "Drag to reorder"
  }, /*#__PURE__*/React.createElement(Grip, null)) : /*#__PURE__*/React.createElement("button", {
    className: "folder-chev",
    onClick: () => toggle(f.id),
    "aria-label": f.collapsed ? 'Expand' : 'Collapse',
    "aria-expanded": !f.collapsed,
    style: {
      background: 'none',
      border: 0,
      padding: 0,
      cursor: 'pointer'
    }
  }, /*#__PURE__*/React.createElement(Chevron, {
    s: 15
  })), organizing ? /*#__PURE__*/React.createElement("span", {
    className: "folder-name"
  }, /*#__PURE__*/React.createElement("input", {
    value: f.name,
    onChange: e => rename(f.id, e.target.value),
    "aria-label": "Folder name"
  })) : /*#__PURE__*/React.createElement("button", {
    className: "folder-name",
    onClick: () => toggle(f.id),
    style: {
      background: 'none',
      border: 0,
      padding: 0,
      textAlign: 'left',
      cursor: 'pointer',
      color: 'inherit',
      letterSpacing: 'inherit',
      textTransform: 'inherit'
    }
  }, f.name), organizing ? /*#__PURE__*/React.createElement("button", {
    className: "folder-chev",
    onClick: () => removeFolder(f.id),
    "aria-label": "Remove folder",
    style: {
      background: 'none',
      border: 0,
      padding: 0,
      cursor: 'pointer',
      color: 'var(--text-faint)'
    }
  }, /*#__PURE__*/React.createElement(XIcon, {
    s: 13
  })) : /*#__PURE__*/React.createElement("span", {
    className: "folder-count"
  }, f.boards.length)), /*#__PURE__*/React.createElement("div", {
    className: "folder-body"
  }, /*#__PURE__*/React.createElement("div", {
    className: "folder-body-inner"
  }, /*#__PURE__*/React.createElement("ul", {
    className: "nav-boards"
  }, f.boards.map(b => /*#__PURE__*/React.createElement("li", {
    key: b.slug
  }, /*#__PURE__*/React.createElement("button", {
    className: 'board-btn' + (active === b.slug ? ' active' : ''),
    onClick: () => setActive(b.slug)
  }, organizing ? /*#__PURE__*/React.createElement("span", {
    className: "board-grip"
  }, /*#__PURE__*/React.createElement(Grip, null)) : /*#__PURE__*/React.createElement("span", {
    className: "hash"
  }, "#"), /*#__PURE__*/React.createElement("span", {
    className: "board-name"
  }, b.name), /*#__PURE__*/React.createElement("span", {
    className: "board-count"
  }, b.count)))), f.boards.length === 0 ? /*#__PURE__*/React.createElement("li", {
    style: {
      padding: '4px 11px',
      fontStyle: 'italic',
      color: 'var(--text-faint)',
      fontSize: 13
    }
  }, "Drag boards here") : null))))), organizing ? /*#__PURE__*/React.createElement("button", {
    className: "rail-add",
    onClick: addFolder
  }, /*#__PURE__*/React.createElement(Plus, {
    s: 12
  }), " New folder") : null), /*#__PURE__*/React.createElement(Canvas, null, /*#__PURE__*/React.createElement("div", {
    className: "canvas-filterbar"
  }, /*#__PURE__*/React.createElement("span", {
    className: "filter-label"
  }, "Viewing"), /*#__PURE__*/React.createElement("span", {
    className: "filter-chip"
  }, /*#__PURE__*/React.createElement("span", {
    className: "hash",
    style: {
      color: 'var(--brand)'
    }
  }, "#"), active))));
}

/* ════════════════════════════════════════════════════════════
   02 · saved_feeds — save a filter as a named feed in the rail
   ════════════════════════════════════════════════════════════ */
const SEED_FEEDS = [{
  id: 'unsolved',
  name: 'Unsolved in evals',
  color: 'var(--gold-500)',
  count: 7
}, {
  id: 'mine',
  name: 'Mentions of me',
  color: 'var(--accent-2)',
  count: 2
}, {
  id: 'decisions',
  name: 'Decisions log',
  color: 'var(--success)',
  count: 4
}];
const DRAFT_FILTER = [{
  label: 'Unsolved',
  kind: 'status'
}, {
  label: '#interpretability',
  kind: 'board'
}, {
  label: 'last 7 days',
  kind: 'time'
}];
function SaveFeed() {
  const [feeds, setFeeds] = useState(SEED_FEEDS);
  const [active, setActive] = useState('unsolved');
  const [saving, setSaving] = useState(false);
  const [name, setName] = useState('Open interpretability');
  const [color, setColor] = useState(FEED_COLORS[3]);
  const [justSaved, setJustSaved] = useState(null);
  const save = () => {
    const id = 'f' + Date.now();
    setFeeds(fs => [...fs, {
      id,
      name: name.trim() || 'New feed',
      color,
      count: 5,
      fresh: true
    }]);
    setActive(id);
    setJustSaved(id);
    setSaving(false);
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "shell"
  }, /*#__PURE__*/React.createElement("nav", {
    className: "rail",
    "aria-label": "Feeds and boards"
  }, /*#__PURE__*/React.createElement("div", {
    className: "rail-section-label"
  }, /*#__PURE__*/React.createElement("span", {
    className: "leaf"
  }, /*#__PURE__*/React.createElement(FeedGlyph, {
    s: 13
  })), " Saved feeds"), feeds.map(f => /*#__PURE__*/React.createElement("button", {
    key: f.id,
    className: 'feed-btn' + (active === f.id ? ' active' : '') + (f.fresh ? ' feed-new' : ''),
    onClick: () => setActive(f.id)
  }, /*#__PURE__*/React.createElement("span", {
    className: "feed-dot",
    style: {
      background: f.color
    }
  }), /*#__PURE__*/React.createElement("span", {
    className: "feed-name"
  }, f.name), /*#__PURE__*/React.createElement("span", {
    className: "feed-count"
  }, f.count))), /*#__PURE__*/React.createElement("div", {
    className: "rail-divider"
  }), /*#__PURE__*/React.createElement("div", {
    className: "rail-section-label"
  }, "Boards"), ['announcements', 'interpretability', 'evaluations', 'audit-trails'].map(b => /*#__PURE__*/React.createElement("button", {
    key: b,
    className: "board-btn",
    onClick: () => setActive(null)
  }, /*#__PURE__*/React.createElement("span", {
    className: "hash"
  }, "#"), /*#__PURE__*/React.createElement("span", {
    className: "board-name"
  }, b)))), /*#__PURE__*/React.createElement(Canvas, null, /*#__PURE__*/React.createElement("div", {
    className: "canvas-filterbar"
  }, /*#__PURE__*/React.createElement("span", {
    className: "filter-label"
  }, "Filter"), DRAFT_FILTER.map(c => /*#__PURE__*/React.createElement("span", {
    className: "filter-chip",
    key: c.label
  }, c.label, /*#__PURE__*/React.createElement("span", {
    className: "x"
  }, /*#__PURE__*/React.createElement(XIcon, {
    s: 12
  })))), !saving && !justSaved ? /*#__PURE__*/React.createElement(Button, {
    variant: "outline",
    size: "sm",
    onClick: () => setSaving(true)
  }, "Save as feed") : null, justSaved ? /*#__PURE__*/React.createElement("span", {
    className: "filter-chip",
    style: {
      color: 'var(--on-done)',
      background: 'var(--surface-done)',
      borderColor: 'var(--green-200)'
    }
  }, /*#__PURE__*/React.createElement(Check, {
    s: 12
  }), " Saved to rail") : null), saving ? /*#__PURE__*/React.createElement("div", {
    className: "savefeed"
  }, /*#__PURE__*/React.createElement("span", {
    className: "savefeed-icon"
  }, /*#__PURE__*/React.createElement(FeedGlyph, {
    s: 15
  })), /*#__PURE__*/React.createElement("div", {
    className: "savefeed-fields"
  }, /*#__PURE__*/React.createElement("input", {
    className: "ds-input",
    value: name,
    onChange: e => setName(e.target.value),
    placeholder: "Name this feed",
    autoFocus: true,
    style: {
      flex: 1,
      minWidth: 140,
      font: 'inherit',
      fontFamily: 'var(--font-body), serif',
      fontSize: 15,
      color: 'var(--text-strong)',
      background: 'var(--surface-raised)',
      border: '1px solid var(--border-hair)',
      borderRadius: 'var(--radius-md)',
      padding: '8px 12px'
    }
  }), /*#__PURE__*/React.createElement("span", {
    className: "savefeed-color",
    role: "radiogroup",
    "aria-label": "Feed colour"
  }, FEED_COLORS.map(c => /*#__PURE__*/React.createElement("button", {
    key: c,
    className: 'swatch' + (color === c ? ' is-on' : ''),
    style: {
      background: c
    },
    onClick: () => setColor(c),
    "aria-label": "colour",
    "aria-pressed": color === c
  })))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8
    }
  }, /*#__PURE__*/React.createElement(Button, {
    variant: "ghost",
    size: "sm",
    onClick: () => setSaving(false)
  }, "Cancel"), /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    size: "sm",
    onClick: save
  }, "Save feed"))) : null));
}
})(); } catch (e) { __ds_ns.__errors.push({ path: "feature-ui/organize/organize.jsx", error: String((e && e.message) || e) }); }

// feature-ui/shared/chrome.jsx
try { (() => {
/* ──────────────────────────────────────────────────────────────────────────
   Feature-activation pages — shared chrome (React, via Babel)
   Icons, the topbar, the segmented control, a theme hook, and the page
   scaffold. Exported to window so each themed page's own babel script can use
   them (separate <script type="text/babel"> blocks don't share scope).
   ────────────────────────────────────────────────────────────────────────── */
(function () {
  const {
    useState,
    useEffect
  } = React;

  /* stroke icon helper — Lucide weight */
  const stroke = (d, s) => /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 24 24",
    width: s || 16,
    height: s || 16,
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2",
    strokeLinecap: "round",
    strokeLinejoin: "round",
    "aria-hidden": "true"
  }, d);
  const Icons = {
    check: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M20 6L9 17l-5-5"
    }), s),
    plus: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M12 5v14M5 12h14"
    }), s),
    x: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M18 6 6 18M6 6l12 12"
    }), s),
    chevron: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M6 9l6 6 6-6"
    }), s),
    chevronR: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M9 6l6 6-6 6"
    }), s),
    clock: s => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "12",
      r: "9"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M12 7v5l3 2"
    })), s),
    lock: s => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("rect", {
      x: "4",
      y: "11",
      width: "16",
      height: "9",
      rx: "2"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M8 11V8a4 4 0 0 1 8 0v3"
    })), s),
    folder: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"
    }), s),
    folderOpen: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2M3 7v11a2 2 0 0 0 2 2h13.5a2 2 0 0 0 1.9-1.4L22 11H6.5a2 2 0 0 0-1.9 1.4z"
    }), s),
    bookmark: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M6 4h12a1 1 0 0 1 1 1v15l-7-4-7 4V5a1 1 0 0 1 1-1z"
    }), s),
    star: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M12 3l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 18.8 6.1 21l1.2-6.5L2.5 9.9 9 9z"
    }), s),
    filter: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M4 5h16l-6 7v6l-4 2v-8z"
    }), s),
    grip: s => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("circle", {
      cx: "9",
      cy: "6",
      r: "0.6"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "15",
      cy: "6",
      r: "0.6"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "9",
      cy: "12",
      r: "0.6"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "15",
      cy: "12",
      r: "0.6"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "9",
      cy: "18",
      r: "0.6"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "15",
      cy: "18",
      r: "0.6"
    })), s),
    dots: s => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("circle", {
      cx: "5",
      cy: "12",
      r: "0.7"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "12",
      r: "0.7"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "19",
      cy: "12",
      r: "0.7"
    })), s),
    hash: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M9 3 7 21M17 3l-2 18M4 8.5h16M3 15.5h16"
    }), s),
    pencil: s => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("path", {
      d: "M12 20h9"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"
    })), s),
    pin: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M9 3h6l-1 7 3 3v2H7v-2l3-3z M12 15v6"
    }), s),
    arrowRight: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M5 12h14M13 6l6 6-6 6"
    }), s),
    search: s => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("circle", {
      cx: "11",
      cy: "11",
      r: "7"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M21 21l-4.3-4.3"
    })), s),
    history: s => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("path", {
      d: "M3 12a9 9 0 1 0 3-6.7L3 8"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M3 4v4h4"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M12 8v4l3 2"
    })), s),
    link: s => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("path", {
      d: "M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.5 1.5"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.5-1.5"
    })), s),
    quote: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M7 7H4v6h5V9c0 2-1 3-3 3M19 7h-3v6h5V9c0 2-1 3-3 3"
    }), s),
    split: s => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("path", {
      d: "M6 3v6a3 3 0 0 0 3 3h6a3 3 0 0 1 3 3v6"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M3 6l3-3 3 3"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M15 21l3-3 3 3",
      transform: "translate(0 -3)"
    })), s),
    merge: s => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("path", {
      d: "M6 21v-6a3 3 0 0 1 3-3h6a3 3 0 0 0 3-3V3"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M3 18l3 3 3-3"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M15 6l3-3 3 3"
    })), s),
    snooze: s => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "13",
      r: "8"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M9 10h6l-6 6h6",
      transform: "scale(0.6) translate(8 8.5)"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M5 4l3-2M19 4l-3-2"
    })), s),
    user: s => stroke(/*#__PURE__*/React.createElement("g", null, /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "8",
      r: "4"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M4 21a8 8 0 0 1 16 0"
    })), s),
    escalate: s => stroke(/*#__PURE__*/React.createElement("path", {
      d: "M12 19V5M5 12l7-7 7 7"
    }), s)
  };

  /* brand stars */
  const EightStar = ({
    size
  }) => /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 100 100",
    width: size,
    height: size,
    fill: "currentColor",
    "aria-hidden": "true"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z"
  }));
  const CommendStar = ({
    size
  }) => /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 100 100",
    width: size || 13,
    height: size || 13,
    fill: "currentColor",
    "aria-hidden": "true"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z"
  }));
  function Segmented({
    value,
    onChange,
    options
  }) {
    return /*#__PURE__*/React.createElement("div", {
      className: "segmented",
      role: "group"
    }, options.map(o => {
      const val = typeof o === 'string' ? o : o.value;
      const label = typeof o === 'string' ? o : o.label;
      return /*#__PURE__*/React.createElement("button", {
        key: val,
        className: value === val ? 'is-on' : '',
        "aria-pressed": value === val,
        onClick: () => onChange(val)
      }, label);
    }));
  }
  function useTheme() {
    const [theme, setTheme] = useState('light');
    useEffect(() => {
      if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');else document.documentElement.removeAttribute('data-theme');
    }, [theme]);
    return [theme, setTheme];
  }

  /* the page topbar with flag chips + theme toggle */
  function Topbar({
    eyebrow,
    flags,
    theme,
    setTheme
  }) {
    return /*#__PURE__*/React.createElement("div", {
      className: "fa-topbar"
    }, /*#__PURE__*/React.createElement("span", {
      className: "fa-star"
    }, /*#__PURE__*/React.createElement(EightStar, {
      size: 22
    })), /*#__PURE__*/React.createElement("span", {
      className: "fa-brand"
    }, "RetroBoards"), /*#__PURE__*/React.createElement("span", {
      className: "fa-sep"
    }), /*#__PURE__*/React.createElement("span", {
      className: "fa-eyebrow"
    }, eyebrow || 'Feature activation'), /*#__PURE__*/React.createElement("span", {
      className: "fa-spacer"
    }), /*#__PURE__*/React.createElement("span", {
      className: "fa-flag-row"
    }, (flags || []).map(f => {
      const name = typeof f === 'string' ? f : f.name;
      const blocked = typeof f === 'object' && f.blocked;
      return /*#__PURE__*/React.createElement("span", {
        key: name,
        className: 'fa-flag' + (blocked ? ' is-blocked' : '')
      }, "flag: ", name);
    })), /*#__PURE__*/React.createElement(Segmented, {
      value: theme,
      onChange: setTheme,
      options: [{
        value: 'light',
        label: 'Parchment'
      }, {
        value: 'dark',
        label: 'Twilight'
      }]
    }));
  }

  /* lede block — eyebrow, title, body (children), optional feature ledger */
  function Lede({
    eyebrow,
    title,
    children,
    ledger
  }) {
    return /*#__PURE__*/React.createElement("div", {
      className: "fa-lede"
    }, /*#__PURE__*/React.createElement("span", {
      className: "fa-lede-star"
    }, /*#__PURE__*/React.createElement(EightStar, {
      size: 150
    })), /*#__PURE__*/React.createElement("p", {
      className: "fa-lede-eyebrow"
    }, eyebrow), /*#__PURE__*/React.createElement("h1", {
      className: "fa-lede-title"
    }, title), /*#__PURE__*/React.createElement("p", {
      className: "fa-lede-body"
    }, children), ledger ? /*#__PURE__*/React.createElement("div", {
      className: "fa-ledger"
    }, ledger.map(l => /*#__PURE__*/React.createElement("span", {
      className: "fa-ledger-item",
      key: l.flag
    }, /*#__PURE__*/React.createElement("span", {
      className: "fa-ledger-dot"
    }), /*#__PURE__*/React.createElement("span", {
      className: "fa-ledger-name"
    }, l.flag), /*#__PURE__*/React.createElement("span", {
      className: "fa-ledger-desc"
    }, l.desc)))) : null);
  }
  function Spec({
    num,
    label,
    title,
    note,
    children
  }) {
    return /*#__PURE__*/React.createElement("section", {
      className: "spec"
    }, /*#__PURE__*/React.createElement("div", {
      className: "spec-kicker"
    }, /*#__PURE__*/React.createElement("span", {
      className: "spec-num"
    }, num), " ", /*#__PURE__*/React.createElement("span", {
      className: "spec-label"
    }, label)), /*#__PURE__*/React.createElement("h2", {
      className: "spec-title"
    }, title), /*#__PURE__*/React.createElement("p", {
      className: "spec-note"
    }, note), /*#__PURE__*/React.createElement("div", {
      className: "spec-frame"
    }, children));
  }
  function FrameBar({
    route
  }) {
    return /*#__PURE__*/React.createElement("div", {
      className: "frame-bar"
    }, /*#__PURE__*/React.createElement("span", {
      className: "frame-dots"
    }, /*#__PURE__*/React.createElement("span", {
      className: "frame-dot"
    }), /*#__PURE__*/React.createElement("span", {
      className: "frame-dot"
    }), /*#__PURE__*/React.createElement("span", {
      className: "frame-dot"
    })), /*#__PURE__*/React.createElement("span", {
      className: "frame-route"
    }, route));
  }
  Object.assign(window, {
    FA: {
      Icons,
      stroke,
      EightStar,
      CommendStar,
      Segmented,
      useTheme,
      Topbar,
      Lede,
      Spec,
      FrameBar
    }
  });
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "feature-ui/shared/chrome.jsx", error: String((e && e.message) || e) }); }

// public/assets/app.js
try { (() => {
// RetroBoards — progressive enhancement only. Every flow works without this
// file; it just adds small conveniences on top of the server-rendered HTML.
(function () {
  'use strict';

  // Signal that JS is active so CSS can enable JS-only affordances (e.g. the
  // off-canvas nav drawer) without ever trapping no-JS users behind them.
  document.documentElement.classList.add('has-js');

  // Auto-grow composer textareas as you type.
  function autosize(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
  }
  document.addEventListener('input', function (e) {
    var t = e.target;
    if (t && t.classList && t.classList.contains('composer-input')) {
      autosize(t);
    }
  });

  // Reactions: toggle an EXISTING reaction chip over fetch and update it in
  // place. The "add a reaction" menu uses a normal POST (full reload) so a
  // brand-new chip is server-rendered with a valid CSRF token. Either way the
  // no-JavaScript path is unchanged.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.classList || !form.classList.contains('reaction-form')) {
      return;
    }
    if (form.closest('.reaction-add')) {
      return;
    } // adding a new emoji → normal submit
    if (!window.fetch || !window.FormData) {
      return;
    }
    var btn = form.querySelector('button');
    e.preventDefault();
    var body = new FormData(form);
    body.append('format', 'json');
    fetch(form.action, {
      method: 'POST',
      body: body,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    }).then(function (r) {
      return r.json();
    }).then(function (data) {
      if (!data || !data.ok) {
        form.submit();
        return;
      }
      var emoji = (form.querySelector('input[name=emoji]') || {}).value;
      var n = data.counts && data.counts[emoji] || 0;
      if (n === 0) {
        form.remove();
        return;
      }
      var on = data.state === 'added';
      btn.classList.toggle('reaction-on', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      var ncell = btn.querySelector('.reaction-n');
      if (ncell) {
        ncell.textContent = n;
      }
    }).catch(function () {
      form.submit();
    });
  });

  // Notification bell: short-poll the unread count (DECISIONS §2: short-polling,
  // no WebSockets). The bell is a plain link without JS, so this only decorates.
  var bell = document.querySelector('[data-bell]');
  if (bell && window.fetch) {
    var countEl = bell.querySelector('[data-bell-count]');
    var poll = function () {
      fetch('/notifications/bell?format=json', {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      }).then(function (r) {
        return r.ok ? r.json() : null;
      }).then(function (data) {
        if (!data || !countEl) {
          return;
        }
        if (data.unread > 0) {
          countEl.textContent = data.unread > 99 ? '99+' : data.unread;
          countEl.hidden = false;
        } else {
          countEl.hidden = true;
        }
      }).catch(function () {});
    };
    poll();
    setInterval(poll, 60000); // once a minute is plenty for a forum bell
  }

  // Presence roster: short-poll who's online (P2-11). The server already
  // excludes hidden users, the viewer, and blocked members — the client just
  // renders. The widget stays hidden (no-JS) until there's someone to show.
  var presence = document.querySelector('[data-presence]');
  if (presence && window.fetch) {
    var pList = presence.querySelector('[data-presence-list]');
    var pCount = presence.querySelector('[data-presence-count]');
    var pollPresence = function () {
      fetch('/presence?format=json', {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      }).then(function (r) {
        return r.ok ? r.json() : null;
      }).then(function (data) {
        if (!data || !pList) {
          return;
        }
        if (pCount) {
          pCount.textContent = data.count;
        }
        pList.innerHTML = '';
        (data.online || []).slice(0, 20).forEach(function (u) {
          var li = document.createElement('li');
          var a = document.createElement('a');
          a.href = '/u/' + encodeURIComponent(u.username);
          var dot = document.createElement('span');
          dot.className = 'dot';
          a.appendChild(dot);
          a.appendChild(document.createTextNode(u.display_name || u.username));
          li.appendChild(a);
          pList.appendChild(li);
        });
        presence.hidden = (data.count || 0) === 0;
      }).catch(function () {});
    };
    pollPresence();
    setInterval(pollPresence, 45000);
  }

  // Operator branding preview (P3-07). The saved /brand.css remains the source
  // of truth; this only previews unsaved form values inside the admin card.
  var brandForm = document.querySelector('[data-brand-form]');
  var brandPreview = document.querySelector('[data-brand-preview]');
  if (brandForm && brandPreview) {
    var brandName = brandForm.querySelector('[data-brand-name]');
    var brandPrimary = brandForm.querySelector('[data-brand-primary]');
    var brandAccent = brandForm.querySelector('[data-brand-accent]');
    var brandTheme = brandForm.querySelector('[data-brand-theme]');
    var previewName = brandPreview.querySelector('[data-brand-preview-name]');
    var previewTheme = brandPreview.querySelector('[data-brand-preview-theme]');
    var hex = function (v) {
      return /^#[0-9a-fA-F]{6}$/.test((v || '').trim());
    };
    var rgb = function (v) {
      v = v.replace('#', '');
      return [parseInt(v.slice(0, 2), 16), parseInt(v.slice(2, 4), 16), parseInt(v.slice(4, 6), 16)];
    };
    var lum = function (v) {
      return rgb(v).map(function (n) {
        n = n / 255;
        return n <= 0.03928 ? n / 12.92 : Math.pow((n + 0.055) / 1.055, 2.4);
      }).reduce(function (sum, n, i) {
        return sum + n * [0.2126, 0.7152, 0.0722][i];
      }, 0);
    };
    var contrast = function (a, b) {
      var l1 = lum(a),
        l2 = lum(b),
        hi = Math.max(l1, l2),
        lo = Math.min(l1, l2);
      return (hi + 0.05) / (lo + 0.05);
    };
    var contrastToken = function (v) {
      return contrast(v, '#ffffff') >= contrast(v, '#0f1218') ? '#ffffff' : '#0f1218';
    };
    var updateBrandPreview = function () {
      var primary = brandPrimary && hex(brandPrimary.value) ? brandPrimary.value : '#2f6feb';
      var accent = brandAccent && hex(brandAccent.value) ? brandAccent.value : primary;
      brandPreview.style.setProperty('--preview-accent', primary);
      brandPreview.style.setProperty('--preview-accent-contrast', contrastToken(primary));
      brandPreview.style.setProperty('--preview-accent-2', accent);
      if (previewName && brandName) {
        previewName.textContent = brandName.value || 'Community';
      }
      if (previewTheme && brandTheme) {
        previewTheme.textContent = brandTheme.value.charAt(0).toUpperCase() + brandTheme.value.slice(1);
      }
    };
    brandForm.addEventListener('input', updateBrandPreview);
    brandForm.addEventListener('change', updateBrandPreview);
    updateBrandPreview();
  }

  // Site announcement banner (ADMIN §7.4): a dismissible operator notice. With
  // JS off the server-rendered banner simply stays visible; this only remembers
  // a per-version dismissal in localStorage and hides the bar on later loads.
  var announcement = document.querySelector('[data-announcement]');
  if (announcement && announcement.getAttribute('data-dismissible') === '1') {
    var annVersion = announcement.getAttribute('data-announcement-version') || '0';
    var annKey = 'rb-announcement-dismissed';
    var annDismissed = null;
    try {
      annDismissed = window.localStorage.getItem(annKey);
    } catch (e) {
      annDismissed = null;
    }
    if (annDismissed === annVersion) {
      announcement.hidden = true;
    } else {
      var annBtn = announcement.querySelector('[data-announcement-dismiss]');
      if (annBtn) {
        annBtn.addEventListener('click', function () {
          announcement.hidden = true;
          try {
            window.localStorage.setItem(annKey, annVersion);
          } catch (e) {/* ignore */}
        });
      }
    }
  }

  // Community Inbox — load a topic into the reading pane (enhancement only; with
  // JS off, the thread-title links open each topic as its own page). Short-fetch
  // the thread HTML, lift its #main content into the reading pane, and keep the
  // URL shareable via ?t=<id> + history. Reactions/edit forms inside keep working
  // because their handlers are delegated on document.
  var inbox = document.querySelector('[data-inbox]');
  if (inbox && window.fetch && window.history && window.DOMParser) {
    var reading = inbox.querySelector('[data-inbox-reading]');
    var inboxList = inbox.querySelector('[data-inbox-list]');
    var emptyHtml = reading.innerHTML; // the server-rendered placeholder
    try {
      history.replaceState({}, '', window.location.href);
    } catch (e) {/* ignore */}
    var idOf = function (href) {
      var m = href && href.match(/\/t\/(\d+)/);
      return m ? m[1] : null;
    };
    var markActive = function (href) {
      var rows = inboxList.querySelectorAll('.thread-row');
      for (var i = 0; i < rows.length; i++) {
        var a = rows[i].querySelector('a.thread-title');
        rows[i].classList.toggle('is-active', !!a && a.getAttribute('href') === href);
      }
    };
    var showEmpty = function () {
      reading.innerHTML = emptyHtml;
      reading.scrollTop = 0;
      markActive('');
    };
    var loadThread = function (href, push, focus) {
      reading.setAttribute('aria-busy', 'true');
      fetch(href, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      }).then(function (r) {
        if (r.redirected) {
          window.location.href = r.url;
          return null;
        } // e.g. session expired → /login
        return r.ok ? r.text() : null;
      }).then(function (html) {
        if (html === null) {
          return;
        }
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var main = doc.querySelector('#main');
        if (!main || !main.querySelector('.thread-view, .post-stream, .thread-head')) {
          window.location.href = href;
          return; // not a topic page → real navigation
        }
        reading.innerHTML = main.innerHTML;
        reading.removeAttribute('aria-busy');
        reading.scrollTop = 0;
        markActive(href);
        if (push) {
          var id = idOf(href);
          var url = new URL(window.location.href);
          if (id) {
            url.searchParams.set('t', id);
          }
          history.pushState({
            href: href
          }, '', url.toString());
        }
        if (focus) {
          // move focus, don't announce the whole thread
          var h = reading.querySelector('h1, h2, .thread-head');
          if (h) {
            h.setAttribute('tabindex', '-1');
            h.focus();
          } else {
            reading.focus();
          }
        }
      }).catch(function () {
        window.location.href = href;
      });
    };
    var rowSelector = function (id) {
      return 'a.thread-title[href^="/t/' + id + '-"], a.thread-title[href="/t/' + id + '"]';
    };
    inboxList.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('a.thread-title') : null;
      if (!a || !inboxList.contains(a)) {
        return;
      }
      if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
        return;
      }
      e.preventDefault();
      loadThread(a.getAttribute('href'), true, true);
    });
    window.addEventListener('popstate', function () {
      var id = new URL(window.location.href).searchParams.get('t');
      if (!id) {
        showEmpty();
        return;
      } // Back to the bare /inbox restores the placeholder
      var a = inboxList.querySelector(rowSelector(id));
      if (a) {
        loadThread(a.getAttribute('href'), false, false);
      } else {
        showEmpty();
      }
    });
    var initId = new URL(window.location.href).searchParams.get('t');
    if (initId) {
      var initA = inboxList.querySelector(rowSelector(initId));
      if (initA) {
        loadThread(initA.getAttribute('href'), false, false);
      }
    }
  }

  // Mobile navigation drawer (Phase 4): the sidebar rail slides in over a scrim
  // on small screens. Without JS the rail simply stacks above the content (the
  // server-rendered nav stays reachable); this only adds the off-canvas toggle.
  var navToggle = document.querySelector('[data-nav-toggle]');
  var navScrim = document.querySelector('[data-nav-scrim]');
  if (navToggle) {
    var setNav = function (open) {
      document.body.classList.toggle('nav-open', open);
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      navToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
      if (navScrim) {
        navScrim.hidden = !open;
      }
    };
    navToggle.addEventListener('click', function () {
      setNav(!document.body.classList.contains('nav-open'));
    });
    if (navScrim) {
      navScrim.addEventListener('click', function () {
        setNav(false);
      });
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('nav-open')) {
        setNav(false);
      }
    });
    // Closing the drawer after following a rail link keeps the next page clean.
    var sidebar = document.querySelector('[data-sidebar]');
    if (sidebar) {
      sidebar.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('a')) {
          setNav(false);
        }
      });
    }
  }

  // New-Topic composer becomes a centred modal once JS is present (handoff §5.2).
  // The overlay itself is CSS, gated on .has-js; here we add Esc, scrim-click, and
  // a Cancel button to dismiss it, and focus the title on open. Without JS the
  // native <details> stays an inline expand, so creating a topic never needs script.
  var newTopic = document.querySelector('details.composer-details');
  if (newTopic) {
    var trigger = newTopic.querySelector('summary');
    var closeTopic = function () {
      if (!newTopic.open) {
        return;
      }
      newTopic.open = false;
      if (trigger) {
        trigger.focus();
      } // restore focus to the trigger, not hidden content
    };
    newTopic.addEventListener('toggle', function () {
      if (newTopic.open) {
        var title = newTopic.querySelector('input[name="title"]');
        if (title) {
          title.focus();
        }
      }
    });
    // A click on the backdrop (the open details' ::before fills the viewport and
    // hit-tests to the details element itself) dismisses the modal.
    newTopic.addEventListener('click', function (e) {
      if (e.target === newTopic) {
        closeTopic();
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && newTopic.open) {
        closeTopic();
      }
    });
    var cancel = newTopic.querySelector('[data-close-composer]');
    if (cancel) {
      cancel.addEventListener('click', closeTopic);
    }
  }
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "public/assets/app.js", error: String((e && e.message) || e) }); }

// public/assets/composer.js
try { (() => {
// RetroBoards shared composer — progressive enhancement (P3-02/P3-03/P3-04).
// Everything here is optional: the server-rendered <textarea> posts fine without
// it. When present it adds a Markdown toolbar, a live server-rendered preview,
// a character counter, image paste/drag-drop upload, and local draft autosave.
(function () {
  'use strict';

  if (!window.fetch) {
    return;
  }
  var BODY_MAX = 20000;

  // Composing preferences (P3-01) are stamped on <body> by the layout for
  // signed-in users. Defaults match the schema: enter-to-send off, preview on,
  // smart lists on.
  function composingPrefs() {
    var b = document.body;
    return {
      enterToSend: b.getAttribute('data-enter-to-send') === '1',
      showPreview: b.getAttribute('data-show-preview') !== '0',
      smartLists: b.getAttribute('data-smart-lists') !== '0'
    };
  }
  function draftsEnabled() {
    return document.body.getAttribute('data-drafts') !== '0';
  }
  function tokenField(form) {
    var t = form.querySelector('input[name="_token"]');
    return t ? t.value : '';
  }

  // ---- Markdown toolbar -------------------------------------------------
  function wrapSelection(ta, before, after) {
    var s = ta.selectionStart,
      e = ta.selectionEnd;
    var sel = ta.value.slice(s, e) || '';
    ta.value = ta.value.slice(0, s) + before + sel + after + ta.value.slice(e);
    ta.focus();
    ta.selectionStart = s + before.length;
    ta.selectionEnd = s + before.length + sel.length;
    ta.dispatchEvent(new Event('input', {
      bubbles: true
    }));
  }

  // Sentence-case labels render in the Marcellus toolbar (handoff §5.2). The
  // accessible label stays "Insert {label}" so existing role queries still match.
  var ACTIONS = {
    bold: {
      label: 'Bold',
      before: '**',
      after: '**',
      shortcut: 'b',
      active: 'inline'
    },
    italic: {
      label: 'Italic',
      before: '*',
      after: '*',
      shortcut: 'i',
      active: 'inline'
    },
    strike: {
      label: 'Strike',
      before: '~~',
      after: '~~',
      active: 'inline'
    },
    code: {
      label: 'Code',
      before: '`',
      after: '`',
      shortcut: 'e',
      active: 'inline'
    },
    spoiler: {
      label: 'Spoiler',
      before: '||',
      after: '||',
      active: 'inline'
    },
    quote: {
      label: 'Quote',
      before: '\n> ',
      after: '',
      prefix: '> ',
      active: 'line'
    },
    h2: {
      label: 'Heading',
      before: '\n## ',
      after: '',
      prefix: '## ',
      active: 'line'
    },
    list: {
      label: 'List',
      before: '\n- ',
      after: '',
      prefix: '- ',
      active: 'line'
    },
    codeblock: {
      label: 'Code block',
      before: '\n```\n',
      after: '\n```\n',
      active: 'fence'
    },
    link: {
      label: 'Link',
      before: '[',
      after: '](https://)',
      shortcut: 'k',
      active: 'inline'
    },
    emoji: {
      label: 'Emoji',
      before: ':smile:',
      after: ''
    }
  };
  // A hairline separator follows these keys, grouping the bar as
  // emphasis | block | insert (handoff §5.2).
  var GROUP_BREAKS = {
    spoiler: true,
    codeblock: true
  };
  function applyAction(ta, key) {
    var action = ACTIONS[key];
    if (!action) {
      return false;
    }
    wrapSelection(ta, action.before, action.after);
    return true;
  }
  function currentLine(ta) {
    var v = ta.value,
      pos = ta.selectionStart;
    var lineStart = v.lastIndexOf('\n', pos - 1) + 1;
    var lineEnd = v.indexOf('\n', pos);
    if (lineEnd < 0) {
      lineEnd = v.length;
    }
    return v.slice(lineStart, lineEnd);
  }
  function inFence(ta) {
    var before = ta.value.slice(0, ta.selectionStart);
    return (before.match(/^```/gm) || []).length % 2 === 1;
  }
  function actionActive(ta, action) {
    if (!action.active) {
      return false;
    }
    var s = ta.selectionStart,
      e = ta.selectionEnd,
      v = ta.value;
    if (action.active === 'inline') {
      return s >= action.before.length && v.slice(s - action.before.length, s) === action.before && v.slice(e, e + action.after.length) === action.after;
    }
    if (action.active === 'line') {
      return currentLine(ta).indexOf(action.prefix || '') === 0;
    }
    if (action.active === 'fence') {
      return inFence(ta);
    }
    return false;
  }
  function buildToolbar(ta) {
    var bar = document.createElement('div');
    bar.className = 'composer-toolbar';
    var buttons = [];
    function updateState() {
      buttons.forEach(function (item) {
        item.button.setAttribute('aria-pressed', actionActive(ta, item.action) ? 'true' : 'false');
      });
    }
    Object.keys(ACTIONS).forEach(function (key) {
      var action = ACTIONS[key];
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = action.label;
      b.setAttribute('aria-label', 'Insert ' + action.label);
      if (action.shortcut) {
        b.setAttribute('aria-keyshortcuts', 'Control+' + action.shortcut.toUpperCase() + ' Meta+' + action.shortcut.toUpperCase());
      }
      b.setAttribute('aria-pressed', 'false');
      b.addEventListener('click', function () {
        applyAction(ta, key);
        updateState();
      });
      buttons.push({
        button: b,
        action: action
      });
      bar.appendChild(b);
      if (GROUP_BREAKS[key]) {
        var sep = document.createElement('span');
        sep.className = 'composer-toolbar-sep';
        sep.setAttribute('aria-hidden', 'true');
        bar.appendChild(sep);
      }
    });
    ['input', 'keyup', 'mouseup', 'select'].forEach(function (evt) {
      ta.addEventListener(evt, updateState);
    });
    ta.parentNode.insertBefore(bar, ta);
    updateState();
  }

  // ---- Character counter ------------------------------------------------
  function buildCounter(ta) {
    var c = document.createElement('div');
    c.className = 'composer-count';
    function update() {
      var n = ta.value.length;
      c.textContent = n + ' / ' + BODY_MAX;
      c.classList.toggle('over', n > BODY_MAX);
    }
    ta.addEventListener('input', update);
    update();
    ta.parentNode.appendChild(c);
  }

  // ---- Live preview (same server pipeline) ------------------------------
  function buildPreview(form, ta) {
    var pane = document.createElement('div');
    pane.className = 'composer-preview';
    pane.setAttribute('aria-live', 'polite');
    ta.parentNode.appendChild(pane);
    var timer = null;
    ta.addEventListener('input', function () {
      if (timer) {
        clearTimeout(timer);
      }
      timer = setTimeout(function () {
        var data = new FormData();
        data.append('_token', tokenField(form));
        data.append('body', ta.value);
        fetch('/composer/preview', {
          method: 'POST',
          body: data,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        }).then(function (r) {
          return r.ok ? r.json() : null;
        }).then(function (j) {
          if (j && j.ok) {
            pane.innerHTML = j.html;
          }
        }).catch(function () {});
      }, 350);
    });
  }

  // ---- Idempotency: stamp a fresh token per composer instance -----------
  function stampIdempotency(form) {
    if (form.querySelector('input[name="idempotency_key"]')) {
      return;
    }
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'idempotency_key';
    input.value = 'c-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    form.appendChild(input);
  }

  // ---- Local draft autosave (per user+context, P3-03) -------------------
  function draftUser() {
    return document.body.getAttribute('data-user') || 'anon';
  }
  function draftContext(form) {
    return form.getAttribute('action') || location.pathname;
  }
  function draftKeyFor(who, context) {
    return 'rb-draft:' + who + ':' + context;
  }
  var pendingDraftSubmitKey = 'rb-draft:pending-submits';
  function pendingDraftSubmits() {
    try {
      var raw = sessionStorage.getItem(pendingDraftSubmitKey);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }
  function writePendingDraftSubmits(items) {
    try {
      if (items.length) {
        sessionStorage.setItem(pendingDraftSubmitKey, JSON.stringify(items));
      } else {
        sessionStorage.removeItem(pendingDraftSubmitKey);
      }
    } catch (e) {}
  }
  function rememberPendingDraftSubmit(key, context) {
    var items = pendingDraftSubmits().filter(function (item) {
      return item && item.key !== key;
    });
    items.push({
      key: key,
      context: context,
      at: Date.now()
    });
    writePendingDraftSubmits(items.slice(-20));
  }
  function clearCompletedDraftSubmits() {
    var pending = pendingDraftSubmits();
    if (!pending.length) {
      return;
    }
    var keep = [];
    var forms = document.querySelectorAll('form.composer');
    pending.forEach(function (item) {
      if (!item || !item.key || !item.context) {
        return;
      }
      var samePostUrlWithBody = false;
      for (var i = 0; i < forms.length; i++) {
        var form = forms[i];
        if (form.getAttribute('action') !== item.context) {
          continue;
        }
        var ta = form.querySelector('.composer-input');
        if (location.pathname === item.context && ta && ta.value) {
          samePostUrlWithBody = true;
          break;
        }
      }
      if (samePostUrlWithBody) {
        keep.push(item);
        return;
      }
      try {
        localStorage.removeItem(item.key);
      } catch (e) {}
    });
    writePendingDraftSubmits(keep);
  }
  function migrateAnonDrafts(who) {
    if (who === 'anon') {
      return;
    }
    try {
      var anonPrefix = 'rb-draft:anon:';
      for (var i = localStorage.length - 1; i >= 0; i--) {
        var key = localStorage.key(i);
        if (!key || key.indexOf(anonPrefix) !== 0) {
          continue;
        }
        var context = key.slice(anonPrefix.length);
        var target = draftKeyFor(who, context);
        if (!localStorage.getItem(target)) {
          localStorage.setItem(target, localStorage.getItem(key) || '');
        }
        localStorage.removeItem(key);
      }
    } catch (e) {}
  }
  function buildDiscard(form, ta, key) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'btn btn-secondary btn-small composer-discard';
    b.textContent = 'Discard draft';
    function update() {
      var hasSaved = false;
      try {
        hasSaved = !!localStorage.getItem(key);
      } catch (e) {}
      b.hidden = !hasSaved && !ta.value;
    }
    b.addEventListener('click', function () {
      try {
        localStorage.removeItem(key);
      } catch (e) {}
      ta.value = '';
      ta.dispatchEvent(new Event('input', {
        bubbles: true
      }));
      update();
    });
    ta.parentNode.appendChild(b);
    update();
    return update;
  }
  function wireDrafts(form, ta) {
    var who = draftUser();
    var context = draftContext(form);
    migrateAnonDrafts(who);
    var key = draftKeyFor(who, context);
    try {
      var saved = localStorage.getItem(key);
      if (saved && !ta.value) {
        ta.value = saved;
        ta.dispatchEvent(new Event('input', {
          bubbles: true
        }));
      }
    } catch (e) {}
    var updateDiscard = buildDiscard(form, ta, key);
    ta.addEventListener('input', function () {
      try {
        ta.value ? localStorage.setItem(key, ta.value) : localStorage.removeItem(key);
      } catch (e) {}
      updateDiscard();
    });
    form.addEventListener('submit', function () {
      // Do not clear immediately: a dropped connection can leave the user on
      // the same page. The next successfully loaded page clears this context
      // before an empty composer can repopulate from localStorage.
      rememberPendingDraftSubmit(key, context);
      updateDiscard();
    });
  }
  function draftResumeHref(context) {
    var m = context.match(/^\/t\/(\d+)\/reply$/);
    if (m) {
      return '/t/' + m[1];
    }
    m = context.match(/^\/messages\/(\d+)$/);
    if (m) {
      return context;
    }
    if (context === '/messages') {
      return '/messages/new';
    }
    if (context === '/threads') {
      return '/';
    }
    return '';
  }
  function draftLabel(context) {
    if (context === '/threads') {
      return 'New topic';
    }
    if (context === '/messages') {
      return 'New direct message';
    }
    if (/^\/messages\/\d+$/.test(context)) {
      return 'Direct-message reply';
    }
    if (/^\/t\/\d+\/reply$/.test(context)) {
      return 'Thread reply';
    }
    if (/^\/posts\/\d+\/edit$/.test(context)) {
      return 'Post edit';
    }
    return context;
  }
  function renderDraftsPage() {
    var host = document.querySelector('[data-drafts-list]');
    if (!host || !draftsEnabled()) {
      return;
    }
    var who = draftUser();
    migrateAnonDrafts(who);
    var prefix = 'rb-draft:' + who + ':';
    var drafts = [];
    try {
      for (var i = 0; i < localStorage.length; i++) {
        var key = localStorage.key(i);
        if (!key || key.indexOf(prefix) !== 0) {
          continue;
        }
        var body = localStorage.getItem(key) || '';
        if (!body) {
          continue;
        }
        drafts.push({
          key: key,
          context: key.slice(prefix.length),
          body: body
        });
      }
    } catch (e) {}
    drafts.sort(function (a, b) {
      return a.context.localeCompare(b.context);
    });
    host.innerHTML = '';
    if (!drafts.length) {
      var empty = document.createElement('p');
      empty.className = 'muted empty';
      empty.textContent = 'No saved drafts in this browser.';
      host.appendChild(empty);
      return;
    }
    drafts.forEach(function (d) {
      var card = document.createElement('article');
      card.className = 'card';
      var h = document.createElement('h2');
      h.textContent = draftLabel(d.context);
      var p = document.createElement('p');
      p.className = 'muted';
      p.textContent = d.context;
      var pre = document.createElement('pre');
      pre.className = 'draft-preview';
      pre.textContent = d.body.length > 500 ? d.body.slice(0, 500) + '...' : d.body;
      var actions = document.createElement('p');
      actions.className = 'form-actions';
      var href = draftResumeHref(d.context);
      if (href) {
        var resume = document.createElement('a');
        resume.className = 'btn btn-small';
        resume.href = href;
        resume.textContent = 'Resume';
        actions.appendChild(resume);
      }
      var discard = document.createElement('button');
      discard.type = 'button';
      discard.className = 'btn btn-secondary btn-small';
      discard.textContent = 'Discard';
      discard.addEventListener('click', function () {
        try {
          localStorage.removeItem(d.key);
        } catch (e) {}
        card.remove();
        if (!host.querySelector('article')) {
          renderDraftsPage();
        }
      });
      actions.appendChild(discard);
      card.appendChild(h);
      card.appendChild(p);
      card.appendChild(pre);
      card.appendChild(actions);
      host.appendChild(card);
    });
  }

  // ---- Image paste / drag-drop upload (P3-04) ---------------------------
  function insertAtCursor(ta, text) {
    var s = ta.selectionStart;
    ta.value = ta.value.slice(0, s) + text + ta.value.slice(ta.selectionEnd);
    ta.selectionStart = ta.selectionEnd = s + text.length;
    ta.dispatchEvent(new Event('input', {
      bubbles: true
    }));
  }
  function replaceRange(ta, start, end, text) {
    ta.value = ta.value.slice(0, start) + text + ta.value.slice(end);
    ta.selectionStart = ta.selectionEnd = start + text.length;
    ta.focus();
    ta.dispatchEvent(new Event('input', {
      bubbles: true
    }));
  }
  function replaceOnce(ta, from, to) {
    var idx = ta.value.indexOf(from);
    if (idx < 0) {
      return false;
    }
    ta.value = ta.value.slice(0, idx) + to + ta.value.slice(idx + from.length);
    ta.dispatchEvent(new Event('input', {
      bubbles: true
    }));
    return true;
  }
  function markdownAlt(alt) {
    // Escape backslash and both brackets in one pass (so inserted escapes
    // are not re-processed) to keep the ![alt](url) image syntax intact.
    return (alt || '').replace(/[\r\n]+/g, ' ').replace(/[\\\[\]]/g, '\\$&');
  }
  function imageMarkdown(url, alt) {
    return '![' + markdownAlt(alt) + '](' + url + ')';
  }
  function uploadPurpose(form) {
    var action = form.getAttribute('action') || '';
    return action.indexOf('/messages') === 0 ? 'dm' : 'post';
  }
  function uploadTray(form, ta) {
    var tray = form.querySelector('.composer-upload-tray');
    if (tray) {
      return tray;
    }
    tray = document.createElement('div');
    tray.className = 'composer-upload-tray';
    tray.setAttribute('aria-live', 'polite');
    ta.parentNode.appendChild(tray);
    return tray;
  }
  function moveSnippetBefore(ta, moving, anchor) {
    if (!moving || !anchor || moving === anchor) {
      return false;
    }
    var v = ta.value;
    var mi = v.indexOf(moving);
    if (mi < 0 || v.indexOf(anchor) < 0) {
      return false;
    }
    v = v.slice(0, mi) + v.slice(mi + moving.length);
    var ai = v.indexOf(anchor);
    if (ai < 0) {
      return false;
    }
    ta.value = v.slice(0, ai) + moving + v.slice(ai);
    ta.dispatchEvent(new Event('input', {
      bubbles: true
    }));
    return true;
  }
  function moveSnippetAfter(ta, moving, anchor) {
    if (!moving || !anchor || moving === anchor) {
      return false;
    }
    var v = ta.value;
    var mi = v.indexOf(moving);
    if (mi < 0 || v.indexOf(anchor) < 0) {
      return false;
    }
    v = v.slice(0, mi) + v.slice(mi + moving.length);
    var ai = v.indexOf(anchor);
    if (ai < 0) {
      return false;
    }
    ta.value = v.slice(0, ai + anchor.length) + moving + v.slice(ai + anchor.length);
    ta.dispatchEvent(new Event('input', {
      bubbles: true
    }));
    return true;
  }
  function uploadCard(form, ta, file, placeholder) {
    var tray = uploadTray(form, ta);
    var card = document.createElement('div');
    card.className = 'composer-upload-card is-uploading';
    var preview = document.createElement('img');
    preview.className = 'composer-upload-thumb';
    preview.alt = '';
    preview.hidden = true;
    var meta = document.createElement('div');
    meta.className = 'composer-upload-meta';
    var status = document.createElement('div');
    status.className = 'composer-upload-status';
    status.textContent = 'Uploading ' + (file && file.name ? file.name : 'image') + '...';
    var progress = document.createElement('progress');
    progress.max = 100;
    progress.value = 0;
    var alt = document.createElement('input');
    alt.type = 'text';
    alt.className = 'input input-small';
    alt.placeholder = 'Alt text';
    alt.setAttribute('aria-label', 'Image alt text');
    alt.disabled = true;
    var actions = document.createElement('div');
    actions.className = 'composer-upload-actions';
    var up = document.createElement('button');
    up.type = 'button';
    up.className = 'btn btn-secondary btn-small';
    up.textContent = 'Up';
    var down = document.createElement('button');
    down.type = 'button';
    down.className = 'btn btn-secondary btn-small';
    down.textContent = 'Down';
    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-secondary btn-small';
    remove.textContent = 'Remove';
    actions.appendChild(up);
    actions.appendChild(down);
    actions.appendChild(remove);
    meta.appendChild(status);
    meta.appendChild(progress);
    meta.appendChild(alt);
    meta.appendChild(actions);
    card.appendChild(preview);
    card.appendChild(meta);
    tray.appendChild(card);
    card._rbMarkdown = '';
    remove.addEventListener('click', function () {
      replaceOnce(ta, card._rbMarkdown || placeholder, '');
      card.remove();
    });
    up.addEventListener('click', function () {
      var prev = card.previousElementSibling;
      if (prev && moveSnippetBefore(ta, card._rbMarkdown, prev._rbMarkdown || '')) {
        card.parentNode.insertBefore(card, prev);
      }
    });
    down.addEventListener('click', function () {
      var next = card.nextElementSibling;
      if (next && moveSnippetAfter(ta, card._rbMarkdown, next._rbMarkdown || '')) {
        card.parentNode.insertBefore(next, card);
      }
    });
    alt.addEventListener('input', function () {
      if (!card._rbUrl || !card._rbMarkdown) {
        return;
      }
      var next = imageMarkdown(card._rbUrl, alt.value);
      if (replaceOnce(ta, card._rbMarkdown, next)) {
        card._rbMarkdown = next;
        preview.alt = alt.value;
      }
    });
    return {
      progress: function (pct) {
        progress.value = Math.max(0, Math.min(100, pct));
      },
      complete: function (json) {
        var markdown = imageMarkdown(json.url, '');
        replaceOnce(ta, placeholder, markdown);
        card._rbUrl = json.url;
        card._rbMarkdown = markdown;
        preview.src = json.url;
        preview.alt = '';
        preview.hidden = false;
        alt.disabled = false;
        progress.value = 100;
        status.textContent = 'Uploaded image ' + json.width + 'x' + json.height + '.';
        card.classList.remove('is-uploading');
        card.classList.add('is-complete');
      },
      fail: function (message) {
        replaceOnce(ta, placeholder, '');
        progress.remove();
        alt.disabled = true;
        status.textContent = message || 'Upload failed.';
        card.classList.remove('is-uploading');
        card.classList.add('is-failed');
      }
    };
  }
  function uploadImage(form, ta, file) {
    var data = new FormData();
    data.append('_token', tokenField(form));
    data.append('image', file);
    data.append('purpose', uploadPurpose(form));
    // Unique per upload so several images pasted/dropped at once each resolve
    // into their OWN marker — String.replace(str) only swaps the first match.
    var token = 'rbup-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    var placeholder = '![uploading…](' + token + ')';
    insertAtCursor(ta, placeholder);
    var card = uploadCard(form, ta, file, placeholder);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/upload');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.upload.onprogress = function (e) {
      if (e.lengthComputable) {
        card.progress(e.loaded / e.total * 95);
      }
    };
    xhr.onload = function () {
      var j = null;
      try {
        j = JSON.parse(xhr.responseText || '{}');
      } catch (e) {}
      if (xhr.status >= 200 && xhr.status < 300 && j && j.ok) {
        card.complete(j);
      } else {
        card.fail(j && j.error || 'Upload failed.');
      }
    };
    xhr.onerror = function () {
      card.fail('Upload failed. Check your connection and try again.');
    };
    xhr.send(data);
  }
  function wireUploads(form, ta) {
    ta.addEventListener('paste', function (e) {
      var items = (e.clipboardData || {}).items || [];
      for (var i = 0; i < items.length; i++) {
        if (items[i].type && items[i].type.indexOf('image/') === 0) {
          uploadImage(form, ta, items[i].getAsFile());
        }
      }
    });
    ta.addEventListener('dragover', function (e) {
      e.preventDefault();
    });
    ta.addEventListener('drop', function (e) {
      var files = (e.dataTransfer || {}).files || [];
      if (!files.length) {
        return;
      }
      e.preventDefault();
      for (var i = 0; i < files.length; i++) {
        if (files[i].type && files[i].type.indexOf('image/') === 0) {
          uploadImage(form, ta, files[i]);
        }
      }
    });
  }

  // ---- Slash inserts + GIPHY picker (Phase 4 carryover) ----------------
  var slashConfigPromise = null;
  function loadSlashConfig() {
    if (slashConfigPromise !== null) {
      return slashConfigPromise;
    }
    slashConfigPromise = fetch('/composer/giphy-config', {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    }).then(function (r) {
      return r.ok ? r.json() : null;
    }).then(function (j) {
      return j && j.ok && j.enabled ? j : null;
    }).catch(function () {
      return null;
    });
    return slashConfigPromise;
  }
  function allowedInsert(config, key) {
    return config && Array.isArray(config.allowed_inserts) && config.allowed_inserts.indexOf(key) !== -1;
  }
  var SLASH_SNIPPETS = {
    table: {
      label: 'table',
      terms: ['table'],
      body: '| Heading | Heading |\n|---|---|\n| Cell | Cell |'
    },
    task_list: {
      label: 'task list',
      terms: ['task', 'tasks', 'todo'],
      body: '- [ ] Task'
    },
    poll: {
      label: 'poll outline',
      terms: ['poll', 'vote'],
      body: 'Poll: Question?\n- Option A\n- Option B'
    },
    custom_emoji: {
      label: 'custom emoji shortcode',
      terms: ['emoji', 'custom emoji'],
      body: ':shortcode:'
    }
  };
  function slashState(ta) {
    if (ta.selectionStart !== ta.selectionEnd) {
      return null;
    }
    var pos = ta.selectionStart;
    var lineStart = ta.value.lastIndexOf('\n', pos - 1) + 1;
    var prefix = ta.value.slice(lineStart, pos);
    var match = prefix.match(/(^|\s)\/([A-Za-z0-9_ -]*)$/);
    if (!match) {
      return null;
    }
    var query = (match[2] || '').toLowerCase().trim();
    return {
      start: pos - (match[2] || '').length - 1,
      end: pos,
      query: query
    };
  }
  function slashQueryMatches(query, command) {
    if (query === '') {
      return true;
    }
    for (var i = 0; i < command.terms.length; i++) {
      if (command.terms[i].indexOf(query) === 0 || query.indexOf(command.terms[i] + ' ') === 0) {
        return true;
      }
    }
    return command.label.indexOf(query) !== -1;
  }
  function slashCommands(config, query) {
    var commands = [];
    Object.keys(SLASH_SNIPPETS).forEach(function (key) {
      if (!allowedInsert(config, key)) {
        return;
      }
      var snippet = SLASH_SNIPPETS[key];
      var command = {
        key: key,
        type: 'snippet',
        label: snippet.label,
        terms: snippet.terms,
        body: snippet.body
      };
      if (slashQueryMatches(query, command)) {
        commands.push(command);
      }
    });
    if (allowedInsert(config, 'giphy') && config.public_key) {
      var giphy = {
        key: 'giphy',
        type: 'giphy',
        label: 'GIPHY',
        terms: ['gif', 'giphy']
      };
      if (slashQueryMatches(query, giphy)) {
        commands.push(giphy);
      }
    }
    return commands;
  }
  function giphySearchTerm(query) {
    return query.replace(/^(gif|giphy)\s*/i, '').trim();
  }
  function giphyResultUrl(item) {
    return item && item.images && item.images.original && item.images.original.url ? item.images.original.url : '';
  }
  function wireSlashMenu(form, ta) {
    var menu = document.createElement('div');
    menu.className = 'composer-slash-menu';
    menu.setAttribute('aria-label', 'Composer insert commands');
    menu.hidden = true;
    ta.parentNode.insertBefore(menu, ta.nextSibling);
    var config = null;
    var ready = false;
    var activeState = null;
    function hide() {
      activeState = null;
      menu.hidden = true;
      menu.innerHTML = '';
    }
    function renderButtons(commands) {
      menu.innerHTML = '';
      commands.forEach(function (command) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'composer-slash-command';
        b.textContent = command.type === 'giphy' ? 'Search GIPHY' : 'Insert ' + command.label;
        b.addEventListener('mousedown', function (e) {
          e.preventDefault();
        });
        b.addEventListener('click', function (e) {
          e.stopPropagation();
          var state = activeState || slashState(ta);
          if (!state) {
            hide();
            return;
          }
          if (command.type === 'giphy') {
            searchGiphy(state, giphySearchTerm(state.query));
            return;
          }
          replaceRange(ta, state.start, state.end, command.body);
          hide();
        });
        menu.appendChild(b);
      });
      menu.hidden = false;
    }
    function render() {
      if (!ready || config === null) {
        return;
      }
      var state = slashState(ta);
      if (!state) {
        hide();
        return;
      }
      activeState = state;
      var commands = slashCommands(config, state.query);
      if (commands.length === 0) {
        hide();
        return;
      }
      renderButtons(commands);
    }
    function searchGiphy(state, term) {
      if (!term) {
        menu.innerHTML = '<p class="muted">Type a search after /gif.</p>';
        menu.hidden = false;
        return;
      }
      menu.innerHTML = '<p class="muted">Searching GIPHY...</p>';
      menu.hidden = false;
      var url = 'https://api.giphy.com/v1/gifs/search' + '?api_key=' + encodeURIComponent(config.public_key) + '&q=' + encodeURIComponent(term) + '&rating=' + encodeURIComponent(config.rating || 'pg') + '&limit=6';
      fetch(url).then(function (r) {
        return r.ok ? r.json() : null;
      }).then(function (j) {
        var items = j && Array.isArray(j.data) ? j.data : [];
        menu.innerHTML = '';
        if (items.length === 0) {
          menu.innerHTML = '<p class="muted">No GIFs found.</p>';
          return;
        }
        items.forEach(function (item) {
          var mediaUrl = giphyResultUrl(item);
          if (!mediaUrl) {
            return;
          }
          var title = (item.title || 'GIF').trim() || 'GIF';
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'composer-slash-gif';
          b.setAttribute('aria-label', 'Insert GIF ' + title);
          var imgUrl = item.images && item.images.fixed_height_small && item.images.fixed_height_small.url ? item.images.fixed_height_small.url : mediaUrl;
          var img = document.createElement('img');
          img.src = imgUrl;
          img.alt = '';
          var span = document.createElement('span');
          span.textContent = title;
          b.appendChild(img);
          b.appendChild(span);
          b.addEventListener('mousedown', function (e) {
            e.preventDefault();
          });
          b.addEventListener('click', function (e) {
            e.stopPropagation();
            replaceRange(ta, state.start, state.end, imageMarkdown(mediaUrl, title));
            hide();
          });
          menu.appendChild(b);
        });
        if (!menu.childNodes.length) {
          menu.innerHTML = '<p class="muted">No GIFs found.</p>';
        }
      }).catch(function () {
        menu.innerHTML = '<p class="muted">GIPHY search is unavailable.</p>';
      });
    }
    loadSlashConfig().then(function (j) {
      config = j;
      ready = true;
      render();
    });
    ta.addEventListener('input', render);
    ta.addEventListener('keyup', render);
    ta.addEventListener('click', render);
    ta.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !menu.hidden) {
        e.preventDefault();
        hide();
      }
    });
    document.addEventListener('click', function (e) {
      if (e.target === ta || menu.contains(e.target)) {
        return;
      }
      hide();
    });
  }

  // ---- Enter-to-send + smart list continuation (P3-01) ------------------
  // Continue or end a Markdown list when Enter is pressed inside one. Returns
  // true when it handled the key (so the caller suppresses the default newline).
  function continueList(ta) {
    if (ta.selectionStart !== ta.selectionEnd) {
      return false;
    }
    var v = ta.value,
      pos = ta.selectionStart;
    var lineStart = v.lastIndexOf('\n', pos - 1) + 1;
    var line = v.slice(lineStart, pos);
    var um = line.match(/^(\s*)([-*+])\s+(\S.*)?$/); // - item / * item
    var om = line.match(/^(\s*)(\d+)([.)])\s+(\S.*)?$/); // 1. item / 1) item
    if (!um && !om) {
      return false;
    }
    var empty = um ? !um[3] : !om[4];
    if (empty) {
      // Enter on a bare marker ends the list: drop the marker, blank line.
      ta.value = v.slice(0, lineStart) + v.slice(pos);
      ta.selectionStart = ta.selectionEnd = lineStart;
    } else {
      var next = um ? um[1] + um[2] + ' ' : om[1] + (parseInt(om[2], 10) + 1) + om[3] + ' ';
      ta.value = v.slice(0, pos) + '\n' + next + v.slice(pos);
      ta.selectionStart = ta.selectionEnd = pos + 1 + next.length;
    }
    ta.dispatchEvent(new Event('input', {
      bubbles: true
    }));
    return true;
  }
  function wireKeys(form, ta, prefs) {
    ta.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && !e.altKey) {
        var key = e.key.toLowerCase();
        var action = key === 'b' ? 'bold' : key === 'i' ? 'italic' : key === 'k' ? 'link' : key === 'e' ? 'code' : null;
        if (action !== null) {
          e.preventDefault();
          applyAction(ta, action);
          return;
        }
      }
      if (e.key !== 'Enter' || e.isComposing) {
        return;
      }
      // Shift/modifier+Enter always inserts a newline (default behaviour).
      if (e.shiftKey || e.ctrlKey || e.metaKey || e.altKey) {
        return;
      }
      if (prefs.enterToSend) {
        e.preventDefault();
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
        return;
      }
      if (prefs.smartLists && continueList(ta)) {
        e.preventDefault();
      }
    });
  }
  function enhance(form, prefs) {
    var ta = form.querySelector('.composer-input');
    if (!ta || ta.getAttribute('data-rb-enhanced')) {
      return;
    }
    ta.setAttribute('data-rb-enhanced', '1');
    buildToolbar(ta);
    buildCounter(ta);
    if (prefs.showPreview) {
      buildPreview(form, ta);
    }
    wireKeys(form, ta, prefs);
    stampIdempotency(form);
    // A form may opt out of local draft autosave (data-no-draft). The inline
    // post-edit form does: its textarea is server-pre-filled with the current
    // body, so a saved draft is never restored into it (the !ta.value guard
    // fails) and there is no Drafts-page resume target — autosaving would only
    // leave a misleading, unrecoverable draft that the next load discards.
    if (draftsEnabled() && !form.hasAttribute('data-no-draft')) {
      wireDrafts(form, ta);
    }
    wireUploads(form, ta);
    wireSlashMenu(form, ta);
  }
  document.addEventListener('DOMContentLoaded', function () {
    var prefs = composingPrefs();
    clearCompletedDraftSubmits();
    var forms = document.querySelectorAll('form.composer');
    for (var i = 0; i < forms.length; i++) {
      enhance(forms[i], prefs);
    }
    renderDraftsPage();
  });
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "public/assets/composer.js", error: String((e && e.message) || e) }); }

// public/assets/tour.js
try { (() => {
// RetroBoards onboarding tour — progressive enhancement only (P3-11).
// The forum is fully usable without this script; if a target is missing the
// step is skipped, and completion is recorded server-side so it persists across
// devices. No inline styles (the popover is positioned by CSS class).
(function () {
  'use strict';

  if (!window.fetch) {
    return;
  }
  function token() {
    var el = document.querySelector('input[name="_token"]');
    return el ? el.value : '';
  }
  function record(path) {
    var data = new FormData();
    data.append('_token', token());
    return fetch(path, {
      method: 'POST',
      body: data,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).catch(function () {});
  }

  // Steps target final Gate-A DOM nodes; any missing target is skipped.
  var STEPS = [{
    sel: '.brand',
    title: 'Welcome',
    text: 'This is your community home. Click the name any time to come back here.'
  }, {
    sel: '.topbar-search',
    title: 'Search',
    text: 'Find topics and people from the search box up top.'
  }, {
    sel: '[data-bell]',
    title: 'Notifications',
    text: 'Replies, mentions, and reactions show up under the bell.'
  }, {
    sel: '.sidebar, .app-shell',
    title: 'Boards',
    text: 'Browse boards in the sidebar and jump into any conversation.'
  }, {
    sel: 'form.composer, .composer-details',
    title: 'Compose',
    text: 'Write in Markdown — bold, lists, code, spoilers, and image uploads all work in the same editor.'
  }, {
    sel: '.topbar-user',
    title: 'Your account',
    text: 'Tune appearance, reading, and composing under Settings whenever you like.'
  }];
  function run() {
    // Re-entry guard: a tour is already on screen (e.g. the auto-start tour
    // for a first-run user) and the Replay button was clicked. Each run()
    // appends its own popover and binds a private document keydown listener
    // that only its own finish() can remove, so a second concurrent run()
    // would stack a duplicate dialog and leak a listener. One tour at a time.
    if (document.querySelector('.tour-popover')) {
      return;
    }
    var steps = STEPS.filter(function (s) {
      return document.querySelector(s.sel);
    });
    if (!steps.length) {
      return;
    }
    var i = 0;
    var previousFocus = document.activeElement;
    var pop = document.createElement('div');
    pop.className = 'tour-popover';
    pop.setAttribute('role', 'dialog');
    pop.setAttribute('aria-modal', 'true');
    pop.setAttribute('aria-live', 'polite');
    pop.setAttribute('tabindex', '-1');
    document.body.appendChild(pop);
    var highlighted = null;
    function clearHighlight() {
      if (highlighted) {
        highlighted.classList.remove('tour-highlight');
        highlighted = null;
      }
    }
    function focusables() {
      return Array.prototype.slice.call(pop.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter(function (el) {
        return !el.disabled && el.offsetParent !== null;
      });
    }
    function finish(replay) {
      clearHighlight();
      document.removeEventListener('keydown', onKeydown);
      if (pop.parentNode) {
        pop.parentNode.removeChild(pop);
      }
      document.body.removeAttribute('data-tour');
      if (previousFocus && previousFocus.focus) {
        previousFocus.focus();
      }
      if (!replay) {
        record('/onboarding/complete');
      }
    }
    function onKeydown(e) {
      if (e.key === 'Escape') {
        e.preventDefault();
        finish(false);
        return;
      }
      if (e.key !== 'Tab') {
        return;
      }
      var nodes = focusables();
      if (!nodes.length) {
        return;
      }
      var first = nodes[0];
      var last = nodes[nodes.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
    function show() {
      clearHighlight();
      var step = steps[i];
      var target = document.querySelector(step.sel);
      if (target) {
        target.classList.add('tour-highlight');
        highlighted = target;
        if (target.scrollIntoView) {
          target.scrollIntoView({
            block: 'center'
          });
        }
      }
      var last = i === steps.length - 1;
      pop.innerHTML = '';
      var h = document.createElement('h3');
      h.id = 'tour-title';
      h.textContent = step.title;
      pop.appendChild(h);
      var p = document.createElement('p');
      p.id = 'tour-desc';
      p.textContent = step.text;
      pop.appendChild(p);
      pop.setAttribute('aria-labelledby', h.id);
      pop.setAttribute('aria-describedby', p.id);
      var actions = document.createElement('div');
      actions.className = 'tour-actions';
      var prog = document.createElement('span');
      prog.className = 'tour-progress';
      prog.textContent = i + 1 + ' of ' + steps.length;
      actions.appendChild(prog);
      var skip = document.createElement('button');
      skip.type = 'button';
      skip.className = 'btn btn-secondary';
      skip.textContent = 'Skip';
      skip.addEventListener('click', function () {
        finish(false);
      });
      actions.appendChild(skip);
      var next = document.createElement('button');
      next.type = 'button';
      next.className = 'btn';
      next.textContent = last ? 'Done' : 'Next';
      next.addEventListener('click', function () {
        if (last) {
          finish(false);
        } else {
          i++;
          show();
        }
      });
      actions.appendChild(next);
      pop.appendChild(actions);
      next.focus();
    }
    document.addEventListener('keydown', onKeydown);
    show();
  }

  // Replay links anywhere: <a data-tour-replay> resets + restarts.
  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-tour-replay]') : null;
    if (!t) {
      return;
    }
    e.preventDefault();
    record('/onboarding/replay').then(function () {
      document.body.setAttribute('data-tour', '1');
      run();
    });
  });
  document.addEventListener('DOMContentLoaded', function () {
    if (document.body.getAttribute('data-tour') === '1') {
      run();
    }
  });
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "public/assets/tour.js", error: String((e && e.message) || e) }); }

// ui_kits/admin/AdminApp.jsx
try { (() => {
/* Admin Console kit — app shell. Topbar + admin-head + horizontal subnav +
   section routing (Users drills into a user record). */
(function () {
  function App() {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      EightPointStar,
      Monogram,
      Pill
    } = DS;
    const SECT = window.RBAdminSections;
    const UserRecord = window.RBAdminUserRecord;
    const a = window.RBAdmin.admin;
    const keys = Object.keys(SECT);
    const [active, setActive] = React.useState('dashboard');
    const [userId, setUserId] = React.useState(null);
    const showingUser = active === 'users' && userId != null;
    const Section = SECT[active].render;
    return /*#__PURE__*/React.createElement("div", {
      className: "app-root"
    }, /*#__PURE__*/React.createElement("header", {
      className: "topbar"
    }, /*#__PURE__*/React.createElement("div", {
      className: "topbar-inner"
    }, /*#__PURE__*/React.createElement("a", {
      className: "brand",
      href: "../retroboards/index.html"
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 26
    }), /*#__PURE__*/React.createElement("span", {
      className: "brand-name"
    }, "RetroBoards")), /*#__PURE__*/React.createElement("a", {
      className: "topbar-back",
      href: "../retroboards/index.html"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M15 18l-6-6 6-6"
    })), " Back to the inbox"), /*#__PURE__*/React.createElement("div", {
      className: "topbar-right"
    }, /*#__PURE__*/React.createElement("span", {
      className: "topbar-user"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: a.name,
      username: a.username,
      size: "sm",
      presence: "online",
      gilt: true
    }), /*#__PURE__*/React.createElement("span", {
      className: "topbar-name"
    }, a.name))))), /*#__PURE__*/React.createElement("div", {
      className: "admin"
    }, /*#__PURE__*/React.createElement("div", {
      className: "admin-head"
    }, /*#__PURE__*/React.createElement("span", null, /*#__PURE__*/React.createElement("span", {
      className: "eyebrow"
    }, "Operator's desk"), /*#__PURE__*/React.createElement("h1", null, "Admin console")), /*#__PURE__*/React.createElement(Pill, {
      tone: "admin"
    }, "Admin mode")), /*#__PURE__*/React.createElement("nav", {
      className: "admin-subnav",
      "aria-label": "Admin sections"
    }, keys.map(k => /*#__PURE__*/React.createElement("button", {
      key: k,
      className: k === active ? 'active' : '',
      "aria-current": k === active ? 'page' : undefined,
      onClick: () => {
        setActive(k);
        setUserId(null);
      }
    }, SECT[k].label))), /*#__PURE__*/React.createElement("div", {
      className: "admin-pane",
      key: active + (userId || '')
    }, showingUser ? /*#__PURE__*/React.createElement(UserRecord, {
      userId: userId,
      back: () => setUserId(null)
    }) : active === 'users' ? /*#__PURE__*/React.createElement(Section, {
      openUser: id => setUserId(id)
    }) : /*#__PURE__*/React.createElement(Section, null))));
  }
  window.RBAdminApp = App;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/admin/AdminApp.jsx", error: String((e && e.message) || e) }); }

// ui_kits/admin/AdminSections.jsx
try { (() => {
/* Admin Console kit — the section panes. Each is a faithful recreation of an
   admin/*.php template, composed from design-system primitives + .audit tables,
   .stat-cards, .flash, structure rows, and link-lists. */
(function () {
  const A = () => window.RBAdmin;
  const DS = () => window.ImladrisDesignSystem_c3e027;

  /* ── Dashboard ────────────────────────────────────────────────────────── */
  function Dashboard() {
    const {
      Input,
      Textarea,
      Button
    } = DS();
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Site name"), /*#__PURE__*/React.createElement("form", {
      className: "inline-form",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement(Input, {
      defaultValue: A().siteName,
      maxLength: 80,
      style: {
        maxWidth: 280
      }
    }), /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Update"))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Trust & safety"), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Registration"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "Open"), /*#__PURE__*/React.createElement("option", null, "Closed (no new sign-ups)"), /*#__PURE__*/React.createElement("option", null, "Invite only"))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Anti-abuse enforcement"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "Observe (log only)"), /*#__PURE__*/React.createElement("option", null, "Flag"), /*#__PURE__*/React.createElement("option", null, "Hold (queue for approval)"), /*#__PURE__*/React.createElement("option", null, "Block (reject)"))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Blocked words"), /*#__PURE__*/React.createElement(Textarea, {
      rows: 3,
      placeholder: "One word or phrase per line",
      defaultValue: "palantír-scam\nfree mithril"
    }), /*#__PURE__*/React.createElement("span", {
      className: "field-error",
      style: {
        color: 'var(--text-faint)'
      }
    }, "Case-insensitive; matched as substrings against new posts.")), /*#__PURE__*/React.createElement(Button, null, "Save settings"))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Recent activity"), /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "When"), /*#__PURE__*/React.createElement("th", null, "Actor"), /*#__PURE__*/React.createElement("th", null, "Action"), /*#__PURE__*/React.createElement("th", null, "Target"), /*#__PURE__*/React.createElement("th", null, "Reason"))), /*#__PURE__*/React.createElement("tbody", null, A().audit.map((r, i) => /*#__PURE__*/React.createElement("tr", {
      key: i
    }, /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, r.when), /*#__PURE__*/React.createElement("td", null, r.actor), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, r.action)), /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, r.target), /*#__PURE__*/React.createElement("td", null, r.reason || /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "\u2014"))))))));
  }

  /* ── Boards & categories (structure) ──────────────────────────────────── */
  function Structure() {
    const {
      Input,
      Button
    } = DS();
    return /*#__PURE__*/React.createElement(React.Fragment, null, A().categories.map(c => /*#__PURE__*/React.createElement("section", {
      className: "card",
      key: c.id
    }, /*#__PURE__*/React.createElement("div", {
      className: "admin-cat-head"
    }, /*#__PURE__*/React.createElement("form", {
      className: "inline-form",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement(Input, {
      defaultValue: c.name,
      maxLength: 64,
      style: {
        maxWidth: 220
      }
    }), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, "Save")), /*#__PURE__*/React.createElement("span", {
      className: "admin-cat-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: "move-btn",
      type: "button",
      "aria-label": "Move up"
    }, "\u2191"), /*#__PURE__*/React.createElement("button", {
      className: "move-btn",
      type: "button",
      "aria-label": "Move down"
    }, "\u2193"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn danger",
      type: "button"
    }, "Delete category"))), /*#__PURE__*/React.createElement("ul", {
      className: "admin-board-list"
    }, c.boards.map(b => /*#__PURE__*/React.createElement("li", {
      className: "admin-board-row",
      key: b.id
    }, /*#__PURE__*/React.createElement("span", {
      className: "admin-board-name"
    }, /*#__PURE__*/React.createElement("span", {
      className: "hash"
    }, "#"), /*#__PURE__*/React.createElement("b", null, b.name), /*#__PURE__*/React.createElement("span", {
      className: "muted mono"
    }, "/c/", b.slug), b.visibility !== 'public' ? /*#__PURE__*/React.createElement("span", {
      className: "tag"
    }, b.visibility) : null, b.archived ? /*#__PURE__*/React.createElement("span", {
      className: "tag tag-archived"
    }, "Archived") : null, /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "\xB7 ", b.threads, " threads")), /*#__PURE__*/React.createElement("span", {
      className: "admin-board-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: "move-btn",
      type: "button",
      "aria-label": "Move up"
    }, "\u2191"), /*#__PURE__*/React.createElement("button", {
      className: "move-btn",
      type: "button",
      "aria-label": "Move down"
    }, "\u2193"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Edit"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, b.archived ? 'Unarchive' : 'Archive'), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn danger",
      type: "button"
    }, "Delete"))))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Add a category"), /*#__PURE__*/React.createElement("form", {
      className: "inline-form",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement(Input, {
      placeholder: "Category name",
      maxLength: 64,
      style: {
        maxWidth: 240
      }
    }), /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Add category"))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Add a board"), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Category"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, A().categories.map(c => /*#__PURE__*/React.createElement("option", {
      key: c.id
    }, "#", c.name)))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Name"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 80
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Slug ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(optional \u2014 derived from name)")), /*#__PURE__*/React.createElement(Input, {
      maxLength: 64
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Description"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 255
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Visibility"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "Public"), /*#__PURE__*/React.createElement("option", null, "Hidden (unlisted)"), /*#__PURE__*/React.createElement("option", null, "Private (admins only)"))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Assignment mode"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "Off"), /*#__PURE__*/React.createElement("option", null, "Members can assign themselves"), /*#__PURE__*/React.createElement("option", null, "Staff can assign members"))), /*#__PURE__*/React.createElement("label", {
      className: "checkline"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox"
    }), " Allow anonymous posting"), /*#__PURE__*/React.createElement("label", {
      className: "checkline"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox",
      defaultChecked: true
    }), " Allow approved tags"), /*#__PURE__*/React.createElement("label", {
      className: "checkline"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox"
    }), " Allow wiki-style post editing"), /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Add board"))));
  }

  /* ── Users ────────────────────────────────────────────────────────────── */
  function Users({
    openUser
  }) {
    const {
      Input,
      Button,
      Monogram
    } = DS();
    return /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("form", {
      className: "inline-form",
      onSubmit: e => e.preventDefault(),
      style: {
        marginBottom: 14
      }
    }, /*#__PURE__*/React.createElement(Input, {
      type: "search",
      placeholder: "Search username, name, or email",
      style: {
        maxWidth: 320
      }
    }), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, "Search")), /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "User"), /*#__PURE__*/React.createElement("th", null, "Role"), /*#__PURE__*/React.createElement("th", null, "State"), /*#__PURE__*/React.createElement("th", null, "Regard"), /*#__PURE__*/React.createElement("th", null, "Joined"))), /*#__PURE__*/React.createElement("tbody", null, A().users.map(u => /*#__PURE__*/React.createElement("tr", {
      key: u.id
    }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      style: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: 8
      }
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: u.display,
      username: u.username,
      size: "sm"
    }), /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        openUser(u.id);
      }
    }, u.username), /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, u.display))), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: 'role-pill role-' + u.role
    }, u.role)), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: 'state state-' + u.state
    }, u.state)), /*#__PURE__*/React.createElement("td", {
      className: "tnum"
    }, u.rep.toLocaleString()), /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, u.joined))))), /*#__PURE__*/React.createElement("nav", {
      className: "pager"
    }, /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary",
      disabled: true
    }, "Previous"), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, "Next")));
  }

  /* ── User record (drill-in) ───────────────────────────────────────────── */
  function UserRecord({
    userId,
    back
  }) {
    const {
      Input,
      Button,
      Monogram
    } = DS();
    const u = A().users.find(x => x.id === userId) || A().users[0];
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button",
      onClick: back,
      style: {
        alignSelf: 'flex-start',
        marginBottom: 2
      }
    }, "\u2190 All users"), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 11
      }
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: u.display,
      username: u.username,
      size: "lg",
      gilt: true
    }), " ", u.display, " ", /*#__PURE__*/React.createElement("span", {
      className: "muted",
      style: {
        fontFamily: 'var(--font-mono)',
        fontSize: '.9rem'
      }
    }, "@", u.username)), /*#__PURE__*/React.createElement("dl", {
      className: "id-stats"
    }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("dt", null, "Role"), /*#__PURE__*/React.createElement("dd", null, u.role)), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("dt", null, "State"), /*#__PURE__*/React.createElement("dd", null, u.state)), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("dt", null, "Regard"), /*#__PURE__*/React.createElement("dd", {
      className: "tnum"
    }, u.rep.toLocaleString())), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("dt", null, "Profile"), /*#__PURE__*/React.createElement("dd", null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => e.preventDefault()
    }, "View public profile"))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Cosmetic title"), /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "Effective: ", /*#__PURE__*/React.createElement("strong", null, u.role === 'admin' ? 'Master of the House' : 'Loremaster of Imladris'), " \xB7 Derived ladder: Legend"), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Title override"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 64,
      placeholder: "(none)"
    })), /*#__PURE__*/React.createElement("div", {
      className: "form-actions"
    }, /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Save title"), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "ghost"
    }, "Clear (revert to derived)")))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Badges"), /*#__PURE__*/React.createElement("h3", null, "Grant a manual badge"), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Badge"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, A().badgeCatalogue.map(b => /*#__PURE__*/React.createElement("option", {
      key: b
    }, b)))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Reason (optional)"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 255
    })), /*#__PURE__*/React.createElement("div", {
      className: "form-actions"
    }, /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Grant badge"))), /*#__PURE__*/React.createElement("h3", null, "Held manual badges"), /*#__PURE__*/React.createElement("ul", {
      className: "link-list"
    }, /*#__PURE__*/React.createElement("li", null, /*#__PURE__*/React.createElement("span", {
      "aria-hidden": "true",
      style: {
        color: 'var(--star)'
      }
    }, "\u2726"), " Trusted Answerer ", /*#__PURE__*/React.createElement("button", {
      className: "linkbtn muted spacer",
      type: "button"
    }, "Revoke")), /*#__PURE__*/React.createElement("li", null, /*#__PURE__*/React.createElement("span", {
      "aria-hidden": "true",
      style: {
        color: 'var(--star)'
      }
    }, "\u2726"), " Anniversary ", /*#__PURE__*/React.createElement("button", {
      className: "linkbtn muted spacer",
      type: "button"
    }, "Revoke")))));
  }

  /* ── Badge rules ──────────────────────────────────────────────────────── */
  function BadgeRules() {
    const {
      Input,
      Button
    } = DS();
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Create rule"), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Badge"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, A().badgeCatalogue.map(b => /*#__PURE__*/React.createElement("option", {
      key: b
    }, b)))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Rule"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "Post count"), /*#__PURE__*/React.createElement("option", null, "Thread count"), /*#__PURE__*/React.createElement("option", null, "Reputation"), /*#__PURE__*/React.createElement("option", null, "Solved answers"))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Threshold"), /*#__PURE__*/React.createElement(Input, {
      type: "number",
      defaultValue: "10",
      className: "input-small"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Board scope"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "All boards"), A().categories.flatMap(c => c.boards).map(b => /*#__PURE__*/React.createElement("option", {
      key: b.id
    }, b.name)))), /*#__PURE__*/React.createElement(Button, null, "Create rule"))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Rules"), /*#__PURE__*/React.createElement("ul", {
      className: "link-list"
    }, A().badgeRules.map(r => /*#__PURE__*/React.createElement("li", {
      key: r.id
    }, /*#__PURE__*/React.createElement("strong", null, r.badge), /*#__PURE__*/React.createElement("span", {
      className: "rule-meta"
    }, r.rule, " \u2265 ", r.threshold, r.board ? ' · ' + r.board : ''), /*#__PURE__*/React.createElement("span", {
      className: 'badge' + (r.enabled ? '' : ' badge-muted')
    }, r.enabled ? 'Enabled' : 'Disabled'), /*#__PURE__*/React.createElement("span", {
      className: "spacer"
    }), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Preview"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Backfill"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn muted",
      type: "button"
    }, r.enabled ? 'Disable' : 'Enable'), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn danger",
      type: "button"
    }, "Revoke awards"))))));
  }

  /* ── Email delivery ───────────────────────────────────────────────────── */
  function Email() {
    const {
      Button
    } = DS();
    const q = A().emailQueue;
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
      className: "flash"
    }, /*#__PURE__*/React.createElement("strong", null, "Sending is configured"), " from ", /*#__PURE__*/React.createElement("code", null, "council@imladris.example"), ". The delivery worker drains queued mail."), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Sending domain"), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("strong", null, "imladris.example"), " ", /*#__PURE__*/React.createElement("span", {
      className: "muted mono"
    }, "selector council")), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "SPF: pass \xB7 DKIM: pass \xB7 checked 2h ago"), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, "Refresh SPF/DKIM status")), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Queue status"), /*#__PURE__*/React.createElement("ul", {
      className: "stat-cards"
    }, Object.keys(q).map(k => /*#__PURE__*/React.createElement("li", {
      className: "stat-card",
      key: k
    }, /*#__PURE__*/React.createElement("span", {
      className: "stat-num tnum"
    }, q[k]), /*#__PURE__*/React.createElement("span", {
      className: "stat-label"
    }, k))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Delivery log"), /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "When"), /*#__PURE__*/React.createElement("th", null, "To"), /*#__PURE__*/React.createElement("th", null, "Kind"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null, "Attempts"), /*#__PURE__*/React.createElement("th", null, "Subject"), /*#__PURE__*/React.createElement("th", null, "Action"))), /*#__PURE__*/React.createElement("tbody", null, A().deliveries.map((d, i) => /*#__PURE__*/React.createElement("tr", {
      key: i
    }, /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, d.when), /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, d.to), /*#__PURE__*/React.createElement("td", null, d.kind), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: 'state state-' + d.status
    }, d.status)), /*#__PURE__*/React.createElement("td", {
      className: "tnum"
    }, d.attempts), /*#__PURE__*/React.createElement("td", null, d.subject), /*#__PURE__*/React.createElement("td", null, d.status === 'failed' ? /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Requeue") : /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "\u2014"))))))));
  }

  /* ── Webhooks ─────────────────────────────────────────────────────────── */
  function Webhooks() {
    const {
      Input,
      Button
    } = DS();
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
      className: "flash flash-secret"
    }, /*#__PURE__*/React.createElement("strong", null, "Copy this signing secret now \u2014 it will not be shown again:"), " ", /*#__PURE__*/React.createElement("code", null, "whsec_iml_3kf9d2a77qdh1pb42")), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Register an endpoint"), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Name"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 80
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "URL"), /*#__PURE__*/React.createElement(Input, {
      type: "url",
      placeholder: "https://"
    })), /*#__PURE__*/React.createElement("fieldset", {
      className: "events"
    }, /*#__PURE__*/React.createElement("legend", null, "Events"), Object.entries(A().webhookEvents).map(([ev, desc]) => /*#__PURE__*/React.createElement("label", {
      className: "checkline",
      key: ev
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox"
    }), " ", /*#__PURE__*/React.createElement("code", null, ev), " \u2014 ", desc))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Confirm your password"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, null, "Register endpoint"))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Endpoints"), /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Name"), /*#__PURE__*/React.createElement("th", null, "URL"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null, "Last status"), /*#__PURE__*/React.createElement("th", null))), /*#__PURE__*/React.createElement("tbody", null, A().webhooks.map(w => /*#__PURE__*/React.createElement("tr", {
      key: w.id
    }, /*#__PURE__*/React.createElement("td", null, w.name), /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, w.url), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: 'state state-' + (w.active ? 'active' : 'paused')
    }, w.active ? 'active' : 'paused')), /*#__PURE__*/React.createElement("td", {
      className: "tnum"
    }, w.last), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => e.preventDefault()
    }, "Manage"))))))));
  }

  /* ── API tokens ───────────────────────────────────────────────────────── */
  function ApiTokens() {
    const {
      Input,
      Button
    } = DS();
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Create a token"), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Name"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 80
    })), /*#__PURE__*/React.createElement("fieldset", {
      className: "events"
    }, /*#__PURE__*/React.createElement("legend", null, "Scopes"), Object.entries(A().tokenScopes).map(([s, desc]) => /*#__PURE__*/React.createElement("label", {
      className: "checkline",
      key: s
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox"
    }), " ", /*#__PURE__*/React.createElement("code", null, s), " \u2014 ", desc))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Expires in days ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(optional)")), /*#__PURE__*/React.createElement(Input, {
      type: "number",
      className: "input-small"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Confirm your password"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, null, "Create token"))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Tokens"), /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Name"), /*#__PURE__*/React.createElement("th", null, "Scopes"), /*#__PURE__*/React.createElement("th", null, "Created"), /*#__PURE__*/React.createElement("th", null, "Last used"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null))), /*#__PURE__*/React.createElement("tbody", null, A().tokens.map(t => /*#__PURE__*/React.createElement("tr", {
      key: t.id
    }, /*#__PURE__*/React.createElement("td", null, t.name), /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, t.scopes), /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, t.created), /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, t.last), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: 'state state-' + (t.revoked ? 'revoked' : 'active')
    }, t.revoked ? 'revoked' : 'active')), /*#__PURE__*/React.createElement("td", null, t.revoked ? /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "\u2014") : /*#__PURE__*/React.createElement("button", {
      className: "linkbtn danger",
      type: "button"
    }, "Revoke"))))))));
  }

  /* ── Announcements ────────────────────────────────────────────────────── */
  function Announcements() {
    const {
      Textarea,
      Button
    } = DS();
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Current banner"), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "No banner is currently shown.")), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Publish a banner"), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Message"), /*#__PURE__*/React.createElement(Textarea, {
      rows: 3,
      maxLength: 500,
      placeholder: "A short notice for the whole council\u2026"
    })), /*#__PURE__*/React.createElement("label", {
      className: "checkline"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox",
      defaultChecked: true
    }), " Members can dismiss this banner"), /*#__PURE__*/React.createElement("label", {
      className: "checkline"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox"
    }), " Also send an in-app broadcast notification to all members"), /*#__PURE__*/React.createElement("label", {
      className: "checkline"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox"
    }), " Also queue an email broadcast to active members"), /*#__PURE__*/React.createElement(Button, null, "Publish banner"))));
  }

  /* ── Tags ─────────────────────────────────────────────────────────────── */
  function Tags() {
    const {
      Input,
      Button
    } = DS();
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Add a tag"), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Name"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 80
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Slug"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 64
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Description"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 255
    })), /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Add tag"))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Catalogue"), /*#__PURE__*/React.createElement("ul", {
      className: "admin-board-list"
    }, A().tags.map(t => /*#__PURE__*/React.createElement("li", {
      className: "admin-board-row",
      key: t.id
    }, /*#__PURE__*/React.createElement("span", {
      className: "admin-board-name"
    }, /*#__PURE__*/React.createElement("b", null, t.name), /*#__PURE__*/React.createElement("span", {
      className: "muted mono"
    }, "/t/", t.slug), t.visibility !== 'public' ? /*#__PURE__*/React.createElement("span", {
      className: "tag"
    }, t.visibility) : null, /*#__PURE__*/React.createElement("span", {
      className: 'badge' + (t.enabled ? '' : ' badge-muted')
    }, t.enabled ? 'Enabled' : 'Disabled')), /*#__PURE__*/React.createElement("span", {
      className: "admin-board-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Edit"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn muted",
      type: "button"
    }, "Merge")))))));
  }

  /* ── Extensions ───────────────────────────────────────────────────────── */
  function Extensions() {
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Sandbox probe"), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("strong", null, "available"), " ", /*#__PURE__*/React.createElement("span", {
      className: "muted mono"
    }, "wasm-runtime")), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Server extension execution is controlled by the ", /*#__PURE__*/React.createElement("code", null, "server_extensions"), " feature flag.")), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Handlers"), /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Package"), /*#__PURE__*/React.createElement("th", null, "Handler"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null, "Entrypoint"))), /*#__PURE__*/React.createElement("tbody", null, A().handlers.map((h, i) => /*#__PURE__*/React.createElement("tr", {
      key: i
    }, /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, h.pkg), /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, h.handler), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: "state state-active"
    }, h.status)), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, h.entrypoint))))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Run history"), /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "When"), /*#__PURE__*/React.createElement("th", null, "Handler"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null, "Detail"))), /*#__PURE__*/React.createElement("tbody", null, A().runs.map((r, i) => /*#__PURE__*/React.createElement("tr", {
      key: i
    }, /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, r.when), /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, r.handler), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: 'state state-' + (r.status === 'ok' ? 'active' : 'failed')
    }, r.status)), /*#__PURE__*/React.createElement("td", null, r.detail || /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "\u2014"))))))));
  }

  /* ── Branding ─────────────────────────────────────────────────────────── */
  function Branding() {
    const {
      Input,
      Textarea,
      Button
    } = DS();
    const [name, setName] = React.useState(A().siteName);
    const [primary, setPrimary] = React.useState('#2E4A3A');
    const [accent, setAccent] = React.useState('#C29A44');
    return /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Branding"), /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "Replace the placeholder name, colours, logo, and favicon with your community's own. Everything falls back to safe defaults if left blank."), /*#__PURE__*/React.createElement("div", {
      className: "brand-cols"
    }, /*#__PURE__*/React.createElement("div", {
      className: "stacked",
      style: {
        width: '100%'
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Site name"), /*#__PURE__*/React.createElement(Input, {
      value: name,
      onChange: e => setName(e.target.value),
      maxLength: 80
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Primary colour (hex)"), /*#__PURE__*/React.createElement("span", {
      className: "swatch-input"
    }, /*#__PURE__*/React.createElement("span", {
      className: "swatch-chip",
      style: {
        background: primary
      }
    }), /*#__PURE__*/React.createElement(Input, {
      value: primary,
      onChange: e => setPrimary(e.target.value),
      maxLength: 7,
      className: "input-small"
    }))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Accent colour (hex)"), /*#__PURE__*/React.createElement("span", {
      className: "swatch-input"
    }, /*#__PURE__*/React.createElement("span", {
      className: "swatch-chip",
      style: {
        background: accent
      }
    }), /*#__PURE__*/React.createElement(Input, {
      value: accent,
      onChange: e => setAccent(e.target.value),
      maxLength: 7,
      className: "input-small"
    }))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Default theme for signed-out visitors"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "System"), /*#__PURE__*/React.createElement("option", null, "Light"), /*#__PURE__*/React.createElement("option", null, "Dark"))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Theme preset"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "Classic"), /*#__PURE__*/React.createElement("option", null, "Retro"))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Logo"), /*#__PURE__*/React.createElement("input", {
      type: "file",
      className: "input"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Favicon"), /*#__PURE__*/React.createElement("input", {
      type: "file",
      className: "input"
    })), /*#__PURE__*/React.createElement("label", {
      className: "checkline"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox"
    }), " Enable custom CSS"), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Custom CSS"), /*#__PURE__*/React.createElement(Textarea, {
      className: "code-area",
      rows: 5,
      placeholder: "/* applies site-wide */"
    })), /*#__PURE__*/React.createElement(Button, null, "Save branding")), /*#__PURE__*/React.createElement("aside", {
      className: "brand-preview"
    }, /*#__PURE__*/React.createElement("p", {
      className: "pane-intro",
      style: {
        marginBottom: 8
      }
    }, "Live preview"), /*#__PURE__*/React.createElement("div", {
      className: "brand-preview-shell"
    }, /*#__PURE__*/React.createElement("div", {
      className: "brand-preview-bar",
      style: {
        background: primary
      }
    }, /*#__PURE__*/React.createElement("strong", null, name || 'RetroBoards'), /*#__PURE__*/React.createElement("span", null, "System")), /*#__PURE__*/React.createElement("div", {
      className: "brand-preview-body"
    }, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => e.preventDefault(),
      style: {
        color: primary
      }
    }, "Sample link"), /*#__PURE__*/React.createElement("button", {
      className: "btn",
      type: "button",
      style: {
        background: primary
      }
    }, "Primary button"), /*#__PURE__*/React.createElement("span", {
      className: "brand-preview-accent",
      style: {
        color: accent,
        borderColor: accent
      }
    }, "Accent marker"))))));
  }
  window.RBAdminSections = {
    dashboard: {
      label: 'Dashboard',
      render: Dashboard
    },
    structure: {
      label: 'Boards & categories',
      render: Structure
    },
    users: {
      label: 'Users',
      render: Users
    },
    badgeRules: {
      label: 'Badge rules',
      render: BadgeRules
    },
    tags: {
      label: 'Tags',
      render: Tags
    },
    email: {
      label: 'Email',
      render: Email
    },
    webhooks: {
      label: 'Webhooks',
      render: Webhooks
    },
    apiTokens: {
      label: 'API tokens',
      render: ApiTokens
    },
    announcements: {
      label: 'Announcements',
      render: Announcements
    },
    extensions: {
      label: 'Extensions',
      render: Extensions
    },
    branding: {
      label: 'Branding',
      render: Branding
    }
  };
  window.RBAdminUserRecord = UserRecord;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/admin/AdminSections.jsx", error: String((e && e.message) || e) }); }

// ui_kits/admin/data.js
try { (() => {
/* Admin Console kit — seed data for the operator's desk. */
(function () {
  window.RBAdmin = {
    admin: {
      name: 'Elrond',
      username: 'elrond'
    },
    siteName: 'RetroBoards',
    audit: [{
      when: '2h ago',
      actor: 'elrond',
      action: 'post.lock',
      target: 'thread #1042',
      reason: 'Resolved; locking to preserve the answer.'
    }, {
      when: '5h ago',
      actor: 'galadriel',
      action: 'post.accept_answer',
      target: 'post #7731',
      reason: ''
    }, {
      when: 'yesterday',
      actor: 'system',
      action: 'badge.award',
      target: 'user #88',
      reason: 'Rule: solved_count ≥ 10'
    }, {
      when: '2 days ago',
      actor: 'elrond',
      action: 'user.role_change',
      target: 'user #51',
      reason: 'Promoted to moderator.'
    }, {
      when: '3 days ago',
      actor: 'erestor',
      action: 'board.archive',
      target: 'board #14',
      reason: 'Inactive since Second Age.'
    }],
    categories: [{
      id: 1,
      name: 'The Commons',
      boards: [{
        id: 11,
        name: 'announcements',
        slug: 'announcements',
        visibility: 'public',
        threads: 12,
        archived: false
      }, {
        id: 12,
        name: 'introductions',
        slug: 'introductions',
        visibility: 'public',
        threads: 31,
        archived: false
      }, {
        id: 13,
        name: 'the-valley',
        slug: 'the-valley',
        visibility: 'public',
        threads: 88,
        archived: false
      }]
    }, {
      id: 2,
      name: 'Vilya · Expose',
      boards: [{
        id: 21,
        name: 'interpretability',
        slug: 'interpretability',
        visibility: 'public',
        threads: 47,
        archived: false
      }, {
        id: 22,
        name: 'evaluations',
        slug: 'evaluations',
        visibility: 'members',
        threads: 63,
        archived: false
      }, {
        id: 23,
        name: 'audit-trails',
        slug: 'audit-trails',
        visibility: 'private',
        threads: 39,
        archived: false
      }, {
        id: 24,
        name: 'old-council',
        slug: 'old-council',
        visibility: 'public',
        threads: 5,
        archived: true
      }]
    }],
    users: [{
      id: 88,
      username: 'galadriel',
      display: 'Galadriel',
      role: 'moderator',
      state: 'active',
      rep: 5120,
      joined: 'T.A. 2019'
    }, {
      id: 12,
      username: 'elrond',
      display: 'Elrond',
      role: 'admin',
      state: 'active',
      rep: 8740,
      joined: 'T.A. 2018'
    }, {
      id: 51,
      username: 'erestor',
      display: 'Erestor',
      role: 'member',
      state: 'active',
      rep: 3985,
      joined: 'T.A. 2021'
    }, {
      id: 64,
      username: 'glorfindel',
      display: 'Glorfindel',
      role: 'member',
      state: 'active',
      rep: 2140,
      joined: 'T.A. 2022'
    }, {
      id: 77,
      username: 'arwen',
      display: 'Arwen',
      role: 'member',
      state: 'active',
      rep: 1760,
      joined: 'T.A. 2023'
    }, {
      id: 90,
      username: 'saruman',
      display: 'Saruman',
      role: 'member',
      state: 'deactivated',
      rep: 12,
      joined: 'T.A. 2024'
    }],
    badgeRules: [{
      id: 1,
      badge: 'Welcome',
      rule: 'post_count',
      threshold: 1,
      board: null,
      enabled: true
    }, {
      id: 2,
      badge: 'Trusted Answerer',
      rule: 'solved_count',
      threshold: 10,
      board: null,
      enabled: true
    }, {
      id: 3,
      badge: 'Loremaster of Evals',
      rule: 'reputation',
      threshold: 5000,
      board: 'evaluations',
      enabled: false
    }],
    emailQueue: {
      queued: 3,
      sent: 1284,
      failed: 2,
      suppressed: 6,
      bounced: 1,
      complained: 0
    },
    deliveries: [{
      when: '2h ago',
      to: 'arwen@imladris.council',
      kind: 'instant',
      status: 'sent',
      attempts: '1 / 3',
      subject: 'New counsel on your topic',
      detail: 'msg_8821'
    }, {
      when: '6h ago',
      to: 'lindir@imladris.council',
      kind: 'digest',
      status: 'sent',
      attempts: '1 / 3',
      subject: 'Your daily digest',
      detail: 'msg_8790'
    }, {
      when: 'yesterday',
      to: 'bounce@nowhere.test',
      kind: 'instant',
      status: 'failed',
      attempts: '3 / 3',
      subject: 'You were mentioned',
      detail: '550 mailbox unavailable'
    }],
    suppressions: [{
      email: 'bounce@nowhere.test',
      reason: 'hard_bounce',
      since: 'yesterday'
    }],
    webhooks: [{
      id: 1,
      name: 'Ops bridge',
      url: 'https://ops.imladris.council/hooks/forum',
      active: true,
      last: '200'
    }, {
      id: 2,
      name: 'Archive mirror',
      url: 'https://mirror.example/ingest',
      active: false,
      last: '— '
    }],
    webhookEvents: {
      'post.created': 'A new post or reply is published',
      'thread.created': 'A new topic is opened',
      'thread.solved': 'A topic is marked solved',
      'user.registered': 'A new member joins',
      'ping': 'Test event (admin-only)'
    },
    tokens: [{
      id: 1,
      name: 'Read-only mirror',
      scopes: 'read:threads, read:posts',
      created: 'T.A. 2024',
      last: '2h ago',
      revoked: false
    }, {
      id: 2,
      name: 'Legacy importer',
      scopes: 'write:posts',
      created: 'T.A. 2023',
      last: '—',
      revoked: true
    }],
    tokenScopes: {
      'read:threads': 'Read topics and boards',
      'read:posts': 'Read posts and reactions',
      'write:posts': 'Create posts on behalf of a member',
      'admin:users': 'Read and modify user records'
    },
    tags: [{
      id: 1,
      name: 'how-to',
      slug: 'how-to',
      desc: 'Practical guides',
      visibility: 'public',
      enabled: true
    }, {
      id: 2,
      name: 'rfc',
      slug: 'rfc',
      desc: 'Proposals for council',
      visibility: 'public',
      enabled: true
    }, {
      id: 3,
      name: 'archived-lore',
      slug: 'archived-lore',
      desc: 'Older reference',
      visibility: 'hidden',
      enabled: false
    }],
    handlers: [{
      pkg: 'imladris/anti-abuse',
      handler: 'post.scan',
      status: 'enabled',
      entrypoint: 'handlers/scan.php'
    }, {
      pkg: 'imladris/digest',
      handler: 'cron.digest',
      status: 'enabled',
      entrypoint: 'handlers/digest.php'
    }],
    runs: [{
      when: '1h ago',
      handler: 'post.scan',
      status: 'ok',
      detail: ''
    }, {
      when: '8h ago',
      handler: 'cron.digest',
      status: 'ok',
      detail: ''
    }, {
      when: 'yesterday',
      handler: 'post.scan',
      status: 'error',
      detail: 'sandbox timeout (5s)'
    }],
    badgeCatalogue: ['Welcome', 'First Thread', 'Trusted Answerer', 'Problem Solver', 'Loremaster of Evals']
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/admin/data.js", error: String((e && e.message) || e) }); }

// ui_kits/auth/AuthApp.jsx
try { (() => {
/* Auth UI kit — the six gate views (login, register, forgot, reset, mfa,
   verify), faithful to templates/auth/*. A top-right switcher (a kit
   affordance, not part of the product) jumps between them. */
(function () {
  function Brand() {
    const {
      EightPointStar
    } = window.ImladrisDesignSystem_c3e027;
    return /*#__PURE__*/React.createElement("span", {
      className: "auth-brand"
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 30
    }), /*#__PURE__*/React.createElement("span", {
      className: "auth-brand-name"
    }, "RetroBoards"));
  }
  const OAUTH = [{
    name: 'Google',
    glyph: 'G'
  }, {
    name: 'GitHub',
    glyph: 'GH'
  }, {
    name: 'Apple',
    glyph: ''
  }];
  function OAuth() {
    return /*#__PURE__*/React.createElement("div", {
      className: "oauth-buttons"
    }, /*#__PURE__*/React.createElement("p", {
      className: "oauth-sep"
    }, "or sign in with"), /*#__PURE__*/React.createElement("div", {
      className: "oauth-row"
    }, OAUTH.map(p => /*#__PURE__*/React.createElement("a", {
      key: p.name,
      className: "btn-oauth",
      href: "#",
      onClick: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement("span", {
      className: "oauth-glyph",
      "aria-hidden": "true"
    }, p.glyph), p.name))));
  }
  function Login({
    go
  }) {
    const {
      Input,
      Button
    } = window.ImladrisDesignSystem_c3e027;
    return /*#__PURE__*/React.createElement("div", {
      className: "auth-card"
    }, /*#__PURE__*/React.createElement("span", {
      className: "auth-eyebrow"
    }, "Welcome back to the council"), /*#__PURE__*/React.createElement("h1", null, "Log in"), /*#__PURE__*/React.createElement("form", {
      className: "auth-form",
      onSubmit: e => {
        e.preventDefault();
        go('mfa');
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Email"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "email",
      autoComplete: "username",
      defaultValue: "erestor@imladris.council",
      autoFocus: true
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Password"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      type: "submit"
    }, "Log in")), /*#__PURE__*/React.createElement(OAuth, null), /*#__PURE__*/React.createElement("div", {
      className: "auth-links"
    }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('forgot');
      }
    }, "Forgot your password?")), /*#__PURE__*/React.createElement("p", null, "New here? ", /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('register');
      }
    }, "Create an account"), ".")));
  }
  function Register({
    go
  }) {
    const {
      Input,
      Button
    } = window.ImladrisDesignSystem_c3e027;
    return /*#__PURE__*/React.createElement("div", {
      className: "auth-card wide"
    }, /*#__PURE__*/React.createElement("span", {
      className: "auth-eyebrow"
    }, "Take a seat at the table"), /*#__PURE__*/React.createElement("h1", null, "Create your account"), /*#__PURE__*/React.createElement("form", {
      className: "auth-form",
      onSubmit: e => {
        e.preventDefault();
        go('verifyPending');
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Username"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      maxLength: 32,
      autoFocus: true
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Display name ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(optional)")), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      maxLength: 64
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Email"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "email",
      autoComplete: "username"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Password"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "password",
      autoComplete: "new-password"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Confirm password"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "password",
      autoComplete: "new-password"
    })), /*#__PURE__*/React.createElement(Button, {
      type: "submit"
    }, "Sign up")), /*#__PURE__*/React.createElement("div", {
      className: "auth-links"
    }, /*#__PURE__*/React.createElement("p", null, "Already have an account? ", /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('login');
      }
    }, "Log in"), ".")));
  }
  function Forgot({
    go
  }) {
    const {
      Input,
      Button
    } = window.ImladrisDesignSystem_c3e027;
    const [sent, setSent] = React.useState(false);
    return /*#__PURE__*/React.createElement("div", {
      className: "auth-card"
    }, /*#__PURE__*/React.createElement("h1", null, "Reset your password"), sent ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "auth-lede",
      style: {
        marginTop: 8
      }
    }, "If an account exists for that email, we've sent a link to choose a new password. The link is valid for a limited time."), /*#__PURE__*/React.createElement("div", {
      className: "auth-links"
    }, /*#__PURE__*/React.createElement("p", null, "Didn't get it? Check your spam folder, or ", /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        setSent(false);
      }
    }, "try again"), "."), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('login');
      }
    }, "Back to log in")))) : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "auth-lede"
    }, "Enter your account's email address and we'll send you a link to choose a new password."), /*#__PURE__*/React.createElement("form", {
      className: "auth-form",
      onSubmit: e => {
        e.preventDefault();
        setSent(true);
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Email"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "email",
      autoComplete: "username",
      autoFocus: true
    })), /*#__PURE__*/React.createElement(Button, {
      type: "submit"
    }, "Send reset link")), /*#__PURE__*/React.createElement("div", {
      className: "auth-links"
    }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('login');
      }
    }, "Back to log in")))));
  }
  function Reset({
    go
  }) {
    const {
      Input,
      Button
    } = window.ImladrisDesignSystem_c3e027;
    return /*#__PURE__*/React.createElement("div", {
      className: "auth-card"
    }, /*#__PURE__*/React.createElement("h1", null, "Choose a new password"), /*#__PURE__*/React.createElement("p", {
      className: "auth-lede"
    }, "Pick something only you would know. You'll use it next time you log in."), /*#__PURE__*/React.createElement("form", {
      className: "auth-form",
      onSubmit: e => {
        e.preventDefault();
        go('login');
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "New password"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "password",
      autoComplete: "new-password",
      autoFocus: true
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Confirm new password"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "password",
      autoComplete: "new-password"
    })), /*#__PURE__*/React.createElement(Button, {
      type: "submit"
    }, "Update password")));
  }
  function Mfa({
    go
  }) {
    const {
      Input,
      Button
    } = window.ImladrisDesignSystem_c3e027;
    return /*#__PURE__*/React.createElement("div", {
      className: "auth-card"
    }, /*#__PURE__*/React.createElement("span", {
      className: "auth-eyebrow"
    }, "One more ward"), /*#__PURE__*/React.createElement("h1", null, "Two-factor verification"), /*#__PURE__*/React.createElement("p", {
      className: "auth-lede"
    }, "Enter the code from your authenticator, or a one-time recovery code."), /*#__PURE__*/React.createElement("form", {
      className: "auth-form",
      onSubmit: e => {
        e.preventDefault();
        go('verified');
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Authenticator or recovery code"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      inputMode: "numeric",
      autoComplete: "one-time-code",
      placeholder: "000000",
      autoFocus: true
    })), /*#__PURE__*/React.createElement(Button, {
      type: "submit"
    }, "Verify")), /*#__PURE__*/React.createElement("div", {
      className: "auth-links"
    }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('login');
      }
    }, "Back to log in"))));
  }
  function VerifyPending({
    go
  }) {
    return /*#__PURE__*/React.createElement("div", {
      className: "auth-card"
    }, /*#__PURE__*/React.createElement("div", {
      className: "auth-emblem"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M4 4h16v16H4z",
      fill: "none"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M22 6l-10 7L2 6"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M2 6h20v12H2z"
    }))), /*#__PURE__*/React.createElement("h1", null, "Confirm your email"), /*#__PURE__*/React.createElement("p", {
      className: "auth-lede"
    }, "We've sent a confirmation link to your inbox. Verifying keeps your account recoverable and unlocks your ", /*#__PURE__*/React.createElement("em", null, "Welcome"), " mark of esteem."), /*#__PURE__*/React.createElement("div", {
      className: "auth-links"
    }, /*#__PURE__*/React.createElement("p", null, "Already confirmed? ", /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('verified');
      }
    }, "Continue"), "."), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('login');
      }
    }, "Back to log in"))));
  }
  function Verified({
    go
  }) {
    return /*#__PURE__*/React.createElement("div", {
      className: "auth-card"
    }, /*#__PURE__*/React.createElement("div", {
      className: "auth-emblem"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "12",
      r: "10"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M8 12l3 3 5-6"
    }))), /*#__PURE__*/React.createElement("h1", null, "Email verified"), /*#__PURE__*/React.createElement("p", {
      className: "auth-lede"
    }, "Thanks \u2014 your email address is confirmed. Your seat at the council is ready."), /*#__PURE__*/React.createElement("div", {
      className: "auth-links"
    }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("a", {
      href: "../retroboards/index.html"
    }, "Go to the community \u2192"))));
  }
  const VIEWS = {
    login: Login,
    register: Register,
    forgot: Forgot,
    reset: Reset,
    mfa: Mfa,
    verifyPending: VerifyPending,
    verified: Verified
  };
  const SWITCH = [['login', 'Log in'], ['register', 'Sign up'], ['forgot', 'Forgot'], ['reset', 'Reset'], ['mfa', 'MFA'], ['verifyPending', 'Verify'], ['verified', 'Verified']];
  function App() {
    const {
      EightPointStar
    } = window.ImladrisDesignSystem_c3e027;
    const [view, setView] = React.useState('login');
    const View = VIEWS[view];
    return /*#__PURE__*/React.createElement("div", {
      className: "auth-stage"
    }, /*#__PURE__*/React.createElement("span", {
      className: "auth-stage-star",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 760,
      variant: "watermark",
      style: {
        opacity: 1,
        width: 760,
        height: 760
      }
    })), /*#__PURE__*/React.createElement("nav", {
      className: "auth-switch",
      "aria-label": "Auth views (kit demo)"
    }, SWITCH.map(([k, label]) => /*#__PURE__*/React.createElement("button", {
      key: k,
      className: view === k ? 'active' : '',
      onClick: () => setView(k)
    }, label))), /*#__PURE__*/React.createElement(Brand, null), /*#__PURE__*/React.createElement(View, {
      go: setView
    }), /*#__PURE__*/React.createElement("p", {
      className: "auth-colophon"
    }, "Et E\xE4rello Endorenna ut\xFAlien."));
  }
  window.RBAuthApp = App;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/auth/AuthApp.jsx", error: String((e && e.message) || e) }); }

// ui_kits/dm/Compose.jsx
try { (() => {
/* Messages kit — the new-message composer (right pane). Mirrors dm/new.php:
   recipients, an optional group title, and the body. */
(function () {
  const chev = /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 24 24",
    width: "13",
    height: "13",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2",
    strokeLinecap: "round",
    strokeLinejoin: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M15 18l-6-6 6-6"
  }));
  function Compose({
    onBack,
    onSend
  }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      Input,
      Textarea,
      Button
    } = DS;
    const [to, setTo] = React.useState('');
    const [title, setTitle] = React.useState('');
    const [body, setBody] = React.useState('');
    const isGroup = to.includes(',');
    return /*#__PURE__*/React.createElement("section", {
      className: "dm-compose"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-compose-wrap"
    }, /*#__PURE__*/React.createElement("button", {
      className: "breadcrumb",
      onClick: onBack
    }, chev, " Messages"), /*#__PURE__*/React.createElement("h1", null, "New message"), /*#__PURE__*/React.createElement("form", {
      className: "dm-form",
      onSubmit: e => {
        e.preventDefault();
        onSend();
      }
    }, /*#__PURE__*/React.createElement(Input, {
      label: "To",
      value: to,
      onChange: e => setTo(e.target.value),
      placeholder: "username, username",
      maxLength: 255
    }), /*#__PURE__*/React.createElement("p", {
      className: "field-hint"
    }, "Separate multiple usernames with commas to start a group."), isGroup ? /*#__PURE__*/React.createElement(Input, {
      label: "Group title",
      value: title,
      onChange: e => setTitle(e.target.value),
      placeholder: "Optional",
      maxLength: 120
    }) : null, /*#__PURE__*/React.createElement(Textarea, {
      label: "Message",
      rows: 6,
      value: body,
      onChange: e => setBody(e.target.value),
      placeholder: "Write your counsel\u2026",
      maxLength: 5000
    }), /*#__PURE__*/React.createElement("div", {
      className: "form-actions"
    }, /*#__PURE__*/React.createElement(Button, {
      type: "submit",
      disabled: !to.trim() || !body.trim()
    }, "Send message"), /*#__PURE__*/React.createElement(Button, {
      type: "button",
      variant: "ghost",
      onClick: onBack
    }, "Cancel")))));
  }
  window.DMCompose = Compose;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/dm/Compose.jsx", error: String((e && e.message) || e) }); }

// ui_kits/dm/ConvoList.jsx
try { (() => {
/* Messages kit — conversation list (left pane). Direct + group rows with
   monogram, last-message preview, unread marker, and a "New message" action. */
(function () {
  function ConvoList({
    conversations,
    activeId,
    onOpen,
    onNew,
    filter,
    onFilter
  }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      Button,
      Tabs,
      Monogram
    } = DS;
    const RBDM = window.RBDM;
    const shown = conversations.filter(c => filter === 'Unread' ? c.unread : true);
    return /*#__PURE__*/React.createElement("aside", {
      className: "dm-listpane"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-listpane-head"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-listpane-top"
    }, /*#__PURE__*/React.createElement("span", null, /*#__PURE__*/React.createElement("span", {
      className: "eyebrow"
    }, "Private counsel"), /*#__PURE__*/React.createElement("h1", null, "Messages")), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      onClick: onNew,
      icon: /*#__PURE__*/React.createElement("svg", {
        viewBox: "0 0 24 24",
        width: "14",
        height: "14",
        fill: "none",
        stroke: "currentColor",
        strokeWidth: "2",
        strokeLinecap: "round",
        strokeLinejoin: "round"
      }, /*#__PURE__*/React.createElement("path", {
        d: "M12 5v14M5 12h14"
      }))
    }, "New message")), /*#__PURE__*/React.createElement("div", {
      className: "dm-listpane-filters"
    }, /*#__PURE__*/React.createElement(Tabs, {
      variant: "segment",
      items: ['All', 'Unread'],
      value: filter,
      onChange: onFilter
    }))), shown.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "dm-list-empty"
    }, "No conversations here yet.") : /*#__PURE__*/React.createElement("ul", {
      className: "dm-list"
    }, shown.map(c => {
      const isGroup = c.kind === 'group';
      const other = isGroup ? c.title : RBDM.users[c.other].name;
      const seed = isGroup ? 'group-' + c.id : c.other;
      const presence = isGroup ? undefined : RBDM.users[c.other].presence;
      const groupMeta = isGroup ? c.members.filter(m => !m.left).map(m => RBDM.users[m.username].name).join(', ') : null;
      return /*#__PURE__*/React.createElement("li", {
        key: c.id
      }, /*#__PURE__*/React.createElement("button", {
        type: "button",
        className: 'dm-row' + (c.id === activeId ? ' active' : '') + (c.unread ? ' is-unread' : ''),
        onClick: () => onOpen(c.id)
      }, /*#__PURE__*/React.createElement(Monogram, {
        name: other,
        username: seed,
        size: "md",
        presence: presence,
        gilt: isGroup
      }), /*#__PURE__*/React.createElement("span", {
        className: "dm-row-top"
      }, c.unread ? /*#__PURE__*/React.createElement("span", {
        className: "unread-dot",
        "aria-label": "Unread"
      }) : null, /*#__PURE__*/React.createElement("span", {
        className: "dm-other"
      }, other)), /*#__PURE__*/React.createElement("span", {
        className: "dm-time"
      }, c.time), /*#__PURE__*/React.createElement("span", {
        className: "dm-preview"
      }, c.preview), groupMeta ? /*#__PURE__*/React.createElement("span", {
        className: "dm-group-meta"
      }, groupMeta) : null));
    })));
  }
  window.DMConvoList = ConvoList;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/dm/ConvoList.jsx", error: String((e && e.message) || e) }); }

// ui_kits/dm/DMApp.jsx
try { (() => {
/* Messages kit — app shell. Two-pane reading room: the conversation list beside
   the open letter (or the new-message composer). Holds open/read/reply state. */
(function () {
  function clone(x) {
    return JSON.parse(JSON.stringify(x));
  }
  function Empty() {
    const {
      EightPointStar
    } = window.ImladrisDesignSystem_c3e027;
    return /*#__PURE__*/React.createElement("section", {
      className: "dm-threadpane"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-empty"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-empty-inner"
    }, /*#__PURE__*/React.createElement("span", {
      className: "star"
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 54
    })), /*#__PURE__*/React.createElement("h2", null, "Choose a letter to read"), /*#__PURE__*/React.createElement("p", null, "Your private counsel opens here, beside the list. Pick a conversation, or begin a new message."))));
  }
  function DMApp() {
    const Topbar = window.DMTopbar;
    const ConvoList = window.DMConvoList;
    const Thread = window.DMThread;
    const Compose = window.DMCompose;
    const RBDM = window.RBDM;
    const [convos, setConvos] = React.useState(() => RBDM.conversations.map(clone));
    const [activeId, setActiveId] = React.useState(RBDM.conversations[0].id);
    const [mode, setMode] = React.useState('thread'); // thread | compose
    const [filter, setFilter] = React.useState('All');
    const [reply, setReply] = React.useState('');
    const [reading, setReading] = React.useState(false); // mobile single-pane

    // Mark the first conversation read on first paint.
    React.useEffect(() => {
      setConvos(prev => prev.map(c => c.id === RBDM.conversations[0].id ? {
        ...c,
        unread: false
      } : c));
    }, []);
    const active = convos.find(c => c.id === activeId) || null;
    function open(id) {
      setActiveId(id);
      setMode('thread');
      setReply('');
      setReading(true);
      setConvos(prev => prev.map(c => c.id === id ? {
        ...c,
        unread: false
      } : c));
    }
    function send() {
      const body = reply.trim();
      if (!body || !active) return;
      const msg = {
        id: Date.now(),
        from: RBDM.me,
        time: 'just now',
        body
      };
      setConvos(prev => prev.map(c => c.id === active.id ? {
        ...c,
        messages: [...c.messages, msg],
        preview: body
      } : c));
      setReply('');
    }
    let right;
    if (mode === 'compose') right = /*#__PURE__*/React.createElement(Compose, {
      onBack: () => setMode('thread'),
      onSend: () => setMode('thread')
    });else if (active) right = /*#__PURE__*/React.createElement(Thread, {
      convo: active,
      onBack: () => setReading(false),
      replyValue: reply,
      onReplyChange: setReply,
      onSend: send
    });else right = /*#__PURE__*/React.createElement(Empty, null);
    return /*#__PURE__*/React.createElement("div", {
      className: "app-root"
    }, /*#__PURE__*/React.createElement(Topbar, null), /*#__PURE__*/React.createElement("div", {
      className: 'dm-shell' + (reading ? ' reading' : '')
    }, /*#__PURE__*/React.createElement(ConvoList, {
      conversations: convos,
      activeId: mode === 'thread' ? activeId : null,
      onOpen: open,
      onNew: () => {
        setMode('compose');
        setReading(true);
      },
      filter: filter,
      onFilter: setFilter
    }), right));
  }
  window.DMApp = DMApp;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/dm/DMApp.jsx", error: String((e && e.message) || e) }); }

// ui_kits/dm/DMTopbar.jsx
try { (() => {
/* Messages kit — top bar (member register, mirrors RetroBoards). Static chrome;
   brand returns to the inbox. */
(function () {
  function DMTopbar() {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      EightPointStar,
      Input,
      Monogram
    } = DS;
    const me = window.RBDM.users[window.RBDM.me];
    return /*#__PURE__*/React.createElement("header", {
      className: "topbar"
    }, /*#__PURE__*/React.createElement("div", {
      className: "topbar-inner"
    }, /*#__PURE__*/React.createElement("a", {
      className: "brand",
      href: "../retroboards/index.html"
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 26
    }), /*#__PURE__*/React.createElement("span", {
      className: "brand-name"
    }, "RetroBoards")), /*#__PURE__*/React.createElement("form", {
      className: "topbar-search",
      onSubmit: e => e.preventDefault(),
      role: "search"
    }, /*#__PURE__*/React.createElement(Input, {
      pill: true,
      type: "search",
      placeholder: "Search the council\u2026",
      "aria-label": "Search the council"
    })), /*#__PURE__*/React.createElement("div", {
      className: "topbar-right"
    }, /*#__PURE__*/React.createElement("span", {
      className: "bell",
      title: "Notifications"
    }, /*#__PURE__*/React.createElement("svg", {
      className: "bell-ic",
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M10.3 21a1.94 1.94 0 0 0 3.4 0"
    })), /*#__PURE__*/React.createElement("span", {
      className: "bell-dot",
      "aria-hidden": "true"
    })), /*#__PURE__*/React.createElement("span", {
      className: "topbar-user"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: me.name,
      username: me.username,
      size: "sm",
      presence: "online"
    }), /*#__PURE__*/React.createElement("span", {
      className: "topbar-name"
    }, me.name)), /*#__PURE__*/React.createElement("svg", {
      className: "topbar-ic",
      viewBox: "0 0 24 24",
      "aria-hidden": "true",
      title: "Settings"
    }, /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "12",
      r: "3"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H2a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 3.6 8a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H8a1.65 1.65 0 0 0 1-1.51V2a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H22a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"
    })), /*#__PURE__*/React.createElement("button", {
      className: "topbar-logout",
      type: "button"
    }, "Log out"))));
  }
  window.DMTopbar = DMTopbar;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/dm/DMTopbar.jsx", error: String((e && e.message) || e) }); }

// ui_kits/dm/Thread.jsx
try { (() => {
/* Messages kit — the open conversation (right pane). Header (direct / group),
   the group-members panel with owner tools, the message stream with reference
   cards + report affordance, and the pinned composer. */
(function () {
  const chev = /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 24 24",
    width: "13",
    height: "13",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2",
    strokeLinecap: "round",
    strokeLinejoin: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M15 18l-6-6 6-6"
  }));
  const label = code => code.charAt(0).toUpperCase() + code.slice(1).replace(/_/g, ' ');
  function Thread({
    convo,
    onBack,
    replyValue,
    onReplyChange,
    onSend
  }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      Composer,
      Monogram,
      Input,
      Button
    } = DS;
    const RBDM = window.RBDM;
    const scrollRef = React.useRef(null);
    React.useEffect(() => {
      const el = scrollRef.current;
      if (!el) return;
      const pin = () => {
        el.scrollTop = el.scrollHeight;
      };
      pin();
      requestAnimationFrame(pin);
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(pin);
      const t = setTimeout(pin, 250);
      return () => clearTimeout(t);
    }, [convo.id, convo.messages.length]);
    const isGroup = convo.kind === 'group';
    const active = isGroup ? convo.members.filter(m => !m.left) : [];
    const isOwner = isGroup && (convo.members.find(m => m.role === 'owner') || {}).username === RBDM.me;
    const other = isGroup ? null : RBDM.users[convo.other];
    const title = isGroup ? convo.title : other.name;
    const seed = isGroup ? 'group-' + convo.id : convo.other;
    return /*#__PURE__*/React.createElement("section", {
      className: "dm-threadpane"
    }, /*#__PURE__*/React.createElement("header", {
      className: "dm-thread-head"
    }, /*#__PURE__*/React.createElement("button", {
      className: "breadcrumb",
      onClick: onBack
    }, chev, " Messages"), /*#__PURE__*/React.createElement("div", {
      className: "dm-thread-title-row"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-thread-id"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: title,
      username: seed,
      size: "lg",
      gilt: true,
      presence: other ? other.presence : undefined
    }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h1", {
      className: "dm-thread-title"
    }, isGroup ? title : /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => e.preventDefault()
    }, title)), /*#__PURE__*/React.createElement("p", {
      className: "dm-thread-sub"
    }, isGroup ? active.length + ' active members' : '@' + other.username + ' · ' + other.presence))), isGroup ? /*#__PURE__*/React.createElement("div", {
      className: "dm-thread-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: "dm-head-btn",
      type: "button"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M11 5 6 9H2v6h4l5 4z"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M22 9l-6 6M16 9l6 6"
    })), " Mute"), /*#__PURE__*/React.createElement("button", {
      className: "dm-head-btn danger",
      type: "button"
    }, "Leave")) : null)), isGroup ? /*#__PURE__*/React.createElement("section", {
      className: "dm-group-panel"
    }, /*#__PURE__*/React.createElement("h2", null, "Members"), /*#__PURE__*/React.createElement("ul", {
      className: "dm-members"
    }, convo.members.map(m => /*#__PURE__*/React.createElement("li", {
      key: m.username,
      className: 'dm-member' + (m.left ? ' is-left' : '')
    }, /*#__PURE__*/React.createElement("span", {
      className: "handle"
    }, "@", m.username), m.role === 'owner' ? /*#__PURE__*/React.createElement("span", {
      className: "role"
    }, "Owner") : null, m.left ? /*#__PURE__*/React.createElement("span", {
      className: "role"
    }, "left") : null, isOwner && !m.left && m.role !== 'owner' ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
      className: "linkbtn danger",
      type: "button"
    }, "Remove"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Make owner")) : null))), isOwner ? /*#__PURE__*/React.createElement("div", {
      className: "dm-owner-tools"
    }, /*#__PURE__*/React.createElement("form", {
      className: "dm-owner-tools",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement(Input, {
      placeholder: "username",
      maxLength: 32,
      style: {
        maxWidth: 150
      }
    }), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, "Add member")), /*#__PURE__*/React.createElement("form", {
      className: "dm-owner-tools",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement(Input, {
      defaultValue: convo.title,
      maxLength: 120,
      style: {
        maxWidth: 180
      }
    }), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, "Rename"))) : null) : null, /*#__PURE__*/React.createElement("div", {
      className: "dm-scroll",
      ref: scrollRef
    }, /*#__PURE__*/React.createElement("span", {
      className: "dm-day"
    }, "Beginning of your counsel"), convo.messages.map(m => {
      const mine = m.from === RBDM.me;
      const from = RBDM.users[m.from];
      return /*#__PURE__*/React.createElement("div", {
        key: m.id,
        className: 'dm-message' + (mine ? ' dm-mine' : '')
      }, /*#__PURE__*/React.createElement("div", {
        className: "dm-message-head"
      }, /*#__PURE__*/React.createElement("span", {
        className: "dm-author"
      }, mine ? 'You' : from.name), /*#__PURE__*/React.createElement("span", {
        className: "post-time"
      }, m.time)), /*#__PURE__*/React.createElement("div", {
        className: "dm-bubble"
      }, /*#__PURE__*/React.createElement("p", null, m.body)), m.refs ? /*#__PURE__*/React.createElement("div", {
        className: "reference-cards",
        "aria-label": "Referenced content"
      }, m.refs.map((r, i) => /*#__PURE__*/React.createElement("a", {
        key: i,
        className: "reference-card",
        href: r.url,
        onClick: e => e.preventDefault()
      }, /*#__PURE__*/React.createElement("span", {
        className: "badge badge-muted"
      }, r.type), /*#__PURE__*/React.createElement("strong", null, r.title), r.meta ? /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, r.meta) : null))) : null, !mine ? /*#__PURE__*/React.createElement("details", {
        className: "dm-report"
      }, /*#__PURE__*/React.createElement("summary", null, "Report"), /*#__PURE__*/React.createElement("form", {
        className: "dm-report-form",
        onSubmit: e => e.preventDefault()
      }, /*#__PURE__*/React.createElement("select", {
        className: "input input-small",
        "aria-label": "Reason"
      }, RBDM.reportReasons.map(rc => /*#__PURE__*/React.createElement("option", {
        key: rc,
        value: rc
      }, label(rc)))), /*#__PURE__*/React.createElement(Input, {
        placeholder: "Details (optional)",
        maxLength: 255,
        style: {
          flex: 1,
          minWidth: 120
        }
      }), /*#__PURE__*/React.createElement(Button, {
        size: "sm",
        variant: "danger"
      }, "Report message"))) : null);
    })), /*#__PURE__*/React.createElement("div", {
      className: "dm-composer"
    }, /*#__PURE__*/React.createElement(Composer, {
      toolbar: false,
      sendLabel: "Send",
      placeholder: "Write a message\u2026",
      value: replyValue,
      onChange: e => onReplyChange(e.target.value),
      count: (replyValue ? replyValue.length : 0) + ' / 5000',
      onSubmit: e => {
        e.preventDefault();
        onSend();
      }
    })));
  }
  window.DMThread = Thread;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/dm/Thread.jsx", error: String((e && e.message) || e) }); }

// ui_kits/dm/data.js
try { (() => {
/* Messages kit — seed data for private counsel (direct + group conversations).
   Same Imladris roster register as RetroBoards. Shared via window.RBDM. */
(function () {
  const users = {
    erestor: {
      username: 'erestor',
      name: 'Erestor',
      presence: 'online'
    },
    galadriel: {
      username: 'galadriel',
      name: 'Galadriel',
      presence: 'online'
    },
    elrond: {
      username: 'elrond',
      name: 'Elrond',
      presence: 'online'
    },
    glorfindel: {
      username: 'glorfindel',
      name: 'Glorfindel',
      presence: 'away'
    },
    arwen: {
      username: 'arwen',
      name: 'Arwen',
      presence: 'online'
    },
    lindir: {
      username: 'lindir',
      name: 'Lindir',
      presence: 'offline'
    }
  };
  const me = 'erestor';

  /* Each conversation: direct (with `other`) or group (with `title` + `members`).
     `messages` are ordered oldest→newest; `mine` is derived in the view. */
  const conversations = [{
    id: 1,
    kind: 'direct',
    other: 'galadriel',
    unread: true,
    time: '9m',
    preview: 'Send me the rollback drill — Glorfindel will want it for the wardens.',
    messages: [{
      id: 11,
      from: 'galadriel',
      time: 'Yesterday 18:40',
      body: 'Erestor — I read your note on audit trails before the council met. It holds. The three questions are the right ones.'
    }, {
      id: 12,
      from: 'erestor',
      time: 'Yesterday 19:02',
      body: 'Then it is ready to record. I will mark the accepted answer and link the written verdict from the topic.',
      refs: [{
        type: 'Topic',
        title: 'Who changed what — and can you prove the rollback?',
        meta: '#audit-trails · 41 replies',
        url: '#'
      }]
    }, {
      id: 13,
      from: 'galadriel',
      time: '9m',
      body: 'Do that. And send me the rollback drill — Glorfindel will want it for the wardens.'
    }]
  }, {
    id: 2,
    kind: 'group',
    title: 'Vilya · wardens',
    unread: true,
    time: '1h',
    members: [{
      username: 'erestor',
      role: 'owner'
    }, {
      username: 'elrond',
      role: 'member'
    }, {
      username: 'glorfindel',
      role: 'member'
    }, {
      username: 'arwen',
      role: 'member'
    }, {
      username: 'lindir',
      role: 'member',
      left: true
    }],
    preview: 'Glorfindel: the rollback drill is set for Tuesday. Bring the audit trail.',
    messages: [{
      id: 21,
      from: 'elrond',
      time: '3h',
      body: 'Wardens — we keep counsel here on what does not yet belong in the open hall. Verify before you carry it further.'
    }, {
      id: 22,
      from: 'arwen',
      time: '2h',
      body: 'Understood. I have the eval verdicts ready to read; they resolve cleanly into artifacts now.'
    }, {
      id: 23,
      from: 'glorfindel',
      time: '1h',
      body: 'The rollback drill is set for Tuesday. Bring the audit trail — I want precedence recorded this time.'
    }]
  }, {
    id: 3,
    kind: 'direct',
    other: 'elrond',
    unread: false,
    time: '3h',
    preview: 'Recorded. I will amend the charter to say so plainly.',
    messages: [{
      id: 31,
      from: 'erestor',
      time: 'Today 09:10',
      body: 'The charter should say that testimony never outranks the work. People keep forgetting the order.'
    }, {
      id: 32,
      from: 'elrond',
      time: '3h',
      body: 'Recorded. I will amend the charter to say so plainly.'
    }]
  }, {
    id: 4,
    kind: 'direct',
    other: 'arwen',
    unread: false,
    time: 'Yesterday',
    preview: 'The accepted answer reads well now. Thank you for the gilt.',
    messages: [{
      id: 41,
      from: 'arwen',
      time: 'Yesterday',
      body: 'The accepted answer reads well now. Thank you for the gilt.'
    }]
  }, {
    id: 5,
    kind: 'direct',
    other: 'glorfindel',
    unread: false,
    time: '2d',
    preview: 'Two actors could edit one setting with no record of precedence. Fixed.',
    messages: [{
      id: 51,
      from: 'glorfindel',
      time: '2 days ago',
      body: 'Two actors could edit one setting with no record of precedence. Fixed now — the warden log keeps order.'
    }]
  }, {
    id: 6,
    kind: 'direct',
    other: 'lindir',
    unread: false,
    time: '5d',
    preview: 'Thank you for the three topics. I have read all of them twice.',
    messages: [{
      id: 61,
      from: 'lindir',
      time: '5 days ago',
      body: 'Thank you for the three topics. I have read all of them twice. The songs will keep them.'
    }]
  }];

  // Reasons offered when reporting a message (mapped to readable labels in the view).
  const reportReasons = ['spam', 'harassment', 'off_topic', 'other'];
  window.RBDM = {
    users,
    me,
    conversations,
    reportReasons
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/dm/data.js", error: String((e && e.message) || e) }); }

// ui_kits/mod/ModApp.jsx
try { (() => {
/* Moderation kit — app shell. Topbar + mod-head + horizontal subnav with live
   queue counts + section routing. Holds the triage state so claim/resolve/
   approve actions update the queues and the counts. */
(function () {
  function clone(x) {
    return JSON.parse(JSON.stringify(x));
  }
  function ModApp() {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      EightPointStar,
      Monogram
    } = DS;
    const SECT = window.RBModSections;
    const M = window.RBMod;
    const mod = M.moderator;
    const [active, setActive] = React.useState('reports');
    const [reports, setReports] = React.useState(() => clone(M.reports));
    const [approvals, setApprovals] = React.useState(() => clone(M.approvals));
    const [appeals, setAppeals] = React.useState(() => clone(M.appeals));

    // Reports: claim keeps the row; resolve/dismiss closes it.
    function actReport(id, kind) {
      setReports(prev => prev.map(r => r.id === id ? {
        ...r,
        done: kind
      } : r));
    }
    // Approvals: approve or reject removes the held item from the queue.
    function resolveApproval(type, id) {
      setApprovals(prev => ({
        threads: type === 'thread' ? prev.threads.filter(x => x.id !== id) : prev.threads,
        posts: type === 'post' ? prev.posts.filter(x => x.id !== id) : prev.posts
      }));
    }
    // Appeals: record an outcome + note; row stays, marked resolved.
    function resolveAppeal(id, outcome, note) {
      setAppeals(prev => prev.map(a => a.id === id ? {
        ...a,
        done: true,
        outcome,
        note
      } : a));
    }
    const openReports = reports.filter(r => !r.done || r.done === 'claimed').length;
    const urgentReports = reports.some(r => r.reason_code === 'harassment' && (!r.done || r.done === 'claimed'));
    const pendingApprovals = approvals.threads.length + approvals.posts.length;
    const openAppeals = appeals.filter(a => !a.done).length;
    const NAV = [{
      key: 'reports',
      label: 'Reports',
      count: openReports,
      urgent: urgentReports
    }, {
      key: 'approvals',
      label: 'Approval hold',
      count: pendingApprovals
    }, {
      key: 'appeals',
      label: 'Appeals',
      count: openAppeals
    }, {
      key: 'member',
      label: 'Member view',
      count: null
    }];
    let pane;
    if (active === 'reports') pane = /*#__PURE__*/React.createElement(SECT.Reports, {
      reports: reports,
      onAct: actReport
    });else if (active === 'approvals') pane = /*#__PURE__*/React.createElement(SECT.Approvals, {
      approvals: approvals,
      onResolve: resolveApproval
    });else if (active === 'appeals') pane = /*#__PURE__*/React.createElement(SECT.Appeals, {
      appeals: appeals,
      onResolve: resolveAppeal
    });else pane = /*#__PURE__*/React.createElement(SECT.MemberAppeal, null);
    return /*#__PURE__*/React.createElement("div", {
      className: "app-root"
    }, /*#__PURE__*/React.createElement("header", {
      className: "topbar"
    }, /*#__PURE__*/React.createElement("div", {
      className: "topbar-inner"
    }, /*#__PURE__*/React.createElement("a", {
      className: "brand",
      href: "../retroboards/index.html"
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 26
    }), /*#__PURE__*/React.createElement("span", {
      className: "brand-name"
    }, "RetroBoards")), /*#__PURE__*/React.createElement("a", {
      className: "topbar-back",
      href: "../retroboards/index.html"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M15 18l-6-6 6-6"
    })), " ", /*#__PURE__*/React.createElement("span", null, "Back to the inbox")), /*#__PURE__*/React.createElement("div", {
      className: "topbar-right"
    }, /*#__PURE__*/React.createElement("span", {
      className: "topbar-user"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: mod.name,
      username: mod.username,
      size: "sm",
      presence: "online",
      gilt: true
    }), /*#__PURE__*/React.createElement("span", {
      className: "topbar-name"
    }, mod.name))))), /*#__PURE__*/React.createElement("div", {
      className: "mod"
    }, /*#__PURE__*/React.createElement("div", {
      className: "mod-head"
    }, /*#__PURE__*/React.createElement("span", null, /*#__PURE__*/React.createElement("span", {
      className: "eyebrow"
    }, "The warden's table"), /*#__PURE__*/React.createElement("h1", null, "Moderation")), /*#__PURE__*/React.createElement("span", {
      className: "pill mod-pill"
    }, "Moderator")), /*#__PURE__*/React.createElement("nav", {
      className: "mod-subnav",
      "aria-label": "Moderation queues"
    }, NAV.map(n => /*#__PURE__*/React.createElement("button", {
      key: n.key,
      className: n.key === active ? 'active' : '',
      "aria-current": n.key === active ? 'page' : undefined,
      onClick: () => setActive(n.key)
    }, n.label, n.count != null && n.count > 0 ? /*#__PURE__*/React.createElement("span", {
      className: 'mod-count' + (n.urgent ? ' is-urgent' : '')
    }, n.count) : null))), /*#__PURE__*/React.createElement("div", {
      className: "mod-pane-wrap",
      key: active
    }, pane)));
  }
  window.RBModApp = ModApp;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/mod/ModApp.jsx", error: String((e && e.message) || e) }); }

// ui_kits/mod/ModSections.jsx
try { (() => {
/* Moderation kit — the four triage panes. Faithful to the mod/* templates,
   composed from design-system primitives. Each is a component fed queue state
   + handlers by the shell. */
(function () {
  const DS = () => window.ImladrisDesignSystem_c3e027;
  const check = /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 24 24",
    "aria-hidden": "true"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M20 6L9 17l-5-5"
  }));

  /* ── Reports queue (mod/reports) ──────────────────────────────────────── */
  function Reports({
    reports,
    onAct
  }) {
    const {
      Badge,
      Tag,
      Button
    } = DS();
    const M = window.RBMod;
    const live = reports.filter(r => !r.done || r.done === 'claimed');
    return /*#__PURE__*/React.createElement("section", {
      className: "mod-pane"
    }, /*#__PURE__*/React.createElement("header", {
      className: "board-header"
    }, /*#__PURE__*/React.createElement("h1", null, "Reports queue"), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Open and claimed reports in your scope. Claim one to take it off the shared pile, then resolve or dismiss.")), reports.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted empty"
    }, "No open reports. Nice and quiet.") : /*#__PURE__*/React.createElement("ul", {
      className: "report-list"
    }, reports.map(r => {
      const urgent = r.reason_code === 'harassment';
      const claimed = r.done === 'claimed';
      const closed = r.done === 'resolved' || r.done === 'dismissed';
      const status = closed ? r.done : claimed ? 'claimed' : r.status;
      return /*#__PURE__*/React.createElement("li", {
        key: r.id,
        className: 'report-row' + (urgent && !closed ? ' is-urgent' : r.status === 'open' && !closed ? ' is-open' : '')
      }, /*#__PURE__*/React.createElement("div", {
        className: "report-head"
      }, /*#__PURE__*/React.createElement(Badge, {
        variant: status === 'triaged' || claimed ? 'op' : 'muted'
      }, status), r.reason_code ? /*#__PURE__*/React.createElement(Tag, null, M.reasonLabels[r.reason_code] || r.reason_code) : null, /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "by ", r.reporter_username, " \xB7 ", r.created_at)), r.post ? /*#__PURE__*/React.createElement("p", {
        className: "report-target"
      }, /*#__PURE__*/React.createElement("a", {
        href: "#",
        onClick: e => e.preventDefault()
      }, r.post.thread_title)) : /*#__PURE__*/React.createElement("p", {
        className: "report-target"
      }, /*#__PURE__*/React.createElement("em", null, r.dm.conversation_title, " \xB7 message #", r.dm.message_id, " from ", r.dm.sender_display, " (@", r.dm.sender_username, ")")), /*#__PURE__*/React.createElement("blockquote", {
        className: "report-excerpt"
      }, r.post ? r.post.body : r.dm.body), r.reason ? /*#__PURE__*/React.createElement("p", {
        className: "report-note"
      }, r.reason) : null, /*#__PURE__*/React.createElement("div", {
        className: "report-actions"
      }, closed ? /*#__PURE__*/React.createElement("span", {
        className: "resolved-tag"
      }, check, " ", r.done === 'resolved' ? 'Resolved' : 'Dismissed') : /*#__PURE__*/React.createElement(React.Fragment, null, !claimed ? /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        onClick: () => onAct(r.id, 'claimed')
      }, "Claim") : /*#__PURE__*/React.createElement("span", {
        className: "muted",
        style: {
          fontFamily: 'var(--font-label)',
          fontSize: '.78rem'
        }
      }, "Claimed by you"), /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        onClick: () => onAct(r.id, 'resolved')
      }, "Resolve"), /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        onClick: () => onAct(r.id, 'dismissed')
      }, "Dismiss"))));
    })));
  }

  /* ── Approval hold (mod/approvals) ────────────────────────────────────── */
  function Approvals({
    approvals,
    onResolve
  }) {
    const {
      Button
    } = DS();
    const t = approvals.threads,
      p = approvals.posts;
    return /*#__PURE__*/React.createElement("section", {
      className: "mod-pane"
    }, /*#__PURE__*/React.createElement("header", {
      className: "board-header"
    }, /*#__PURE__*/React.createElement("h1", null, "Approval queue"), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Content held by anti-abuse rules or board approval. Approving publishes it and runs the normal counters and notifications; rejecting removes it.")), /*#__PURE__*/React.createElement("div", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Topics awaiting approval"), t.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "No topics are awaiting approval.") : /*#__PURE__*/React.createElement("ul", {
      className: "approval-list"
    }, t.map(x => /*#__PURE__*/React.createElement("li", {
      key: x.id,
      className: "approval-item"
    }, /*#__PURE__*/React.createElement("div", {
      className: "approval-meta"
    }, /*#__PURE__*/React.createElement("strong", null, x.title), /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "by @", x.author_username, " in #", x.board_slug, " \xB7 ", x.created_at, " UTC")), /*#__PURE__*/React.createElement("div", {
      className: "approval-actions"
    }, /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      onClick: () => onResolve('thread', x.id)
    }, "Approve"), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary",
      onClick: () => onResolve('thread', x.id)
    }, "Reject")))))), /*#__PURE__*/React.createElement("div", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Replies awaiting approval"), p.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "No replies are awaiting approval.") : /*#__PURE__*/React.createElement("ul", {
      className: "approval-list"
    }, p.map(x => /*#__PURE__*/React.createElement("li", {
      key: x.id,
      className: "approval-item"
    }, /*#__PURE__*/React.createElement("div", {
      className: "approval-meta"
    }, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => e.preventDefault()
    }, x.thread_title), /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "reply by @", x.author_username, " in #", x.board_slug, " \xB7 ", x.created_at, " UTC"), /*#__PURE__*/React.createElement("p", null, x.body)), /*#__PURE__*/React.createElement("div", {
      className: "approval-actions"
    }, /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      onClick: () => onResolve('post', x.id)
    }, "Approve"), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary",
      onClick: () => onResolve('post', x.id)
    }, "Reject")))))));
  }

  /* ── Appeals review (mod/appeals) ─────────────────────────────────────── */
  function Appeals({
    appeals,
    onResolve
  }) {
    const {
      Badge,
      Textarea,
      Button
    } = DS();
    const M = window.RBMod;
    return /*#__PURE__*/React.createElement("section", {
      className: "mod-pane"
    }, /*#__PURE__*/React.createElement("header", {
      className: "board-header"
    }, /*#__PURE__*/React.createElement("h1", null, "Appeals queue"), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Open appeals in your moderation scope. Record an outcome and a note; the appellant is notified.")), appeals.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted empty"
    }, "No open appeals.") : /*#__PURE__*/React.createElement("ul", {
      className: "report-list"
    }, appeals.map(a => /*#__PURE__*/React.createElement("li", {
      key: a.id,
      className: 'report-row' + (a.done ? '' : ' is-open')
    }, /*#__PURE__*/React.createElement("div", {
      className: "report-head"
    }, /*#__PURE__*/React.createElement(Badge, {
      variant: a.done ? 'muted' : 'op'
    }, a.done ? a.outcome : a.status), /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "by ", a.appellant_username, " \xB7 ", a.created_at)), /*#__PURE__*/React.createElement("p", {
      className: "report-target"
    }, a.target_type, " #", a.target_id, " \xB7 ", a.original_action), a.target_summary ? /*#__PURE__*/React.createElement("blockquote", {
      className: "report-excerpt"
    }, a.target_summary) : null, /*#__PURE__*/React.createElement("p", {
      style: {
        margin: '0 0 4px',
        lineHeight: 1.55
      }
    }, a.reason), a.done ? /*#__PURE__*/React.createElement("p", {
      className: "resolution-note"
    }, /*#__PURE__*/React.createElement("strong", null, "Resolution:"), " ", a.note || 'Marked ' + a.outcome + '.') : /*#__PURE__*/React.createElement(AppealResolver, {
      outcomes: M.outcomes,
      onResolve: (outcome, note) => onResolve(a.id, outcome, note)
    })))));
  }
  function AppealResolver({
    outcomes,
    onResolve
  }) {
    const {
      Textarea,
      Button
    } = DS();
    const [outcome, setOutcome] = React.useState(outcomes[0]);
    const [note, setNote] = React.useState('');
    return /*#__PURE__*/React.createElement("form", {
      className: "appeal-resolve",
      onSubmit: e => {
        e.preventDefault();
        onResolve(outcome, note);
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Outcome"), /*#__PURE__*/React.createElement("select", {
      className: "input",
      value: outcome,
      onChange: e => setOutcome(e.target.value)
    }, outcomes.map(o => /*#__PURE__*/React.createElement("option", {
      key: o,
      value: o
    }, o.charAt(0).toUpperCase() + o.slice(1))))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Resolution note"), /*#__PURE__*/React.createElement(Textarea, {
      rows: 2,
      value: note,
      onChange: e => setNote(e.target.value),
      placeholder: "What you decided, and why."
    })), /*#__PURE__*/React.createElement(Button, {
      type: "submit",
      size: "sm"
    }, "Resolve appeal"));
  }

  /* ── Member appeal view (appeals/index) — what the appellant sees ─────── */
  function MemberAppeal() {
    const {
      Badge,
      Textarea,
      Button
    } = DS();
    const M = window.RBMod;
    const e = M.myAppeals.eligible;
    const has = e.posts.length || e.logs.length;
    return /*#__PURE__*/React.createElement("section", {
      className: "mod-pane"
    }, /*#__PURE__*/React.createElement("header", {
      className: "board-header"
    }, /*#__PURE__*/React.createElement("h1", null, "Appeals"), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "The member's own view. Appeal forms appear beside each eligible moderation action; resolved appeals show the outcome and note.")), has ? /*#__PURE__*/React.createElement("div", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Appealable actions"), /*#__PURE__*/React.createElement("ul", {
      className: "report-list",
      style: {
        marginTop: 8
      }
    }, e.posts.map(post => /*#__PURE__*/React.createElement("li", {
      key: 'p' + post.id,
      className: "report-row"
    }, /*#__PURE__*/React.createElement("div", {
      className: "report-head"
    }, /*#__PURE__*/React.createElement(Badge, {
      variant: "muted"
    }, "post removed"), /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: ev => ev.preventDefault(),
      style: {
        color: 'var(--brand)'
      }
    }, post.thread_title), " \xB7 ", post.deleted_at)), /*#__PURE__*/React.createElement("blockquote", {
      className: "report-excerpt"
    }, post.body), /*#__PURE__*/React.createElement("form", {
      className: "appeal-form",
      onSubmit: ev => ev.preventDefault()
    }, /*#__PURE__*/React.createElement("label", null, "Reason"), /*#__PURE__*/React.createElement(Textarea, {
      rows: 3,
      maxLength: 2000,
      placeholder: "Why this should be reconsidered.",
      required: true
    }), /*#__PURE__*/React.createElement(Button, {
      type: "submit",
      size: "sm"
    }, "Submit appeal")))), e.logs.map(log => /*#__PURE__*/React.createElement("li", {
      key: 'l' + log.id,
      className: "report-row"
    }, /*#__PURE__*/React.createElement("div", {
      className: "report-head"
    }, /*#__PURE__*/React.createElement(Badge, {
      variant: "muted"
    }, log.action), /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, log.created_at)), log.reason ? /*#__PURE__*/React.createElement("blockquote", {
      className: "report-excerpt"
    }, log.reason) : null, /*#__PURE__*/React.createElement("form", {
      className: "appeal-form",
      onSubmit: ev => ev.preventDefault()
    }, /*#__PURE__*/React.createElement("label", null, "Reason"), /*#__PURE__*/React.createElement(Textarea, {
      rows: 3,
      maxLength: 2000,
      placeholder: "Why this should be reconsidered.",
      required: true
    }), /*#__PURE__*/React.createElement(Button, {
      type: "submit",
      size: "sm"
    }, "Submit appeal")))))) : null, /*#__PURE__*/React.createElement("div", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Your appeals"), M.myAppeals.submitted.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "No appeals yet. Appeal forms appear next to eligible moderation actions.") : /*#__PURE__*/React.createElement("ul", {
      className: "report-list",
      style: {
        marginTop: 8
      }
    }, M.myAppeals.submitted.map(a => /*#__PURE__*/React.createElement("li", {
      key: a.id,
      className: "report-row"
    }, /*#__PURE__*/React.createElement("div", {
      className: "report-head"
    }, /*#__PURE__*/React.createElement(Badge, {
      variant: a.status === 'reversed' || a.status === 'modified' ? 'op' : 'muted'
    }, a.status), /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, a.target_type, " #", a.target_id, " \xB7 ", a.created_at)), a.target_summary ? /*#__PURE__*/React.createElement("p", {
      className: "report-target",
      style: {
        fontSize: '.98rem'
      }
    }, a.target_summary) : null, /*#__PURE__*/React.createElement("blockquote", {
      className: "report-excerpt"
    }, a.reason), a.resolution_note ? /*#__PURE__*/React.createElement("p", {
      className: "resolution-note"
    }, /*#__PURE__*/React.createElement("strong", null, "Resolution:"), " ", a.resolution_note) : null)))));
  }
  window.RBModSections = {
    Reports,
    Approvals,
    Appeals,
    MemberAppeal
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/mod/ModSections.jsx", error: String((e && e.message) || e) }); }

// ui_kits/mod/data.js
try { (() => {
/* Moderation kit — seed data for the warden's table. The triage side of
   RetroBoards: reports, the approval hold, appeals review, and a member's own
   appeal view. Imladris council register. Shared via window.RBMod.
   Mirrors templates/mod/{reports,approvals,appeals}.php + appeals/index.php. */
(function () {
  const moderator = {
    name: 'Glorfindel',
    username: 'glorfindel'
  };

  // Reports queue (mod/reports). Targets are either a post-in-thread or a DM message.
  const reports = [{
    id: 412,
    status: 'open',
    reason_code: 'harassment',
    reporter_username: 'lindir',
    created_at: '11 minutes ago',
    post: {
      thread_id: 88,
      thread_slug: 'on-the-naming-of-wards',
      thread_title: 'On the naming of wards',
      body: 'You weren\u2019t in the room when the rollback failed, so spare us the lecture. People who actually ship don\u2019t need your ceremony.'
    },
    reason: 'Tone aimed at a person, not the argument. Second time this week from the same account.'
  }, {
    id: 409,
    status: 'triaged',
    reason_code: 'spam',
    reporter_username: 'arwen',
    created_at: '40 minutes ago',
    post: {
      thread_id: 73,
      thread_slug: 'eval-harness-v2',
      thread_title: 'Eval harness v2 \u2014 call for testers',
      body: 'Boost your council standing FAST \u2014 commendations, badges, leaderboard rank. Visit my profile for the link, first ten are free\u2026'
    },
    reason: 'Same copy posted in four topics. Link farm.'
  }, {
    id: 406,
    status: 'open',
    reason_code: 'harassment',
    reporter_username: 'arwen',
    created_at: '2 hours ago',
    dm: {
      conversation_title: 'Direct message',
      kind: 'direct',
      message_id: 1841,
      sender_display: 'unknown',
      sender_username: 'mellon',
      body: 'I know which boards you moderate. Keep removing my posts and we\u2019ll see how long that lasts.'
    },
    reason: 'Veiled threat in a DM after I removed a rule-breaking reply.'
  }, {
    id: 401,
    status: 'open',
    reason_code: 'off_topic',
    reporter_username: 'erestor',
    created_at: '5 hours ago',
    post: {
      thread_id: 41,
      thread_slug: 'who-changed-what',
      thread_title: 'Who changed what \u2014 and can you prove the rollback?',
      body: 'Off the audit thread: has anyone tried the new forge in the eastern hall? The fires there run hotter than Mount Doom, I swear by it.'
    },
    reason: ''
  }];

  // Approval hold (mod/approvals): topics and replies held by anti-abuse / board rules.
  const approvals = {
    threads: [{
      id: 220,
      title: 'Proposal: require a written verdict before any merge',
      author_username: 'arwen',
      board_slug: 'governance',
      created_at: '2026-04-18 09:12'
    }, {
      id: 219,
      title: 'New warden intake \u2014 spring cohort',
      author_username: 'lindir',
      board_slug: 'wardens',
      created_at: '2026-04-18 08:40'
    }],
    posts: [{
      id: 5120,
      thread_id: 73,
      thread_slug: 'eval-harness-v2',
      thread_title: 'Eval harness v2 \u2014 call for testers',
      author_username: 'celebrian',
      board_slug: 'tooling',
      created_at: '2026-04-18 10:02',
      body: 'I can take the Tuesday slot. One ask: can we record which artifacts the harness actually read, so the verdict is reproducible from the trail rather than from memory?'
    }, {
      id: 5118,
      thread_id: 41,
      thread_slug: 'who-changed-what',
      thread_title: 'Who changed what \u2014 and can you prove the rollback?',
      author_username: 'haldir',
      board_slug: 'audit-trails',
      created_at: '2026-04-18 09:58',
      body: 'New account, first post \u2014 held for review. The precedence log answers this cleanly; I attached the drill we ran last cycle so the wardens have it on record.'
    }]
  };

  // Appeals review (mod/appeals): the staff side.
  const appeals = [{
    id: 77,
    status: 'open',
    appellant_username: 'mellon',
    created_at: '1 hour ago',
    target_type: 'post',
    target_id: 5099,
    original_action: 'post removed',
    target_summary: 'Reply removed from \u201cOn the naming of wards\u201d for personal attack.',
    reason: 'I was heated but I never threatened anyone. The line about shipping was about the process, not the person. Asking for the removal to be reconsidered.'
  }, {
    id: 75,
    status: 'open',
    appellant_username: 'haldir',
    created_at: 'Yesterday',
    target_type: 'moderation_log',
    target_id: 318,
    original_action: 'topic locked',
    target_summary: 'Topic \u201cForge fires of the eastern hall\u201d locked as off-topic for #audit-trails.',
    reason: 'Fair that it was off-topic there. Could it be moved to #commons instead of locked? People were enjoying it.'
  }];
  const outcomes = ['upheld', 'modified', 'reversed', 'dismissed'];

  // Member's own appeals view (appeals/index) — Glorfindel is not the subject here;
  // this is shown as "what a member sees". Uses the council member Erestor.
  const member = {
    name: 'Erestor',
    username: 'erestor'
  };
  const myAppeals = {
    eligible: {
      posts: [{
        id: 5099,
        thread_id: 12,
        thread_slug: 'on-the-naming-of-wards',
        thread_title: 'On the naming of wards',
        deleted_at: '2 days ago',
        body: 'The charter should say plainly that testimony never outranks the work. I will not soften that for anyone who finds it inconvenient.'
      }],
      logs: [{
        id: 318,
        action: 'warning issued',
        created_at: '3 days ago',
        reason: 'Repeated sharp tone toward another member in #governance.'
      }]
    },
    submitted: [{
      id: 71,
      status: 'reversed',
      target_type: 'post',
      target_id: 4980,
      created_at: 'Last week',
      target_summary: 'Reply removed from \u201cEval harness v2\u201d.',
      reason: 'It was on-topic \u2014 I was answering the tester call directly.',
      resolution_note: 'Agreed on review. Post restored; removal was in error.'
    }, {
      id: 64,
      status: 'upheld',
      target_type: 'moderation_log',
      target_id: 290,
      created_at: '3 weeks ago',
      target_summary: 'Warning for editing a shared setting without recording precedence.',
      reason: 'I thought the change was uncontested.',
      resolution_note: 'Warning stands \u2014 the warden log is the record of precedence; edits there must be noted.'
    }]
  };
  const reasonLabels = {
    harassment: 'harassment',
    spam: 'spam',
    off_topic: 'off topic',
    other: 'other'
  };
  window.RBMod = {
    moderator,
    reports,
    approvals,
    appeals,
    outcomes,
    member,
    myAppeals,
    reasonLabels
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/mod/data.js", error: String((e && e.message) || e) }); }

// ui_kits/reading/ReadingApp.jsx
try { (() => {
/* Reading-surfaces kit — app shell. Topbar + sidebar chrome around a routed
   main pane: home · feed · search · tags · tag · notifications · compose ·
   connections. Notifications live here so the bell count stays in sync. */
(function () {
  function ReadingApp() {
    const {
      Sidebar,
      Topbar
    } = window.RBReadingChrome;
    const S = window.RBReadingSurfaces;
    const X = window.RBReadingExtras;
    const [route, setRoute] = React.useState('home');
    const [ctx, setCtx] = React.useState({}); // per-route params (tag slug, search query, conn mode)
    const [feedView, setFeedView] = React.useState('following');
    const [notifs, setNotifs] = React.useState(() => window.RBReading.notifications.map(n => ({
      ...n
    })));
    const unread = notifs.filter(n => !n.isRead).length;
    function go(next, params) {
      setRoute(next);
      setCtx(params || {});
      if (next === 'connections' && (!params || !params.mode)) setCtx({
        mode: 'followers'
      });
      window.scrollTo(0, 0);
    }
    let pane;
    if (route === 'home') pane = /*#__PURE__*/React.createElement(S.Home, {
      onRoute: go
    });else if (route === 'feed') pane = /*#__PURE__*/React.createElement(S.Feed, {
      view: feedView,
      onView: setFeedView
    });else if (route === 'search') pane = /*#__PURE__*/React.createElement(S.Search, {
      query: ctx.query || '',
      onSearch: q => go('search', {
        query: q
      })
    });else if (route === 'tags') pane = /*#__PURE__*/React.createElement(S.Tags, {
      onRoute: go
    });else if (route === 'tag') pane = /*#__PURE__*/React.createElement(S.TagShow, {
      ctx: ctx,
      onRoute: go
    });else if (route === 'notifications') pane = /*#__PURE__*/React.createElement(X.Notifications, {
      notifications: notifs,
      onOpen: id => setNotifs(p => p.map(n => n.id === id ? {
        ...n,
        isRead: true
      } : n)),
      onMarkAll: () => setNotifs(p => p.map(n => ({
        ...n,
        isRead: true
      }))),
      onClear: () => setNotifs([])
    });else if (route === 'compose') pane = /*#__PURE__*/React.createElement(X.Compose, {
      onDone: () => go('home')
    });else if (route === 'connections') pane = /*#__PURE__*/React.createElement(X.Connections, {
      mode: ctx.mode || 'followers',
      onMode: m => setCtx({
        mode: m
      })
    });else pane = /*#__PURE__*/React.createElement(S.Home, {
      onRoute: go
    });
    return /*#__PURE__*/React.createElement("div", {
      className: "app-root"
    }, /*#__PURE__*/React.createElement(Topbar, {
      route: route,
      onRoute: go,
      unread: unread,
      query: ctx.query || ''
    }), /*#__PURE__*/React.createElement("div", {
      className: "app-shell"
    }, /*#__PURE__*/React.createElement(Sidebar, {
      route: route,
      onRoute: go
    }), /*#__PURE__*/React.createElement("main", {
      className: "main read-main",
      id: "main"
    }, pane)));
  }
  window.RBReadingApp = ReadingApp;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/reading/ReadingApp.jsx", error: String((e && e.message) || e) }); }

// ui_kits/reading/ReadingChrome.jsx
try { (() => {
/* Reading-surfaces kit — shell chrome (topbar + sidebar). Faithful to the real
   partials/topbar.php + partials/sidebar.php: brand · search · New topic · bell ·
   identity, and Home / Inbox / Messages / Following / Tags / Top + boards + presence.
   Routes this kit owns switch the main pane; the rest are real cross-kit links. */
(function () {
  const ic = (paths, extra) => /*#__PURE__*/React.createElement("svg", {
    className: "rail-ic",
    viewBox: "0 0 24 24",
    "aria-hidden": "true"
  }, paths.map((d, i) => /*#__PURE__*/React.createElement("path", {
    key: i,
    d: d
  })), extra);
  const ICON = {
    home: [['M3 11.5 12 4l9 7.5'], ['M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9']],
    inbox: [['M22 12h-6l-2 3h-4l-2-3H2'], ['M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z']],
    messages: [['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z']],
    following: [['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'], ['M22 21v-2a4 4 0 0 0-3-3.87'], ['M16 3.13a4 4 0 0 1 0 7.75']],
    tags: [['M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z']],
    top: [['M8 21h8'], ['M12 17v4'], ['M7 4h10v4a5 5 0 0 1-10 0z'], ['M5 4H3v2a3 3 0 0 0 3 3'], ['M19 4h2v2a3 3 0 0 1-3 3']]
  };
  function Sidebar({
    route,
    onRoute
  }) {
    const RB = window.RB;
    const filters = [{
      key: 'inbox',
      label: 'Inbox',
      icon: 'inbox',
      href: '../retroboards/index.html'
    }, {
      key: 'messages',
      label: 'Messages',
      icon: 'messages',
      href: '../dm/index.html'
    }, {
      key: 'feed',
      label: 'Following',
      icon: 'following'
    }, {
      key: 'tags',
      label: 'Tags',
      icon: 'tags'
    }, {
      key: 'top',
      label: 'Top contributors',
      icon: 'top',
      href: '../retroboards/index.html'
    }];
    return /*#__PURE__*/React.createElement("aside", {
      className: "sidebar",
      id: "sidebar-nav"
    }, /*#__PURE__*/React.createElement("button", {
      className: 'sidebar-home' + (route === 'home' ? ' active' : ''),
      onClick: () => onRoute('home')
    }, ic(ICON.home.flat()), /*#__PURE__*/React.createElement("span", null, "Home")), /*#__PURE__*/React.createElement("nav", {
      className: "rail-filters-nav",
      "aria-label": "Quick filters"
    }, /*#__PURE__*/React.createElement("ul", {
      className: "rail-filters"
    }, filters.map(f => /*#__PURE__*/React.createElement("li", {
      key: f.key
    }, f.href ? /*#__PURE__*/React.createElement("a", {
      className: "rail-filter",
      href: f.href
    }, ic(ICON[f.icon].flat()), /*#__PURE__*/React.createElement("span", null, f.label)) : /*#__PURE__*/React.createElement("button", {
      className: 'rail-filter' + (route === f.key ? ' active' : ''),
      onClick: () => onRoute(f.key)
    }, ic(ICON[f.icon].flat()), /*#__PURE__*/React.createElement("span", null, f.label)))))), /*#__PURE__*/React.createElement("nav", {
      "aria-label": "Boards"
    }, RB.categories.map(cat => /*#__PURE__*/React.createElement("div", {
      className: "nav-cat",
      key: cat.name
    }, /*#__PURE__*/React.createElement("span", {
      className: "nav-cat-name"
    }, cat.name), /*#__PURE__*/React.createElement("ul", {
      className: "nav-boards"
    }, cat.boards.map(b => /*#__PURE__*/React.createElement("li", {
      key: b.slug
    }, /*#__PURE__*/React.createElement("button", {
      onClick: () => onRoute('home')
    }, /*#__PURE__*/React.createElement("span", {
      className: "hash"
    }, "#"), /*#__PURE__*/React.createElement("span", null, b.name)))))))), /*#__PURE__*/React.createElement("section", {
      className: "presence-widget"
    }, /*#__PURE__*/React.createElement("h2", {
      className: "presence-title"
    }, "Online \xB7 4"), /*#__PURE__*/React.createElement("ul", {
      className: "presence-list"
    }, ['galadriel', 'elrond', 'arwen', 'erestor'].map(u => /*#__PURE__*/React.createElement("li", {
      key: u
    }, /*#__PURE__*/React.createElement("span", {
      className: "dot"
    }), RB.users[u].name)))));
  }
  function Topbar({
    route,
    onRoute,
    unread,
    query
  }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      EightPointStar,
      Monogram
    } = DS;
    const me = window.RB.users[window.RB.currentUserKey];
    const [q, setQ] = React.useState(query || '');
    React.useEffect(() => {
      setQ(query || '');
    }, [query]);
    return /*#__PURE__*/React.createElement("header", {
      className: "topbar"
    }, /*#__PURE__*/React.createElement("div", {
      className: "topbar-inner"
    }, /*#__PURE__*/React.createElement("button", {
      className: "nav-toggle",
      type: "button",
      "aria-label": "Open navigation"
    }, /*#__PURE__*/React.createElement("svg", {
      className: "nav-toggle-ic",
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M3 6h18M3 12h18M3 18h18"
    }))), /*#__PURE__*/React.createElement("span", {
      className: "brand",
      onClick: () => onRoute('home')
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 26
    }), /*#__PURE__*/React.createElement("span", {
      className: "brand-name"
    }, "RetroBoards")), /*#__PURE__*/React.createElement("form", {
      className: "topbar-search",
      role: "search",
      onSubmit: e => {
        e.preventDefault();
        onRoute('search', {
          query: q
        });
      }
    }, /*#__PURE__*/React.createElement("input", {
      className: "input input-pill",
      type: "search",
      value: q,
      onChange: e => setQ(e.target.value),
      placeholder: "Search the council\u2026",
      "aria-label": "Search the council"
    })), /*#__PURE__*/React.createElement("div", {
      className: "topbar-right"
    }, /*#__PURE__*/React.createElement("button", {
      className: "topbar-cta",
      type: "button",
      onClick: () => onRoute('compose')
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M12 5v14M5 12h14"
    })), /*#__PURE__*/React.createElement("span", null, "New topic")), /*#__PURE__*/React.createElement("button", {
      className: "topbar-link bell",
      onClick: () => onRoute('notifications'),
      title: "Notifications",
      "aria-current": route === 'notifications' ? 'page' : undefined
    }, /*#__PURE__*/React.createElement("svg", {
      className: "bell-ic",
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M10.3 21a1.94 1.94 0 0 0 3.4 0"
    })), unread > 0 ? /*#__PURE__*/React.createElement("span", {
      className: "bell-count"
    }, unread) : null), /*#__PURE__*/React.createElement("button", {
      className: "topbar-user",
      onClick: () => onRoute('connections', {
        mode: 'followers'
      }),
      title: "Your connections"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: me.name,
      username: me.username,
      size: "sm",
      presence: "online"
    }), /*#__PURE__*/React.createElement("span", {
      className: "topbar-name"
    }, me.name)), /*#__PURE__*/React.createElement("a", {
      className: "topbar-link",
      href: "../admin/index.html",
      title: "Admin"
    }, "Admin"), /*#__PURE__*/React.createElement("a", {
      className: "topbar-link",
      href: "../settings/index.html",
      title: "Settings"
    }, /*#__PURE__*/React.createElement("svg", {
      className: "topbar-ic",
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "12",
      r: "3"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H2a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 3.6 8a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H8a1.65 1.65 0 0 0 1-1.51V2a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H22a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"
    }))), /*#__PURE__*/React.createElement("button", {
      className: "topbar-logout",
      type: "button"
    }, "Log out"))));
  }
  window.RBReadingChrome = {
    Sidebar,
    Topbar
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/reading/ReadingChrome.jsx", error: String((e && e.message) || e) }); }

// ui_kits/reading/ReadingExtras.jsx
try { (() => {
/* Reading-surfaces kit — Notifications, Connections, and full-page Compose.
   Faithful to templates/notifications.php, profile/connections.php, compose.php. */
(function () {
  const RB = () => window.RB;
  const nameOf = u => RB().users[u] ? RB().users[u].name : u || 'Someone';
  const NOTIF_ICON = {
    reply: ['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
    new_thread: ['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
    mention: ['M12 12m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0', 'M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8'],
    reaction: ['M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z'],
    follow: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0', 'M19 8v6', 'M22 11h-6'],
    badge: ['M12 15m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0', 'M8.2 13.9 7 22l5-3 5 3-1.2-8.1'],
    solved: ['M22 11.1V12a10 10 0 1 1-5.9-9.1', 'M22 4 12 14.01l-3-3'],
    dm: ['M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z', 'm22 6-10 7L2 6'],
    mod: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
    announcement: ['M3 11l18-5v12L3 14v-3z', 'M11.6 16.8a3 3 0 1 1-5.8-1.6']
  };
  function verb(n) {
    const a = nameOf(n.actor);
    switch (n.type) {
      case 'reply':
        return a + ' replied';
      case 'new_thread':
        return a + ' started a thread';
      case 'new_post':
        return a + ' posted';
      case 'mention':
        return a + ' mentioned you';
      case 'reaction':
        return a + ' reacted to your post';
      case 'follow':
        return a + ' followed you';
      case 'badge':
        return 'You earned a badge';
      case 'solved':
        return 'Your answer was accepted';
      case 'dm':
        return a + ' sent you a message';
      case 'mod':
        return 'A moderator action affects you';
      case 'announcement':
        return 'Announcement';
      default:
        return 'Notification';
    }
  }

  /* ── Notifications ────────────────────────────────────────────────────── */
  function Notifications({
    notifications,
    onOpen,
    onMarkAll,
    onClear
  }) {
    const unread = notifications.filter(n => !n.isRead).length;
    return /*#__PURE__*/React.createElement("div", {
      className: "read-pad notifications-view"
    }, /*#__PURE__*/React.createElement("header", {
      className: "board-header"
    }, /*#__PURE__*/React.createElement("h1", null, "Notifications ", unread > 0 ? /*#__PURE__*/React.createElement("span", {
      className: "badge"
    }, unread, " unread") : null), notifications.length ? /*#__PURE__*/React.createElement("div", {
      className: "notif-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button",
      onClick: onMarkAll
    }, "Mark all read"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn danger",
      type: "button",
      onClick: onClear
    }, "Clear all")) : null), notifications.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted empty"
    }, "No notifications yet.") : /*#__PURE__*/React.createElement("ul", {
      className: "notif-list"
    }, notifications.map(n => /*#__PURE__*/React.createElement("li", {
      key: n.id,
      className: 'notif-row' + (n.isRead ? '' : ' notif-unread')
    }, /*#__PURE__*/React.createElement("button", {
      className: "notif-link",
      type: "button",
      onClick: () => onOpen(n.id)
    }, /*#__PURE__*/React.createElement("span", {
      className: "notif-icon"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, (NOTIF_ICON[n.type] || NOTIF_ICON.reply).map((d, i) => /*#__PURE__*/React.createElement("path", {
      key: i,
      d: d
    })))), /*#__PURE__*/React.createElement("span", {
      className: "notif-body"
    }, /*#__PURE__*/React.createElement("span", {
      className: "notif-text"
    }, verb(n)), n.threadTitle ? /*#__PURE__*/React.createElement("span", {
      className: "notif-thread"
    }, "\u2014 ", n.threadTitle) : null), /*#__PURE__*/React.createElement("span", {
      className: "notif-time"
    }, n.time), /*#__PURE__*/React.createElement("span", {
      className: 'notif-dot' + (n.isRead ? ' is-read' : ''),
      "aria-hidden": "true"
    }))))));
  }

  /* ── Connections — followers / following ──────────────────────────────── */
  function Connections({
    mode,
    onMode
  }) {
    const {
      Monogram
    } = window.ImladrisDesignSystem_c3e027;
    const conn = window.RBReading.connections;
    const [removed, setRemoved] = React.useState(() => new Set());
    const isFollowers = mode === 'followers';
    const people = (isFollowers ? conn.followers : conn.following).filter(u => !removed.has(u));
    const profile = RB().users[conn.profile];
    return /*#__PURE__*/React.createElement("div", {
      className: "read-pad connections"
    }, /*#__PURE__*/React.createElement("header", {
      className: "board-header"
    }, /*#__PURE__*/React.createElement("h1", null, isFollowers ? 'Followers' : 'Following', " ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "\xB7 ", /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => e.preventDefault()
    }, "@", profile.username)))), /*#__PURE__*/React.createElement("nav", {
      className: "inbox-tabs conn-tabs",
      "aria-label": "Connections"
    }, /*#__PURE__*/React.createElement("button", {
      className: 'inbox-tab' + (isFollowers ? ' is-active' : ''),
      onClick: () => onMode('followers')
    }, "Followers"), /*#__PURE__*/React.createElement("button", {
      className: 'inbox-tab' + (!isFollowers ? ' is-active' : ''),
      onClick: () => onMode('following')
    }, "Following")), people.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted empty"
    }, isFollowers ? 'No followers yet.' : 'Not following anyone yet.') : /*#__PURE__*/React.createElement("ul", {
      className: "people-list"
    }, people.map(u => {
      const p = RB().users[u];
      return /*#__PURE__*/React.createElement("li", {
        className: "person-row",
        key: u
      }, /*#__PURE__*/React.createElement(Monogram, {
        name: p.name,
        username: p.username
      }), /*#__PURE__*/React.createElement("a", {
        className: "person-name",
        href: "#",
        onClick: e => e.preventDefault()
      }, p.name), /*#__PURE__*/React.createElement("span", {
        className: "handle"
      }, "@", p.username), /*#__PURE__*/React.createElement("span", {
        className: "person-rep"
      }, RB().fmt(p.rep), " rep"), isFollowers ? /*#__PURE__*/React.createElement("button", {
        className: "linkbtn danger",
        type: "button",
        onClick: () => setRemoved(s => new Set(s).add(u))
      }, "Remove") : null);
    })));
  }

  /* ── Compose — full-page new topic ────────────────────────────────────── */
  function Compose({
    onDone
  }) {
    const boards = RB().categories.flatMap(c => c.boards);
    const [board, setBoard] = React.useState(boards[0].slug);
    const [title, setTitle] = React.useState('');
    const [body, setBody] = React.useState('');
    const [anon, setAnon] = React.useState(false);
    return /*#__PURE__*/React.createElement("div", {
      className: "read-pad"
    }, /*#__PURE__*/React.createElement("div", {
      className: "card compose-page"
    }, /*#__PURE__*/React.createElement("h1", null, "New topic"), /*#__PURE__*/React.createElement("form", {
      className: "composer stacked",
      onSubmit: e => {
        e.preventDefault();
        onDone();
      }
    }, /*#__PURE__*/React.createElement("p", {
      className: "md-hint"
    }, "Markdown supported \u2014 ", /*#__PURE__*/React.createElement("strong", null, "**bold**"), ", ", /*#__PURE__*/React.createElement("em", null, "*italic*"), ", ", /*#__PURE__*/React.createElement("code", null, "`code`"), ", ", /*#__PURE__*/React.createElement("code", null, "||spoiler||"), ", and ", /*#__PURE__*/React.createElement("code", null, "![alt](image)"), " after uploading."), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Board"), /*#__PURE__*/React.createElement("select", {
      className: "input",
      value: board,
      onChange: e => setBoard(e.target.value)
    }, boards.map(b => /*#__PURE__*/React.createElement("option", {
      key: b.slug,
      value: b.slug
    }, "#", b.name)))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Title"), /*#__PURE__*/React.createElement("input", {
      className: "input",
      type: "text",
      maxLength: 160,
      value: title,
      onChange: e => setTitle(e.target.value),
      required: true
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Body"), /*#__PURE__*/React.createElement("textarea", {
      className: "input composer-input",
      rows: 8,
      maxLength: 20000,
      value: body,
      onChange: e => setBody(e.target.value),
      placeholder: "Write your topic\u2026",
      required: true
    })), /*#__PURE__*/React.createElement("label", {
      className: "checkline"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox",
      checked: anon,
      onChange: e => setAnon(e.target.checked)
    }), /*#__PURE__*/React.createElement("span", null, "Post anonymously ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(only on boards that allow it; your name stays visible to moderators)"))), /*#__PURE__*/React.createElement("div", {
      className: "form-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: "btn",
      type: "submit",
      disabled: !title.trim() || !body.trim()
    }, "Create topic"), /*#__PURE__*/React.createElement("button", {
      className: "btn btn-ghost",
      type: "button",
      onClick: onDone
    }, "Cancel")))));
  }
  window.RBReadingExtras = {
    Notifications,
    Connections,
    Compose
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/reading/ReadingExtras.jsx", error: String((e && e.message) || e) }); }

// ui_kits/reading/ReadingSurfaces.jsx
try { (() => {
/* Reading-surfaces kit — Home, Feed, Search, Tags, and a single Tag.
   Faithful to templates/{home,feed,search}.php and tags/{index,show}.php. */
(function () {
  const RB = () => window.RB;
  const RD = () => window.RBReading;
  const nameOf = u => RB().users[u] ? RB().users[u].name : u;

  // Thread row — a faithful recreation of partials/thread_row.php, reusing the
  // global .thread-row / .chip primitives. Used by Tag show.
  function ThreadRow({
    t,
    showBoard
  }) {
    const {
      Monogram
    } = window.ImladrisDesignSystem_c3e027;
    const cls = ['thread-row'];
    if (t.unread) cls.push('thread-unread');
    if (t.pinned) cls.push('thread-pinned');
    if (t.status && t.status !== 'open') cls.push('thread-status-' + t.status);
    const statusChip = t.status === 'solved' ? /*#__PURE__*/React.createElement("span", {
      className: "chip chip-solved"
    }, "Solved") : t.status === 'needs_answer' ? /*#__PURE__*/React.createElement("span", {
      className: "chip chip-needs"
    }, "Needs answer") : t.status === 'decision_made' ? /*#__PURE__*/React.createElement("span", {
      className: "chip chip-decision_made"
    }, "Decision made") : null;
    return /*#__PURE__*/React.createElement("li", {
      className: cls.join(' ')
    }, t.unread ? /*#__PURE__*/React.createElement("span", {
      className: "unread-dot",
      title: "Unread"
    }) : null, /*#__PURE__*/React.createElement(Monogram, {
      name: nameOf(t.author),
      username: t.author
    }), /*#__PURE__*/React.createElement("div", {
      className: "thread-row-main"
    }, t.pinned || statusChip ? /*#__PURE__*/React.createElement("div", {
      className: "thread-row-chips"
    }, t.pinned ? /*#__PURE__*/React.createElement("span", {
      className: "chip chip-pinned"
    }, "Pinned") : null, statusChip) : null, /*#__PURE__*/React.createElement("a", {
      className: "thread-title",
      href: "#",
      onClick: e => e.preventDefault()
    }, t.title), /*#__PURE__*/React.createElement("span", {
      className: "thread-meta"
    }, showBoard ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("a", {
      className: "thread-board",
      href: "#",
      onClick: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement("span", {
      className: "hash"
    }, "#"), t.board), " \xB7 ") : null, "by ", nameOf(t.author), " \xB7 ", t.replies, " ", t.replies === 1 ? 'reply' : 'replies', " \xB7 ", t.time)), t.starred ? /*#__PURE__*/React.createElement("span", {
      className: "thread-star",
      title: "Starred"
    }, "\u2605") : null);
  }

  /* ── Home — board index ───────────────────────────────────────────────── */
  function Home({
    onRoute
  }) {
    const cats = RB().categories;
    const stats = RD().boardStats;
    return /*#__PURE__*/React.createElement("div", {
      className: "read-pad board-index"
    }, /*#__PURE__*/React.createElement("h1", {
      className: "page-title"
    }, "RetroBoards"), cats.map(s => /*#__PURE__*/React.createElement("section", {
      className: "cat-block",
      key: s.name
    }, /*#__PURE__*/React.createElement("h2", {
      className: "cat-title"
    }, s.name), /*#__PURE__*/React.createElement("ul", {
      className: "board-list"
    }, s.boards.map(b => /*#__PURE__*/React.createElement("li", {
      className: "board-row",
      key: b.slug
    }, /*#__PURE__*/React.createElement("a", {
      className: "board-link",
      href: "#",
      onClick: e => {
        e.preventDefault();
        onRoute('tag', {
          slug: b.slug,
          board: true,
          name: b.name,
          desc: b.desc
        });
      }
    }, /*#__PURE__*/React.createElement("span", {
      className: "board-name"
    }, /*#__PURE__*/React.createElement("span", {
      className: "hash"
    }, "#"), b.name), b.desc ? /*#__PURE__*/React.createElement("span", {
      className: "board-desc"
    }, b.desc) : null), /*#__PURE__*/React.createElement("span", {
      className: "board-stats"
    }, b.count, " threads \xB7 ", stats[b.slug] != null ? stats[b.slug] : b.count * 6, " posts")))))));
  }

  /* ── Feed — Following / Latest ────────────────────────────────────────── */
  function Feed({
    view,
    onView
  }) {
    const items = RD().feed;
    const latest = view === 'latest';
    const list = latest ? items.slice().sort((a, b) => a.threadId - b.threadId) : items;
    return /*#__PURE__*/React.createElement("div", {
      className: "read-pad feed"
    }, /*#__PURE__*/React.createElement("header", {
      className: "board-header"
    }, /*#__PURE__*/React.createElement("h1", null, latest ? 'Latest' : 'Following'), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, latest ? 'Recent visible community activity.' : 'Recent activity from people, boards, and tags you follow.')), /*#__PURE__*/React.createElement("nav", {
      className: "inbox-tabs feed-tabs",
      "aria-label": "Feed views"
    }, /*#__PURE__*/React.createElement("button", {
      className: 'inbox-tab' + (!latest ? ' is-active' : ''),
      onClick: () => onView('following')
    }, "Following"), /*#__PURE__*/React.createElement("button", {
      className: 'inbox-tab' + (latest ? ' is-active' : ''),
      onClick: () => onView('latest')
    }, "Latest")), /*#__PURE__*/React.createElement("ul", {
      className: "feed-list"
    }, list.map((it, i) => /*#__PURE__*/React.createElement("li", {
      className: "feed-item",
      key: i
    }, /*#__PURE__*/React.createElement("div", {
      className: "feed-meta"
    }, /*#__PURE__*/React.createElement("a", {
      className: "post-author",
      href: "#",
      onClick: e => e.preventDefault()
    }, nameOf(it.author)), /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, it.isOp ? 'started a topic' : 'replied'), /*#__PURE__*/React.createElement("span", {
      className: "post-time"
    }, it.time)), /*#__PURE__*/React.createElement("a", {
      className: "feed-thread",
      href: "#",
      onClick: e => e.preventDefault()
    }, it.threadTitle), ' ', /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "in ", /*#__PURE__*/React.createElement("span", {
      className: "hash"
    }, "#"), it.board), /*#__PURE__*/React.createElement("p", {
      className: "feed-excerpt"
    }, it.excerpt)))), /*#__PURE__*/React.createElement("nav", {
      className: "pager"
    }, /*#__PURE__*/React.createElement("button", {
      className: "btn btn-small",
      type: "button"
    }, "Older \u2192")));
  }

  /* ── Search ───────────────────────────────────────────────────────────── */
  function Search({
    query,
    onSearch
  }) {
    const [q, setQ] = React.useState(query || '');
    React.useEffect(() => {
      setQ(query || '');
    }, [query]);
    const data = RD().search;
    const searched = (query || '').trim() !== '';
    const results = searched ? data.results : [];
    return /*#__PURE__*/React.createElement("div", {
      className: "read-pad search-view"
    }, /*#__PURE__*/React.createElement("header", {
      className: "board-header"
    }, /*#__PURE__*/React.createElement("h1", null, "Search"), /*#__PURE__*/React.createElement("form", {
      className: "search-form",
      role: "search",
      onSubmit: e => {
        e.preventDefault();
        onSearch(q);
      }
    }, /*#__PURE__*/React.createElement("input", {
      className: "input",
      type: "search",
      value: q,
      onChange: e => setQ(e.target.value),
      placeholder: "Search threads and posts\u2026",
      autoFocus: true
    }), /*#__PURE__*/React.createElement("button", {
      className: "btn",
      type: "submit"
    }, "Search"))), !searched ? /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Search thread titles and posts you can access.") : results.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted empty"
    }, "No results for \u201C", query, "\u201D.") : /*#__PURE__*/React.createElement("ul", {
      className: "search-results"
    }, results.map((r, i) => /*#__PURE__*/React.createElement("li", {
      className: "search-result",
      key: i
    }, /*#__PURE__*/React.createElement("a", {
      className: "search-title",
      href: r.url,
      onClick: e => e.preventDefault()
    }, r.type === 'post' ? /*#__PURE__*/React.createElement("span", {
      className: "chip"
    }, "post") : null, r.title), /*#__PURE__*/React.createElement("span", {
      className: "search-board"
    }, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement("span", {
      className: "hash"
    }, "#"), r.boardName)), r.snippet ? /*#__PURE__*/React.createElement("p", {
      className: "search-snippet",
      dangerouslySetInnerHTML: {
        __html: r.snippet
      }
    }) : null))));
  }

  /* ── Tags — directory ─────────────────────────────────────────────────── */
  function Tags({
    onRoute
  }) {
    const tags = RD().tags;
    return /*#__PURE__*/React.createElement("div", {
      className: "read-pad tag-view"
    }, /*#__PURE__*/React.createElement("header", {
      className: "board-header"
    }, /*#__PURE__*/React.createElement("h1", null, "Tags"), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Approved community topics you can follow for discovery.")), /*#__PURE__*/React.createElement("ul", {
      className: "tag-cloud"
    }, tags.map(t => /*#__PURE__*/React.createElement("li", {
      key: t.slug
    }, /*#__PURE__*/React.createElement("a", {
      className: "tag-card",
      href: "#",
      onClick: e => {
        e.preventDefault();
        onRoute('tag', {
          slug: t.slug,
          name: t.name,
          desc: t.desc
        });
      }
    }, /*#__PURE__*/React.createElement("span", {
      className: "tag-name"
    }, t.name, " ", /*#__PURE__*/React.createElement("span", {
      className: "tag-count"
    }, "\xB7 ", t.threads.length)), /*#__PURE__*/React.createElement("span", {
      className: "tag-desc"
    }, t.desc))))));
  }

  /* ── Tag — single (also serves a board listing from Home) ─────────────── */
  function TagShow({
    ctx,
    onRoute
  }) {
    const [following, setFollowing] = React.useState(false);
    const isBoard = !!ctx.board;
    let threads;
    if (isBoard) {
      threads = RB().threads.filter(t => t.board === ctx.slug);
    } else {
      const tag = RD().tags.find(t => t.slug === ctx.slug);
      const ids = tag ? tag.threads : [];
      threads = ids.map(id => RB().threads.find(t => t.id === id)).filter(Boolean);
    }
    return /*#__PURE__*/React.createElement("div", {
      className: "read-pad tag-view"
    }, /*#__PURE__*/React.createElement("header", {
      className: "board-header"
    }, /*#__PURE__*/React.createElement("p", {
      className: "breadcrumb"
    }, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        onRoute(isBoard ? 'home' : 'tags');
      }
    }, isBoard ? 'Home' : 'Tags')), /*#__PURE__*/React.createElement("h1", null, /*#__PURE__*/React.createElement("span", {
      className: "hash",
      style: {
        color: 'var(--gold-ink)'
      }
    }, "#"), ctx.name || ctx.slug), ctx.desc ? /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, ctx.desc) : null, !isBoard ? /*#__PURE__*/React.createElement("div", {
      className: "header-follow"
    }, /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button",
      onClick: () => setFollowing(v => !v)
    }, following ? 'Unfollow tag' : 'Follow tag'), /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "Discovery feed only")) : null), threads.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted empty"
    }, isBoard ? 'No topics in this board yet.' : 'No visible topics use this tag.') : /*#__PURE__*/React.createElement("ul", {
      className: "thread-list"
    }, threads.map(t => /*#__PURE__*/React.createElement(ThreadRow, {
      key: t.id,
      t: t,
      showBoard: !isBoard
    }))));
  }
  window.RBReadingSurfaces = {
    Home,
    Feed,
    Search,
    Tags,
    TagShow,
    ThreadRow
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/reading/ReadingSurfaces.jsx", error: String((e && e.message) || e) }); }

// ui_kits/reading/reading-data.js
try { (() => {
/* Reading-surfaces kit — seed data for the public/member reading routes:
   home (board index), feed, search, tags, notifications, connections.
   Reuses the RetroBoards roster, boards, and threads (window.RB); this file
   adds only what those surfaces need. Shared via window.RBReading.
   Mirrors templates/{home,feed,search,notifications,compose}.php,
   templates/tags/{index,show}.php, templates/profile/connections.php. */
(function () {
  const RB = window.RB;

  // Post counts per board (threads come from RB.categories `count`). Home shows both.
  const boardStats = {
    announcements: 84,
    introductions: 213,
    'the-valley': 612,
    interpretability: 388,
    evaluations: 547,
    'capability-disclosure': 164,
    'audit-trails': 331
  };

  // Public tag directory (approved discovery topics). Each maps to thread ids in RB.threads.
  const tags = [{
    slug: 'evaluations',
    name: 'evaluations',
    desc: 'Rites, gates, and the verdicts they leave behind.',
    threads: [1, 5]
  }, {
    slug: 'audit-trails',
    name: 'audit-trails',
    desc: 'Who changed what — and whether you can prove the rollback.',
    threads: [2]
  }, {
    slug: 'interpretability',
    name: 'interpretability',
    desc: 'Reading the machine without reading a verdict into the map.',
    threads: [5]
  }, {
    slug: 'disclosure',
    name: 'disclosure',
    desc: 'What we publish, and when.',
    threads: [3]
  }, {
    slug: 'governance',
    name: 'governance',
    desc: 'How the council keeps counsel.',
    threads: [4]
  }, {
    slug: 'rollback',
    name: 'rollback',
    desc: 'Drills, precedence, and undoing safely.',
    threads: [2]
  }, {
    slug: 'first-posts',
    name: 'first-posts',
    desc: 'Newcomers finding their footing.',
    threads: [6]
  }];

  // Following feed — recent activity from people you follow.
  const feed = [{
    author: 'galadriel',
    isOp: true,
    time: '2h',
    threadId: 1,
    threadSlug: 'evaluations-as-ritual',
    threadTitle: 'Evaluations as ritual, not gate',
    board: 'evaluations',
    excerpt: 'We keep treating the eval suite as a turnstile — pass and forget. I want to argue it should be a rite: something the whole council performs, reads, and remembers.'
  }, {
    author: 'glorfindel',
    isOp: false,
    time: '5h',
    threadId: 2,
    threadSlug: 'who-changed-what',
    threadTitle: 'Who changed what — and can you prove the rollback?',
    board: 'audit-trails',
    excerpt: 'We ran the rollback drill on Tuesday. The surprise was not the revert — it was discovering two actors could edit the same setting with no record of precedence.'
  }, {
    author: 'arwen',
    isOp: true,
    time: '6h',
    threadId: 5,
    threadSlug: 'reading-attention-as-a-map',
    threadTitle: 'Reading attention as a map, not a verdict',
    board: 'interpretability',
    excerpt: 'Attention tells you where the model looked, not what it concluded. I keep watching people read a verdict into a heatmap.'
  }, {
    author: 'elrond',
    isOp: true,
    time: '1d',
    threadId: 3,
    threadSlug: 'on-exposing-capability',
    threadTitle: 'On exposing capability before we are asked',
    board: 'capability-disclosure',
    excerpt: 'A decision, recorded: we publish the capability notes the same day we brief the council — not after. Disclosure that trails the briefing is not disclosure.'
  }, {
    author: 'lindir',
    isOp: true,
    time: '8h',
    threadId: 6,
    threadSlug: 'newly-arrived',
    threadTitle: 'Newly arrived — keeper of songs, learner of evals',
    board: 'introductions',
    excerpt: 'Hello, council. I keep the songs of the house and I am here to learn how you read the machine. Point me at the three topics you wish you had read first.'
  }];

  // Search — a performed query and its results (threads + posts).
  const search = {
    query: 'rollback',
    results: [{
      type: 'thread',
      title: 'Who changed what — and can you prove the rollback?',
      url: '#',
      boardSlug: 'audit-trails',
      boardName: 'audit-trails',
      snippet: 'The diff is small; the audit trail must be whole. Every change should answer three questions: who changed what, was it authorized, and can you prove the <mark>rollback</mark>?'
    }, {
      type: 'post',
      title: 'Re: Who changed what — and can you prove the rollback?',
      url: '#',
      boardSlug: 'audit-trails',
      boardName: 'audit-trails',
      snippet: 'We ran the <mark>rollback</mark> drill on Tuesday. The surprise was not the revert — it was discovering two actors could edit the same setting with no record of precedence.'
    }, {
      type: 'post',
      title: 'Re: Evaluations as ritual, not gate',
      url: '#',
      boardSlug: 'evaluations',
      boardName: 'evaluations',
      snippet: 'Every eval run resolves into an artifact — a short written verdict. If a change cannot be rolled back cleanly, that is itself a verdict worth recording.'
    }]
  };

  // Notifications — types match the product's verb map.
  const notifications = [{
    id: 9,
    type: 'mention',
    actor: 'galadriel',
    threadTitle: 'Evaluations as ritual, not gate',
    time: '8m',
    isRead: false
  }, {
    id: 8,
    type: 'reply',
    actor: 'glorfindel',
    threadTitle: 'Who changed what — and can you prove the rollback?',
    time: '40m',
    isRead: false
  }, {
    id: 7,
    type: 'reaction',
    actor: 'arwen',
    threadTitle: 'Who changed what — and can you prove the rollback?',
    time: '1h',
    isRead: false
  }, {
    id: 6,
    type: 'solved',
    actor: '',
    threadTitle: 'Evaluations as ritual, not gate',
    time: '2h',
    isRead: true
  }, {
    id: 5,
    type: 'follow',
    actor: 'lindir',
    threadTitle: '',
    time: '3h',
    isRead: true
  }, {
    id: 4,
    type: 'dm',
    actor: 'elrond',
    threadTitle: '',
    time: '5h',
    isRead: true
  }, {
    id: 3,
    type: 'badge',
    actor: '',
    threadTitle: '',
    time: 'Yesterday',
    isRead: true
  }, {
    id: 2,
    type: 'new_thread',
    actor: 'arwen',
    threadTitle: 'Reading attention as a map, not a verdict',
    time: 'Yesterday',
    isRead: true
  }, {
    id: 1,
    type: 'announcement',
    actor: '',
    threadTitle: 'The hall reopens — read this first',
    time: '3d',
    isRead: true
  }];
  const unreadCount = notifications.filter(n => !n.isRead).length;

  // Connections — followers / following for a profile (Erestor's).
  const connections = {
    profile: 'erestor',
    followers: ['galadriel', 'elrond', 'arwen', 'lindir'],
    following: ['galadriel', 'elrond', 'glorfindel']
  };
  window.RBReading = {
    boardStats,
    tags,
    feed,
    search,
    notifications,
    unreadCount,
    connections
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/reading/reading-data.js", error: String((e && e.message) || e) }); }

// ui_kits/retroboards/App.jsx
try { (() => {
/* RetroBoards — app shell. Routes between the Community Inbox, profile, and
   the leaderboard; holds auth (guest/member), star and reply state. */
(function () {
  const Topbar = window.RBTopbar;
  const Rail = window.RBRail;
  const Inbox = window.RBInbox;
  const Conversation = window.RBConversation;
  const Profile = window.RBProfile;
  const Leaderboard = window.RBLeaderboard;
  function clone(t) {
    return JSON.parse(JSON.stringify(t));
  }
  function App() {
    const RB = window.RB;
    const [user, setUser] = React.useState(RB.users[RB.currentUserKey]);
    const [view, setView] = React.useState('inbox'); // inbox | profile | leaderboard
    const [scope, setScope] = React.useState('inbox'); // inbox | mentions | watching | drafts
    const [board, setBoard] = React.useState(null);
    const [threads, setThreads] = React.useState(() => RB.threads.map(clone));
    const [activeId, setActiveId] = React.useState(RB.threads[0].id);
    const [profileKey, setProfileKey] = React.useState(RB.currentUserKey);
    const [density, setDensity] = React.useState('Hall');
    const [filter, setFilter] = React.useState('All');
    const [sort, setSort] = React.useState('Active');
    const [starred, setStarred] = React.useState(() => new Set(RB.threads.filter(t => t.starred).map(t => t.id)));
    const [reply, setReply] = React.useState('');
    const [mobileReading, setMobileReading] = React.useState(false);

    // Derive the visible thread list.
    let list = threads.slice();
    if (board) list = list.filter(t => t.board === board);
    if (scope === 'mentions') list = list.filter(t => t.unread);else if (scope === 'watching') list = list.filter(t => starred.has(t.id));else if (scope === 'drafts') list = [];
    if (filter === 'Unread') list = list.filter(t => t.unread);else if (filter === 'Starred') list = list.filter(t => starred.has(t.id));else if (filter === 'Mine') list = list.filter(t => t.author === (user && user.username));
    if (sort === 'Newest') list = list.slice().sort((a, b) => b.id - a.id);else if (sort === 'Unanswered') list = list.filter(t => t.replies === 0);

    // Reflect live star state onto the rows.
    list = list.map(t => ({
      ...t,
      starred: starred.has(t.id)
    }));
    const activeThread = threads.find(t => t.id === activeId) || null;
    const shownThread = view === 'inbox' && activeThread ? {
      ...activeThread,
      starred: starred.has(activeThread.id)
    } : activeThread;
    function openThread(id) {
      setActiveId(id);
      setMobileReading(true);
    }
    function goInboxFilter(key) {
      if (key === 'top') {
        setView('leaderboard');
        return;
      }
      setView('inbox');
      setBoard(null);
      setScope(key);
      setMobileReading(false);
    }
    function goBoard(slug) {
      setView('inbox');
      setScope('inbox');
      setBoard(slug);
      setMobileReading(false);
      const first = threads.find(t => t.board === slug);
      if (first) setActiveId(first.id);
    }
    function toggleStar(id) {
      setStarred(prev => {
        const n = new Set(prev);
        n.has(id) ? n.delete(id) : n.add(id);
        return n;
      });
    }
    function sendReply() {
      const body = reply.trim();
      if (!body || !activeThread) return;
      setThreads(prev => prev.map(t => t.id === activeThread.id ? {
        ...t,
        replies: t.replies + 1,
        posts: [...t.posts, {
          author: user.username,
          time: 'just now',
          rep: String(user.rep),
          body,
          reactions: []
        }]
      } : t));
      setReply('');
    }
    return /*#__PURE__*/React.createElement("div", {
      className: "app-root"
    }, /*#__PURE__*/React.createElement(Topbar, {
      user: user,
      onBrand: () => {
        setView('inbox');
        setBoard(null);
        setScope('inbox');
      },
      onProfile: () => {
        setProfileKey(user ? user.username : RB.currentUserKey);
        setView('profile');
      },
      onLogout: () => setUser(null),
      onToggleAuth: () => setUser(RB.users[RB.currentUserKey])
    }), view === 'inbox' ? /*#__PURE__*/React.createElement("div", {
      className: "app-shell",
      style: {
        maxWidth: 'none'
      }
    }, /*#__PURE__*/React.createElement(Rail, {
      view: scope,
      board: board,
      user: user,
      onFilter: goInboxFilter,
      onBoard: goBoard
    }), /*#__PURE__*/React.createElement("div", {
      className: "inbox-shell"
    }, /*#__PURE__*/React.createElement(Inbox, {
      board: board,
      threads: list,
      density: density,
      onDensity: setDensity,
      filter: filter,
      onFilter: setFilter,
      sort: sort,
      onSort: setSort,
      activeId: activeId,
      onOpen: openThread,
      user: user,
      onNewTopic: () => {},
      hiddenOnMobile: mobileReading
    }), /*#__PURE__*/React.createElement(Conversation, {
      thread: shownThread,
      user: user,
      onBack: () => setMobileReading(false),
      starred: shownThread ? starred.has(shownThread.id) : false,
      onStar: () => shownThread && toggleStar(shownThread.id),
      replyValue: reply,
      onReplyChange: setReply,
      onSend: sendReply,
      isOpenMobile: mobileReading
    }))) : /*#__PURE__*/React.createElement("div", {
      className: "app-shell"
    }, /*#__PURE__*/React.createElement(Rail, {
      view: view === 'leaderboard' ? 'top' : scope,
      board: board,
      user: user,
      onFilter: goInboxFilter,
      onBoard: goBoard
    }), /*#__PURE__*/React.createElement("main", {
      style: {
        minWidth: 0
      }
    }, view === 'profile' ? /*#__PURE__*/React.createElement(Profile, {
      userKey: profileKey,
      onBack: () => {
        setView('inbox');
      }
    }) : /*#__PURE__*/React.createElement(Leaderboard, {
      onOpenProfile: k => {
        setProfileKey(k);
        setView('profile');
      }
    }))));
  }
  window.RBApp = App;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/retroboards/App.jsx", error: String((e && e.message) || e) }); }

// ui_kits/retroboards/Conversation.jsx
try { (() => {
/* RetroBoards — the conversation reading pane (right column). */
(function () {
  const ic = d => /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 24 24",
    width: "12",
    height: "12",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "2",
    strokeLinecap: "round",
    strokeLinejoin: "round",
    style: {
      display: 'block'
    }
  }, /*#__PURE__*/React.createElement("path", {
    d: d
  }));
  const ICONS = {
    flame: ic('M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z'),
    check: ic('M20 6L9 17l-5-5'),
    spark: ic('M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.13-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.13a.5.5 0 0 1 .96 0L14.06 8.5A2 2 0 0 0 15.5 9.94l6.13 1.58a.5.5 0 0 1 0 .96L15.5 14.06a2 2 0 0 0-1.44 1.44l-1.58 6.13a.5.5 0 0 1-.96 0z')
  };
  function Conversation({
    thread,
    user,
    onBack,
    starred,
    onStar,
    replyValue,
    onReplyChange,
    onSend,
    isOpenMobile
  }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      EightPointStar,
      Post,
      Reaction,
      Composer,
      JoinBar,
      StarButton,
      ParticipantStack,
      Monogram
    } = DS;
    const RB = window.RB;
    if (!thread) {
      return /*#__PURE__*/React.createElement("div", {
        className: 'inbox-reading' + (isOpenMobile ? ' is-open' : '')
      }, /*#__PURE__*/React.createElement("div", {
        style: {
          textAlign: 'center',
          padding: '80px 24px',
          maxWidth: '44ch',
          margin: '0 auto',
          color: 'var(--text-muted)'
        }
      }, /*#__PURE__*/React.createElement("span", {
        style: {
          color: 'var(--green-400)',
          opacity: .6,
          display: 'inline-block'
        }
      }, /*#__PURE__*/React.createElement(EightPointStar, {
        size: 54,
        style: {
          opacity: 1
        }
      })), /*#__PURE__*/React.createElement("p", {
        style: {
          fontFamily: 'var(--font-display)',
          fontSize: '1.5rem',
          color: 'var(--text-strong)',
          margin: '14px 0 6px'
        }
      }, "Choose a topic to read"), /*#__PURE__*/React.createElement("p", {
        style: {
          margin: 0
        }
      }, "The council's threads open here, beside the inbox.")));
    }
    const author = RB.users[thread.author];
    return /*#__PURE__*/React.createElement("div", {
      className: 'inbox-reading' + (isOpenMobile ? ' is-open' : '')
    }, /*#__PURE__*/React.createElement("div", {
      className: "reading-wrap"
    }, /*#__PURE__*/React.createElement("header", {
      className: "thread-head"
    }, /*#__PURE__*/React.createElement("span", {
      className: "thread-head-star",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 130,
      variant: "watermark",
      style: {
        opacity: 1,
        width: 130,
        height: 130
      }
    })), /*#__PURE__*/React.createElement("button", {
      className: "breadcrumb",
      onClick: onBack
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      width: "13",
      height: "13",
      fill: "none",
      stroke: "currentColor",
      strokeWidth: "2",
      strokeLinecap: "round",
      strokeLinejoin: "round"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M15 18l-6-6 6-6"
    })), "Inbox / #", thread.board), /*#__PURE__*/React.createElement("h1", null, thread.title), /*#__PURE__*/React.createElement("div", {
      className: "thread-head-meta"
    }, /*#__PURE__*/React.createElement("div", {
      className: "convo-byline"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: author.name,
      username: author.username,
      size: "lg",
      gilt: true,
      presence: author.presence
    }), /*#__PURE__*/React.createElement("div", {
      className: "convo-byline-id"
    }, /*#__PURE__*/React.createElement("div", {
      className: "convo-byline-name"
    }, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => e.preventDefault()
    }, author.name), /*#__PURE__*/React.createElement("span", {
      className: 'tier tier-' + author.tier.toLowerCase()
    }, author.tier), /*#__PURE__*/React.createElement("span", {
      className: "regard"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 100 100",
      width: "11",
      height: "11",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("path", {
      fill: "currentColor",
      d: "M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z"
    })), RB.fmt(author.rep))), /*#__PURE__*/React.createElement("p", {
      className: "convo-byline-sub"
    }, /*#__PURE__*/React.createElement("span", {
      className: "sign-title"
    }, author.title), " \xB7 opened this topic \xB7 ", thread.replies, " replies"))), /*#__PURE__*/React.createElement("div", {
      className: "convo-head-actions"
    }, /*#__PURE__*/React.createElement("div", {
      className: "convo-participants"
    }, /*#__PURE__*/React.createElement("span", {
      className: "convo-participants-label"
    }, "In council"), /*#__PURE__*/React.createElement(ParticipantStack, {
      members: thread.participants.map(u => ({
        name: RB.users[u].name,
        username: u
      })),
      max: 4
    })), /*#__PURE__*/React.createElement(StarButton, {
      active: starred,
      onClick: onStar
    })))), /*#__PURE__*/React.createElement("div", {
      className: "post-stream"
    }, thread.posts.map((p, i) => {
      const pa = RB.users[p.author];
      const reactions = (p.reactions || []).map((r, j) => /*#__PURE__*/React.createElement(Reaction, {
        key: j,
        name: r.name,
        count: r.count,
        active: r.on,
        icon: r.icon ? ICONS[r.icon] : undefined
      }));
      return /*#__PURE__*/React.createElement(Post, {
        key: i,
        author: pa.name,
        authorSeed: p.author,
        authorHref: "#",
        authorTier: pa.tier,
        handle: pa.username,
        authorTitle: pa.title,
        presence: pa.presence,
        time: p.time,
        rep: p.rep,
        op: p.op,
        staff: p.staff,
        accepted: p.accepted,
        reactions: reactions.length ? reactions : null
      }, /*#__PURE__*/React.createElement("p", {
        style: {
          margin: 0
        }
      }, p.body));
    })), user ? /*#__PURE__*/React.createElement(Composer, {
      postingAs: user.name,
      sendLabel: "Reply",
      value: replyValue,
      onChange: e => onReplyChange(e.target.value),
      count: (replyValue ? replyValue.length : 0) + ' / 20000',
      onSubmit: e => {
        e.preventDefault();
        onSend();
      }
    }) : /*#__PURE__*/React.createElement(JoinBar, null)));
  }
  window.RBConversation = Conversation;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/retroboards/Conversation.jsx", error: String((e && e.message) || e) }); }

// ui_kits/retroboards/Inbox.jsx
try { (() => {
/* RetroBoards — the inbox list pane (middle column). */
(function () {
  const Plus = {
    d: 'M12 5v14M5 12h14'
  };
  function Inbox({
    board,
    threads,
    density,
    onDensity,
    filter,
    onFilter,
    sort,
    onSort,
    activeId,
    onOpen,
    user,
    onNewTopic,
    hiddenOnMobile
  }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      ThreadRow,
      Tabs,
      Button
    } = DS;
    const PlusIcon = /*#__PURE__*/React.createElement("svg", {
      className: "btn-icon",
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("path", {
      d: Plus.d
    }));
    const RB = window.RB;
    let eyebrow = 'For you',
      heading = 'The Council Inbox',
      desc = null;
    if (board) {
      const b = RB.categories.flatMap(c => c.boards).find(x => x.slug === board);
      eyebrow = '#' + board;
      heading = b ? b.name : board;
      desc = b ? b.desc : null;
    }
    return /*#__PURE__*/React.createElement("div", {
      className: 'inbox-list' + (hiddenOnMobile ? ' is-hidden' : '')
    }, /*#__PURE__*/React.createElement("div", {
      className: "inbox-list-head"
    }, /*#__PURE__*/React.createElement("span", {
      className: "eyebrow"
    }, eyebrow), /*#__PURE__*/React.createElement("div", {
      className: "inbox-list-head-row"
    }, /*#__PURE__*/React.createElement("h1", {
      className: "thread-title-display",
      style: {
        fontFamily: 'var(--font-display)',
        fontWeight: 500,
        fontSize: '1.85rem',
        margin: 0,
        color: 'var(--text-strong)'
      }
    }, board ? /*#__PURE__*/React.createElement("span", null, /*#__PURE__*/React.createElement("span", {
      className: "hash"
    }, "#"), heading) : heading), user ? /*#__PURE__*/React.createElement(Button, {
      icon: PlusIcon,
      onClick: onNewTopic
    }, "New topic") : null), desc ? /*#__PURE__*/React.createElement("p", {
      className: "muted",
      style: {
        margin: '4px 0 0',
        fontSize: '.95rem'
      }
    }, desc) : null), /*#__PURE__*/React.createElement("div", {
      className: "inbox-toolbar"
    }, /*#__PURE__*/React.createElement(Tabs, {
      variant: "segment",
      items: ['Hall', 'Watch'],
      value: density,
      onChange: onDensity
    })), /*#__PURE__*/React.createElement("div", {
      className: "inbox-sort"
    }, /*#__PURE__*/React.createElement(Tabs, {
      variant: "underline",
      items: ['Active', 'Newest', 'Unanswered'],
      value: sort,
      onChange: onSort
    }), /*#__PURE__*/React.createElement(Tabs, {
      variant: "pill",
      items: ['All', 'Unread', 'Starred', 'Mine'],
      value: filter,
      onChange: onFilter
    })), threads.length ? /*#__PURE__*/React.createElement("ul", {
      className: 'thread-list' + (density === 'Watch' ? ' is-compact' : '')
    }, threads.map(t => /*#__PURE__*/React.createElement(ThreadRow, {
      key: t.id,
      title: t.title,
      author: RB.users[t.author].name,
      authorSeed: t.author,
      authorTier: RB.users[t.author].tier,
      authorRep: RB.fmt(RB.users[t.author].rep),
      presence: RB.users[t.author].presence,
      giltAuthor: RB.users[t.author].rep >= 3000,
      status: t.status,
      pinned: t.pinned,
      replies: t.replies,
      time: t.time,
      commends: t.commends,
      starred: t.starred,
      unread: t.unread,
      snippet: t.snippet,
      showBoard: !board,
      board: t.board,
      boardName: t.board,
      active: t.id === activeId,
      onClick: e => {
        e.preventDefault();
        onOpen(t.id);
      },
      style: {
        cursor: 'pointer'
      }
    }))) : /*#__PURE__*/React.createElement("div", {
      className: "inbox-empty",
      style: {
        textAlign: 'center',
        padding: '56px 16px',
        color: 'var(--text-muted)'
      }
    }, /*#__PURE__*/React.createElement("p", {
      style: {
        fontFamily: 'var(--font-display)',
        fontSize: '1.4rem',
        color: 'var(--text-strong)',
        margin: '0 0 4px'
      }
    }, "Nothing here yet"), /*#__PURE__*/React.createElement("p", {
      style: {
        margin: 0
      }
    }, "No topics match this filter.")));
  }
  window.RBInbox = Inbox;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/retroboards/Inbox.jsx", error: String((e && e.message) || e) }); }

// ui_kits/retroboards/Leaderboard.jsx
try { (() => {
/* RetroBoards — top contributors (leaderboard). */
(function () {
  const ROMAN = ['I', 'II', 'III'];
  function Leaderboard({
    onOpenProfile
  }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      Monogram
    } = DS;
    const RB = window.RB;
    const rows = RB.leaderboard;
    const top = rows.slice(0, 3);
    const rest = rows.slice(3);
    return /*#__PURE__*/React.createElement("div", {
      className: "screen-pad"
    }, /*#__PURE__*/React.createElement("div", {
      className: "leaderboard-screen"
    }, /*#__PURE__*/React.createElement("span", {
      className: "eyebrow"
    }, "The council"), /*#__PURE__*/React.createElement("h1", {
      style: {
        marginTop: 4
      }
    }, "Top contributors"), /*#__PURE__*/React.createElement("p", {
      className: "muted",
      style: {
        margin: '0 0 18px',
        maxWidth: '56ch'
      }
    }, "Ranked by Regard \u2014 the sum of Commends a member's counsel has earned."), top.map((r, i) => {
      const u = RB.users[r.username];
      return /*#__PURE__*/React.createElement("div", {
        className: "lb-top",
        key: r.username
      }, /*#__PURE__*/React.createElement("span", {
        className: "lb-rank-roman"
      }, ROMAN[i]), /*#__PURE__*/React.createElement(Monogram, {
        name: u.name,
        username: u.username,
        size: "lg",
        gilt: true
      }), /*#__PURE__*/React.createElement("div", {
        style: {
          minWidth: 0
        }
      }, /*#__PURE__*/React.createElement("div", {
        className: "lb-name",
        style: {
          cursor: 'pointer'
        },
        onClick: () => onOpenProfile(r.username)
      }, u.name), /*#__PURE__*/React.createElement("div", {
        className: "lb-handle"
      }, "@", u.username, " \xB7 ", u.title)), /*#__PURE__*/React.createElement("span", {
        className: "lb-rep"
      }, /*#__PURE__*/React.createElement("span", {
        className: "star-marker"
      }, "\u2726"), r.rep.toLocaleString()));
    }), /*#__PURE__*/React.createElement("ul", {
      className: "leaderboard-list"
    }, rest.map((r, i) => {
      const u = RB.users[r.username];
      return /*#__PURE__*/React.createElement("li", {
        className: "leaderboard-row",
        key: r.username
      }, /*#__PURE__*/React.createElement("span", {
        className: "lb-rank"
      }, i + 4), /*#__PURE__*/React.createElement(Monogram, {
        name: u.name,
        username: u.username,
        size: "sm"
      }), /*#__PURE__*/React.createElement("span", {
        className: "lb-name",
        style: {
          fontSize: '1rem',
          cursor: 'pointer'
        },
        onClick: () => onOpenProfile(r.username)
      }, u.name), /*#__PURE__*/React.createElement("span", {
        className: "lb-row-rep"
      }, /*#__PURE__*/React.createElement("span", {
        className: "star-marker"
      }, "\u2726"), r.rep.toLocaleString()));
    })), /*#__PURE__*/React.createElement("p", {
      className: "lb-note"
    }, "Regard is earned, never assigned \u2014 remove a post or a commend and it adjusts itself. The leaderboard is a record of counsel kept, not a contest.")));
  }
  window.RBLeaderboard = Leaderboard;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/retroboards/Leaderboard.jsx", error: String((e && e.message) || e) }); }

// ui_kits/retroboards/Profile.jsx
try { (() => {
/* RetroBoards — member profile (twilight identity cover). */
(function () {
  function Profile({
    userKey,
    onBack
  }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      EightPointStar,
      Monogram,
      Button,
      Tabs
    } = DS;
    const RB = window.RB;
    const u = RB.users[userKey] || RB.users.erestor;
    const [tab, setTab] = React.useState('Overview');
    const activity = [{
      ic: 'check',
      text: 'Answered ',
      link: 'Who changed what — and can you prove the rollback?',
      tail: ' in #audit-trails'
    }, {
      ic: 'star',
      text: 'Earned the ',
      link: 'Trusted Answerer',
      tail: ' mark of esteem'
    }, {
      ic: 'msg',
      text: 'Opened ',
      link: 'On exposing capability before we are asked',
      tail: ' in #capability-disclosure'
    }];
    return /*#__PURE__*/React.createElement("div", {
      className: "screen-pad"
    }, /*#__PURE__*/React.createElement("div", {
      className: "profile-screen"
    }, /*#__PURE__*/React.createElement("button", {
      className: "breadcrumb",
      onClick: onBack,
      style: {
        marginBottom: 10
      }
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      width: "13",
      height: "13",
      fill: "none",
      stroke: "currentColor",
      strokeWidth: "2",
      strokeLinecap: "round",
      strokeLinejoin: "round"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M15 18l-6-6 6-6"
    })), "Back to inbox"), /*#__PURE__*/React.createElement("div", {
      className: "profile-cover"
    }, /*#__PURE__*/React.createElement("span", {
      className: "profile-cover-star",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 196,
      variant: "watermark",
      style: {
        opacity: 1,
        width: 196,
        height: 196
      }
    })), /*#__PURE__*/React.createElement("span", {
      className: "profile-avatar"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: u.name,
      username: u.username,
      size: "xl",
      gilt: true
    }), /*#__PURE__*/React.createElement("span", {
      className: "presence-dot",
      "aria-hidden": "true"
    })), /*#__PURE__*/React.createElement("div", {
      className: "profile-id"
    }, /*#__PURE__*/React.createElement("h1", {
      className: "profile-name"
    }, u.name, " ", /*#__PURE__*/React.createElement("span", {
      className: "profile-tier"
    }, u.tier)), /*#__PURE__*/React.createElement("p", {
      className: "profile-handle"
    }, "@", u.username, " \xB7 ", u.title), /*#__PURE__*/React.createElement("p", {
      className: "profile-meta"
    }, "Joined Third Age, 2021 \xB7 Imladris"), /*#__PURE__*/React.createElement("dl", {
      className: "profile-stats"
    }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("dt", null, "Followers"), /*#__PURE__*/React.createElement("dd", null, "418")), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("dt", null, "Following"), /*#__PURE__*/React.createElement("dd", null, "112")), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("dt", null, "Posts"), /*#__PURE__*/React.createElement("dd", null, "1,204")))), /*#__PURE__*/React.createElement("div", {
      className: "profile-aside"
    }, /*#__PURE__*/React.createElement("div", {
      className: "profile-rep"
    }, /*#__PURE__*/React.createElement("span", {
      className: "profile-rep-value"
    }, /*#__PURE__*/React.createElement("span", {
      className: "star-marker"
    }, "\u2726"), u.rep.toLocaleString()), /*#__PURE__*/React.createElement("span", {
      className: "profile-rep-label"
    }, "Commends earned")), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        gap: 8
      }
    }, /*#__PURE__*/React.createElement(Button, {
      variant: "accent"
    }, "Follow"), /*#__PURE__*/React.createElement(Button, {
      variant: "secondary"
    }, "Message")))), /*#__PURE__*/React.createElement("div", {
      className: "profile-badges"
    }, /*#__PURE__*/React.createElement("p", {
      className: "profile-badges-label"
    }, "Marks of esteem"), /*#__PURE__*/React.createElement("ul", {
      className: "badge-row"
    }, RB.badges.map(b => /*#__PURE__*/React.createElement("li", {
      key: b.label,
      className: 'badge-chip' + (b.locked ? ' is-locked' : '')
    }, b.locked ? /*#__PURE__*/React.createElement("span", {
      "aria-hidden": "true",
      style: {
        display: 'inline-flex',
        color: 'var(--text-muted)'
      }
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      width: "11",
      height: "11",
      fill: "none",
      stroke: "currentColor",
      strokeWidth: "2",
      strokeLinecap: "round",
      strokeLinejoin: "round"
    }, /*#__PURE__*/React.createElement("rect", {
      x: "3",
      y: "11",
      width: "18",
      height: "11",
      rx: "2"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M7 11V7a5 5 0 0 1 10 0v4"
    }))) : /*#__PURE__*/React.createElement("span", {
      className: "b-dot",
      "aria-hidden": "true"
    }), b.label, b.locked ? ' · locked' : '')))), /*#__PURE__*/React.createElement(Tabs, {
      variant: "underline",
      items: ['Overview', 'Threads', 'Posts', 'Commends'],
      value: tab,
      onChange: setTab,
      className: "profile"
    }), /*#__PURE__*/React.createElement("ul", {
      style: {
        listStyle: 'none',
        padding: 0,
        margin: 0
      }
    }, activity.map((a, i) => /*#__PURE__*/React.createElement("li", {
      key: i,
      style: {
        display: 'flex',
        gap: 12,
        alignItems: 'flex-start',
        padding: '12px 0',
        borderTop: '1px solid var(--border-hair)'
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        flex: '0 0 auto',
        width: 30,
        height: 30,
        borderRadius: 8,
        background: 'var(--surface-sunken)',
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        color: 'var(--gold-ink)'
      }
    }, a.ic === 'star' ? /*#__PURE__*/React.createElement("span", {
      style: {
        color: 'var(--star)'
      }
    }, "\u2726") : /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      width: "14",
      height: "14",
      fill: "none",
      stroke: "currentColor",
      strokeWidth: "2",
      strokeLinecap: "round",
      strokeLinejoin: "round"
    }, a.ic === 'check' ? /*#__PURE__*/React.createElement("path", {
      d: "M20 6L9 17l-5-5"
    }) : /*#__PURE__*/React.createElement("path", {
      d: "M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
    }))), /*#__PURE__*/React.createElement("span", {
      style: {
        color: 'var(--text-body)'
      }
    }, a.text, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => e.preventDefault(),
      style: {
        fontWeight: 600
      }
    }, a.link), a.tail))))));
  }
  window.RBProfile = Profile;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/retroboards/Profile.jsx", error: String((e && e.message) || e) }); }

// ui_kits/retroboards/Rail.jsx
try { (() => {
/* RetroBoards — sidebar rail. Quick filters, board categories, who's online. */
(function () {
  const ICON = {
    inbox: ['M22 12h-6l-2 3h-4l-2-3H2', 'M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z'],
    mentions: ['M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8'],
    watching: ['M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z'],
    drafts: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6'],
    top: ['M8 21h8', 'M12 17v4', 'M7 4h10v4a5 5 0 0 1-10 0z', 'M5 4H3v2a3 3 0 0 0 3 3', 'M19 4h2v2a3 3 0 0 1-3 3']
  };
  function Ic({
    name
  }) {
    return /*#__PURE__*/React.createElement("svg", {
      className: "rail-ic",
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, ICON[name].map((d, i) => /*#__PURE__*/React.createElement("path", {
      key: i,
      d: d
    })), name === 'mentions' ? /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "12",
      r: "4"
    }) : null, name === 'watching' ? /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "12",
      r: "3"
    }) : null);
  }
  function Rail({
    view,
    board,
    onFilter,
    onBoard,
    user
  }) {
    const RB = window.RB;
    const filters = [{
      key: 'inbox',
      label: 'Inbox',
      count: 2
    }, {
      key: 'mentions',
      label: 'Mentions',
      count: 2
    }, {
      key: 'watching',
      label: 'Watching'
    }, {
      key: 'drafts',
      label: 'Drafts'
    }, {
      key: 'top',
      label: 'Top contributors'
    }];
    return /*#__PURE__*/React.createElement("aside", {
      className: "sidebar"
    }, user ? /*#__PURE__*/React.createElement("ul", {
      className: "rail-filters"
    }, filters.map(f => {
      const active = f.key === 'top' && view === 'leaderboard' || f.key === 'inbox' && view === 'inbox' && !board || view === f.key;
      return /*#__PURE__*/React.createElement("li", {
        key: f.key
      }, /*#__PURE__*/React.createElement("button", {
        className: 'rail-filter' + (active ? ' active' : ''),
        onClick: () => onFilter(f.key)
      }, /*#__PURE__*/React.createElement(Ic, {
        name: f.key
      }), /*#__PURE__*/React.createElement("span", null, f.label), f.count ? /*#__PURE__*/React.createElement("span", {
        className: "rail-count"
      }, f.count) : null));
    })) : null, RB.categories.map(cat => /*#__PURE__*/React.createElement("div", {
      className: "nav-cat",
      key: cat.name
    }, /*#__PURE__*/React.createElement("span", {
      className: "nav-cat-name"
    }, cat.name), /*#__PURE__*/React.createElement("ul", {
      className: "nav-boards"
    }, cat.boards.map(b => /*#__PURE__*/React.createElement("li", {
      key: b.slug
    }, /*#__PURE__*/React.createElement("button", {
      className: board === b.slug ? 'active' : '',
      onClick: () => onBoard(b.slug)
    }, /*#__PURE__*/React.createElement("span", {
      className: "hash"
    }, "#"), /*#__PURE__*/React.createElement("span", null, b.name), /*#__PURE__*/React.createElement("span", {
      className: "rail-count-soft"
    }, b.count))))))), /*#__PURE__*/React.createElement("section", {
      className: "presence-widget"
    }, /*#__PURE__*/React.createElement("h2", {
      className: "presence-title"
    }, "Online \xB7 4"), /*#__PURE__*/React.createElement("ul", {
      className: "presence-list"
    }, ['galadriel', 'elrond', 'arwen', 'erestor'].map(u => /*#__PURE__*/React.createElement("li", {
      key: u
    }, /*#__PURE__*/React.createElement("span", {
      className: "dot"
    }), RB.users[u].name)))));
  }
  window.RBRail = Rail;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/retroboards/Rail.jsx", error: String((e && e.message) || e) }); }

// ui_kits/retroboards/Topbar.jsx
try { (() => {
/* RetroBoards — top bar. Brand mark + search + bell + identity (guest vs member). */
(function () {
  function Topbar({
    user,
    onBrand,
    onProfile,
    onLogout,
    onToggleAuth
  }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      EightPointStar,
      Input,
      Pill,
      Button
    } = DS;
    return /*#__PURE__*/React.createElement("header", {
      className: "topbar"
    }, /*#__PURE__*/React.createElement("div", {
      className: "topbar-inner"
    }, /*#__PURE__*/React.createElement("span", {
      className: "brand",
      onClick: onBrand
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 26
    }), /*#__PURE__*/React.createElement("span", {
      className: "brand-name"
    }, "RetroBoards")), /*#__PURE__*/React.createElement("form", {
      className: "topbar-search",
      onSubmit: e => e.preventDefault(),
      role: "search"
    }, /*#__PURE__*/React.createElement(Input, {
      pill: true,
      type: "search",
      placeholder: "Search the council\u2026",
      "aria-label": "Search the council"
    })), /*#__PURE__*/React.createElement("div", {
      className: "topbar-right"
    }, user ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("span", {
      className: "bell",
      title: "Notifications"
    }, /*#__PURE__*/React.createElement("svg", {
      className: "bell-ic",
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M10.3 21a1.94 1.94 0 0 0 3.4 0"
    })), /*#__PURE__*/React.createElement("span", {
      className: "bell-dot",
      "aria-hidden": "true"
    })), /*#__PURE__*/React.createElement("span", {
      className: "topbar-user",
      onClick: onProfile
    }, /*#__PURE__*/React.createElement(DS.Monogram, {
      name: user.name,
      username: user.username,
      size: "sm",
      presence: "online"
    }), /*#__PURE__*/React.createElement("span", {
      className: "topbar-name"
    }, user.name)), /*#__PURE__*/React.createElement("svg", {
      className: "topbar-ic",
      viewBox: "0 0 24 24",
      "aria-hidden": "true",
      title: "Settings"
    }, /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "12",
      r: "3"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H2a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 3.6 8a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H8a1.65 1.65 0 0 0 1-1.51V2a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H22a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"
    })), /*#__PURE__*/React.createElement("button", {
      className: "topbar-logout",
      onClick: onLogout
    }, "Log out")) : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(Pill, null, "Guest"), /*#__PURE__*/React.createElement("button", {
      className: "topbar-logout",
      onClick: onToggleAuth
    }, "Log in"), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      onClick: onToggleAuth
    }, "Sign up")))));
  }
  window.RBTopbar = Topbar;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/retroboards/Topbar.jsx", error: String((e && e.message) || e) }); }

// ui_kits/retroboards/data.js
try { (() => {
/* RetroBoards seed data — illustrative council content in the Imladris register.
   Shared with the kit's JSX via window.RB. */
(function () {
  const users = {
    erestor: {
      username: 'erestor',
      name: 'Erestor',
      tier: 'Legend',
      title: 'Loremaster of Imladris',
      rep: 3985,
      presence: 'online'
    },
    galadriel: {
      username: 'galadriel',
      name: 'Galadriel',
      tier: 'Loremaster',
      title: 'Lady of the Wood',
      rep: 5120,
      presence: 'online'
    },
    elrond: {
      username: 'elrond',
      name: 'Elrond',
      tier: 'Legend',
      title: 'Master of the House',
      rep: 8740,
      presence: 'online'
    },
    glorfindel: {
      username: 'glorfindel',
      name: 'Glorfindel',
      tier: 'Veteran',
      title: 'Captain of the Vale',
      rep: 2140,
      presence: 'away'
    },
    arwen: {
      username: 'arwen',
      name: 'Arwen',
      tier: 'Veteran',
      title: 'Evenstar',
      rep: 1760,
      presence: 'online'
    },
    lindir: {
      username: 'lindir',
      name: 'Lindir',
      tier: 'Member',
      title: 'Keeper of Songs',
      rep: 940,
      presence: 'offline'
    }
  };
  const categories = [{
    name: 'The Commons',
    boards: [{
      slug: 'announcements',
      name: 'announcements',
      desc: 'Notices from the house',
      count: 12
    }, {
      slug: 'introductions',
      name: 'introductions',
      desc: 'Newcomers, say hello',
      count: 31
    }, {
      slug: 'the-valley',
      name: 'the-valley',
      desc: 'Open talk of the vale',
      count: 88
    }]
  }, {
    name: 'Vilya · Expose',
    boards: [{
      slug: 'interpretability',
      name: 'interpretability',
      desc: 'Reading the machine',
      count: 47
    }, {
      slug: 'evaluations',
      name: 'evaluations',
      desc: 'Tests, rites, gates',
      count: 63
    }, {
      slug: 'capability-disclosure',
      name: 'capability-disclosure',
      desc: 'What we publish, when',
      count: 22
    }, {
      slug: 'audit-trails',
      name: 'audit-trails',
      desc: 'Who changed what',
      count: 39
    }]
  }];
  const threads = [{
    id: 1,
    board: 'evaluations',
    author: 'galadriel',
    status: 'solved',
    pinned: false,
    title: 'Evaluations as ritual, not gate',
    replies: 23,
    time: '2h',
    commends: 31,
    starred: true,
    unread: false,
    snippet: 'We keep treating the eval suite as a turnstile. What if it were a rite the whole council kept?',
    participants: ['galadriel', 'elrond', 'arwen', 'erestor', 'lindir'],
    posts: [{
      author: 'galadriel',
      op: true,
      time: '2 days ago',
      rep: '5.1k',
      body: 'We keep treating the eval suite as a turnstile — pass and forget. I want to argue it should be a rite: something the whole council performs, reads, and remembers.',
      reactions: [{
        name: 'Commend',
        count: 31,
        on: true
      }, {
        name: 'Illuminating',
        count: 9,
        icon: 'spark'
      }]
    }, {
      author: 'elrond',
      time: '1 day ago',
      rep: '8.7k',
      staff: true,
      body: 'Agreed. A gate asks "may this pass?" A rite asks "what did we learn, and who will carry it?" The second question is the one that compounds.',
      reactions: [{
        name: 'Seconded',
        count: 14,
        icon: 'check'
      }]
    }, {
      author: 'arwen',
      time: '22h',
      rep: '1.7k',
      accepted: true,
      body: 'Here is the shape that worked for us: every eval run resolves into an artifact — a short written verdict, linked from the topic. The rite is reading the last three before you open a new one.',
      reactions: [{
        name: 'Commend',
        count: 26,
        on: true
      }, {
        name: 'Kindled',
        count: 7,
        icon: 'flame'
      }]
    }]
  }, {
    id: 2,
    board: 'audit-trails',
    author: 'erestor',
    status: 'open',
    pinned: false,
    title: 'Who changed what — and can you prove the rollback?',
    replies: 41,
    time: '5h',
    commends: 54,
    starred: false,
    unread: true,
    snippet: 'The diff is small; the audit trail must be whole. Here is the rollback drill we ran on Tuesday.',
    participants: ['erestor', 'glorfindel', 'elrond', 'galadriel'],
    posts: [{
      author: 'erestor',
      op: true,
      time: '2 days ago',
      rep: '3.9k',
      body: 'The diff is small; the audit trail must be whole. AI proposes; the council approves. Every change should answer three questions: who changed what, was it authorized, and can you prove the rollback?',
      reactions: [{
        name: 'Commend',
        count: 54,
        on: false
      }, {
        name: 'Seconded',
        count: 19,
        icon: 'check'
      }]
    }, {
      author: 'glorfindel',
      time: '1 day ago',
      rep: '2.1k',
      body: 'We ran the rollback drill on Tuesday. The surprise was not the revert — it was discovering two actors could edit the same setting with no record of precedence. Fixed now.',
      reactions: [{
        name: 'Kindled',
        count: 11,
        icon: 'flame'
      }]
    }]
  }, {
    id: 3,
    board: 'capability-disclosure',
    author: 'elrond',
    status: 'decision_made',
    pinned: false,
    title: 'On exposing capability before we are asked',
    replies: 12,
    time: '1d',
    commends: 28,
    starred: false,
    unread: false,
    snippet: 'A decision, recorded: we publish the capability notes the same day we brief the council, not after.',
    participants: ['elrond', 'galadriel', 'erestor'],
    posts: [{
      author: 'elrond',
      op: true,
      time: '3 days ago',
      rep: '8.7k',
      staff: true,
      body: 'A decision, recorded: we publish the capability notes the same day we brief the council — not after. Disclosure that trails the briefing is not disclosure; it is a press release.',
      reactions: [{
        name: 'Commend',
        count: 28,
        on: false
      }]
    }]
  }, {
    id: 4,
    board: 'announcements',
    author: 'elrond',
    status: 'open',
    pinned: true,
    title: 'The hall reopens — read this first',
    replies: 8,
    time: '3d',
    commends: 64,
    starred: false,
    unread: false,
    snippet: 'A short charter for how we keep counsel here: verify, record, and never let testimony outrank the work.',
    participants: ['elrond', 'erestor'],
    posts: [{
      author: 'elrond',
      op: true,
      time: '5 days ago',
      rep: '8.7k',
      staff: true,
      body: 'Welcome back. A short charter for how we keep counsel here: status is verified, not asserted; outcomes resolve into artifacts; testimony never outranks the work. Star this topic — we will amend it as the council grows.',
      reactions: [{
        name: 'Commend',
        count: 64,
        on: false
      }]
    }]
  }, {
    id: 5,
    board: 'interpretability',
    author: 'arwen',
    status: 'needs_answer',
    pinned: false,
    title: 'Reading attention as a map, not a verdict',
    replies: 17,
    time: '6h',
    commends: 19,
    starred: false,
    unread: true,
    snippet: 'Attention tells you where the model looked, not what it concluded. How do you keep the two separate?',
    participants: ['arwen', 'lindir', 'galadriel'],
    posts: [{
      author: 'arwen',
      op: true,
      time: '1 day ago',
      rep: '1.7k',
      body: 'Attention tells you where the model looked, not what it concluded. I keep watching people read a verdict into a heatmap. How do you keep the map and the verdict separate in your own notes?',
      reactions: [{
        name: 'Kindled',
        count: 6,
        icon: 'flame'
      }]
    }]
  }, {
    id: 6,
    board: 'introductions',
    author: 'lindir',
    status: 'open',
    pinned: false,
    title: 'Newly arrived — keeper of songs, learner of evals',
    replies: 4,
    time: '8h',
    commends: 12,
    starred: false,
    unread: false,
    snippet: 'Hello, council. I keep the songs of the house and I am here to learn how you read the machine.',
    participants: ['lindir', 'arwen'],
    posts: [{
      author: 'lindir',
      op: true,
      time: '8 hours ago',
      rep: '940',
      body: 'Hello, council. I keep the songs of the house and I am here to learn how you read the machine. Point me at the three topics you wish you had read first.',
      reactions: [{
        name: 'Commend',
        count: 12,
        on: false
      }]
    }]
  }];
  const leaderboard = [{
    username: 'elrond',
    rep: 8740
  }, {
    username: 'galadriel',
    rep: 5120
  }, {
    username: 'erestor',
    rep: 3985
  }, {
    username: 'glorfindel',
    rep: 2140
  }, {
    username: 'arwen',
    rep: 1760
  }, {
    username: 'lindir',
    rep: 940
  }];
  const badges = [{
    label: 'Welcome',
    on: true
  }, {
    label: 'First Thread',
    on: true
  }, {
    label: 'Conversation Starter',
    on: true
  }, {
    label: 'Well-Liked',
    on: true
  }, {
    label: 'Trusted Answerer',
    on: true
  }, {
    label: 'Problem Solver',
    on: true
  }, {
    label: 'Anniversary',
    on: true
  }, {
    label: 'Well of 1,000',
    on: false,
    locked: true
  }];

  // Compact regard formatter: 5120 → "5.1k", 940 → "940".
  function fmt(n) {
    n = Number(n) || 0;
    return n >= 1000 ? (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k' : String(n);
  }
  window.RB = {
    users,
    categories,
    threads,
    leaderboard,
    badges,
    currentUserKey: 'erestor',
    fmt
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/retroboards/data.js", error: String((e && e.message) || e) }); }

// ui_kits/settings/Chrome.jsx
try { (() => {
/* Settings kit — chrome: slim top bar + the lapidary settings subnav. */
(function () {
  function Topbar({
    user,
    onBrand
  }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      EightPointStar,
      Monogram
    } = DS;
    return /*#__PURE__*/React.createElement("header", {
      className: "topbar"
    }, /*#__PURE__*/React.createElement("div", {
      className: "topbar-inner"
    }, /*#__PURE__*/React.createElement("span", {
      className: "brand",
      onClick: onBrand
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 26
    }), /*#__PURE__*/React.createElement("span", {
      className: "brand-name"
    }, "RetroBoards")), /*#__PURE__*/React.createElement("span", {
      className: "topbar-spacer"
    }), /*#__PURE__*/React.createElement("span", {
      className: "topbar-user"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: user.name,
      username: user.username,
      size: "sm",
      presence: "online"
    }), /*#__PURE__*/React.createElement("span", {
      className: "topbar-name"
    }, user.name))));
  }

  // The settings subnav. Order + labels mirror partials/settings_nav.php.
  const NAV = [{
    key: 'account',
    label: 'Profile'
  }, {
    key: 'security',
    label: 'Security'
  }, {
    key: 'privacy',
    label: 'Privacy'
  }, {
    key: 'appearance',
    label: 'Appearance'
  }, {
    key: 'preferences',
    label: 'Reading'
  }, {
    key: 'composing',
    label: 'Composing'
  }, {
    key: 'drafts',
    label: 'Drafts'
  }, {
    key: 'notifications',
    label: 'Notifications'
  }, {
    key: 'connections',
    label: 'Connections'
  }, {
    key: 'sessions',
    label: 'Sessions'
  }, {
    key: 'blocks',
    label: 'Blocks'
  }, {
    key: 'boards',
    label: 'Boards'
  }, {
    key: 'lifecycle',
    label: 'Account'
  }];
  function SettingsNav({
    active,
    onNav
  }) {
    return /*#__PURE__*/React.createElement("nav", {
      className: "subnav",
      "aria-label": "Settings sections"
    }, NAV.map(it => /*#__PURE__*/React.createElement("a", {
      key: it.key,
      className: active === it.key ? 'active' : '',
      "aria-current": active === it.key ? 'page' : undefined,
      onClick: e => {
        e.preventDefault();
        onNav(it.key);
      },
      href: '#' + it.key
    }, it.label)));
  }
  window.SETTopbar = Topbar;
  window.SETNav = SettingsNav;
  window.SET_NAV = NAV;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/settings/Chrome.jsx", error: String((e && e.message) || e) }); }

// ui_kits/settings/SettingsApp.jsx
try { (() => {
/* Settings kit — app shell. Slim topbar + sticky rail subnav + content pane. */
(function () {
  const ICON = {
    user: ['M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2', 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
    shield: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
    eye: ['M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'],
    sun: ['M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10z', 'M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4'],
    book: ['M4 19.5A2.5 2.5 0 0 1 6.5 17H20', 'M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z'],
    pen: ['M12 19l7-7 3 3-7 7-3-3z', 'M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z', 'M2 2l7.586 7.586'],
    file: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6'],
    bell: ['M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9', 'M10.3 21a1.94 1.94 0 0 0 3.4 0'],
    link: ['M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71', 'M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71'],
    monitor: ['M20 3H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z', 'M8 21h8M12 17v4'],
    ban: ['M4.9 4.9l14.2 14.2', 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z'],
    hash: ['M4 9h16M4 15h16M10 3L8 21M16 3l-2 18'],
    archive: ['M21 8v13H3V8', 'M1 3h22v5H1zM10 12h4']
  };
  function Ic({
    name
  }) {
    return /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, (ICON[name] || []).map((d, i) => /*#__PURE__*/React.createElement("path", {
      key: i,
      d: d
    })));
  }
  function App() {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      EightPointStar,
      Monogram
    } = DS;
    const SECT = window.RBSettingsSections;
    const u = window.RBSettings.user;
    const keys = Object.keys(SECT);
    const [active, setActive] = React.useState('account');

    // Group the rail (preserve declaration order within each group).
    const groups = [];
    keys.forEach(k => {
      const g = SECT[k].group;
      let bucket = groups.find(x => x.name === g);
      if (!bucket) {
        bucket = {
          name: g,
          items: []
        };
        groups.push(bucket);
      }
      bucket.items.push(k);
    });
    const Section = SECT[active].render;
    return /*#__PURE__*/React.createElement("div", {
      className: "app-root"
    }, /*#__PURE__*/React.createElement("header", {
      className: "topbar"
    }, /*#__PURE__*/React.createElement("div", {
      className: "topbar-inner"
    }, /*#__PURE__*/React.createElement("a", {
      className: "brand",
      href: "../retroboards/index.html"
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 26
    }), /*#__PURE__*/React.createElement("span", {
      className: "brand-name"
    }, "RetroBoards")), /*#__PURE__*/React.createElement("a", {
      className: "topbar-back",
      href: "../retroboards/index.html"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M15 18l-6-6 6-6"
    })), " Back to the inbox"), /*#__PURE__*/React.createElement("div", {
      className: "topbar-right"
    }, /*#__PURE__*/React.createElement("span", {
      className: "topbar-user"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: u.name,
      username: u.username,
      size: "sm",
      presence: "online"
    }), /*#__PURE__*/React.createElement("span", {
      className: "topbar-name"
    }, u.name)), /*#__PURE__*/React.createElement("button", {
      className: "topbar-logout",
      type: "button"
    }, "Log out")))), /*#__PURE__*/React.createElement("div", {
      className: "settings-screen"
    }, /*#__PURE__*/React.createElement("div", {
      className: "settings-head"
    }, /*#__PURE__*/React.createElement("span", {
      className: "eyebrow"
    }, "Your seat at the council"), /*#__PURE__*/React.createElement("h1", null, "Account settings")), /*#__PURE__*/React.createElement("div", {
      className: "settings"
    }, /*#__PURE__*/React.createElement("nav", {
      className: "settings-rail",
      "aria-label": "Settings sections"
    }, groups.map(g => /*#__PURE__*/React.createElement(React.Fragment, {
      key: g.name
    }, /*#__PURE__*/React.createElement("span", {
      className: "settings-rail-cat"
    }, g.name), g.items.map(k => /*#__PURE__*/React.createElement("button", {
      key: k,
      className: 'rail-link' + (k === active ? ' active' : ''),
      "aria-current": k === active ? 'page' : undefined,
      onClick: () => setActive(k)
    }, /*#__PURE__*/React.createElement(Ic, {
      name: SECT[k].icon
    }), SECT[k].label))))), /*#__PURE__*/React.createElement("div", {
      className: "settings-pane",
      key: active
    }, /*#__PURE__*/React.createElement(Section, null)))));
  }
  window.RBSettingsApp = App;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/settings/SettingsApp.jsx", error: String((e && e.message) || e) }); }

// ui_kits/settings/SettingsSections.jsx
try { (() => {
/* Settings kit — the section panes. Each is a faithful recreation of an
   account/*.php template, composed from design-system primitives and the
   lapidary forms register. window.RBSettingsSections maps key → component. */
(function () {
  const S = () => window.RBSettings;
  const DS = () => window.ImladrisDesignSystem_c3e027;
  const Star = ({
    s = 11
  }) => /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 100 100",
    width: s,
    height: s,
    "aria-hidden": "true",
    style: {
      display: 'block'
    }
  }, /*#__PURE__*/React.createElement("path", {
    fill: "currentColor",
    d: "M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z"
  }));
  const swatch = cls => /*#__PURE__*/React.createElement("span", {
    className: 'theme-swatch ' + cls
  }, /*#__PURE__*/React.createElement("span", {
    className: "sw-bg"
  }), /*#__PURE__*/React.createElement("span", {
    className: "sw-card"
  }), /*#__PURE__*/React.createElement("span", {
    className: "sw-accent"
  }));

  /* ── Profile ──────────────────────────────────────────────────────────── */
  function Profile() {
    const {
      Input,
      Textarea,
      Button,
      Monogram
    } = DS();
    const u = S().user;
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
      className: "scribe-panel"
    }, /*#__PURE__*/React.createElement("span", {
      className: "scribe-panel-head"
    }, "Avatar"), /*#__PURE__*/React.createElement("div", {
      className: "avatar-row"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: u.name,
      username: u.username,
      size: "xl",
      gilt: true
    }), /*#__PURE__*/React.createElement("div", {
      className: "avatar-actions"
    }, /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      size: "sm",
      className: "file-btn",
      icon: /*#__PURE__*/React.createElement("svg", {
        className: "btn-icon",
        viewBox: "0 0 24 24",
        "aria-hidden": "true"
      }, /*#__PURE__*/React.createElement("path", {
        d: "M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"
      }), /*#__PURE__*/React.createElement("path", {
        d: "M17 8l-5-5-5 5"
      }), /*#__PURE__*/React.createElement("path", {
        d: "M12 3v12"
      }))
    }, "Upload avatar"), /*#__PURE__*/React.createElement("p", {
      className: "avatar-hint"
    }, "PNG, JP, GIF or WebP. A gilt ring marks members of high regard."), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn muted",
      type: "button"
    }, "Remove avatar")))), /*#__PURE__*/React.createElement("section", {
      className: "scribe-panel"
    }, /*#__PURE__*/React.createElement("span", {
      className: "scribe-panel-head"
    }, "Identity"), /*#__PURE__*/React.createElement("div", {
      className: "stacked",
      style: {
        gap: 14,
        width: '100%'
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Email ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(not editable in this version)")), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "email",
      defaultValue: u.email,
      disabled: true
    })), /*#__PURE__*/React.createElement("div", {
      className: "field-grid",
      style: {
        width: '100%'
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Display name"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      defaultValue: u.name,
      maxLength: 64
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Pronouns"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      placeholder: "they/them",
      maxLength: 32
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Location"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      placeholder: "Rivendell",
      maxLength: 64
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Website"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "url",
      placeholder: "https://example.com"
    }))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Bio ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(Markdown supported)")), /*#__PURE__*/React.createElement(Textarea, {
      className: "textarea-engraved",
      rows: 4,
      defaultValue: "Keeper of the Red Book; I read the machine and write down what it says.",
      maxLength: 1000
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Signature ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(shown under your posts, max 3 lines)")), /*#__PURE__*/React.createElement(Textarea, {
      className: "textarea-engraved",
      rows: 3,
      defaultValue: "\u201CThe diff is small; the audit trail must be whole.\u201D",
      maxLength: 500
    })))), /*#__PURE__*/React.createElement("section", {
      className: "scribe-panel"
    }, /*#__PURE__*/React.createElement("span", {
      className: "scribe-panel-head"
    }, "Custom profile fields"), /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "Add up to three public facts. Labels to 40 characters; values to 160."), [['Allegiance', 'The Last Homely House'], ['Tongue', 'Sindarin, Quenya'], ['', '']].map((row, i) => /*#__PURE__*/React.createElement("div", {
      className: "field-row",
      key: i,
      style: {
        marginBottom: 9
      }
    }, /*#__PURE__*/React.createElement("span", {
      className: "row-bullet",
      "aria-hidden": "true"
    }), /*#__PURE__*/React.createElement("input", {
      className: "row-input",
      defaultValue: row[0],
      placeholder: "Label",
      style: {
        flex: '0 0 32%'
      },
      "aria-label": 'Custom label ' + (i + 1)
    }), /*#__PURE__*/React.createElement("span", {
      style: {
        color: 'var(--border-strong)'
      }
    }, "\xB7"), /*#__PURE__*/React.createElement("input", {
      className: "row-input",
      defaultValue: row[1],
      placeholder: "Value",
      "aria-label": 'Custom value ' + (i + 1)
    })))), /*#__PURE__*/React.createElement(Button, null, "Save changes"));
  }

  /* ── Security ─────────────────────────────────────────────────────────── */
  function Security() {
    const {
      Input,
      Button
    } = DS();
    const [setup, setSetup] = React.useState(false);
    const [enabled, setEnabled] = React.useState(false);
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
      className: "scribe-panel"
    }, /*#__PURE__*/React.createElement("span", {
      className: "scribe-panel-head"
    }, "Password"), /*#__PURE__*/React.createElement("div", {
      className: "stacked",
      style: {
        width: '100%'
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Current password"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement("div", {
      className: "field-grid",
      style: {
        width: '100%'
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "New password"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "password",
      autoComplete: "new-password"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Confirm new password"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "password",
      autoComplete: "new-password"
    }))), /*#__PURE__*/React.createElement(Button, null, "Change password"))), /*#__PURE__*/React.createElement("section", {
      className: "scribe-panel"
    }, /*#__PURE__*/React.createElement("span", {
      className: "scribe-panel-head"
    }, "Two-factor authentication"), enabled ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "pane-intro",
      style: {
        marginBottom: 8
      }
    }, "Enabled \xB7 ", S().recoveryCodes.length, " recovery codes remaining. Keep these somewhere safe \u2014 each works once."), /*#__PURE__*/React.createElement("h3", null, "Recovery codes"), /*#__PURE__*/React.createElement("ul", {
      className: "code-list"
    }, S().recoveryCodes.map(c => /*#__PURE__*/React.createElement("li", {
      key: c
    }, /*#__PURE__*/React.createElement("code", null, c)))), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        gap: 10,
        marginTop: 16,
        flexWrap: 'wrap'
      }
    }, /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      size: "sm"
    }, "Rotate recovery codes"), /*#__PURE__*/React.createElement(Button, {
      variant: "danger",
      size: "sm",
      onClick: () => {
        setEnabled(false);
        setSetup(false);
      }
    }, "Disable two-factor"))) : setup ? /*#__PURE__*/React.createElement("div", {
      className: "totp-setup"
    }, /*#__PURE__*/React.createElement("p", {
      className: "muted",
      style: {
        margin: 0
      }
    }, "Scan the cipher with your authenticator, then enter the 6-digit code it shows."), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        gap: 15,
        alignItems: 'center',
        flexWrap: 'wrap'
      }
    }, /*#__PURE__*/React.createElement("span", {
      className: "qr-stub",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      width: "30",
      height: "30",
      fill: "none",
      stroke: "currentColor",
      strokeWidth: "1.5"
    }, /*#__PURE__*/React.createElement("rect", {
      x: "3",
      y: "3",
      width: "7",
      height: "7"
    }), /*#__PURE__*/React.createElement("rect", {
      x: "14",
      y: "3",
      width: "7",
      height: "7"
    }), /*#__PURE__*/React.createElement("rect", {
      x: "3",
      y: "14",
      width: "7",
      height: "7"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M14 14h3v3h-3zM20 14v7M14 20h3"
    }))), /*#__PURE__*/React.createElement("label", {
      className: "field",
      style: {
        flex: 1,
        minWidth: 180
      }
    }, /*#__PURE__*/React.createElement("span", null, "Authenticator secret"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      readOnly: true,
      value: "IMLA DRIS 7K2F 9QD4 H1PB"
    }))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "6-digit code"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      inputMode: "numeric",
      autoComplete: "one-time-code",
      placeholder: "000000",
      style: {
        maxWidth: 160
      }
    })), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        gap: 10
      }
    }, /*#__PURE__*/React.createElement(Button, {
      onClick: () => setEnabled(true)
    }, "Verify and enable"), /*#__PURE__*/React.createElement(Button, {
      variant: "ghost",
      onClick: () => setSetup(false)
    }, "Cancel"))) : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "Not enabled. A second factor keeps your seat at the council secure even if your password is lost."), /*#__PURE__*/React.createElement(Button, {
      onClick: () => setSetup(true)
    }, "Start setup"))));
  }

  /* ── Privacy ──────────────────────────────────────────────────────────── */
  function Privacy() {
    const {
      Button
    } = DS();
    return /*#__PURE__*/React.createElement("section", {
      className: "scribe-panel"
    }, /*#__PURE__*/React.createElement("span", {
      className: "scribe-panel-head"
    }, "Privacy"), /*#__PURE__*/React.createElement("div", {
      className: "stacked",
      style: {
        width: '100%'
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Profile visibility"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "Public \u2014 anyone can view"), /*#__PURE__*/React.createElement("option", null, "Members only \u2014 signed-in members"))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Allow direct messages from"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "Everyone"), /*#__PURE__*/React.createElement("option", {
      defaultValue: true
    }, "Members"), /*#__PURE__*/React.createElement("option", null, "No one"))), /*#__PURE__*/React.createElement("div", {
      className: "toggle-stack"
    }, /*#__PURE__*/React.createElement("label", {
      className: "gem-field"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox",
      className: "gem-check gem-leaf",
      defaultChecked: true
    }), /*#__PURE__*/React.createElement("span", null, "Show when I'm online", /*#__PURE__*/React.createElement("span", {
      className: "gem-sub"
    }, "A leaf marks your presence beside your name."))), /*#__PURE__*/React.createElement("label", {
      className: "gem-field"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox",
      className: "gem-check gem-gold"
    }), /*#__PURE__*/React.createElement("span", null, "Hide me from leaderboards", /*#__PURE__*/React.createElement("span", {
      className: "gem-sub"
    }, "You still earn regard; you just won't be ranked publicly."))), /*#__PURE__*/React.createElement("label", {
      className: "gem-field"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox",
      className: "gem-check gem-river",
      defaultChecked: true
    }), /*#__PURE__*/React.createElement("span", null, "Let others find me by email"))), /*#__PURE__*/React.createElement(Button, null, "Save privacy settings")));
  }

  /* ── Appearance ───────────────────────────────────────────────────────── */
  function Appearance() {
    const {
      ChoiceCard,
      Switch,
      Button
    } = DS();
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
      className: "scribe-panel"
    }, /*#__PURE__*/React.createElement("span", {
      className: "scribe-panel-head"
    }, "Appearance"), /*#__PURE__*/React.createElement("div", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Theme"), /*#__PURE__*/React.createElement("div", {
      className: "choice-cards",
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(3, 1fr)',
        gap: 10
      }
    }, /*#__PURE__*/React.createElement(ChoiceCard, {
      name: "theme",
      title: "Parchment",
      desc: "Warm paper \u2014 daylight",
      swatch: swatch('swatch-parchment'),
      defaultChecked: true
    }), /*#__PURE__*/React.createElement(ChoiceCard, {
      name: "theme",
      title: "Twilight",
      desc: "Evergreen night",
      swatch: swatch('swatch-twilight')
    }), /*#__PURE__*/React.createElement(ChoiceCard, {
      name: "theme",
      title: "System",
      desc: "Match your device",
      swatch: swatch('swatch-system')
    }))), /*#__PURE__*/React.createElement("div", {
      className: "field",
      style: {
        marginTop: 16
      }
    }, /*#__PURE__*/React.createElement("span", null, "Density"), /*#__PURE__*/React.createElement("div", {
      className: "choice-cards",
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(2, 1fr)',
        gap: 10
      }
    }, /*#__PURE__*/React.createElement(ChoiceCard, {
      name: "density",
      title: "Comfortable",
      desc: "A card per topic \u2014 for reading",
      defaultChecked: true
    }), /*#__PURE__*/React.createElement(ChoiceCard, {
      name: "density",
      title: "Compact",
      desc: "One line per topic \u2014 for triage"
    }))), /*#__PURE__*/React.createElement("label", {
      className: "field",
      style: {
        marginTop: 16,
        maxWidth: 220
      }
    }, /*#__PURE__*/React.createElement("span", null, "Font size"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "Small"), /*#__PURE__*/React.createElement("option", {
      defaultValue: true
    }, "Medium"), /*#__PURE__*/React.createElement("option", null, "Large"))), /*#__PURE__*/React.createElement("div", {
      style: {
        marginTop: 14
      }
    }, /*#__PURE__*/React.createElement(Switch, {
      label: "Reduce motion and animations"
    })), /*#__PURE__*/React.createElement("div", {
      style: {
        marginTop: 16
      }
    }, /*#__PURE__*/React.createElement(Button, null, "Save appearance"))), /*#__PURE__*/React.createElement("section", {
      className: "card",
      style: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: 12,
        alignItems: 'center',
        justifyContent: 'space-between'
      }
    }, /*#__PURE__*/React.createElement("p", {
      className: "muted",
      style: {
        margin: 0,
        maxWidth: '44ch'
      }
    }, "Download a copy of your appearance, reading, and composing preferences, or reset them to defaults."), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        gap: 10
      }
    }, /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      size: "sm"
    }, "Export preferences"), /*#__PURE__*/React.createElement(Button, {
      variant: "ghost",
      size: "sm"
    }, "Reset to defaults"))));
  }

  /* ── Reading ──────────────────────────────────────────────────────────── */
  function Reading() {
    const {
      Button
    } = DS();
    return /*#__PURE__*/React.createElement("section", {
      className: "scribe-panel"
    }, /*#__PURE__*/React.createElement("span", {
      className: "scribe-panel-head"
    }, "Reading"), /*#__PURE__*/React.createElement("div", {
      className: "stacked",
      style: {
        width: '100%'
      }
    }, /*#__PURE__*/React.createElement("div", {
      className: "field-grid",
      style: {
        width: '100%'
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Threads per page"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "20"), /*#__PURE__*/React.createElement("option", null, "25"), /*#__PURE__*/React.createElement("option", null, "50"), /*#__PURE__*/React.createElement("option", null, "100"))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Posts per page"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "10"), /*#__PURE__*/React.createElement("option", null, "20"), /*#__PURE__*/React.createElement("option", null, "40")))), /*#__PURE__*/React.createElement("label", {
      className: "field",
      style: {
        maxWidth: 260
      }
    }, /*#__PURE__*/React.createElement("span", null, "Default thread sort"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "Last post"), /*#__PURE__*/React.createElement("option", null, "Newest"), /*#__PURE__*/React.createElement("option", null, "Most replies"))), /*#__PURE__*/React.createElement("div", {
      className: "toggle-stack"
    }, /*#__PURE__*/React.createElement("label", {
      className: "gem-field"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox",
      className: "gem-check gem-leaf",
      defaultChecked: true
    }), /*#__PURE__*/React.createElement("span", null, "Show signatures")), /*#__PURE__*/React.createElement("label", {
      className: "gem-field"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox",
      className: "gem-check gem-leaf",
      defaultChecked: true
    }), /*#__PURE__*/React.createElement("span", null, "Show avatars")), /*#__PURE__*/React.createElement("label", {
      className: "gem-field"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox",
      className: "gem-check gem-leaf",
      defaultChecked: true
    }), /*#__PURE__*/React.createElement("span", null, "Show reactions"))), /*#__PURE__*/React.createElement(Button, null, "Save reading preferences")));
  }

  /* ── Composing ────────────────────────────────────────────────────────── */
  function Composing() {
    const {
      Switch,
      Button
    } = DS();
    return /*#__PURE__*/React.createElement("section", {
      className: "scribe-panel"
    }, /*#__PURE__*/React.createElement("span", {
      className: "scribe-panel-head"
    }, "Composing"), /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "These control how the shared Markdown composer behaves for new topics, replies, direct messages, and edits."), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 14
      }
    }, /*#__PURE__*/React.createElement(Switch, {
      label: "Press Enter to send (Shift+Enter for a new line)"
    }), /*#__PURE__*/React.createElement(Switch, {
      label: "Show a live preview while composing",
      defaultChecked: true
    }), /*#__PURE__*/React.createElement(Switch, {
      label: "Continue lists and quotes on the next line",
      defaultChecked: true
    })), /*#__PURE__*/React.createElement("div", {
      style: {
        marginTop: 16
      }
    }, /*#__PURE__*/React.createElement(Button, null, "Save composing preferences")));
  }

  /* ── Drafts ───────────────────────────────────────────────────────────── */
  function Drafts() {
    return /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Drafts"), /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "Unsent topics and replies are kept here for 30 days."), /*#__PURE__*/React.createElement("ul", {
      className: "row-list"
    }, S().drafts.map((d, i) => /*#__PURE__*/React.createElement("li", {
      className: "list-row",
      key: i
    }, /*#__PURE__*/React.createElement("div", {
      className: "list-row-main"
    }, /*#__PURE__*/React.createElement("span", {
      className: "list-row-title"
    }, d.title), /*#__PURE__*/React.createElement("span", {
      className: "list-row-sub"
    }, "#", d.board, " \xB7 saved ", d.when)), /*#__PURE__*/React.createElement("div", {
      className: "list-row-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Resume"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn danger",
      type: "button"
    }, "Discard"))))));
  }

  /* ── Notifications ────────────────────────────────────────────────────── */
  function Notifications() {
    const {
      Button
    } = DS();
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
      className: "scribe-panel"
    }, /*#__PURE__*/React.createElement("span", {
      className: "scribe-panel-head"
    }, "Daily digest"), /*#__PURE__*/React.createElement("div", {
      className: "field-grid",
      style: {
        width: '100%'
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Timezone"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "UTC"), /*#__PURE__*/React.createElement("option", {
      defaultValue: true
    }, "Europe / Rivendell"), /*#__PURE__*/React.createElement("option", null, "America / New York"))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Digest hour (local)"), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "Off"), /*#__PURE__*/React.createElement("option", {
      defaultValue: true
    }, "08:00"), /*#__PURE__*/React.createElement("option", null, "18:00")))), /*#__PURE__*/React.createElement("div", {
      style: {
        marginTop: 14
      }
    }, /*#__PURE__*/React.createElement(Button, null, "Save digest settings"))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Your subscriptions"), /*#__PURE__*/React.createElement("ul", {
      className: "row-list"
    }, S().subscriptions.map((s, i) => /*#__PURE__*/React.createElement("li", {
      className: "list-row",
      key: i
    }, /*#__PURE__*/React.createElement("div", {
      className: "list-row-main"
    }, /*#__PURE__*/React.createElement("a", {
      className: "list-row-title",
      href: "#",
      onClick: e => e.preventDefault()
    }, s.label), /*#__PURE__*/React.createElement("span", {
      className: "list-row-sub"
    }, s.freq, s.email ? ' · email' : '')), /*#__PURE__*/React.createElement("div", {
      className: "list-row-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: "linkbtn danger",
      type: "button"
    }, "Unsubscribe")))))));
  }

  /* ── Connections ──────────────────────────────────────────────────────── */
  function Connections() {
    return /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Connected accounts"), /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "Link Google, GitHub, or Apple to sign in faster. Email/password always stays available."), /*#__PURE__*/React.createElement("ul", {
      className: "row-list"
    }, S().providers.map(p => /*#__PURE__*/React.createElement("li", {
      className: "list-row",
      key: p.name
    }, /*#__PURE__*/React.createElement("span", {
      className: "provider-mark",
      "aria-hidden": "true"
    }, p.name[0]), /*#__PURE__*/React.createElement("div", {
      className: "list-row-main"
    }, /*#__PURE__*/React.createElement("span", {
      className: "list-row-title"
    }, p.name), p.linked ? /*#__PURE__*/React.createElement("span", {
      className: "list-row-sub"
    }, p.email) : null), /*#__PURE__*/React.createElement("div", {
      className: "list-row-actions"
    }, p.linked ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("span", {
      className: "pill pill-online"
    }, "Connected"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn danger",
      type: "button"
    }, "Disconnect")) : p.configured ? /*#__PURE__*/React.createElement("a", {
      className: "btn btn-secondary btn-small",
      href: "#",
      onClick: e => e.preventDefault()
    }, "Connect") : /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "Not available"))))));
  }

  /* ── Sessions ─────────────────────────────────────────────────────────── */
  function Sessions() {
    const {
      Button
    } = DS();
    return /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("div", {
      className: "list-head"
    }, /*#__PURE__*/React.createElement("h2", null, "Active sessions & devices"), /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      size: "sm"
    }, "Log out of all other devices")), /*#__PURE__*/React.createElement("ul", {
      className: "row-list"
    }, S().sessions.map(s => /*#__PURE__*/React.createElement("li", {
      className: "list-row",
      key: s.id
    }, /*#__PURE__*/React.createElement("div", {
      className: "list-row-main"
    }, /*#__PURE__*/React.createElement("span", {
      className: "list-row-title",
      style: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: 8
      }
    }, s.ua, s.current ? /*#__PURE__*/React.createElement("span", {
      className: "pill pill-online"
    }, "This device") : null), /*#__PURE__*/React.createElement("span", {
      className: "list-row-sub"
    }, "IP ", s.ip, " \xB7 last active ", s.last)), !s.current ? /*#__PURE__*/React.createElement("div", {
      className: "list-row-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: "linkbtn danger",
      type: "button"
    }, "Sign out")) : null))));
  }

  /* ── Blocks ───────────────────────────────────────────────────────────── */
  function Blocks() {
    const {
      Monogram
    } = DS();
    return /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Blocked users"), /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "Blocked members can't message or @mention you, and their notifications to you are suppressed."), /*#__PURE__*/React.createElement("ul", {
      className: "row-list"
    }, S().blocks.map(b => /*#__PURE__*/React.createElement("li", {
      className: "list-row",
      key: b.username
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: b.name,
      username: b.username,
      size: "sm"
    }), /*#__PURE__*/React.createElement("div", {
      className: "list-row-main"
    }, /*#__PURE__*/React.createElement("a", {
      className: "list-row-title",
      href: "#",
      onClick: e => e.preventDefault()
    }, b.name), /*#__PURE__*/React.createElement("span", {
      className: "list-row-sub"
    }, "@", b.username)), /*#__PURE__*/React.createElement("div", {
      className: "list-row-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Unblock"))))));
  }

  /* ── Boards ───────────────────────────────────────────────────────────── */
  function Boards() {
    const [boards, setBoards] = React.useState(() => JSON.parse(JSON.stringify(S().boards)));
    const toggle = (ci, bi, key) => setBoards(prev => prev.map((c, x) => x !== ci ? c : {
      ...c,
      items: c.items.map((b, y) => y !== bi ? b : {
        ...b,
        [key]: !b[key]
      })
    }));
    return /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Organize your boards"), /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "Favorite boards rise to the top; muted boards are hidden from your sidebar and unread counts."), boards.map((c, ci) => /*#__PURE__*/React.createElement("div", {
      key: c.cat
    }, /*#__PURE__*/React.createElement("h3", {
      className: "board-cat"
    }, c.cat), /*#__PURE__*/React.createElement("ul", {
      className: "row-list",
      style: {
        marginTop: 0
      }
    }, c.items.map((b, bi) => /*#__PURE__*/React.createElement("li", {
      className: "list-row",
      key: b.name
    }, /*#__PURE__*/React.createElement("div", {
      className: "list-row-main"
    }, /*#__PURE__*/React.createElement("span", {
      className: "list-row-title"
    }, /*#__PURE__*/React.createElement("span", {
      className: "hash"
    }, "#"), b.name)), /*#__PURE__*/React.createElement("div", {
      className: "list-row-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: 'toggle-link' + (b.fav ? ' on' : ''),
      type: "button",
      onClick: () => toggle(ci, bi, 'fav')
    }, b.fav ? '★ Favorited' : '☆ Favorite'), /*#__PURE__*/React.createElement("button", {
      className: 'toggle-link' + (b.muted ? ' on' : ''),
      type: "button",
      onClick: () => toggle(ci, bi, 'muted')
    }, b.muted ? 'Muted' : 'Mute'))))))));
  }

  /* ── Account (lifecycle) ──────────────────────────────────────────────── */
  function Lifecycle() {
    const {
      Input,
      Button
    } = DS();
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
      className: "scribe-panel"
    }, /*#__PURE__*/React.createElement("span", {
      className: "scribe-panel-head"
    }, "Export account data"), /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "Download a JSON archive of your profile, preferences, subscriptions, notifications, posts, direct messages, and related audit rows."), /*#__PURE__*/React.createElement(Button, {
      variant: "secondary"
    }, "Download account export")), /*#__PURE__*/React.createElement("section", {
      className: "scribe-panel"
    }, /*#__PURE__*/React.createElement("span", {
      className: "scribe-panel-head"
    }, "Deactivate account"), /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "Deactivation is reversible. Your seat stays sign-in capable, but counsel and posts are blocked until you reactivate."), /*#__PURE__*/React.createElement("div", {
      className: "stacked",
      style: {
        width: '100%'
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field",
      style: {
        maxWidth: 320
      }
    }, /*#__PURE__*/React.createElement("span", null, "Current password"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      variant: "secondary"
    }, "Deactivate account"))), /*#__PURE__*/React.createElement("section", {
      className: "card danger-zone"
    }, /*#__PURE__*/React.createElement("h2", null, "Delete account"), /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "Deletion starts a 30-day grace period. During it your account is write-blocked and you can cancel. Public counsel is preserved under a deleted-user identity while your PII is purged."), /*#__PURE__*/React.createElement("div", {
      className: "stacked",
      style: {
        width: '100%'
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field",
      style: {
        maxWidth: 320
      }
    }, /*#__PURE__*/React.createElement("span", null, "Current password"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      variant: "danger"
    }, "Request account deletion"))));
  }
  window.RBSettingsSections = {
    account: {
      label: 'Profile',
      icon: 'user',
      group: 'Account',
      render: Profile
    },
    security: {
      label: 'Security',
      icon: 'shield',
      group: 'Account',
      render: Security
    },
    privacy: {
      label: 'Privacy',
      icon: 'eye',
      group: 'Account',
      render: Privacy
    },
    appearance: {
      label: 'Appearance',
      icon: 'sun',
      group: 'Reading & writing',
      render: Appearance
    },
    preferences: {
      label: 'Reading',
      icon: 'book',
      group: 'Reading & writing',
      render: Reading
    },
    composing: {
      label: 'Composing',
      icon: 'pen',
      group: 'Reading & writing',
      render: Composing
    },
    drafts: {
      label: 'Drafts',
      icon: 'file',
      group: 'Reading & writing',
      render: Drafts
    },
    notifications: {
      label: 'Notifications',
      icon: 'bell',
      group: 'Council',
      render: Notifications
    },
    connections: {
      label: 'Connections',
      icon: 'link',
      group: 'Council',
      render: Connections
    },
    sessions: {
      label: 'Sessions',
      icon: 'monitor',
      group: 'Council',
      render: Sessions
    },
    blocks: {
      label: 'Blocks',
      icon: 'ban',
      group: 'Council',
      render: Blocks
    },
    boards: {
      label: 'Boards',
      icon: 'hash',
      group: 'Council',
      render: Boards
    },
    lifecycle: {
      label: 'Account',
      icon: 'archive',
      group: 'Council',
      render: Lifecycle
    }
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/settings/SettingsSections.jsx", error: String((e && e.message) || e) }); }

// ui_kits/settings/data.js
try { (() => {
/* Settings kit — seed state (the signed-in member + their account data). */
(function () {
  window.RBSettings = {
    user: {
      name: 'Erestor',
      username: 'erestor',
      email: 'erestor@imladris.council',
      tier: 'Legend',
      title: 'Loremaster of Imladris',
      rep: 3985
    },
    sessions: [{
      id: 's1',
      ua: 'Firefox 128 · macOS',
      ip: '10.0.4.18',
      last: 'just now',
      current: true
    }, {
      id: 's2',
      ua: 'Safari · iPhone',
      ip: '10.0.4.91',
      last: '3 hours ago',
      current: false
    }, {
      id: 's3',
      ua: 'Chrome 126 · Windows',
      ip: '198.51.100.7',
      last: 'yesterday',
      current: false
    }],
    providers: [{
      name: 'Google',
      linked: true,
      email: 'erestor@imladris.council'
    }, {
      name: 'GitHub',
      linked: false,
      configured: true
    }, {
      name: 'Apple',
      linked: false,
      configured: false
    }],
    blocks: [{
      name: 'Saruman',
      username: 'saruman'
    }],
    boards: [{
      cat: 'The Commons',
      items: [{
        name: 'announcements',
        fav: true,
        muted: false
      }, {
        name: 'introductions',
        fav: false,
        muted: false
      }, {
        name: 'the-valley',
        fav: false,
        muted: true
      }]
    }, {
      cat: 'Vilya · Expose',
      items: [{
        name: 'interpretability',
        fav: true,
        muted: false
      }, {
        name: 'evaluations',
        fav: true,
        muted: false
      }, {
        name: 'audit-trails',
        fav: false,
        muted: false
      }]
    }],
    subscriptions: [{
      label: 'Evaluations as ritual, not gate',
      kind: 'thread',
      freq: 'Watching',
      email: true
    }, {
      label: '#audit-trails',
      kind: 'board',
      freq: 'Tracking',
      email: false
    }],
    drafts: [{
      title: 'On the precedence of edits',
      board: 'audit-trails',
      when: '2 days ago'
    }, {
      title: 'Untitled topic',
      board: 'the-valley',
      when: '1 week ago'
    }],
    recoveryCodes: ['imla-3kf9-2a', 'imla-77qd-h1', 'imla-pb42-9c', 'imla-x8mn-4t', 'imla-5rty-0v', 'imla-9wlk-6e']
  };
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/settings/data.js", error: String((e && e.message) || e) }); }

__ds_ns.CommendStar = __ds_scope.CommendStar;

__ds_ns.EightPointStar = __ds_scope.EightPointStar;

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.Chip = __ds_scope.Chip;

__ds_ns.Pill = __ds_scope.Pill;

__ds_ns.Tag = __ds_scope.Tag;

__ds_ns.Callout = __ds_scope.Callout;

__ds_ns.DocCover = __ds_scope.DocCover;

__ds_ns.Figure = __ds_scope.Figure;

__ds_ns.SectionHeader = __ds_scope.SectionHeader;

__ds_ns.SpecTable = __ds_scope.SpecTable;

__ds_ns.ChoiceCard = __ds_scope.ChoiceCard;

__ds_ns.Input = __ds_scope.Input;

__ds_ns.Switch = __ds_scope.Switch;

__ds_ns.Textarea = __ds_scope.Textarea;

__ds_ns.Composer = __ds_scope.Composer;

__ds_ns.JoinBar = __ds_scope.JoinBar;

__ds_ns.ParticipantStack = __ds_scope.ParticipantStack;

__ds_ns.Post = __ds_scope.Post;

__ds_ns.Tabs = __ds_scope.Tabs;

__ds_ns.ThreadRow = __ds_scope.ThreadRow;

__ds_ns.Monogram = __ds_scope.Monogram;

__ds_ns.Reaction = __ds_scope.Reaction;

__ds_ns.StarButton = __ds_scope.StarButton;

})();
