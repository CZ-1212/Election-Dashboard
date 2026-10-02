#!/usr/bin/env python3
"""Builds a standalone copy of the dashboard from the plugin files.

    python3 tools/build-demo.py            -> demo/index.html (relative asset paths, open from disk)
    python3 tools/build-demo.py --inline OUT.html
                                           -> one self-contained file, images embedded (for sharing)
Without WordPress the county pages cannot be fetched, so each county shows the
fragment in ballots/<county>.html instead.
"""
import base64, json, os, re, sys
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
P = os.path.join(ROOT, 'election-dashboard')
INLINE = '--inline' in sys.argv
OUT = sys.argv[sys.argv.index('--inline') + 1] if INLINE else os.path.join(ROOT, 'demo/index.html')
ASSET = '../election-dashboard/assets'

def asset(rel, mime):
    if INLINE:
        return 'data:%s;base64,%s' % (mime, base64.b64encode(open(os.path.join(P, 'assets', rel), 'rb').read()).decode())
    return ASSET + '/' + rel

cfg_php = open(os.path.join(P, 'includes/counties.php')).read()
rows = re.findall(r"'([a-z-]+)'\s*=>\s*array\(\s*'name'\s*=>\s*'([^']+)',\s*'title'\s*=>\s*'([^']+)',\s*'emblem'\s*=>\s*'([^']+)',\s*'ballot' => array\((.*?)\)\s*\)", cfg_php)
config = {'counties': {}}
templates = []
for slug, name, title, emblem, ballot in rows:
    entry = {'name': name, 'title': title, 'emblem': asset('img/emblems/%s.webp' % emblem, 'image/webp')}
    if not INLINE:
        entry['emblemFallback'] = asset('img/emblems/%s.png' % emblem, 'image/png')
    m = re.search(r"'url'\s*=>\s*'([^']+)'", ballot)
    if m:
        entry['page'] = m.group(1)
    config['counties'][slug] = entry
    frag = open(os.path.join(P, 'ballots', '%s.html' % slug)).read()
    templates.append('<template data-ballot="%s">%s</template>' % (slug, frag))

map_art = asset('img/map-art.webp', 'image/webp')
map_svg = open(os.path.join(P, 'assets/img/county-map.svg')).read()
settings = json.load(open(os.path.join(P, 'includes/settings.json')))

def esc(s):
    return str(s).replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;').replace('"', '&quot;')

# Render the PHP template by hand: it is small and the loops are simple.
icon = lambda rel: asset('img/' + rel, 'image/webp' if rel.endswith('.webp') else 'image/png')
map_dims = json.load(open(os.path.join(P, 'includes/map.json')))
links = ''.join('<a class="ed-btn%s" href="%s">%s</a>\n' % (' ed-btn-' + l['style'] if l.get('style') else '', esc(l['url']), esc(l['label'])) for l in settings['header_links'] if l.get('url'))
nav = ''.join('<a class="ed-nav-btn" href="%s" aria-label="%s"><picture><source srcset="%s" type="image/webp"><img src="%s" alt="" width="543" height="362" decoding="async"></picture></a>\n' % (esc(n['url']), esc(n['label']), icon('nav/' + n['icon']), icon('nav/' + n['icon'].replace('.webp', '.png'))) for n in settings['side_nav'])
dates = ''.join('<li class="ed-date%s"><div class="ed-date-badge"><span class="ed-date-month">%s</span><span class="ed-date-day">%s</span></div><div class="ed-date-text">%s</div></li>\n' % (' is-highlight' if d.get('highlight') else '', esc(d['month']), esc(d['day']), d['text']) for d in settings['key_dates'])

tpl = open(os.path.join(P, 'templates/dashboard.php')).read().split('?>', 1)[1]
tpl = tpl.replace("<?php echo $style ? ' style=\"' . esc_attr( $style ) . '\"' : ''; ?>", '')
tpl = tpl.replace("<?php echo '' !== $top_gap ? ' data-top-gap=\"' . intval( $top_gap ) . '\"' : ''; ?>", '')
tpl = tpl.replace('<?php echo wp_json_encode( $config ); ?>', json.dumps(config))
tpl = tpl.replace('<?php echo esc_url( $map_art ); ?>', map_art)
tpl = tpl.replace('<?php echo $map_svg; // traced hit regions and markers ?>', map_svg)
tpl = tpl.replace("<?php echo esc_url( $icon_url . 'ballot-box.png' ); ?>", icon('ballot-box.png'))
tpl = tpl.replace("<?php echo intval( $map_dims['w'] ); ?>", str(map_dims['w'])).replace("<?php echo intval( $map_dims['h'] ); ?>", str(map_dims['h']))
tpl = tpl.replace("<?php echo esc_html( $s['title']['year'] ); ?>", esc(settings['title']['year']))
tpl = tpl.replace("<?php echo esc_html( $s['title']['text'] ); ?>", esc(settings['title']['text']))
for key in ('map_title', 'map_subtitle', 'key_dates_title'):
    tpl = tpl.replace("<?php echo esc_html( $s['%s'] ); ?>" % key, esc(settings[key]))
tpl = re.sub(r"<\?php foreach \( \$s\['header_links'\] as \$l \) :.*?<\?php endforeach; \?>", links, tpl, flags=re.S)
tpl = re.sub(r"<\?php foreach \( \$s\['side_nav'\] as \$n \) : \?>.*?<\?php endforeach; \?>", nav, tpl, flags=re.S)
tpl = re.sub(r"<\?php foreach \( \$s\['key_dates'\] as \$d \) : \?>.*?<\?php endforeach; \?>", dates, tpl, flags=re.S)
tpl = re.sub(r"<\?php esc_(?:html|attr)_e\( '([^']+)', 'election-dashboard' \); \?>", r'\1', tpl)
tpl = tpl.replace('<p class="ed-sr" aria-live="polite"></p>', '<p class="ed-sr" aria-live="polite"></p>\n\t' + '\n\t'.join(templates))
assert '<?php' not in tpl, 'unreplaced PHP left in template'

FONTS = '<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;700&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">'
css = open(os.path.join(P, 'assets/css/election-dashboard.css')).read()
js = open(os.path.join(P, 'assets/js/election-dashboard.js')).read()
if INLINE:
    html = '''<title>2026 Election Dashboard</title>
''' + FONTS + '''
<style>
:root { --page-bg: #ffffff; --page-fg: #1b1f2a; --page-muted: #5b6373; --page-rule: #d9dfeb; color-scheme: light; }
body { background: var(--page-bg); color: var(--page-fg); margin: 0; padding-inline: 16px; padding-block: 8px 32px; font-family: "Montserrat", "Helvetica Neue", Arial, sans-serif; }
.pv-bar { max-width: 1400px; margin: 0 auto 10px; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 6px 14px; font-size: 12px; color: var(--page-muted); }
.pv-bar b { color: var(--page-fg); }
.pv-seg { display: inline-flex; border: 1px solid #c6d6ee; }
.pv-seg button { font: inherit; font-size: 12px; font-weight: 700; padding: 5px 10px; border: 0; background: #fff; color: #1f5fbf; cursor: pointer; }
.pv-seg button + button { border-left: 1px solid #c6d6ee; }
.pv-seg button[aria-pressed="true"] { background: #1f5fbf; color: #fff; }
.pv-stage { position: relative; overflow: hidden; }
.pv-stage .ed-root { transform-origin: top left; }
.pv-stage.is-phone .ed-root { width: 390px; margin: 0 auto; }
.ed-dashboard { border: 1px solid #e3e8f2; }
.pv-foot { max-width: 720px; margin: 8px auto 0; padding-top: 12px; border-top: 1px solid var(--page-rule); font-size: 12px; line-height: 1.5; color: var(--page-muted); text-align: center; }
%s
.ed-dashboard { padding-inline: 0; }
</style>
<div class="pv-bar"><b>Preview build.</b> Click a county to open its ballot. <span>View:</span> <span class="pv-seg" role="group" aria-label="Preview size"><button type="button" id="pv-desktop" data-view="desktop">Desktop</button><button type="button" id="pv-phone" data-view="phone">Phone</button><button type="button" id="pv-fit" data-view="fit">Fit window</button></span></div>
<div class="pv-stage" id="pv-stage">
%s
</div>
<p class="pv-foot">On the live site each ballot window shows that county&rsquo;s page from localnewsmatters.org. This preview cannot reach the site, so it shows sample text instead; the &ldquo;Full page&rdquo; button links to the real page.</p>
<script>%s</script>
<script>
(function () {
  var stage = document.getElementById('pv-stage'), root = stage.querySelector('.ed-root'), btns = document.querySelectorAll('.pv-seg button');
  var view = 'fit';
  function apply() {
    stage.classList.toggle('is-phone', view === 'phone');
    root.style.width = ''; root.style.transform = ''; stage.style.height = '';
    if (view === 'desktop') {
      var avail = stage.clientWidth, s = Math.min(1, avail / 1400);
      root.style.width = '1400px'; root.style.transform = 'scale(' + s + ')';
      root.style.marginLeft = Math.max(0, (avail - 1400 * s) / 2) + 'px';
      stage.style.height = Math.ceil(root.offsetHeight * s) + 'px';
    } else { root.style.marginLeft = ''; }
    btns.forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-view') === view)); });
  }
  btns.forEach(function (b) { b.addEventListener('click', function () { view = b.getAttribute('data-view'); apply(); }); });
  window.addEventListener('resize', apply);
  var ro = window.ResizeObserver ? new ResizeObserver(function () { if (view === 'desktop') { apply(); } }) : null;
  if (ro) { ro.observe(root); }
  view = window.innerWidth < 1000 ? 'desktop' : 'fit';   // narrow panel: show the desktop layout scaled down
  apply();
})();
</script>
''' % (css, tpl, js)
else:
    html = '''<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>2026 General Election Dashboard - demo</title>
''' + FONTS + '''
<link rel="stylesheet" href="%s/css/election-dashboard.css">
<style>body{margin:0;background:#fff;}</style>
</head>
<body>
%s
<script src="%s/js/election-dashboard.js" defer></script>
</body>
</html>
''' % (ASSET, tpl, ASSET)
os.makedirs(os.path.dirname(OUT), exist_ok=True)
open(OUT, 'w').write(html)
print('wrote', OUT, len(html) // 1024, 'KB')
