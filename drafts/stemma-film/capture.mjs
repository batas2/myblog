/**
 * Films the real app. One pass per theme; each pass walks a scripted session and hands the
 * frames to the Recorder, which lays them down at the frame budget the film expects.
 *
 *   node capture.mjs <light|dark> [beat …]
 */
import { chromium } from '/home/user/code/verso/src/Stemma.Web.Client/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import { execSync } from 'node:child_process';
import { Recorder } from './recorder.mjs';

const SP = process.env.STEMMA_FILM_WORK || '/tmp/stemma-film';
const THEME = process.argv[2] === 'light' ? 'light' : 'dark';
const ONLY = process.argv.slice(3);
const want = (b) => ONLY.length === 0 || ONLY.includes(b);

// Film seconds each beat occupies × 30fps — the capture is resampled onto exactly this many.
const BUDGET = { arch: 990, book: 615, expo: 150, rept: 225 };

const OUT = `${SP}/film/seq-${THEME}`;
fs.mkdirSync(OUT, { recursive: true });
fs.mkdirSync(`${SP}/shots`, { recursive: true });   // the beat drops a still at each checkpoint

const AURORA = `${SP}/ws/AuroraRail`;
// The topbar prints parent/name, so the last two segments are on camera for the whole beat.
const SCRATCH = `${SP}/ws/${THEME}/stemma/OrderPlatform`;

const browser = await chromium.launch({
  headless: true,
  executablePath: '/usr/bin/google-chrome',
  args: ['--hide-scrollbars', '--disable-lcd-text'],
});
// CDP screencast hands back CSS pixels whatever the device scale is, so the viewport IS the frame
// size. 1440×810 is the deliberate choice: the film draws these plates at 1920, and that 1.33×
// upscale is what makes the app's own 12px chrome legible at a distance.
const ctx = await browser.newContext({
  viewport: { width: 1440, height: 810 },
  deviceScaleFactor: 1,
  colorScheme: THEME,
  acceptDownloads: true,
});
await ctx.addInitScript((t) => {
  localStorage.setItem('stemma.theme', t);
  localStorage.removeItem('stemma.sidebar.collapsedSections');
}, THEME);

const page = await ctx.newPage();
page.on('pageerror', (e) => console.log('  [pageerror]', String(e).slice(0, 160)));

const log = (...a) => console.log(`[${THEME}]`, ...a);
const pause = (ms) => page.waitForTimeout(ms);

async function openWorkspace(root, init = false) {
  await page.evaluate(async ({ root, init }) => {
    await fetch(init ? '/api/workspace/init' : '/api/workspace/open', {
      method: 'POST', headers: { 'content-type': 'application/json' },
      body: JSON.stringify(init ? { rootPath: root, name: 'Order Platform' } : { rootPath: root }),
    });
  }, { root, init });
}

/** The in-place title editor React Flow opens on F2. */
const nodeEditor = () => page.locator('.react-flow__node input').first();

const fitView = () => page.getByRole('button', { name: /fit view/i }).first().click();

async function addAndName(tile, name, typeDelay = 45) {
  await page.getByLabel(`Add a ${tile}`).click();
  await page.waitForTimeout(1500);
  await page.keyboard.press('F2');
  const inp = nodeEditor();
  await inp.waitFor({ state: 'visible', timeout: 8000 });
  await inp.click();
  await inp.press('ControlOrMeta+a');
  await inp.pressSequentially(name, { delay: typeDelay });
  await pause(220);
  await inp.press('Enter');
  await pause(1100);
}

/** The Context <select> in the Properties blade re-parents a module. */
async function assignContext(name) {
  const sel = page.locator('select').filter({ hasText: 'Ordering' }).first();
  await sel.selectOption({ label: name });
  await pause(1400);
}

async function connect(fromLabel, toLabel) {
  const from = page.locator('.react-flow__node', { hasText: fromLabel }).last();
  const to = page.locator('.react-flow__node', { hasText: toLabel }).last();
  const a = await from.boundingBox();
  const b = await to.boundingBox();
  if (!a || !b) { log('  connect: node not found', fromLabel, toLabel); return; }
  // right-middle dot of the source → left-middle dot of the target
  await page.mouse.move(a.x + a.width, a.y + a.height / 2, { steps: 6 });
  await page.mouse.down();
  await page.mouse.move(b.x + b.width / 2, b.y + b.height / 2, { steps: 26 });
  await page.mouse.move(b.x, b.y + b.height / 2, { steps: 8 });
  await page.mouse.up();
  await pause(1600);
}

// ───────────────────────────────────────────────────────────── beat: arch

async function beatArch() {
  fs.rmSync(SCRATCH, { recursive: true, force: true });
  await page.goto('http://localhost:5050/');
  await pause(900);
  await openWorkspace(SCRATCH, true);
  await page.reload();
  await page.waitForSelector('.react-flow', { timeout: 20000 });
  await pause(2500);

  const rec = new Recorder(page);
  await rec.start();
  await pause(900);

  await addAndName('Bounded Context', 'Ordering');
  await addAndName('Bounded Context', 'Payments');
  await addAndName('Bounded Context', 'Fulfilment');

  // Open Properties once; it stays open and mirrors each new selection.
  await page.getByLabel('Properties').click();
  await pause(700);

  for (const [mod, context] of [
    ['Cart', 'Ordering'], ['Checkout', 'Ordering'],
    ['Authorisation', 'Payments'], ['Ledger', 'Payments'],
    ['Dispatch', 'Fulfilment'],
  ]) {
    await addAndName('Module', mod);
    await assignContext(context);
  }

  // Let the layout engine place what we declared.
  await page.getByLabel('Layout & arrange').click();
  await pause(900);
  await page.getByRole('button', { name: /Architectural \(by type\)/ }).click();
  await pause(2400);
  await page.getByLabel('Layout & arrange').click();   // collapse the blade again
  await pause(700);
  await fitView();
  await pause(1400);
  await page.screenshot({ path: `${SP}/shots/arch-${THEME}-laid-out.png` });

  await connect('Checkout', 'Authorisation');
  await connect('Authorisation', 'Ledger');
  await connect('Checkout', 'Dispatch');

  await fitView();
  await pause(2000);
  await page.screenshot({ path: `${SP}/shots/arch-${THEME}-final.png` });

  await rec.stop();
  log('arch', JSON.stringify(rec.write(`${OUT}/arch`, BUDGET.arch, { maxHoldSec: 0.45 })));
}

// ───────────────────────────────────────────────────────────── beat: book

async function beatBook() {
  // The beat ends by typing into a narrative, which writes the sidecar. Reset it so the second
  // theme films the same page the first one did.
  execSync(`git -C ${AURORA} checkout -- Concepts/view-book.stemma.yaml`);
  await page.goto('http://localhost:5050/');
  await pause(900);
  await openWorkspace(AURORA);
  await page.reload();
  await page.waitForSelector('.react-flow', { timeout: 20000 });
  await pause(4000);
  await fitView();
  await pause(1200);

  const rec = new Recorder(page);
  await rec.start();
  await pause(800);

  await page.getByRole('button', { name: /^Views/ }).click();
  await pause(1100);

  const book = page.getByRole('button', { name: /How a Ticket Is Sold/ }).first();
  await book.scrollIntoViewIfNeeded();
  await pause(500);
  await book.click();
  await pause(2600);

  const next = page.getByRole('button', { name: /next page|^Next/i }).first();
  for (let i = 0; i < 3; i++) {
    await next.click();
    await pause(2400);
  }

  // The authored half. Pages come from the model's own views, but the words are written here,
  // in the strip, and land in the sidecar next to the model — so put that on camera.
  const narrative = page.getByLabel('Page narrative');
  await narrative.click();
  await pause(700);
  await narrative.press('End');
  await narrative.pressSequentially(' Say so in the review, not after it.', { delay: 55 });
  await pause(1600);

  await next.click();
  await pause(2400);

  await rec.stop();
  log('book', JSON.stringify(rec.write(`${OUT}/book`, BUDGET.book, { maxHoldSec: 0.6 })));
}

// ───────────────────────────────────────────────── beats: expo + rept

async function beatExport() {
  await page.goto('http://localhost:5050/');
  await pause(900);
  await openWorkspace(AURORA);
  await page.reload();
  await page.waitForSelector('.react-flow', { timeout: 20000 });
  await pause(4000);
  await fitView();
  await pause(1500);

  const rec = new Recorder(page);
  await rec.start();
  await pause(700);

  const dl = page.waitForEvent('download', { timeout: 60000 });
  await page.getByLabel('Export this view').click();
  await pause(1400);
  await page.getByRole('menuitem', { name: /Architecture report/ }).click();
  await pause(3200);

  await rec.stop();
  log('expo', JSON.stringify(rec.write(`${OUT}/expo`, BUDGET.expo, { maxHoldSec: 0.5 })));

  const file = `${SP}/film/report-${THEME}.html`;
  const download = await dl;
  await download.saveAs(file);
  log('report saved', file, fs.statSync(file).size, 'bytes');

  // The artefact itself, opened the way a recipient opens it.
  await page.goto('file://' + file);
  await pause(3500);

  const rec2 = new Recorder(page);
  await rec2.start();
  await pause(900);
  await reportTour();
  await rec2.stop();
  log('rept', JSON.stringify(rec2.write(`${OUT}/rept`, BUDGET.rept, { maxHoldSec: 0.5 })));
}

/** Drive the exported file the way a recipient would: switch audience, change view, read a box. */
async function reportTour() {
  const tap = async (name, wait = 1500) => {
    for (const cand of [page.getByRole('button', { name, exact: false }), page.getByText(name).locator('visible=true')]) {
      if (await cand.count() === 0) continue;
      const ok = await cand.first().click({ timeout: 5000 }).then(() => true).catch(() => false);
      if (ok) { await pause(wait); return; }
    }
    log('  report: could not tap', String(name));
  };
  await page.mouse.move(720, 420, { steps: 18 });
  await pause(700);
  await tap(/^Buy a Journey/, 2100);
  await tap(/^Stakeholder/, 1900);
  await tap(/^Dependencies/, 2100);
  await tap(/^Concerns/, 2000);
}

// ─────────────────────────────────────────────────────────────────────────

try {
  if (want('arch')) await beatArch();
  if (want('book')) await beatBook();
  if (want('expo') || want('rept')) await beatExport();
} finally {
  await browser.close();
}
log('done');
