#!/usr/bin/env python3
"""Turns the county map artwork (tools/source-art/county-map-green.svg) into:
  election-dashboard/assets/img/map-art.webp   the drawing, trimmed, 900 px wide, transparent
  election-dashboard/assets/img/county-map.svg invisible clickable regions + marker dots
  election-dashboard/includes/map.json         the drawing's proportions

Each county is a separate green shape with a white gap around it, so the script finds the
green areas, grows them to the middle of the gap, simplifies the outlines and names them by
where their centre sits in the drawing (COUNTIES below, as fractions of width / height).

    pip install numpy opencv-python-headless pillow
    python3 tools/trace-map-art.py
"""
import os, json, subprocess
import cv2, numpy as np
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'tools/source-art/county-map-green.svg')
OUT_DIR = os.path.join(ROOT, 'election-dashboard/assets/img')
RENDER_W = 2160
TMP = os.path.join(ROOT, 'tools/source-art/.render.png')

COUNTIES = {
    'mendocino': (0.17, 0.13), 'sonoma': (0.30, 0.35), 'napa': (0.45, 0.36), 'solano': (0.55, 0.42),
    'marin': (0.35, 0.46), 'contra-costa': (0.55, 0.50), 'san-joaquin': (0.74, 0.49), 'alameda': (0.57, 0.57),
    'san-francisco': (0.43, 0.53), 'san-mateo': (0.45, 0.61), 'santa-clara': (0.61, 0.66),
    'santa-cruz': (0.54, 0.70), 'monterey': (0.74, 0.90),
}
TITLES = {'san-francisco': 'City and County of San Francisco'}
def title(slug): return TITLES.get(slug, slug.replace('-', ' ').title() + ' County')

subprocess.run(['node', os.path.join(ROOT, 'tools/render-svg.js'), SRC, TMP, str(RENDER_W)], check=True)
img = cv2.imread(TMP, cv2.IMREAD_UNCHANGED)
b, g, r, a = cv2.split(img)
green = (a > 128) & (g.astype(int) > r.astype(int) + 10) & (g.astype(int) > b.astype(int) + 10)
ys, xs = np.where(green)
pad = 6
x0, x1, y0, y1 = max(xs.min() - pad, 0), min(xs.max() + pad, img.shape[1]), max(ys.min() - pad, 0), min(ys.max() + pad, img.shape[0])
green = green[y0:y1, x0:x1]
H, W = green.shape

mask = green.astype(np.uint8) * 255
mask = cv2.morphologyEx(mask, cv2.MORPH_OPEN, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (5, 5)))
n, lab, stats, cent = cv2.connectedComponentsWithStats(mask, 8)
comps = sorted([(stats[i, cv2.CC_STAT_AREA], i) for i in range(1, n)], reverse=True)[:20]

regions = {}
for slug, (fx, fy) in COUNTIES.items():
    tx, ty = fx * W, fy * H
    regions[slug] = min(comps, key=lambda c: (cent[c[1]][0] - tx) ** 2 + (cent[c[1]][1] - ty) ** 2)[1]
assert len(set(regions.values())) == len(COUNTIES), 'two counties matched the same region'

def contour_path(m, grow):
    m = cv2.dilate(m, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (grow * 2 + 1, grow * 2 + 1)))
    cs, _ = cv2.findContours(m, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    c = cv2.approxPolyDP(max(cs, key=cv2.contourArea), 3.0, True).reshape(-1, 2)
    return 'M' + ' '.join('%d,%d' % (x, y) for x, y in c) + 'Z'

paths = {}
for slug, idx in regions.items():
    m = (lab == idx).astype(np.uint8) * 255
    if slug == 'san-francisco':            # small in the drawing: give it a hit area people can find
        cx, cy = cent[idx]
        cv2.circle(m, (int(cx), int(cy)), 48, 255, -1)
    paths[slug] = contour_path(m, 7)

order = [s for s in COUNTIES if s != 'san-francisco'] + ['san-francisco']  # SF last = on top
svg = ['<svg class="ed-map-svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 %d %d" role="group" aria-label="Map of the counties we cover">' % (W, H), '<defs>']
for s in order:
    svg.append('<clipPath id="ed-clip-%s" clipPathUnits="userSpaceOnUse"><use href="#ed-p-%s"/></clipPath>' % (s, s))
svg.append('</defs>')
svg.append('<g class="ed-counties">')
for s in order:
    svg.append('<path id="ed-p-%s" class="ed-county" data-county="%s" d="%s" tabindex="0" role="button" aria-label="%s"><title>%s</title></path>' % (s, s, paths[s], title(s), title(s)))
svg.append('</g><g class="ed-markers" aria-hidden="true">')
for s in order:
    cx, cy = cent[regions[s]]
    svg.append('<circle class="ed-marker" data-county="%s" cx="%d" cy="%d" r="26"/>' % (s, cx, cy))
svg.append('</g></svg>')
open(os.path.join(OUT_DIR, 'county-map.svg'), 'w').write('\n'.join(svg))

art = Image.open(TMP).convert('RGBA').crop((x0, y0, x1, y1))
art = art.resize((900, round(900 * H / W)), Image.LANCZOS)
art.save(os.path.join(OUT_DIR, 'map-art.webp'), 'WEBP', quality=88, method=6)
json.dump({'w': W, 'h': H}, open(os.path.join(ROOT, 'election-dashboard/includes/map.json'), 'w'))
os.remove(TMP)
print('regions:', {s: int(stats[i, cv2.CC_STAT_AREA]) for s, i in regions.items()})
print('viewBox %dx%d, art %s, svg %d bytes' % (W, H, art.size, sum(len(l) for l in svg)))
