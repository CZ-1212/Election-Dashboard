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
map_svg = open(os.path.join(P, 'assets/img/county-map.svg')).read().replace('{{MAP_ART}}', map_art)
tpl = open(os.path.join(P, 'templates/dashboard.php')).read().split('?>', 1)[1]
tpl = tpl.replace("<?php echo $style ? ' style=\"' . esc_attr( $style ) . '\"' : ''; ?>", '')
tpl = tpl.replace('<?php echo wp_json_encode( $config ); ?>', json.dumps(config))
tpl = tpl.replace('<?php echo esc_url( $map_art ); ?>', map_art)
tpl = tpl.replace('<?php echo $map_svg; // traced hit regions + lift layer, shipped with the plugin ?>', map_svg)
tpl = tpl.replace('<?php echo esc_url( $brand ); ?>', asset('img/brand-2026.webp', 'image/webp'))
tpl = re.sub(r"<\?php esc_(?:html|attr)_e\( '([^']+)', 'election-dashboard' \); \?>", r'\1', tpl)
tpl = tpl.replace('<p class="ed-sr" aria-live="polite"></p>', '<p class="ed-sr" aria-live="polite"></p>\n\t' + '\n\t'.join(templates))
assert '<?php' not in tpl, 'unreplaced PHP left in template'

css = open(os.path.join(P, 'assets/css/election-dashboard.css')).read()
js = open(os.path.join(P, 'assets/js/election-dashboard.js')).read()
if INLINE:
    html = '''<title>2026 Election Dashboard</title>
<style>
:root { --page-bg: #ffffff; --page-fg: #1b1f2a; --page-muted: #5b6373; --page-rule: #d9dfeb; color-scheme: light; }
body { background: var(--page-bg); color: var(--page-fg); margin: 0; padding-inline: 16px; padding-block: 8px 32px; font-family: "Montserrat", "Helvetica Neue", Arial, sans-serif; }
.pv-note { max-width: 1400px; margin: 0 auto 4px; font-size: 12px; color: var(--page-muted); text-align: center; }
.pv-note b { color: var(--page-fg); }
.pv-foot { max-width: 720px; margin: 8px auto 0; padding-top: 12px; border-top: 1px solid var(--page-rule); font-size: 12px; line-height: 1.5; color: var(--page-muted); text-align: center; }
%s
.ed-dashboard { padding-inline: 0; }
</style>
<p class="pv-note"><b>Preview build.</b> Hover a county (or tap on a phone), click to keep the ballot open.</p>
%s
<p class="pv-foot">On the live site each ballot window shows that county&rsquo;s page from localnewsmatters.org. This preview cannot reach the site, so it shows sample text instead; the &ldquo;Full page&rdquo; button links to the real page.</p>
<script>%s</script>
''' % (css, tpl, js)
else:
    html = '''<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>2026 General Election Dashboard - demo</title>
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
