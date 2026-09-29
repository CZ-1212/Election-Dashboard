<?php
/**
 * Plugin Name: Election Dashboard
 * Description: Interactive county map — hover a county to preview its emblem and ballot, click to pin it. Use the [election_dashboard] shortcode.
 * Version:     1.0.0
 * Author:      Cal Metrics Consulting
 * License:     GPL-2.0-or-later
 * Text Domain: election-dashboard
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'ED_VERSION', '1.0.0' );
define( 'ED_DIR', plugin_dir_path( __FILE__ ) );
define( 'ED_URL', plugin_dir_url( __FILE__ ) );

/**
 * Returns the county configuration (see includes/counties.php).
 */
function ed_get_counties() {
	$counties = include ED_DIR . 'includes/counties.php';
	return apply_filters( 'election_dashboard_counties', $counties );
}

/**
 * Register assets. They are only enqueued on pages that render the shortcode.
 */
function ed_register_assets() {
	wp_register_style( 'election-dashboard', ED_URL . 'assets/css/election-dashboard.css', array(), ED_VERSION );
	wp_register_script( 'election-dashboard', ED_URL . 'assets/js/election-dashboard.js', array(), ED_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'ed_register_assets' );

/**
 * Build the JSON config the front-end script reads.
 */
function ed_build_config( $counties ) {
	$out = array();
	foreach ( $counties as $slug => $c ) {
		$ballot = isset( $c['ballot'] ) ? $c['ballot'] : array();
		$entry  = array(
			'name'           => $c['name'],
			'title'          => $c['title'],
			'emblem'         => ED_URL . 'assets/img/emblems/' . $c['emblem'] . '.webp',
			'emblemFallback' => ED_URL . 'assets/img/emblems/' . $c['emblem'] . '.png',
		);
		if ( isset( $ballot['type'] ) && 'iframe' === $ballot['type'] ) {
			$entry['type'] = 'iframe';
			$entry['src']  = $ballot['url'];
		} else {
			$entry['type'] = 'fetch';
			$entry['src']  = rest_url( 'election-dashboard/v1/ballot/' . $slug );
		}
		$out[ $slug ] = $entry;
	}
	return array( 'counties' => $out );
}

/**
 * [election_dashboard] shortcode.
 * Attributes: ballot_width (px), map_width (px), brand (URL of your own logo image to replace the built-in SVG).
 */
function ed_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'ballot_width' => '',
		'map_width'    => '',
		'brand'        => '',
	), $atts, 'election_dashboard' );

	wp_enqueue_style( 'election-dashboard' );
	wp_enqueue_script( 'election-dashboard' );

	$counties = ed_get_counties();
	$config   = ed_build_config( $counties );
	$map_svg  = file_get_contents( ED_DIR . 'assets/img/county-map.svg' );
	$brand    = $atts['brand'] ? esc_url( $atts['brand'] ) : ED_URL . 'assets/img/brand-2026.svg';

	$style = '';
	if ( $atts['ballot_width'] ) { $style .= '--ed-ballot-width:' . intval( $atts['ballot_width'] ) . 'px;'; }
	if ( $atts['map_width'] )    { $style .= '--ed-map-width:' . intval( $atts['map_width'] ) . 'px;'; }

	ob_start();
	include ED_DIR . 'templates/dashboard.php';
	return ob_get_clean();
}
add_shortcode( 'election_dashboard', 'ed_shortcode' );

/**
 * REST route that returns a county's ballot HTML on demand:
 *   GET /wp-json/election-dashboard/v1/ballot/{slug}
 */
function ed_register_rest() {
	register_rest_route( 'election-dashboard/v1', '/ballot/(?P<slug>[a-z0-9-]+)', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'ed_rest_ballot',
		'args'                => array( 'slug' => array( 'sanitize_callback' => 'sanitize_key' ) ),
	) );
}
add_action( 'rest_api_init', 'ed_register_rest' );

function ed_rest_ballot( WP_REST_Request $req ) {
	$slug     = $req['slug'];
	$counties = ed_get_counties();
	if ( ! isset( $counties[ $slug ] ) ) {
		return new WP_Error( 'ed_not_found', 'Unknown county', array( 'status' => 404 ) );
	}
	$html = ed_get_ballot_html( $slug, $counties[ $slug ] );
	$res  = new WP_REST_Response( array( 'slug' => $slug, 'html' => $html ) );
	$res->header( 'Cache-Control', 'public, max-age=300' );
	return $res;
}

/**
 * Resolve a county's ballot source to HTML.
 */
function ed_get_ballot_html( $slug, $county ) {
	$ballot = isset( $county['ballot'] ) ? $county['ballot'] : array();
	$type   = isset( $ballot['type'] ) ? $ballot['type'] : 'file';
	$html   = '';

	switch ( $type ) {
		case 'page':
			$post = get_post( intval( $ballot['id'] ) );
			if ( $post && 'publish' === $post->post_status ) {
				$html = apply_filters( 'the_content', $post->post_content );
			}
			break;
		case 'html':
			$html = $ballot['html'];
			break;
		case 'file':
		default:
			$file = isset( $ballot['file'] ) ? $ballot['file'] : $slug . '.html';
			$path = ED_DIR . 'ballots/' . basename( $file );
			if ( file_exists( $path ) ) { $html = file_get_contents( $path ); }
			break;
	}
	if ( '' === $html ) {
		$html = '<p class="ed-empty">Ballot preview coming soon.</p>';
	}
	return apply_filters( 'election_dashboard_ballot_html', $html, $slug, $county );
}
