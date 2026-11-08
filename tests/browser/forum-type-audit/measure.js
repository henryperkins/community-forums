// In-page measurement routine (serialised into the page by audit.mjs).
// Returns text, target, centering, prose and focus-free data for one theme state.
() => {
  const parse = (c) => {
    const m = c && c.match(/rgba?\(([^)]+)\)/);
    if (!m) {
      const s = c && c.match(/color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)(?: \/ ([\d.]+))?\)/);
      if (s) return [s[1]*255, s[2]*255, s[3]*255, s[4] === undefined ? 1 : +s[4]];
      return null;
    }
    const p = m[1].split(/[ ,/]+/).filter(Boolean).map(Number);
    return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1];
  };
  const over = (top, bottom) => {
    const a = top[3];
    return [top[0]*a + bottom[0]*(1-a), top[1]*a + bottom[1]*(1-a), top[2]*a + bottom[2]*(1-a), 1];
  };
  const lin = (v) => { v /= 255; return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
  const lum = (c) => 0.2126*lin(c[0]) + 0.7152*lin(c[1]) + 0.0722*lin(c[2]);
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const apca = (txt, bg) => {
    const Y = (c) => 0.2126729*(c[0]/255)**2.4 + 0.7151522*(c[1]/255)**2.4 + 0.0721750*(c[2]/255)**2.4;
    let t = Y(txt), b = Y(bg);
    const clamp = (y) => y > 0.022 ? y : y + (0.022 - y) ** 1.414;
    t = clamp(t); b = clamp(b);
    if (Math.abs(b - t) < 0.0005) return 0;
    if (b > t) { const s = (b ** 0.56 - t ** 0.57) * 1.14; return s < 0.1 ? 0 : (s - 0.027) * 100; }
    const s = (b ** 0.65 - t ** 0.62) * 1.14; return s > -0.1 ? 0 : (s + 0.027) * 100;
  };
  const visible = (el) => {
    const cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || cs.display === 'none') return false;
    const r = el.getBoundingClientRect();
    if (r.width < 1 || r.height < 1) return false;
    for (let n = el; n; n = n.parentElement) {
      const s = getComputedStyle(n);
      if (s.display === 'none' || +s.opacity === 0) return false;
      if (n.hidden || n.getAttribute('aria-hidden') === 'true' && n !== document.documentElement) { /* still visible to sighted users */ }
      if (s.clip === 'rect(0px, 0px, 0px, 0px)' || (s.clipPath && s.clipPath.includes('inset(50%)'))) return false;
      if (s.position === 'absolute' && parseFloat(s.width) <= 1 && parseFloat(s.height) <= 1 && s.overflow === 'hidden') return false;
    }
    return true;
  };
  const canvas = () => {
    const h = parse(getComputedStyle(document.documentElement).backgroundColor);
    if (h && h[3] > 0) return over(h, [255,255,255,1]);
    const b = parse(getComputedStyle(document.body).backgroundColor);
    if (b && b[3] > 0) return over(b, [255,255,255,1]);
    return [255,255,255,1];
  };
  const base = canvas();
  const background = (el) => {
    const chain = [];
    let image = false;
    for (let n = el; n && n !== document.documentElement; n = n.parentElement) {
      const s = getComputedStyle(n);
      if (s.backgroundImage && s.backgroundImage !== 'none') image = true;
      chain.push({ c: parse(s.backgroundColor), o: +s.opacity });
    }
    let c = base;
    for (let i = chain.length - 1; i >= 0; i--) {
      const bg = chain[i].c;
      if (bg && bg[3] > 0) c = over(bg, c);
    }
    return { color: c, image };
  };
  const opacityChain = (el) => { let o = 1; for (let n = el; n; n = n.parentElement) o *= +getComputedStyle(n).opacity; return o; };
  const short = (el) => {
    const cls = (el.getAttribute('class') || '').trim().split(/\s+/).filter(Boolean).slice(0, 3).join('.');
    return el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (cls ? '.' + cls : '');
  };
  const path = (el) => { const parts = []; for (let n = el, i = 0; n && n !== document.body && i < 4; n = n.parentElement, i++) parts.unshift(short(n)); return parts.join(' > '); };

  // Exact identifiers (keys, hashes, slugs, versions…) keep the code face (ADR 0057).
  const identifier = /(^|\s)(mono|is-mono|code-area|[\w-]*-mono[\w-]*|[\w-]*uid|[\w-]*digest|[\w-]*-key|[\w-]*hex-input|[\w-]*secret[\w-]*|[\w-]*rule-id|[\w-]*emoji-path|[\w-]*pii-value|[\w-]*slug|[\w-]*-version|[\w-]*-hosts)(\s|$)/;

  // 1. Text inventory
  const text = [];
  const seen = new Set();
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  for (let node = walker.nextNode(); node; node = walker.nextNode()) {
    const value = node.nodeValue.replace(/\s+/g, ' ').trim();
    if (!value) continue;
    const el = node.parentElement;
    if (!el || seen.has(el)) continue;
    if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE', 'OPTION'].includes(el.tagName)) continue;
    seen.add(el);
    if (!visible(el)) continue;
    const range = document.createRange(); range.selectNodeContents(node);
    const rr = range.getBoundingClientRect();
    if (rr.width < 1 || rr.height < 1) continue;
    const cs = getComputedStyle(el);
    const bg = background(el);
    let fg = parse(cs.color);
    const op = opacityChain(el);
    if (fg) fg = over([fg[0], fg[1], fg[2], fg[3] * op], bg.color);
    const size = parseFloat(cs.fontSize);
    const weight = parseInt(cs.fontWeight, 10);
    const large = size >= 24 || (size >= 18.66 && weight >= 700);
    text.push({
      path: path(el), text: value.slice(0, 60), size, weight, family: cs.fontFamily.split(',')[0].replace(/"/g, '').trim(),
      transform: cs.textTransform, spacing: cs.letterSpacing, lineHeight: cs.lineHeight, style: cs.fontStyle,
      ratio: fg ? +ratio(fg, bg.color).toFixed(2) : null, apca: fg ? +apca(fg, bg.color).toFixed(1) : null,
      large, image: bg.image, inCode: !!el.closest('code, pre, kbd, samp') || [el, el.parentElement].some(n => n && identifier.test(n.getAttribute('class') || '')),
      ariaHidden: !!el.closest('[aria-hidden="true"]'),
    });
  }
  // 2. Targets + 3. label centering
  const targets = [];
  const centering = [];
  const sel = 'a[href], button, input:not([type=hidden]), select, textarea, summary, [role=button], [role=tab], [role=menuitem], [tabindex]:not([tabindex="-1"]), label:has(> input[type=checkbox]), label:has(> input[type=radio])';
  for (const el of document.querySelectorAll(sel)) {
    if (!visible(el)) continue;
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    const inlineLink = el.tagName === 'A' && cs.display === 'inline' && !!el.closest('p, li, td, dd, .formatted-content, .post-body');
    targets.push({ path: path(el), name: (el.getAttribute('aria-label') || el.textContent || el.value || '').replace(/\s+/g, ' ').trim().slice(0, 40), w: +r.width.toFixed(1), h: +r.height.toFixed(1), inlineLink, tag: el.tagName.toLowerCase(), type: el.getAttribute('type') || '' });
    const isButtonish = el.tagName === 'BUTTON' || el.getAttribute('role') === 'button' || /\b(btn|button|chip|pill|tab)\b/i.test(el.className || '') || (el.tagName === 'A' && cs.display !== 'inline' && parseFloat(cs.borderTopWidth) > 0);
    if (isButtonish && el.textContent.trim()) {
      const range = document.createRange();
      const tw = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
      let first = null, last = null;
      for (let t = tw.nextNode(); t; t = tw.nextNode()) { if (t.nodeValue.trim() && visible(t.parentElement)) { if (!first) first = t; last = t; } }
      if (first) {
        range.setStart(first, 0); range.setEnd(last, last.nodeValue.length);
        const rects = [...range.getClientRects()].filter(x => x.width > 0 && x.height > 0);
        if (rects.length) {
          const top = Math.min(...rects.map(x => x.top)), bottom = Math.max(...rects.map(x => x.bottom));
          const pt = parseFloat(cs.paddingTop), pb = parseFloat(cs.paddingBottom), bt = parseFloat(cs.borderTopWidth), bb = parseFloat(cs.borderBottomWidth);
          const boxTop = r.top + bt + pt, boxBottom = r.bottom - bb - pb;
          const offset = ((top + bottom) / 2) - ((r.top + r.bottom) / 2);
          centering.push({ path: path(el), name: el.textContent.replace(/\s+/g, ' ').trim().slice(0, 30), offset: +offset.toFixed(1), h: +r.height.toFixed(1), textH: +(bottom - top).toFixed(1), lineHeight: cs.lineHeight, display: cs.display, align: cs.alignItems, pt, pb });
        }
      }
    }
  }
  // 4. Prose measure
  const prose = [];
  for (const el of document.querySelectorAll('.formatted-content, .post-body, .prose, article p')) {
    if (!visible(el)) continue;
    const cs = getComputedStyle(el);
    const probe = document.createElement('span'); probe.textContent = '0'.repeat(10); probe.style.cssText = '';
    probe.setAttribute('aria-hidden', 'true');
    el.appendChild(probe); const zw = probe.getBoundingClientRect().width / 10; probe.remove();
    const width = el.getBoundingClientRect().width - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
    prose.push({ path: path(el), size: parseFloat(cs.fontSize), lineHeight: cs.lineHeight, ratio: +(parseFloat(cs.lineHeight) / parseFloat(cs.fontSize)).toFixed(2), widthPx: Math.round(width), ch: zw ? Math.round(width / zw) : null, maxWidth: cs.maxWidth, family: cs.fontFamily.split(',')[0] });
    if (prose.length > 6) break;
  }
  const families = {};
  for (const t of text) families[t.family] = (families[t.family] || 0) + 1;
  return { url: location.pathname + location.search, theme: document.documentElement.dataset.theme, width: innerWidth, scrollWidth: document.documentElement.scrollWidth, text, targets, centering, prose, families };
}
