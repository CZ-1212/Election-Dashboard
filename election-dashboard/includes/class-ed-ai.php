<?php
/**
 * Optional AI step: asks Claude which ballot items a story is about, choosing only from the
 * candidate list we give it. Used when the plain matcher is unsure or finds nothing.
 * Calls the Claude API over HTTP through WordPress's HTTP layer (no Composer in a plugin).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ED_AI {

	public static function api_key() {
		if ( defined( 'ED_ANTHROPIC_API_KEY' ) && ED_ANTHROPIC_API_KEY ) { return ED_ANTHROPIC_API_KEY; }
		return trim( (string) get_option( 'ed_anthropic_api_key', '' ) );
	}

	public static function enabled() { return '' !== self::api_key(); }

	/**
	 * @param array $story  title, text, url, tags
	 * @param array $items  ballot items in scope (id, county, type, key, jurisdiction, candidates, desc)
	 * @return array|WP_Error  list of array( 'id', 'confidence' => 'exact'|'likely', 'reason' )
	 */
	public static function classify( $story, $items ) {
		$key = self::api_key();
		if ( ! $key ) { return new WP_Error( 'ed_no_key', 'No Claude API key configured.' ); }
		if ( ! $items ) { return array(); }

		$list = array();
		foreach ( $items as $it ) {
			$line = $it['id'] . ' | ' . $it['county'] . ' | ' . $it['type'] . ' | ' . $it['key'];
			if ( ! empty( $it['jurisdiction'] ) ) { $line .= ' (' . $it['jurisdiction'] . ')'; }
			if ( ! empty( $it['candidates'] ) ) { $line .= ' | candidates: ' . implode( ', ', array_slice( $it['candidates'], 0, 12 ) ); }
			if ( ! empty( $it['desc'] ) ) { $line .= ' | ' . mb_substr( $it['desc'], 0, 160 ); }
			$list[] = $line;
		}
		$text = mb_substr( (string) $story['text'], 0, 12000 );
		$prompt = "You match local news stories to the ballot items they are about.\n\n"
			. "STORY\nHeadline: " . $story['title'] . "\nTags: " . implode( ', ', (array) $story['tags'] ) . "\nURL: " . $story['url'] . "\n\n" . $text . "\n\n"
			. "BALLOT ITEMS (id | county | type | name | details)\n" . implode( "\n", $list ) . "\n\n"
			. "Return the ids of the ballot items this story is substantially about: a measure the story explains or reports on, or a race the story covers (a candidate profile, a race overview, an endorsement or results story). "
			. "Do not include items that are only mentioned in passing. If the same measure letter exists in several counties, use the cities, districts and county named in the story to pick the right one; if you cannot tell, return it with confidence \"likely\" and say why. Return an empty list if none apply.";

		$body = array(
			'model'      => get_option( 'ed_ai_model', 'claude-opus-5-5' ),
			'max_tokens' => 2048,
			'output_config' => array(
				'effort' => 'low',
				'format' => array(
					'type'   => 'json_schema',
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'matches' => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'id'         => array( 'type' => 'string' ),
										'confidence' => array( 'type' => 'string', 'enum' => array( 'exact', 'likely' ) ),
										'reason'     => array( 'type' => 'string' ),
									),
									'required'             => array( 'id', 'confidence', 'reason' ),
									'additionalProperties' => false,
								),
							),
						),
						'required'             => array( 'matches' ),
						'additionalProperties' => false,
					),
				),
			),
			'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ),
		);

		$res = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'timeout' => 60,
			'headers' => array(
				'content-type'      => 'application/json',
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
			),
			'body' => wp_json_encode( $body ),
		) );
		if ( is_wp_error( $res ) ) { return $res; }
		$code = wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code ) {
			return new WP_Error( 'ed_api', 'Claude API error ' . $code . ': ' . ( isset( $data['error']['message'] ) ? $data['error']['message'] : wp_remote_retrieve_body( $res ) ) );
		}
		if ( isset( $data['stop_reason'] ) && 'refusal' === $data['stop_reason'] ) {
			return new WP_Error( 'ed_refusal', 'Claude declined to classify this story.' );
		}
		$json = '';
		foreach ( (array) $data['content'] as $block ) { if ( 'text' === $block['type'] ) { $json = $block['text']; break; } }
		$parsed = json_decode( $json, true );
		if ( ! is_array( $parsed ) || ! isset( $parsed['matches'] ) ) { return new WP_Error( 'ed_parse', 'Unexpected response from Claude.' ); }
		$valid = array();
		foreach ( $items as $it ) { $valid[ $it['id'] ] = true; }
		$out = array();
		foreach ( $parsed['matches'] as $m ) {
			if ( isset( $valid[ $m['id'] ] ) ) { $out[] = array( 'id' => $m['id'], 'confidence' => 'exact' === $m['confidence'] ? 'exact' : 'likely', 'reason' => 'AI: ' . $m['reason'] ); }
		}
		return $out;
	}
}
