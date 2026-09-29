<?php
/**
 * Dashboard markup. Variables available: $config (array), $map_svg (string), $brand (URL), $style (string).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="ed-dashboard" id="election-dashboard"<?php echo $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
	<script type="application/json" class="ed-config"><?php echo wp_json_encode( $config ); ?></script>
	<div class="ed-stage">
		<div class="ed-map-wrap">
			<img class="ed-map-art" src="<?php echo esc_url( $map_art ); ?>" width="900" height="1326" alt="" decoding="async">
			<?php echo $map_svg; // traced hit regions + lift layer, shipped with the plugin ?>
			<div class="ed-map-tip" aria-hidden="true"></div>
		</div>
		<div class="ed-side">
			<div class="ed-hint"><span class="ed-hint-mouse"><?php esc_html_e( 'Hover over your county to see your ballot', 'election-dashboard' ); ?></span><span class="ed-hint-touch"><?php esc_html_e( 'Tap your county to see your ballot', 'election-dashboard' ); ?></span></div>
			<div class="ed-emblem-float" aria-hidden="true">
				<svg viewBox="0 0 44 44" fill="none" stroke="#8b3a62" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4 36 C 8 20, 20 12, 36 12"/><path d="M28 6 l8 6 -6 8"/></svg>
				<img alt="" width="96" height="96" decoding="async">
			</div>
		</div>
		<div class="ed-ballot">
			<div class="ed-ballot-inner">
				<div class="ed-ballot-head">
					<img alt="" width="44" height="44" decoding="async">
					<h2 class="ed-ballot-title"></h2>
					<a class="ed-open-page" href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Full page', 'election-dashboard' ); ?> &#8599;</a>
					<button type="button" class="ed-close" aria-label="<?php esc_attr_e( 'Close ballot', 'election-dashboard' ); ?>">&times;</button>
				</div>
				<div class="ed-search"><input type="search" placeholder="<?php esc_attr_e( 'Search for measures, candidates, contests…', 'election-dashboard' ); ?>" aria-label="<?php esc_attr_e( 'Search this ballot', 'election-dashboard' ); ?>"></div>
				<div class="ed-ballot-body"></div>
			</div>
		</div>
		<div class="ed-brand"><img src="<?php echo esc_url( $brand ); ?>" alt="<?php esc_attr_e( '2026 General Election', 'election-dashboard' ); ?>" width="720" height="461" decoding="async"></div>
	</div>
	<p class="ed-sr" aria-live="polite"></p>
</div>
