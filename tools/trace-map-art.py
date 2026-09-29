#!/usr/bin/env python3
"""Traces the hand-drawn county map (tools/source-art/county-map-art.webp) into
clickable SVG regions. Each county is the blue-filled area between the red outlines;
the script finds those areas, grows them to the outline midline, simplifies them and
writes election-dashboard/assets/img/county-map.svg plus the trimmed artwork
(map-art.webp). Re-run after replacing the artwork.

    pip install numpy opencv-python-headless pillow
    python3 tools/trace-map-art.py
"""
import os, json
import cv2, numpy as np
from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'tools/source-art/county-map-art.webp')
OUT_DIR = os.path.join(ROOT, 'election-dashboard/assets/img')

# Which drawn region is which county: matched by where its centre sits in the drawing
# (fractions of width/height). Update if the artwork changes.
COUNTIES = {
    'mendocino':     (0.14, 0.13), 'sonoma':      (0.27, 0.36), 'napa':        (0.45, 0.36),
    'solano':        (0.55, 0.41), 'marin':       (0.30, 0.47), 'contra-costa':(0.53, 0.52),
    'san-joaquin':   (0.73, 0.50), 'alameda':     (0.56, 0.58), 'san-mateo':   (0.42, 0.61),
    'santa-clara':   (0.59, 0.66), 'santa-cruz':  (0.51, 0.71), 'monterey':    (0.72, 0.89),
    'san-francisco': (0.39, 0.56),
}
TITLES = {'san-francisco': 'City and County of San Francisco'}
def title(slug): return TITLES.get(slug, slug.replace('-', ' ').title() + ' County')

img = cv2.imread(SRC)
gray_min = np.min(img, axis=2)
ys, xs = np.where(gray_min < 245)
x0, x1, y0, y1 = xs.min(), xs.max() + 1, ys.min(), ys.max() + 1
img = img[y0:y1, x0:x1]
H, W = img.shape[:2]

b, g, r = cv2.split(img)
nonwhite = gray_min[y0:y1, x0:x1] < 235
red = (r.astype(int) - b.astype(int) > 40) & (r > 120) & (g < 120)
fill = (nonwhite & ~red).astype(np.uint8) * 255
fill = cv2.morphologyEx(fill, cv2.MORPH_CLOSE, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (9, 9)))
fill = cv2.morphologyEx(fill, cv2.MORPH_OPEN, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (5, 5)))
n, lab, stats, cent = cv2.connectedComponentsWithStats(fill, 8)
comps = sorted([(stats[i, cv2.CC_STAT_AREA], i) for i in range(1, n)], reverse=True)

# assign the components to counties by nearest expected centre
regions = {}
for slug, (fx, fy) in COUNTIES.items():
    tx, ty = fx * W, fy * H
    best = min(comps[:20], key=lambda c: (cent[c[1]][0] - tx) ** 2 + (cent[c[1]][1] - ty) ** 2)
    regions[slug] = best[1]
assert len(set(regions.values())) == len(COUNTIES), 'two counties matched the same region'

def contour_path(mask, grow):
    mask = cv2.dilate(mask, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (grow * 2 + 1, grow * 2 + 1)))
    cs, _ = cv2.findContours(mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    c = max(cs, key=cv2.contourArea)
    c = cv2.approxPolyDP(c, 2.5, True).reshape(-1, 2)
    return 'M' + ' '.join(f'{x},{y}' for x, y in c) + 'Z'

paths = {}
for slug, idx in regions.items():
    mask = (lab == idx).astype(np.uint8) * 255
    grow = 8
    if slug == 'san-francisco':            # tiny in the drawing: give it a hit area people can find
        cx, cy = cent[idx]
        cv2.circle(mask, (int(cx), int(cy)), 34, 255, -1)
        grow = 4
    paths[slug] = contour_path(mask, grow)

order = [s for s in COUNTIES if s != 'san-francisco'] + ['san-francisco']  # SF last = on top
svg = [f'<svg class="ed-map-svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 {W} {H}" role="group" aria-label="Map of the counties we cover">',
       '<defs>']
for s in order:
    svg.append(f'<clipPath id="ed-clip-{s}" clipPathUnits="userSpaceOnUse"><use href="#ed-p-{s}"/></clipPath>')
svg.append('</defs>')
svg.append(f'<g class="ed-lift" aria-hidden="true"><g class="ed-lift-clip"><image href="{{{{MAP_ART}}}}" width="{W}" height="{H}"/></g></g>')
svg.append('<g class="ed-counties">')
for s in order:
    svg.append(f'<path id="ed-p-{s}" class="ed-county" data-county="{s}" d="{paths[s]}" tabindex="0" role="button" aria-label="{title(s)}"><title>{title(s)}</title></path>')
svg.append('</g></svg>')
open(os.path.join(OUT_DIR, 'county-map.svg'), 'w').write('\n'.join(svg))

# trimmed artwork with the white keyed to transparent, at web size
art = Image.open(SRC).convert('RGBA').crop((x0, y0, x1, y1))
px = np.array(art); m = px[:, :, :3].min(axis=2)
alpha = np.clip((250 - m) * (255 / 50), 0, 255).astype(np.uint8); alpha[m >= 250] = 0
px[:, :, 3] = alpha
art = Image.fromarray(px)
art = art.resize((900, round(900 * H / W)), Image.LANCZOS)
art.save(os.path.join(OUT_DIR, 'map-art.webp'), 'WEBP', quality=85, method=6)
json.dump({'viewBox': [W, H], 'art': list(art.size)}, open(os.path.join(ROOT, 'tools/map-data.json'), 'w'))
print('regions:', {s: int(stats[i, cv2.CC_STAT_AREA]) for s, i in regions.items()})
print('svg bytes:', sum(len(l) for l in svg), 'art:', art.size)
