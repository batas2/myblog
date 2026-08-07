/** Shoots a handful of representative times from both cuts, for eyeballing before the long render. */
import { chromium } from '/home/user/code/verso/src/Stemma.Web.Client/node_modules/playwright/index.mjs';
import fs from 'node:fs';

const SP = process.env.STEMMA_FILM_WORK || '/tmp/stemma-film';
const TIMES = process.argv.slice(2).map(Number);
const STOPS = TIMES.length ? TIMES : [2.5, 9.5, 14.0, 20.0, 30.0, 45.0, 51.5, 60.0, 66.0, 78.0, 86.0, 93.0, 98.0, 104.0, 111.0, 116.0];

fs.mkdirSync(`${SP}/preview`, { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: '/usr/bin/google-chrome', args: ['--hide-scrollbars'] });

for (const theme of ['dark', 'light']) {
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1 });
  page.on('requestfailed', (r) => console.log('[404]', theme, r.url().slice(-60)));
  await page.goto(`http://127.0.0.1:8919/drafts/stemma-film/film-source.html?theme=${theme}`, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  for (const t of STOPS) {
    await page.evaluate((x) => window.render(x), t);
    await page.waitForTimeout(120);
    await page.screenshot({ path: `${SP}/preview/${theme}-${String(t).padStart(5, '0')}.png` });
  }
  await page.close();
}
await browser.close();
console.log('preview frames:', STOPS.join(' '));
