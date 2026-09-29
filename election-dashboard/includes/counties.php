<?php
/**
 * County configuration for the Election Dashboard.
 *
 * One entry per county. Keys:
 *   name    - short name used in tooltips/labels
 *   title   - heading shown on the ballot panel
 *   emblem  - filename inside assets/img/emblems/ (webp + png of the same name are shipped)
 *   ballot  - where the ballot preview comes from. One of:
 *       [ 'type' => 'file',   'file' => 'mendocino.html' ]   HTML fragment in the plugin's ballots/ folder
 *       [ 'type' => 'page',   'id'   => 123 ]                 a WordPress page/post; its content is loaded on demand
 *       [ 'type' => 'iframe', 'url'  => 'https://…' ]         any page or PDF, embedded in a scrollable frame
 *       [ 'type' => 'html',   'html' => '<div>…</div>' ]      inline HTML string
 *
 * Developers can override everything with the `election_dashboard_counties` filter.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

return array(
	'mendocino'     => array( 'name' => 'Mendocino',     'title' => 'Mendocino County',                 'emblem' => 'mendocino',     'ballot' => array( 'type' => 'file', 'file' => 'mendocino.html' ) ),
	'sonoma'        => array( 'name' => 'Sonoma',        'title' => 'Sonoma County',                    'emblem' => 'sonoma',        'ballot' => array( 'type' => 'file', 'file' => 'sonoma.html' ) ),
	'napa'          => array( 'name' => 'Napa',          'title' => 'Napa County',                      'emblem' => 'napa',          'ballot' => array( 'type' => 'file', 'file' => 'napa.html' ) ),
	'solano'        => array( 'name' => 'Solano',        'title' => 'Solano County',                    'emblem' => 'solano',        'ballot' => array( 'type' => 'file', 'file' => 'solano.html' ) ),
	'marin'         => array( 'name' => 'Marin',         'title' => 'Marin County',                     'emblem' => 'marin',         'ballot' => array( 'type' => 'file', 'file' => 'marin.html' ) ),
	'contra-costa'  => array( 'name' => 'Contra Costa',  'title' => 'Contra Costa County',              'emblem' => 'contra-costa',  'ballot' => array( 'type' => 'file', 'file' => 'contra-costa.html' ) ),
	'san-francisco' => array( 'name' => 'San Francisco', 'title' => 'City and County of San Francisco', 'emblem' => 'san-francisco', 'ballot' => array( 'type' => 'file', 'file' => 'san-francisco.html' ) ),
	'alameda'       => array( 'name' => 'Alameda',       'title' => 'Alameda County',                   'emblem' => 'alameda',       'ballot' => array( 'type' => 'file', 'file' => 'alameda.html' ) ),
	'san-mateo'     => array( 'name' => 'San Mateo',     'title' => 'San Mateo County',                 'emblem' => 'san-mateo',     'ballot' => array( 'type' => 'file', 'file' => 'san-mateo.html' ) ),
	'san-joaquin'   => array( 'name' => 'San Joaquin',   'title' => 'San Joaquin County',               'emblem' => 'san-joaquin',   'ballot' => array( 'type' => 'file', 'file' => 'san-joaquin.html' ) ),
	'santa-clara'   => array( 'name' => 'Santa Clara',   'title' => 'Santa Clara County',               'emblem' => 'santa-clara',   'ballot' => array( 'type' => 'file', 'file' => 'santa-clara.html' ) ),
	'santa-cruz'    => array( 'name' => 'Santa Cruz',    'title' => 'Santa Cruz County',                'emblem' => 'santa-cruz',    'ballot' => array( 'type' => 'file', 'file' => 'santa-cruz.html' ) ),
	'monterey'      => array( 'name' => 'Monterey',      'title' => 'Monterey County',                  'emblem' => 'monterey',      'ballot' => array( 'type' => 'file', 'file' => 'monterey.html' ) ),
);
