// Renders an SVG to a PNG at a given width using the bundled Chromium (transparent background).
// usage: node tools/render-svg.js in.svg out.png width
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const [,, src, out, w] = process.argv;
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: +w, height: +w }, deviceScaleFactor: 1 });
  const svg = require('fs').readFileSync(src, 'utf8');
  const m = svg.match(/viewBox="([\d.\s-]+)"/); const vb = m[1].trim().split(/\s+/).map(Number);
  const h = Math.round(+w * vb[3] / vb[2]);
  await p.setViewportSize({ width: +w, height: h });
  await p.setContent('<html><body style="margin:0;background:transparent">' + svg.replace(/<svg([^>]*?)\swidth="[^"]*"/, '<svg$1').replace(/<svg([^>]*?)\sheight="[^"]*"/, '<svg$1').replace('<svg', `<svg width="${w}" height="${h}" style="display:block"`) + '</body></html>');
  await p.waitForTimeout(400);
  await p.screenshot({ path: out, omitBackground: true, clip: { x: 0, y: 0, width: +w, height: h } });
  await b.close();
  console.log(out, w + 'x' + h);
})();
