# 2026 General Election Dashboard

Interactive county map for the 13 counties we cover. Hover a county to preview its emblem and ballot, click (or tap) to pin the ballot open. Built as a WordPress plugin with a standalone demo page.

```
election-dashboard/            <- the WordPress plugin (zip this folder to install)
  election-dashboard.php       <- shortcode, asset loading, REST route for ballots
  includes/counties.php        <- the 13 counties: names, emblems, where each ballot comes from
  ballots/<county>.html        <- ballot preview fragments (Mendocino is filled in; the rest are placeholders)
  templates/dashboard.php      <- the markup
  assets/css, assets/js        <- styles and the (dependency-free) interaction script
  assets/img/map-art.webp      <- the green county map (900 px, 18 KB)
  assets/img/county-map.svg    <- invisible clickable regions traced from that map, marker dots, and the "lift" layer
  assets/img/emblems/          <- 13 emblems as 320px WebP (about 30 KB each) with PNG fallbacks
  assets/img/nav/*.webp|png    <- the three side-button icons (labels are part of the artwork)
  assets/img/ballot-box.png    <- ballot box next to the title
  includes/settings.json       <- title, button labels and links, prompt text, key election dates
demo/index.html                <- open this in a browser to try it without WordPress
tools/build-demo.py            <- rebuilds demo/index.html (or a single shareable file with --inline)
tools/trace-map-art.py         <- re-traces the clickable regions if the map artwork changes
tools/render-svg.js            <- renders an SVG to PNG with the bundled browser (used by the tracer)
tools/source-art/              <- the original Canva SVG exports (map, icons, ballot box)
```

## Layout (v8)

Top bar with the title and four resource buttons; a three-column body with the county map, the centre panel (prompt, then the ballot once a county is clicked) and three stacked buttons for State, Senate & Assembly and Counties; and a "Key election dates" timeline along the bottom. On phones the columns stack and the timeline becomes a list. All wording, links and dates are in `election-dashboard/includes/settings.json`.

## How it behaves

* **Idle**: the map, a "Click a county to view your ballot" panel, and the three side buttons.
* **Hover** (mouse only): the county lifts off the map with a shadow, its marker grows and its name appears.
* **Click / tap**: the county's ballot fills the centre panel and its marker turns red. Click the same county again, the × button, or press Escape to go back to the prompt.
* **Ballot panel**: county emblem and title in the header, a "Full page" button that opens the county's own page in a new tab, a search box that filters sections as you type, and a body that scrolls inside the box.
* **Mobile** (under 900 px): everything stacks. A tap opens the ballot beneath the map and scrolls it into view.
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

   Optional attributes: `height="800"` (desktop frame height), `map_width="340"`, `nav_width="208"`.

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
* Button labels and links, the prompt text, and the key dates are in `includes/settings.json`.
* The three side-button icons and the ballot box come from the Canva SVGs in `tools/source-art/`, rendered to PNG/WebP in `assets/img/`. To change one, replace the SVG and re-render it with `node tools/render-svg.js in.svg out.png 528`.
* The lift effect (how far the county rises, its shadow) is the `.ed-lift` rule in the stylesheet.
* To replace the map, put the new SVG at `tools/source-art/county-map-green.svg` and run `python3 tools/trace-map-art.py`. It renders the drawing, finds each green county shape, and rebuilds the clickable regions and marker dots. If a county moves a lot, adjust its expected centre in the script's `COUNTIES` table. San Francisco is small, so the script gives it a larger round hit area.
* To regenerate the demo after editing the plugin: `python3 tools/build-demo.py`.
