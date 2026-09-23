# Side-by-side review composites: historical (left) | rewrite (right). Usage: python3 sbs.py OUTDIR [filter]
import os, sys
from PIL import Image
here = os.path.dirname(os.path.abspath(__file__))
out = sys.argv[1]; flt = sys.argv[2] if len(sys.argv) > 2 else ''
os.makedirs(out, exist_ok=True)
for f in sorted(os.listdir(os.path.join(here, 'reference'))):
    if flt not in f: continue
    a = Image.open(os.path.join(here, 'reference', f)).convert('RGB')
    b = Image.open(os.path.join(here, 'baselines', f)).convert('RGB')
    h = max(a.height, b.height); w = a.width + b.width + 20
    c = Image.new('RGB', (w, h), (255, 0, 255)); c.paste(a, (0, 0)); c.paste(b, (a.width + 20, 0))
    s = min(1.0, 1800 / w)
    if s < 1: c = c.resize((int(w * s), int(h * s)))
    c.save(os.path.join(out, f))
