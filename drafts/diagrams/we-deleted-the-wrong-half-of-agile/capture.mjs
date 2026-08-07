/**
 * Shoots the post's diagrams out of the running Stemma app.
 *
 *   ./run.sh --prod   in the Stemma repo first (port 5050), then:
 *   node capture.mjs
 *
 * Two views, two themes, four PNGs. The pictures in the post are projections of the model in
 * ./Reviewability, not drawings of it: change Architecture.cs, re-run this, and the post updates.
 *
 * The site has a dark mode and a screenshot cannot be transparent, so each diagram ships as a
 * light/dark pair and lib/markdown.php picks between them with a <picture> element.
 */
import { chromium } from '/home/user/code/verso/src/Stemma.Web.Client/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const WS = path.join(HERE, 'Reviewability');
const OUT = path.resolve(HERE, '../../../assets/img/posts/we-deleted-the-wrong-half-of-agile');

/**
 * Layout is done by dragging, which is the only thing that sticks.
 *
 * Three cheaper routes were tried first and all lost to the same rule. A view whose members come
 * from ElementIds in C# has never been "placed": the canvas auto-arranges those on open and treats
 * a stored position as stale, so neither Reviewability/stemma.layout.json (which the server does
 * serve back from /api/workspace/layout) nor a pre-seeded localStorage entry survives contact with
 * the first render. Only a real pointer drag registers. So the script drags, exactly as a person
 * would, and the positions it writes are then persisted by the app itself.
 *
 * Grids are given as columns of element ids, laid out in screen space after Fit View.
 */
const GRIDS = {
  // Each question directly above the artifact that answers it. That vertical pairing is the
  // diagram's whole claim, and it is precisely what the auto-layouts would not preserve.
  view_destinations: [
    ['q_blast', 'mod_contracts'],
    ['q_why', 'mod_decision'],
    ['q_claims', 'mod_tests'],
    ['q_scope', 'mod_size'],
  ],
  // Left column is the group that needed a human; the right two are what is left. The four/seven
  // split is the argument, so the right group wraps into two columns rather than running off the
  // bottom of the frame.
  view_two_jobs: [
    ['ctx_reachable', 'cap_pairing', 'cap_onsite', 'cap_collective', 'cap_standup'],
    ['ctx_selfanswering', 'cap_tdd', 'cap_ci', 'cap_small'],
    ['cap_simple', 'cap_refactor', 'cap_standards', 'cap_metaphor'],
  ],
};

const VIEWS = [
  { view: 'Where the question goes', id: 'view_destinations', file: 'where-the-question-goes', viewport: { width: 2300, height: 900 } },
  { view: 'The two jobs XP was doing', id: 'view_two_jobs', file: 'two-jobs', viewport: { width: 1900, height: 1100 } },
];

/** Drag one node by its middle. The middle matters: the edges carry the connection dots. */
async function dragTo(page, id, tx, ty) {
  const node = page.locator(`.react-flow__node[data-id="${id}"]`).first();
  const b = await node.boundingBox();
  if (!b) { console.log(`  ! ${id} not on canvas`); return; }
  const cx = b.x + b.width / 2, cy = b.y + b.height / 2;
  await page.mouse.move(cx, cy);
  await page.mouse.down();
  await page.mouse.move(tx, ty, { steps: 12 });
  await page.mouse.up();
  await page.waitForTimeout(120);
}

/**
 * Lay a view's nodes out as columns.
 *
 * Every node is measured, not just the first: these cards size themselves to their label, so a
 * single cell width taken from one node puts "Why this and not simpler?" underneath its neighbour.
 * Columns are as wide as their widest member and rows as tall as their tallest.
 */
async function placeGrid(page, columns) {
  const size = Object.fromEntries(await page.locator('.react-flow__node').evaluateAll((els) =>
    els.map((e) => { const b = e.getBoundingClientRect(); return [e.getAttribute('data-id'), { w: b.width, h: b.height }]; })));

  const GAP_X = 56, GAP_Y = 30;
  const colW = columns.map((col) => Math.max(...col.map((id) => size[id]?.w ?? 240)));
  const rows = Math.max(...columns.map((c) => c.length));
  const rowH = Array.from({ length: rows }, (_, r) =>
    Math.max(...columns.map((col) => (col[r] ? size[col[r]]?.h ?? 120 : 0))));

  // Offsets of every cell from the grid's top-left, in screen pixels.
  const offset = {};
  let ox = 0;
  for (let c = 0; c < columns.length; c++) {
    let oy = 0;
    for (let r = 0; r < columns[c].length; r++) {
      offset[columns[c][r]] = { dx: ox, dy: oy };
      oy += rowH[r] + GAP_Y;
    }
    ox += colW[c] + GAP_X;
  }

  // React Flow pans the canvas while a node is dragged near an edge, so an absolute screen target
  // computed once is stale by the second drag: the earlier attempt produced a grid that stepped
  // further left on every row. Place one node, then treat it as the origin and re-read it before
  // each subsequent drag, so all targets are expressed relative to something that moved with the
  // canvas rather than to a frame that no longer exists.
  const anchorId = columns[0][0];
  await dragTo(page, anchorId, 300 + colW[0] / 2, 190 + rowH[0] / 2);
  const anchorBox = async () => (await page.locator(`.react-flow__node[data-id="${anchorId}"]`).first().boundingBox());

  // Three passes. The anchor can itself be nudged by the auto-pan of a later drag, so the first
  // pass lands everything roughly and leaves a ragged grid; each later pass only has to move a
  // node a few pixels, which is short enough not to trigger the pan, and it converges. The
  // viewports above are wider than the diagrams need for the same reason: a drag that ends near
  // the right edge pans the canvas mid-gesture and drops that node a whole column out of place.
  for (let pass = 0; pass < 3; pass++) {
    for (const col of columns) {
      for (const nodeId of col) {
        if (nodeId === anchorId) continue;
        const a = await anchorBox();
        const { dx, dy } = offset[nodeId];
        const s = size[nodeId] ?? { w: 240, h: 120 };
        await dragTo(page, nodeId, a.x + dx + s.w / 2, a.y + dy + s.h / 2);
      }
    }
  }
  // A dragged node stays selected and its handles would be in the shot. Deselect by clicking bare
  // canvas below the grid, never with Escape: Escape also closes the active custom view, and the
  // screenshot then quietly contains the whole model instead of the eight elements asked for.
  const pane = await page.locator('.react-flow__pane').first().boundingBox();
  const bottom = await page.evaluate(() => Math.max(
    ...[...document.querySelectorAll('.react-flow__node')].map((n) => n.getBoundingClientRect().bottom)));
  await page.mouse.click(pane.x + pane.width - 80, bottom + 70);
  await page.waitForTimeout(300);
}

const browser = await chromium.launch({ headless: true, executablePath: '/usr/bin/google-chrome', args: ['--hide-scrollbars'] });

for (const theme of ['light', 'dark']) {
  for (const { view, id, file, viewport } of VIEWS) {
    const ctx = await browser.newContext({
      viewport,
      deviceScaleFactor: 2,        // retina, the same reason the draw.io rule says export at 200%
      colorScheme: theme,
    });
    // No layout mode is written, and a view without one defaults to 'custom' — which is what stops
    // an auto-layout from undoing the drags the moment the view opens.
    await ctx.addInitScript((t) => { localStorage.setItem('stemma.theme', t); }, theme);
    const page = await ctx.newPage();
    page.on('pageerror', (e) => console.log('  [pageerror]', String(e).slice(0, 160)));

    await page.goto('http://localhost:5050/');
    await page.waitForTimeout(900);
    await page.evaluate(async (root) => {
      await fetch('/api/workspace/open', {
        method: 'POST', headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ rootPath: root }),
      });
    }, WS);
    await page.reload();
    await page.waitForSelector('.react-flow', { timeout: 30000 });
    await page.waitForTimeout(2500);

    await page.getByRole('button', { name: /^Views/ }).first().click();
    await page.waitForTimeout(600);
    await page.getByText(view, { exact: true }).first().click();
    await page.waitForTimeout(2200);

    // Zoom to 1:1 before dragging, so the grid maths is in the same units the nodes are drawn at.
    await page.getByRole('button', { name: /Fit View/i }).first().click();
    await page.waitForTimeout(1200);
    await placeGrid(page, GRIDS[id]);
    await page.waitForTimeout(800);

    await page.getByRole('button', { name: /Fit View/i }).first().click();
    await page.waitForTimeout(1500);

    // App chrome, not diagram: the minimap, the zoom pad, and whatever toast is still fading out.
    await page.addStyleTag({
      content: `.react-flow__minimap,.react-flow__controls,.react-flow__attribution,
                [data-sonner-toaster],[class*="toast" i]{display:none !important}`,
    });
    await page.waitForTimeout(400);

    // Crop to what the nodes occupy. Fit View frames content in the canvas, which still leaves a
    // margin sized by whichever axis was slack.
    const box = await page.evaluate(() => {
      const ns = [...document.querySelectorAll('.react-flow__node')];
      if (!ns.length) return null;
      const r = ns.map((n) => n.getBoundingClientRect());
      return {
        x: Math.min(...r.map((b) => b.left)), y: Math.min(...r.map((b) => b.top)),
        right: Math.max(...r.map((b) => b.right)), bottom: Math.max(...r.map((b) => b.bottom)),
      };
    });
    const PAD = 26;
    const clip = box && {
      x: Math.max(0, box.x - PAD), y: Math.max(0, box.y - PAD),
      width: Math.min(viewport.width, box.right + PAD) - Math.max(0, box.x - PAD),
      height: Math.min(viewport.height, box.bottom + PAD) - Math.max(0, box.y - PAD),
    };
    // Guard: a stray click or key can drop the active custom view, and the shot then silently
    // contains the entire model. Count the nodes and refuse to pretend that is the diagram.
    const onCanvas = await page.locator('.react-flow__node').count();
    const expected = GRIDS[id].flat().length;
    if (onCanvas !== expected) console.log(`  ! ${file}: ${onCanvas} nodes on canvas, expected ${expected}`);

    await page.screenshot({ path: `${OUT}/${file}-${theme}.png`, clip });
    console.log(`  ${file}-${theme}.png  ${clip ? `${Math.round(clip.width)}x${Math.round(clip.height)}` : 'full'}  ${onCanvas} nodes`);
    await ctx.close();
  }
}

await browser.close();
console.log('done');
