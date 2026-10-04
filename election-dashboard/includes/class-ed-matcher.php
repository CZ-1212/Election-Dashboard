<?php
/**
 * Decides which ballot items (measures, races) a story is about.
 * Pure PHP, no WordPress dependencies, so it can be tested from the command line.
 *
 * Input story: array( 'title' => '', 'text' => '', 'url' => '', 'tags' => array( 'Election 2026', 'Measure EE', ... ), 'counties' => array( 'alameda', ... ) )
 * Tags carry weight: the newsroom tags stories with the measure or candidate name for SEO.
 * Returns: array of array( 'id' => item id, 'confidence' => 'exact'|'likely', 'reason' => '' )
 */
class ED_Matcher {

	/** County display names and the short forms people use in copy. */
	public static $county_names = array(
		'alameda'       => array( 'alameda county' ),
		'contra-costa'  => array( 'contra costa' ),
		'marin'         => array( 'marin county', 'marin' ),
		'mendocino'     => array( 'mendocino' ),
		'monterey'      => array( 'monterey county', 'monterey' ),
		'napa'          => array( 'napa county', 'napa' ),
		'san-francisco' => array( 'san francisco', 'sf' ),
		'san-joaquin'   => array( 'san joaquin' ),
		'san-mateo'     => array( 'san mateo county', 'san mateo' ),
		'santa-clara'   => array( 'santa clara county', 'santa clara' ),
		'santa-cruz'    => array( 'santa cruz' ),
		'solano'        => array( 'solano' ),
		'sonoma'        => array( 'sonoma county', 'sonoma' ),
	);

	public static function norm( $s ) {
		$s = strtolower( (string) $s );
		if ( function_exists( 'iconv' ) ) { $t = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $s ); if ( false !== $t ) { $s = $t; } }
		$s = preg_replace( '/[^a-z0-9]+/', ' ', $s );
		return trim( preg_replace( '/\s+/', ' ', $s ) );
	}

	/** Words in a jurisdiction that identify the place: "City Of Albany" -> "albany", "Dublin USD" -> "dublin". */
	public static function place_tokens( $jurisdiction ) {
		$n = self::norm( $jurisdiction );
		$stop = array( 'city', 'of', 'town', 'county', 'usd', 'unified', 'school', 'district', 'joint', 'union', 'elementary', 'high', 'community', 'college', 'the', 'and', 'area', 'jurisdiction', 'multicounty', 'special', 'services', 'fire', 'protection', 'water', 'sanitary', 'park', 'recreation', 'healthcare', 'transit', 'library',
			// office words are not places
			'san', 'santa', 'los', 'las', 'el', 'la', 'de', 'del', 'st', 'saint', 'mt', 'mount', 'new', 'north', 'south', 'east', 'west', 'central',
			'mayor', 'council', 'councilmember', 'member', 'members', 'director', 'directors', 'trustee', 'trustees', 'board', 'education', 'governing', 'supervisor', 'supervisors', 'seat', 'ward', 'term', 'full', 'short', 'large', 'clerk', 'treasurer', 'auditor', 'judge', 'superior', 'court', 'office', 'commissioner', 'commissioners', 'rent', 'stabilization', 'sanitation', 'utility', 'municipal', 'regional', 'dist', 'no', 'zone', 'division', 'division', 'elect', 'elected', 'districtwide', 'at', 'amp' );
		$out = array();
		foreach ( explode( ' ', $n ) as $w ) {
			if ( strlen( $w ) > 2 && ! in_array( $w, $stop, true ) ) { $out[] = $w; }
		}
		return $out;
	}

	/** Candidate name without nicknames, middle initials and suffixes: 'Suzanne "Sue" Lee Chan' -> 'suzanne lee chan'. */
	public static function name_forms( $name ) {
		$n = preg_replace( '/["“”].*?["“”]/u', ' ', (string) $name );
		$n = preg_replace( '/\b(jr|sr|ii|iii|iv)\b\.?/i', ' ', $n );
		$n = self::norm( $n );
		$parts = array_values( array_filter( explode( ' ', $n ), function ( $p ) { return strlen( $p ) > 1; } ) );
		$forms = array();
		if ( count( $parts ) >= 2 ) {
			$forms[] = implode( ' ', $parts );                                   // full name
			$forms[] = $parts[0] . ' ' . $parts[ count( $parts ) - 1 ];           // first + last
		}
		return array_unique( $forms );
	}

	/** Measure / proposition identifiers in the text, including lists: "Measure B", "Props D, E and F" -> B, D, E, F. */
	public static function measure_letters( $text ) {
		$out = array();
		$words = array( 'a', 'i', 'an', 'is', 'in', 'on', 'to', 'of', 'or', 'at', 'as', 'by', 'it', 'if', 'so', 'be', 'up', 'no', 'we', 'he', 'do', 'go', 'the', 'and', 'for', 'has', 'was', 'had', 'its', 'not', 'but', 'can', 'may', 'now', 'our', 'out', 'own', 'two', 'who', 'you', 'all', 'any', 'are', 'did', 'due', 'get', 'how', 'let', 'new', 'one', 'put', 'say', 'set', 'yet' );
		if ( preg_match_all( '/\b(?:Measures?|Props?\.?|Propositions?)\s+((?:[A-Za-z]{1,3}|\d{1,3})(?:\s*(?:,|and|&|or)\s*(?:[A-Za-z]{1,3}|\d{1,3}))*)\b/i', (string) $text, $mm ) ) {
			foreach ( $mm[1] as $list ) {
				foreach ( preg_split( '/\s*(?:,|and|&|or)\s*/i', $list ) as $tok ) {
					$tok = trim( $tok );
					if ( '' === $tok ) { continue; }
					// a lowercase token is only a measure letter if it is not an ordinary word ("measure to", "measure a")
					if ( ctype_lower( $tok ) && in_array( $tok, $words, true ) ) { continue; }
					$out[] = strtoupper( $tok );
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Which counties the story is about: explicit hints first, then names found in the text. */
	public static function detect_counties( $story ) {
		$found = array();
		if ( ! empty( $story['counties'] ) ) { $found = array_values( $story['counties'] ); }
		$tags = isset( $story['tags'] ) ? implode( ' . ', (array) $story['tags'] ) : '';
		$hay = ' ' . self::norm( $story['title'] . ' ' . $tags . ' ' . $story['text'] . ' ' . $story['url'] ) . ' ';
		foreach ( self::$county_names as $slug => $names ) {
			foreach ( $names as $n ) {
				if ( strlen( $n ) <= 2 ) { continue; }
				if ( false !== strpos( $hay, ' ' . $n . ' ' ) ) { $found[] = $slug; break; }
			}
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * @param array $story  title, text, url, counties
	 * @param array $index  decoded ballot-index.json
	 */
	public static function match( $story, $index ) {
		$tags  = isset( $story['tags'] ) ? implode( ' . ', (array) $story['tags'] ) : '';
		$story['title'] = trim( (string) $story['title'] );
		$hay   = ' ' . self::norm( $story['title'] . ' ' . $tags . ' ' . $story['text'] ) . ' ';
		$title = ' ' . self::norm( $story['title'] . ' ' . $tags ) . ' ';   // headline and tags: the strong signals
		$counties = self::detect_counties( $story );
		$items = $index['items'];
		$in_scope = $counties ? array_filter( $items, function ( $i ) use ( $counties ) { return in_array( $i['county'], $counties, true ); } ) : $items;

		$results = array();

		// --- measures: "Measure X" / "Prop X" tokens, confirmed by the jurisdiction or county ---
		$letters = self::measure_letters( $story['title'] . ' ' . $tags . ' ' . $story['text'] );
		if ( $letters ) {
			foreach ( $in_scope as $it ) {
				if ( 'measure' !== $it['type'] || ! in_array( strtoupper( $it['letter'] ), $letters, true ) ) { continue; }
				$place = self::place_tokens( $it['jurisdiction'] );
				$place_hit = false;
				foreach ( $place as $p ) { if ( false !== strpos( $hay, ' ' . $p . ' ' ) ) { $place_hit = true; break; } }
				$county_hit = false;
				foreach ( self::$county_names[ $it['county'] ] as $n ) { if ( false !== strpos( $hay, ' ' . $n . ' ' ) ) { $county_hit = true; break; } }
				$same_letter_in_county = 0;
				foreach ( $in_scope as $o ) { if ( 'measure' === $o['type'] && $o['county'] === $it['county'] && strtoupper( $o['letter'] ) === strtoupper( $it['letter'] ) ) { $same_letter_in_county++; } }
				if ( $place_hit || ( $county_hit && 1 === $same_letter_in_county ) ) {
					$results[ $it['id'] ] = array( 'id' => $it['id'], 'confidence' => 'exact', 'reason' => 'Measure ' . $it['letter'] . ' named with ' . ( $place_hit ? $it['jurisdiction'] : 'the county' ) );
				} elseif ( $county_hit || 1 === count( $counties ) ) {
					$results[ $it['id'] ] = array( 'id' => $it['id'], 'confidence' => 'likely', 'reason' => 'Measure ' . $it['letter'] . ' named; jurisdiction not confirmed' );
				} elseif ( ! $counties ) {
					$results[ $it['id'] ] = array( 'id' => $it['id'], 'confidence' => 'likely', 'reason' => 'Measure ' . $it['letter'] . ' named; county not identified (same letter exists in other counties)' );
				}
			}
		}

		// --- races: a candidate's full name in the text is a strong signal ---
		foreach ( $in_scope as $it ) {
			if ( empty( $it['candidates'] ) ) { continue; }
			$hits = array();
			foreach ( $it['candidates'] as $cand ) {
				foreach ( self::name_forms( $cand ) as $form ) {
					if ( false !== strpos( $hay, ' ' . $form . ' ' ) ) { $hits[] = $cand; break; }
				}
			}
			if ( ! $hits ) { continue; }
			$in_title = false;
			foreach ( $hits as $h ) { foreach ( self::name_forms( $h ) as $f ) { if ( false !== strpos( $title, ' ' . $f . ' ' ) ) { $in_title = true; } } }
			$race_place = self::place_tokens( $it['key'] );
			$place_hit = false;
			foreach ( $race_place as $p ) { if ( false !== strpos( $hay, ' ' . $p . ' ' ) ) { $place_hit = true; break; } }
			$conf = ( count( $hits ) >= 2 || $in_title || ( $place_hit && count( $hits ) >= 1 ) ) ? 'exact' : 'likely';
			$results[ $it['id'] ] = array( 'id' => $it['id'], 'confidence' => $conf, 'reason' => 'Names ' . implode( ', ', array_slice( $hits, 0, 3 ) ), 'candidates' => $hits );
		}

		// --- races named by title: "Richmond mayoral", "Novato City Council" (only when the county is known) ---
		foreach ( $counties ? $in_scope : array() as $it ) {
			if ( 'race' !== $it['type'] || isset( $results[ $it['id'] ] ) ) { continue; }
			$k = self::norm( $it['key'] );
			$place = self::place_tokens( $it['key'] );
			$office = '';
			if ( preg_match( '/\b(mayor|mayoral)\b/', $k ) ) { $office = 'mayor'; }
			elseif ( preg_match( '/\bcity council\b/', $k ) ) { $office = 'city council'; }
			elseif ( preg_match( '/\bsupervisor\b/', $k ) ) { $office = 'supervisor'; }
			if ( ! $office || ! $place ) { continue; }
			$place_hit = false;
			foreach ( $place as $p ) { if ( false !== strpos( $title, ' ' . $p . ' ' ) ) { $place_hit = true; break; } }
			$office_hit = ( 'mayor' === $office && preg_match( '/\bmayor(al)?\b/', $title ) )
				|| ( 'city council' === $office && preg_match( '/\bcity council\b|\bcouncil\b/', $title ) )
				|| ( 'supervisor' === $office && preg_match( '/\bsupervisor/', $title ) );
			if ( $place_hit && $office_hit ) {
				$results[ $it['id'] ] = array( 'id' => $it['id'], 'confidence' => 'likely', 'reason' => 'Headline names the ' . $office . ' race in ' . implode( ' ', $place ) );
			}
		}

		usort( $results, function ( $a, $b ) { return strcmp( $a['confidence'], $b['confidence'] ); } ); // "exact" sorts before "likely"
		return array_values( $results );
	}
}
