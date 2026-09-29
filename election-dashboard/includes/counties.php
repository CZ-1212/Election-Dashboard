<?php
/**
 * County configuration for the Election Dashboard.
 *
 * One entry per county. Keys:
 *   name    - short name used in tooltips/labels
 *   title   - heading shown on the ballot panel
 *   emblem  - filename inside assets/img/emblems/ (webp + png of the same name are shipped)
 *   ballot  - where the ballot preview comes from. One of:
 *       [ 'type' => 'url',    'url'  => 'https://…' ]         a page on this site (content used directly) or elsewhere (fetched + cached)
 *       [ 'type' => 'file',   'file' => 'mendocino.html' ]   HTML fragment in the plugin's ballots/ folder
 *       [ 'type' => 'page',   'id'   => 123 ]                 a WordPress page/post; its content is loaded on demand
 *       [ 'type' => 'iframe', 'url'  => 'https://…' ]         any page or PDF, embedded in a scrollable frame
 *       [ 'type' => 'html',   'html' => '<div>…</div>' ]      inline HTML string
 *
 * Developers can override everything with the `election_dashboard_counties` filter.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

return array(
	'mendocino'     => array( 'name' => 'Mendocino',     'title' => 'Mendocino County',                 'emblem' => 'mendocino',     'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/mendocino-county-november-3-2026/' ) ),
	'sonoma'        => array( 'name' => 'Sonoma',        'title' => 'Sonoma County',                    'emblem' => 'sonoma',        'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/sonoma-county-november-3-2026/' ) ),
	'napa'          => array( 'name' => 'Napa',          'title' => 'Napa County',                      'emblem' => 'napa',          'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/napa-county-november-3-2026/' ) ),
	'solano'        => array( 'name' => 'Solano',        'title' => 'Solano County',                    'emblem' => 'solano',        'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/solano-county-november-3-2026/' ) ),
	'marin'         => array( 'name' => 'Marin',         'title' => 'Marin County',                     'emblem' => 'marin',         'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/marin-county-november-3-2026-2/' ) ),
	'contra-costa'  => array( 'name' => 'Contra Costa',  'title' => 'Contra Costa County',              'emblem' => 'contra-costa',  'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/contra-costa-county-november-3-2026/' ) ),
	'san-francisco' => array( 'name' => 'San Francisco', 'title' => 'City and County of San Francisco', 'emblem' => 'san-francisco', 'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/san-francisco-november-3-2026/' ) ),
	'alameda'       => array( 'name' => 'Alameda',       'title' => 'Alameda County',                   'emblem' => 'alameda',       'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/alameda-county-november-3-2026/' ) ),
	'san-mateo'     => array( 'name' => 'San Mateo',     'title' => 'San Mateo County',                 'emblem' => 'san-mateo',     'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/san-mateo-november-3-2026/' ) ),
	'san-joaquin'   => array( 'name' => 'San Joaquin',   'title' => 'San Joaquin County',               'emblem' => 'san-joaquin',   'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/san-joaquin-november-3-2026-2/' ) ),
	'santa-clara'   => array( 'name' => 'Santa Clara',   'title' => 'Santa Clara County',               'emblem' => 'santa-clara',   'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/santa-clara-november-3-2026/' ) ),
	'santa-cruz'    => array( 'name' => 'Santa Cruz',    'title' => 'Santa Cruz County',                'emblem' => 'santa-cruz',    'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/santa-cruz-november-3-2026/' ) ),
	'monterey'      => array( 'name' => 'Monterey',      'title' => 'Monterey County',                  'emblem' => 'monterey',      'ballot' => array( 'type' => 'url', 'url' => 'https://localnewsmatters.org/monterey-county-november-3-2026/' ) ),
);
