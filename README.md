# 2026 General Election Dashboard

Interactive county map for the 13 counties we cover. Hover a county to preview its emblem and ballot, click (or tap) to pin the ballot open. Built as a WordPress plugin with a standalone demo page.

```
election-dashboard/            <- the WordPress plugin (zip this folder to install)
  election-dashboard.php       <- shortcode, asset loading, REST route for ballots
  includes/counties.php        <- the 13 counties: names, emblems, where each ballot comes from
  ballots/<county>.html        <- ballot preview fragments (Mendocino is filled in; the rest are placeholders)
  templates/dashboard.php      <- the markup
  assets/css, assets/js        <- styles and the (dependency-free) interaction script
  assets/img/map-art.webp      <- the hand-drawn county map artwork (900 px, 130 KB)
  assets/img/county-map.svg    <- invisible clickable regions traced from that artwork, plus the "lift" layer
  assets/img/emblems/          <- 13 emblems as 320px WebP (about 30 KB each) with PNG fallbacks
  assets/img/brand-2026.webp   <- "2026 General Election" logo (PNG copy alongside)
demo/index.html                <- open this in a browser to try it without WordPress
tools/build-demo.py            <- rebuilds demo/index.html (or a single shareable file with --inline)
tools/trace-map-art.py         <- re-traces the clickable regions if the map artwork changes
tools/source-art/              <- the original artwork files
```

## How it behaves

* **Idle**: map and logo sit close together in the middle, with the "Hover over your county" hint.
* **Hover** (mouse only): the county lifts off the map with a shadow, its emblem pops in with an arrow, the map slides left and the logo slides right to make room, and the ballot panel slides open in between. Moving the mouse away from the whole dashboard closes it again.
* **Click / tap**: pins the county so the ballot stays open. Hovering another county while pinned gives a temporary peek, then snaps back. Click the same county again, the × button, or press Escape to close.
* **Ballot panel**: county emblem and title in the header, a "Full page" button that opens the county's own page in a new tab, a search box that filters sections as you type, and a body that scrolls inside the box.
* **Mobile** (under 900 px): logo, hint, map and ballot stack vertically. The hint says "Tap your county", a tap pins the ballot beneath the map and scrolls it into view.
* **Keyboard / screen readers**: counties are tabbable buttons, Enter/Space pins, status is announced.
* **Deep links**: `?county=mendocino` or `#county=mendocino` opens that county on load.

## Performance

* First load is roughly 40 KB of CSS + JS + inline SVG map, plus the logo SVG. No libraries, no web fonts.
* Emblems are not loaded until the visitor first moves toward the map (or the browser goes idle), and each ballot is fetched only when its county is opened and then cached. In WordPress the ballots come from a REST endpoint with a 5-minute cache header.

## Installing in WordPress

1. Zip the `election-dashboard/` folder and upload it under **Plugins → Add New → Upload Plugin**, then activate it.
2. Put the shortcode on any page:

   ```
   [election_dashboard]
   ```

   Optional attributes: `ballot_width="440"`, `map_width="340"`, `brand="https://…/your-logo.png"` (to use your own logo export instead of the built-in SVG).

3. A full-width page template with no sidebar works best, since the open layout is about 1,150 px wide.

## Where the ballots come from

Every county in `includes/counties.php` points at its ballot page on localnewsmatters.org, for example:

```php
'mendocino' => array( 'name' => 'Mendocino', 'title' => 'Mendocino County', 'emblem' => 'mendocino',
    'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/mendocino-county-november-3-2026/' ) ),
```

When a visitor opens a county, the plugin loads that page's article content into the ballot window (not the site header, menus or footer). If the dashboard runs on localnewsmatters.org itself, the content comes straight from the WordPress database with no extra request. If it runs on a different site, the page is fetched, its article body extracted, and the result cached for 10 minutes. Headings in the page become searchable sections automatically.

If you would rather show the page exactly as it looks on the site, including its own header, switch a county to `'type' => 'iframe'` with the same URL. It then loads in a scrollable frame inside the ballot window.

Other options for the `ballot` entry:

| Your ballot previews are… | Use |
| --- | --- |
| A page URL (default) | `['type' => 'url', 'url' => 'https://…']` — article content, loaded on demand |
| HTML you can paste | `['type' => 'file', 'file' => 'mendocino.html']` — drop the HTML into `ballots/` |
| WordPress pages | `['type' => 'page', 'id' => 123]` — the page's content is loaded on demand |
| Pages or PDFs at a URL | `['type' => 'iframe', 'url' => 'https://…']` — embedded in a scrollable frame |
| A short inline string | `['type' => 'html', 'html' => '<div>…</div>']` |

Search works on any page with headings. For hand-written fragments you can also use this markup, which the CSS styles to match the mockup:

```html
<div class="ed-group">
  <h3>City of Fort Bragg</h3>
  <div class="ed-contest">
    <h4>Measure B</h4>
    <p>Fort Bragg Fire Protection Measure. …</p>
    <p class="ed-result">Results: Yes 61% · No 39%</p>   <!-- optional, for election night -->
  </div>
</div>
```

Content with no headings and no `.ed-contest` blocks (an iframe, a single image) still displays; the search box just hides itself.

Developers can also change the county list or the ballot HTML with the `election_dashboard_counties` and `election_dashboard_ballot_html` filters.

## Customising the look

* Colours, widths and animation speed are CSS variables at the top of `assets/css/election-dashboard.css` (`--ed-navy`, `--ed-ballot-width`, `--ed-speed`, …).
* The lift effect (how far the county rises, its shadow) is the `.ed-lift` rule in the stylesheet.
* To replace the map drawing, put the new file at `tools/source-art/county-map-art.webp` and run `python3 tools/trace-map-art.py`. It finds the blue areas between the red outlines and rebuilds the clickable regions. If a county moves a lot, adjust its expected centre in the script's `COUNTIES` table. San Francisco is tiny in the drawing, so the script gives it a larger round hit area.
* To replace the logo, pass `brand="…"` to the shortcode or overwrite `assets/img/brand-2026.webp`.
* To regenerate the demo after editing the plugin: `python3 tools/build-demo.py`.
