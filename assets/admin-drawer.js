/**
 * admin-drawer.js — the post list is for scanning; this is for deciding.
 *
 * One panel is reused for every row. Thirty-one inline copies of the same four forms is thirty-one
 * copies of the same CSRF token and the same date picker, and only one of them can ever be open.
 */
(function () {
  'use strict';

  var DATA = JSON.parse(document.getElementById('pdata').textContent);
  var drawer = document.getElementById('pdrawer');
  var body = document.getElementById('pd-body');
  var lastFocus = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  /** A POST back to admin.php, built here so the row markup does not carry four hidden forms. */
  function form(action, label, cls, extra) {
    return '<form method="POST" action="/admin.php">' +
      '<input type="hidden" name="action" value="setstatus">' +
      '<input type="hidden" name="csrf" value="' + esc(window.PD_CSRF) + '">' +
      '<input type="hidden" name="slug" value="' + esc(form.slug) + '">' +
      '<input type="hidden" name="status_action" value="' + action + '">' +
      (extra || '') +
      '<button class="mini ' + (cls || '') + '" type="submit">' + label + '</button></form>';
  }

  function render(p) {
    form.slug = p.slug;
    var live = p.status === 'published' && p.built;
    var out = [];

    if (p.thumb) out.push('<img class="pd-hero" src="' + esc(p.thumb) + '" alt="">');
    out.push('<span class="badge b-' + esc(p.status) + '">' + esc(p.status) + '</span>');
    out.push('<h3 id="pd-title">' + esc(p.title) + '</h3>');
    if (p.subtitle) out.push('<p class="pd-sub">' + esc(p.subtitle) + '</p>');

    out.push('<dl class="pd-meta">' +
      '<div><dt>Goes live</dt><dd>' + (p.when ? esc(p.when) : 'not scheduled') +
        (p.countdown ? ' <span class="countdown">' + esc(p.countdown) + '</span>' : '') + '</dd></div>' +
      '<div><dt>Reach</dt><dd>' + (p.reach ? '<span class="badge b-' + esc(p.band) + '">' + esc(p.reach) + '</span>' : 'unscored') + '</dd></div>' +
      '<div><dt>Reads</dt><dd>' + (p.read ? '<span class="rd rd-' + esc(p.read) + '">' + esc(p.read) + '</span>' : '—') + '</dd></div>' +
      '<div><dt>Length</dt><dd>' + Number(p.words).toLocaleString() + ' words · ' + esc(p.minutes) + ' min</dd></div>' +
      '<div><dt>Comments</dt><dd>' + (p.comments || 'none') + '</dd></div>' +
      '</dl>');

    if (p.out && p.out.length) {
      out.push('<h4>Already out</h4><div class="pd-acts row">' + p.out.map(function (o) {
        return '<a class="mini ob-link" href="' + esc(o.url) + '" target="_blank" rel="noopener">' +
               '<span class="ob">' + esc(o.badge) + '</span> ' + esc(o.label) + ' ↗</a>';
      }).join('') + '</div>');
    }

    if (p.tags && p.tags.length) {
      out.push('<p class="pd-tags">' + p.tags.map(function (t) { return '<span>' + esc(t) + '</span>'; }).join('') + '</p>');
    }

    // What you came here to do, before the machinery that changes state.
    out.push('<div class="pd-do">' +
      '<a class="pd-go" href="/review.php?slug=' + encodeURIComponent(p.slug) + '">✎ Comment &amp; edit</a>' +
      '<a class="pd-alt" href="' + (live ? esc(p.path) : '/preview.php?slug=' + encodeURIComponent(p.slug)) +
        '" target="_blank" rel="noopener">' + (live ? 'View live ↗' : 'Preview ↗') + '</a>' +
      '</div>');

    out.push('<h4>Change status</h4><div class="pd-acts">');
    if (p.status !== 'published') out.push(form('publish', '▶ publish now', 'go'));
    if (p.status !== 'draft') out.push(form('draft', '⏸ to draft', 'hold'));
    out.push(form('schedule', '⏱ schedule',
      '', '<input class="dt" type="datetime-local" name="when" required value="' + esc(p.dt) + '">'));
    out.push('</div>');

    if (p.links && p.links.length) {
      out.push('<h4>Tagged links</h4><p class="pd-note">Post these instead of the bare URL, or Acquisition reads every click as direct.</p><div class="pd-acts row">');
      p.links.forEach(function (l) {
        out.push('<button type="button" class="mini cp" data-url="' + esc(l.url) + '" title="' + esc(l.url) + '">' + esc(l.label) + '</button>');
      });
      out.push('</div>');
    }
    if (p.share) {
      out.push('<h4>Share this draft</h4><p class="pd-note">Anyone with the link can read it. No session needed, bots get a 404.</p>' +
        '<div class="pd-acts row"><button type="button" class="mini cp" data-url="' + esc(p.share) + '">copy read link</button></div>');
    } else if (p.shareOff) {
      out.push('<h4>Share this draft</h4><p class="pd-note">' + esc(p.shareOff) + '</p>');
    }

    body.innerHTML = out.join('');
    body.querySelectorAll('.cp').forEach(function (b) {
      b.addEventListener('click', function () {
        navigator.clipboard.writeText(b.dataset.url).then(function () {
          var was = b.textContent;
          b.textContent = 'copied';
          setTimeout(function () { b.textContent = was; }, 1200);
        });
      });
    });
  }

  function open(slug) {
    var p = DATA[slug];
    if (!p) return;
    lastFocus = document.activeElement;
    render(p);
    drawer.hidden = false;
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('pd-open');
    document.querySelectorAll('.prow.is-open').forEach(function (r) { r.classList.remove('is-open'); });
    var row = document.querySelector('.prow[data-slug="' + CSS.escape(slug) + '"]');
    if (row) row.classList.add('is-open');
    requestAnimationFrame(function () { drawer.classList.add('is-in'); });
    var first = body.querySelector('.pd-go');
    if (first) first.focus();
  }

  function close() {
    drawer.classList.remove('is-in');
    document.body.classList.remove('pd-open');
    drawer.setAttribute('aria-hidden', 'true');
    document.querySelectorAll('.prow.is-open').forEach(function (r) { r.classList.remove('is-open'); });
    setTimeout(function () { drawer.hidden = true; }, 200);
    if (lastFocus) lastFocus.focus();
  }

  document.querySelectorAll('.prow').forEach(function (row) {
    row.addEventListener('click', function () { open(row.dataset.slug); });
    row.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(row.dataset.slug); }
    });
  });
  drawer.querySelectorAll('[data-close]').forEach(function (el) { el.addEventListener('click', close); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !drawer.hidden) close(); });
})();
