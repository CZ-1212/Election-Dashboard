<?php
/**
 * Dashboard markup. Variables: $config (array), $settings (array), $map_svg (string), $map_art (URL),
 * $icon_url (base URL for assets/img), $style (string).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$s = $settings;
?>
<div class="ed-root"><div class="ed-dashboard" id="election-dashboard"<?php echo $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
	<script type="application/json" class="ed-config"><?php echo wp_json_encode( $config ); ?></script>

	<header class="ed-top">
		<div class="ed-title">
			<h2 class="ed-title-text"><span class="ed-title-year"><?php echo esc_html( $s['title']['year'] ); ?></span> <?php echo esc_html( $s['title']['text'] ); ?></h2>
			<img class="ed-title-icon" src="<?php echo esc_url( $icon_url . 'ballot-box.png' ); ?>" alt="" width="42" height="42" decoding="async">
		</div>
		<nav class="ed-top-links" aria-label="<?php esc_attr_e( 'Election resources', 'election-dashboard' ); ?>">
			<?php foreach ( $s['header_links'] as $l ) : if ( empty( $l['url'] ) ) { continue; } ?>
			<a class="ed-btn<?php echo ! empty( $l['style'] ) ? ' ed-btn-' . esc_attr( $l['style'] ) : ''; ?>" href="<?php echo esc_url( $l['url'] ); ?>"><?php echo esc_html( $l['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>
	</header>

	<div class="ed-main">
		<section class="ed-map-col">
			<div class="ed-map-head">
				<div class="ed-map-title"><?php echo esc_html( $s['map_title'] ); ?></div>
				<div class="ed-map-sub"><?php echo esc_html( $s['map_subtitle'] ); ?></div>
			</div>
			<div class="ed-map-wrap" style="aspect-ratio: <?php echo intval( $map_dims['w'] ); ?> / <?php echo intval( $map_dims['h'] ); ?>">
				<img class="ed-map-art" src="<?php echo esc_url( $map_art ); ?>" width="<?php echo intval( $map_dims['w'] ); ?>" height="<?php echo intval( $map_dims['h'] ); ?>" alt="" decoding="async">
				<?php echo $map_svg; // traced hit regions, markers and lift layer ?>
				<div class="ed-map-tip" aria-hidden="true"></div>
			</div>
		</section>

		<section class="ed-center">
			<div class="ed-prompt">
				<div class="ed-prompt-arrow" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M40 24H10"/><path d="M20 14 10 24l10 10"/></svg></div>
				<div class="ed-prompt-title"><?php echo esc_html( $s['prompt_title'] ); ?></div>
				<div class="ed-prompt-text"><?php echo esc_html( $s['prompt_text'] ); ?></div>
			</div>
			<div class="ed-ballot">
				<div class="ed-ballot-head">
					<img alt="" width="54" height="54" decoding="async">
					<h3 class="ed-ballot-title"></h3>
					<a class="ed-open-page" href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Full page', 'election-dashboard' ); ?> &#8599;</a>
					<button type="button" class="ed-close" aria-label="<?php esc_attr_e( 'Close ballot', 'election-dashboard' ); ?>">&times;</button>
				</div>
				<div class="ed-ballot-scroll">
					<div class="ed-search"><input type="search" id="ed-search-input" placeholder="<?php esc_attr_e( 'Search for measures, candidates, contests…', 'election-dashboard' ); ?>" aria-label="<?php esc_attr_e( 'Search this ballot', 'election-dashboard' ); ?>"></div>
					<div class="ed-ballot-body"></div>
				</div>
			</div>
		</section>

		<nav class="ed-side-nav" aria-label="<?php esc_attr_e( 'Other ballots', 'election-dashboard' ); ?>">
			<?php foreach ( $s['side_nav'] as $n ) : ?>
			<a class="ed-nav-btn" href="<?php echo esc_url( $n['url'] ); ?>" aria-label="<?php echo esc_attr( $n['label'] ); ?>">
				<picture>
					<source srcset="<?php echo esc_url( $icon_url . 'nav/' . $n['icon'] ); ?>" type="image/webp">
					<img src="<?php echo esc_url( $icon_url . 'nav/' . str_replace( '.webp', '.png', $n['icon'] ) ); ?>" alt="" width="543" height="362" decoding="async">
				</picture>
			</a>
			<?php endforeach; ?>
		</nav>
	</div>

	<footer class="ed-dates">
		<div class="ed-dates-title"><?php echo esc_html( $s['key_dates_title'] ); ?></div>
		<ol class="ed-timeline">
			<?php foreach ( $s['key_dates'] as $d ) : ?>
			<li class="ed-date<?php echo ! empty( $d['highlight'] ) ? ' is-highlight' : ''; ?>">
				<div class="ed-date-badge"><span class="ed-date-month"><?php echo esc_html( $d['month'] ); ?></span><span class="ed-date-day"><?php echo esc_html( $d['day'] ); ?></span></div>
				<div class="ed-date-text"><?php echo wp_kses( $d['text'], array( 'b' => array(), 'strong' => array(), 'br' => array() ) ); ?></div>
			</li>
			<?php endforeach; ?>
		</ol>
	</footer>

	<p class="ed-sr" aria-live="polite"></p>
</div></div>
