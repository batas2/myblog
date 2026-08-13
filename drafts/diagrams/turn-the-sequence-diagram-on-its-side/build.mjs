/**
 * Projects model.mjs into the three pictures the post uses, plus editable draw.io sources.
 *
 *   node build.mjs
 *
 * There is no drawio CLI on this machine and the post needs the .drawio committed anyway (site
 * policy: an exported PNG you cannot edit is a dead end). So the geometry is authored once here and
 * emitted twice, as SVG for the render and as mxCells for the editor. One source, no drift.
 *
 * The PNG is produced by screenshotting the SVG in Chrome, because Inter has to be the real Inter:
 * the site ships it as a woff2 subset and nothing else on this box has it installed. A tiny static
 * server roots the page at the repo so /assets/fonts.css resolves the way it does in production.
 */
import { chromium } from '/home/user/code/stemma/src/Stemma.Web.Client/node_modules/playwright/index.mjs';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { FONT, THEMES, palette, tone, PARTICIPANTS, STEPS, EDGES, BAND, MESSAGES, NOTE, CROSSINGS, COMPARISON, SD_NAME } from './model.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const REPO = path.resolve(HERE, '../../..');
const SLUG = 'turn-the-sequence-diagram-on-its-side';
const OUT_IMG = path.join(REPO, `assets/img/posts/${SLUG}`);

const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
/** draw.io keeps labels in an XML attribute, so quotes and newlines both need escaping. */
const mxLabel = s => esc(s).replace(/"/g, '&quot;').replace(/\n/g, '&#10;');

/** The palette every projection is currently drawing with. Set once per render pass. */
let C = palette('light');
let THEME = 'light';
const T = name => tone(THEME, name);
/** A marker id has to be unique per colour, and the colours change per theme. */
const mk = (kind, name) => `${kind}-${THEME}-${name}`;

// ---------------------------------------------------------------------------- shared SVG plumbing

/** One arrowhead marker per colour and fill: SVG markers do not inherit the path's stroke. */
function markers() {
  const out = [];
  for (const name of ['neutral', 'accent', 'warn', 'person']) {
    const col = T(name).stroke;
    out.push(`<marker id="${mk('a', name)}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="9" markerHeight="9" orient="auto-start-reverse" markerUnits="userSpaceOnUse"><path d="M0,0 L10,5 L0,10 z" fill="${col}"/></marker>`);
    out.push(`<marker id="${mk('o', name)}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="10" markerHeight="10" orient="auto-start-reverse" markerUnits="userSpaceOnUse"><path d="M0,0 L10,5 L0,10" fill="none" stroke="${col}" stroke-width="1.5"/></marker>`);
  }
  return out.join('\n    ');
}

const svgDoc = (w, h, body) => `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" font-family='${FONT}'>
  <defs>
    ${markers()}
  </defs>
${body}
</svg>`;

function text(x, y, s, { size = 11, fill = null, weight = 400, anchor = 'start', ls = 0 } = {}) {
  const sp = ls ? ` letter-spacing="${ls}"` : '';
  return `<text x="${x}" y="${y}" font-size="${size}" fill="${fill ?? C.neutral}" font-weight="${weight}" text-anchor="${anchor}"${sp}>${esc(s)}</text>`;
}

const lines = (x, y, arr, o = {}) =>
  arr.map((s, i) => text(x, y + i * (o.lh ?? 12.5), s, { size: o.size ?? 10, fill: o.fill })).join('\n');

// ------------------------------------------------------------------- projection 1: UML sequence

const A = { w: 976, h: 452, headW: 132, headY: 44, headH: 36, top: 96, bottom: 416 };
A.x = PARTICIPANTS.map((_, i) => Math.round(38 + (A.w - 76) * (i + 0.5) / PARTICIPANTS.length));
const ax = id => A.x[PARTICIPANTS.findIndex(p => p.id === id)];

const MSG_Y = [120, 158, 196, 238, 276, 318, 356, 394];
const ACTIVATIONS = [
  { id: 'checkout', from: 120, to: 362 },
  { id: 'payments', from: 158, to: 196 },
  { id: 'inventory', from: 238, to: 276 },
  { id: 'notify', from: 318, to: 400 },
];

function sequenceSvg() {
  const b = [];
  const n = C.stroke;   // lifelines, frames and boxes recede; the labels carry the weight

  // The interaction frame, which is the bit everyone leaves off and a purist asks for first.
  b.push(`<rect x="8" y="8" width="960" height="430" fill="none" stroke="${C.rule}" stroke-width="1.4"/>`);
  b.push(`<path d="M 8 8 H 140 L 156 24 V 34 H 8 Z" fill="none" stroke="${C.rule}" stroke-width="1.4"/>`);
  b.push(text(18, 26, SD_NAME, { size: 11, weight: 600 }));

  for (const [i, p] of PARTICIPANTS.entries()) {
    const x = A.x[i];
    if (p.actor) {
      // A real actor glyph. The header row still has to line up, so the figure sits in the band.
      const pc = T('person').stroke;
      b.push(`<g stroke="${pc}" stroke-width="1.5" fill="none"><circle cx="${x}" cy="${A.headY + 6}" r="6"/><path d="M ${x} ${A.headY + 12} V ${A.headY + 26} M ${x - 10} ${A.headY + 17} H ${x + 10} M ${x} ${A.headY + 26} L ${x - 8} ${A.headY + 36} M ${x} ${A.headY + 26} L ${x + 8} ${A.headY + 36}"/></g>`);
      b.push(text(x, A.headY + 50, p.name, { size: 12.5, weight: 600, anchor: 'middle', fill: pc }));
    } else {
      b.push(`<rect x="${x - A.headW / 2}" y="${A.headY}" width="${A.headW}" height="${A.headH}" rx="4" fill="${C.fillNeutral}" stroke="${n}" stroke-width="1.6"/>`);
      b.push(text(x, A.headY + 23, p.name, { size: 12.5, weight: 600, anchor: 'middle', fill: C.strong }));
    }
    b.push(`<line x1="${x}" y1="${A.top}" x2="${x}" y2="${A.bottom}" stroke="${n}" stroke-width="1.2" stroke-dasharray="5 5" opacity="0.75"/>`);
  }
  for (const a of ACTIVATIONS) {
    b.push(`<rect x="${ax(a.id) - 5}" y="${a.from}" width="10" height="${a.to - a.from}" fill="${C.fillNeutral}" stroke="${n}" stroke-width="1.4"/>`);
  }
  for (const [i, m] of MESSAGES.entries()) {
    const y = MSG_Y[i], x1 = ax(m.from), x2 = ax(m.to), dir = x2 > x1 ? 1 : -1;
    const sx = x1 + dir * 5, ex = x2 - dir * 5;
    // Filled head only for a synchronous call. Returns and events get an open head, per the spec.
    const dash = m.style === 'return' ? ' stroke-dasharray="5 4"' : '';
    const head = m.style === 'call' ? mk('a', 'neutral') : mk('o', 'neutral');
    b.push(`<line x1="${sx}" y1="${y}" x2="${ex}" y2="${y}" stroke="${n}" stroke-width="1.6"${dash} marker-end="url(#${head})"/>`);
    b.push(text((sx + ex) / 2, y - 7, m.label, { size: 11, anchor: 'middle' }));
  }
  return svgDoc(A.w, A.h, b.join('\n'));
}

// -------------------------------------------------------------------- projection 2: the lane flow

const B = {
  w: 976, gutter: 126, laneY0: 8, laneH: 92,
  boxX0: 134, boxPitch: 139, boxW: 128, boxH: 66,
};
const laneIdx = id => PARTICIPANTS.findIndex(p => p.id === id);
const laneY = id => B.laneY0 + laneIdx(id) * B.laneH;
const colX = i => B.boxX0 + i * B.boxPitch;
const laneBottom = B.laneY0 + PARTICIPANTS.length * B.laneH;
const bandY = laneY(BAND.at) + 78;

function laneSvg() {
  const b = [];
  const box = {};
  for (const s of STEPS) box[s.id] = { x: colX(s.col), y: laneY(s.at) + 8, col: s.col, tone: s.tone };

  // Zebra banding instead of solid lanes: 7% neutral reads as a band on both themes, and nothing in
  // it carries meaning the labels do not also carry.
  for (const [i, p] of PARTICIPANTS.entries()) {
    const y = B.laneY0 + i * B.laneH;
    if (i % 2 === 1) b.push(`<rect x="0" y="${y}" width="${B.w}" height="${B.laneH}" fill="${C.zebra}"/>`);
    b.push(text(8, y + (p.team ? 38 : 50), p.name, { size: 12.5, weight: 600, fill: p.actor ? T('person').stroke : C.strong }));
    if (p.team) b.push(text(8, y + 54, p.team.toUpperCase(), { size: 8.5, ls: 0.8 }));
  }
  b.push(`<line x1="${B.gutter}" y1="${B.laneY0}" x2="${B.gutter}" y2="${laneBottom}" stroke="${C.rule}" stroke-width="1.2"/>`);
  b.push(`<line x1="0" y1="${laneBottom}" x2="${B.w}" y2="${laneBottom}" stroke="${C.rule}" stroke-width="1.2"/>`);

  // Directory holds the request open across the flow. This is the lane answer to the activation
  // bar: it shows THAT control is held, where the sequence diagram shows exactly WHEN it returns.
  const bx1 = colX(BAND.fromCol), bx2 = colX(BAND.toCol) + B.boxW;
  b.push(`<rect x="${bx1}" y="${bandY}" width="${bx2 - bx1}" height="5" rx="2.5" fill="${C.band}"/>`);
  // Label parks in the stretch of the Checkout lane that has no step box in it, at column 4.
  b.push(text(colX(4) + 2, bandY - 5, 'Directory holds the request open', { size: 9, ls: 0.3 }));

  for (const s of STEPS) {
    const tn = T(s.tone), g = box[s.id];
    b.push(`<rect x="${g.x}" y="${g.y}" width="${B.boxW}" height="${B.boxH}" rx="5" fill="${tn.fill}" stroke="${tn.stroke}" stroke-width="${s.tone === 'neutral' ? 1.6 : 2.4}"/>`);
    b.push(text(g.x + 8, g.y + 19, s.title, { size: 11, weight: 600, fill: tn.text }));
    b.push(lines(g.x + 8, g.y + 35, s.detail, { fill: tn.text }));
  }

  for (const e of EDGES) {
    const a = box[e.from], z = box[e.to];
    const col = T(e.tone ?? 'neutral').stroke;
    const dash = e.kind === 'command' ? '' : ' stroke-dasharray="6 4"';
    const sw = e.tone === 'accent' ? 2.4 : 1.6;
    const head = mk('a', e.tone ?? 'neutral');
    const ay = a.y + B.boxH / 2, zy = z.y + B.boxH / 2;
    const mx = a.x + B.boxW + (z.x - a.x - B.boxW) / 2;
    let d;
    if (e.via) {
      // Up to the orchestrator's band, along it, then down into the target lane. The event leaves
      // Checkout, not Inventory, and the path says so.
      const cx = z.x + B.boxW / 2;
      d = `M ${a.x + B.boxW} ${ay} H ${mx} V ${bandY + 2.5} H ${cx} V ${z.y - 2}`;
      b.push(`<path d="${d}" fill="none" stroke="${col}" stroke-width="${sw}"${dash} marker-end="url(#${head})"/>`);
      b.push(text(cx + 8, (bandY + z.y) / 2, e.label, { size: 9.5, weight: 600, fill: col }));
      continue;
    }
    d = `M ${a.x + B.boxW} ${ay} H ${mx} V ${zy} H ${z.x - 2}`;
    b.push(`<path d="${d}" fill="none" stroke="${col}" stroke-width="${sw}"${dash} marker-end="url(#${head})"/>`);
  }

  // The count the notation exists to produce, printed where the reader can check it.
  b.push(text(B.w - 8, B.laneY0 + 22, CROSSINGS, { size: 10.5, weight: 600, anchor: 'end', fill: C.strong }));

  // The rationale, on the picture.
  const ny = laneBottom + 22;
  b.push(`<rect x="${B.boxX0}" y="${ny}" width="560" height="94" rx="5" fill="${T('warn').fill}" stroke="${C.warn}" stroke-width="1.6"/>`);
  b.push(text(B.boxX0 + 12, ny + 22, NOTE.title, { size: 11.5, weight: 600, fill: C.warn }));
  b.push(lines(B.boxX0 + 12, ny + 42, NOTE.body, { fill: C.warn, size: 10, lh: 15 }));

  // Legend, because nothing here is encoded in colour alone and the reader should be able to check.
  const lx = 726, ly = ny + 16;
  [['solid', 'command, someone waits', C.neutral, ''],
   ['dashed', 'return or event', C.neutral, ' stroke-dasharray="6 4"'],
   ['star', '★ the only new build', C.accent, ''],
   ['warn', 'where it can hurt you', C.warn, '']].forEach(([kind, label, col, dash], i) => {
    const y = ly + i * 19;
    if (kind === 'solid' || kind === 'dashed') {
      b.push(`<line x1="${lx}" y1="${y}" x2="${lx + 24}" y2="${y}" stroke="${col}" stroke-width="1.6"${dash} marker-end="url(${'#' + mk('a', 'neutral')})"/>`);
    } else {
      b.push(`<rect x="${lx}" y="${y - 6}" width="24" height="12" rx="3" fill="${kind === 'star' ? T('accent').fill : T('warn').fill}" stroke="${col}" stroke-width="2.4"/>`);
    }
    b.push(text(lx + 34, y + 4, label, { size: 10, fill: kind === 'star' || kind === 'warn' ? col : C.neutral }));
  });

  return { svg: svgDoc(B.w, ny + 120, b.join('\n')), h: ny + 120 };
}

// ---------------------------------------------------------------- projection 3: the scorecard

const C3 = { w: 976, labelX: 24, umlX: 700, laneX: 872, rowH: 27, groupGap: 46, top: 56 };

/** full = carries it, half = carries it badly or at a price, none = nowhere to put it. */
function token(x, y, state, col) {
  const r = 7;
  if (state === 'none') return `<circle cx="${x}" cy="${y}" r="${r}" fill="none" stroke="${col}" stroke-width="1.4" opacity="0.55" stroke-dasharray="2.6 2.4"/>`;
  const ring = `<circle cx="${x}" cy="${y}" r="${r}" fill="none" stroke="${col}" stroke-width="1.6"/>`;
  if (state === 'full') return ring + `<circle cx="${x}" cy="${y}" r="${r - 2.4}" fill="${col}"/>`;
  return ring + `<path d="M ${x} ${y - r + 2.4} A ${r - 2.4} ${r - 2.4} 0 0 0 ${x} ${y + r - 2.4} Z" fill="${col}"/>`;
}

function comparisonSvg() {
  const b = [];
  let y = C3.top;
  b.push(text(C3.umlX, 26, 'On lifelines', { size: 11.5, weight: 600, anchor: 'middle', fill: C.strong }));
  b.push(text(C3.laneX, 26, 'In bands', { size: 11.5, weight: 600, anchor: 'middle', fill: C.accent }));
  b.push(`<line x1="${C3.labelX}" y1="36" x2="${C3.w - 24}" y2="36" stroke="${C.rule}" stroke-width="1.2"/>`);

  for (const [gi, g] of COMPARISON.entries()) {
    if (gi) y += C3.groupGap - C3.rowH;
    b.push(text(C3.labelX, y + 12, g.group.toUpperCase(), { size: 9, ls: 1, weight: 600 }));
    y += 31;
    for (const r of g.rows) {
      b.push(text(C3.labelX, y + 4, r.label, { size: 11.5, fill: C.strong }));
      b.push(token(C3.umlX, y, r.uml, C.neutral));
      b.push(token(C3.laneX, y, r.lane, r.lane === 'none' ? C.neutral : C.accent));
      y += C3.rowH;
    }
  }
  y += 6;
  b.push(`<line x1="${C3.labelX}" y1="${y}" x2="${C3.w - 24}" y2="${y}" stroke="${C.rule}" stroke-width="1.2"/>`);
  [['full', 'on the page'], ['half', 'only at a price'], ['none', 'nowhere to put it']].forEach(([st, label], i) => {
    const kx = C3.labelX + 12 + i * 178;
    b.push(token(kx, y + 22, st, C.neutral));
    b.push(text(kx + 16, y + 26, label, { size: 10 }));
  });
  return { svg: svgDoc(C3.w, y + 42, b.join('\n')), h: y + 42 };
}

// ------------------------------------------------------------------- projection 4: the hero

/**
 * The post's emblem: the before and the after, with the rotation between them.
 *
 * Three earlier attempts are worth recording, because each failed for a reason that is easy to walk
 * back into. Splitting lifelines and bands across the left and right edges read well at full width
 * and lost its meaning to every crop. Replacing the words with grey bars survived the crop and then
 * looked exactly like a skeleton loader. Drawing a faithful miniature of the notation was honest and
 * had no tension in it at all: four identical boxes on a diagonal and a lot of dead canvas.
 *
 * The fix for the crop is to stack, not to abstract. A 1.6:1 thumbnail crop of a 1.9:1 image only
 * trims the sides, so a composition arranged top-to-bottom keeps both halves of the argument. The
 * fix for the boredom is contrast of mass: the top is thin, grey and cramped, the bottom is wide,
 * coloured and full, and the eye reads the trade before it reads a word.
 *
 * One file serves both grounds, so every hue is dual-safe and every fill is a low-alpha tint.
 */
function heroSvg() {
  const W = 1200, H = 630, b = [];
  const n = C.stroke, a = C.accent;

  // ---- before: four lifelines, and labels that have nowhere to go
  const lf = [420, 540, 660, 780];
  for (const x of lf) {
    b.push(`<rect x="${x - 38}" y="56" width="76" height="24" rx="4" fill="none" stroke="${n}" stroke-width="1.4"/>`);
    b.push(`<line x1="${x}" y1="84" x2="${x}" y2="222" stroke="${n}" stroke-width="1.1" stroke-dasharray="4 4" opacity="0.8"/>`);
  }
  [[0, 1, 112, 'addPartner(name)', 0], [1, 2, 142, 'resolve(candidate)', 0], [2, 1, 172, 'match | mint', 1], [1, 3, 202, 'OrgLinked', 0]].forEach(([f, t, y, label, dash]) => {
    const x1 = lf[f], x2 = lf[t], d = x2 > x1 ? 1 : -1;
    b.push(`<line x1="${x1 + d * 3}" y1="${y}" x2="${x2 - d * 3}" y2="${y}" stroke="${n}" stroke-width="1.4"${dash ? ' stroke-dasharray="4 3"' : ''} marker-end="url(${'#' + mk('a', 'neutral')})"/>`);
    b.push(text((x1 + x2) / 2, y - 6, label, { size: 10, anchor: 'middle', fill: n }));
  });
  b.push(text(110, 132, 'a line', { size: 22, weight: 600, fill: C.strong }));
  b.push(text(110, 156, 'four words fit on it', { size: 11.5, fill: C.faint }));

  // ---- the rotation, dead centre between the two halves
  b.push(`<path d="M 566 268 a 34 34 0 0 1 68 0" fill="none" stroke="${a}" stroke-width="3.2" marker-end="url(${'#' + mk('a', 'accent')})"/>`);

  // ---- after: two bands with room in them. Same caption position as the half above, so the two
  // read as a pair rather than as two unrelated drawings.
  b.push(text(110, 372, 'a band', { size: 22, weight: 600, fill: a }));
  b.push(text(110, 396, 'with the reason in it', { size: 11.5, fill: C.faint }));

  const LX = 262, RX = 1090, GUT = 452;
  const lanes = [
    { y: 316, name: 'Directory', team: 'DISCOVERY SQUAD', tone: 'neutral' },
    { y: 446, name: 'Resolver', team: 'IDENTITY SQUAD', tone: 'warn' },
  ];
  const steps = [
    { x: 480, title: '2  Ask who this is', body: ['resolve(candidate).', 'Directory blocks here.', 'No id in 40% of them'] },
    { x: 800, title: '3  Match, or mint', body: ['Under the threshold we', 'mint a second company', 'that never merges back.'] },
  ];
  lanes.forEach((l, i) => {
    const t = T(l.tone);
    if (i === 1) b.push(`<rect x="${LX}" y="${l.y}" width="${RX - LX}" height="126" fill="${C.zebra}"/>`);
    b.push(text(LX + 16, l.y + 56, l.name, { size: 16, weight: 600, fill: l.tone === 'neutral' ? C.strong : t.stroke }));
    b.push(text(LX + 16, l.y + 76, l.team, { size: 10, ls: 0.9, fill: C.faint }));
    const s = steps[i], y = l.y + 14;
    b.push(`<rect x="${s.x}" y="${y}" width="290" height="98" rx="9" fill="${t.fill}" stroke="${t.stroke}" stroke-width="${i ? 2.6 : 1.8}"/>`);
    b.push(text(s.x + 16, y + 28, s.title, { size: 14.5, weight: 600, fill: t.stroke }));
    b.push(lines(s.x + 16, y + 50, s.body, { size: 12, lh: 17, fill: t.stroke }));
  });
  b.push(`<line x1="${GUT}" y1="316" x2="${GUT}" y2="572" stroke="${C.rule}" stroke-width="1.3"/>`);
  b.push(`<path d="M 770 379 H 785 V 509 H 794" fill="none" stroke="${C.stroke}" stroke-width="1.8" marker-end="url(${'#' + mk('a', 'neutral')})"/>`);

  return { svg: svgDoc(W, H, b.join('\n')), w: W, h: H };
}

// -------------------------------------------------------------------------- draw.io projections

const RECT = 'rounded=0;whiteSpace=wrap;html=1;fillColor=none;fontFamily=Inter;';
const SIDE = 'exitX=1;exitY=0.5;exitDx=0;exitDy=0;entryX=0;entryY=0.5;entryDx=0;entryDy=0;';
let uid = 0;
const cell = (value, style, x, y, w, h) =>
  `        <mxCell id="n${++uid}" value="${mxLabel(value)}" style="${style}" vertex="1" parent="1"><mxGeometry x="${x}" y="${y}" width="${w}" height="${h}" as="geometry"/></mxCell>`;
const edgeAt = (value, style, x1, y1, x2, y2) =>
  `        <mxCell id="e${++uid}" value="${mxLabel(value)}" style="${style}" edge="1" parent="1"><mxGeometry relative="1" as="geometry"><mxPoint x="${x1}" y="${y1}" as="sourcePoint"/><mxPoint x="${x2}" y="${y2}" as="targetPoint"/></mxGeometry></mxCell>`;
const edgeIds = (value, style, src, tgt) =>
  `        <mxCell id="e${++uid}" value="${mxLabel(value)}" style="${style}" edge="1" parent="1" source="${src}" target="${tgt}"><mxGeometry relative="1" as="geometry"/></mxCell>`;

const wrap = (name, w, h, body) => `<mxfile host="app.diagrams.net" agent="bfrackowiak.pl diagram-gen" version="24.7.17">
  <diagram name="${name}" id="${name}">
    <mxGraphModel dx="${w}" dy="${h}" grid="0" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="${w}" pageHeight="${h}" background="none" math="0" shadow="0">
      <root>
        <mxCell id="0"/>
        <mxCell id="1" parent="0"/>
${body}
      </root>
    </mxGraphModel>
  </diagram>
</mxfile>
`;

function sequenceDrawio() {
  const c = [];
  c.push(cell(SD_NAME, `${RECT}strokeColor=${C.neutral};fontColor=${C.neutral};fontSize=11;fontStyle=1;align=left;verticalAlign=top;spacingLeft=8;spacingTop=4;`, 8, 8, 960, 430));
  for (const [i, p] of PARTICIPANTS.entries()) {
    const x = A.x[i];
    const style = p.actor
      ? `shape=umlActor;verticalLabelPosition=bottom;verticalAlign=top;html=1;outlineConnect=0;fillColor=none;strokeColor=${C.neutral};fontColor=${C.neutral};fontFamily=Inter;fontSize=12;fontStyle=1;`
      : `${RECT}strokeColor=${C.neutral};fontColor=${C.neutral};fontSize=12;fontStyle=1;`;
    c.push(p.actor
      ? cell(p.name, style, x - 15, A.headY, 30, 40)
      : cell(p.name, style, x - A.headW / 2, A.headY, A.headW, A.headH));
    c.push(edgeAt('', `endArrow=none;html=1;strokeColor=${C.neutral};dashed=1;dashPattern=5 5;strokeWidth=1;`, x, A.top, x, A.bottom));
  }
  for (const a of ACTIVATIONS) c.push(cell('', `${RECT}strokeColor=${C.neutral};`, ax(a.id) - 5, a.from, 10, a.to - a.from));
  for (const [i, m] of MESSAGES.entries()) {
    const y = MSG_Y[i], dir = ax(m.to) > ax(m.from) ? 1 : -1;
    const st = `html=1;rounded=0;strokeColor=${C.neutral};fontColor=${C.neutral};fontSize=11;fontFamily=Inter;strokeWidth=1.6;`
      + (m.style === 'return' ? 'dashed=1;dashPattern=5 4;endArrow=open;endFill=0;' : m.style === 'async' ? 'endArrow=open;endFill=0;' : 'endArrow=block;endFill=1;');
    c.push(edgeAt(m.label, st, ax(m.from) + dir * 5, y, ax(m.to) - dir * 5, y));
  }
  return wrap('same-flow-in-uml', A.w, A.h, c.join('\n'));
}

function laneDrawio(h) {
  const c = [];
  const ids = {};
  for (const [i, p] of PARTICIPANTS.entries()) {
    const y = B.laneY0 + i * B.laneH;
    // One mxCell has one font size, so the two-tier lane label has to be HTML.
    const label = p.team
      ? `<b>${esc(p.name)}</b><br><span style="font-size:9px;letter-spacing:0.6px">${esc(p.team.toUpperCase())}</span>`
      : `<b>${esc(p.name)}</b>`;
    c.push(cell(label, `${RECT}strokeColor=${C.neutral};fontColor=${C.neutral};fontSize=12;align=left;verticalAlign=middle;spacingLeft=8;${i % 2 ? `fillColor=${C.neutral};opacity=7;` : ''}`, 0, y, B.w, B.laneH));
  }
  c.push(cell('Directory holds the request open', `${RECT}strokeColor=none;fontColor=${C.neutral};fontSize=9;align=left;verticalAlign=bottom;`, colX(4), bandY - 18, 220, 16));
  c.push(cell('', `${RECT}fillColor=${C.neutral};strokeColor=none;opacity=50;`, colX(BAND.fromCol), bandY, colX(BAND.toCol) + B.boxW - colX(BAND.fromCol), 5));
  for (const s of STEPS) {
    const col = T(s.tone).stroke;
    ids[s.id] = `n${uid + 1}`;
    c.push(cell(`<b>${esc(s.title)}</b><br>${s.detail.map(esc).join('<br>')}`,
      `rounded=1;arcSize=8;whiteSpace=wrap;html=1;fillColor=none;fontFamily=Inter;strokeColor=${col};fontColor=${col};fontSize=10;align=left;verticalAlign=top;spacingLeft=8;spacingTop=4;strokeWidth=${s.tone === 'neutral' ? 1.6 : 2.4};`,
      colX(s.col), laneY(s.at) + 8, B.boxW, B.boxH));
  }
  for (const e of EDGES) {
    const col = T(e.tone ?? 'neutral').stroke;
    c.push(edgeIds(e.label ?? '',
      `edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;${SIDE}strokeColor=${col};fontColor=${col};fontSize=10;fontFamily=Inter;strokeWidth=${e.tone === 'accent' ? 2.4 : 1.6};endArrow=block;endFill=1;${e.kind === 'command' ? '' : 'dashed=1;dashPattern=6 4;'}`,
      ids[e.from], ids[e.to]));
  }
  c.push(cell(CROSSINGS, `${RECT}strokeColor=none;fontColor=${C.neutral};fontSize=10;fontStyle=1;align=right;`, B.w - 320, B.laneY0 + 8, 312, 18));
  c.push(cell(`<b>${esc(NOTE.title)}</b><br>${esc(NOTE.body.join(' '))}`,
    `rounded=1;arcSize=6;whiteSpace=wrap;html=1;fillColor=none;fontFamily=Inter;strokeColor=${C.warn};fontColor=${C.warn};fontSize=10;align=left;verticalAlign=top;spacingLeft=10;spacingTop=6;spacingRight=10;`,
    B.boxX0, laneBottom + 22, 560, 94));
  return wrap('same-flow-in-lanes', B.w, h, c.join('\n'));
}

function comparisonDrawio(h) {
  const c = [];
  c.push(cell('On lifelines', `${RECT}strokeColor=none;fontColor=${C.neutral};fontSize=11;fontStyle=1;`, C3.umlX - 60, 14, 120, 20));
  c.push(cell('In bands', `${RECT}strokeColor=none;fontColor=${C.accent};fontSize=11;fontStyle=1;`, C3.laneX - 60, 14, 120, 20));
  let y = C3.top;
  const glyph = { full: '●', half: '◐', none: '○' };
  for (const [gi, g] of COMPARISON.entries()) {
    if (gi) y += C3.groupGap - C3.rowH;
    c.push(cell(g.group.toUpperCase(), `${RECT}strokeColor=none;fontColor=${C.neutral};fontSize=9;fontStyle=1;align=left;`, C3.labelX, y, 520, 18));
    y += 31;
    for (const r of g.rows) {
      c.push(cell(r.label, `${RECT}strokeColor=none;fontColor=${C.neutral};fontSize=11;align=left;`, C3.labelX, y - 10, 600, 20));
      c.push(cell(glyph[r.uml], `${RECT}strokeColor=none;fontColor=${C.neutral};fontSize=14;`, C3.umlX - 12, y - 10, 24, 20));
      c.push(cell(glyph[r.lane], `${RECT}strokeColor=none;fontColor=${r.lane === 'none' ? C.neutral : C.accent};fontSize=14;`, C3.laneX - 12, y - 10, 24, 20));
      y += C3.rowH;
    }
  }
  return wrap('what-each-notation-carries', C3.w, h, c.join('\n'));
}

// ------------------------------------------------------------------------------------- rendering

const server = http.createServer((req, res) => {
  const p = path.join(REPO, decodeURIComponent(req.url.split('?')[0]));
  if (!p.startsWith(REPO) || !fs.existsSync(p) || fs.statSync(p).isDirectory()) return res.writeHead(404).end();
  const type = { '.css': 'text/css', '.woff2': 'font/woff2', '.svg': 'image/svg+xml' }[path.extname(p)] || 'application/octet-stream';
  res.writeHead(200, { 'Content-Type': type }).end(fs.readFileSync(p));
});
await new Promise(r => server.listen(0, r));
const port = server.address().port;
const browser = await chromium.launch({ executablePath: '/usr/bin/google-chrome' });

async function shoot(render, name) {
  for (const theme of THEMES) {
    THEME = theme;
    C = palette(theme);
    const { svg, w, h } = render();
    fs.writeFileSync(path.join(HERE, `${name}-${theme}.svg`), svg);
    const page = await browser.newPage({ viewport: { width: w, height: Math.round(h) }, deviceScaleFactor: 2 });
    await page.goto(`http://127.0.0.1:${port}/assets/site.css`); // same origin, so /assets/* resolves
    await page.setContent(
      `<link rel="stylesheet" href="/assets/fonts.css">
       <style>html,body{margin:0;padding:0;background:transparent}</style>${svg}`,
      { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: path.join(OUT_IMG, `${name}-${theme}.png`), omitBackground: true });
    await page.close();
    console.log(`  ${name}-${theme}.png  ${w * 2}x${Math.round(h) * 2}`);
  }
}

fs.mkdirSync(OUT_IMG, { recursive: true });
await shoot(() => ({ svg: sequenceSvg(), w: A.w, h: A.h }), 'same-flow-in-uml');
await shoot(() => { const r = laneSvg(); return { svg: r.svg, w: B.w, h: r.h }; }, 'same-flow-in-lanes');
await shoot(() => { const r = comparisonSvg(); return { svg: r.svg, w: C3.w, h: r.h }; }, 'what-each-notation-carries');

// The hero is one file for both themes, so it takes the dual-theme ink and lives at the post slug
// rather than in the post's figure directory.
THEME = 'dual';
C = palette('dual');
{
  const { svg, w, h } = heroSvg();
  fs.writeFileSync(path.join(HERE, 'hero.svg'), svg);
  const page = await browser.newPage({ viewport: { width: w, height: h }, deviceScaleFactor: 2 });
  await page.goto(`http://127.0.0.1:${port}/assets/site.css`);
  await page.setContent(`<link rel="stylesheet" href="/assets/fonts.css"><style>html,body{margin:0;padding:0;background:transparent}</style>${svg}`, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: path.join(REPO, `assets/img/posts/${SLUG}.png`), omitBackground: true });
  await page.close();
  console.log(`  ${SLUG}.png  ${w * 2}x${h * 2}  (hero, dual-theme)`);
}

// The .drawio sources are edited on draw.io's white canvas, so they carry the light palette.
THEME = 'light';
C = palette('light');
fs.writeFileSync(path.join(HERE, 'same-flow-in-uml.drawio'), sequenceDrawio());
fs.writeFileSync(path.join(HERE, 'same-flow-in-lanes.drawio'), laneDrawio(laneSvg().h));
fs.writeFileSync(path.join(HERE, 'what-each-notation-carries.drawio'), comparisonDrawio(comparisonSvg().h));

await browser.close();
server.close();
console.log('done');
