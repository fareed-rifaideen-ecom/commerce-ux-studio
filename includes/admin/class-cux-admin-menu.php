<?php
/**
 * Top-Level WordPress Dashboard Menu ("Commerce UX Studio") & 7 Submenu Controllers.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Admin_Menu {

	/**
	 * Initialize admin menu hooks and form listeners.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ), 56 );
		add_action( 'admin_init', array( __CLASS__, 'handle_form_submissions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register the top-level Commerce UX Studio menu and all 7 submenus.
	 */
	public static function register_menus(): void {
		$capability = 'manage_woocommerce';
		$main_slug  = 'cux-studio';

		add_menu_page(
			__( 'Commerce UX Studio', 'commerce-ux-studio' ),
			__( 'Commerce UX Studio', 'commerce-ux-studio' ),
			$capability,
			$main_slug,
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-art',
			56
		);

		$submenus = array(
			array(
				'parent'   => $main_slug,
				'title'    => __( 'Dashboard & Sync Health', 'commerce-ux-studio' ),
				'menu'     => __( '1. Dashboard & Sync', 'commerce-ux-studio' ),
				'slug'     => 'cux-studio',
				'callback' => array( __CLASS__, 'render_dashboard_page' ),
			),
			array(
				'parent'   => $main_slug,
				'title'    => __( 'Stock & Gallery Engine', 'commerce-ux-studio' ),
				'menu'     => __( '2. Stock & Gallery', 'commerce-ux-studio' ),
				'slug'     => 'cux-stock-gallery',
				'callback' => array( __CLASS__, 'render_stock_gallery_page' ),
			),
			array(
				'parent'   => $main_slug,
				'title'    => __( 'Swatch & Card Designer', 'commerce-ux-studio' ),
				'menu'     => __( '3. Swatch & Cards', 'commerce-ux-studio' ),
				'slug'     => 'cux-swatch-cards',
				'callback' => array( __CLASS__, 'render_swatch_cards_page' ),
			),
			array(
				'parent'   => $main_slug,
				'title'    => __( 'Single Product & Fulfilment UX', 'commerce-ux-studio' ),
				'menu'     => __( '4. PDP & Fulfilment', 'commerce-ux-studio' ),
				'slug'     => 'cux-pdp-fulfilment',
				'callback' => array( __CLASS__, 'render_pdp_fulfilment_page' ),
			),
			array(
				'parent'   => $main_slug,
				'title'    => __( 'Complete Your Ride Builder', 'commerce-ux-studio' ),
				'menu'     => __( '5. Complete Your Ride', 'commerce-ux-studio' ),
				'slug'     => 'cux-cross-sell',
				'callback' => array( __CLASS__, 'render_cross_sell_page' ),
			),
			array(
				'parent'   => $main_slug,
				'title'    => __( 'Conditional Rules & Presets', 'commerce-ux-studio' ),
				'menu'     => __( '6. Rules & Presets', 'commerce-ux-studio' ),
				'slug'     => 'cux-rules-presets',
				'callback' => array( __CLASS__, 'render_rules_presets_page' ),
			),
			array(
				'parent'   => $main_slug,
				'title'    => __( 'SEO Schema, Odoo & Tools', 'commerce-ux-studio' ),
				'menu'     => __( '7. Schema & Odoo', 'commerce-ux-studio' ),
				'slug'     => 'cux-integrations',
				'callback' => array( __CLASS__, 'render_integrations_page' ),
			),
		);

		foreach ( $submenus as $sub ) {
			add_submenu_page(
				$sub['parent'],
				$sub['title'],
				$sub['menu'],
				$capability,
				$sub['slug'],
				$sub['callback']
			);
		}
	}

	/**
	 * Enqueue admin styles and scripts on Commerce UX Studio screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_admin_assets( string $hook ): void {
		if ( false === strpos( $hook, 'cux-' ) && false === strpos( $hook, 'commerce-ux-studio' ) ) {
			return;
		}

		wp_enqueue_style(
			'cux-admin-studio',
			CUX_STUDIO_URL . 'assets/css/admin-studio.css',
			array(),
			CUX_STUDIO_VERSION
		);

		wp_enqueue_script(
			'cux-admin-studio',
			CUX_STUDIO_URL . 'assets/js/admin-studio.js',
			array( 'jquery' ),
			CUX_STUDIO_VERSION,
			true
		);

		wp_localize_script(
			'cux-admin-studio',
			'cuxAdminVars',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cux_admin_nonce' ),
			)
		);
	}

	/**
	 * Handle settings saves, JSON export, and Git seed resets.
	 */
	public static function handle_form_submissions(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// 1. Save Global Settings across any Studio tab.
		if ( isset( $_POST['cux_save_settings'] ) && check_admin_referer( 'cux_save_settings_action', 'cux_settings_nonce' ) ) {
			$current = CUX_Inheritance_Engine::get_global_settings();
			$posted  = isset( $_POST['cux_settings'] ) && is_array( $_POST['cux_settings'] ) ? wp_unslash( $_POST['cux_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			foreach ( $posted as $key => $val ) {
				$clean_key = sanitize_key( $key );
				if ( is_array( $val ) ) {
					$current[ $clean_key ] = array_map( 'sanitize_text_field', $val );
				} elseif ( in_array( $clean_key, array( 'secondary_visual_attrs', 'dimensional_attrs' ), true ) ) {
					$parts                 = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', (string) $val ) ) ) );
					$current[ $clean_key ] = array_values( $parts );
				} else {
					$current[ $clean_key ] = sanitize_text_field( (string) $val );
				}
			}

			update_option( 'cux_global_settings', $current );
			CUX_Inheritance_Engine::flush_runtime_cache();

			add_settings_error( 'cux_messages', 'cux_saved', __( 'Commerce UX Studio settings saved successfully.', 'commerce-ux-studio' ), 'updated' );
		}

		// 2. Export Configuration JSON (Settings-as-Code for GitHub).
		if ( isset( $_POST['cux_export_json'] ) && check_admin_referer( 'cux_tools_action', 'cux_tools_nonce' ) ) {
			global $wpdb;
			$rules_table = $wpdb->prefix . 'cux_rules_presets';
			$rows        = $wpdb->get_results( "SELECT * FROM {$rules_table} ORDER BY priority ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$presets = array();
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$presets[] = array(
						'rule_type'  => $row['rule_type'],
						'title'      => $row['title'],
						'priority'   => (int) $row['priority'],
						'status'     => $row['status'],
						'conditions' => json_decode( $row['conditions_json'], true ),
						'settings'   => json_decode( $row['settings_json'], true ),
					);
				}
			}

			$export_payload = array(
				'version'         => CUX_STUDIO_VERSION,
				'exported_at'     => gmdate( 'c' ),
				'global_settings' => CUX_Inheritance_Engine::get_global_settings(),
				'presets'         => $presets,
			);

			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=cux-studio-config-' . gmdate( 'Y-m-d' ) . '.json' );
			echo wp_json_encode( $export_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			exit;
		}

		// 3. Re-Sync from Repository config/default-presets.json.
		if ( isset( $_POST['cux_sync_repo_json'] ) && check_admin_referer( 'cux_tools_action', 'cux_tools_nonce' ) ) {
			CUX_Activator::seed_defaults_if_empty( true );
			CUX_Inheritance_Engine::flush_runtime_cache();
			add_settings_error( 'cux_messages', 'cux_seeded', __( 'Successfully synced configuration and presets from config/default-presets.json.', 'commerce-ux-studio' ), 'updated' );
		}
	}

	/**
	 * Shared Admin Header Navigation Bar across all 7 submenus.
	 *
	 * @param string $active_slug Current submenu slug.
	 * @param string $page_title  Current submenu title.
	 */
	private static function render_header( string $active_slug, string $page_title ): void {
		$tabs = array(
			'cux-studio'         => __( '1. Dashboard & Sync', 'commerce-ux-studio' ),
			'cux-stock-gallery'  => __( '2. Stock & Gallery', 'commerce-ux-studio' ),
			'cux-swatch-cards'   => __( '3. Swatch & Cards', 'commerce-ux-studio' ),
			'cux-pdp-fulfilment' => __( '4. PDP & Fulfilment', 'commerce-ux-studio' ),
			'cux-cross-sell'     => __( '5. Complete Your Ride', 'commerce-ux-studio' ),
			'cux-rules-presets'  => __( '6. Rules & Presets', 'commerce-ux-studio' ),
			'cux-integrations'   => __( '7. Schema & Odoo', 'commerce-ux-studio' ),
		);
		?>
		<div class="cux-admin-wrap">
			<header class="cux-admin-header">
				<div class="cux-brand">
					<span class="dashicons dashicons-art"></span>
					<div>
						<h1><?php echo esc_html( $page_title ); ?></h1>
						<p><?php esc_html_e( 'Commerce UX Studio • Flatsome UX Builder & Odoo Stock Engine', 'commerce-ux-studio' ); ?> <span class="cux-version">v<?php echo esc_html( CUX_STUDIO_VERSION ); ?></span></p>
					</div>
				</div>
				<nav class="cux-nav-tabs">
					<?php foreach ( $tabs as $slug => $label ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="cux-tab <?php echo $active_slug === $slug ? 'is-active' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			</header>
			<?php settings_errors( 'cux_messages' ); ?>
		<?php
	}

	/**
	 * Submenu 1: Dashboard & Sync Health.
	 */
	public static function render_dashboard_page(): void {
		$health   = CUX_Activator::get_database_health();
		$settings = CUX_Inheritance_Engine::get_global_settings();

		$phase2_files = array(
			'Attribute Fields'  => 'includes/admin/class-cux-attribute-fields.php',
			'Taxonomy Fields'   => 'includes/admin/class-cux-taxonomy-fields.php',
			'Product Metabox'   => 'includes/admin/class-cux-product-metabox.php',
			'Variation Fields'  => 'includes/admin/class-cux-variation-fields.php',
			'Bulk & Quick Edit' => 'includes/admin/class-cux-bulk-quick-edit.php',
		);

		self::render_header( 'cux-studio', __( 'Dashboard & Catalog Sync Health', 'commerce-ux-studio' ) );
		?>
			<div class="cux-grid-3">
				<div class="cux-card">
					<h3><?php esc_html_e( 'Database & Schema Status', 'commerce-ux-studio' ); ?></h3>
					<ul class="cux-status-list">
						<li>
							<span><?php esc_html_e( 'Rules & Presets Table:', 'commerce-ux-studio' ); ?></span>
							<strong class="cux-badge <?php echo $health['rules_table_exists'] ? 'cux-badge-success' : 'cux-badge-error'; ?>">
								<?php echo $health['rules_table_exists'] ? esc_html__( 'Installed', 'commerce-ux-studio' ) . ' (' . esc_html( (string) $health['rules_count'] ) . ')' : esc_html__( 'Missing', 'commerce-ux-studio' ); ?>
							</strong>
						</li>
						<li>
							<span><?php esc_html_e( 'Sync Audit Log Table:', 'commerce-ux-studio' ); ?></span>
							<strong class="cux-badge <?php echo $health['audit_table_exists'] ? 'cux-badge-success' : 'cux-badge-error'; ?>">
								<?php echo $health['audit_table_exists'] ? esc_html__( 'Installed', 'commerce-ux-studio' ) . ' (' . esc_html( (string) $health['audit_count'] ) . ')' : esc_html__( 'Missing', 'commerce-ux-studio' ); ?>
							</strong>
						</li>
						<li>
							<span><?php esc_html_e( 'Active Stock Calculation Mode:', 'commerce-ux-studio' ); ?></span>
							<strong><?php echo esc_html( $settings['stock_calc_mode'] ); ?></strong>
						</li>
					</ul>
				</div>

				<div class="cux-card">
					<h3><?php esc_html_e( 'Installed Module Files (Git Check)', 'commerce-ux-studio' ); ?></h3>
					<ul class="cux-status-list">
						<?php
						foreach ( $phase2_files as $label => $rel_path ) :
							$exists = file_exists( CUX_STUDIO_PATH . $rel_path );
							?>
							<li>
								<span><?php echo esc_html( $label ); ?>:</span>
								<strong class="cux-badge <?php echo $exists ? 'cux-badge-success' : 'cux-badge-error'; ?>">
									<?php echo $exists ? esc_html__( 'Loaded', 'commerce-ux-studio' ) : esc_html__( 'Not Found on Server', 'commerce-ux-studio' ); ?>
								</strong>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="cux-card">
					<h3><?php esc_html_e( 'One-Click Catalog Bulk Sync', 'commerce-ux-studio' ); ?></h3>
					<p class="cux-muted"><?php esc_html_e( 'Recalculate winning stock variations, parent main/gallery images, and precomputed frontend UX caches across all variable products.', 'commerce-ux-studio' ); ?></p>
					<button type="button" id="cux-run-bulk-sync" class="button button-primary button-hero">
						<?php esc_html_e( 'Run Full Catalog Sync', 'commerce-ux-studio' ); ?>
					</button>
					<div id="cux-bulk-sync-status" class="cux-sync-progress" style="display:none;"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Submenu 2: Stock & Gallery Engine Settings.
	 */
	public static function render_stock_gallery_page(): void {
		$s = CUX_Inheritance_Engine::get_global_settings();
		self::render_header( 'cux-stock-gallery', __( 'Stock Winner & Dynamic Gallery Engine', 'commerce-ux-studio' ) );
		?>
		<form method="post" class="cux-card">
			<?php wp_nonce_field( 'cux_save_settings_action', 'cux_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Stock Winner Calculation Mode', 'commerce-ux-studio' ); ?></th>
					<td>
						<select name="cux_settings[stock_calc_mode]">
							<option value="grouped_color" <?php selected( $s['stock_calc_mode'], 'grouped_color' ); ?>><?php esc_html_e( 'Grouped by Primary Visual Attribute (Sum all sizes/specs per Colorway)', 'commerce-ux-studio' ); ?></option>
							<option value="single_sku" <?php selected( $s['stock_calc_mode'], 'single_sku' ); ?>><?php esc_html_e( 'Single Variation Winner (Individual SKU with highest stock wins)', 'commerce-ux-studio' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Primary Visual Attribute Slug', 'commerce-ux-studio' ); ?></th>
					<td>
						<input type="text" name="cux_settings[primary_visual_attr]" value="<?php echo esc_attr( $s['primary_visual_attr'] ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'e.g., pa_color. Controls primary gallery grouping and catalog color circles.', 'commerce-ux-studio' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Secondary Visual Attributes (CSV)', 'commerce-ux-studio' ); ?></th>
					<td>
						<input type="text" name="cux_settings[secondary_visual_attrs]" value="<?php echo esc_attr( implode( ', ', (array) $s['secondary_visual_attrs'] ) ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Attributes that also have distinct photos (e.g., pa_groupset, pa_wheelset, pa_spec).', 'commerce-ux-studio' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Non-Visual Dimensional Attributes (CSV)', 'commerce-ux-studio' ); ?></th>
					<td>
						<input type="text" name="cux_settings[dimensional_attrs]" value="<?php echo esc_attr( implode( ', ', (array) $s['dimensional_attrs'] ) ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Attributes like pa_size that bypass Flickity slider reloads for instant zero-lag clicks.', 'commerce-ux-studio' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Tie-Breaker Hierarchy', 'commerce-ux-studio' ); ?></th>
					<td>
						<select name="cux_settings[tie_breaker]">
							<option value="price_desc" <?php selected( $s['tie_breaker'], 'price_desc' ); ?>><?php esc_html_e( 'Highest Retail Price First -> Menu Order -> Variation ID', 'commerce-ux-studio' ); ?></option>
							<option value="menu_order" <?php selected( $s['tie_breaker'], 'menu_order' ); ?>><?php esc_html_e( 'Custom Menu Order First -> Variation ID', 'commerce-ux-studio' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Parent Gallery Build Mode', 'commerce-ux-studio' ); ?></th>
					<td>
						<select name="cux_settings[parent_gallery_mode]">
							<option value="winner_plus_colors" <?php selected( $s['parent_gallery_mode'], 'winner_plus_colors' ); ?>><?php esc_html_e( 'Winning Variation Angles + Other In-Stock Color Main Images', 'commerce-ux-studio' ); ?></option>
							<option value="winner_only" <?php selected( $s['parent_gallery_mode'], 'winner_only' ); ?>><?php esc_html_e( 'Winning Variation Angles Only', 'commerce-ux-studio' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="cux_save_settings" class="button button-primary"><?php esc_html_e( 'Save Stock & Gallery Settings', 'commerce-ux-studio' ); ?></button>
			</p>
		</form>
		</div>
		<?php
	}

	/**
	 * Submenu 3: Swatch & Card Designer.
	 */
	public static function render_swatch_cards_page(): void {
		$s = CUX_Inheritance_Engine::get_global_settings();
		self::render_header( 'cux-swatch-cards', __( 'Swatch & Catalog Card Designer', 'commerce-ux-studio' ) );
		?>
		<form method="post" class="cux-card">
			<?php wp_nonce_field( 'cux_save_settings_action', 'cux_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Global Catalog Card Layout Preset', 'commerce-ux-studio' ); ?></th>
					<td>
						<select name="cux_settings[catalog_card_preset]">
							<option value="specialized_spec" <?php selected( $s['catalog_card_preset'], 'specialized_spec' ); ?>><?php esc_html_e( 'Specialized Technical Spec Card (Dual-color swatches, Compare box, Spec subtitle)', 'commerce-ux-studio' ); ?></option>
							<option value="pinarello_showroom" <?php selected( $s['catalog_card_preset'], 'pinarello_showroom' ); ?>><?php esc_html_e( 'Pinarello Minimalist Showroom Card (Overlaid metallic swatches, Top-right DISC badge)', 'commerce-ux-studio' ); ?></option>
							<option value="zara_quick_add" <?php selected( $s['catalog_card_preset'], 'zara_quick_add' ); ?>><?php esc_html_e( 'Zara Apparel Card (Hover Quick-Add size overlay)', 'commerce-ux-studio' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Flatsome Shop Grid Hover Image Preference', 'commerce-ux-studio' ); ?></th>
					<td>
						<select name="cux_settings[catalog_hover_mode]">
							<option value="angle_2" <?php selected( $s['catalog_hover_mode'], 'angle_2' ); ?>><?php esc_html_e( 'Option A: Angle #2 of Winning Variation', 'commerce-ux-studio' ); ?></option>
							<option value="second_color" <?php selected( $s['catalog_hover_mode'], 'second_color' ); ?>><?php esc_html_e( 'Option B: Main Image #1 of Second-Highest Stock Variation', 'commerce-ux-studio' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Out-of-Stock Swatch Behavior', 'commerce-ux-studio' ); ?></th>
					<td>
						<select name="cux_settings[oos_swatch_behavior]">
							<option value="clickable_preview" <?php selected( $s['oos_swatch_behavior'], 'clickable_preview' ); ?>><?php esc_html_e( 'Option 1: Clickable Photo Preview + Out-of-Stock / Notify Notice', 'commerce-ux-studio' ); ?></option>
							<option value="crossed_disabled" <?php selected( $s['oos_swatch_behavior'], 'crossed_disabled' ); ?>><?php esc_html_e( 'Option 2: Crossed-Out & Unclickable (Disabled)', 'commerce-ux-studio' ); ?></option>
							<option value="hide_completely" <?php selected( $s['oos_swatch_behavior'], 'hide_completely' ); ?>><?php esc_html_e( 'Option 3: Hide Out-of-Stock Swatches Completely', 'commerce-ux-studio' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Max Catalog Card Swatches (+X More)', 'commerce-ux-studio' ); ?></th>
					<td>
						<input type="number" min="1" max="12" name="cux_settings[catalog_max_swatches]" value="<?php echo esc_attr( (string) $s['catalog_max_swatches'] ); ?>" class="small-text" />
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="cux_save_settings" class="button button-primary"><?php esc_html_e( 'Save Swatch & Card Settings', 'commerce-ux-studio' ); ?></button>
			</p>
		</form>
		</div>
		<?php
	}

	/**
	 * Submenu 4: Single Product & Fulfilment UX.
	 */
	public static function render_pdp_fulfilment_page(): void {
		$s = CUX_Inheritance_Engine::get_global_settings();
		self::render_header( 'cux-pdp-fulfilment', __( 'Single Product & Omnichannel Fulfilment UX', 'commerce-ux-studio' ) );
		?>
		<form method="post" class="cux-card">
			<?php wp_nonce_field( 'cux_save_settings_action', 'cux_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Mobile Gallery Thumbnail Layout', 'commerce-ux-studio' ); ?></th>
					<td>
						<select name="cux_settings[mobile_gallery_layout]">
							<option value="floating_right" <?php selected( $s['mobile_gallery_layout'], 'floating_right' ); ?>><?php esc_html_e( 'Floating Vertical Glass Pill on Right Edge', 'commerce-ux-studio' ); ?></option>
							<option value="floating_bottom" <?php selected( $s['mobile_gallery_layout'], 'floating_bottom' ); ?>><?php esc_html_e( 'Floating Horizontal Strip on Bottom Edge', 'commerce-ux-studio' ); ?></option>
							<option value="minimal_dots" <?php selected( $s['mobile_gallery_layout'], 'minimal_dots' ); ?>><?php esc_html_e( 'Minimal Dots + 1/N Counter Pill', 'commerce-ux-studio' ); ?></option>
							<option value="standard_below" <?php selected( $s['mobile_gallery_layout'], 'standard_below' ); ?>><?php esc_html_e( 'Standard Flatsome Row Below Image', 'commerce-ux-studio' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Omnichannel Delivery Method Switcher', 'commerce-ux-studio' ); ?></th>
					<td>
						<select name="cux_settings[enable_delivery_block]">
							<option value="yes" <?php selected( $s['enable_delivery_block'], 'yes' ); ?>><?php esc_html_e( 'Enabled (Show Ship to Home vs In-Store Pickup Cards)', 'commerce-ux-studio' ); ?></option>
							<option value="no" <?php selected( $s['enable_delivery_block'], 'no' ); ?>><?php esc_html_e( 'Disabled', 'commerce-ux-studio' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default Ship to Home Lead Time & Cost', 'commerce-ux-studio' ); ?></th>
					<td>
						<input type="text" name="cux_settings[default_home_lead]" value="<?php echo esc_attr( $s['default_home_lead'] ); ?>" placeholder="4-7 days" />
						<input type="text" name="cux_settings[default_home_cost]" value="<?php echo esc_attr( $s['default_home_cost'] ); ?>" placeholder="$75.00" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default In-Store Pickup Lead Time', 'commerce-ux-studio' ); ?></th>
					<td>
						<input type="text" name="cux_settings[default_pickup_lead]" value="<?php echo esc_attr( $s['default_pickup_lead'] ); ?>" placeholder="6-10 days" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default Low Stock Warning Threshold', 'commerce-ux-studio' ); ?></th>
					<td>
						<input type="number" min="1" max="50" name="cux_settings[default_low_stock_limit]" value="<?php echo esc_attr( (string) $s['default_low_stock_limit'] ); ?>" class="small-text" />
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="cux_save_settings" class="button button-primary"><?php esc_html_e( 'Save PDP & Fulfilment Settings', 'commerce-ux-studio' ); ?></button>
			</p>
		</form>
		</div>
		<?php
	}

	/**
	 * Submenu 5: Complete Your Ride Builder.
	 */
	public static function render_cross_sell_page(): void {
		$s = CUX_Inheritance_Engine::get_global_settings();
		self::render_header( 'cux-cross-sell', __( 'In-Page "Complete Your Ride" Configurator', 'commerce-ux-studio' ) );
		?>
		<form method="post" class="cux-card">
			<?php wp_nonce_field( 'cux_save_settings_action', 'cux_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Complete Your Ride Block', 'commerce-ux-studio' ); ?></th>
					<td>
						<select name="cux_settings[enable_complete_ride]">
							<option value="yes" <?php selected( $s['enable_complete_ride'], 'yes' ); ?>><?php esc_html_e( 'Enabled (Render inline accessory configurator below Add to Cart)', 'commerce-ux-studio' ); ?></option>
							<option value="no" <?php selected( $s['enable_complete_ride'], 'no' ); ?>><?php esc_html_e( 'Disabled', 'commerce-ux-studio' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="cux_save_settings" class="button button-primary"><?php esc_html_e( 'Save Cross-Sell Settings', 'commerce-ux-studio' ); ?></button>
			</p>
		</form>
		</div>
		<?php
	}

	/**
	 * Submenu 6: Conditional Rules & Presets.
	 */
	public static function render_rules_presets_page(): void {
		global $wpdb;
		$rules_table = $wpdb->prefix . 'cux_rules_presets';
		$rows        = $wpdb->get_results( "SELECT * FROM {$rules_table} ORDER BY priority ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		self::render_header( 'cux-rules-presets', __( '4-Tier Conditional Rules & UI Presets', 'commerce-ux-studio' ) );
		?>
		<div class="cux-card">
			<h3><?php esc_html_e( 'Installed UI Presets & Conditional Rules', 'commerce-ux-studio' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'commerce-ux-studio' ); ?></th>
						<th><?php esc_html_e( 'Type', 'commerce-ux-studio' ); ?></th>
						<th><?php esc_html_e( 'Title', 'commerce-ux-studio' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'commerce-ux-studio' ); ?></th>
						<th><?php esc_html_e( 'Status', 'commerce-ux-studio' ); ?></th>
						<th><?php esc_html_e( 'Settings Payload', 'commerce-ux-studio' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $rows ) ) : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td>#<?php echo esc_html( (string) $row['id'] ); ?></td>
								<td><code><?php echo esc_html( $row['rule_type'] ); ?></code></td>
								<td><strong><?php echo esc_html( $row['title'] ); ?></strong></td>
								<td><?php echo esc_html( (string) $row['priority'] ); ?></td>
								<td><span class="cux-badge cux-badge-success"><?php echo esc_html( $row['status'] ); ?></span></td>
								<td><code><?php echo esc_html( $row['settings_json'] ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No presets found.', 'commerce-ux-studio' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		</div>
		<?php
	}

	/**
	 * Submenu 7: SEO Schema, Odoo & Settings-as-Code Tools.
	 */
	public static function render_integrations_page(): void {
		$s = CUX_Inheritance_Engine::get_global_settings();
		self::render_header( 'cux-integrations', __( 'Google ProductGroup Schema, Odoo Bridge & Git Tools', 'commerce-ux-studio' ) );
		?>
		<div class="cux-grid-2">
			<form method="post" class="cux-card">
				<?php wp_nonce_field( 'cux_save_settings_action', 'cux_settings_nonce' ); ?>
				<h3><?php esc_html_e( 'SEO Schema & Automation Triggers', 'commerce-ux-studio' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Google ProductGroup JSON-LD', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="cux_settings[enable_product_group_schema]">
								<option value="yes" <?php selected( $s['enable_product_group_schema'], 'yes' ); ?>><?php esc_html_e( 'Enabled (Output hasVariant & variesBy Schema)', 'commerce-ux-studio' ); ?></option>
								<option value="no" <?php selected( $s['enable_product_group_schema'], 'no' ); ?>><?php esc_html_e( 'Disabled', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Server-Side Variant Deep-Linking', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="cux_settings[enable_deep_link_ssr]">
								<option value="yes" <?php selected( $s['enable_deep_link_ssr'], 'yes' ); ?>><?php esc_html_e( 'Enabled (Pre-render ?attribute_pa_... URLs on server)', 'commerce-ux-studio' ); ?></option>
								<option value="no" <?php selected( $s['enable_deep_link_ssr'], 'no' ); ?>><?php esc_html_e( 'Disabled', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Odoo Webhook & CSV Sync Listeners', 'commerce-ux-studio' ); ?></th>
						<td>
							<label><input type="checkbox" name="cux_settings[trigger_on_odoo_webhook]" value="yes" <?php checked( $s['trigger_on_odoo_webhook'], 'yes' ); ?> /> <?php esc_html_e( 'Sync on Odoo API / Direct _stock Meta Update', 'commerce-ux-studio' ); ?></label><br/>
							<label><input type="checkbox" name="cux_settings[trigger_on_csv_import]" value="yes" <?php checked( $s['trigger_on_csv_import'], 'yes' ); ?> /> <?php esc_html_e( 'Sync on WooCommerce CSV Import', 'commerce-ux-studio' ); ?></label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" name="cux_save_settings" class="button button-primary"><?php esc_html_e( 'Save Integration Settings', 'commerce-ux-studio' ); ?></button>
				</p>
			</form>

			<form method="post" class="cux-card">
				<?php wp_nonce_field( 'cux_tools_action', 'cux_tools_nonce' ); ?>
				<h3><?php esc_html_e( 'Git Deploy Manager & Settings-as-Code', 'commerce-ux-studio' ); ?></h3>
				<p class="cux-muted"><?php esc_html_e( 'Export your current Studio configuration and presets to commit into config/default-presets.json in GitHub, or re-seed from the repository file.', 'commerce-ux-studio' ); ?></p>
				<p>
					<button type="submit" name="cux_export_json" class="button button-secondary"><?php esc_html_e( 'Export Configuration JSON', 'commerce-ux-studio' ); ?></button>
					<button type="submit" name="cux_sync_repo_json" class="button"><?php esc_html_e( 'Re-Sync from config/default-presets.json', 'commerce-ux-studio' ); ?></button>
				</p>
			</form>
		</div>
		</div>
		<?php
	}
}
