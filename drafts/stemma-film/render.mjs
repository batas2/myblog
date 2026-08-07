/**
 * Walks film-source.html frame by frame and writes a PNG per frame.
 *
 *   node render.mjs <light|dark> [startFrame] [endFrame]
 *
 * The film is deterministic — render(t) sets the whole frame from a time — so this is a pure
 * function of t and reruns produce identical bytes.
 */
import { chromium } from '/home/user/code/verso/src/Stemma.Web.Client/node_modules/playwright/index.mjs';
import fs from 'node:fs';

const SP = process.env.STEMMA_FILM_WORK || '/tmp/stemma-film';
const THEME = process.argv[2] === 'light' ? 'light' : 'dark';
const FPS = 30;
const OUT = `${SP}/film/frames-${THEME}`;
fs.mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch({ headless: true, executablePath: '/usr/bin/google-chrome', args: ['--hide-scrollbars'] });
const page = await browser.newPage({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1 });
page.on('pageerror', (e) => console.log('[pageerror]', String(e).slice(0, 200)));
page.on('requestfailed', (r) => console.log('[404]', r.url().slice(-70)));

await page.goto(`http://127.0.0.1:8919/drafts/stemma-film/film-source.html?theme=${THEME}`, { waitUntil: 'networkidle' });
await page.waitForFunction(() => typeof window.render === 'function');
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(600);

const duration = await page.evaluate(() => window.DURATION);
const total = Math.round(duration * FPS);
const from = Number(process.argv[3] ?? 0);
const to = Number(process.argv[4] ?? total);
console.log(`[${THEME}] ${duration}s → ${total} frames; rendering ${from}…${to}`);

const started = Date.now();
for (let i = from; i < to; i++) {
  await page.evaluate((t) => window.render(t), i / FPS);
  await page.screenshot({ path: `${OUT}/f${String(i + 1).padStart(5, '0')}.png` });
  if (i % 150 === 0 && i > from) {
    const done = i - from, rate = done / ((Date.now() - started) / 1000);
    console.log(`  ${i}/${to}  ${rate.toFixed(1)} fps  eta ${Math.round((to - i) / rate)}s`);
  }
}
await browser.close();
console.log(`[${THEME}] done in ${Math.round((Date.now() - started) / 1000)}s`);
