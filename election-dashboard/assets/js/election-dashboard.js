/* Election Dashboard — interaction script (no dependencies)
   Hover a county -> it brightens and pops forward, its marker grows and its name shows
   Click / tap    -> its ballot fills the centre panel (click again, × or Esc to close)
*/
(function () {
  'use strict';

  function init(root) {
    if (root.__edInit) { return; }
    root.__edInit = true;

    var cfgEl = root.querySelector('script.ed-config');
    var cfg = {};
    try { cfg = JSON.parse(cfgEl ? cfgEl.textContent : '{}'); } catch (e) { cfg = {}; }
    var counties = cfg.counties || {};

    var mapWrap  = root.querySelector('.ed-map-wrap');
    var tip      = root.querySelector('.ed-map-tip');
    var paths    = Array.prototype.slice.call(root.querySelectorAll('.ed-county'));
    var markers  = Array.prototype.slice.call(root.querySelectorAll('.ed-marker'));
    var ballot   = root.querySelector('.ed-ballot');
    var headImg  = root.querySelector('.ed-ballot-head img');
    var title    = root.querySelector('.ed-ballot-title');
    var pageLink = root.querySelector('.ed-open-page');
    var closeBtn = root.querySelector('.ed-close');
    var scroller = root.querySelector('.ed-ballot-scroll');
    var searchWrap = root.querySelector('.ed-search');
    var search   = root.querySelector('.ed-search input');
    var body     = root.querySelector('.ed-ballot-body');
    var status   = root.querySelector('.ed-sr');

    var pinned = null;      // county whose ballot is open
    var hovered = null;
    var cache = {}, pending = {};
    var emblemsPreloaded = false;

    /* ---------- helpers ---------- */
    function byCounty(list, slug) { return list.filter(function (el) { return el.getAttribute('data-county') === slug; })[0]; }
    function setImg(img, county) {
      if (!img || !county) { return; }
      img.onerror = function () { if (county.emblemFallback && img.src !== county.emblemFallback) { img.src = county.emblemFallback; } };
      img.src = county.emblem;
      img.alt = county.title + ' seal';
    }
    function preloadEmblems() {
      if (emblemsPreloaded) { return; }
      emblemsPreloaded = true;
      Object.keys(counties).forEach(function (slug) { var im = new Image(); im.src = counties[slug].emblem; });
    }
    function idle(fn) { if (window.requestIdleCallback) { window.requestIdleCallback(fn, { timeout: 4000 }); } else { setTimeout(fn, 1500); } }
    function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    function paintMap() {
      var shown = hovered || pinned;
      markers.forEach(function (m) {
        var s = m.getAttribute('data-county');
        m.classList.toggle('is-pinned', s === pinned);
        m.classList.toggle('is-hover', s === hovered && s !== pinned);
      });
      paths.forEach(function (p) {
        var s = p.getAttribute('data-county');
        p.classList.toggle('is-pinned', s === pinned);
        p.classList.toggle('is-hover', s === hovered && s !== pinned);
      });
      showTip(shown);
    }
    // name label above the county's marker
    function showTip(slug) {
      if (!tip) { return; }
      var m = slug && byCounty(markers, slug);
      if (!m) { tip.classList.remove('is-visible'); return; }
      var r = m.getBoundingClientRect(), w = mapWrap.getBoundingClientRect();
      var k = w.width ? mapWrap.offsetWidth / w.width : 1;   // 1 unless the dashboard is CSS-scaled
      tip.textContent = counties[slug] ? counties[slug].title : slug;
      tip.style.left = ((r.left + r.width / 2 - w.left) * k) + 'px';
      tip.style.top = ((r.top - w.top) * k) + 'px';
      tip.classList.add('is-visible');
    }

    /* Wrap free-form page content into searchable sections, one per heading */
    function autoSection(container) {
      if (container.querySelector('.ed-contest')) { return; }
      var kids = Array.prototype.slice.call(container.childNodes);
      var headings = kids.filter(function (n) { return n.nodeType === 1 && /^H[1-4]$/.test(n.tagName); });
      if (!headings.length) { return; }
      var level = headings.map(function (h) { return +h.tagName[1]; }).sort()[0];
      var frag = document.createDocumentFragment(), section = null;
      kids.forEach(function (n) {
        if (n.nodeType === 3 && !n.textContent.trim()) { return; }
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
      var tpl = root.querySelector('template[data-ballot="' + slug + '"]');
      if (tpl) { cache[slug] = tpl.innerHTML; return Promise.resolve(cache[slug]); }
      if (county.type === 'iframe' && county.src) {
        cache[slug] = '<iframe class="ed-frame" loading="lazy" title="' + escapeHtml(county.title) + ' ballot" src="' + escapeHtml(county.src) + '"></iframe>';
        return Promise.resolve(cache[slug]);
      }
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
      body.innerHTML = '<p class="ed-loading">Loading ballot…</p>';
      if (scroller) { scroller.scrollTop = 0; }
      if (search) { search.value = ''; }
      loadBallot(slug).then(function (html) {
        if (pinned !== slug) { return; }
        body.innerHTML = html;
        autoSection(body);
        if (searchWrap) { searchWrap.style.display = body.querySelector('.ed-contest, .race-box') ? '' : 'none'; }
      }).catch(function () {
        if (pinned !== slug) { return; }
        body.innerHTML = '<p class="ed-error">Sorry, this ballot could not be loaded right now.</p>';
      });
      if (status) { status.textContent = county.title + ' ballot shown.'; }
    }

    /* ---------- state ---------- */
    function open(slug) {
      if (!counties[slug]) { return; }
      pinned = slug;
      root.classList.add('is-active');
      renderBallot(slug);
      paintMap();
      if (root.offsetWidth <= 900 && ballot.scrollIntoView) {
        setTimeout(function () { ballot.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 80);
      }
    }
    function close() {
      pinned = null;
      root.classList.remove('is-active');
      paintMap();
      if (status) { status.textContent = 'Ballot closed.'; }
    }
    function toggle(slug) { if (pinned === slug) { close(); } else { open(slug); } }

    /* ---------- events ---------- */
    paths.forEach(function (p) {
      var slug = p.getAttribute('data-county');
      p.addEventListener('pointerenter', function (ev) {
        if (ev.pointerType && ev.pointerType !== 'mouse') { return; }
        preloadEmblems();
        hovered = slug; paintMap();
      });
      p.addEventListener('pointerleave', function () { if (hovered === slug) { hovered = null; paintMap(); } });
      p.addEventListener('click', function (ev) { ev.preventDefault(); toggle(slug); });
      p.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); toggle(slug); } });
      p.addEventListener('focus', function () { hovered = slug; paintMap(); });
      p.addEventListener('blur', function () { if (hovered === slug) { hovered = null; paintMap(); } });
    });
    mapWrap.addEventListener('pointerenter', preloadEmblems, { once: true });
    window.addEventListener('resize', function () { showTip(hovered || pinned); });

    if (closeBtn) { closeBtn.addEventListener('click', close); }
    // "Local coverage" style links point at a block further down the same page
    Array.prototype.forEach.call(root.querySelectorAll('.ed-top-links a[href^="#"]'), function (a) {
      a.addEventListener('click', function (ev) {
        var href = a.getAttribute('href');
        if (href === '#') { ev.preventDefault(); return; }   // placeholder link, nothing to open yet
        var target = document.querySelector(href);
        if (target) { ev.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
      });
    });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && pinned) { close(); } });

    /* ---------- search / filter ---------- */
    if (search) {
      search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase(), any = false;
        Array.prototype.forEach.call(body.querySelectorAll('.ed-contest, .race-box'), function (c) {
          var hit = !q || c.textContent.toLowerCase().indexOf(q) !== -1;
          c.classList.toggle('is-filtered', !hit); if (hit) { any = true; }
        });
        Array.prototype.forEach.call(body.querySelectorAll('.ed-group'), function (g) {
          g.classList.toggle('is-filtered', !g.querySelector('.ed-contest:not(.is-filtered), .race-box:not(.is-filtered)'));
        });
        var empty = body.querySelector('.ed-no-results');
        if (!any && q) {
          if (!empty) { empty = document.createElement('p'); empty.className = 'ed-empty ed-no-results'; body.appendChild(empty); }
          empty.textContent = 'No matches for “' + search.value.trim() + '”.';
        } else if (empty) { empty.parentNode.removeChild(empty); }
      });
    }

    /* ---------- close the gap the theme leaves between the site header and the dashboard ----------
       The wrapper carries data-top-gap (px wanted). We measure the real distance from the bottom of the
       site header to the top of the dashboard and pull the dashboard up by the difference, but only when
       nothing else (a title, an ad, an image) sits in that space. */
    var wrap = root.parentNode && root.parentNode.classList && root.parentNode.classList.contains('ed-root') ? root.parentNode : null;
    function closeTopGap() {
      if (!wrap) { return; }
      var want = parseInt(wrap.getAttribute('data-top-gap'), 10);
      if (isNaN(want)) { return; }
      var header = document.querySelector('#masthead, header.site-header, .site-header, header[role="banner"], body > header');
      if (!header) { return; }
      wrap.style.marginTop = wrap.getAttribute('data-base-margin') || '';
      var hb = header.getBoundingClientRect().bottom, rt = wrap.getBoundingClientRect().top, gap = rt - hb;
      if (gap <= want || gap > 400) { return; }
      // anything visible between the header and the dashboard (a page title, an ad, an image)? then leave it alone
      var all = document.body.getElementsByTagName('*'), FOLLOWING = 4;
      for (var i = 0; i < all.length; i++) {
        var el = all[i];
        if (el === wrap || wrap.contains(el) || el.contains(wrap) || header.contains(el) || el.contains(header)) { continue; }
        if (!(header.compareDocumentPosition(el) & FOLLOWING) || !(el.compareDocumentPosition(wrap) & FOLLOWING)) { continue; }
        var tag = el.tagName;
        if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'LINK' || tag === 'NOSCRIPT' || tag === 'TEMPLATE') { continue; }
        var hasText = false;
        for (var n = 0; n < el.childNodes.length; n++) { if (el.childNodes[n].nodeType === 3 && el.childNodes[n].textContent.trim()) { hasText = true; break; } }
        if ((hasText || /^(IMG|IFRAME|SVG|VIDEO|CANVAS|INPUT|BUTTON|SELECT)$/.test(tag)) && el.getBoundingClientRect().height > 0) { return; }
      }
      var base = parseFloat(getComputedStyle(wrap).marginTop) || 0;
      wrap.style.marginTop = (base - (gap - want)) + 'px';
    }
    /* fit="screen": size the dashboard to the space left between its top edge and the bottom of the window */
    function fitScreen() {
      if (!root.classList.contains('ed-compact')) { return; }
      if (root.offsetWidth <= 900) { root.style.removeProperty('--ed-fit-height'); return; }   // phones stack and scroll
      var top = root.getBoundingClientRect().top + window.scrollY;
      var h = window.innerHeight - top - 12;
      root.style.setProperty('--ed-fit-height', Math.max(520, Math.round(h)) + 'px');
    }
    function layout() { closeTopGap(); fitScreen(); }
    if (wrap) { wrap.setAttribute('data-base-margin', wrap.style.marginTop || ''); }
    layout();
    window.addEventListener('load', layout);
    window.addEventListener('resize', layout);

    /* deep link: ?county=mendocino or #county=mendocino */
    var m = (location.hash + location.search).match(/county=([a-z0-9-]+)/i);
    if (m && counties[m[1]]) { open(m[1]); }

    idle(preloadEmblems);
  }

  function boot() { Array.prototype.forEach.call(document.querySelectorAll('.ed-dashboard'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
  window.ElectionDashboard = { init: init };
})();
