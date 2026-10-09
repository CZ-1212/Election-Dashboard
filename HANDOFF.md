# Election Dashboard – project handoff

Everything a new Claude account (or a new person) needs to pick this project up. Read this first, then `README.md`.

## 1. What this project is

An interactive **2026 General Election dashboard** for localnewsmatters.org (Local News Matters, Bay City News Foundation, Newspack theme). Live test page: https://localnewsmatters.org/new-ceh/

- A green map of 13 Northern California counties: Alameda, Contra Costa, Marin, Mendocino, Monterey, Napa, San Francisco, San Joaquin, San Mateo, Santa Clara, Santa Cruz, Solano, Sonoma.
- Click a county and its **ballot preview** (the article body of that county's page on the site, e.g. `/santa-clara-county-november-3-2026/`) opens in a scrollable window beside the map, with a search box.
- Top bar: "2026 General Election" title, ballot-box graphic, four buttons (Local coverage, Find your polling place, Register to vote, Ask a chatbot ballot questions → `/votemate/`).
- Three side buttons: State, Senate & Assembly, Counties list view (links in `election-dashboard/includes/settings.json`).
- "Key election dates" timeline footer (Oct 5, 19, 24, 31, Nov 3 Election Day, Nov 10).
- **Coverage links**: under each measure or race in the ballot window, a red "COVERAGE" line listing the newsroom's stories about it.
- Mobile layout, fast loading, no frameworks (vanilla JS/CSS).

## 2. Where everything lives

- **GitHub**: `cz-1212/election-dashboard`. All work is on the branch **`claude/lucid-mccarthy-sn7d5k`**. There is no `main` branch; that branch *is* the project. Add this repo to the new Claude account (or push it to a repo that account can reach).
- **This folder** (also shipped as `election-dashboard-project.zip`):

| Path | What it is |
| --- | --- |
| `election-dashboard/` | The WordPress plugin, v1.4.4 (zip this folder to install). |
| `election-dashboard/election-dashboard.php` | Plugin bootstrap: shortcode `[election_dashboard]`, asset loading, REST route `election-dashboard/v1/ballot/{county}`, ballot content extraction. |
| `election-dashboard/templates/dashboard.php` | The dashboard markup. |
| `election-dashboard/assets/css/election-dashboard.css`, `assets/js/election-dashboard.js` | All styling and behaviour (map clicks, ballot loading, search, coverage injection, fit-to-screen). |
| `election-dashboard/assets/img/` | `map-art.webp` (the green map), `county-map.svg` (clickable regions traced from the art), `ballot-box.png`, `nav/` side-button icons, `emblems/` county seals. |
| `election-dashboard/includes/counties.php` | The 13 counties and their ballot page URLs. |
| `election-dashboard/includes/settings.json` | Title, button labels/links, map captions, side nav, key dates. |
| `election-dashboard/includes/map.json` | SVG viewBox size for the map art. |
| `election-dashboard/data/ballot-index.json` | **The ballot index**: 1,163 items (179 measures, 508 contested races, 476 uncontested) across the 13 counties, with county, type, key (e.g. "Measure N", "Mayor, Alameda"), letter, jurisdiction, description, candidates. Built from `tools/source-data/Measure_Candidate_Backfill.xlsx`. |
| `election-dashboard/includes/class-ed-matcher.php` | The plain-text story matcher (see §4). Pure PHP, no WordPress dependencies, so it can be run or ported anywhere. |
| `election-dashboard/includes/class-ed-ai.php` | Optional Claude API classification step (off unless a key is set). |
| `election-dashboard/includes/class-ed-stories.php` | Store for story→ballot-item links (one `wp_options` row, `ed_story_links`). |
| `election-dashboard/includes/automation.php` | Match on publish, tag scan, queue processing. |
| `election-dashboard/includes/admin.php` | WP admin page "Election Dashboard → Story matches" (review queue, manual add, scan button, settings). |
| `demo/index.html` | **Standalone demo** of the dashboard with no WordPress at all (ballots inlined). Open in a browser. |
| `tools/build-demo.py` | Rebuilds `demo/index.html` from the plugin files (`--inline <path>` writes a single-file preview). |
| `tools/build-ballot-index.py` | Rebuilds `data/ballot-index.json` from the spreadsheet. |
| `tools/trace-map-art.py`, `tools/render-svg.js`, `tools/source-art/` | How the clickable SVG regions were traced from the Canva map art (OpenCV). |
| `design/` | The v8 prototype and mockups the layout follows. |
| `README.md` | Install, options, where ballots come from, story coverage, customising. |

## 3. How the ballot content works

Each county page on the site contains an embed (`.lnm-results-widget`) with this markup, which both the dashboard and the matcher rely on:

- `.race-box` → one measure or race
- `.measure-name` → "Measure N" (measure boxes); `.race-title` → "Mayor, Alameda" (race boxes)
- `.candidate-row` / `.candidate-name`
- `.lnm-county-banner`, `.search-wrap` → hidden inside the dashboard (the dashboard has its own title and search)

The plugin's REST route returns the page's article body plus the approved coverage links keyed by normalised measure name / race title. The JS appends `<div class="ed-coverage">` under the matching `.race-box` in the browser. The county pages themselves are never modified.

**Without the plugin**, the ballot HTML can be fetched from WordPress core's public REST API instead, no custom code needed:

```
https://localnewsmatters.org/wp-json/wp/v2/pages?slug=santa-clara-county-november-3-2026&_fields=content.rendered
```

## 4. How story matching works (the rules, so they can be reproduced anywhere)

Input: a story's title, body text, SEO tags, categories, publish date. Index: `data/ballot-index.json`.

1. **Skip** stories without the `election-2026` tag and stories published before **July 1, 2026** (June primary coverage reuses the same measure letters and candidate names).
2. **Detect counties** named in tags/categories/title/body (county names, plus city and district names mapped to their county via the index).
3. **Measure letters**: find "Measure N", "Measure CC", "Props D, E and F", "Measures A and B" (case-tolerant, a stop list for words like "Measure A" inside ordinary prose). A letter is **certain** when it appears with its jurisdiction (city, district or county name from the index) or the story's tags name that jurisdiction; a letter with no jurisdiction confirmation is **likely** (several counties share letters, e.g. Measure N exists in Santa Clara, Santa Cruz and others).
4. **Candidates**: a candidate's full name in the text or tags is **certain** for that race when the race's county is among the detected counties (first-name-only or surname-only never matches).
5. **Headline rule**: a race title whose place tokens appear in the headline, with county confirmed, is certain; office words ("mayor", "council", "district", "board", "school") are never treated as places.
6. Result: a list of `{item id, confidence: exact|likely, reason}`.
7. **Approval**: all matches certain → auto-approved. Any likely → waits for an editor. No match → listed as unmatched for manual linking.
8. **Optional AI step**: with a Claude API key, only stories with likely matches are sent to Claude (`claude-opus-5-5`, JSON-schema output, low effort) together with the shortlisted ballot items (capped at 450); Claude picks the right ones or none. It classifies, never writes. Cost roughly a few cents per story.

Validated behaviour (sample stories in the matcher test harness):

- "Los Gatos-Saratoga Measure N seeks $321M in bonds…" tagged Santa Clara County, Santa Cruz County, Los Gatos-Saratoga Union High School District → **certain** for Santa Clara Measure N *and* Santa Cruz Measure N (same district, both counties).
- "Voters asked to approve new business taxes… Hayward and Emeryville" → Alameda Measure CC and BB certain.
- "Measure N: what voters need to know" with no county → likely only, goes to review.
- Matcher speed: about 2 ms per story; 238 tagged stories scan in under a second.

## 5. Current state (October 9, 2026)

- Plugin v1.4.4 is installed and active on the live site; the dashboard renders on `/new-ceh/`. The first scan has run: approved links appear (e.g. Santa Clara Measure N → Los Gatos story).
- **Newspack's position** (Slack, Oct 7–8): custom plugins are allowed but "use at your own risk", must follow their Custom Development Guidelines, be tested on staging, installed during support hours, and they cannot roll back what a custom plugin does. They were concerned about AI changing content.
- **Decision**: do not keep a custom plugin on the site. Instead connect Claude to WordPress (WordPress connector / application password) and have Claude do the matching work from a Claude account, with the dashboard itself delivered without custom PHP.

## 6. The no-plugin plan (suggested, not yet built)

1. **Dashboard**: paste the standalone build into a Custom HTML block on the page. Start from `demo/index.html` / `tools/build-demo.py --inline`. Change the ballot loader in the JS to fetch county pages from WordPress core REST (`/wp-json/wp/v2/pages?slug=…`) instead of the plugin route. Host the images in the Media Library and point the HTML at those URLs.
2. **Coverage links**: keep the links as a small JSON object (`{ "santa-clara": { "measure": { "measure n": [ {url,title,date} ] }, "race": {…} } }`), either inlined in the HTML block or stored as a private page/JSON that the JS fetches. Claude, connected to WordPress, runs the matching rules in §4 against newly tagged stories (`/wp-json/wp/v2/posts?tags=<id>&after=…`) and updates that JSON. An editor approves anything uncertain in the chat before it is written. County pages are never edited.
3. **Reference code**: `class-ed-matcher.php` is the exact rule set; `data/ballot-index.json` is the index. Both can be given to Claude as project files.
4. **Deactivate and delete the plugin** on the live site once the HTML block version is in place (deactivation removes all its scheduled events; its only data is the `ed_story_links` option and its settings rows).

## 7. Moving to another Claude account

- Give the new account the repo (`cz-1212/election-dashboard`, branch `claude/lucid-mccarthy-sn7d5k`) or upload `election-dashboard-project.zip` to a Claude Project as files. The most useful files to attach to a Project are: `HANDOFF.md`, `README.md`, `election-dashboard/data/ballot-index.json`, `election-dashboard/includes/class-ed-matcher.php`, `election-dashboard/includes/settings.json`, `election-dashboard/includes/counties.php`, `demo/index.html`.
- Artifacts created in this account (dashboard preview, flow document, workflow poster) are tied to this account; they can all be regenerated from the repo (`tools/build-demo.py --inline` for the preview; the poster/flow HTML sources are not in the repo and would be recreated from this handoff if needed).
- **The WordPress key**: never paste it into a chat. Enter it only in the new account's WordPress connector settings (claude.ai → Settings → Connectors) or keep it in a password manager. If it was ever pasted into a chat, revoke it in WordPress (Users → Profile → Application Passwords) and issue a new one.
- Suggested first prompt in the new account:

> Read HANDOFF.md and README.md in this project. We are moving the Election Dashboard off the custom plugin: (1) build the dashboard as a standalone HTML block that loads county ballots from WordPress core REST, and (2) you will run the story-matching rules in HANDOFF.md §4 against new election-2026 stories through the WordPress connector and maintain the coverage-links JSON, asking me to approve anything uncertain.

## 8. Design decisions worth keeping

- Red is the only accent for emphasis (no yellow highlights); navy headings; Fira Sans body, Merriweather titles.
- Click to open a county (no hover preview); green "pop" fill on hover, no red/blue.
- Map and side buttons start centred; the ballot window slides in beside them.
- Ballot window hides the embed's own county banner and search box; one title only.
- Ballot text is small (13px) to fit more; search filters whole `.race-box` blocks.
- Phone layout: blue rule under the title, buttons stacked, bigger side buttons.
- Theme fights: the Newspack theme overrides button styles and adds a gap above the dashboard; the CSS uses `!important` on buttons and the JS measures and closes the gap (`top_gap`), so keep those if the HTML block version inherits the same theme.
