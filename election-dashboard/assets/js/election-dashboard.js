/* Election Dashboard — interaction script (no dependencies)
   Hover a county -> it lifts, its marker grows and its name shows
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
    var lift     = root.querySelector('.ed-lift');
    var liftClip = root.querySelector('.ed-lift-clip');
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

    function setLift(slug) {
      if (!lift || !liftClip) { return; }
      if (!slug) { lift.classList.remove('is-on'); return; }
      liftClip.setAttribute('clip-path', 'url(#ed-clip-' + slug + ')');
      liftClip.style.clipPath = 'url(#ed-clip-' + slug + ')';
      lift.classList.add('is-on');
      lift.classList.toggle('is-pinned', pinned === slug);
    }
    function paintMap() {
      var shown = hovered || pinned;
      markers.forEach(function (m) {
        var s = m.getAttribute('data-county');
        m.classList.toggle('is-pinned', s === pinned);
        m.classList.toggle('is-hover', s === hovered && s !== pinned);
      });
      paths.forEach(function (p) { p.classList.toggle('is-pinned', p.getAttribute('data-county') === pinned); });
      setLift(shown);
      showTip(shown);
    }
    // name label above the county's marker
    function showTip(slug) {
      if (!tip) { return; }
      var m = slug && byCounty(markers, slug);
      if (!m) { tip.classList.remove('is-visible'); return; }
      var r = m.getBoundingClientRect(), w = mapWrap.getBoundingClientRect();
      tip.textContent = counties[slug] ? counties[slug].title : slug;
      tip.style.left = (r.left + r.width / 2 - w.left) + 'px';
      tip.style.top = (r.top - w.top) + 'px';
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
        if (searchWrap) { searchWrap.style.display = body.querySelector('.ed-contest') ? '' : 'none'; }
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
      if (window.innerWidth <= 900 && ballot.scrollIntoView) {
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
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && pinned) { close(); } });

    /* ---------- search / filter ---------- */
    if (search) {
      search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase(), any = false;
        Array.prototype.forEach.call(body.querySelectorAll('.ed-contest'), function (c) {
          var hit = !q || c.textContent.toLowerCase().indexOf(q) !== -1;
          c.classList.toggle('is-filtered', !hit); if (hit) { any = true; }
        });
        Array.prototype.forEach.call(body.querySelectorAll('.ed-group'), function (g) {
          g.classList.toggle('is-filtered', !g.querySelector('.ed-contest:not(.is-filtered)'));
        });
        var empty = body.querySelector('.ed-no-results');
        if (!any && q) {
          if (!empty) { empty = document.createElement('p'); empty.className = 'ed-empty ed-no-results'; body.appendChild(empty); }
          empty.textContent = 'No matches for “' + search.value.trim() + '”.';
        } else if (empty) { empty.parentNode.removeChild(empty); }
      });
    }

    /* deep link: ?county=mendocino or #county=mendocino */
    var m = (location.hash + location.search).match(/county=([a-z0-9-]+)/i);
    if (m && counties[m[1]]) { open(m[1]); }

    idle(preloadEmblems);
  }

  function boot() { Array.prototype.forEach.call(document.querySelectorAll('.ed-dashboard'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
  window.ElectionDashboard = { init: init };
})();
