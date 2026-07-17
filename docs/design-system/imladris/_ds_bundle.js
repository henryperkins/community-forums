/* @ds-bundle: {"format":4,"namespace":"ImladrisDesignSystem_c3e027","components":[{"name":"CommendStar","sourcePath":"components/brand/CommendStar.jsx"},{"name":"EightPointStar","sourcePath":"components/brand/EightPointStar.jsx"},{"name":"Badge","sourcePath":"components/core/Badge.jsx"},{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"Card","sourcePath":"components/core/Card.jsx"},{"name":"Chip","sourcePath":"components/core/Chip.jsx"},{"name":"Pill","sourcePath":"components/core/Pill.jsx"},{"name":"Tag","sourcePath":"components/core/Tag.jsx"},{"name":"Callout","sourcePath":"components/doc/Callout.jsx"},{"name":"DocCover","sourcePath":"components/doc/DocCover.jsx"},{"name":"Figure","sourcePath":"components/doc/Figure.jsx"},{"name":"SectionHeader","sourcePath":"components/doc/SectionHeader.jsx"},{"name":"SpecTable","sourcePath":"components/doc/SpecTable.jsx"},{"name":"ChoiceCard","sourcePath":"components/forms/ChoiceCard.jsx"},{"name":"Input","sourcePath":"components/forms/Input.jsx"},{"name":"Switch","sourcePath":"components/forms/Switch.jsx"},{"name":"Textarea","sourcePath":"components/forms/Textarea.jsx"},{"name":"Composer","sourcePath":"components/forum/Composer.jsx"},{"name":"JoinBar","sourcePath":"components/forum/JoinBar.jsx"},{"name":"ParticipantStack","sourcePath":"components/forum/ParticipantStack.jsx"},{"name":"Post","sourcePath":"components/forum/Post.jsx"},{"name":"Tabs","sourcePath":"components/forum/Tabs.jsx"},{"name":"ThreadRow","sourcePath":"components/forum/ThreadRow.jsx"},{"name":"Monogram","sourcePath":"components/identity/Monogram.jsx"},{"name":"Reaction","sourcePath":"components/identity/Reaction.jsx"},{"name":"StarButton","sourcePath":"components/identity/StarButton.jsx"}],"sourceHashes":{"components/brand/CommendStar.jsx":"2fdec638ecb6","components/brand/EightPointStar.jsx":"78e9e4f44d92","components/core/Badge.jsx":"dceb5116fea3","components/core/Button.jsx":"6d2696ea6302","components/core/Card.jsx":"36db3a574747","components/core/Chip.jsx":"506fbf1d2fe5","components/core/Pill.jsx":"c1f2c9ae1c51","components/core/Tag.jsx":"cf0c0c19f406","components/doc/Callout.jsx":"d81172950bf7","components/doc/DocCover.jsx":"18a6819b7965","components/doc/Figure.jsx":"0b0e23dd7055","components/doc/SectionHeader.jsx":"bace9f8cd863","components/doc/SpecTable.jsx":"ee0a3c3d869b","components/forms/ChoiceCard.jsx":"996f6b5363ed","components/forms/Input.jsx":"f678e1e24152","components/forms/Switch.jsx":"124d55994abc","components/forms/Textarea.jsx":"8a89777423e7","components/forum/Composer.jsx":"bc31ffb3e4fb","components/forum/JoinBar.jsx":"fe58e0c52b0c","components/forum/ParticipantStack.jsx":"206956583bdc","components/forum/Post.jsx":"8c5b7492401e","components/forum/Tabs.jsx":"a082051bec4a","components/forum/ThreadRow.jsx":"9e69f32282fa","components/identity/Monogram.jsx":"f31129a7e7ae","components/identity/Reaction.jsx":"456807636487","components/identity/StarButton.jsx":"3b65ec629ed5","feature-ui/organize/organize.jsx":"5afce4767810","feature-ui/shared/chrome.jsx":"36ebda32d49a","ui_kits/admin/AdminApp.jsx":"38668c355b54","ui_kits/admin/AdminPackages.jsx":"a7b229de2a6d","ui_kits/admin/AdminParity.jsx":"b1a8a1fff814","ui_kits/admin/AdminSections.jsx":"ca73c216e4ac","ui_kits/admin/data.js":"e51c0287f01d","ui_kits/admin/parity-data.js":"3e624e7a13a5","ui_kits/auth/AuthApp.jsx":"4ef9912af995","ui_kits/dm/ConvoList.jsx":"eae136b0652c","ui_kits/dm/DMApp.jsx":"6007249dc356","ui_kits/dm/DMTopbar.jsx":"cc73ecef8318","ui_kits/dm/InfoRail.jsx":"d39b137272d5","ui_kits/dm/NavRail.jsx":"a46eaccc6b17","ui_kits/dm/Overlays.jsx":"c009805a98f2","ui_kits/dm/Thread.jsx":"b5d1aa9b95e7","ui_kits/dm/data.js":"fbd6f737b3df","ui_kits/mod/ModApp.jsx":"0989542838fc","ui_kits/mod/ModSections.jsx":"6f5ed1730587","ui_kits/mod/data.js":"e4dd249c62cf","ui_kits/reading/ReadingApp.jsx":"72d25dbaf619","ui_kits/reading/ReadingChrome.jsx":"5f89513e1b17","ui_kits/reading/ReadingExtras.jsx":"9d7ab8a18cf4","ui_kits/reading/ReadingSurfaces.jsx":"1dfe54c4faf5","ui_kits/reading/reading-data.js":"9e93b326c2c3","ui_kits/retroboards/App.jsx":"90a6ffa3d85c","ui_kits/retroboards/Conversation.jsx":"ebf0d840fb16","ui_kits/retroboards/Inbox.jsx":"8aece8676535","ui_kits/retroboards/Leaderboard.jsx":"019dde493247","ui_kits/retroboards/Profile.jsx":"39d2b38848d3","ui_kits/retroboards/Rail.jsx":"824ffa2bf89b","ui_kits/retroboards/Topbar.jsx":"70d697989cc1","ui_kits/retroboards/data.js":"3d5e91a4fabd","ui_kits/settings/Chrome.jsx":"1ac72a03b412","ui_kits/settings/SettingsApp.jsx":"916eb090d2f3","ui_kits/settings/SettingsSections.jsx":"eaac085bdeb3","ui_kits/settings/data.js":"8af210b00f80","ui_kits/system/SystemApp.jsx":"e316b1690a4d"},"inlinedExternals":[],"unexposedExports":[]} */

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

/* The production toolbar contract (composer.js at the inspected commit):
   order, labels, shortcuts, group breaks, narrow-screen essentials, and the
   exact Lucide-register icon paths. Do not reorder or redraw. */
const TOOLBAR_ORDER = ['bold', 'italic', 'strike', 'code', 'quote', 'h2', 'list', 'orderedList', 'codeblock', 'spoiler', 'link'];
const TOOLBAR_ACTIONS = {
  bold: {
    label: 'Bold',
    shortcut: 'B'
  },
  italic: {
    label: 'Italic',
    shortcut: 'I'
  },
  strike: {
    label: 'Strike'
  },
  code: {
    label: 'Inline code',
    shortcut: 'E'
  },
  quote: {
    label: 'Quote'
  },
  h2: {
    label: 'Heading'
  },
  list: {
    label: 'Bullet list'
  },
  orderedList: {
    label: 'Numbered list'
  },
  codeblock: {
    label: 'Code block'
  },
  spoiler: {
    label: 'Spoiler'
  },
  link: {
    label: 'Link',
    shortcut: 'K'
  }
};
const GROUP_BREAKS = {
  code: true,
  spoiler: true
};
const ESSENTIAL = {
  bold: true,
  italic: true,
  list: true,
  link: true
};
const OVERFLOW_ORDER = ['strike', 'code', 'quote', 'h2', 'orderedList', 'codeblock', 'spoiler'];
const ICON_PATHS = {
  bold: ['M8 5h5a3 3 0 0 1 0 6H8z', 'M8 11h6a4 4 0 0 1 0 8H8z'],
  italic: ['M10 5h7', 'M7 19h7', 'M14 5 10 19'],
  strike: ['M6 7h10', 'M5 12h14', 'M8 17h8'],
  code: ['m9 8-4 4 4 4', 'm15 8 4 4-4 4'],
  quote: ['M6 7h5v5H7v5', 'M14 7h5v5h-4v5'],
  h2: ['M5 6v12', 'M13 6v12', 'M5 12h8', 'M16 10c0-2 4-2 4 0 0 2-4 3-4 6h5'],
  list: ['M9 7h10', 'M9 12h10', 'M9 17h10', 'M5 7h.01', 'M5 12h.01', 'M5 17h.01'],
  orderedList: ['M5 6h1v3', 'M5 13c2-1 2 2 0 3h2', 'M10 7h9', 'M10 12h9', 'M10 17h9'],
  codeblock: ['M5 6h14v12H5z', 'm9 10-2 2 2 2', 'm6-4 2 2-2 2'],
  spoiler: ['M3 12s3-5 9-5 9 5 9 5-3 5-9 5-9-5-9-5', 'M12 10a2 2 0 1 0 0 4 2 2 0 0 0 0-4'],
  link: ['M10 14 8.5 15.5a3 3 0 0 1-4-4L7 9a3 3 0 0 1 4 0', 'm14 10 1.5-1.5a3 3 0 0 1 4 4L17 15a3 3 0 0 1-4 0', 'm9 15 6-6']
};
function ActionIcon({
  k
}) {
  return /*#__PURE__*/React.createElement("svg", {
    viewBox: "0 0 24 24",
    "aria-hidden": "true",
    focusable: "false"
  }, (ICON_PATHS[k] || []).map((d, i) => /*#__PURE__*/React.createElement("path", {
    key: i,
    d: d
  })));
}

/**
 * Composer — the shared composer shell (COMPOSER.md v0.8; composer_shell.php).
 * One contained box serving all four mounts — `context` reply / new_thread /
 * dm / edit — with the identical feature surface: the engraved icon formatting
 * row (toggled by Aa, persisted in the product), attach ＋ / emoji 😊, the
 * identity line ("as **Name**"), the Anonymous chip where a board allows it,
 * the Preview toggle, and the circular quill send. Below the box: draft state,
 * anonymous disclosure, and the near-limit counter. Wrapper differences (a
 * Title + board picker for new_thread, recipients for dm) mount via `header`.
 *
 * This is the presentational design reference: in production the textarea is
 * canonical Markdown (WYSIWYG mounts over it when `rich_composer` +
 * `wysiwyg_composer` are enabled, and everything works with no JS), every form
 * carries a CSRF token + a fresh idempotency key, and send performs a full
 * navigation (optimistic send remains deferred — ADR 0020).
 */
function Composer({
  context = 'reply',
  placeholder = 'Add your counsel…',
  maxLength = 20000,
  value,
  defaultValue,
  onChange,
  submitLabel = 'Reply',
  identity,
  // display name for the "as …" line; omit to hide
  identitySeed,
  // monogram hash seed (defaults to identity)
  showAvatar = true,
  // honors the user's show_avatars preference
  allowAnonymous = false,
  anonymousChecked = false,
  anonymousDisclosure = 'Your name is hidden from other members; moderators can still see it.',
  toolbarOpen = true,
  // the Aa row state (production default: open)
  activeFormats,
  // e.g. ['bold'] — aria-pressed specimens
  error,
  // field error shown inside the box, above the input
  uploads,
  // [{ name, thumb, status, progress, failed, alt }]
  draftSaved = false,
  count,
  // "18,204 / 20,000" — shown near the limit
  countOver = false,
  previewOpen = false,
  previewContent,
  // rendered server-preview HTML (same pipeline as posts)
  submitting = false,
  disabled = false,
  disabledNotice,
  // e.g. "This topic was locked while you were writing. Your draft is kept."
  header,
  // wrapper slot above the box (Title field, recipients…)
  actionsStart,
  actionsEnd,
  className = '',
  onSubmit,
  ...rest
}) {
  const [fmtOpen, setFmtOpen] = React.useState(!!toolbarOpen);
  const [moreOpen, setMoreOpen] = React.useState(false);
  const [anon, setAnon] = React.useState(!!anonymousChecked);
  const [showPreview, setShowPreview] = React.useState(!!previewOpen);
  const active = new Set(activeFormats || []);
  const cls = ['composer', 'composer-shell', submitting ? 'is-submitting' : '', className].filter(Boolean).join(' ');
  const seed = identitySeed || identity;
  return /*#__PURE__*/React.createElement("form", _extends({
    className: cls,
    "data-composer-context": context,
    "aria-busy": submitting || undefined,
    onSubmit: onSubmit
  }, rest), header, /*#__PURE__*/React.createElement("div", {
    className: "composer-box"
  }, /*#__PURE__*/React.createElement("div", {
    className: "composer-format-slot"
  }, /*#__PURE__*/React.createElement("div", {
    className: "composer-toolbar",
    role: "toolbar",
    "aria-label": "Formatting",
    hidden: !fmtOpen
  }, TOOLBAR_ORDER.map(k => {
    const a = TOOLBAR_ACTIONS[k];
    const sc = a.shortcut ? ' (Ctrl+' + a.shortcut + ')' : '';
    return /*#__PURE__*/React.createElement(React.Fragment, {
      key: k
    }, /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: 'composer-toolbar-action' + (ESSENTIAL[k] ? ' is-essential' : ''),
      "aria-label": a.label + sc,
      "data-tip": a.label + (a.shortcut ? ' · Ctrl+' + a.shortcut : ''),
      "aria-keyshortcuts": a.shortcut ? 'Control+' + a.shortcut + ' Meta+' + a.shortcut : undefined,
      "aria-pressed": active.has(k),
      disabled: disabled
    }, /*#__PURE__*/React.createElement(ActionIcon, {
      k: k
    })), GROUP_BREAKS[k] ? /*#__PURE__*/React.createElement("span", {
      className: "composer-toolbar-sep",
      "aria-hidden": "true"
    }) : null);
  }), /*#__PURE__*/React.createElement("span", {
    className: "composer-more-wrap"
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "composer-more-toggle",
    "aria-label": "More formatting",
    "aria-expanded": moreOpen,
    onClick: () => setMoreOpen(!moreOpen),
    disabled: disabled
  }, "\uFF0B"))), moreOpen ? /*#__PURE__*/React.createElement("div", {
    className: "composer-format-overflow",
    role: "group",
    "aria-label": "More formatting"
  }, OVERFLOW_ORDER.map(k => /*#__PURE__*/React.createElement("button", {
    type: "button",
    key: k,
    className: "composer-overflow-action",
    "aria-pressed": active.has(k),
    onClick: () => setMoreOpen(false)
  }, TOOLBAR_ACTIONS[k].label))) : null), error ? /*#__PURE__*/React.createElement("p", {
    className: "field-error"
  }, error) : null, /*#__PURE__*/React.createElement("textarea", {
    className: "composer-input",
    rows: 4,
    maxLength: maxLength,
    placeholder: placeholder,
    value: value,
    defaultValue: defaultValue,
    onChange: onChange,
    disabled: disabled,
    required: true
  }), /*#__PURE__*/React.createElement("div", {
    className: "composer-upload-tray",
    "aria-live": "polite"
  }, (uploads || []).map((u, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    className: 'composer-upload-card' + (u.failed ? ' is-failed' : '')
  }, u.thumb ? /*#__PURE__*/React.createElement("img", {
    className: "composer-upload-thumb",
    src: u.thumb,
    alt: ""
  }) : /*#__PURE__*/React.createElement("span", {
    className: "composer-upload-thumb",
    "aria-hidden": "true"
  }), /*#__PURE__*/React.createElement("div", {
    className: "composer-upload-meta"
  }, /*#__PURE__*/React.createElement("span", {
    className: "composer-upload-name"
  }, u.name), /*#__PURE__*/React.createElement("span", {
    className: "composer-upload-status"
  }, u.status)), u.progress != null ? /*#__PURE__*/React.createElement("progress", {
    max: 100,
    value: u.progress
  }) : null, u.alt != null ? /*#__PURE__*/React.createElement("input", {
    className: "input",
    defaultValue: u.alt,
    placeholder: "Describe this image (alt text)",
    "aria-label": "Alt text"
  }) : null, /*#__PURE__*/React.createElement("div", {
    className: "composer-upload-actions"
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "btn btn-secondary btn-small"
  }, "Up"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "btn btn-secondary btn-small"
  }, "Down"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "btn btn-secondary btn-small"
  }, "Remove"))))), /*#__PURE__*/React.createElement("div", {
    className: "composer-actions-bar"
  }, /*#__PURE__*/React.createElement("div", {
    className: "composer-actions-start"
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "composer-format-toggle",
    "aria-label": "Formatting",
    "aria-expanded": fmtOpen,
    onClick: () => setFmtOpen(!fmtOpen),
    disabled: disabled
  }, "Aa"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "composer-attach-toggle",
    "aria-label": "Attach images",
    title: "Attach images",
    disabled: disabled
  }, "\uFF0B"), /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "composer-emoji-toggle",
    "aria-label": "Emoji",
    "aria-haspopup": "dialog",
    disabled: disabled
  }, "\uD83D\uDE0A"), actionsStart, identity ? /*#__PURE__*/React.createElement("span", {
    className: "composer-identity",
    dir: "auto"
  }, showAvatar ? /*#__PURE__*/React.createElement("span", {
    className: 'monogram ' + monoClass(seed),
    "aria-hidden": "true"
  }, initials(identity)) : null, /*#__PURE__*/React.createElement("span", {
    className: "composer-identity-copy"
  }, "as ", /*#__PURE__*/React.createElement("strong", null, identity))) : null, allowAnonymous ? /*#__PURE__*/React.createElement("span", {
    className: "composer-anonymous-chip"
  }, /*#__PURE__*/React.createElement("input", {
    type: "checkbox",
    id: "composer-anon",
    checked: anon,
    onChange: e => setAnon(e.target.checked),
    disabled: disabled
  }), /*#__PURE__*/React.createElement("label", {
    htmlFor: "composer-anon"
  }, "Anonymous")) : null), /*#__PURE__*/React.createElement("div", {
    className: "composer-actions-end"
  }, actionsEnd, /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "composer-preview-toggle",
    "aria-label": "Preview",
    "aria-expanded": showPreview,
    onClick: () => setShowPreview(!showPreview),
    disabled: disabled
  }, "Preview"), /*#__PURE__*/React.createElement("button", {
    type: "submit",
    className: "btn composer-send",
    "aria-label": submitLabel,
    disabled: disabled || submitting
  }, /*#__PURE__*/React.createElement("span", {
    "aria-hidden": "true"
  }, "\u2712"))))), /*#__PURE__*/React.createElement("div", {
    className: "composer-meta-row"
  }, /*#__PURE__*/React.createElement("span", {
    className: "composer-meta-draft"
  }, draftSaved ? /*#__PURE__*/React.createElement(React.Fragment, null, "Draft saved \xB7 ", /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "linkbtn composer-discard",
    "aria-label": "Discard draft"
  }, "Discard")) : null), allowAnonymous ? /*#__PURE__*/React.createElement("span", {
    className: "composer-anonymous-disclosure"
  }, anonymousDisclosure) : /*#__PURE__*/React.createElement("span", null), count != null ? /*#__PURE__*/React.createElement("span", {
    className: 'composer-count' + (countOver ? ' over' : '')
  }, count) : null), showPreview ? /*#__PURE__*/React.createElement("div", {
    className: "composer-preview formatted-content",
    "aria-live": "polite"
  }, previewContent) : null, disabledNotice ? /*#__PURE__*/React.createElement("p", {
    className: "composer-meta-row",
    role: "status"
  }, disabledNotice) : null, /*#__PURE__*/React.createElement("span", {
    className: "sr-only",
    role: "status",
    "aria-live": "polite"
  }, submitting ? 'Sending…' : ''));
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

// ui_kits/admin/AdminApp.jsx
try { (() => {
/* Admin Console kit — app shell. Topbar + admin-head + horizontal subnav +
   section routing. Sections come from RBAdminSections (core) + RBAdminParity
   (the eight P5/runtime consoles). Users drills into a user record; the
   parity sections manage their own drill-ins. The reserved "Extensions" entry
   renders in its production disabled state (server_extensions is a Gate-B
   reserved-dark flag; the nav shows it disabled with a note). */
(function () {
  function App() {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      EightPointStar,
      Monogram,
      Pill
    } = DS;
    const SECT = Object.assign({}, window.RBAdminSections, window.RBAdminParity);
    const UserRecord = window.RBAdminUserRecord;
    const a = window.RBAdmin.admin;

    /* Production nav order (templates/admin/_nav.php). */
    const ORDER = ['dashboard', 'features', 'threadIntelligence', 'structure', 'users', 'branding', 'tags', 'badgeRules', 'email', 'announcements', 'apiTokens', 'webhooks', 'packages', 'registries', 'themes', 'roles', 'providers', 'invitations'];
    const DISABLED = [{
      key: 'extensions',
      label: 'Extensions',
      note: 'Disabled until the feature flag is enabled'
    }];
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
    }, ORDER.map(k => /*#__PURE__*/React.createElement("button", {
      key: k,
      className: k === active ? 'active' : '',
      "aria-current": k === active ? 'page' : undefined,
      onClick: () => {
        setActive(k);
        setUserId(null);
      }
    }, SECT[k].label)), DISABLED.map(d => /*#__PURE__*/React.createElement("span", {
      key: d.key,
      className: "subnav-item is-disabled",
      "aria-disabled": "true",
      title: d.note
    }, /*#__PURE__*/React.createElement("span", {
      className: "subnav-item-label"
    }, d.label), /*#__PURE__*/React.createElement("span", {
      className: "subnav-item-note"
    }, d.note)))), /*#__PURE__*/React.createElement("div", {
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

// ui_kits/admin/AdminPackages.jsx
try { (() => {
/* Admin Console kit — production-parity Packages pane (admin/packages.php,
   package_detail.php, package_plan.php, package_consent.php,
   package_security.php, package_publisher.php). Self-contained drill-in via
   local state. Registers onto window.RBAdminParity. */
(function () {
  const A = () => window.RBAdmin;
  const DS = () => window.ImladrisDesignSystem_c3e027;
  function Packages() {
    const {
      Input,
      Textarea,
      Button
    } = DS();
    const P = A().packages;
    const [view, setView] = React.useState('catalogue');
    const [pkgId, setPkgId] = React.useState(null);
    const [pubId, setPubId] = React.useState(null);
    const go = (v, id) => {
      if (id != null) setPkgId(id);
      setView(v);
    };
    const det = pkgId != null ? P.detail[pkgId] : null;

    /* ── Catalogue ─────────────────────────────────────────────────────── */
    if (view === 'catalogue') {
      return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
        className: "kit-note"
      }, /*#__PURE__*/React.createElement("span", null, "Security & publishers:"), /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        type: "button",
        onClick: () => setView('security')
      }, "Package security response \u2192")), /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, "Staff browse of signed registry metadata. A signature proves byte provenance under a pinned key; install and enable still require review, consent, and local policy checks."), P.registrySnapshots.filter(r => !r.fresh).map(r => /*#__PURE__*/React.createElement("p", {
        className: "field-error",
        key: r.sourceId
      }, "Stale snapshot: ", /*#__PURE__*/React.createElement("strong", null, r.sourceId), " has no verified snapshot inside its freshness window (", r.expires ? 'expired ' + r.expires + ' UTC' : 'never fetched', "). Cached metadata below remains viewable. Run ", /*#__PURE__*/React.createElement("code", null, "php bin/console worker:registry-refresh"), ".")), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Packages"), /*#__PURE__*/React.createElement("div", {
        className: "table-scroll table-scroll-wide",
        tabIndex: 0,
        role: "region",
        "aria-label": "Package catalogue"
      }, /*#__PURE__*/React.createElement("table", {
        className: "audit"
      }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Package"), /*#__PURE__*/React.createElement("th", null, "Type"), /*#__PURE__*/React.createElement("th", null, "Install"), /*#__PURE__*/React.createElement("th", null, "Trust class"), /*#__PURE__*/React.createElement("th", null, "Latest"), /*#__PURE__*/React.createElement("th", null, "Compatibility"), /*#__PURE__*/React.createElement("th", null, "Advisory"), /*#__PURE__*/React.createElement("th", null))), /*#__PURE__*/React.createElement("tbody", null, P.list.map(p => /*#__PURE__*/React.createElement("tr", {
        key: p.id
      }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("strong", null, p.name), /*#__PURE__*/React.createElement("br", null), /*#__PURE__*/React.createElement("code", null, p.uid), " ", /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "via ", p.registry, " \xB7 ", p.publisher)), /*#__PURE__*/React.createElement("td", {
        className: "nowrap"
      }, p.type), /*#__PURE__*/React.createElement("td", {
        className: "nowrap"
      }, p.installState ? /*#__PURE__*/React.createElement("span", {
        className: "pill"
      }, p.installState.charAt(0).toUpperCase() + p.installState.slice(1)) : /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "-")), /*#__PURE__*/React.createElement("td", {
        className: "nowrap"
      }, /*#__PURE__*/React.createElement("code", null, p.trustClass)), /*#__PURE__*/React.createElement("td", {
        className: "nowrap"
      }, p.latest || /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "none stable")), /*#__PURE__*/React.createElement("td", {
        className: "nowrap"
      }, p.compatible == null ? /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "n/a") : p.compatible ? /*#__PURE__*/React.createElement("span", {
        className: "pill"
      }, "compatible") : /*#__PURE__*/React.createElement("span", {
        className: "pill"
      }, "incompatible with this core")), /*#__PURE__*/React.createElement("td", {
        className: "nowrap"
      }, p.blocked ? /*#__PURE__*/React.createElement("span", {
        className: "pill"
      }, "locally blocked") : null, p.advisoryStatus !== 'none' ? /*#__PURE__*/React.createElement("span", {
        className: "pill"
      }, p.advisoryStatus) : !p.blocked ? /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "none") : null), /*#__PURE__*/React.createElement("td", {
        className: "action-cell"
      }, P.detail[p.id] ? /*#__PURE__*/React.createElement("a", {
        href: "#",
        onClick: e => {
          e.preventDefault();
          go('detail', p.id);
        }
      }, "Details") : /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "Details")))))))));
    }

    /* ── Security response ─────────────────────────────────────────────── */
    if (view === 'security') {
      const S = P.security;
      return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        type: "button",
        onClick: () => setView('catalogue'),
        style: {
          alignSelf: 'flex-start'
        }
      }, "\u2190 Package catalogue"), /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, "The emergency brake applies regardless of the package flag. Advisory ingest, acknowledgement, and the local blocklist live on the ", /*#__PURE__*/React.createElement("a", {
        href: "#",
        onClick: e => e.preventDefault()
      }, "registry trust console"), "."), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Emergency execution brake ", S.executionDisabled ? /*#__PURE__*/React.createElement("span", {
        className: "pill pill-admin"
      }, "disabled") : /*#__PURE__*/React.createElement("span", {
        className: "pill"
      }, "live")), /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, S.executionDisabled ? 'Package execution is halted: ' + S.affectedInstalls + ' integration install(s) paused. Operators can still view, revoke, export, and uninstall.' : 'Package-owned webhooks and credentials are live for ' + S.affectedInstalls + ' integration install(s).'), /*#__PURE__*/React.createElement("form", {
        className: "inline-form",
        onSubmit: e => e.preventDefault()
      }, /*#__PURE__*/React.createElement(Input, {
        placeholder: "Reason (optional)",
        style: {
          maxWidth: 220
        }
      }), /*#__PURE__*/React.createElement(Input, {
        type: "password",
        placeholder: "Your password",
        autoComplete: "current-password",
        style: {
          maxWidth: 150
        }
      }), /*#__PURE__*/React.createElement(Button, {
        size: "sm"
      }, S.executionDisabled ? 'Resume package execution' : 'Emergency-disable all packages'))), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Publishers"), /*#__PURE__*/React.createElement("table", {
        className: "audit"
      }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Publisher"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null, "Verified"), /*#__PURE__*/React.createElement("th", null))), /*#__PURE__*/React.createElement("tbody", null, S.publishers.map(pub => /*#__PURE__*/React.createElement("tr", {
        key: pub.id
      }, /*#__PURE__*/React.createElement("td", null, pub.displayName, " ", /*#__PURE__*/React.createElement("code", null, pub.uid)), /*#__PURE__*/React.createElement("td", null, pub.status), /*#__PURE__*/React.createElement("td", null, pub.verifiedAt ? pub.verifiedAt + ' UTC' : 'unverified'), /*#__PURE__*/React.createElement("td", null, P.publisherDetail[pub.id] ? /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        type: "button",
        onClick: () => {
          setPubId(pub.id);
          setView('publisher');
        }
      }, "Manage") : /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "Manage"))))))), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Advisories & blocklist"), /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, S.advisoriesCount, " advisory record(s), ", S.blocklistCount, " local block(s). Ingest, acknowledge, and block on the registry trust console.")), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Transparency log"), /*#__PURE__*/React.createElement("table", {
        className: "audit"
      }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "When"), /*#__PURE__*/React.createElement("th", null, "Event"), /*#__PURE__*/React.createElement("th", null, "Detail"))), /*#__PURE__*/React.createElement("tbody", null, S.transparency.map((r, i) => /*#__PURE__*/React.createElement("tr", {
        key: i
      }, /*#__PURE__*/React.createElement("td", {
        className: "nowrap mono"
      }, r.when), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, r.event)), /*#__PURE__*/React.createElement("td", null, r.detail)))))));
    }

    /* ── Publisher trust ───────────────────────────────────────────────── */
    if (view === 'publisher') {
      const pub = P.publisherDetail[pubId];
      return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        type: "button",
        onClick: () => setView('security'),
        style: {
          alignSelf: 'flex-start'
        }
      }, "\u2190 Package security"), /*#__PURE__*/React.createElement("h2", {
        style: {
          margin: 0
        }
      }, pub.displayName, " ", /*#__PURE__*/React.createElement("span", {
        className: "pill"
      }, pub.status), pub.verifiedAt ? /*#__PURE__*/React.createElement("span", {
        className: "pill"
      }, "verified") : null), /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, /*#__PURE__*/React.createElement("code", null, pub.uid), ". Trust changes require your password. Suspension force-disables every install of this publisher's packages; reinstatement never silently re-enables them."), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Status"), /*#__PURE__*/React.createElement("div", {
        className: "form-cell"
      }, /*#__PURE__*/React.createElement("form", {
        className: "inline-form",
        onSubmit: e => e.preventDefault()
      }, /*#__PURE__*/React.createElement(Input, {
        placeholder: "Suspension reason",
        maxLength: 255,
        style: {
          maxWidth: 200
        }
      }), /*#__PURE__*/React.createElement(Input, {
        type: "password",
        placeholder: "Your password",
        autoComplete: "current-password",
        style: {
          maxWidth: 150
        }
      }), /*#__PURE__*/React.createElement(Button, {
        size: "sm"
      }, "Suspend publisher")))), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Signing keys"), /*#__PURE__*/React.createElement("table", {
        className: "audit"
      }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Key id"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null, "Window"), /*#__PURE__*/React.createElement("th", null, "Fingerprint"))), /*#__PURE__*/React.createElement("tbody", null, pub.keys.map(k => /*#__PURE__*/React.createElement("tr", {
        key: k.id
      }, /*#__PURE__*/React.createElement("td", {
        className: "nowrap"
      }, /*#__PURE__*/React.createElement("code", null, k.keyId)), /*#__PURE__*/React.createElement("td", null, k.status), /*#__PURE__*/React.createElement("td", null, k.validFrom, " to ", k.validUntil), /*#__PURE__*/React.createElement("td", {
        className: "nowrap"
      }, /*#__PURE__*/React.createElement("code", null, k.fingerprint))))))), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Packages & review decisions"), pub.packages.map(pk => /*#__PURE__*/React.createElement("div", {
        key: pk.uid
      }, /*#__PURE__*/React.createElement("h3", null, /*#__PURE__*/React.createElement("code", null, pk.uid), " ", /*#__PURE__*/React.createElement("span", {
        className: "pill"
      }, pk.advisoryStatus)), /*#__PURE__*/React.createElement("ul", {
        className: "plain-list"
      }, pk.decisions.map((d, i) => /*#__PURE__*/React.createElement("li", {
        key: i
      }, d.decision, " \u2014 ", /*#__PURE__*/React.createElement("code", null, d.digest), " (", d.source, ")")))))));
    }

    /* ── Install plan ──────────────────────────────────────────────────── */
    if (view === 'plan') {
      const rel = det.releases[0];
      return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        type: "button",
        onClick: () => go('detail', pkgId),
        style: {
          alignSelf: 'flex-start'
        }
      }, "\u2190 ", det.name), /*#__PURE__*/React.createElement("h2", {
        style: {
          margin: 0
        }
      }, "Install plan \u2014 ", det.name, " ", rel.version), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Install plan"), /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, "Installing records provenance and permissions; nothing executes until you consent and enable."), /*#__PURE__*/React.createElement("table", {
        className: "audit"
      }, /*#__PURE__*/React.createElement("tbody", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Package"), /*#__PURE__*/React.createElement("td", null, det.name, " ", /*#__PURE__*/React.createElement("code", null, det.uid))), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Version"), /*#__PURE__*/React.createElement("td", null, rel.version)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Digest"), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, rel.digest))), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Registry"), /*#__PURE__*/React.createElement("td", null, det.registry ? det.registry.sourceId : 'local')), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Review"), /*#__PURE__*/React.createElement("td", null, rel.review)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Compatibility"), /*#__PURE__*/React.createElement("td", null, rel.compatible ? /*#__PURE__*/React.createElement("span", {
        className: "pill"
      }, "compatible") : /*#__PURE__*/React.createElement("span", {
        className: "pill"
      }, "incompatible")))))), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Permission preview"), det.permissions.length === 0 ? /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, "No permissions declared.") : /*#__PURE__*/React.createElement("table", {
        className: "audit"
      }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Permission"), /*#__PURE__*/React.createElement("th", null, "Risk"))), /*#__PURE__*/React.createElement("tbody", null, det.permissions.map((p, i) => /*#__PURE__*/React.createElement("tr", {
        key: i
      }, /*#__PURE__*/React.createElement("td", null, p.label, /*#__PURE__*/React.createElement("br", null), /*#__PURE__*/React.createElement("code", null, p.kind, ":", p.key)), /*#__PURE__*/React.createElement("td", null, p.risk)))))), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Install"), /*#__PURE__*/React.createElement("div", {
        className: "stacked"
      }, /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Current password"), /*#__PURE__*/React.createElement(Input, {
        type: "password",
        autoComplete: "current-password"
      })), /*#__PURE__*/React.createElement(Button, {
        size: "sm"
      }, "Install (disabled until consent)"))));
    }

    /* ── Consent ───────────────────────────────────────────────────────── */
    if (view === 'consent') {
      const pending = det.permissions.filter(p => !p.granted);
      return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        type: "button",
        onClick: () => go('detail', pkgId),
        style: {
          alignSelf: 'flex-start'
        }
      }, "\u2190 ", det.name), /*#__PURE__*/React.createElement("h2", {
        style: {
          margin: 0
        }
      }, "Consent to permissions"), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Pending grants"), pending.length === 0 ? /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, "No pending grants.") : /*#__PURE__*/React.createElement("table", {
        className: "audit"
      }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Permission"), /*#__PURE__*/React.createElement("th", null, "Risk"))), /*#__PURE__*/React.createElement("tbody", null, pending.map((p, i) => /*#__PURE__*/React.createElement("tr", {
        key: i
      }, /*#__PURE__*/React.createElement("td", null, p.label, /*#__PURE__*/React.createElement("br", null), /*#__PURE__*/React.createElement("code", null, p.kind, ":", p.key)), /*#__PURE__*/React.createElement("td", null, p.risk)))))), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Grant"), /*#__PURE__*/React.createElement("div", {
        className: "stacked"
      }, /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Current password"), /*#__PURE__*/React.createElement(Input, {
        type: "password",
        autoComplete: "current-password"
      })), /*#__PURE__*/React.createElement(Button, {
        size: "sm"
      }, "Grant and continue"))));
    }

    /* ── Package detail ────────────────────────────────────────────────── */
    const inst = det.installed;
    const pendingCount = det.permissions.filter(p => !p.granted).length;
    const notInstalled = !inst || inst.state === 'uninstalled';
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button",
      onClick: () => setView('catalogue'),
      style: {
        alignSelf: 'flex-start'
      }
    }, "\u2190 Package catalogue"), /*#__PURE__*/React.createElement("h2", {
      style: {
        margin: 0
      }
    }, det.name), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Provenance"), /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("tbody", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Package identity"), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, det.uid))), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Pinned source"), /*#__PURE__*/React.createElement("td", null, det.registry ? det.registry.sourceId + ' (' + det.registry.baseUrl + ')' : 'local')), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Type"), /*#__PURE__*/React.createElement("td", null, det.type)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Trust class"), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, det.trustClass), "; trust is never implied by being listed")), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Advisory status"), /*#__PURE__*/React.createElement("td", null, det.advisoryStatus, det.blocked ? ' · locally blocked' : ''))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Releases ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(immutable: any changed byte is a new release)")), /*#__PURE__*/React.createElement("div", {
      className: "table-scroll table-scroll-wide",
      tabIndex: 0,
      role: "region",
      "aria-label": "Package releases"
    }, /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Version"), /*#__PURE__*/React.createElement("th", null, "Channel"), /*#__PURE__*/React.createElement("th", null, "Digest"), /*#__PURE__*/React.createElement("th", null, "Signed by"), /*#__PURE__*/React.createElement("th", null, "Review"), /*#__PURE__*/React.createElement("th", null, "Core range"), /*#__PURE__*/React.createElement("th", null, "Local review"))), /*#__PURE__*/React.createElement("tbody", null, det.releases.map(r => /*#__PURE__*/React.createElement("tr", {
      key: r.id
    }, /*#__PURE__*/React.createElement("td", null, r.version), /*#__PURE__*/React.createElement("td", null, r.channel), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, r.digest.slice(0, 16), "\u2026"), r.blocked ? /*#__PURE__*/React.createElement("span", {
      className: "pill"
    }, "blocked") : null), /*#__PURE__*/React.createElement("td", null, r.signedKey ? /*#__PURE__*/React.createElement("code", null, r.signedKey) : /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "snapshot-listed")), /*#__PURE__*/React.createElement("td", null, r.review), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, r.coreMin, " - ", r.coreMax), " ", r.compatible ? /*#__PURE__*/React.createElement("span", {
      className: "pill"
    }, "compatible") : /*#__PURE__*/React.createElement("span", {
      className: "pill"
    }, "incompatible")), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("select", {
      className: "input input-small",
      defaultValue: "approved"
    }, /*#__PURE__*/React.createElement("option", null, "approved"), /*#__PURE__*/React.createElement("option", null, "rejected"), /*#__PURE__*/React.createElement("option", null, "revoked"))))))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Installation"), notInstalled ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Create an install plan before any local state is written. Enabling happens only after install and permission consent."), /*#__PURE__*/React.createElement("div", {
      className: "form-actions"
    }, /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      onClick: () => setView('plan')
    }, "Install plan"))) : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("tbody", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "State"), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: "pill"
    }, inst.state.charAt(0).toUpperCase() + inst.state.slice(1)))), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Health"), /*#__PURE__*/React.createElement("td", null, inst.health)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Version"), /*#__PURE__*/React.createElement("td", null, inst.version)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Digest"), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, inst.digest.slice(0, 24), "\u2026"))), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Pinned"), /*#__PURE__*/React.createElement("td", null, inst.pinned ? 'yes' : 'no')), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Update policy"), /*#__PURE__*/React.createElement("td", null, inst.updatePolicy)))), pendingCount > 0 ? /*#__PURE__*/React.createElement("p", {
      className: "field-error"
    }, pendingCount, " permissions await consent. ", /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        setView('consent');
      }
    }, "Review consent"), ".") : null, /*#__PURE__*/React.createElement("div", {
      className: "form-grid"
    }, inst.state === 'installed' || inst.state === 'disabled' ? /*#__PURE__*/React.createElement("form", {
      className: "stacked",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Current password"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Enable")) : null, inst.state === 'enabled' ? /*#__PURE__*/React.createElement("form", {
      className: "stacked",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, "Disable")) : null, /*#__PURE__*/React.createElement("form", {
      className: "stacked",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, inst.pinned ? 'Unpin' : 'Pin')), /*#__PURE__*/React.createElement("form", {
      className: "stacked",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Update policy"), /*#__PURE__*/React.createElement("select", {
      className: "input",
      defaultValue: inst.updatePolicy
    }, /*#__PURE__*/React.createElement("option", {
      value: "manual"
    }, "manual"), /*#__PURE__*/React.createElement("option", {
      value: "notify"
    }, "notify"))), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, "Save policy")), /*#__PURE__*/React.createElement("form", {
      className: "stacked",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "ghost"
    }, "Export")), /*#__PURE__*/React.createElement("form", {
      className: "stacked",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Current password"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "ghost"
    }, "Uninstall"))), /*#__PURE__*/React.createElement("h3", null, "Permissions"), /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Permission"), /*#__PURE__*/React.createElement("th", null, "Risk"), /*#__PURE__*/React.createElement("th", null, "Granted"))), /*#__PURE__*/React.createElement("tbody", null, det.permissions.map((p, i) => /*#__PURE__*/React.createElement("tr", {
      key: i
    }, /*#__PURE__*/React.createElement("td", null, p.label, /*#__PURE__*/React.createElement("br", null), /*#__PURE__*/React.createElement("code", null, p.kind, ":", p.key)), /*#__PURE__*/React.createElement("td", null, p.risk), /*#__PURE__*/React.createElement("td", null, p.granted ? 'yes' : 'pending'))))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "History"), det.history.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "No lifecycle history recorded for this package.") : /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Event"), /*#__PURE__*/React.createElement("th", null, "Versions"), /*#__PURE__*/React.createElement("th", null, "Digest"), /*#__PURE__*/React.createElement("th", null, "Detail"), /*#__PURE__*/React.createElement("th", null, "When"))), /*#__PURE__*/React.createElement("tbody", null, det.history.map((h, i) => /*#__PURE__*/React.createElement("tr", {
      key: i
    }, /*#__PURE__*/React.createElement("td", null, h.event), /*#__PURE__*/React.createElement("td", null, h.versions), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, h.digest, "\u2026")), /*#__PURE__*/React.createElement("td", null, h.detail || /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "\u2014")), /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, h.when, " UTC")))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Advisories"), det.advisories.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "No advisories recorded for this package.") : /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Advisory"), /*#__PURE__*/React.createElement("th", null, "Severity"), /*#__PURE__*/React.createElement("th", null, "Action"))), /*#__PURE__*/React.createElement("tbody", null, det.advisories.map((a, i) => /*#__PURE__*/React.createElement("tr", {
      key: i
    }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, a.uid)), /*#__PURE__*/React.createElement("td", null, a.severity), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, a.action))))))));
  }
  window.RBAdminParity = Object.assign(window.RBAdminParity || {}, {
    packages: {
      label: 'Packages',
      render: Packages
    }
  });
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/admin/AdminPackages.jsx", error: String((e && e.message) || e) }); }

// ui_kits/admin/AdminParity.jsx
try { (() => {
/* Admin Console kit — production-parity section panes (part 1):
   Feature flags, Thread Intelligence, Registry trust, Themes, Roles &
   capabilities, Sign-in providers, Invitations. Faithful recreations of the
   admin/*.php templates at RetroBoards @ 6d81da5. Packages live in
   AdminPackages.jsx. All register onto window.RBAdminParity. */
(function () {
  const A = () => window.RBAdmin;
  const DS = () => window.ImladrisDesignSystem_c3e027;
  function QueueCard({
    head,
    count,
    detail
  }) {
    return /*#__PURE__*/React.createElement("div", {
      className: "card queue-card is-static"
    }, /*#__PURE__*/React.createElement("span", {
      className: "queue-card-head"
    }, head), /*#__PURE__*/React.createElement("strong", {
      className: "queue-card-count"
    }, count), /*#__PURE__*/React.createElement("span", {
      className: "queue-card-detail"
    }, detail));
  }

  /* ── Feature flags (admin/features.php) ───────────────────────────────── */
  function Features() {
    const s = A().featureStats;
    const [corrupt, setCorrupt] = React.useState(false);
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "pane-intro"
    }, "Read-only view of the declared feature flags from ", /*#__PURE__*/React.createElement("code", null, "src/Core/FeatureFlags.php"), ", their configured overrides in ", /*#__PURE__*/React.createElement("code", null, "settings.features"), ", and the effective runtime state. The readiness column distinguishes rows that are not simply shipped \u2014 ", /*#__PURE__*/React.createElement("strong", null, "Ready for acceptance"), ", ", /*#__PURE__*/React.createElement("strong", null, "Missing user UI"), ", ", /*#__PURE__*/React.createElement("strong", null, "Missing admin operations"), ", ", /*#__PURE__*/React.createElement("strong", null, "Safety-blocked"), ", ", /*#__PURE__*/React.createElement("strong", null, "Operational configuration required"), ", and ", /*#__PURE__*/React.createElement("strong", null, "Reserved (ADR 0018)"), ". Enablement stays a deliberate ", /*#__PURE__*/React.createElement("code", null, "settings.features"), " write; there are intentionally no toggles here."), /*#__PURE__*/React.createElement("div", {
      className: "kit-note"
    }, /*#__PURE__*/React.createElement("span", null, "Kit demo \u2014 reveal the corrupt-overrides state:"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button",
      onClick: () => setCorrupt(v => !v)
    }, corrupt ? 'Restore valid overrides' : 'Simulate corrupt settings.features')), corrupt ? /*#__PURE__*/React.createElement("p", {
      className: "field-error"
    }, "The ", /*#__PURE__*/React.createElement("code", null, "settings.features"), " value is not a JSON object, so all stored feature overrides are being ignored and code defaults are in effect. Rewrite it as a JSON object (see ", /*#__PURE__*/React.createElement("code", null, "docs/runbooks/operations.md"), " \xA72) to restore your overrides.") : null, /*#__PURE__*/React.createElement("section", {
      className: "admin-dashboard-grid",
      "aria-label": "Feature flag summary"
    }, /*#__PURE__*/React.createElement(QueueCard, {
      head: "Declared",
      count: s.declared,
      detail: s.declared + ' declared flags'
    }), /*#__PURE__*/React.createElement(QueueCard, {
      head: "Defaults",
      count: s.default_on,
      detail: s.default_on + ' default-on · ' + s.default_off + ' default-dark'
    }), /*#__PURE__*/React.createElement(QueueCard, {
      head: "Effective",
      count: s.effective_on,
      detail: s.effective_on + ' on · ' + s.effective_off + ' off'
    }), /*#__PURE__*/React.createElement(QueueCard, {
      head: "Overrides",
      count: s.overrides,
      detail: s.unknown_overrides + ' unknown override' + (s.unknown_overrides === 1 ? '' : 's')
    })), A().featureGroups.map(g => /*#__PURE__*/React.createElement("section", {
      className: "card",
      key: g.group
    }, /*#__PURE__*/React.createElement("h2", null, g.group), /*#__PURE__*/React.createElement("div", {
      className: "table-scroll",
      tabIndex: 0,
      role: "region",
      "aria-label": g.group + ' feature flags'
    }, /*#__PURE__*/React.createElement("table", {
      className: "audit audit-flags"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Flag"), /*#__PURE__*/React.createElement("th", null, "Effective"), /*#__PURE__*/React.createElement("th", null, "Default"), /*#__PURE__*/React.createElement("th", null, "Override"), /*#__PURE__*/React.createElement("th", null, "Rollback / enablement note"), /*#__PURE__*/React.createElement("th", null, "Readiness / next step"))), /*#__PURE__*/React.createElement("tbody", null, g.rows.map(r => /*#__PURE__*/React.createElement("tr", {
      key: r.flag
    }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, r.flag)), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: 'state ' + (r.effective ? 'state-active' : 'state-paused')
    }, r.effective ? 'on' : 'off')), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: 'state ' + (r.default ? 'state-active' : 'state-paused')
    }, r.default ? 'default-on' : 'default-off')), /*#__PURE__*/React.createElement("td", null, r.override ? /*#__PURE__*/React.createElement("span", {
      className: 'state ' + r.override.cls
    }, r.override.text) : /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "none")), /*#__PURE__*/React.createElement("td", null, r.rollback), /*#__PURE__*/React.createElement("td", null, r.readiness ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("span", {
      className: 'state ' + r.readiness.cls
    }, r.readiness.status), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, r.readiness.note, r.readiness.href ? /*#__PURE__*/React.createElement(React.Fragment, null, " ", /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => e.preventDefault()
    }, r.readiness.link)) : null)) : /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "\u2014"))))))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Unknown overrides"), A().unknownOverrides.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "No undeclared keys are present in ", /*#__PURE__*/React.createElement("code", null, "settings.features"), ".") : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "These keys are present in ", /*#__PURE__*/React.createElement("code", null, "settings.features"), " but are not declared in ", /*#__PURE__*/React.createElement("code", null, "FeatureFlags::DEFAULTS"), ". Remove them unless they are part of an in-progress local patch."), /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Key"), /*#__PURE__*/React.createElement("th", null, "Cast value"), /*#__PURE__*/React.createElement("th", null, "Raw value"))), /*#__PURE__*/React.createElement("tbody", null, A().unknownOverrides.map(r => /*#__PURE__*/React.createElement("tr", {
      key: r.flag
    }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, r.flag)), /*#__PURE__*/React.createElement("td", null, r.valueText), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, r.rawValue)))))))));
  }

  /* ── Thread Intelligence (admin/thread_intelligence.php) ──────────────── */
  function ThreadIntelligence() {
    const d = A().ti;
    const usedCalls = d.budget.usedCalls + d.budget.reservedCalls;
    const usedTokens = d.budget.usedTokens + d.budget.reservedTokens;
    return /*#__PURE__*/React.createElement("div", {
      className: "thread-intelligence-admin"
    }, d.warnings.length ? /*#__PURE__*/React.createElement("section", {
      className: "card ti-attention",
      "aria-label": "Needs attention"
    }, /*#__PURE__*/React.createElement("h2", null, "Needs attention"), /*#__PURE__*/React.createElement("ul", null, d.warnings.map((w, i) => /*#__PURE__*/React.createElement("li", {
      key: i
    }, w)))) : null, /*#__PURE__*/React.createElement("section", {
      className: "admin-dashboard-grid",
      "aria-label": "Thread Intelligence status"
    }, /*#__PURE__*/React.createElement(QueueCard, {
      head: "Product flags",
      count: (d.flags.community_memory ? 1 : 0) + (d.flags.automated_context ? 1 : 0),
      detail: 'community memory ' + (d.flags.community_memory ? 'on' : 'off') + ' · automated context ' + (d.flags.automated_context ? 'on' : 'off')
    }), /*#__PURE__*/React.createElement(QueueCard, {
      head: "Provider",
      count: d.credentialReady ? 'Ready' : 'Not ready',
      detail: d.providerLabel + ' · ' + (d.providerBlocked ? 'latched' : 'available')
    }), /*#__PURE__*/React.createElement(QueueCard, {
      head: "Worker",
      count: d.heartbeat.classification,
      detail: d.heartbeat.status
    }), /*#__PURE__*/React.createElement(QueueCard, {
      head: "Generation",
      count: d.paused ? 'Paused' : 'Running',
      detail: "Global provider egress brake"
    })), /*#__PURE__*/React.createElement("section", {
      className: "card ti-controls",
      "aria-label": "Recovery controls"
    }, /*#__PURE__*/React.createElement("h2", null, "Recovery controls"), /*#__PURE__*/React.createElement("button", {
      className: "btn btn-small",
      type: "button"
    }, d.paused ? 'Resume generation' : 'Pause generation'), /*#__PURE__*/React.createElement("button", {
      className: "btn btn-small",
      type: "button"
    }, "Retry provider configuration"), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Provider retry clears only the current health latch. Configure credentials outside this page.")), /*#__PURE__*/React.createElement("section", {
      className: "card ti-budget",
      "aria-label": "Daily budget"
    }, /*#__PURE__*/React.createElement("h2", null, "Daily budget"), /*#__PURE__*/React.createElement("label", null, "Calls ", usedCalls, " of ", d.budget.callLimit, /*#__PURE__*/React.createElement("progress", {
      max: d.budget.callLimit,
      value: usedCalls
    }, usedCalls)), /*#__PURE__*/React.createElement("label", null, "Input tokens ", usedTokens.toLocaleString(), " of ", d.budget.tokenLimit.toLocaleString(), /*#__PURE__*/React.createElement("progress", {
      max: d.budget.tokenLimit,
      value: usedTokens
    }, usedTokens)), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Resets ", d.budget.nextReset, " UTC")), /*#__PURE__*/React.createElement("section", {
      className: "admin-dashboard-grid",
      "aria-label": "Queue states"
    }, Object.keys(d.queue).map(k => /*#__PURE__*/React.createElement(QueueCard, {
      key: k,
      head: k.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase()),
      count: d.queue[k],
      detail: 'thread' + (d.queue[k] === 1 ? '' : 's')
    }))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Generation contract"), /*#__PURE__*/React.createElement("dl", {
      className: "ti-metadata"
    }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("dt", null, "Model"), /*#__PURE__*/React.createElement("dd", null, /*#__PURE__*/React.createElement("code", null, d.model))), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("dt", null, "Reasoning effort"), /*#__PURE__*/React.createElement("dd", null, d.reasoningEffort)), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("dt", null, "Prompt version"), /*#__PURE__*/React.createElement("dd", null, /*#__PURE__*/React.createElement("code", null, d.promptVersion))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Recent generation evidence"), /*#__PURE__*/React.createElement("div", {
      className: "table-scroll",
      tabIndex: 0,
      role: "region",
      "aria-label": "Recent redacted generation attempts"
    }, /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "ID"), /*#__PURE__*/React.createElement("th", null, "Thread"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null, "Requested"), /*#__PURE__*/React.createElement("th", null, "Contract"), /*#__PURE__*/React.createElement("th", null, "Evidence"), /*#__PURE__*/React.createElement("th", null, "Actions"))), /*#__PURE__*/React.createElement("tbody", null, d.recent.map(g => /*#__PURE__*/React.createElement("tr", {
      key: g.id
    }, /*#__PURE__*/React.createElement("td", null, "#", g.id), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => e.preventDefault()
    }, g.thread)), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: 'state state-' + (g.status === 'published' ? 'active' : g.status === 'failed' ? 'failed' : 'pending')
    }, g.status)), /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, g.requested, " UTC"), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, g.model), /*#__PURE__*/React.createElement("br", null), g.effort, " \xB7 ", /*#__PURE__*/React.createElement("code", null, g.prompt)), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("details", {
      className: "ti-evidence"
    }, /*#__PURE__*/React.createElement("summary", null, "Redacted details"), /*#__PURE__*/React.createElement("p", null, "Trigger ", /*#__PURE__*/React.createElement("code", null, g.trigger), " \xB7 retry ", g.retry, " \xB7 window ", g.window), g.failure ? /*#__PURE__*/React.createElement("p", null, "Failure ", /*#__PURE__*/React.createElement("code", null, g.failure.code), " \xB7 ", g.failure.message) : null, g.sources.length ? /*#__PURE__*/React.createElement("p", null, "Sources: ", g.sources.map(id => /*#__PURE__*/React.createElement("a", {
      key: id,
      href: "#",
      onClick: e => e.preventDefault()
    }, "Post #", id, " "))) : null, g.candidates.length ? /*#__PURE__*/React.createElement("p", null, "Candidates: ", g.candidates.map(id => /*#__PURE__*/React.createElement("a", {
      key: id,
      href: "#",
      onClick: e => e.preventDefault()
    }, "Thread #", id, " "))) : null, /*#__PURE__*/React.createElement("p", null, "Usage: input ", g.usage.input, " \xB7 output ", g.usage.output, " \xB7 reasoning ", g.usage.reasoning, " \xB7 cached ", g.usage.cached))), /*#__PURE__*/React.createElement("td", {
      className: "ti-actions"
    }, /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Retry"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Reconcile"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Pause")))))))));
  }

  /* ── Registry trust (admin/registries.php) ────────────────────────────── */
  function Registries() {
    const {
      Input,
      Textarea,
      Button
    } = DS();
    const r = A().registries;
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "The private signing root lives offline with the operator; this console pins, rotates, and revokes public keys only. Trust changes require your password. The local blocklist works regardless of registry state."), r.list.map(reg => /*#__PURE__*/React.createElement("section", {
      className: "card",
      key: reg.id
    }, /*#__PURE__*/React.createElement("h2", null, reg.displayName, " ", /*#__PURE__*/React.createElement("code", null, reg.sourceId), " ", /*#__PURE__*/React.createElement("span", {
      className: "pill"
    }, reg.enabled ? 'enabled' : 'disabled')), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, reg.baseUrl, ". ", reg.snapshot ? 'Last verified snapshot ' + reg.snapshot.generated + ' UTC; expires ' + reg.snapshot.expires + ' UTC.' : 'No verified snapshot yet.'), /*#__PURE__*/React.createElement("div", {
      className: "table-scroll table-scroll-wide",
      tabIndex: 0,
      role: "region",
      "aria-label": 'Signing keys for ' + reg.displayName
    }, /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Key id"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null, "Window"), /*#__PURE__*/React.createElement("th", null, "Fingerprint"), /*#__PURE__*/React.createElement("th", null))), /*#__PURE__*/React.createElement("tbody", null, reg.keys.map(k => /*#__PURE__*/React.createElement("tr", {
      key: k.id
    }, /*#__PURE__*/React.createElement("td", {
      className: "nowrap"
    }, /*#__PURE__*/React.createElement("code", null, k.keyId)), /*#__PURE__*/React.createElement("td", null, k.status, k.revokedReason ? ' — ' + k.revokedReason : ''), /*#__PURE__*/React.createElement("td", {
      className: "nowrap"
    }, k.validFrom, " to ", k.validUntil), /*#__PURE__*/React.createElement("td", {
      className: "nowrap"
    }, /*#__PURE__*/React.createElement("code", null, k.fingerprint)), /*#__PURE__*/React.createElement("td", {
      className: "form-cell"
    }, k.status !== 'revoked' ? /*#__PURE__*/React.createElement("form", {
      className: "inline-form",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement(Input, {
      placeholder: "Revocation reason",
      required: true,
      style: {
        maxWidth: 180
      }
    }), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      placeholder: "Your password",
      autoComplete: "current-password",
      required: true,
      style: {
        maxWidth: 150
      }
    }), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, "Revoke")) : /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "\u2014"))))))), /*#__PURE__*/React.createElement("details", {
      className: "admin-details"
    }, /*#__PURE__*/React.createElement("summary", null, "Pin a new public key"), /*#__PURE__*/React.createElement("div", {
      className: "stacked",
      style: {
        marginTop: 12
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Key id"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 190
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Public key ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(base64, 32 bytes)")), /*#__PURE__*/React.createElement(Input, null)), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Confirm your password"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Pin key"))), /*#__PURE__*/React.createElement("details", {
      className: "admin-details"
    }, /*#__PURE__*/React.createElement("summary", null, "Apply a signed key rotation"), /*#__PURE__*/React.createElement("div", {
      className: "stacked",
      style: {
        marginTop: 12
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Rotation envelope JSON"), /*#__PURE__*/React.createElement(Textarea, {
      rows: 3
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Confirm your password"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Apply rotation"))), /*#__PURE__*/React.createElement("form", {
      className: "stacked",
      onSubmit: e => e.preventDefault(),
      style: {
        marginTop: 6
      }
    }, reg.enabled ? /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, "Disable registry (no password)") : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Confirm your password to enable"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Enable registry"))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Add a registry source"), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Source id"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 190
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Display name"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 190
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Base URL"), /*#__PURE__*/React.createElement(Input, {
      type: "url"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Confirm your password"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Add registry (starts disabled)"))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Local blocklist ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(registry-independent)")), /*#__PURE__*/React.createElement("div", {
      className: "table-scroll table-scroll-wide",
      tabIndex: 0,
      role: "region",
      "aria-label": "Local blocklist entries"
    }, /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Digest"), /*#__PURE__*/React.createElement("th", null, "Package uid"), /*#__PURE__*/React.createElement("th", null, "Reason"), /*#__PURE__*/React.createElement("th", null))), /*#__PURE__*/React.createElement("tbody", null, r.blocks.map(b => /*#__PURE__*/React.createElement("tr", {
      key: b.id
    }, /*#__PURE__*/React.createElement("td", null, b.digest ? /*#__PURE__*/React.createElement("code", null, b.digest.slice(0, 16), "\u2026") : '—'), /*#__PURE__*/React.createElement("td", null, b.uid ? /*#__PURE__*/React.createElement("code", null, b.uid) : '—'), /*#__PURE__*/React.createElement("td", null, b.reason), /*#__PURE__*/React.createElement("td", {
      className: "form-cell"
    }, /*#__PURE__*/React.createElement("form", {
      className: "inline-form",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement(Input, {
      type: "password",
      placeholder: "Your password",
      autoComplete: "current-password",
      style: {
        maxWidth: 150
      }
    }), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, "Remove (re-enables)"))))))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Advisories"), r.advisories.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "None ingested.") : /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Advisory"), /*#__PURE__*/React.createElement("th", null, "Package"), /*#__PURE__*/React.createElement("th", null, "Severity"), /*#__PURE__*/React.createElement("th", null, "Action"), /*#__PURE__*/React.createElement("th", null, "Acknowledged"), /*#__PURE__*/React.createElement("th", null))), /*#__PURE__*/React.createElement("tbody", null, r.advisories.map(a => /*#__PURE__*/React.createElement("tr", {
      key: a.id
    }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, a.uid)), /*#__PURE__*/React.createElement("td", null, a.pkgUid ? /*#__PURE__*/React.createElement("code", null, a.pkgUid) : /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "unresolved")), /*#__PURE__*/React.createElement("td", null, a.severity), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, a.action)), /*#__PURE__*/React.createElement("td", null, a.ack ? a.ack + ' UTC' : 'not yet'), /*#__PURE__*/React.createElement("td", null, a.ack ? null : /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Acknowledge"))))))));
  }

  /* ── Themes (admin/themes.php + theme_safe_mode.php) ──────────────────── */
  function Themes() {
    const {
      Input,
      Button
    } = DS();
    const t = A().themes;
    const [safe, setSafe] = React.useState(false);
    if (safe) {
      return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        type: "button",
        onClick: () => setSafe(false),
        style: {
          alignSelf: 'flex-start'
        }
      }, "\u2190 Back to Themes"), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Theme safe mode ", /*#__PURE__*/React.createElement("span", {
        className: "pill pill-admin"
      }, "Recovery")), t.safeMode ? /*#__PURE__*/React.createElement("p", {
        className: "field-error"
      }, "Safe mode is on. The built-in system theme is being served.") : /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, "Safe mode is off.")), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Enter safe mode"), /*#__PURE__*/React.createElement(Button, {
        size: "sm"
      }, "Enter safe mode")), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Exit safe mode"), /*#__PURE__*/React.createElement("div", {
        className: "stacked"
      }, /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Current password"), /*#__PURE__*/React.createElement(Input, {
        type: "password",
        autoComplete: "current-password"
      })), /*#__PURE__*/React.createElement(Button, {
        size: "sm"
      }, "Exit safe mode"))));
    }
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Safe mode"), t.safeMode ? /*#__PURE__*/React.createElement("p", {
      className: "field-error"
    }, "Theme safe mode is on. The built-in system theme is being served.") : /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Safe mode is off. Active package themes are eligible to serve."), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        setSafe(true);
      }
    }, "Open recovery page"))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Active theme"), t.active ? /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("tbody", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Package"), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("strong", null, t.active.packageName), /*#__PURE__*/React.createElement("br", null), /*#__PURE__*/React.createElement("code", null, t.active.uid))), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Version"), /*#__PURE__*/React.createElement("td", null, t.active.version)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "CSS digest"), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, t.active.cssDigest))), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Install state"), /*#__PURE__*/React.createElement("td", null, t.active.installState)), /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Activated"), /*#__PURE__*/React.createElement("td", null, t.active.activatedAt, " UTC")))) : /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "No package theme is active."), t.lkg ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Last-known-good: ", /*#__PURE__*/React.createElement("code", null, t.lkg.cssDigest), " from ", t.lkg.uid, " ", t.lkg.version, "."), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Current password"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary"
    }, "Roll back"))) : null), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Installed theme packages"), /*#__PURE__*/React.createElement("div", {
      className: "table-scroll",
      tabIndex: 0,
      role: "region",
      "aria-label": "Installed theme packages"
    }, /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Package"), /*#__PURE__*/React.createElement("th", null, "Version"), /*#__PURE__*/React.createElement("th", null, "State"), /*#__PURE__*/React.createElement("th", null, "Latest build"), /*#__PURE__*/React.createElement("th", null, "Actions"))), /*#__PURE__*/React.createElement("tbody", null, t.installs.map(i => /*#__PURE__*/React.createElement("tr", {
      key: i.id
    }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("strong", null, i.packageName), /*#__PURE__*/React.createElement("br", null), /*#__PURE__*/React.createElement("code", null, i.uid)), /*#__PURE__*/React.createElement("td", null, i.version), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: "pill"
    }, i.state.charAt(0).toUpperCase() + i.state.slice(1))), /*#__PURE__*/React.createElement("td", null, i.latestBuild ? /*#__PURE__*/React.createElement("code", null, i.latestBuild) : /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "not built")), /*#__PURE__*/React.createElement("td", {
      className: "action-cell"
    }, i.state === 'enabled' ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Preview"), " ", /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button"
    }, "Activate")) : /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => e.preventDefault()
    }, "Enable it from Packages first")))))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Preview"), t.preview ? /*#__PURE__*/React.createElement("p", null, "Previewing ", /*#__PURE__*/React.createElement("strong", null, t.preview.packageName), " in this admin session only.") : /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "No session preview is active.")));
  }

  /* ── Roles & capabilities (admin/roles.php + role_edit/simulator) ─────── */
  function Roles() {
    const {
      Input,
      Textarea,
      Button
    } = DS();
    const R = A().roles;
    const [view, setView] = React.useState('list');
    const [roleId, setRoleId] = React.useState(null);
    if (view === 'sim') {
      const sim = R.simulator;
      const res = sim.result;
      return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        type: "button",
        onClick: () => setView('list'),
        style: {
          alignSelf: 'flex-start'
        }
      }, "\u2190 Roles"), /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, "Runs ", /*#__PURE__*/React.createElement("code", null, "can(actor, capability, target, time)"), " on the ", /*#__PURE__*/React.createElement("strong", null, "real resolver"), ". While ", /*#__PURE__*/React.createElement("code", null, "capabilities"), " is in shadow, answers predict the post-cutover decision; live requests still use legacy authority."), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Simulate"), /*#__PURE__*/React.createElement("div", {
        className: "stacked"
      }, /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Actor ", /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "(username, id, or guest)")), /*#__PURE__*/React.createElement(Input, {
        defaultValue: sim.actor
      })), /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Capability"), /*#__PURE__*/React.createElement("select", {
        className: "input",
        defaultValue: sim.capability
      }, Object.keys(R.catalogue).map(k => /*#__PURE__*/React.createElement("option", {
        key: k
      }, k)))), /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Board id ", /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "(optional target)")), /*#__PURE__*/React.createElement(Input, {
        type: "number",
        defaultValue: sim.boardId,
        className: "input-small"
      })), /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "At ", /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "(optional, UTC)")), /*#__PURE__*/React.createElement(Input, {
        placeholder: "2026-07-15 12:00"
      })), /*#__PURE__*/React.createElement(Button, {
        size: "sm"
      }, "Simulate"))), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Result"), /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("strong", null, res.allowed ? 'Allowed' : 'Denied'), " \u2014 ", /*#__PURE__*/React.createElement("code", null, res.capability), " for ", res.actorLabel, res.targetLabel ? ' on ' + res.targetLabel : ''), /*#__PURE__*/React.createElement("ul", {
        className: "plain-list"
      }, /*#__PURE__*/React.createElement("li", null, "Decisive rule: ", /*#__PURE__*/React.createElement("code", null, res.source)), /*#__PURE__*/React.createElement("li", null, "Reason: ", res.reason), res.roleKey ? /*#__PURE__*/React.createElement("li", null, "Via role: ", /*#__PURE__*/React.createElement("code", null, res.roleKey), " at ", res.scopeType, " #", res.scopeId) : null)));
    }
    if (view === 'edit') {
      const det = R.detail[roleId];
      const isSystem = det.role.kind === 'system';
      return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        type: "button",
        onClick: () => setView('list'),
        style: {
          alignSelf: 'flex-start'
        }
      }, "\u2190 Roles"), /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, /*#__PURE__*/React.createElement("code", null, det.role.roleKey), " \u2014 ", isSystem ? 'Protected system anchor (decision #18), read-only.' : 'Custom role.', " Active assignments affected by changes: ", /*#__PURE__*/React.createElement("strong", null, det.impact), "."), isSystem ? /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Capabilities held"), /*#__PURE__*/React.createElement("ul", {
        className: "plain-list"
      }, det.currentKeys.map(k => /*#__PURE__*/React.createElement("li", {
        key: k
      }, /*#__PURE__*/React.createElement("code", null, k))))) : /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Edit definition"), /*#__PURE__*/React.createElement("div", {
        className: "stacked"
      }, /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Name"), /*#__PURE__*/React.createElement(Input, {
        defaultValue: det.role.name,
        maxLength: 190
      })), /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Description ", /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "(optional)")), /*#__PURE__*/React.createElement(Input, {
        defaultValue: det.role.description,
        maxLength: 255
      })), /*#__PURE__*/React.createElement("fieldset", {
        className: "events"
      }, /*#__PURE__*/React.createElement("legend", null, "Capabilities"), Object.entries(R.catalogue).map(([k, m]) => /*#__PURE__*/React.createElement("label", {
        className: "checkline",
        key: k
      }, /*#__PURE__*/React.createElement("input", {
        type: "checkbox",
        defaultChecked: det.currentKeys.includes(k),
        disabled: !m.enforced
      }), " ", /*#__PURE__*/React.createElement("code", null, k), " \u2014 ", m.consent, m.risk === 'high' ? /*#__PURE__*/React.createElement("span", {
        className: "pill"
      }, "high risk") : null, !m.enforced ? /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, " (not yet enforceable)") : null))), /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Confirm your password"), /*#__PURE__*/React.createElement(Input, {
        type: "password",
        autoComplete: "current-password"
      })), /*#__PURE__*/React.createElement(Button, {
        size: "sm"
      }, "Save (bumps version)"))), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Clone into a new custom role"), /*#__PURE__*/React.createElement("div", {
        className: "stacked"
      }, /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "New role name"), /*#__PURE__*/React.createElement(Input, {
        maxLength: 190
      })), /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Confirm your password"), /*#__PURE__*/React.createElement(Input, {
        type: "password",
        autoComplete: "current-password"
      })), /*#__PURE__*/React.createElement(Button, {
        size: "sm",
        variant: "secondary"
      }, "Clone"))), !isSystem ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Assignments"), det.assignments.length === 0 ? /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, "No one has been assigned this role yet.") : /*#__PURE__*/React.createElement("table", {
        className: "audit"
      }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Member"), /*#__PURE__*/React.createElement("th", null, "Scope"), /*#__PURE__*/React.createElement("th", null, "Window"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null))), /*#__PURE__*/React.createElement("tbody", null, det.assignments.map(a => /*#__PURE__*/React.createElement("tr", {
        key: a.id
      }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("a", {
        href: "#",
        onClick: e => e.preventDefault()
      }, "@", a.username)), /*#__PURE__*/React.createElement("td", null, a.scopeType, a.scopeName ? ' — ' + a.scopeName : ''), /*#__PURE__*/React.createElement("td", null, a.starts, " \u2192 ", a.ends), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
        className: 'state state-' + a.status
      }, a.status)), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("button", {
        className: "linkbtn danger",
        type: "button"
      }, "Revoke"))))))), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Assign this role"), /*#__PURE__*/React.createElement("div", {
        className: "stacked"
      }, /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Member username"), /*#__PURE__*/React.createElement(Input, {
        maxLength: 32
      })), /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Scope"), /*#__PURE__*/React.createElement("select", {
        className: "input"
      }, /*#__PURE__*/React.createElement("option", null, "Site-wide"), /*#__PURE__*/React.createElement("option", null, "A single board"), /*#__PURE__*/React.createElement("option", null, "A single category"))), /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Ends ", /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "(UTC, optional \u2014 blank never expires)")), /*#__PURE__*/React.createElement(Input, {
        placeholder: "YYYY-MM-DD HH:MM"
      })), /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Confirm your password"), /*#__PURE__*/React.createElement(Input, {
        type: "password",
        autoComplete: "current-password"
      })), /*#__PURE__*/React.createElement(Button, {
        size: "sm"
      }, "Assign role")))) : null);
    }
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Resolver posture: ", /*#__PURE__*/React.createElement("strong", null, R.mode), " (", /*#__PURE__*/React.createElement("code", null, "CAPABILITIES_MODE"), "). Under ", /*#__PURE__*/React.createElement("code", null, "shadow"), " the legacy rules decide and the resolver only shadow-compares; under ", /*#__PURE__*/React.createElement("code", null, "enforce"), " the resolver decides and fails closed. System roles are protected compatibility anchors and cannot be edited; clone one to adapt it."), /*#__PURE__*/React.createElement("div", {
      className: "kit-note"
    }, /*#__PURE__*/React.createElement("span", null, "Operator tools:"), /*#__PURE__*/React.createElement("button", {
      className: "linkbtn",
      type: "button",
      onClick: () => setView('sim')
    }, "Open permission simulator \u2192")), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Roles"), /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Name"), /*#__PURE__*/React.createElement("th", null, "Key"), /*#__PURE__*/React.createElement("th", null, "Kind"), /*#__PURE__*/React.createElement("th", null, "Version"), /*#__PURE__*/React.createElement("th", null, "Capabilities"), /*#__PURE__*/React.createElement("th", null, "Active assignments"), /*#__PURE__*/React.createElement("th", null))), /*#__PURE__*/React.createElement("tbody", null, R.rows.map(r => /*#__PURE__*/React.createElement("tr", {
      key: r.id
    }, /*#__PURE__*/React.createElement("td", null, r.name), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, r.roleKey)), /*#__PURE__*/React.createElement("td", null, r.kind === 'system' ? 'Protected anchor' : 'Custom'), /*#__PURE__*/React.createElement("td", null, "v", r.version), /*#__PURE__*/React.createElement("td", {
      className: "tnum"
    }, r.capabilityCount), /*#__PURE__*/React.createElement("td", {
      className: "tnum"
    }, r.impact), /*#__PURE__*/React.createElement("td", null, R.detail[r.id] ? /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        setRoleId(r.id);
        setView('edit');
      }
    }, r.kind === 'system' ? 'View / clone' : 'Edit') : /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, r.kind === 'system' ? 'View / clone' : 'Edit'))))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Create a custom role"), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Name"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 190
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Description ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(optional)")), /*#__PURE__*/React.createElement(Input, {
      maxLength: 255
    })), /*#__PURE__*/React.createElement("fieldset", {
      className: "events"
    }, /*#__PURE__*/React.createElement("legend", null, "Capabilities ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(delegable only; protected authority is never offered)")), Object.entries(R.catalogue).map(([k, m]) => /*#__PURE__*/React.createElement("label", {
      className: "checkline",
      key: k
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox",
      disabled: !m.enforced
    }), " ", /*#__PURE__*/React.createElement("code", null, k), " \u2014 ", m.consent, m.risk === 'high' ? /*#__PURE__*/React.createElement("span", {
      className: "pill"
    }, "high risk") : null, !m.enforced ? /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, " (not yet enforceable)") : null))), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Confirm your password"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Create role"))));
  }

  /* ── Sign-in providers (admin/providers.php + provider_disable.php) ────── */
  function Providers() {
    const {
      Input,
      Textarea,
      Button
    } = DS();
    const P = A().providers;
    const [disableId, setDisableId] = React.useState(null);
    if (disableId != null) {
      const tgt = P.disableTarget[disableId];
      return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        type: "button",
        onClick: () => setDisableId(null),
        style: {
          alignSelf: 'flex-start'
        }
      }, "\u2190 Sign-in providers"), /*#__PURE__*/React.createElement("section", {
        className: "card"
      }, /*#__PURE__*/React.createElement("h2", null, "Before you disable ", tgt.displayName), /*#__PURE__*/React.createElement("p", null, "Disabling removes ", /*#__PURE__*/React.createElement("strong", null, tgt.displayName), " from sign-in and blocks its ", /*#__PURE__*/React.createElement("code", null, "/auth/", tgt.providerKey, "/\u2026"), " flow. Linked identities are ", /*#__PURE__*/React.createElement("strong", null, "retained"), " \u2014 re-enabling restores sign-in unchanged."), tgt.soleAccounts.length === 0 ? /*#__PURE__*/React.createElement("p", {
        className: "muted"
      }, "No accounts rely on this provider as their only sign-in method.") : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
        className: "field-error",
        role: "alert"
      }, tgt.soleAccounts.length, " account", tgt.soleAccounts.length === 1 ? '' : 's', " can sign in ", /*#__PURE__*/React.createElement("strong", null, "only"), " through this provider (no password, no passkey, no other provider). They will be locked out until they use password reset on their listed email, or you re-enable the provider. Contact them first."), /*#__PURE__*/React.createElement("table", {
        className: "audit"
      }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Account"), /*#__PURE__*/React.createElement("th", null, "Email"))), /*#__PURE__*/React.createElement("tbody", null, tgt.soleAccounts.map(a => /*#__PURE__*/React.createElement("tr", {
        key: a.username
      }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("a", {
        href: "#",
        onClick: e => e.preventDefault()
      }, a.username)), /*#__PURE__*/React.createElement("td", {
        className: "mono"
      }, a.email)))))), /*#__PURE__*/React.createElement("div", {
        className: "stacked",
        style: {
          marginTop: 12
        }
      }, /*#__PURE__*/React.createElement("label", {
        className: "field"
      }, /*#__PURE__*/React.createElement("span", null, "Your password ", /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "(re-authentication)")), /*#__PURE__*/React.createElement(Input, {
        type: "password",
        autoComplete: "current-password"
      })), /*#__PURE__*/React.createElement("div", {
        className: "form-actions"
      }, /*#__PURE__*/React.createElement(Button, {
        size: "sm"
      }, "Disable ", tgt.displayName), /*#__PURE__*/React.createElement(Button, {
        size: "sm",
        variant: "secondary",
        onClick: () => setDisableId(null)
      }, "Cancel")))));
    }
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Generic OIDC providers are configuration, not code: a pinned HTTPS issuer, a client id, and a client secret stored only in the encrypted vault. New providers land ", /*#__PURE__*/React.createElement("strong", null, "disabled"), " \u2014 run \"Test connection\", then enable. Builtin providers (Google, Apple, GitHub) are configured through environment variables and only shown here for visibility. Disabling never deletes linked identities."), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Providers"), /*#__PURE__*/React.createElement("div", {
      className: "table-scroll table-scroll-wide",
      tabIndex: 0,
      role: "region",
      "aria-label": "Sign-in providers"
    }, /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Provider"), /*#__PURE__*/React.createElement("th", null, "Key"), /*#__PURE__*/React.createElement("th", null, "Type"), /*#__PURE__*/React.createElement("th", null, "Issuer"), /*#__PURE__*/React.createElement("th", null, "Health"), /*#__PURE__*/React.createElement("th", null, "Sole-method"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null, "Actions"))), /*#__PURE__*/React.createElement("tbody", null, P.rows.map(r => {
      const builtin = r.type !== 'generic_oidc';
      return /*#__PURE__*/React.createElement("tr", {
        key: r.id
      }, /*#__PURE__*/React.createElement("td", null, r.displayName), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("code", null, r.providerKey)), /*#__PURE__*/React.createElement("td", null, builtin ? 'Builtin (env config)' : 'Generic OIDC'), /*#__PURE__*/React.createElement("td", {
        className: "mono"
      }, r.issuer || '—'), /*#__PURE__*/React.createElement("td", null, r.health, r.healthCheckedAt ? /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, " ", r.healthCheckedAt) : null), /*#__PURE__*/React.createElement("td", {
        className: "tnum"
      }, r.soleMethodCount), /*#__PURE__*/React.createElement("td", null, builtin ? r.envConfigured ? 'Configured' : 'Not configured' : r.isEnabled ? 'Enabled' : 'Disabled'), /*#__PURE__*/React.createElement("td", {
        className: "action-cell"
      }, builtin ? /*#__PURE__*/React.createElement("span", {
        className: "muted"
      }, "Set ", /*#__PURE__*/React.createElement("code", null, "OAUTH_", r.providerKey.toUpperCase(), "_*"), " env vars") : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        type: "button"
      }, "Test connection"), r.isEnabled ? /*#__PURE__*/React.createElement(React.Fragment, null, " ", /*#__PURE__*/React.createElement("a", {
        href: "#",
        onClick: e => {
          e.preventDefault();
          if (P.disableTarget[r.id]) setDisableId(r.id);
        }
      }, "Disable\u2026")) : /*#__PURE__*/React.createElement(React.Fragment, null, " ", /*#__PURE__*/React.createElement("button", {
        className: "linkbtn",
        type: "button"
      }, "Enable")))));
    }))))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Add an OIDC provider"), /*#__PURE__*/React.createElement("div", {
      className: "stacked"
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Provider key"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 32,
      pattern: "[a-z0-9][a-z0-9_-]{1,31}"
    }), /*#__PURE__*/React.createElement("span", {
      className: "field-error",
      style: {
        color: 'var(--text-faint)'
      }
    }, "Stable slug used in ", /*#__PURE__*/React.createElement("code", null, "/auth/", '{key}', "/\u2026"), " URLs \u2014 it cannot be changed later.")), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Display name"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 190
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Issuer ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(pinned)")), /*#__PURE__*/React.createElement(Input, {
      type: "url",
      placeholder: "https://gitlab.com"
    }), /*#__PURE__*/React.createElement("span", {
      className: "field-error",
      style: {
        color: 'var(--text-faint)'
      }
    }, "Discovery resolves from ", /*#__PURE__*/React.createElement("code", null, '{issuer}', "/.well-known/openid-configuration"), "; a trailing slash is significant.")), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Client ID"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 255
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Client secret"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "off"
    }), /*#__PURE__*/React.createElement("span", {
      className: "field-error",
      style: {
        color: 'var(--text-faint)'
      }
    }, "Stored write-only in the encrypted vault (", /*#__PURE__*/React.createElement("code", null, "service_secrets"), " must be enabled first).")), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Claim map ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(optional JSON)")), /*#__PURE__*/React.createElement(Textarea, {
      rows: 2,
      placeholder: "{\"email\":\"upn\"}"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Your password ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(re-authentication)")), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Add provider"))));
  }

  /* ── Invitations (admin/invitations.php) ──────────────────────────────── */
  function Invitations() {
    const {
      Input,
      Button
    } = DS();
    const I = A().invitations;
    const [issued, setIssued] = React.useState(false);
    return /*#__PURE__*/React.createElement(React.Fragment, null, issued ? /*#__PURE__*/React.createElement("div", {
      className: "flash flash-secret",
      role: "status"
    }, /*#__PURE__*/React.createElement("strong", null, "Copy this invitation link now \u2014 it will not be shown again:"), " ", /*#__PURE__*/React.createElement("code", null, "https://imladris.example/join/inv_7f3k9d2a77qd")) : null, /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Issue an invitation"), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Invitations admit one member per use, expire automatically, and never grant staff or custom roles. Bind to an email address or a domain to restrict who can redeem."), /*#__PURE__*/React.createElement("form", {
      className: "stacked",
      onSubmit: e => {
        e.preventDefault();
        setIssued(true);
      }
    }, /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Bind to email ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(optional)")), /*#__PURE__*/React.createElement(Input, {
      type: "email",
      maxLength: 255,
      placeholder: "person@example.com"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Bind to domain ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(optional)")), /*#__PURE__*/React.createElement(Input, {
      maxLength: 190,
      placeholder: "example.com"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Max uses ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(1\u2013", I.limits.maxUses, ", default 1)")), /*#__PURE__*/React.createElement(Input, {
      type: "number",
      min: 1,
      max: I.limits.maxUses,
      className: "input-small"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Expires in days ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(1\u2013", I.limits.maxExpiryDays, ", default ", I.limits.defaultExpiryDays, ")")), /*#__PURE__*/React.createElement(Input, {
      type: "number",
      min: 1,
      max: I.limits.maxExpiryDays,
      className: "input-small"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Grant board membership ", /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "(optional)")), /*#__PURE__*/React.createElement("select", {
      className: "input"
    }, /*#__PURE__*/React.createElement("option", null, "No board grant"), I.boards.map(b => /*#__PURE__*/React.createElement("option", {
      key: b.id
    }, b.name)))), /*#__PURE__*/React.createElement(Button, {
      size: "sm"
    }, "Issue invitation"))), /*#__PURE__*/React.createElement("section", {
      className: "card"
    }, /*#__PURE__*/React.createElement("h2", null, "Issued invitations"), I.rows.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "No invitations have been issued yet.") : /*#__PURE__*/React.createElement("table", {
      className: "audit"
    }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Created"), /*#__PURE__*/React.createElement("th", null, "By"), /*#__PURE__*/React.createElement("th", null, "Binding"), /*#__PURE__*/React.createElement("th", null, "Uses"), /*#__PURE__*/React.createElement("th", null, "Expires"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null))), /*#__PURE__*/React.createElement("tbody", null, I.rows.map(r => /*#__PURE__*/React.createElement("tr", {
      key: r.id
    }, /*#__PURE__*/React.createElement("td", {
      className: "mono"
    }, r.created), /*#__PURE__*/React.createElement("td", null, r.creator), /*#__PURE__*/React.createElement("td", null, r.email ? r.email : r.domain ? '@' + r.domain : /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "any email")), /*#__PURE__*/React.createElement("td", {
      className: "tnum"
    }, r.usedCount, "/", r.maxUses), /*#__PURE__*/React.createElement("td", null, r.expires), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
      className: 'state state-' + (r.status === 'active' ? 'active' : r.status === 'revoked' ? 'revoked' : 'sent')
    }, r.status)), /*#__PURE__*/React.createElement("td", null, r.status === 'active' ? /*#__PURE__*/React.createElement("button", {
      className: "linkbtn danger",
      type: "button"
    }, "Revoke") : /*#__PURE__*/React.createElement("span", {
      className: "muted"
    }, "\u2014"))))))));
  }
  window.RBAdminParity = Object.assign(window.RBAdminParity || {}, {
    features: {
      label: 'Feature flags',
      render: Features
    },
    threadIntelligence: {
      label: 'Thread Intelligence',
      render: ThreadIntelligence
    },
    registries: {
      label: 'Registry trust',
      render: Registries
    },
    themes: {
      label: 'Themes',
      render: Themes
    },
    roles: {
      label: 'Roles',
      render: Roles
    },
    providers: {
      label: 'Sign-in providers',
      render: Providers
    },
    invitations: {
      label: 'Invitations',
      render: Invitations
    }
  });
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/admin/AdminParity.jsx", error: String((e && e.message) || e) }); }

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

// ui_kits/admin/parity-data.js
try { (() => {
/* Admin Console kit — seed data for the eight production-parity sections
   (feature flags, Thread Intelligence, packages, registry trust, themes,
   roles, sign-in providers, invitations). Faithful to the admin/*.php
   templates at RetroBoards @ 6d81da5. Merged onto window.RBAdmin. */
(function () {
  Object.assign(window.RBAdmin, {
    /* ── Feature flags (admin/features.php) ──────────────────────────────── */
    featureStats: {
      declared: 57,
      default_on: 49,
      default_off: 8,
      effective_on: 49,
      effective_off: 8,
      overrides: 2,
      unknown_overrides: 1
    },
    featureGroups: [{
      group: 'Engagement & delivery',
      rows: [{
        flag: 'engagement',
        effective: true,
        default: true,
        override: null,
        rollback: 'Set false to hide reactions and regard; content unaffected.',
        readiness: null
      }, {
        flag: 'notifications',
        effective: true,
        default: true,
        override: null,
        rollback: 'Set false to stop in-app notices; existing rows retained.',
        readiness: null
      }, {
        flag: 'email',
        effective: true,
        default: true,
        override: null,
        rollback: 'Set false to drain the mail worker to a no-op.',
        readiness: {
          status: 'Operational configuration required',
          cls: 'state-pending',
          note: 'Sending domain SPF/DKIM must pass before broadcast.',
          href: '#',
          link: 'Email console'
        }
      }, {
        flag: 'search',
        effective: true,
        default: true,
        override: null,
        rollback: 'Set false to fall back to unindexed board listing.',
        readiness: null
      }, {
        flag: 'dms',
        effective: true,
        default: true,
        override: null,
        rollback: 'Set false to hide the messages room; threads retained.',
        readiness: null
      }, {
        flag: 'presence',
        effective: true,
        default: true,
        override: null,
        rollback: 'Set false to stop presence beacons.',
        readiness: null
      }]
    }, {
      group: 'Content & composer',
      rows: [{
        flag: 'rich_composer',
        effective: true,
        default: true,
        override: null,
        rollback: 'Set false to serve the plain Markdown textarea.',
        readiness: null
      }, {
        flag: 'wysiwyg_composer',
        effective: true,
        default: true,
        override: null,
        rollback: 'GA 2026-07-02. Set false to keep the source-mode textarea canonical.',
        readiness: null
      }, {
        flag: 'server_drafts',
        effective: true,
        default: true,
        override: null,
        rollback: 'GA 2026-07-02 (ADR 0010). Set false to keep drafts client-only.',
        readiness: null
      }, {
        flag: 'custom_emoji',
        effective: true,
        default: true,
        override: null,
        rollback: 'Set false to hide the custom set from the picker.',
        readiness: null
      }, {
        flag: 'slash_giphy',
        effective: true,
        default: true,
        override: {
          cls: 'state-active',
          text: 'on'
        },
        rollback: 'GA 2026-07-02; inert until a GIPHY key is configured.',
        readiness: {
          status: 'Operational configuration required',
          cls: 'state-pending',
          note: 'giphy_public_key is unset — the /giphy picker stays hidden.'
        }
      }, {
        flag: 'community_memory',
        effective: true,
        default: true,
        override: null,
        rollback: 'Thread Intelligence GA 2026-07-12. Pause generation from Operations.',
        readiness: {
          status: 'Ready for acceptance',
          cls: 'state-active',
          note: 'Provider credential ready; worker healthy.',
          href: '#',
          link: 'Thread Intelligence'
        }
      }, {
        flag: 'automated_context',
        effective: true,
        default: true,
        override: null,
        rollback: 'GA 2026-07-12. Companion to community_memory.',
        readiness: {
          status: 'Ready for acceptance',
          cls: 'state-active',
          note: 'Runs the automated related-context pass.',
          href: '#',
          link: 'Thread Intelligence'
        }
      }]
    }, {
      group: 'Platform · P5 Gate A (ADR 0018)',
      rows: [{
        flag: 'package_registry',
        effective: true,
        default: true,
        override: null,
        rollback: 'GA 2026-07-09. Set false to freeze catalogue reads.',
        readiness: {
          status: 'Ready for acceptance',
          cls: 'state-active',
          note: 'Trust keys pinned; refresh worker current.',
          href: '#',
          link: 'Packages'
        }
      }, {
        flag: 'package_themes',
        effective: true,
        default: true,
        override: null,
        rollback: 'GA 2026-07-09. Falls back to the built-in system theme.',
        readiness: {
          status: 'Ready for acceptance',
          cls: 'state-active',
          note: 'One theme active; last-known-good recorded.',
          href: '#',
          link: 'Themes'
        }
      }, {
        flag: 'capabilities',
        effective: true,
        default: true,
        override: null,
        rollback: 'GA 2026-07-09. Resolver posture is CAPABILITIES_MODE.',
        readiness: {
          status: 'Operational configuration required',
          cls: 'state-pending',
          note: 'Resolver in shadow — legacy rules still decide.',
          href: '#',
          link: 'Roles'
        }
      }, {
        flag: 'passkeys',
        effective: true,
        default: true,
        override: null,
        rollback: 'GA 2026-07-09. Set false to hide passkey sign-in and enrolment.',
        readiness: null
      }, {
        flag: 'provider_registry',
        effective: true,
        default: true,
        override: null,
        rollback: 'GA 2026-07-09. Generic OIDC providers are configuration, not code.',
        readiness: {
          status: 'Ready for acceptance',
          cls: 'state-active',
          note: 'Builtins visible; OIDC providers land disabled.',
          href: '#',
          link: 'Sign-in providers'
        }
      }, {
        flag: 'invitations',
        effective: true,
        default: true,
        override: null,
        rollback: 'GA 2026-07-09. Set false to disable invite redemption.',
        readiness: null
      }, {
        flag: 'service_secrets',
        effective: true,
        default: true,
        override: null,
        rollback: 'GA 2026-07-09. Encrypted vault for provider/integration secrets.',
        readiness: null
      }]
    }, {
      group: 'Implemented, default-dark',
      rows: [{
        flag: 'custom_css',
        effective: false,
        default: false,
        override: null,
        rollback: 'ADR 0009. Real UI exists behind the flag; enable to allow site CSS.',
        readiness: {
          status: 'Safety-blocked',
          cls: 'state-failed',
          note: 'Theme safe mode does not suppress /brand.css custom CSS, so the documented recovery path leaves broken CSS active.',
          href: '#',
          link: 'Custom CSS editor'
        }
      }, {
        flag: 'group_dms',
        effective: false,
        default: false,
        override: null,
        rollback: 'Enable to allow multi-party direct messages.',
        readiness: {
          status: 'Ready for acceptance',
          cls: 'state-active',
          note: 'Member journey verified end-to-end on desktop and mobile; committed browser/no-JS/a11y evidence and the moderation runbook remain before enablement.',
          href: '#',
          link: 'Report queue'
        }
      }, {
        flag: 'link_previews',
        effective: false,
        default: false,
        override: null,
        rollback: 'Enable to unfurl links in posts (fetch egress).',
        readiness: {
          status: 'Missing admin operations',
          cls: 'state-paused',
          note: 'The admin list surface, per-board opt-in, and author removal controls are absent.'
        }
      }, {
        flag: 'expanded_files',
        effective: false,
        default: false,
        override: null,
        rollback: 'Enable to allow non-image attachments.',
        readiness: {
          status: 'Missing user UI',
          cls: 'state-paused',
          note: 'No member file chooser, no-JS upload form, quarantine states, or scanner outage workflow render yet.'
        }
      }]
    }, {
      group: 'Reserved · Gate B (no UI)',
      rows: [{
        flag: 'server_extensions',
        effective: false,
        default: false,
        override: null,
        rollback: 'Reserved. Enabling only unlocks the read-only Extensions probe.',
        readiness: {
          status: 'Reserved (ADR 0018)',
          cls: 'state-paused',
          note: 'No operator UI is shipped for this flag.'
        }
      }, {
        flag: 'governance',
        effective: false,
        default: false,
        override: null,
        rollback: 'Reserved anchor; no behaviour.',
        readiness: {
          status: 'Reserved (ADR 0018)',
          cls: 'state-paused',
          note: 'Placeholder for a future policy engine.'
        }
      }, {
        flag: 'service_principals',
        effective: false,
        default: false,
        override: null,
        rollback: 'Reserved anchor; no behaviour.',
        readiness: {
          status: 'Reserved (ADR 0018)',
          cls: 'state-paused',
          note: 'Placeholder for machine identities.'
        }
      }, {
        flag: 'verified_links',
        effective: false,
        default: false,
        override: null,
        rollback: 'Reserved anchor; no behaviour.',
        readiness: {
          status: 'Reserved (ADR 0018)',
          cls: 'state-paused',
          note: 'Placeholder for domain verification.'
        }
      }]
    }],
    unknownOverrides: [{
      flag: 'legacy_beta_banner',
      valueText: 'true',
      rawValue: 'true'
    }],
    /* ── Thread Intelligence (admin/thread_intelligence.php) ─────────────── */
    ti: {
      warnings: [],
      flags: {
        community_memory: true,
        automated_context: true
      },
      credentialReady: true,
      providerLabel: 'OpenAI · responses',
      providerBlocked: false,
      heartbeat: {
        classification: 'Healthy',
        status: 'last beat 40s ago'
      },
      paused: false,
      budget: {
        usedCalls: 312,
        reservedCalls: 6,
        callLimit: 2000,
        usedTokens: 486230,
        reservedTokens: 4200,
        tokenLimit: 3000000,
        nextReset: '2026-07-15 00:00'
      },
      queue: {
        pending: 4,
        in_progress: 1,
        published: 118,
        failed: 2
      },
      model: 'gpt-5',
      reasoningEffort: 'medium',
      promptVersion: 'ti-brief@2026-07-12',
      recent: [{
        id: 8841,
        thread: 'Interpreting attention head #7',
        threadId: 1042,
        status: 'published',
        requested: '2026-07-14 08:12',
        model: 'gpt-5',
        effort: 'medium',
        prompt: 'ti-brief@2026-07-12',
        trigger: 'reply_burst',
        retry: 0,
        window: 3,
        failure: null,
        usage: {
          input: 12840,
          output: 940,
          reasoning: 610,
          cached: 8200
        },
        sources: [7731, 7742],
        candidates: []
      }, {
        id: 8840,
        thread: 'Eval harness flakiness',
        threadId: 1039,
        status: 'failed',
        requested: '2026-07-14 06:03',
        model: 'gpt-5',
        effort: 'medium',
        prompt: 'ti-brief@2026-07-12',
        trigger: 'schedule',
        retry: 1,
        window: 2,
        failure: {
          code: 'provider_timeout',
          message: 'upstream 30s deadline'
        },
        usage: {
          input: 9120,
          output: 0,
          reasoning: 0,
          cached: 0
        },
        sources: [],
        candidates: [1044]
      }]
    },
    /* ── Packages (admin/packages.php + package_*.php) ───────────────────── */
    packages: {
      registrySnapshots: [{
        sourceId: 'imladris-registry',
        fresh: true,
        expires: '2026-07-16 00:00'
      }, {
        sourceId: 'community-mirror',
        fresh: false,
        expires: null
      }],
      list: [{
        id: 1,
        name: 'Aurora',
        uid: 'imladris/aurora-theme',
        type: 'theme',
        installState: 'enabled',
        trustClass: 'community',
        latest: '1.4.2',
        compatible: true,
        blocked: false,
        advisoryStatus: 'none',
        registry: 'imladris-registry',
        publisher: 'Rivendell Atelier'
      }, {
        id: 2,
        name: 'Anti-abuse scanner',
        uid: 'imladris/anti-abuse',
        type: 'integration',
        installState: 'installed',
        trustClass: 'first-party',
        latest: '3.1.0',
        compatible: true,
        blocked: false,
        advisoryStatus: 'none',
        registry: 'imladris-registry',
        publisher: 'Imladris Core'
      }, {
        id: 3,
        name: 'Digest mailer',
        uid: 'imladris/digest',
        type: 'integration',
        installState: null,
        trustClass: 'community',
        latest: '0.9.0',
        compatible: true,
        blocked: false,
        advisoryStatus: 'advisory',
        registry: 'imladris-registry',
        publisher: 'Lindir Works'
      }, {
        id: 4,
        name: 'Palantír embed',
        uid: 'thirdparty/palantir',
        type: 'integration',
        installState: null,
        trustClass: 'unverified',
        latest: '2.0.0',
        compatible: false,
        blocked: true,
        advisoryStatus: 'blocked',
        registry: 'community-mirror',
        publisher: 'unknown publisher'
      }],
      detail: {
        1: {
          name: 'Aurora',
          uid: 'imladris/aurora-theme',
          type: 'theme',
          trustClass: 'community',
          advisoryStatus: 'none',
          blocked: false,
          registry: {
            sourceId: 'imladris-registry',
            baseUrl: 'https://registry.imladris.example'
          },
          releases: [{
            id: 14,
            version: '1.4.2',
            channel: 'stable',
            digest: 'a19f7c2e5b0d4411aa77',
            signedKey: 'atelier-2026',
            review: 'approved',
            coreMin: '1.0',
            coreMax: '*',
            compatible: true,
            advisory: 'none',
            blocked: false
          }, {
            id: 12,
            version: '1.3.0',
            channel: 'stable',
            digest: '77c0d9be21aa0043fe18',
            signedKey: 'atelier-2026',
            review: 'approved',
            coreMin: '1.0',
            coreMax: '*',
            compatible: true,
            advisory: 'none',
            blocked: false
          }],
          installed: {
            state: 'enabled',
            health: 'ok',
            version: '1.4.2',
            digest: 'a19f7c2e5b0d4411aa77c0',
            pinned: true,
            updatePolicy: 'notify'
          },
          permissions: [{
            label: 'Serve theme CSS',
            kind: 'render',
            key: 'theme.css',
            risk: 'low',
            granted: true
          }, {
            label: 'Read branding tokens',
            kind: 'read',
            key: 'branding.tokens',
            risk: 'low',
            granted: true
          }],
          history: [{
            event: 'enable',
            versions: '1.3.0 -> 1.4.2',
            digest: 'a19f7c2e5b0d',
            stage: '',
            detail: 'Consent re-granted',
            when: '2026-07-13 19:40'
          }, {
            event: 'install',
            versions: '-> 1.3.0',
            digest: '77c0d9be21aa',
            stage: '',
            detail: '',
            when: '2026-07-01 09:10'
          }],
          advisories: []
        },
        2: {
          name: 'Anti-abuse scanner',
          uid: 'imladris/anti-abuse',
          type: 'integration',
          trustClass: 'first-party',
          advisoryStatus: 'none',
          blocked: false,
          registry: {
            sourceId: 'imladris-registry',
            baseUrl: 'https://registry.imladris.example'
          },
          releases: [{
            id: 31,
            version: '3.1.0',
            channel: 'stable',
            digest: 'be44aa019f7c2e5b0d44',
            signedKey: 'imladris-core',
            review: 'approved',
            coreMin: '1.0',
            coreMax: '*',
            compatible: true,
            advisory: 'none',
            blocked: false
          }],
          installed: {
            state: 'installed',
            health: 'ok',
            version: '3.1.0',
            digest: 'be44aa019f7c2e5b0d4400',
            pinned: false,
            updatePolicy: 'manual'
          },
          permissions: [{
            label: 'Scan post content on create',
            kind: 'hook',
            key: 'post.create',
            risk: 'medium',
            granted: false
          }, {
            label: 'Write moderation holds',
            kind: 'write',
            key: 'moderation.hold',
            risk: 'high',
            granted: false
          }],
          history: [{
            event: 'install',
            versions: '-> 3.1.0',
            digest: 'be44aa019f7c',
            stage: '',
            detail: 'Awaiting consent',
            when: '2026-07-14 07:55'
          }],
          advisories: []
        }
      },
      security: {
        executionDisabled: false,
        affectedInstalls: 2,
        publishers: [{
          id: 1,
          displayName: 'Imladris Core',
          uid: 'pub/imladris-core',
          status: 'active',
          verifiedAt: '2026-06-20 00:00'
        }, {
          id: 2,
          displayName: 'Rivendell Atelier',
          uid: 'pub/rivendell-atelier',
          status: 'active',
          verifiedAt: '2026-07-02 00:00'
        }, {
          id: 3,
          displayName: 'Lindir Works',
          uid: 'pub/lindir-works',
          status: 'active',
          verifiedAt: null
        }],
        transparency: [{
          when: '2026-07-13 19:40',
          event: 'package.enable',
          detail: 'imladris/aurora-theme 1.4.2'
        }, {
          when: '2026-07-10 11:02',
          event: 'registry.key.pin',
          detail: 'atelier-2026'
        }],
        advisoriesCount: 1,
        blocklistCount: 1
      },
      publisherDetail: {
        2: {
          displayName: 'Rivendell Atelier',
          uid: 'pub/rivendell-atelier',
          status: 'active',
          verifiedAt: '2026-07-02 00:00',
          keys: [{
            id: 5,
            keyId: 'atelier-2026',
            status: 'active',
            validFrom: '2026-01-01',
            validUntil: 'inf',
            fingerprint: 'c41d9a77e0b3f218'
          }],
          packages: [{
            uid: 'imladris/aurora-theme',
            advisoryStatus: 'none',
            decisions: [{
              decision: 'approved',
              digest: 'a19f7c2e5b0d',
              source: 'local-review'
            }]
          }]
        }
      }
    },
    /* ── Registry trust (admin/registries.php) ───────────────────────────── */
    registries: {
      list: [{
        id: 1,
        sourceId: 'imladris-registry',
        displayName: 'Imladris registry',
        baseUrl: 'https://registry.imladris.example',
        enabled: true,
        snapshot: {
          generated: '2026-07-14 00:00',
          expires: '2026-07-16 00:00'
        },
        keys: [{
          id: 1,
          keyId: 'imladris-2026',
          status: 'active',
          validFrom: '2026-01-01',
          validUntil: 'inf',
          fingerprint: '9f2a7c41d0b3e881',
          revokedReason: null
        }, {
          id: 2,
          keyId: 'imladris-2025',
          status: 'revoked',
          validFrom: '2025-01-01',
          validUntil: '2026-01-01',
          fingerprint: '11a0be77c2d94430',
          revokedReason: 'scheduled rotation'
        }]
      }, {
        id: 2,
        sourceId: 'community-mirror',
        displayName: 'Community mirror',
        baseUrl: 'https://mirror.example',
        enabled: false,
        snapshot: null,
        keys: [{
          id: 3,
          keyId: 'mirror-2026',
          status: 'active',
          validFrom: '2026-03-01',
          validUntil: 'inf',
          fingerprint: '77e0b3f218c41d9a',
          revokedReason: null
        }]
      }],
      blocks: [{
        id: 1,
        digest: 'deadbeef00c0ffee1122',
        uid: 'thirdparty/palantir',
        reason: 'incompatible + unverified publisher'
      }],
      advisories: [{
        id: 1,
        uid: 'ADV-2026-014',
        pkgUid: 'imladris/digest',
        severity: 'moderate',
        action: 'upgrade',
        ack: null
      }]
    },
    /* ── Themes (admin/themes.php + theme_safe_mode.php) ─────────────────── */
    themes: {
      safeMode: false,
      forcedSafeMode: false,
      active: {
        packageName: 'Aurora',
        uid: 'imladris/aurora-theme',
        version: '1.4.2',
        cssDigest: 'a19f7c2e5b0d4411',
        installState: 'enabled',
        activatedAt: '2026-07-13 19:40'
      },
      lkg: {
        cssDigest: '77c0d9be21aa0043',
        uid: 'imladris/aurora-theme',
        version: '1.3.0'
      },
      installs: [{
        id: 1,
        packageName: 'Aurora',
        uid: 'imladris/aurora-theme',
        version: '1.4.2',
        state: 'enabled',
        latestBuild: 'a19f7c2e5b0d4411',
        packageId: 1
      }, {
        id: 2,
        packageName: 'Mithril',
        uid: 'imladris/mithril-theme',
        version: '0.8.0',
        state: 'installed',
        latestBuild: null,
        packageId: 5
      }],
      preview: null
    },
    /* ── Roles & capabilities (admin/roles.php + role_edit/simulator) ────── */
    roles: {
      mode: 'shadow',
      rows: [{
        id: 1,
        name: 'Administrator',
        roleKey: 'admin',
        kind: 'system',
        version: 1,
        capabilityCount: 42,
        impact: 2
      }, {
        id: 2,
        name: 'Moderator',
        roleKey: 'moderator',
        kind: 'system',
        version: 1,
        capabilityCount: 18,
        impact: 3
      }, {
        id: 3,
        name: 'Member',
        roleKey: 'member',
        kind: 'system',
        version: 1,
        capabilityCount: 7,
        impact: 1240
      }, {
        id: 4,
        name: 'Board steward',
        roleKey: 'board_steward',
        kind: 'custom',
        version: 3,
        capabilityCount: 5,
        impact: 4
      }],
      catalogue: {
        'thread.lock': {
          consent: 'Lock and unlock threads',
          risk: 'normal',
          enforced: true
        },
        'post.hide': {
          consent: 'Hide posts pending review',
          risk: 'normal',
          enforced: true
        },
        'user.role_change': {
          consent: 'Change member roles',
          risk: 'high',
          enforced: true
        },
        'board.manage': {
          consent: 'Create, edit, and archive boards',
          risk: 'high',
          enforced: true
        },
        'badge.grant': {
          consent: 'Grant manual badges',
          risk: 'normal',
          enforced: false
        },
        'announcement.publish': {
          consent: 'Publish site banners',
          risk: 'normal',
          enforced: false
        }
      },
      detail: {
        4: {
          role: {
            id: 4,
            name: 'Board steward',
            roleKey: 'board_steward',
            kind: 'custom',
            version: 3,
            description: 'Keeps a single board tidy.'
          },
          currentKeys: ['thread.lock', 'post.hide'],
          impact: 4,
          assignments: [{
            id: 1,
            username: 'glorfindel',
            scopeType: 'board',
            scopeId: 21,
            scopeName: 'interpretability',
            starts: 'now',
            ends: 'no expiry',
            status: 'active'
          }, {
            id: 2,
            username: 'arwen',
            scopeType: 'board',
            scopeId: 22,
            scopeName: 'evaluations',
            starts: '2026-07-01',
            ends: '2026-12-31',
            status: 'active'
          }]
        },
        1: {
          role: {
            id: 1,
            name: 'Administrator',
            roleKey: 'admin',
            kind: 'system',
            version: 1,
            description: 'Protected system anchor.'
          },
          currentKeys: ['thread.lock', 'post.hide', 'user.role_change', 'board.manage'],
          impact: 2,
          assignments: []
        }
      },
      boards: [{
        id: 21,
        name: 'interpretability'
      }, {
        id: 22,
        name: 'evaluations'
      }, {
        id: 13,
        name: 'the-valley'
      }],
      simulator: {
        actor: 'glorfindel',
        capability: 'thread.lock',
        boardId: '21',
        at: '',
        result: {
          allowed: true,
          capability: 'thread.lock',
          actorLabel: '@glorfindel',
          targetLabel: 'board #21 (interpretability)',
          source: 'role_assignment',
          reason: 'Role board_steward grants thread.lock at board #21',
          roleKey: 'board_steward',
          scopeType: 'board',
          scopeId: 21
        }
      }
    },
    /* ── Sign-in providers (admin/providers.php + provider_disable.php) ───── */
    providers: {
      rows: [{
        id: 1,
        displayName: 'Google',
        providerKey: 'google',
        type: 'builtin',
        issuer: null,
        health: 'not checked',
        healthCheckedAt: null,
        soleMethodCount: 0,
        isEnabled: true,
        envConfigured: true
      }, {
        id: 2,
        displayName: 'GitHub',
        providerKey: 'github',
        type: 'builtin',
        issuer: null,
        health: 'not checked',
        healthCheckedAt: null,
        soleMethodCount: 0,
        isEnabled: true,
        envConfigured: false
      }, {
        id: 3,
        displayName: 'Council GitLab',
        providerKey: 'gitlab',
        type: 'generic_oidc',
        issuer: 'https://gitlab.com',
        health: 'ok',
        healthCheckedAt: '2h ago',
        soleMethodCount: 3,
        isEnabled: true,
        envConfigured: false
      }, {
        id: 4,
        displayName: 'Numenor SSO',
        providerKey: 'numenor',
        type: 'generic_oidc',
        issuer: 'https://id.numenor.example',
        health: 'never',
        healthCheckedAt: null,
        soleMethodCount: 0,
        isEnabled: false,
        envConfigured: false
      }],
      disableTarget: {
        3: {
          id: 3,
          displayName: 'Council GitLab',
          providerKey: 'gitlab',
          soleAccounts: [{
            username: 'lindir',
            email: 'lindir@imladris.council'
          }, {
            username: 'gildor',
            email: 'gildor@imladris.council'
          }, {
            username: 'melian',
            email: 'melian@imladris.council'
          }]
        }
      }
    },
    /* ── Invitations (admin/invitations.php) ─────────────────────────────── */
    invitations: {
      limits: {
        maxUses: 100,
        maxExpiryDays: 365,
        defaultExpiryDays: 14
      },
      boards: [{
        id: 12,
        name: 'introductions'
      }, {
        id: 13,
        name: 'the-valley'
      }],
      newInvitation: null,
      rows: [{
        id: 1,
        created: '2h ago',
        creator: 'elrond',
        email: 'nimrodel@example.com',
        domain: null,
        usedCount: 0,
        maxUses: 1,
        expires: 'in 14 days',
        status: 'active'
      }, {
        id: 2,
        created: 'yesterday',
        creator: 'elrond',
        email: null,
        domain: 'lorien.example',
        usedCount: 3,
        maxUses: 25,
        expires: 'in 10 days',
        status: 'active'
      }, {
        id: 3,
        created: '3 days ago',
        creator: 'galadriel',
        email: null,
        domain: null,
        usedCount: 1,
        maxUses: 1,
        expires: '—',
        status: 'redeemed'
      }, {
        id: 4,
        created: 'last week',
        creator: 'elrond',
        email: 'saruman@isengard.example',
        domain: null,
        usedCount: 0,
        maxUses: 1,
        expires: 'expired',
        status: 'revoked'
      }]
    }
  });
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/admin/parity-data.js", error: String((e && e.message) || e) }); }

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

  /* Passkey key glyph (bow + blade + teeth — simple shapes only). */
  function PasskeyGlyph() {
    return /*#__PURE__*/React.createElement("span", {
      className: "passkey-glyph",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24"
    }, /*#__PURE__*/React.createElement("circle", {
      cx: "9",
      cy: "12",
      r: "4"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M13 12h7"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M17 12v3"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M20 12v2"
    })));
  }

  /* Passkey sign-in — the login gate with passkeys.js enhancement revealed
     (login.php `.passkey-signin`, shown when the browser supports WebAuthn). */
  function PasskeySignin({
    go
  }) {
    const {
      Input,
      Button
    } = window.ImladrisDesignSystem_c3e027;
    const [waiting, setWaiting] = React.useState(false);
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
      defaultValue: "erestor@imladris.council"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Password"), /*#__PURE__*/React.createElement(Input, {
      className: "input-engraved",
      type: "password",
      autoComplete: "current-password"
    })), /*#__PURE__*/React.createElement(Button, {
      type: "submit"
    }, "Log in")), /*#__PURE__*/React.createElement("div", {
      className: "passkey-signin",
      "data-waiting": waiting ? '1' : undefined
    }, /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "btn btn-secondary passkey-btn",
      "aria-busy": waiting || undefined,
      onClick: () => setWaiting(v => !v)
    }, /*#__PURE__*/React.createElement(PasskeyGlyph, null), waiting ? 'Waiting for your passkey…' : 'Sign in with a passkey'), waiting ? /*#__PURE__*/React.createElement("p", {
      className: "passkey-status",
      role: "status"
    }, "Use your device screen lock or security key to continue. ", /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        setWaiting(false);
      }
    }, "Cancel")) : null), /*#__PURE__*/React.createElement(OAuth, null), /*#__PURE__*/React.createElement("div", {
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

  /* Passkey step-up — a fresh-check re-authentication ceremony for a sensitive
     action (security.php `data-passkey-stepup-btn`, used when there is no
     password). Confirms with a passkey, then returns to the action. */
  function StepUp({
    go
  }) {
    const [done, setDone] = React.useState(false);
    return /*#__PURE__*/React.createElement("div", {
      className: "auth-card"
    }, /*#__PURE__*/React.createElement("div", {
      className: "auth-emblem ward"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("rect", {
      x: "5",
      y: "4",
      width: "14",
      height: "16",
      rx: "2"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "10",
      r: "3"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M12 13v4"
    }))), /*#__PURE__*/React.createElement("span", {
      className: "auth-eyebrow"
    }, "One more ward"), /*#__PURE__*/React.createElement("h1", null, "Confirm it's you"), done ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "auth-lede",
      style: {
        color: 'var(--success)'
      }
    }, "Confirmed with your passkey. You can finish the change now."), /*#__PURE__*/React.createElement("div", {
      className: "auth-links"
    }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('verified');
      }
    }, "Continue \u2192")))) : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", {
      className: "auth-lede"
    }, "This sensitive change needs a fresh check. Confirm with the passkey on this device to continue."), /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "btn passkey-btn",
      onClick: () => setDone(true)
    }, /*#__PURE__*/React.createElement(PasskeyGlyph, null), "Confirm with a passkey"), /*#__PURE__*/React.createElement("div", {
      className: "auth-links"
    }, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('login');
      }
    }, "Use your password instead")))));
  }

  /* Invited registration — the sign-up gate reached from an invitation link
     (register.php with a valid invite: the acceptance notice, the bound
     invitation context, and the "Accept invitation" submit label). */
  function Invited({
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
    }, "Take a seat at the table"), /*#__PURE__*/React.createElement("h1", null, "Create your account"), /*#__PURE__*/React.createElement("p", {
      className: "notice",
      role: "status"
    }, "You've been invited to join this community. Complete the form to accept your invitation."), /*#__PURE__*/React.createElement("p", {
      className: "invite-chip"
    }, /*#__PURE__*/React.createElement("span", {
      className: "invite-chip-label"
    }, "Invitation"), " bound to ", /*#__PURE__*/React.createElement("strong", null, "nimrodel@example.com"), " \xB7 from ", /*#__PURE__*/React.createElement("strong", null, "@elrond")), /*#__PURE__*/React.createElement("form", {
      className: "auth-form",
      onSubmit: e => {
        e.preventDefault();
        go('verified');
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
      autoComplete: "username",
      defaultValue: "nimrodel@example.com"
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
    }, "Accept invitation")), /*#__PURE__*/React.createElement("div", {
      className: "auth-links"
    }, /*#__PURE__*/React.createElement("p", null, "Already have an account? ", /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('login');
      }
    }, "Log in"), ".")));
  }
  const VIEWS = {
    login: Login,
    register: Register,
    forgot: Forgot,
    reset: Reset,
    mfa: Mfa,
    verifyPending: VerifyPending,
    verified: Verified,
    passkey: PasskeySignin,
    stepUp: StepUp,
    invited: Invited
  };
  const SWITCH = [['login', 'Log in'], ['passkey', 'Passkey'], ['stepUp', 'Step-up'], ['register', 'Sign up'], ['invited', 'Invited'], ['forgot', 'Forgot'], ['reset', 'Reset'], ['mfa', 'MFA'], ['verifyPending', 'Verify'], ['verified', 'Verified']];
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

// ui_kits/dm/ConvoList.jsx
try { (() => {
/* Messages kit — conversation list (left pane). One tidy header (title + the
   single round "new message" invitation), a quiet search, an All / Unread
   filter, then the rows: monogram, name, one-line preview, a lone gold unread
   dot. No stacked sub-headers, no per-row boxes. */
(function () {
  const Icons = window.DMIcons;
  function ConvoList({
    conversations,
    activeId,
    onOpen,
    onNew,
    filter,
    onFilter,
    query,
    onQuery
  }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      Monogram
    } = DS;
    const RBDM = window.RBDM;
    const U = n => RBDM.users[n] || {
      name: n,
      presence: undefined
    };
    const q = query.trim().toLowerCase();
    const shown = conversations.filter(c => {
      if (filter === 'Unread' && !c.unread) return false;
      if (!q) return true;
      const name = c.kind === 'group' ? c.title : U(c.other).name;
      return (name + ' ' + c.preview).toLowerCase().includes(q);
    });
    const unreadCount = conversations.filter(c => c.unread).length;
    return /*#__PURE__*/React.createElement("aside", {
      className: "dm-listpane"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-listpane-head"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-listpane-top"
    }, /*#__PURE__*/React.createElement("span", null, /*#__PURE__*/React.createElement("span", {
      className: "eyebrow"
    }, Icons.Lock(), "Private counsel"), /*#__PURE__*/React.createElement("h1", null, "Messages")), /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-new-btn",
      onClick: onNew,
      title: "New message",
      "aria-label": "New message"
    }, Icons.Plus())), /*#__PURE__*/React.createElement("div", {
      className: "dm-search"
    }, Icons.Search(), /*#__PURE__*/React.createElement("input", {
      type: "search",
      value: query,
      onChange: e => onQuery(e.target.value),
      placeholder: "Search messages\u2026",
      "aria-label": "Search messages"
    })), /*#__PURE__*/React.createElement("div", {
      className: "dm-filter"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-chips",
      role: "tablist",
      "aria-label": "Filter conversations"
    }, ['All', 'Unread'].map(f => /*#__PURE__*/React.createElement("button", {
      key: f,
      type: "button",
      role: "tab",
      "aria-selected": filter === f,
      className: 'dm-chip' + (filter === f ? ' is-active' : ''),
      onClick: () => onFilter(f)
    }, f))), /*#__PURE__*/React.createElement("span", {
      className: "dm-count"
    }, unreadCount ? unreadCount + ' unread' : 'All read'))), shown.length === 0 ? /*#__PURE__*/React.createElement("p", {
      className: "dm-list-empty"
    }, q ? 'No letters match your search.' : 'No conversations here yet.') : /*#__PURE__*/React.createElement("ul", {
      className: "dm-list"
    }, shown.map(c => {
      const isGroup = c.kind === 'group';
      const other = isGroup ? c.title : U(c.other).name;
      const seed = isGroup ? 'group-' + c.id : c.other;
      const presence = isGroup ? undefined : U(c.other).presence;
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
      }, /*#__PURE__*/React.createElement("span", {
        className: "dm-other"
      }, other)), /*#__PURE__*/React.createElement("span", {
        className: "dm-time"
      }, c.time), /*#__PURE__*/React.createElement("span", {
        className: "dm-preview"
      }, c.preview), c.unread ? /*#__PURE__*/React.createElement("span", {
        className: "dm-unread-dot",
        "aria-label": "Unread"
      }) : null));
    })));
  }
  window.DMConvoList = ConvoList;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/dm/ConvoList.jsx", error: String((e && e.message) || e) }); }

// ui_kits/dm/DMApp.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* Messages kit — app shell. ONE reading room: the conversation list, the open
   conversation, and a collapsible details rail. New message is a dialog OVER
   the room (never a co-equal screen); confirms (leave / block / report) reuse
   the same dialog; a small toast acknowledges quiet actions. Holds all state. */
(function () {
  function clone(x) {
    return JSON.parse(JSON.stringify(x));
  }
  const isMobile = () => !!(window.matchMedia && window.matchMedia('(max-width: 900px)').matches);
  function Empty({
    onNew
  }) {
    const {
      EightPointStar,
      Button
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
    })), /*#__PURE__*/React.createElement("h2", null, "Choose a letter to read"), /*#__PURE__*/React.createElement("p", null, "Your private counsel opens here, beside the list. Pick a conversation, or begin a new one."), /*#__PURE__*/React.createElement(Button, {
      onClick: onNew
    }, "New message"))));
  }
  function DMApp() {
    const Topbar = window.DMTopbar;
    const NavRail = window.DMNavRail;
    const ConvoList = window.DMConvoList;
    const Thread = window.DMThread;
    const InfoRail = window.DMInfoRail;
    const Modal = window.DMModal;
    const ComposeForm = window.DMComposeForm;
    const ConfirmBody = window.DMConfirmBody;
    const RBDM = window.RBDM;
    const [convos, setConvos] = React.useState(() => RBDM.conversations.map(clone));
    const [activeId, setActiveId] = React.useState(RBDM.conversations[0].id);
    const [filter, setFilter] = React.useState('All');
    const [query, setQuery] = React.useState('');
    const [reply, setReply] = React.useState('');
    const [railOpen, setRailOpen] = React.useState(false); // details rail — opens on demand (nav rail now grounds the view)
    const [railMobile, setRailMobile] = React.useState(false); // mobile overlay
    const [reading, setReading] = React.useState(false); // mobile single-pane
    const [overlay, setOverlay] = React.useState(null);
    const [toast, setToast] = React.useState(null);
    const toastTimer = React.useRef(null);

    // Mark the first conversation read on first paint.
    React.useEffect(() => {
      setConvos(prev => prev.map(c => c.id === RBDM.conversations[0].id ? {
        ...c,
        unread: false
      } : c));
    }, []);
    const active = convos.find(c => c.id === activeId) || null;
    function showToast(msg) {
      setToast(msg);
      if (toastTimer.current) clearTimeout(toastTimer.current);
      toastTimer.current = setTimeout(() => setToast(null), 2600);
    }
    function open(id) {
      setActiveId(id);
      setReply('');
      setReading(true);
      setRailMobile(false);
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
        preview: body,
        read: false,
        time: 'just now'
      } : c));
      setReply('');
    }
    function updateActive(fn) {
      setConvos(prev => prev.map(c => c.id === activeId ? fn(c) : c));
    }
    function leaveConvo(id) {
      setConvos(prev => {
        const rest = prev.filter(c => c.id !== id);
        if (id === activeId) {
          setActiveId(rest[0] ? rest[0].id : null);
          setReading(false);
        }
        return rest;
      });
      setRailMobile(false);
      showToast('You left the conversation.');
    }
    function toggleRail() {
      if (isMobile()) setRailMobile(v => !v);else setRailOpen(v => !v);
    }
    function openRail() {
      if (isMobile()) setRailMobile(true);else setRailOpen(true);
    }
    function closeRail() {
      setRailMobile(false);
      if (!isMobile()) setRailOpen(false);
    }
    const confirm = spec => setOverlay({
      type: 'confirm',
      ...spec
    });
    function startConversation({
      to,
      title,
      body
    }) {
      const names = to.split(',').map(s => s.trim().replace(/^@/, '')).filter(Boolean);
      const id = Date.now();
      const first = {
        id: id + 1,
        from: RBDM.me,
        time: 'just now',
        body: body.trim()
      };
      let convo;
      if (names.length > 1) {
        convo = {
          id,
          kind: 'group',
          title: (title || '').trim() || names.join(', '),
          unread: false,
          time: 'just now',
          read: false,
          members: [{
            username: RBDM.me,
            role: 'owner'
          }, ...names.map(n => ({
            username: n,
            role: 'member'
          }))],
          preview: body.trim(),
          messages: [first]
        };
      } else {
        convo = {
          id,
          kind: 'direct',
          other: names[0] || 'someone',
          unread: false,
          time: 'just now',
          read: false,
          preview: body.trim(),
          messages: [first]
        };
      }
      setConvos(prev => [convo, ...prev]);
      setActiveId(id);
      setReading(true);
      setRailMobile(false);
      setOverlay(null);
      showToast('Your counsel has been sent.');
    }
    const railShown = !!active && (railOpen || railMobile);
    const shellClass = 'dm-shell' + (railOpen && active ? ' has-rail' : '') + (railMobile && active ? ' rail-open' : '') + (reading ? ' reading' : '');
    return /*#__PURE__*/React.createElement("div", {
      className: "app-root"
    }, /*#__PURE__*/React.createElement(Topbar, null), /*#__PURE__*/React.createElement("div", {
      className: shellClass
    }, /*#__PURE__*/React.createElement(NavRail, {
      onNewMessage: () => setOverlay({
        type: 'compose'
      })
    }), /*#__PURE__*/React.createElement(ConvoList, {
      conversations: convos,
      activeId: activeId,
      onOpen: open,
      onNew: () => setOverlay({
        type: 'compose'
      }),
      filter: filter,
      onFilter: setFilter,
      query: query,
      onQuery: setQuery
    }), active ? /*#__PURE__*/React.createElement(Thread, {
      convo: active,
      onBack: () => setReading(false),
      railOpen: railOpen || railMobile,
      onToggleRail: toggleRail,
      onOpenRail: openRail,
      onUpdateConvo: updateActive,
      onConfirm: confirm,
      onLeaveConvo: leaveConvo,
      onToast: showToast,
      replyValue: reply,
      onReplyChange: setReply,
      onSend: send
    }) : /*#__PURE__*/React.createElement(Empty, {
      onNew: () => setOverlay({
        type: 'compose'
      })
    }), railShown ? /*#__PURE__*/React.createElement(InfoRail, {
      convo: active,
      onClose: closeRail,
      onUpdateConvo: updateActive,
      onConfirm: confirm,
      onLeaveConvo: leaveConvo,
      onToast: showToast
    }) : null), overlay && overlay.type === 'compose' ? /*#__PURE__*/React.createElement(Modal, {
      onClose: () => setOverlay(null)
    }, /*#__PURE__*/React.createElement(ComposeForm, {
      onClose: () => setOverlay(null),
      onSend: startConversation
    })) : null, overlay && overlay.type === 'confirm' ? /*#__PURE__*/React.createElement(Modal, {
      onClose: () => setOverlay(null)
    }, /*#__PURE__*/React.createElement(ConfirmBody, _extends({}, overlay, {
      onClose: () => setOverlay(null)
    }))) : null, toast ? /*#__PURE__*/React.createElement("div", {
      className: "dm-toast",
      role: "status"
    }, toast) : null, !reading ? /*#__PURE__*/React.createElement("nav", {
      className: "dm-tabbar",
      "aria-label": "Primary"
    }, /*#__PURE__*/React.createElement("a", {
      className: "dm-tab",
      href: "../retroboards/index.html"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M3 11.5 12 4l9 7.5"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"
    })), "Home"), /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-tab"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M22 12h-6l-2 3h-4l-2-3H2"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z"
    })), "Inbox"), /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-tab dm-tab-fab",
      onClick: () => setOverlay({
        type: 'compose'
      }),
      "aria-label": "New message"
    }, /*#__PURE__*/React.createElement("span", null, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M12 5v14M5 12h14"
    })))), /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-tab is-active",
      "aria-current": "page"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
    })), "Messages"), /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-tab"
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24"
    }, /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "8",
      r: "4"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M4 21v-1a6 6 0 0 1 12 0v1"
    })), "You")) : null);
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
      className: "bell-badge",
      "aria-hidden": "true"
    }, "3")), /*#__PURE__*/React.createElement("span", {
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

// ui_kits/dm/InfoRail.jsx
try { (() => {
/* Messages kit — details rail (right pane, collapsible). Everything that used
   to be scattered across the thread (the inline members card, mute / leave,
   owner tools, block / report) is re-homed here into one calm, titled column.
   Direct: the person (gilt monogram, tier, joined, presence) + quiet actions.
   Group: the members list with roles + owner tools, mute, leave. */
(function () {
  const {
    useState
  } = React;
  const Icons = window.DMIcons;
  function InfoRail({
    convo,
    onClose,
    onUpdateConvo,
    onConfirm,
    onLeaveConvo,
    onToast
  }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      Monogram,
      Switch,
      Input,
      Button
    } = DS;
    const RBDM = window.RBDM;
    const isGroup = convo.kind === 'group';
    const muted = !!convo.muted;
    const u = name => RBDM.users[name] || {
      username: name,
      name: name,
      presence: 'offline',
      joined: '—',
      tier: 'Member'
    };
    const isOwner = isGroup && (convo.members.find(m => m.role === 'owner') || {}).username === RBDM.me;
    const [newMember, setNewMember] = useState('');
    const [rename, setRename] = useState(convo.title || '');
    const toggleMute = () => onUpdateConvo(c => ({
      ...c,
      muted: !c.muted
    }));
    function addMember(e) {
      e.preventDefault();
      const name = newMember.trim().replace(/^@/, '');
      if (!name) return;
      if (convo.members.some(m => m.username === name && !m.left)) {
        onToast('@' + name + ' is already in counsel.');
        setNewMember('');
        return;
      }
      onUpdateConvo(c => ({
        ...c,
        members: [...c.members.filter(m => m.username !== name), {
          username: name,
          role: 'member'
        }]
      }));
      onToast('Added @' + name + ' to the counsel.');
      setNewMember('');
    }
    function doRename(e) {
      e.preventDefault();
      const t = rename.trim();
      if (!t) return;
      onUpdateConvo(c => ({
        ...c,
        title: t
      }));
      onToast('Group renamed.');
    }
    const removeMember = name => onUpdateConvo(c => ({
      ...c,
      members: c.members.map(m => m.username === name ? {
        ...m,
        left: true
      } : m)
    }));
    const makeOwner = name => onUpdateConvo(c => ({
      ...c,
      members: c.members.map(m => m.username === name ? {
        ...m,
        role: 'owner'
      } : m.role === 'owner' ? {
        ...m,
        role: 'member'
      } : m)
    }));
    const other = isGroup ? null : u(convo.other);
    return /*#__PURE__*/React.createElement("aside", {
      className: "dm-inforail"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-head"
    }, /*#__PURE__*/React.createElement("span", {
      className: "eyebrow"
    }, isGroup ? 'Members & details' : 'Details'), /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-iconbtn",
      onClick: onClose,
      "aria-label": "Close details"
    }, Icons.Close())), /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-body"
    }, isGroup ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-id"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: convo.title,
      username: 'group-' + convo.id,
      size: "xl",
      gilt: true
    }), /*#__PURE__*/React.createElement("h2", {
      className: "dm-rail-name"
    }, convo.title), /*#__PURE__*/React.createElement("span", {
      className: "dm-rail-handle"
    }, convo.members.filter(m => !m.left).length, " in counsel")), /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-sec"
    }, /*#__PURE__*/React.createElement("h3", null, "Members"), /*#__PURE__*/React.createElement("ul", {
      className: "dm-members"
    }, convo.members.map(m => {
      const usr = u(m.username);
      const meRow = m.username === RBDM.me;
      const canManage = isOwner && !m.left && !meRow && m.role !== 'owner';
      return /*#__PURE__*/React.createElement("li", {
        key: m.username,
        className: 'dm-member' + (m.left ? ' is-left' : '')
      }, /*#__PURE__*/React.createElement(Monogram, {
        name: usr.name,
        username: m.username,
        size: "sm",
        presence: m.left ? undefined : usr.presence
      }), /*#__PURE__*/React.createElement("span", {
        className: "m-id"
      }, /*#__PURE__*/React.createElement("span", {
        className: "m-name"
      }, usr.name, meRow ? ' (you)' : ''), /*#__PURE__*/React.createElement("span", {
        className: "m-handle"
      }, "@", m.username)), m.role === 'owner' ? /*#__PURE__*/React.createElement("span", {
        className: "m-role"
      }, "Owner") : m.left ? /*#__PURE__*/React.createElement("span", {
        className: "m-role left"
      }, "Left") : null, canManage ? /*#__PURE__*/React.createElement("span", {
        className: "dm-member-tools"
      }, /*#__PURE__*/React.createElement("button", {
        type: "button",
        className: "dm-linkbtn",
        onClick: () => makeOwner(m.username)
      }, "Make owner"), /*#__PURE__*/React.createElement("button", {
        type: "button",
        className: "dm-linkbtn danger",
        onClick: () => removeMember(m.username)
      }, "Remove")) : null);
    }))), isOwner ? /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-sec"
    }, /*#__PURE__*/React.createElement("h3", null, "Owner tools"), /*#__PURE__*/React.createElement("form", {
      className: "dm-owner-tool",
      onSubmit: addMember
    }, /*#__PURE__*/React.createElement(Input, {
      value: newMember,
      onChange: e => setNewMember(e.target.value),
      placeholder: "username",
      maxLength: 32,
      "aria-label": "Add member"
    }), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary",
      type: "submit"
    }, "Add")), /*#__PURE__*/React.createElement("form", {
      className: "dm-owner-tool",
      onSubmit: doRename
    }, /*#__PURE__*/React.createElement(Input, {
      value: rename,
      onChange: e => setRename(e.target.value),
      maxLength: 120,
      "aria-label": "Rename group"
    }), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      variant: "secondary",
      type: "submit"
    }, "Rename"))) : null, /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-sec"
    }, /*#__PURE__*/React.createElement("h3", null, "This conversation"), /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-toggle"
    }, /*#__PURE__*/React.createElement(Switch, {
      label: muted ? 'Muted' : 'Mute conversation',
      checked: muted,
      onChange: toggleMute
    })), /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-actions"
    }, /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-rail-btn danger",
      onClick: () => onConfirm({
        title: 'Leave ' + convo.title + '?',
        body: 'You will stop receiving this counsel. An owner can add you again later.',
        confirmLabel: 'Leave group',
        danger: true,
        onConfirm: () => onLeaveConvo(convo.id)
      })
    }, Icons.Leave(), " Leave group")))) : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-id"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: other.name,
      username: other.username,
      size: "xl",
      gilt: true,
      presence: other.presence
    }), /*#__PURE__*/React.createElement("h2", {
      className: "dm-rail-name"
    }, other.name), /*#__PURE__*/React.createElement("span", {
      className: "dm-rail-handle"
    }, "@", other.username), /*#__PURE__*/React.createElement("span", {
      className: "dm-tier-pill"
    }, other.tier)), /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-sec"
    }, /*#__PURE__*/React.createElement("h3", null, "About"), /*#__PURE__*/React.createElement("ul", {
      className: "dm-rail-meta"
    }, /*#__PURE__*/React.createElement("li", null, /*#__PURE__*/React.createElement("span", {
      className: "k"
    }, "Presence"), /*#__PURE__*/React.createElement("span", {
      className: "v"
    }, other.presence)), /*#__PURE__*/React.createElement("li", null, /*#__PURE__*/React.createElement("span", {
      className: "k"
    }, "Joined"), /*#__PURE__*/React.createElement("span", {
      className: "v"
    }, other.joined)))), /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-sec"
    }, /*#__PURE__*/React.createElement("h3", null, "This conversation"), /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-toggle"
    }, /*#__PURE__*/React.createElement(Switch, {
      label: muted ? 'Muted' : 'Mute conversation',
      checked: muted,
      onChange: toggleMute
    })), /*#__PURE__*/React.createElement("div", {
      className: "dm-rail-actions"
    }, /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-rail-btn danger",
      onClick: () => onConfirm({
        title: 'Block ' + other.name + '?',
        body: other.name + ' will no longer be able to send you private counsel. You can undo this from settings.',
        confirmLabel: 'Block',
        danger: true,
        onConfirm: () => onToast(other.name + ' is blocked.')
      })
    }, Icons.Block(), " Block ", other.name), /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-rail-btn danger",
      onClick: () => onConfirm({
        title: 'Report this conversation?',
        body: 'The wardens will review the recent messages in this counsel.',
        confirmLabel: 'Report',
        danger: true,
        onConfirm: () => onToast('Reported to the wardens.')
      })
    }, Icons.Flag(), " Report conversation"))))));
  }
  window.DMInfoRail = InfoRail;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/dm/InfoRail.jsx", error: String((e && e.message) || e) }); }

// ui_kits/dm/NavRail.jsx
try { (() => {
/* Messages kit — product nav rail (left-most column). Grounds Messages INSIDE
   the forum chrome, mirroring the flagship inbox: Home / Inbox / Messages
   (active) / Following / Drafts, then a quiet "Direct" section. This is the
   consolidation move — DMs read as one place in the product, not a floating
   island. Static chrome; the active item is Messages. */
(function () {
  const item = (icon, label, opts) => ({
    icon,
    label,
    ...(opts || {})
  });
  function NavRail({
    onNewMessage
  }) {
    // Lucide-register glyphs, matching the forum inbox nav exactly.
    const I = {
      home: /*#__PURE__*/React.createElement("svg", {
        viewBox: "0 0 24 24"
      }, /*#__PURE__*/React.createElement("path", {
        d: "M3 11.5 12 4l9 7.5"
      }), /*#__PURE__*/React.createElement("path", {
        d: "M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"
      })),
      inbox: /*#__PURE__*/React.createElement("svg", {
        viewBox: "0 0 24 24"
      }, /*#__PURE__*/React.createElement("path", {
        d: "M22 12h-6l-2 3h-4l-2-3H2"
      }), /*#__PURE__*/React.createElement("path", {
        d: "M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z"
      })),
      messages: /*#__PURE__*/React.createElement("svg", {
        viewBox: "0 0 24 24"
      }, /*#__PURE__*/React.createElement("path", {
        d: "M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
      })),
      following: /*#__PURE__*/React.createElement("svg", {
        viewBox: "0 0 24 24"
      }, /*#__PURE__*/React.createElement("path", {
        d: "M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
      }), /*#__PURE__*/React.createElement("circle", {
        cx: "9",
        cy: "7",
        r: "4"
      }), /*#__PURE__*/React.createElement("path", {
        d: "M22 21v-2a4 4 0 0 0-3-3.87"
      }), /*#__PURE__*/React.createElement("path", {
        d: "M16 3.13a4 4 0 0 1 0 7.75"
      })),
      drafts: /*#__PURE__*/React.createElement("svg", {
        viewBox: "0 0 24 24"
      }, /*#__PURE__*/React.createElement("path", {
        d: "M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
      }), /*#__PURE__*/React.createElement("path", {
        d: "M14 2v6h6"
      }))
    };
    return /*#__PURE__*/React.createElement("nav", {
      className: "dm-navrail",
      "aria-label": "Primary"
    }, /*#__PURE__*/React.createElement("a", {
      className: "dm-nav-item",
      href: "../retroboards/index.html"
    }, /*#__PURE__*/React.createElement("span", {
      className: "dm-nav-ic"
    }, I.home), /*#__PURE__*/React.createElement("span", null, "Home")), /*#__PURE__*/React.createElement("a", {
      className: "dm-nav-item",
      href: "#inbox",
      onClick: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement("span", {
      className: "dm-nav-ic"
    }, I.inbox), /*#__PURE__*/React.createElement("span", null, "Inbox"), /*#__PURE__*/React.createElement("span", {
      className: "dm-nav-count"
    }, "7")), /*#__PURE__*/React.createElement("a", {
      className: "dm-nav-item is-active",
      href: "#messages",
      "aria-current": "page",
      onClick: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement("span", {
      className: "dm-nav-ic"
    }, I.messages), /*#__PURE__*/React.createElement("span", null, "Messages"), /*#__PURE__*/React.createElement("span", {
      className: "dm-nav-dot",
      "aria-hidden": "true"
    })), /*#__PURE__*/React.createElement("a", {
      className: "dm-nav-item",
      href: "#following",
      onClick: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement("span", {
      className: "dm-nav-ic"
    }, I.following), /*#__PURE__*/React.createElement("span", null, "Following")), /*#__PURE__*/React.createElement("a", {
      className: "dm-nav-item",
      href: "#drafts",
      onClick: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement("span", {
      className: "dm-nav-ic"
    }, I.drafts), /*#__PURE__*/React.createElement("span", null, "Drafts")), /*#__PURE__*/React.createElement("div", {
      className: "dm-nav-sec"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-nav-sec-head"
    }, "Direct"), /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-nav-compose",
      onClick: onNewMessage
    }, /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      "aria-hidden": "true"
    }, /*#__PURE__*/React.createElement("path", {
      d: "M12 5v14M5 12h14"
    })), "New message")));
  }
  window.DMNavRail = NavRail;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/dm/NavRail.jsx", error: String((e && e.message) || e) }); }

// ui_kits/dm/Overlays.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/* Messages kit — shared overlay bits: the popover menu (header ··· and the
   per-message hover ···), the modal shell + its two bodies (new-message and a
   generic confirm), and a small Lucide-style icon set. Exposed on window. */
(function () {
  const {
    useState,
    useEffect,
    useRef
  } = React;
  const DS = window.ImladrisDesignSystem_c3e027;

  /* ── Icons (Lucide register, stroke ~1.8) ─────────────────────────────── */
  const svg = (children, extra) => /*#__PURE__*/React.createElement("svg", _extends({
    viewBox: "0 0 24 24",
    width: "16",
    height: "16",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "1.8",
    strokeLinecap: "round",
    strokeLinejoin: "round"
  }, extra), children);
  const Icons = {
    Plus: () => svg(/*#__PURE__*/React.createElement("path", {
      d: "M12 5v14M5 12h14"
    })),
    Search: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("circle", {
      cx: "11",
      cy: "11",
      r: "7"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M21 21l-4.3-4.3"
    }))),
    Chevron: () => svg(/*#__PURE__*/React.createElement("path", {
      d: "M15 18l-6-6 6-6"
    })),
    More: () => /*#__PURE__*/React.createElement("svg", {
      viewBox: "0 0 24 24",
      width: "16",
      height: "16",
      style: {
        fill: 'currentColor',
        stroke: 'none'
      }
    }, /*#__PURE__*/React.createElement("circle", {
      cx: "5",
      cy: "12",
      r: "1.7"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "12",
      r: "1.7"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "19",
      cy: "12",
      r: "1.7"
    })),
    Panel: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("rect", {
      x: "3",
      y: "4",
      width: "18",
      height: "16",
      rx: "2"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M15 4v16"
    }))),
    Mute: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M11 5 6 9H2v6h4l5 4z"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M22 9l-6 6M16 9l6 6"
    }))),
    Bell: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M10.3 21a1.94 1.94 0 0 0 3.4 0"
    }))),
    User: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "7",
      r: "4"
    }))),
    Users: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "9",
      cy: "7",
      r: "4"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M22 21v-2a4 4 0 0 0-3-3.87"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M16 3.13a4 4 0 0 1 0 7.75"
    }))),
    Rename: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M12 20h9"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"
    }))),
    AddUser: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
    }), /*#__PURE__*/React.createElement("circle", {
      cx: "9",
      cy: "7",
      r: "4"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M19 8v6M22 11h-6"
    }))),
    Leave: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M16 17l5-5-5-5"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M21 12H9"
    }))),
    Block: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("circle", {
      cx: "12",
      cy: "12",
      r: "9"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M5.6 5.6l12.8 12.8"
    }))),
    Flag: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"
    }), /*#__PURE__*/React.createElement("line", {
      x1: "4",
      y1: "22",
      x2: "4",
      y2: "15"
    }))),
    Copy: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("rect", {
      x: "9",
      y: "9",
      width: "12",
      height: "12",
      rx: "2"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"
    }))),
    Close: () => svg(/*#__PURE__*/React.createElement("path", {
      d: "M18 6 6 18M6 6l12 12"
    })),
    Check: () => svg(/*#__PURE__*/React.createElement("path", {
      d: "M20 6 9 17l-5-5"
    })),
    Lock: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("rect", {
      x: "4.5",
      y: "10.5",
      width: "15",
      height: "10",
      rx: "2"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M8 10.5V7a4 4 0 0 1 8 0v3.5"
    }))),
    Send: () => svg(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("path", {
      d: "M12 19V5"
    }), /*#__PURE__*/React.createElement("path", {
      d: "M5 12l7-7 7 7"
    })))
  };

  /* ── Popover menu ─────────────────────────────────────────────────────── */
  /* `button` is a render-prop: ({ open, toggle }) => node.
     `items`: [{ label, icon, onClick, danger } | { sep:true }] */
  function Menu({
    button,
    items,
    align
  }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    useEffect(() => {
      if (!open) return;
      const onDoc = e => {
        if (ref.current && !ref.current.contains(e.target)) setOpen(false);
      };
      const onKey = e => {
        if (e.key === 'Escape') setOpen(false);
      };
      document.addEventListener('mousedown', onDoc);
      document.addEventListener('keydown', onKey);
      return () => {
        document.removeEventListener('mousedown', onDoc);
        document.removeEventListener('keydown', onKey);
      };
    }, [open]);
    return /*#__PURE__*/React.createElement("span", {
      className: "dm-menu-wrap",
      ref: ref
    }, button({
      open,
      toggle: () => setOpen(o => !o)
    }), open ? /*#__PURE__*/React.createElement("div", {
      className: 'dm-menu-pop ' + (align === 'left' ? 'to-left' : 'to-right'),
      role: "menu"
    }, items.filter(Boolean).map((it, i) => it.sep ? /*#__PURE__*/React.createElement("div", {
      key: i,
      className: "dm-menu-sep"
    }) : /*#__PURE__*/React.createElement("button", {
      key: i,
      type: "button",
      role: "menuitem",
      className: 'dm-menu-item' + (it.danger ? ' danger' : ''),
      onClick: () => {
        setOpen(false);
        it.onClick && it.onClick();
      }
    }, it.icon, it.label))) : null);
  }

  /* ── Modal shell ──────────────────────────────────────────────────────── */
  function Modal({
    onClose,
    children
  }) {
    useEffect(() => {
      const onKey = e => {
        if (e.key === 'Escape') onClose();
      };
      document.addEventListener('keydown', onKey);
      return () => document.removeEventListener('keydown', onKey);
    }, [onClose]);
    return /*#__PURE__*/React.createElement("div", {
      className: "dm-scrim",
      onMouseDown: e => {
        if (e.target === e.currentTarget) onClose();
      }
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-dialog",
      role: "dialog",
      "aria-modal": "true"
    }, children));
  }

  /* New-message body (mirrors dm/new.php: recipients → group, title, body) */
  function ComposeForm({
    onClose,
    onSend
  }) {
    const {
      Input,
      Textarea,
      Button
    } = DS;
    const [to, setTo] = useState('');
    const [title, setTitle] = useState('');
    const [body, setBody] = useState('');
    const isGroup = to.includes(',');
    return /*#__PURE__*/React.createElement("form", {
      onSubmit: e => {
        e.preventDefault();
        onSend && onSend({
          to,
          title,
          body
        });
      }
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-dialog-head"
    }, /*#__PURE__*/React.createElement("span", null, /*#__PURE__*/React.createElement("span", {
      className: "eyebrow"
    }, "Private counsel"), /*#__PURE__*/React.createElement("h2", null, "New message")), /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-dialog-close",
      onClick: onClose,
      "aria-label": "Close"
    }, Icons.Close())), /*#__PURE__*/React.createElement("div", {
      className: "dm-dialog-body"
    }, /*#__PURE__*/React.createElement(Input, {
      label: "To",
      value: to,
      onChange: e => setTo(e.target.value),
      placeholder: "username, username",
      maxLength: 255,
      autoFocus: true
    }), /*#__PURE__*/React.createElement("p", {
      className: "field-hint"
    }, "Separate usernames with commas to open a group counsel."), isGroup ? /*#__PURE__*/React.createElement(Input, {
      label: "Group title",
      value: title,
      onChange: e => setTitle(e.target.value),
      placeholder: "Optional",
      maxLength: 120
    }) : null, /*#__PURE__*/React.createElement(Textarea, {
      label: "Message",
      rows: 5,
      value: body,
      onChange: e => setBody(e.target.value),
      placeholder: "Write your counsel\u2026",
      maxLength: 5000
    })), /*#__PURE__*/React.createElement("div", {
      className: "dm-dialog-foot"
    }, /*#__PURE__*/React.createElement(Button, {
      type: "submit",
      disabled: !to.trim() || !body.trim()
    }, "Send message"), /*#__PURE__*/React.createElement(Button, {
      type: "button",
      variant: "ghost",
      onClick: onClose
    }, "Cancel")));
  }

  /* Generic confirm (leave / block / report conversation) */
  function ConfirmBody({
    title,
    body,
    confirmLabel,
    danger,
    onConfirm,
    onClose
  }) {
    const {
      Button
    } = DS;
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
      className: "dm-dialog-head"
    }, /*#__PURE__*/React.createElement("span", null, /*#__PURE__*/React.createElement("h2", null, title)), /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-dialog-close",
      onClick: onClose,
      "aria-label": "Close"
    }, Icons.Close())), /*#__PURE__*/React.createElement("div", {
      className: "dm-dialog-body"
    }, /*#__PURE__*/React.createElement("p", null, body)), /*#__PURE__*/React.createElement("div", {
      className: "dm-dialog-foot"
    }, /*#__PURE__*/React.createElement(Button, {
      variant: danger ? 'danger' : 'primary',
      onClick: () => {
        onClose();
        onConfirm && onConfirm();
      }
    }, confirmLabel || 'Confirm'), /*#__PURE__*/React.createElement(Button, {
      variant: "ghost",
      onClick: onClose
    }, "Cancel")));
  }
  window.DMIcons = Icons;
  window.DMMenu = Menu;
  window.DMModal = Modal;
  window.DMComposeForm = ComposeForm;
  window.DMConfirmBody = ConfirmBody;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/dm/Overlays.jsx", error: String((e && e.message) || e) }); }

// ui_kits/dm/Thread.jsx
try { (() => {
/* Messages kit — the open conversation (centre pane). One header (identity +
   a details toggle + a single ··· overflow), the message stream as grouped
   "letters" (consecutive messages share an author line; theirs read plain,
   mine wear the one gold plate), a per-message hover ··· (copy / report), an
   inline report form, reference cards, a read receipt, and a calm composer.
   All secondary controls live in menus or the details rail — nothing shouts. */
(function () {
  const {
    useState,
    useRef,
    useEffect
  } = React;
  const Icons = window.DMIcons;
  const Menu = window.DMMenu;
  function groupRuns(messages) {
    const out = [];
    let cur = null;
    messages.forEach(m => {
      if (cur && cur.from === m.from) cur.items.push(m);else {
        cur = {
          from: m.from,
          items: [m]
        };
        out.push(cur);
      }
    });
    return out;
  }
  const label = code => code.charAt(0).toUpperCase() + code.slice(1).replace(/_/g, ' ');
  function Thread(props) {
    const {
      convo,
      onBack,
      railOpen,
      onToggleRail,
      onOpenRail,
      onUpdateConvo,
      onConfirm,
      onLeaveConvo,
      onToast,
      replyValue,
      onReplyChange,
      onSend
    } = props;
    const DS = window.ImladrisDesignSystem_c3e027;
    const {
      Monogram
    } = DS;
    const RBDM = window.RBDM;
    const me = RBDM.users[RBDM.me];
    const [reportingId, setReportingId] = useState(null);
    const scrollRef = useRef(null);
    const taRef = useRef(null);
    const U = n => RBDM.users[n] || {
      username: n,
      name: n,
      presence: 'offline'
    };
    const isGroup = convo.kind === 'group';
    const other = isGroup ? null : U(convo.other);
    const title = isGroup ? convo.title : other.name;
    const seed = isGroup ? 'group-' + convo.id : convo.other;
    const active = isGroup ? convo.members.filter(m => !m.left) : [];
    const isOwner = isGroup && (convo.members.find(m => m.role === 'owner') || {}).username === RBDM.me;
    const muted = !!convo.muted;
    useEffect(() => {
      setReportingId(null);
    }, [convo.id]);

    // Pin to the newest letter.
    useEffect(() => {
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

    // Auto-grow the composer.
    useEffect(() => {
      const el = taRef.current;
      if (!el) return;
      el.style.height = 'auto';
      el.style.height = Math.min(el.scrollHeight, 148) + 'px';
    }, [replyValue]);
    function copy(text) {
      try {
        navigator.clipboard && navigator.clipboard.writeText(text);
      } catch (e) {/* sandbox */}
      onToast('Copied to clipboard.');
    }
    const toggleMute = () => onUpdateConvo(c => ({
      ...c,
      muted: !c.muted
    }));
    const menuItems = isGroup ? [{
      label: muted ? 'Unmute conversation' : 'Mute conversation',
      icon: Icons.Mute(),
      onClick: toggleMute
    }, isOwner ? {
      sep: true
    } : null, isOwner ? {
      label: 'Rename group',
      icon: Icons.Rename(),
      onClick: onOpenRail
    } : null, isOwner ? {
      label: 'Add member',
      icon: Icons.AddUser(),
      onClick: onOpenRail
    } : null, {
      sep: true
    }, {
      label: 'Leave group',
      icon: Icons.Leave(),
      danger: true,
      onClick: () => onConfirm({
        title: 'Leave ' + convo.title + '?',
        body: 'You will stop receiving this counsel. An owner can add you again later.',
        confirmLabel: 'Leave group',
        danger: true,
        onConfirm: () => onLeaveConvo(convo.id)
      })
    }] : [{
      label: muted ? 'Unmute conversation' : 'Mute conversation',
      icon: Icons.Mute(),
      onClick: toggleMute
    }, {
      sep: true
    }, {
      label: 'View profile',
      icon: Icons.User(),
      onClick: onOpenRail
    }, {
      label: 'Block ' + other.name,
      icon: Icons.Block(),
      danger: true,
      onClick: () => onConfirm({
        title: 'Block ' + other.name + '?',
        body: other.name + ' will no longer be able to send you private counsel. You can undo this from settings.',
        confirmLabel: 'Block',
        danger: true,
        onConfirm: () => onToast(other.name + ' is blocked.')
      })
    }, {
      label: 'Report conversation',
      icon: Icons.Flag(),
      danger: true,
      onClick: () => onConfirm({
        title: 'Report this conversation?',
        body: 'The wardens will review the recent messages in this counsel.',
        confirmLabel: 'Report',
        danger: true,
        onConfirm: () => onToast('Reported to the wardens.')
      })
    }];
    const groups = groupRuns(convo.messages);
    const last = convo.messages[convo.messages.length - 1];
    const lastMine = last && last.from === RBDM.me;
    const receipt = lastMine ? last.time === 'just now' ? 'Sent' : convo.read ? 'Read' : 'Delivered' : null;
    return /*#__PURE__*/React.createElement("section", {
      className: "dm-threadpane"
    }, /*#__PURE__*/React.createElement("header", {
      className: "dm-thread-head"
    }, /*#__PURE__*/React.createElement("button", {
      className: "dm-back",
      onClick: onBack,
      "aria-label": "Back to messages"
    }, Icons.Chevron()), /*#__PURE__*/React.createElement("div", {
      className: "dm-thread-id"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: title,
      username: seed,
      size: "md",
      gilt: true,
      presence: other ? other.presence : undefined
    }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
      className: "dm-thread-eyebrow"
    }, Icons.Lock(), isGroup ? 'Private group' : 'Private counsel'), /*#__PURE__*/React.createElement("h1", {
      className: "dm-thread-title"
    }, title), /*#__PURE__*/React.createElement("p", {
      className: "dm-thread-sub"
    }, isGroup ? /*#__PURE__*/React.createElement(React.Fragment, null, active.length, " in counsel", muted ? ' · muted' : '') : /*#__PURE__*/React.createElement(React.Fragment, null, "@", other.username, " \xB7 ", other.presence, muted ? ' · muted' : '')))), /*#__PURE__*/React.createElement("div", {
      className: "dm-thread-actions"
    }, /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: 'dm-iconbtn' + (railOpen ? ' is-active' : ''),
      onClick: onToggleRail,
      title: isGroup ? 'Members & details' : 'Details',
      "aria-label": isGroup ? 'Members and details' : 'Details',
      "aria-pressed": railOpen
    }, isGroup ? Icons.Users() : Icons.Panel()), /*#__PURE__*/React.createElement(Menu, {
      align: "right",
      button: ({
        toggle,
        open
      }) => /*#__PURE__*/React.createElement("button", {
        type: "button",
        className: 'dm-iconbtn' + (open ? ' is-active' : ''),
        onClick: toggle,
        "aria-label": "More actions"
      }, Icons.More()),
      items: menuItems
    }))), /*#__PURE__*/React.createElement("div", {
      className: "dm-scroll",
      ref: scrollRef
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-scroll-inner"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-day"
    }, /*#__PURE__*/React.createElement("span", {
      className: "dm-day-label"
    }, Icons.Lock(), " Private \u2014 only those named here can read")), groups.map((g, gi) => {
      const mine = g.from === RBDM.me;
      const from = U(g.from);
      return /*#__PURE__*/React.createElement("div", {
        key: gi,
        className: 'dm-group' + (mine ? ' mine' : '')
      }, !mine ? /*#__PURE__*/React.createElement("span", {
        className: "dm-mono-col"
      }, /*#__PURE__*/React.createElement(Monogram, {
        name: from.name,
        username: from.username,
        size: "sm"
      })) : null, /*#__PURE__*/React.createElement("div", {
        className: "dm-msgs"
      }, /*#__PURE__*/React.createElement("div", {
        className: "dm-ghead"
      }, /*#__PURE__*/React.createElement("span", {
        className: "dm-name"
      }, mine ? 'You' : from.name), isGroup && !mine && from.tier ? /*#__PURE__*/React.createElement("span", {
        className: "dm-rank"
      }, from.tier) : null, /*#__PURE__*/React.createElement("span", {
        className: "dm-gtime"
      }, g.items[0].time)), g.items.map(m => /*#__PURE__*/React.createElement(React.Fragment, {
        key: m.id
      }, /*#__PURE__*/React.createElement("div", {
        className: "dm-line"
      }, /*#__PURE__*/React.createElement("div", {
        className: "dm-body"
      }, m.quote ? /*#__PURE__*/React.createElement("blockquote", {
        className: "dm-quote"
      }, /*#__PURE__*/React.createElement("span", {
        className: "dm-quote-who"
      }, (RBDM.users[m.quote.from] || {}).name || m.quote.from), m.quote.text) : null, /*#__PURE__*/React.createElement("p", null, m.body)), /*#__PURE__*/React.createElement("span", {
        className: "dm-line-menu"
      }, /*#__PURE__*/React.createElement(Menu, {
        align: mine ? 'left' : 'right',
        button: ({
          toggle
        }) => /*#__PURE__*/React.createElement("button", {
          type: "button",
          className: "dm-dotbtn",
          onClick: toggle,
          "aria-label": "Message actions"
        }, Icons.More()),
        items: mine ? [{
          label: 'Copy text',
          icon: Icons.Copy(),
          onClick: () => copy(m.body)
        }] : [{
          label: 'Copy text',
          icon: Icons.Copy(),
          onClick: () => copy(m.body)
        }, {
          sep: true
        }, {
          label: 'Report message',
          icon: Icons.Flag(),
          danger: true,
          onClick: () => setReportingId(m.id)
        }]
      }))), m.refs ? /*#__PURE__*/React.createElement("div", {
        className: "reference-cards",
        "aria-label": "Referenced content"
      }, m.refs.map((r, i) => /*#__PURE__*/React.createElement("a", {
        key: i,
        className: "reference-card",
        href: r.url,
        onClick: e => e.preventDefault()
      }, /*#__PURE__*/React.createElement("span", {
        className: "ref-type"
      }, r.type), /*#__PURE__*/React.createElement("strong", null, r.title), r.meta ? /*#__PURE__*/React.createElement("span", {
        className: "ref-meta"
      }, r.meta) : null))) : null, reportingId === m.id ? /*#__PURE__*/React.createElement("form", {
        className: "dm-report-form",
        onSubmit: e => {
          e.preventDefault();
          setReportingId(null);
          onToast('Message reported to the wardens.');
        }
      }, /*#__PURE__*/React.createElement("select", {
        className: "input-small",
        "aria-label": "Reason"
      }, RBDM.reportReasons.map(rc => /*#__PURE__*/React.createElement("option", {
        key: rc,
        value: rc
      }, label(rc)))), /*#__PURE__*/React.createElement("input", {
        className: "input-small",
        style: {
          flex: 1,
          minWidth: 120
        },
        placeholder: "Details (optional)",
        maxLength: 255
      }), /*#__PURE__*/React.createElement(DS.Button, {
        size: "sm",
        variant: "danger",
        type: "submit"
      }, "Report"), /*#__PURE__*/React.createElement(DS.Button, {
        size: "sm",
        variant: "ghost",
        type: "button",
        onClick: () => setReportingId(null)
      }, "Cancel")) : null))));
    }), receipt ? /*#__PURE__*/React.createElement("span", {
      className: "dm-receipt"
    }, receipt === 'Read' ? Icons.Check() : null, receipt) : null)), /*#__PURE__*/React.createElement("div", {
      className: "dm-composer"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-composer-inner"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-composer-row"
    }, /*#__PURE__*/React.createElement(Monogram, {
      name: me.name,
      username: me.username,
      size: "sm"
    }), /*#__PURE__*/React.createElement("div", {
      className: "dm-composer-main"
    }, /*#__PURE__*/React.createElement("div", {
      className: "dm-composer-field"
    }, /*#__PURE__*/React.createElement("textarea", {
      ref: taRef,
      rows: 1,
      value: replyValue,
      maxLength: 5000,
      onChange: e => onReplyChange(e.target.value),
      onKeyDown: e => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          onSend();
        }
      },
      placeholder: "Write your counsel\u2026",
      "aria-label": "Write a message"
    }), /*#__PURE__*/React.createElement("button", {
      type: "button",
      className: "dm-send",
      disabled: !replyValue.trim(),
      onClick: onSend,
      "aria-label": "Send"
    }, Icons.Send())), /*#__PURE__*/React.createElement("div", {
      className: "dm-composer-meta"
    }, /*#__PURE__*/React.createElement("span", {
      className: "dm-composer-hint"
    }, "Enter to send \xB7 Shift + Enter for a new line"), /*#__PURE__*/React.createElement("span", {
      className: "dm-composer-count"
    }, replyValue ? replyValue.length : 0, " / 5000")))))));
  }
  window.DMThread = Thread;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/dm/Thread.jsx", error: String((e && e.message) || e) }); }

// ui_kits/dm/data.js
try { (() => {
/* Messages kit — seed data for private counsel (direct + group conversations).
   Same Imladris roster register as RetroBoards. Shared via window.RBDM.
   v2 (reimagine): users carry joined/tier for the details rail; a couple of
   threads carry same-author runs + a trailing message from me, so grouping and
   the read receipt read true. */
(function () {
  const users = {
    erestor: {
      username: 'erestor',
      name: 'Erestor',
      presence: 'online',
      joined: 'Third Age, 2018',
      tier: 'Loremaster'
    },
    galadriel: {
      username: 'galadriel',
      name: 'Galadriel',
      presence: 'online',
      joined: 'Third Age, 2012',
      tier: 'Legend'
    },
    elrond: {
      username: 'elrond',
      name: 'Elrond',
      presence: 'online',
      joined: 'Third Age, 2009',
      tier: 'Legend'
    },
    glorfindel: {
      username: 'glorfindel',
      name: 'Glorfindel',
      presence: 'away',
      joined: 'Third Age, 2015',
      tier: 'Veteran'
    },
    arwen: {
      username: 'arwen',
      name: 'Arwen',
      presence: 'online',
      joined: 'Third Age, 2016',
      tier: 'Veteran'
    },
    lindir: {
      username: 'lindir',
      name: 'Lindir',
      presence: 'offline',
      joined: 'Third Age, 2019',
      tier: 'Member'
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
      from: 'erestor',
      time: 'Yesterday 19:04',
      body: 'The rollback drill is drafted as well. I will attach it once Glorfindel names the day.',
      quote: {
        from: 'galadriel',
        text: 'The three questions are the right ones.'
      }
    }, {
      id: 14,
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
      body: 'Understood. I have the eval verdicts ready to read; they resolve cleanly into artifacts now.',
      refs: [{
        type: 'Topic',
        title: 'Eval verdicts — the eight that resolved this cycle',
        meta: '#evals · 12 replies',
        url: '#'
      }]
    }, {
      id: 23,
      from: 'glorfindel',
      time: '1h',
      body: 'The rollback drill is set for Tuesday. Bring the audit trail — I want precedence recorded this time.',
      quote: {
        from: 'arwen',
        text: 'They resolve cleanly into artifacts now.'
      }
    }]
  }, {
    id: 3,
    kind: 'direct',
    other: 'elrond',
    unread: false,
    time: '2h',
    read: true,
    preview: 'Thank you. Send me the wording before it is entered into the charter.',
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
    }, {
      id: 33,
      from: 'erestor',
      time: '2h',
      body: 'Thank you. Send me the wording before it is entered into the charter.'
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
      identity: user.name,
      submitLabel: "Reply",
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

// ui_kits/system/SystemApp.jsx
try { (() => {
/* System surfaces kit — the chrome-less product pages: setup wizard, error
   pages (incl. database-down), privacy content page, unsubscribe confirm, and
   the gated-profile stub. Faithful to templates/{setup/wizard, errors/error,
   privacy, unsubscribe, profile/gated}.php. A top-right switcher (kit
   affordance, not part of the product) jumps between them. */
(function () {
  const DS = () => window.ImladrisDesignSystem_c3e027;
  function Brand() {
    const {
      EightPointStar
    } = DS();
    return /*#__PURE__*/React.createElement("span", {
      className: "sys-brand"
    }, /*#__PURE__*/React.createElement(EightPointStar, {
      size: 26
    }), /*#__PURE__*/React.createElement("span", {
      className: "sys-brand-name"
    }, "RetroBoards"));
  }

  /* ── Setup wizard (setup/wizard.php) ──────────────────────────────────── */
  function Setup() {
    const {
      Input,
      Button
    } = DS();
    return /*#__PURE__*/React.createElement("div", {
      className: "sys-card setup"
    }, /*#__PURE__*/React.createElement("h1", null, "Welcome \u2014 let's set up your community"), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Create the first administrator account and name your community. You can change everything later."), /*#__PURE__*/React.createElement("form", {
      className: "stacked",
      onSubmit: e => e.preventDefault()
    }, /*#__PURE__*/React.createElement("fieldset", {
      className: "field-group"
    }, /*#__PURE__*/React.createElement("legend", null, "Community"), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Community name"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 80,
      autoFocus: true
    }))), /*#__PURE__*/React.createElement("fieldset", {
      className: "field-group"
    }, /*#__PURE__*/React.createElement("legend", null, "Administrator account"), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Username"), /*#__PURE__*/React.createElement(Input, {
      maxLength: 32
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Email"), /*#__PURE__*/React.createElement(Input, {
      type: "email"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Password"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "new-password"
    })), /*#__PURE__*/React.createElement("label", {
      className: "field"
    }, /*#__PURE__*/React.createElement("span", null, "Confirm password"), /*#__PURE__*/React.createElement(Input, {
      type: "password",
      autoComplete: "new-password"
    }))), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "A starter set of categories and boards will be created automatically."), /*#__PURE__*/React.createElement(Button, {
      type: "submit"
    }, "Create my community")));
  }

  /* ── Error pages (errors/error.php) ───────────────────────────────────── */
  const ERRORS = {
    '404': {
      code: 404,
      msg: "We couldn't find that page. It may have moved, or never existed."
    },
    '403': {
      code: 403,
      msg: "You don't have permission to view this page.",
      mod: true
    },
    '500': {
      code: 500,
      msg: 'Something went wrong on our end. The council has been notified.'
    },
    '503': {
      code: 503,
      msg: 'The community is temporarily unavailable while the database is unreachable. Please try again in a few moments.',
      db: true
    }
  };
  function ErrorPage() {
    const [s, setS] = React.useState('404');
    const e = ERRORS[s];
    return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
      className: "kit-note"
    }, /*#__PURE__*/React.createElement("span", null, "Status:"), Object.keys(ERRORS).map(k => /*#__PURE__*/React.createElement("button", {
      key: k,
      type: "button",
      className: 'linkbtn' + (k === s ? ' is-active' : ''),
      onClick: () => setS(k)
    }, k, ERRORS[k].db ? ' · database-down' : ''))), /*#__PURE__*/React.createElement("div", {
      className: "sys-card error-card"
    }, /*#__PURE__*/React.createElement("h1", null, e.code), /*#__PURE__*/React.createElement("p", null, e.msg), e.mod ? /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("a", {
      className: "btn btn-secondary",
      href: "#",
      onClick: ev => ev.preventDefault()
    }, "Moderation queue ", /*#__PURE__*/React.createElement("span", {
      className: "mod-count"
    }, "3"))) : null, /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("a", {
      className: "btn",
      href: e.db ? '#' : '../retroboards/index.html',
      onClick: e.db ? ev => ev.preventDefault() : undefined
    }, e.db ? 'Try again' : 'Back to home'))));
  }

  /* ── Privacy content page (privacy.php) ───────────────────────────────── */
  function Privacy() {
    return /*#__PURE__*/React.createElement("article", {
      className: "content-page"
    }, /*#__PURE__*/React.createElement("h1", null, "Privacy"), /*#__PURE__*/React.createElement("section", {
      "aria-labelledby": "ti-h"
    }, /*#__PURE__*/React.createElement("h2", {
      id: "ti-h"
    }, "Thread intelligence"), /*#__PURE__*/React.createElement("p", null, "Eligible public post text may be processed by OpenAI to prepare living summaries and explanations for related public discussions."), /*#__PURE__*/React.createElement("p", null, "Private and hidden content is excluded, and account metadata is not included in these requests."), /*#__PURE__*/React.createElement("p", null, "Provider storage is disabled by the application request. Member-facing pages show the resulting brief and its current sources, but do not expose model or runtime evidence.")));
  }

  /* ── Unsubscribe (unsubscribe.php) ────────────────────────────────────── */
  function Unsubscribe() {
    const {
      Button
    } = DS();
    const [state, setState] = React.useState('confirm');
    const email = 'arwen@imladris.council';
    return /*#__PURE__*/React.createElement("div", {
      className: "sys-card"
    }, /*#__PURE__*/React.createElement("h1", null, "Email preferences"), state === 'confirm' ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", null, "Unsubscribe ", /*#__PURE__*/React.createElement("strong", null, email), " from RetroBoards notification emails?"), /*#__PURE__*/React.createElement(Button, {
      onClick: () => setState('done')
    }, "Unsubscribe")) : state === 'done' ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("p", null, "Done \u2014 ", /*#__PURE__*/React.createElement("strong", null, email), " will no longer receive notification emails."), /*#__PURE__*/React.createElement("p", {
      className: "muted"
    }, "Changed your mind?"), /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      onClick: () => setState('resub')
    }, "Re-subscribe")) : /*#__PURE__*/React.createElement("p", null, /*#__PURE__*/React.createElement("strong", null, email), " has been re-subscribed to notification emails."));
  }

  /* ── Gated profile (profile/gated.php) ────────────────────────────────── */
  function ProfileGated() {
    const {
      Button
    } = DS();
    return /*#__PURE__*/React.createElement("div", {
      className: "sys-gated"
    }, /*#__PURE__*/React.createElement("h1", null, "@saruman"), /*#__PURE__*/React.createElement("p", null, "This member limits their profile to signed-in members."), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      href: "../auth/index.html"
    }, "Log in to view"));
  }
  const VIEWS = {
    setup: Setup,
    error: ErrorPage,
    privacy: Privacy,
    unsubscribe: Unsubscribe,
    gated: ProfileGated
  };
  const SWITCH = [['setup', 'Setup wizard'], ['error', 'Error'], ['privacy', 'Privacy'], ['unsubscribe', 'Unsubscribe'], ['gated', 'Profile gated']];
  function App() {
    const [v, setV] = React.useState('setup');
    const View = VIEWS[v];
    return /*#__PURE__*/React.createElement("div", {
      className: "sys-stage"
    }, /*#__PURE__*/React.createElement("nav", {
      className: "sys-switch",
      "aria-label": "System pages (kit demo)"
    }, SWITCH.map(([k, l]) => /*#__PURE__*/React.createElement("button", {
      key: k,
      className: v === k ? 'active' : '',
      onClick: () => setV(k)
    }, l))), /*#__PURE__*/React.createElement(Brand, null), /*#__PURE__*/React.createElement(View, null));
  }
  window.RBSystemApp = App;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/system/SystemApp.jsx", error: String((e && e.message) || e) }); }

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
