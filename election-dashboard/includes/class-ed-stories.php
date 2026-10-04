<?php
/**
 * Storage for story-to-ballot links.
 *
 * One WordPress option, ed_story_links, keyed by story URL:
 *   url => array( 'title' => '', 'post_id' => 0, 'date' => '', 'status' => 'approved'|'pending'|'rejected',
 *                 'items' => array( id => array( 'confidence' => '', 'reason' => '' ) ), 'source' => 'backfill'|'auto'|'manual' )
 * Approved links are what the ballot window shows.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ED_Stories {

	const OPTION = 'ed_story_links';

	public static function all() {
		$v = get_option( self::OPTION, array() );
		return is_array( $v ) ? $v : array();
	}

	public static function save( $all ) { update_option( self::OPTION, $all, false ); }

	public static function index() {
		static $idx = null;
		if ( null === $idx ) {
			$idx = json_decode( file_get_contents( ED_DIR . 'data/ballot-index.json' ), true );
			$idx['by_id'] = array();
			foreach ( $idx['items'] as $it ) { $idx['by_id'][ $it['id'] ] = $it; }
		}
		return $idx;
	}

	public static function item( $id ) { $idx = self::index(); return isset( $idx['by_id'][ $id ] ) ? $idx['by_id'][ $id ] : null; }

	/** Title and post id for a URL on this site; falls back to a title built from the slug. */
	public static function describe_url( $url ) {
		$post_id = function_exists( 'url_to_postid' ) ? url_to_postid( $url ) : 0;
		$title = $post_id ? html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) : '';
		$date  = $post_id ? get_the_date( 'Y-m-d', $post_id ) : '';
		if ( ! $title ) {
			$slug = preg_replace( '#.*/(\d{4}/\d{2}/\d{2}/)?#', '', rtrim( $url, '/' ) );
			$title = ucfirst( str_replace( '-', ' ', $slug ) );
			if ( preg_match( '#/(\d{4})/(\d{2})/(\d{2})/#', $url, $m ) ) { $date = $m[1] . '-' . $m[2] . '-' . $m[3]; }
		}
		return array( 'title' => $title, 'post_id' => $post_id, 'date' => $date );
	}

	/** Record proposed matches for a story (from the matcher / AI). Exact matches may be auto-approved. */
	public static function propose( $url, $matches, $meta = array() ) {
		$all = self::all();
		$row = isset( $all[ $url ] ) ? $all[ $url ] : array_merge( self::describe_url( $url ), array( 'items' => array(), 'status' => 'pending', 'source' => 'auto' ) );
		$row = array_merge( $row, $meta );
		$auto = (bool) get_option( 'ed_auto_approve_exact', 1 );
		$all_exact = (bool) $matches;
		foreach ( $matches as $m ) {
			$row['items'][ $m['id'] ] = array( 'confidence' => $m['confidence'], 'reason' => $m['reason'] );
			if ( 'exact' !== $m['confidence'] ) { $all_exact = false; }
		}
		if ( 'approved' !== $row['status'] ) {
			$row['status'] = ( $matches && $all_exact && $auto ) ? 'approved' : ( $matches ? 'pending' : 'unmatched' );
		}
		$row['checked'] = current_time( 'mysql' );
		$all[ $url ] = $row;
		self::save( $all );
		return $row;
	}

	public static function set_status( $url, $status, $keep_ids = null ) {
		$all = self::all();
		if ( ! isset( $all[ $url ] ) ) { return false; }
		if ( is_array( $keep_ids ) ) {
			$all[ $url ]['items'] = array_intersect_key( $all[ $url ]['items'], array_flip( $keep_ids ) );
		}
		$all[ $url ]['status'] = $status;
		$all[ $url ]['source'] = 'manual';
		self::save( $all );
		return true;
	}

	public static function remove( $url ) { $all = self::all(); unset( $all[ $url ] ); self::save( $all ); }

	/**
	 * Approved stories for one county, keyed the way the ballot window looks things up:
	 *   array( 'measure' => array( normalized measure name => array( array( 'url', 'title', 'date' ) ) ),
	 *          'race'    => array( normalized race name => ... ) )
	 */
	public static function for_county( $slug ) {
		$out = array( 'measure' => array(), 'race' => array() );
		foreach ( self::all() as $url => $row ) {
			if ( 'approved' !== $row['status'] ) { continue; }
			foreach ( $row['items'] as $id => $m ) {
				$it = self::item( $id );
				if ( ! $it || $it['county'] !== $slug ) { continue; }
				$bucket = 'measure' === $it['type'] ? 'measure' : 'race';
				$out[ $bucket ][ $it['keyNorm'] ][] = array( 'url' => $url, 'title' => html_entity_decode( $row['title'], ENT_QUOTES, 'UTF-8' ), 'date' => $row['date'] );
			}
		}
		foreach ( $out as &$bucket ) {
			foreach ( $bucket as &$list ) { usort( $list, function ( $a, $b ) { return strcmp( $b['date'], $a['date'] ); } ); }
		}
		return $out;
	}
}
