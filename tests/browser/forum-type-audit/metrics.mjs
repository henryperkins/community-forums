// Summarises a forum-type-audit/run.mjs output directory: the before/after
// figures in docs/evidence/forum-refinement/README.md. Usage: node metrics.mjs <OUT>
import fs from 'node:fs';
import path from 'node:path';
const dir = process.argv[2];
const rows = [];
for (const vp of fs.readdirSync(dir)) { const d = path.join(dir, vp); if (!fs.statSync(d).isDirectory()) continue; for (const f of fs.readdirSync(d)) if (f.endsWith('.json')) rows.push({ vp, name: f.replace('.json', ''), ...JSON.parse(fs.readFileSync(path.join(d, f))) }); }
const text = rows.flatMap(r => r.text.filter(t => !t.ariaHidden).map(t => ({ ...t, page: r.vp + '/' + r.name })));
const uniq = (arr) => new Set(arr.map(t => t.path)).size;
const small = text.filter(t => t.size < 13.9);
const mono = text.filter(t => /mono|consol|courier|menlo/i.test(t.family) && !t.inCode);
const caps = text.filter(t => t.transform === 'uppercase');
const track = text.filter(t => t.spacing !== 'normal' && parseFloat(t.spacing) > 0);
const center = rows.flatMap(r => r.centering).filter(c => Math.abs(c.offset) > 1.5);
const sizes = small.map(t => t.size);
const out = {
  states: rows.length,
  textElements: text.length,
  belowFloor: { instances: small.length, unique: uniq(small), smallest: sizes.length ? Math.min(...sizes) : null },
  codeFaceOutsideCode: { instances: mono.length, unique: uniq(mono) },
  capitals: { instances: caps.length, unique: uniq(caps) },
  positiveTracking: { instances: track.length, unique: uniq(track) },
  labelsOffCentre: { unique: new Set(center.map(c => c.path)).size, worst: center.length ? Math.max(...center.map(c => Math.abs(c.offset))) : 0 },
  axeViolations: rows.reduce((n, r) => n + r.axe.violations.length, 0),
  axeIncomplete: rows.reduce((acc, r) => { for (const v of r.axe.incomplete) acc[v.id] = (acc[v.id] || 0) + v.count; return acc; }, {}),
  overflow: rows.filter(r => r.scrollWidth > r.width + 1).length,
};
console.log(JSON.stringify(out, null, 1));
