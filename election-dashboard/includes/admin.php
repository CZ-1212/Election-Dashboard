<?php
/**
 * Admin: Election Dashboard → Story matches (review queue) and settings.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ED_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_action( 'admin_post_ed_review', array( __CLASS__, 'handle_review' ) );
		add_action( 'admin_post_ed_scan', array( __CLASS__, 'handle_scan' ) );
		add_action( 'admin_post_ed_manual', array( __CLASS__, 'handle_manual' ) );
		add_action( 'load-toplevel_page_election-dashboard', array( __CLASS__, 'drain_queue' ) );
	}

	/** Each view of the page processes more of the queue; the page reloads itself until it is empty. */
	public static function drain_queue() {
		if ( ED_Automation::queue_size() && current_user_can( 'edit_posts' ) ) { ED_Automation::run_queue( 12 ); }
	}

	public static function menu() {
		add_menu_page( 'Election Dashboard', 'Election Dashboard', 'edit_posts', 'election-dashboard', array( __CLASS__, 'page' ), 'dashicons-chart-area', 26 );
	}

	public static function settings() {
		register_setting( 'ed_settings', 'ed_anthropic_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'ed_settings', 'ed_ai_model', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'claude-opus-5-5' ) );
		register_setting( 'ed_settings', 'ed_election_tag', array( 'sanitize_callback' => 'sanitize_title', 'default' => 'election-2026' ) );
		register_setting( 'ed_settings', 'ed_min_date', array( 'sanitize_callback' => function ( $v ) { $v = trim( (string) $v ); return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : ''; }, 'default' => '2026-07-01' ) );
		register_setting( 'ed_settings', 'ed_auto_approve_exact', array( 'sanitize_callback' => 'absint', 'default' => 1 ) );
	}

	public static function handle_scan() {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'No access' ); }
		check_admin_referer( 'ed_scan' );
		$n = ED_Automation::scan();
		wp_safe_redirect( admin_url( 'admin.php?page=election-dashboard&status=all&scanned=' . $n ) );
		exit;
	}

	public static function handle_review() {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'No access' ); }
		check_admin_referer( 'ed_review' );
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$do  = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		$keep = isset( $_POST['keep'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['keep'] ) ) : array();
		if ( 'approve' === $do ) { ED_Stories::set_status( $url, 'approved', $keep ); }
		elseif ( 'reject' === $do ) { ED_Stories::set_status( $url, 'rejected' ); }
		elseif ( 'rematch' === $do ) {
			$all = ED_Stories::all();
			if ( isset( $all[ $url ]['post_id'] ) && $all[ $url ]['post_id'] ) { $all[ $url ]['source'] = 'auto'; ED_Stories::save( $all ); ED_Automation::match_post( $all[ $url ]['post_id'] ); }
		}
		elseif ( 'delete' === $do ) { ED_Stories::remove( $url ); }
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=election-dashboard' ) );
		exit;
	}

	public static function handle_manual() {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'No access' ); }
		check_admin_referer( 'ed_manual' );
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$ids = isset( $_POST['items'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['items'] ) ) : array();
		if ( $url && $ids ) {
			$matches = array();
			foreach ( $ids as $id ) { if ( ED_Stories::item( $id ) ) { $matches[] = array( 'id' => $id, 'confidence' => 'exact', 'reason' => 'Added by ' . wp_get_current_user()->display_name ); } }
			ED_Stories::propose( $url, $matches, array( 'source' => 'manual' ) );
			ED_Stories::set_status( $url, 'approved' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=election-dashboard&added=1' ) );
		exit;
	}

	public static function page() {
		$all = ED_Stories::all();
		$filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'pending';
		$counts = array( 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'unmatched' => 0 );
		foreach ( $all as $r ) { $counts[ $r['status'] ] = isset( $counts[ $r['status'] ] ) ? $counts[ $r['status'] ] + 1 : 1; }
		$rows = array_filter( $all, function ( $r ) use ( $filter ) { return 'all' === $filter || $r['status'] === $filter; } );
		uasort( $rows, function ( $a, $b ) { return strcmp( $b['date'], $a['date'] ); } );
		$idx = ED_Stories::index();
		$left = ED_Automation::queue_size();
		?>
		<div class="wrap">
			<h1>Election Dashboard · Story matches</h1>
			<?php if ( $left ) : ?>
				<meta http-equiv="refresh" content="2;url=<?php echo esc_attr( admin_url( 'admin.php?page=election-dashboard&status=' . $filter ) ); ?>">
				<div class="notice notice-info"><p><strong><?php echo intval( $left ); ?> stories still to process.</strong> Keep this page open; it reloads itself until the queue is empty.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['scanned'] ) ) : ?><div class="notice notice-success"><p>Scan finished: <?php echo intval( $_GET['scanned'] ); ?> stories with the tag had not been matched before<?php echo $left ? ', ' . intval( $left ) . ' still processing' : ', all processed'; ?>.</p></div><?php endif; ?>
			<?php if ( isset( $_GET['added'] ) ) : ?><div class="notice notice-success"><p>Story link added.</p></div><?php endif; ?>
			<?php if ( isset( $_GET['settings-updated'] ) ) : ?><div class="notice notice-success"><p>Settings saved.</p></div><?php endif; ?>

			<p>
				<?php foreach ( array( 'pending' => 'Needs review', 'approved' => 'Approved', 'unmatched' => 'No match found', 'rejected' => 'Rejected', 'all' => 'All' ) as $k => $label ) : ?>
					<a class="button<?php echo $filter === $k ? ' button-primary' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=election-dashboard&status=' . $k ) ); ?>"><?php echo esc_html( $label ); ?><?php if ( isset( $counts[ $k ] ) ) : ?> (<?php echo intval( $counts[ $k ] ); ?>)<?php endif; ?></a>
				<?php endforeach; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:12px"><?php wp_nonce_field( 'ed_scan' ); ?><input type="hidden" name="action" value="ed_scan"><button class="button">Scan the “<?php echo esc_html( ED_Automation::tag_slug() ); ?>” tag now</button></form>
				<span style="color:#666;margin-left:8px">Last scan: <?php echo esc_html( get_option( 'ed_last_scan', 'never' ) ); ?> · AI step: <?php echo ED_AI::enabled() ? 'on' : 'off (no API key)'; ?></span>
			</p>
			<p style="color:#666">Ballot index: <?php echo intval( count( $idx['items'] ) ); ?> items in <?php echo intval( count( $idx['counties'] ) ); ?> counties · Tag “<?php echo esc_html( ED_Automation::tag_slug() ); ?>”: <?php $t = get_term_by( 'slug', ED_Automation::tag_slug(), 'post_tag' ); echo $t ? intval( $t->count ) . ' posts' : '<strong style="color:#b32d2e">not found, check the tag slug in Settings below</strong>'; ?> · Approved links: <?php echo intval( $counts['approved'] ); ?><?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?> · WP-Cron is off on this site (fine: matching runs on publish and from this page)<?php endif; ?></p>

			<?php if ( ! $rows ) : ?><p><em>Nothing here.</em></p><?php endif; ?>
			<?php foreach ( $rows as $url => $r ) : ?>
				<div class="card" style="max-width:none;margin:0 0 12px;padding:12px 16px">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'ed_review' ); ?>
						<input type="hidden" name="action" value="ed_review"><input type="hidden" name="url" value="<?php echo esc_attr( $url ); ?>">
						<p style="margin:0 0 6px"><strong><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $r['title'] ); ?></a></strong>
							<span style="color:#666"> · <?php echo esc_html( $r['date'] ); ?> · <?php echo esc_html( $r['status'] ); ?> · <?php echo esc_html( $r['source'] ); ?></span>
							<?php if ( ! empty( $r['note'] ) ) : ?><span style="color:#b32d2e"> · <?php echo esc_html( $r['note'] ); ?></span><?php endif; ?></p>
						<?php if ( $r['items'] ) : ?>
							<ul style="margin:0 0 8px 4px">
							<?php foreach ( $r['items'] as $id => $m ) : $it = ED_Stories::item( $id ); if ( ! $it ) { continue; } ?>
								<li><label><input type="checkbox" name="keep[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( 'exact', $m['confidence'] ); ?> <?php checked( true, 'approved' === $r['status'] ); ?>>
									<strong><?php echo esc_html( ucfirst( str_replace( '-', ' ', $it['county'] ) ) ); ?></strong> · <?php echo esc_html( $it['key'] ); ?><?php if ( ! empty( $it['jurisdiction'] ) ) : ?> (<?php echo esc_html( $it['jurisdiction'] ); ?>)<?php endif; ?>
									<span style="color:#666"> — <?php echo esc_html( $m['confidence'] ); ?>: <?php echo esc_html( $m['reason'] ); ?></span></label></li>
							<?php endforeach; ?>
							</ul>
						<?php else : ?><p style="color:#666;margin:0 0 8px">No ballot item matched. Add one below, or reject.</p><?php endif; ?>
						<button class="button button-primary" name="do" value="approve">Approve checked</button>
						<button class="button" name="do" value="reject">Reject</button>
						<button class="button" name="do" value="rematch">Re-run matcher</button>
						<button class="button-link-delete" name="do" value="delete" style="margin-left:8px">Delete</button>
					</form>
				</div>
			<?php endforeach; ?>

			<h2>Add a link by hand</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ed_manual' ); ?><input type="hidden" name="action" value="ed_manual">
				<p><input type="url" name="url" class="regular-text" placeholder="https://localnewsmatters.org/2026/..." required>
				<select name="items[]" multiple size="8" style="min-width:420px;vertical-align:top">
					<?php $cur = ''; foreach ( $idx['items'] as $it ) : if ( $it['county'] !== $cur ) { if ( $cur ) { echo '</optgroup>'; } $cur = $it['county']; echo '<optgroup label="' . esc_attr( ucwords( str_replace( '-', ' ', $cur ) ) ) . '">'; } ?>
						<option value="<?php echo esc_attr( $it['id'] ); ?>"><?php echo esc_html( ( 'measure' === $it['type'] ? '' : ( 'uncontested' === $it['type'] ? '[uncontested] ' : '[race] ' ) ) . $it['key'] ); ?></option>
					<?php endforeach; if ( $cur ) { echo '</optgroup>'; } ?>
				</select>
				<button class="button">Add</button></p>
				<p class="description">Hold Ctrl (Windows) or Cmd (Mac) to pick more than one item.</p>
			</form>

			<h2>Settings</h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'ed_settings' ); ?>
				<table class="form-table">
					<tr><th>Election tag</th><td><input type="text" name="ed_election_tag" value="<?php echo esc_attr( get_option( 'ed_election_tag', 'election-2026' ) ); ?>" class="regular-text"><p class="description">Tag slug that marks election stories.</p></td></tr>
					<tr><th>Ignore stories before</th><td><input type="date" name="ed_min_date" value="<?php echo esc_attr( get_option( 'ed_min_date', '2026-07-01' ) ); ?>"><p class="description">Stories published before this date are skipped entirely: June primary coverage reuses the same measure letters and candidate names. Links added by hand are kept whatever their date. Leave empty to turn this off. Takes effect on the next scan.</p></td></tr>
					<tr><th>Auto-approve certain matches</th><td><input type="hidden" name="ed_auto_approve_exact" value="0"><label><input type="checkbox" name="ed_auto_approve_exact" value="1" <?php checked( 1, get_option( 'ed_auto_approve_exact', 1 ) ); ?>> When every match is certain, publish the links without review</label></td></tr>
					<tr><th>Claude API key</th><td><input type="password" name="ed_anthropic_api_key" value="<?php echo esc_attr( get_option( 'ed_anthropic_api_key', '' ) ); ?>" class="regular-text" autocomplete="off" <?php disabled( defined( 'ED_ANTHROPIC_API_KEY' ) ); ?>><p class="description">Optional. With a key, uncertain stories are read by Claude before they reach the queue. Leave empty to run without AI. You can also define <code>ED_ANTHROPIC_API_KEY</code> in wp-config.php.</p></td></tr>
					<tr><th>Model</th><td><input type="text" name="ed_ai_model" value="<?php echo esc_attr( get_option( 'ed_ai_model', 'claude-opus-5-5' ) ); ?>" class="regular-text"><p class="description">Default <code>claude-opus-5-5</code>. <code>claude-sonnet-5-5</code> costs about half.</p></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
ED_Admin::init();
