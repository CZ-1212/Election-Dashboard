<?php
/**
 * Automatic story matching.
 *  - When a post is published (or updated) with the election tag, it is queued for matching.
 *  - A daily scan catches stories tagged later and, on first run, the whole tag archive.
 *  - The plain matcher runs first; the Claude step runs only when a key is set and the matcher is unsure.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ED_Automation {

	public static function tag_slug() { return sanitize_title( get_option( 'ed_election_tag', 'election-2026' ) ); }

	public static function init() {
		add_action( 'save_post_post', array( __CLASS__, 'on_save' ), 20, 3 );
		add_action( 'ed_match_story', array( __CLASS__, 'match_post' ), 10, 1 );
		add_action( 'ed_daily_scan', array( __CLASS__, 'scan' ) );
		add_action( 'ed_scan_batch', array( __CLASS__, 'scan_batch' ), 10, 2 );
		if ( ! wp_next_scheduled( 'ed_daily_scan' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ed_daily_scan' ); }
	}

	public static function has_tag( $post_id ) { return has_term( self::tag_slug(), 'post_tag', $post_id ); }

	public static function on_save( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) { return; }
		if ( ! self::has_tag( $post_id ) ) { return; }
		$url = get_permalink( $post_id );
		$all = ED_Stories::all();
		if ( isset( $all[ $url ] ) && in_array( $all[ $url ]['source'], array( 'manual' ), true ) ) { return; } // an editor decided; leave it
		if ( ! wp_next_scheduled( 'ed_match_story', array( $post_id ) ) ) {
			wp_schedule_single_event( time() + 5, 'ed_match_story', array( $post_id ) );
		}
	}

	/** Build the matcher's view of a post. */
	public static function story_for_post( $post_id ) {
		$post = get_post( $post_id );
		$tags = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );
		$cats = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'names' ) );
		$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
		// counties named in categories or tags are reliable hints
		$counties = ED_Matcher::detect_counties( array( 'title' => implode( ' . ', array_merge( (array) $tags, (array) $cats ) ), 'text' => '', 'url' => '' ) );
		return array(
			'title'    => get_the_title( $post_id ),
			'text'     => $post->post_excerpt . "\n" . $content,
			'url'      => get_permalink( $post_id ),
			'tags'     => array_merge( (array) $tags, (array) $cats ),
			'counties' => $counties,
			'post_id'  => $post_id,
			'date'     => get_the_date( 'Y-m-d', $post_id ),
		);
	}

	/** Match one post and store the result. Returns the stored row. */
	public static function match_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) { return null; }
		$story = self::story_for_post( $post_id );
		$index = ED_Stories::index();
		$matches = ED_Matcher::match( $story, $index );

		$all_exact = (bool) $matches;
		foreach ( $matches as $m ) { if ( 'exact' !== $m['confidence'] ) { $all_exact = false; } }

		$ai_note = '';
		if ( ! $all_exact && ED_AI::enabled() ) {
			$counties = ED_Matcher::detect_counties( $story );
			$scope = array();
			foreach ( $index['items'] as $it ) { if ( ! $counties || in_array( $it['county'], $counties, true ) ) { $scope[] = $it; } }
			if ( count( $scope ) > 450 ) { $scope = array_slice( $scope, 0, 450 ); } // whole-region stories: keep the request bounded
			$ai = ED_AI::classify( $story, $scope );
			if ( is_wp_error( $ai ) ) {
				$ai_note = $ai->get_error_message();
			} else {
				// AI decides among the uncertain ones; the matcher's exact hits stay
				$merged = array();
				foreach ( $matches as $m ) { if ( 'exact' === $m['confidence'] ) { $merged[ $m['id'] ] = $m; } }
				foreach ( $ai as $m ) { if ( ! isset( $merged[ $m['id'] ] ) ) { $merged[ $m['id'] ] = $m; } }
				$matches = array_values( $merged );
			}
		}
		$row = ED_Stories::propose( $story['url'], $matches, array( 'title' => $story['title'], 'post_id' => $post_id, 'date' => $story['date'], 'source' => 'auto', 'note' => $ai_note ) );
		return $row;
	}

	/** Daily: queue tagged posts that have never been matched (first run covers the whole tag archive). */
	public static function scan() {
		$all = ED_Stories::all();
		$q = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'tag' => self::tag_slug(), 'posts_per_page' => 500, 'fields' => 'ids', 'no_found_rows' => true ) );
		$todo = array();
		foreach ( $q->posts as $pid ) {
			$url = get_permalink( $pid );
			if ( ! isset( $all[ $url ] ) ) { $todo[] = $pid; }
		}
		update_option( 'ed_last_scan', current_time( 'mysql' ) . ' · ' . count( $q->posts ) . ' tagged stories, ' . count( $todo ) . ' new' );
		foreach ( array_chunk( $todo, 15 ) as $i => $chunk ) {
			wp_schedule_single_event( time() + 10 + 60 * $i, 'ed_scan_batch', array( $chunk, $i ) );
		}
		return count( $todo );
	}

	public static function scan_batch( $ids, $i ) { foreach ( (array) $ids as $pid ) { self::match_post( $pid ); } }
}
ED_Automation::init();
