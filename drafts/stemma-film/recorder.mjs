/**
 * CDP screencast recorder.
 *
 * Chrome emits a frame only when the page actually changes, each stamped with a wall clock.
 * We keep them in memory, then rebuild a fixed-rate timeline from those stamps: real motion
 * keeps its real timing, and dead air (a Roslyn round-trip, a debounce) is capped so it does
 * not eat film seconds.
 */
import fs from 'node:fs';
import path from 'node:path';

export class Recorder {
  constructor(page) {
    this.page = page;
    this.frames = [];      // { t: seconds, buf: Buffer }
    this.session = null;
  }

  async start() {
    this.session = await this.page.context().newCDPSession(this.page);
    this.session.on('Page.screencastFrame', async (ev) => {
      this.frames.push({ t: ev.metadata.timestamp, buf: Buffer.from(ev.data, 'base64') });
      try { await this.session.send('Page.screencastFrameAck', { sessionId: ev.sessionId }); } catch { /* closed */ }
    });
    await this.session.send('Page.startScreencast', {
      format: 'jpeg', quality: 88, maxWidth: 1920, maxHeight: 1080, everyNthFrame: 1,
    });
    // First frame arrives on the next paint; give it one.
    await this.page.waitForTimeout(250);
  }

  async stop() {
    try { await this.session.send('Page.stopScreencast'); } catch { /* closed */ }
    await this.page.waitForTimeout(150);
  }

  /**
   * Write exactly `targetFrames` JPEGs into `dir`, named f0001.jpg…
   *
   * fps          the rate the raw timeline is rebuilt at before compression
   * maxHoldSec   longest a single unchanged frame may occupy in that timeline
   */
  write(dir, targetFrames, { fps = 30, maxHoldSec = 0.5 } = {}) {
    if (this.frames.length === 0) throw new Error('no frames captured');
    fs.rmSync(dir, { recursive: true, force: true });
    fs.mkdirSync(dir, { recursive: true });

    const t0 = this.frames[0].t;
    const t1 = this.frames[this.frames.length - 1].t;
    const span = Math.max(t1 - t0, 1 / fps);

    // 1. fixed-rate timeline of source indices
    const timeline = [];
    let cursor = 0;
    for (let i = 0; i <= Math.round(span * fps); i++) {
      const t = t0 + i / fps;
      while (cursor + 1 < this.frames.length && this.frames[cursor + 1].t <= t) cursor++;
      timeline.push(cursor);
    }

    // 2. cap dead air
    const maxHold = Math.max(1, Math.round(maxHoldSec * fps));
    const trimmed = [];
    let run = 0;
    for (let i = 0; i < timeline.length; i++) {
      if (i > 0 && timeline[i] === timeline[i - 1]) {
        run++;
        if (run >= maxHold) continue;
      } else run = 0;
      trimmed.push(timeline[i]);
    }

    // 3. resample onto the film's frame budget
    const src = trimmed.length ? trimmed : timeline;
    for (let i = 0; i < targetFrames; i++) {
      const p = targetFrames === 1 ? 0 : i / (targetFrames - 1);
      const idx = src[Math.min(src.length - 1, Math.round(p * (src.length - 1)))];
      fs.writeFileSync(path.join(dir, `f${String(i + 1).padStart(4, '0')}.jpg`), this.frames[idx].buf);
    }

    const report = {
      captured: this.frames.length,
      rawSeconds: +span.toFixed(2),
      timelineFrames: timeline.length,
      afterTrim: src.length,
      written: targetFrames,
    };
    this.frames = [];
    return report;
  }
}
