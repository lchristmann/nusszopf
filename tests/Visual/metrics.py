# Per-screen distance between the historical reference and the rewrite baseline (docs/testing/visual-regression.md):
# share of pixels that differ clearly (grey level > 40) over the common height, and the page-height difference.
import os, sys
from PIL import Image, ImageChops
here = os.path.dirname(os.path.abspath(__file__))
rows = []
for f in sorted(os.listdir(os.path.join(here, 'reference'))):
    a = Image.open(os.path.join(here, 'reference', f)).convert('L')
    b = Image.open(os.path.join(here, 'baselines', f)).convert('L')
    h = min(a.height, b.height)
    d = ImageChops.difference(a.crop((0, 0, a.width, h)), b.crop((0, 0, b.width, h))).point(lambda x: 255 if x > 40 else 0)
    rows.append((d.histogram()[255] / (a.width * h), f, a.height, b.height))
for frac, f, ha, hb in sorted(rows, reverse=True):
    print(f"{frac * 100:6.2f}%  {f:45s} height {ha:5d} -> {hb:5d} ({hb - ha:+d})")
