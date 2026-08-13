/**
 * review.js — margin notes and in-place correction for review.php.
 *
 * Two things here are worth knowing before changing anything.
 *
 * 1. A note is anchored by what it quotes, not by where it sat. Offsets are recomputed by searching
 *    the live DOM for prefix+quote+suffix, degrading to the quote alone. That is the same strategy
 *    the server uses over the markdown, arrived at independently on each side, so an edit that moves
 *    a sentence does not strand the note attached to it.
 *
 * 2. A save rewrites one block. The serializer below emits exactly the markdown subset that
 *    lib/markdown.php renders, and review.php splices only that block's source lines, so correcting
 *    a typo produces a one-line diff. Anything richer than this subset would round-trip into noise.
 */
(function () {
  'use strict';

  var DATA = JSON.parse(document.getElementById('rv-data').textContent);
  var REVIEW = DATA.mode === 'review';
  var prose = document.querySelector('.rv-prose');
  var rail = document.getElementById('rv-rail');
  var saveFlag = document.getElementById('rv-save');
  var countEl = document.getElementById('rv-count');
  var tip = document.getElementById('rv-tip');
  var comments = DATA.comments.slice();
  var active = null;

  /* ------------------------------------------------------------------ helpers */

  function post(fields) {
    var body = new URLSearchParams();
    body.set('slug', DATA.slug);
    body.set('csrf', DATA.csrf);
    Object.keys(fields).forEach(function (k) { body.set(k, fields[k]); });
    return fetch('/review.php?slug=' + encodeURIComponent(DATA.slug), {
      method: 'POST', body: body, credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, json: j }; }); });
  }

  function flag(text, isError) {
    saveFlag.textContent = text;
    saveFlag.classList.toggle('is-err', !!isError);
    if (!isError && text) setTimeout(function () { if (saveFlag.textContent === text) saveFlag.textContent = ''; }, 1600);
  }

  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  function blockEl(id) { return prose.querySelector('.rv-b[data-id="' + id + '"]'); }

  /** Text nodes of an element with their cumulative start offsets, ignoring our own highlights. */
  function textMap(el) {
    var nodes = [], total = 0;
    var walk = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
    var n;
    while ((n = walk.nextNode())) {
      nodes.push({ node: n, start: total, end: total + n.nodeValue.length });
      total += n.nodeValue.length;
    }
    return { nodes: nodes, text: el.textContent };
  }

  /** A DOM Range for [start,end) in an element's textContent, or null. */
  function rangeAt(el, start, end) {
    var map = textMap(el), r = document.createRange(), got = 0;
    for (var i = 0; i < map.nodes.length; i++) {
      var m = map.nodes[i];
      if (got === 0 && start >= m.start && start <= m.end) { r.setStart(m.node, start - m.start); got = 1; }
      if (got === 1 && end >= m.start && end <= m.end) { r.setEnd(m.node, end - m.start); return r; }
    }
    return null;
  }

  /** Where a note currently sits, searched the same way the server searches the markdown. */
  function locate(c) {
    var pref = c.prefix || '', suf = c.suffix || '', q = c.quote || '';
    if (!q) return null;
    var ids = [];
    if (c.block && blockEl(c.block)) ids.push(c.block);
    Array.prototype.forEach.call(prose.querySelectorAll('.rv-b'), function (b) {
      if (b.dataset.id !== c.block) ids.push(b.dataset.id);
    });
    for (var pass = 0; pass < 2; pass++) {
      for (var i = 0; i < ids.length; i++) {
        var el = blockEl(ids[i]);
        if (!el) continue;
        var hay = el.textContent;
        var needle = pass === 0 ? pref + q + suf : q;
        var at = hay.indexOf(needle);
        if (at < 0) continue;
        var s = pass === 0 ? at + pref.length : at;
        return { el: el, start: s, end: s + q.length };
      }
    }
    return null;
  }

  /* ------------------------------------------------------- highlights + rail */

  function clearHighlights() {
    Array.prototype.forEach.call(prose.querySelectorAll('mark.rv-hl'), function (m) {
      var parent = m.parentNode;
      while (m.firstChild) parent.insertBefore(m.firstChild, m);
      parent.removeChild(m);
      parent.normalize();
    });
  }

  function paint() {
    clearHighlights();
    comments.forEach(function (c) { c.__at = locate(c); });
    // Later ranges first: wrapping a range invalidates offsets after it in the same block.
    comments.slice().sort(function (a, b) {
      if (!a.__at || !b.__at) return 0;
      if (a.__at.el !== b.__at.el) return 0;
      return b.__at.start - a.__at.start;
    }).forEach(function (c) {
      if (!c.__at) return;
      var r = rangeAt(c.__at.el, c.__at.start, c.__at.end);
      if (!r) return;
      var mark = document.createElement('mark');
      mark.className = 'rv-hl' + (c.status === 'resolved' ? ' is-resolved' : '');
      mark.dataset.cid = c.id;
      try { r.surroundContents(mark); }
      catch (e) { mark.appendChild(r.extractContents()); r.insertNode(mark); }
      mark.addEventListener('click', function (ev) { ev.stopPropagation(); focusNote(c.id); });
    });
    renderRail();
  }

  function card(c) {
    var el = document.createElement('div');
    el.className = 'rv-card' + (c.status === 'resolved' ? ' is-resolved' : '');
    el.dataset.cid = c.id;
    var fix = c.resolution && c.resolution.note
      ? '<div class="rv-fix">✓ ' + esc(c.resolution.by || 'resolved') + ': ' + esc(c.resolution.note) + '</div>' : '';
    el.innerHTML =
      '<blockquote>' + esc(c.quote) + '</blockquote>' +
      '<div class="rv-body-text"></div>' + fix +
      '<div class="rv-meta">' +
        '<button type="button" data-act="edit">edit</button>' +
        '<button type="button" data-act="toggle">' + (c.status === 'resolved' ? 'reopen' : 'resolve') + '</button>' +
        '<button type="button" data-act="del">delete</button>' +
        '<span class="rv-when">' + esc((c.created || '').slice(5, 16).replace('T', ' ')) + '</span>' +
      '</div>';
    el.querySelector('.rv-body-text').textContent = c.body;

    el.addEventListener('click', function () { focusNote(c.id); });
    el.querySelector('[data-act="del"]').addEventListener('click', function (ev) {
      ev.stopPropagation();
      post({ action: 'comment_delete', id: c.id }).then(function () {
        comments = comments.filter(function (x) { return x.id !== c.id; });
        paint(); flag('deleted');
      });
    });
    el.querySelector('[data-act="toggle"]').addEventListener('click', function (ev) {
      ev.stopPropagation();
      var next = c.status === 'resolved' ? 'open' : 'resolved';
      post({ action: 'comment_update', id: c.id, status: next }).then(function () {
        c.status = next; if (next === 'open') c.resolution = null;
        paint(); flag('saved');
      });
    });
    el.querySelector('[data-act="edit"]').addEventListener('click', function (ev) {
      ev.stopPropagation();
      var ta = document.createElement('textarea');
      ta.value = c.body;
      var slot = el.querySelector('.rv-body-text');
      slot.replaceWith(ta);
      ta.focus();
      var commit = function () {
        var v = ta.value.trim();
        if (v && v !== c.body) {
          post({ action: 'comment_update', id: c.id, body: v }).then(function () { c.body = v; paint(); flag('saved'); });
        } else { paint(); }
      };
      ta.addEventListener('blur', commit);
      ta.addEventListener('keydown', function (k) {
        if (k.key === 'Enter' && (k.metaKey || k.ctrlKey)) { k.preventDefault(); ta.blur(); }
        if (k.key === 'Escape') { k.preventDefault(); paint(); }
      });
    });
    return el;
  }

  function renderRail() {
    if (!rail) return;
    rail.textContent = '';
    var railTop = rail.getBoundingClientRect().top + window.scrollY;
    var placed = [];
    comments.filter(function (c) { return c.__at; })
      .sort(function (a, b) { return a.__at.el.offsetTop - b.__at.el.offsetTop || a.__at.start - b.__at.start; })
      .forEach(function (c) {
        var el = card(c);
        rail.appendChild(el);
        var mark = prose.querySelector('mark.rv-hl[data-cid="' + c.id + '"]');
        var y = ((mark || c.__at.el).getBoundingClientRect().top + window.scrollY) - railTop;
        var last = placed[placed.length - 1];
        if (last !== undefined && y < last) y = last;      // never overlap the card above
        el.style.top = Math.max(0, y) + 'px';
        placed.push(y + el.offsetHeight + 10);
      });
    var openN = comments.filter(function (c) { return c.status !== 'resolved' && c.__at; }).length;
    if (countEl) countEl.textContent = openN + ' open';
  }

  function focusNote(id) {
    active = id;
    Array.prototype.forEach.call(rail.querySelectorAll('.rv-card'), function (el) {
      el.classList.toggle('is-active', el.dataset.cid === id);
    });
    Array.prototype.forEach.call(prose.querySelectorAll('mark.rv-hl'), function (m) {
      m.classList.toggle('is-active', m.dataset.cid === id);
    });
  }

  /* --------------------------------------------------- selection → new note */

  var pending = null;

  function hideTip() { if (tip) tip.hidden = true; }

  function onSelect() {
    if (!REVIEW || !tip) return;
    var sel = window.getSelection();
    if (!sel || sel.isCollapsed || !sel.rangeCount) return hideTip();
    var r = sel.getRangeAt(0);
    var host = r.startContainer.nodeType === 1 ? r.startContainer : r.startContainer.parentNode;
    var block = host.closest ? host.closest('.rv-b') : null;
    if (!block || !prose.contains(block)) return hideTip();
    var quote = sel.toString().trim();
    if (quote.length < 2) return hideTip();

    var text = block.textContent;
    var at = text.indexOf(quote);
    pending = {
      block: block.dataset.id,
      section: block.dataset.section || '',
      quote: quote,
      prefix: at > 0 ? text.slice(Math.max(0, at - 40), at) : '',
      suffix: at >= 0 ? text.slice(at + quote.length, at + quote.length + 40) : ''
    };
    var box = r.getBoundingClientRect();
    tip.style.left = (box.left + box.width / 2 + window.scrollX) + 'px';
    tip.style.top = (box.top + window.scrollY - 8) + 'px';
    tip.hidden = false;
  }

  function compose() {
    if (!pending) return;
    hideTip();
    var draft = { id: '__draft', quote: pending.quote, body: '', status: 'open', created: new Date().toISOString(), block: pending.block, prefix: pending.prefix, suffix: pending.suffix };
    comments.push(draft);
    paint();
    var el = rail.querySelector('.rv-card[data-cid="__draft"]');
    if (!el) return;
    var ta = document.createElement('textarea');
    ta.placeholder = 'What should change here?';
    el.querySelector('.rv-body-text').replaceWith(ta);
    el.classList.add('is-active');
    ta.focus();
    var done = false;
    var commit = function (save) {
      if (done) return;
      done = true;
      var body = ta.value.trim();
      comments = comments.filter(function (x) { return x.id !== '__draft'; });
      if (!save || !body) { pending = null; paint(); return; }
      post(Object.assign({ action: 'comment', body: body }, pending)).then(function (res) {
        if (res.json && res.json.comment) comments.push(res.json.comment);
        pending = null; paint(); flag('note saved');
      });
    };
    ta.addEventListener('blur', function () { commit(true); });
    ta.addEventListener('keydown', function (k) {
      if (k.key === 'Enter' && (k.metaKey || k.ctrlKey)) { k.preventDefault(); commit(true); }
      if (k.key === 'Escape') { k.preventDefault(); commit(false); }
    });
  }

  /* ------------------------------------------------- html → markdown, one block */

  function inline(node) {
    var out = '';
    node.childNodes.forEach(function (n) {
      if (n.nodeType === 3) { out += n.nodeValue; return; }
      if (n.nodeType !== 1) return;
      var tag = n.nodeName, inner = inline(n);
      if (tag === 'BR') { out += ' '; return; }
      if (tag === 'STRONG' || tag === 'B') { out += inner.trim() ? '**' + inner + '**' : inner; return; }
      if (tag === 'EM' || tag === 'I') { out += inner.trim() ? '*' + inner + '*' : inner; return; }
      if (tag === 'CODE') { out += '`' + n.textContent + '`'; return; }
      if (tag === 'A') {
        var href = n.getAttribute('href') || '';
        out += href ? '[' + inner + '](' + href + ')' : inner;
        return;
      }
      if (tag === 'MARK') { out += inner; return; }     // our own highlight, never content
      out += inner;                                     // anything the browser invented
    });
    return out;
  }

  function serialize(block) {
    var type = block.dataset.type;
    var root = block.firstElementChild || block;
    var clean = function (s) { return s.replace(/ /g, ' ').replace(/\s+/g, ' ').trim(); };
    if (type === 'h2') return '## ' + clean(inline(root));
    if (type === 'quote') return '> ' + clean(inline(root));
    if (type === 'ul' || type === 'ol') {
      var items = Array.prototype.map.call(block.querySelectorAll('li'), function (li, i) {
        return (type === 'ul' ? '- ' : (i + 1) + '. ') + clean(inline(li));
      }).filter(function (s) { return s.replace(/^(- |\d+\. )/, '').length; });
      return items.join('\n');
    }
    return clean(inline(root));
  }

  var timers = {};

  function save(block) {
    var id = block.dataset.id;
    var md = serialize(block);
    if (!md || md === block.dataset.md) return;
    flag('saving…');
    post({ action: 'block', id: id, md: md, hash: block.dataset.hash }).then(function (res) {
      if (res.ok && res.json.ok) {
        block.dataset.hash = res.json.hash;
        block.dataset.md = res.json.md;
        block.classList.remove('is-dirty', 'is-stale');
        flag('saved');
        paint();
      } else if (res.status === 409) {
        block.classList.add('is-stale');
        flag('changed elsewhere — reload', true);
      } else {
        block.classList.add('is-stale');
        flag((res.json && res.json.error) || 'save failed', true);
      }
    }).catch(function () { flag('offline', true); });
  }

  function wireEditing() {
    Array.prototype.forEach.call(prose.querySelectorAll('.rv-b.is-editable'), function (block) {
      block.dataset.md = serialize(block);

      block.addEventListener('input', function () {
        block.classList.add('is-dirty');
        clearTimeout(timers[block.dataset.id]);
        timers[block.dataset.id] = setTimeout(function () { save(block); }, 900);
      });
      block.addEventListener('blur', function () {
        clearTimeout(timers[block.dataset.id]);
        save(block);
      });
      // Paste arrives as plain text: pasted markup is the fastest way to a diff full of spans.
      block.addEventListener('paste', function (ev) {
        ev.preventDefault();
        var t = (ev.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, t.replace(/\s*\n\s*/g, ' '));
      });
      block.addEventListener('keydown', function (ev) {
        var multi = block.dataset.type === 'ul' || block.dataset.type === 'ol';
        if (ev.key === 'Enter' && !multi) { ev.preventDefault(); block.blur(); }
        if (ev.key === 'Escape') { ev.preventDefault(); block.blur(); }
        if ((ev.metaKey || ev.ctrlKey) && ev.key.toLowerCase() === 'b') { ev.preventDefault(); document.execCommand('bold'); }
        if ((ev.metaKey || ev.ctrlKey) && ev.key.toLowerCase() === 'i') { ev.preventDefault(); document.execCommand('italic'); }
      });
    });
  }

  /* ------------------------------------------------------------------- boot */

  paint();
  if (REVIEW) {
    wireEditing();
    document.addEventListener('mouseup', function () { setTimeout(onSelect, 0); });
    document.addEventListener('keyup', function (e) { if (e.shiftKey) setTimeout(onSelect, 0); });
    document.getElementById('rv-tip-btn').addEventListener('click', compose);
    document.addEventListener('mousedown', function (e) { if (tip && !tip.contains(e.target)) hideTip(); });
  }
  Array.prototype.forEach.call(document.querySelectorAll('.rv-orphan .rv-del'), function (btn) {
    btn.addEventListener('click', function () {
      post({ action: 'comment_delete', id: btn.dataset.id }).then(function () {
        btn.closest('.rv-orphan').remove();
      });
    });
  });
  window.addEventListener('resize', renderRail);
})();
