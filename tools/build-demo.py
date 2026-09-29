#!/usr/bin/env python3
"""Builds demo/index.html — a standalone copy of the dashboard that opens straight
from disk (no WordPress, no server). Ballot fragments are inlined as <template>s.
Run:  python3 tools/build-demo.py
"""
import json, os, re
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
P = os.path.join(ROOT, 'election-dashboard')
ASSET = '../election-dashboard/assets'

cfg_php = open(os.path.join(P, 'includes/counties.php')).read()
rows = re.findall(r"'([a-z-]+)'\s*=>\s*array\(\s*'name'\s*=>\s*'([^']+)',\s*'title'\s*=>\s*'([^']+)',\s*'emblem'\s*=>\s*'([^']+)'", cfg_php)
config = {'counties': {}}
templates = []
for slug, name, title, emblem in rows:
    config['counties'][slug] = {
        'name': name, 'title': title,
        'emblem': f'{ASSET}/img/emblems/{emblem}.webp',
        'emblemFallback': f'{ASSET}/img/emblems/{emblem}.png',
    }
    frag = open(os.path.join(P, 'ballots', f'{slug}.html')).read()
    templates.append(f'<template data-ballot="{slug}">{frag}</template>')

map_svg = open(os.path.join(P, 'assets/img/county-map.svg')).read()
tpl = open(os.path.join(P, 'templates/dashboard.php')).read()
# strip the PHP header, then swap the PHP echoes for static values
tpl = tpl.split('?>', 1)[1]
tpl = tpl.replace("<?php echo $style ? ' style=\"' . esc_attr( $style ) . '\"' : ''; ?>", '')
tpl = tpl.replace('<?php echo wp_json_encode( $config ); ?>', json.dumps(config))
tpl = tpl.replace('<?php echo $map_svg; // static SVG shipped with the plugin ?>', map_svg)
tpl = tpl.replace('<?php echo esc_url( $brand ); ?>', f'{ASSET}/img/brand-2026.svg')
tpl = re.sub(r"<\?php esc_(?:html|attr)_e\( '([^']+)', 'election-dashboard' \); \?>", r'\1', tpl)
tpl = tpl.replace('<p class="ed-sr" aria-live="polite"></p>', '<p class="ed-sr" aria-live="polite"></p>\n\t' + '\n\t'.join(templates))
assert '<?php' not in tpl, 'unreplaced PHP left in template'

html = f'''<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>2026 General Election Dashboard — demo</title>
<link rel="stylesheet" href="{ASSET}/css/election-dashboard.css">
<style>body{{margin:0;background:#fff;}}</style>
</head>
<body>
{tpl}
<script src="{ASSET}/js/election-dashboard.js" defer></script>
</body>
</html>
'''
os.makedirs(os.path.join(ROOT, 'demo'), exist_ok=True)
out = os.path.join(ROOT, 'demo/index.html')
open(out, 'w').write(html)
print('wrote', out, len(html), 'bytes')
