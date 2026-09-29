/* Election Dashboard — interaction script (no dependencies)
   Hover a county  -> preview its emblem + ballot (desktop pointer only)
   Click / tap     -> pin it so the ballot stays open
   Click again / × -> unpin and close
*/
(function () {
  'use strict';

  var HOVER_CLOSE_DELAY = 320; // ms grace period after the pointer leaves the dashboard
  var OPEN_LOCK = 650;         // ms the opening animation runs; hover changes are ignored meanwhile

  function init(root) {
    if (root.__edInit) { return; }
    root.__edInit = true;

    var cfgEl = root.querySelector('script.ed-config');
    var cfg = {};
    try { cfg = JSON.parse(cfgEl ? cfgEl.textContent : '{}'); } catch (e) { cfg = {}; }
    var counties = cfg.counties || {};

    var stage      = root.querySelector('.ed-stage');
    var mapWrap    = root.querySelector('.ed-map-wrap');
    var tip        = root.querySelector('.ed-map-tip');
    var lift       = root.querySelector('.ed-lift');
    var liftClip   = root.querySelector('.ed-lift-clip');
    var pageLink   = root.querySelector('.ed-open-page');
    var paths      = Array.prototype.slice.call(root.querySelectorAll('.ed-county'));
    var side       = root.querySelector('.ed-side');
    var floatImg   = root.querySelector('.ed-emblem-float img');
    var ballot     = root.querySelector('.ed-ballot');
    var headImg    = root.querySelector('.ed-ballot-head img');
    var title      = root.querySelector('.ed-ballot-title');
    var closeBtn   = root.querySelector('.ed-close');
    var searchWrap = root.querySelector('.ed-search');
    var search     = root.querySelector('.ed-search input');
    var body       = root.querySelector('.ed-ballot-body');
    var status     = root.querySelector('.ed-sr');

    var pinned = null;   // slug the user clicked
    var current = null;  // slug currently displayed
    var closeTimer = null;
    var cache = {};      // slug -> HTML string
    var pending = {};    // slug -> Promise
    var emblemsPreloaded = false;

    var supportsHover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    /* ---------- helpers ---------- */
    function pathFor(slug) {
      for (var i = 0; i < paths.length; i++) { if (paths[i].getAttribute('data-county') === slug) { return paths[i]; } }
      return null;
    }
    function setImg(img, county) {
      if (!img || !county) { return; }
      img.onerror = function () { if (county.emblemFallback && img.src !== county.emblemFallback) { img.src = county.emblemFallback; } };
      img.src = county.emblem;
      img.alt = county.title + ' seal';
    }
    function preloadEmblems() {
      if (emblemsPreloaded) { return; }
      emblemsPreloaded = true;
      Object.keys(counties).forEach(function (slug) {
        var im = new Image();
        im.src = counties[slug].emblem;
      });
    }
    function idle(fn) {
      if (window.requestIdleCallback) { window.requestIdleCallback(fn, { timeout: 4000 }); } else { setTimeout(fn, 1500); }
    }

    function setLift(slug) {
      if (!lift || !liftClip) { return; }
      if (!slug) { lift.classList.remove('is-on'); return; }
      liftClip.setAttribute('clip-path', 'url(#ed-clip-' + slug + ')');
      liftClip.style.clipPath = 'url(#ed-clip-' + slug + ')';
      lift.classList.add('is-on');
      lift.classList.toggle('is-pinned', pinned === slug);
    }

    /* Wrap free-form page content into searchable sections, one per heading */
    function autoSection(container) {
      if (container.querySelector('.ed-contest')) { return; }
      var kids = Array.prototype.slice.call(container.childNodes);
      var headings = kids.filter(function (n) { return n.nodeType === 1 && /^H[1-4]$/.test(n.tagName); });
      if (headings.length < 2) { return; }
      var level = headings.map(function (h) { return +h.tagName[1]; }).sort()[0];
      var frag = document.createDocumentFragment(), section = null;
      kids.forEach(function (n) {
        if (n.nodeType === 1 && n.tagName === 'H' + level) {
          section = document.createElement('div'); section.className = 'ed-contest ed-auto-section'; frag.appendChild(section);
        }
        if (section) { section.appendChild(n); } else { frag.appendChild(n); }
      });
      container.innerHTML = ''; container.appendChild(frag);
    }

    /* ---------- ballot content ---------- */
    function loadBallot(slug) {
      if (cache[slug] !== undefined) { return Promise.resolve(cache[slug]); }
      if (pending[slug]) { return pending[slug]; }
      var county = counties[slug] || {};

      // 1) inline <template data-ballot="slug"> (used by the static demo / small ballots)
      var tpl = root.querySelector('template[data-ballot="' + slug + '"]');
      if (tpl) { cache[slug] = tpl.innerHTML; return Promise.resolve(cache[slug]); }

      // 2) iframe embed of an existing page / PDF
      if (county.type === 'iframe' && county.src) {
        cache[slug] = '<iframe class="ed-frame" loading="lazy" title="' + escapeHtml(county.title) + ' ballot" src="' + escapeAttr(county.src) + '"></iframe>';
        return Promise.resolve(cache[slug]);
      }

      // 3) fetch an HTML fragment or a JSON {html:"..."} response (WordPress REST route)
      if (county.src) {
        pending[slug] = fetch(county.src, { credentials: 'same-origin' })
          .then(function (r) {
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            var ct = r.headers.get('content-type') || '';
            return ct.indexOf('json') !== -1 ? r.json().then(function (j) { return j.html || j.content || ''; }) : r.text();
          })
          .then(function (html) { cache[slug] = html; delete pending[slug]; return html; })
          .catch(function (err) { delete pending[slug]; throw err; });
        return pending[slug];
      }

      cache[slug] = '<p class="ed-empty">Ballot preview coming soon.</p>';
      return Promise.resolve(cache[slug]);
    }

    function renderBallot(slug) {
      var county = counties[slug];
      if (!county) { return; }
      title.textContent = county.title;
      if (pageLink) { pageLink.hidden = !county.page; if (county.page) { pageLink.href = county.page; } }
      setImg(headImg, county);
      setImg(floatImg, county);
      body.innerHTML = '<p class="ed-loading">Loading ballot…</p>';
      body.scrollTop = 0;
      if (search) { search.value = ''; }

      loadBallot(slug).then(function (html) {
        if (current !== slug) { return; }
        body.innerHTML = html;
        autoSection(body);
        var searchable = body.querySelectorAll('.ed-contest').length > 0;
        if (searchWrap) { searchWrap.style.display = searchable ? '' : 'none'; }
      }).catch(function () {
        if (current !== slug) { return; }
        body.innerHTML = '<p class="ed-error">Sorry, this ballot could not be loaded right now.</p>';
      });
      if (status) { status.textContent = county.title + ' ballot shown.'; }
    }

    /* ---------- state ---------- */
    var lockUntil = 0; // while the layout is sliding open, ignore counties passing under the cursor
    function show(slug) {
      cancelClose();
      if (!counties[slug]) { return; }
      if (!root.classList.contains('is-active')) { lockUntil = Date.now() + OPEN_LOCK; }
      if (slug !== current) {
        current = slug;
        renderBallot(slug);
      }
      paths.forEach(function (p) { p.classList.toggle('is-hover', p.getAttribute('data-county') === slug); });
      setLift(slug);
      root.classList.add('is-active');
    }
    function hide() {
      current = null;
      paths.forEach(function (p) { p.classList.remove('is-hover'); });
      setLift(null);
      root.classList.remove('is-active');
      if (status) { status.textContent = 'Ballot closed.'; }
    }
    function pin(slug) {
      pinned = slug;
      paths.forEach(function (p) { p.classList.toggle('is-pinned', p.getAttribute('data-county') === slug); });
      root.classList.add('is-pinned');
      show(slug);
      setLift(slug);
      // On small screens bring the ballot into view under the map
      if (window.innerWidth <= 900 && ballot.scrollIntoView) {
        setTimeout(function () { ballot.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 80);
      }
    }
    function unpin() {
      pinned = null;
      paths.forEach(function (p) { p.classList.remove('is-pinned'); });
      root.classList.remove('is-pinned');
      hide();
    }
    function scheduleClose() {
      cancelClose();
      closeTimer = setTimeout(function () {
        if (pinned) { show(pinned); } else { hide(); }
      }, HOVER_CLOSE_DELAY);
    }
    function cancelClose() { if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; } }

    /* ---------- events: map ---------- */
    paths.forEach(function (p) {
      var slug = p.getAttribute('data-county');

      p.addEventListener('pointerenter', function (ev) {
        if (ev.pointerType && ev.pointerType !== 'mouse') { return; }
        if (!supportsHover) { return; }
        if (Date.now() < lockUntil && current && slug !== current) { return; }
        preloadEmblems();
        show(slug);
        if (tip) { tip.textContent = (counties[slug] || {}).title || slug; tip.classList.add('is-visible'); }
      });
      p.addEventListener('pointermove', function (ev) {
        if (!tip || !tip.classList.contains('is-visible')) { return; }
        var r = mapWrap.getBoundingClientRect();
        tip.style.left = (ev.clientX - r.left) + 'px';
        tip.style.top = (ev.clientY - r.top) + 'px';
      });
      p.addEventListener('pointerleave', function () { if (tip) { tip.classList.remove('is-visible'); } });

      p.addEventListener('click', function (ev) {
        ev.preventDefault();
        if (pinned === slug) { unpin(); } else { pin(slug); }
      });
      p.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); if (pinned === slug) { unpin(); } else { pin(slug); } }
      });
      p.addEventListener('focus', function () { show(slug); });
    });

    // The preview stays open while the pointer is anywhere over the dashboard, so the
    // user can travel from the map to the ballot and scroll it. Leaving the whole stage
    // closes an unpinned preview (or snaps back to the pinned county).
    // Note: closing only on stage-leave also avoids a flicker loop, because the map slides
    // ~200px when the ballot opens and would otherwise move out from under the cursor.
    mapWrap.addEventListener('pointerleave', function (ev) {
      if (ev.pointerType && ev.pointerType !== 'mouse') { return; }
      if (pinned && current !== pinned) { scheduleClose(); } // revert the peek to the pinned county
    });
    stage.addEventListener('pointerenter', function () { cancelClose(); });
    stage.addEventListener('pointerleave', function (ev) {
      if (ev.pointerType && ev.pointerType !== 'mouse') { return; }
      scheduleClose();
    });
    mapWrap.addEventListener('pointerenter', preloadEmblems, { once: true });
    root.addEventListener('focusout', function () {
      setTimeout(function () { if (!root.contains(document.activeElement) && !pinned) { hide(); } }, 0);
    });

    if (closeBtn) { closeBtn.addEventListener('click', unpin); }
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && (pinned || current)) { unpin(); } });

    /* ---------- search / filter ---------- */
    if (search) {
      search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        var contests = body.querySelectorAll('.ed-contest');
        var anyVisible = false;
        Array.prototype.forEach.call(contests, function (c) {
          var hit = !q || c.textContent.toLowerCase().indexOf(q) !== -1;
          c.classList.toggle('is-filtered', !hit);
          if (hit) { anyVisible = true; }
        });
        Array.prototype.forEach.call(body.querySelectorAll('.ed-group'), function (g) {
          var visible = g.querySelectorAll('.ed-contest:not(.is-filtered)').length > 0;
          g.classList.toggle('is-filtered', !visible);
        });
        var empty = body.querySelector('.ed-no-results');
        if (!anyVisible && q) {
          if (!empty) { empty = document.createElement('p'); empty.className = 'ed-empty ed-no-results'; body.appendChild(empty); }
          empty.textContent = 'No matches for “' + search.value.trim() + '”.';
        } else if (empty) { empty.parentNode.removeChild(empty); }
      });
    }

    /* ---------- deep link: #county=mendocino or ?county=mendocino ---------- */
    var m = (location.hash + location.search).match(/county=([a-z0-9-]+)/i);
    if (m && counties[m[1]]) { pin(m[1]); }

    idle(preloadEmblems);
  }

  function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function escapeAttr(s) { return escapeHtml(s); }

  function boot() { Array.prototype.forEach.call(document.querySelectorAll('.ed-dashboard'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
  window.ElectionDashboard = { init: init };
})();
