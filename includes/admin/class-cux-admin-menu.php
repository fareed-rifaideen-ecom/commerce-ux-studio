<?php
/**
 * Top-Level WordPress Dashboard Menu ("Commerce UX Studio" - v2.0 Beginner-Friendly 3-Tab Visual Studio).
 *
 * File Path: includes/admin/class-cux-admin-menu.php
 *
 * Designed for zero-learning-curve client handover:
 * - Tab 1: Look & Feel (Visual Card Style Picker, Hover Image, Out-of-Stock & Mobile Gallery)
 * - Tab 2: Stock & Gallery Autopilot (Highest-Stock Winner, Auto-Copy Color Galleries & 1-Click Sync)
 * - Tab 3: Delivery & "Complete Your Ride" (Ship to Home / Pickup Box & Native Cross-Sell Drawer)
 * - Collapsible Bottom Drawer: Developer, Odoo ERP, Google Schema & Git Tools
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
	 * Register the top-level Commerce UX Studio menu and 3 simple submenus.
	 */
	public static function register_menus(): void {
		$capability = 'manage_woocommerce';
		$main_slug  = 'cux-studio';

		add_menu_page(
			__( 'Commerce UX Studio', 'commerce-ux-studio' ),
			__( 'Commerce UX Studio', 'commerce-ux-studio' ),
			$capability,
			$main_slug,
			array( __CLASS__, 'render_look_and_feel_page' ),
			'dashicons-art',
			56
		);

		$submenus = array(
			array(
				'parent'   => $main_slug,
				'title'    => __( 'Store Look & Feel', 'commerce-ux-studio' ),
				'menu'     => __( '1. Look & Feel', 'commerce-ux-studio' ),
				'slug'     => 'cux-studio',
				'callback' => array( __CLASS__, 'render_look_and_feel_page' ),
			),
			array(
				'parent'   => $main_slug,
				'title'    => __( 'Smart Stock & Gallery Autopilot', 'commerce-ux-studio' ),
				'menu'     => __( '2. Stock Autopilot', 'commerce-ux-studio' ),
				'slug'     => 'cux-stock-gallery',
				'callback' => array( __CLASS__, 'render_stock_autopilot_page' ),
			),
			array(
				'parent'   => $main_slug,
				'title'    => __( 'Delivery Box & Complete Your Ride', 'commerce-ux-studio' ),
				'menu'     => __( '3. Delivery & Cross-Sells', 'commerce-ux-studio' ),
				'slug'     => 'cux-pdp-fulfilment',
				'callback' => array( __CLASS__, 'render_delivery_cross_sell_page' ),
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

		// 1. Save Settings across any Studio tab.
		if ( isset( $_POST['cux_save_settings'] ) && check_admin_referer( 'cux_save_settings_action', 'cux_settings_nonce' ) ) {
			$current = CUX_Inheritance_Engine::get_global_settings();
			$posted  = isset( $_POST['cux_settings'] ) && is_array( $_POST['cux_settings'] ) ? wp_unslash( $_POST['cux_settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			foreach ( $posted as $key => $val ) {
				$clean_key = sanitize_key( $key );
				if ( is_array( $val ) ) {
					$current[ $clean_key ] = array_map( 'sanitize_text_field', $val );
				} else {
					$current[ $clean_key ] = sanitize_text_field( (string) $val );
				}
			}

			update_option( 'cux_global_settings', $current );
			CUX_Inheritance_Engine::flush_runtime_cache();

			add_settings_error( 'cux_messages', 'cux_saved', __( 'Settings saved! Your store layout and autopilot rules are now updated.', 'commerce-ux-studio' ), 'updated' );
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
			add_settings_error( 'cux_messages', 'cux_seeded', __( 'Successfully restored default settings from config/default-presets.json.', 'commerce-ux-studio' ), 'updated' );
		}
	}

	/**
	 * Shared Admin Header Navigation Bar across the 3 beginner-friendly tabs.
	 *
	 * @param string $active_slug Current submenu slug.
	 * @param string $page_title  Current submenu title.
	 */
	private static function render_header( string $active_slug, string $page_title ): void {
		$tabs = array(
			'cux-studio'         => __( '1. Store Look & Feel', 'commerce-ux-studio' ),
			'cux-stock-gallery'  => __( '2. Smart Stock & Gallery Autopilot', 'commerce-ux-studio' ),
			'cux-pdp-fulfilment' => __( '3. Delivery Box & Complete Your Ride', 'commerce-ux-studio' ),
		);
		?>
		<style>
			.cux-visual-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin: 12px 0 24px; }
			.cux-visual-card { display: block; border: 2px solid #dcdcde; border-radius: 10px; padding: 16px; background: #fff; cursor: pointer; transition: all 0.15s ease; position: relative; }
			.cux-visual-card:hover { border-color: #2271b1; }
			.cux-visual-card input[type="radio"] { position: absolute; top: 16px; right: 16px; transform: scale(1.2); }
			.cux-visual-card strong { display: block; font-size: 15px; color: #1d2327; margin-bottom: 6px; padding-right: 28px; }
			.cux-visual-card span { display: block; font-size: 13px; color: #646970; line-height: 1.45; }
			.cux-help-banner { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 13.5px; color: #1d2327; }
			.cux-dev-drawer { margin-top: 28px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px 20px; }
			.cux-dev-drawer summary { font-weight: 600; cursor: pointer; color: #334155; font-size: 14px; outline: none; }
		</style>
		<div class="cux-admin-wrap">
			<header class="cux-admin-header">
				<div class="cux-brand">
					<span class="dashicons dashicons-art"></span>
					<div>
						<h1><?php echo esc_html( $page_title ); ?></h1>
						<p><?php esc_html_e( 'Commerce UX Studio • Works Automatically with Standard WooCommerce Products & Variations', 'commerce-ux-studio' ); ?> <span class="cux-version">v<?php echo esc_html( CUX_STUDIO_VERSION ); ?></span></p>
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
	 * Tab 1: Store Look & Feel (Visual Card Picker, Hover Rule, Out of Stock & Mobile Gallery).
	 */
	public static function render_look_and_feel_page(): void {
		$s = CUX_Inheritance_Engine::get_global_settings();
		self::render_header( 'cux-studio', __( '1. Store Look & Feel', 'commerce-ux-studio' ) );
		?>
			<div class="cux-help-banner">
				<strong><?php esc_html_e( 'Quick Tip for Designers:', 'commerce-ux-studio' ); ?></strong>
				<?php esc_html_e( 'Choose your storewide style below. If a specific Category (like Bikes vs. Apparel) needs a different card style, simply edit that Category under Products > Categories.', 'commerce-ux-studio' ); ?>
			</div>

			<form method="post" class="cux-card">
				<?php wp_nonce_field( 'cux_save_settings_action', 'cux_settings_nonce' ); ?>

				<h3><?php esc_html_e( 'Step 1: Choose Your Shop & Category Card Style', 'commerce-ux-studio' ); ?></h3>
				<div class="cux-visual-cards">
					<label class="cux-visual-card">
						<input type="radio" name="cux_settings[catalog_card_preset]" value="specialized_spec" <?php checked( $s['catalog_card_preset'], 'specialized_spec' ); ?> />
						<strong><?php esc_html_e( 'Style A: Technical Spec Card', 'commerce-ux-studio' ); ?></strong>
						<span><?php esc_html_e( 'Specialized-inspired layout. Shows soft grey card background, top-left badge (Award Winning), 50/50 split color circles, Compare checkbox, and spec subtitle.', 'commerce-ux-studio' ); ?></span>
					</label>

					<label class="cux-visual-card">
						<input type="radio" name="cux_settings[catalog_card_preset]" value="pinarello_showroom" <?php checked( $s['catalog_card_preset'], 'pinarello_showroom' ); ?> />
						<strong><?php esc_html_e( 'Style B: Minimalist Showroom Card', 'commerce-ux-studio' ); ?></strong>
						<span><?php esc_html_e( 'Pinarello-inspired layout. Overlays metallic color circles inside the bottom-left of the photo, top-right spec label (DISC), and clean uppercase title.', 'commerce-ux-studio' ); ?></span>
					</label>

					<label class="cux-visual-card">
						<input type="radio" name="cux_settings[catalog_card_preset]" value="zara_quick_add" <?php checked( $s['catalog_card_preset'], 'zara_quick_add' ); ?> />
						<strong><?php esc_html_e( 'Style C: Apparel Quick-Add Card', 'commerce-ux-studio' ); ?></strong>
						<span><?php esc_html_e( 'Zara-inspired layout. Ideal for clothing and gear—reveals clickable size buttons (S, M, L, XL) directly on the product card for 1-click buying.', 'commerce-ux-studio' ); ?></span>
					</label>
				</div>

				<h3><?php esc_html_e( 'Step 2: Hover Image, Out-of-Stock & Mobile Gallery Behavior', 'commerce-ux-studio' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'When a Customer Hovers Over a Product Card', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="cux_settings[catalog_hover_mode]">
								<option value="angle_2" <?php selected( $s['catalog_hover_mode'], 'angle_2' ); ?>><?php esc_html_e( 'Option A: Show 2nd Angle Photo of the Main Color', 'commerce-ux-studio' ); ?></option>
								<option value="second_color" <?php selected( $s['catalog_hover_mode'], 'second_color' ); ?>><?php esc_html_e( 'Option B: Show Main Photo of the 2nd Available Color', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'How to Show Out-of-Stock Colors & Sizes', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="cux_settings[oos_swatch_behavior]">
								<option value="clickable_preview" <?php selected( $s['oos_swatch_behavior'], 'clickable_preview' ); ?>><?php esc_html_e( 'Option 1: Keep Clickable (Let customer view photos + show Out of Stock notice)', 'commerce-ux-studio' ); ?></option>
								<option value="crossed_disabled" <?php selected( $s['oos_swatch_behavior'], 'crossed_disabled' ); ?>><?php esc_html_e( 'Option 2: Cross Out & Disable (Visible with strikethrough, not clickable)', 'commerce-ux-studio' ); ?></option>
								<option value="hide_completely" <?php selected( $s['oos_swatch_behavior'], 'hide_completely' ); ?>><?php esc_html_e( 'Option 3: Hide Out-of-Stock Options Completely', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Mobile Product Gallery Thumbnail Style', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="cux_settings[mobile_gallery_layout]">
								<option value="floating_right" <?php selected( $s['mobile_gallery_layout'], 'floating_right' ); ?>><?php esc_html_e( 'Floating Vertical Strip on Right Edge of Photo (App Style)', 'commerce-ux-studio' ); ?></option>
								<option value="floating_bottom" <?php selected( $s['mobile_gallery_layout'], 'floating_bottom' ); ?>><?php esc_html_e( 'Floating Horizontal Strip at Bottom of Photo', 'commerce-ux-studio' ); ?></option>
								<option value="minimal_dots" <?php selected( $s['mobile_gallery_layout'], 'minimal_dots' ); ?>><?php esc_html_e( 'Clean Swipe Dots + 1/4 Photo Counter Badge', 'commerce-ux-studio' ); ?></option>
								<option value="standard_below" <?php selected( $s['mobile_gallery_layout'], 'standard_below' ); ?>><?php esc_html_e( 'Standard Flatsome Thumbnails Below Photo', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Max Color Circles on Shop Cards', 'commerce-ux-studio' ); ?></th>
						<td>
							<input type="number" min="1" max="12" name="cux_settings[catalog_max_swatches]" value="<?php echo esc_attr( (string) $s['catalog_max_swatches'] ); ?>" class="small-text" />
							<span class="description"><?php esc_html_e( 'Extra colors automatically show as a "+X more" badge.', 'commerce-ux-studio' ); ?></span>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="cux_save_settings" class="button button-primary button-large"><?php esc_html_e( 'Save Look & Feel Settings', 'commerce-ux-studio' ); ?></button>
				</p>
			</form>

			<?php self::render_developer_drawer(); ?>
		</div>
		<?php
	}

	/**
	 * Tab 2: Smart Stock & Gallery Autopilot.
	 */
	public static function render_stock_autopilot_page(): void {
		$s = CUX_Inheritance_Engine::get_global_settings();
		self::render_header( 'cux-stock-gallery', __( '2. Smart Stock & Gallery Autopilot', 'commerce-ux-studio' ) );
		?>
			<div class="cux-help-banner">
				<strong><?php esc_html_e( 'How Autopilot Saves You Time:', 'commerce-ux-studio' ); ?></strong>
				<?php esc_html_e( 'You do not need to upload a Parent Main Image or Parent Gallery. Just add variation photos to ONE size of each color inside WooCommerce Variations—Autopilot copies them to the other sizes of that color and sets the highest-stock color as the Main Image automatically!', 'commerce-ux-studio' ); ?>
			</div>

			<div class="cux-grid-2">
				<form method="post" class="cux-card">
					<?php wp_nonce_field( 'cux_save_settings_action', 'cux_settings_nonce' ); ?>
					<h3><?php esc_html_e( 'Autopilot Rules', 'commerce-ux-studio' ); ?></h3>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'How to Pick the Main Image Winner', 'commerce-ux-studio' ); ?></th>
							<td>
								<select name="cux_settings[stock_calc_mode]">
									<option value="grouped_color" <?php selected( $s['stock_calc_mode'], 'grouped_color' ); ?>><?php esc_html_e( 'Best-Stocked Colorway (Add up stock across all sizes of each color)', 'commerce-ux-studio' ); ?></option>
									<option value="single_sku" <?php selected( $s['stock_calc_mode'], 'single_sku' ); ?>><?php esc_html_e( 'Single Highest-Stock Size (Individual variation with the most stock)', 'commerce-ux-studio' ); ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-Copy Gallery Across Sizes', 'commerce-ux-studio' ); ?></th>
							<td>
								<select name="cux_settings[auto_copy_color_gallery]">
									<option value="yes" <?php selected( $s['auto_copy_color_gallery'] ?? 'yes', 'yes' ); ?>><?php esc_html_e( 'Enabled (Upload photos to 1 size of a color -> auto-fills other sizes)', 'commerce-ux-studio' ); ?></option>
									<option value="no" <?php selected( $s['auto_copy_color_gallery'] ?? 'yes', 'no' ); ?>><?php esc_html_e( 'Disabled (Manage every size variation manually)', 'commerce-ux-studio' ); ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'What Shows in the Main Product Gallery', 'commerce-ux-studio' ); ?></th>
							<td>
								<select name="cux_settings[parent_gallery_mode]">
									<option value="winner_plus_colors" <?php selected( $s['parent_gallery_mode'], 'winner_plus_colors' ); ?>><?php esc_html_e( 'Winning Color Angles + Main Photo of Other In-Stock Colors (Out-of-stock hidden)', 'commerce-ux-studio' ); ?></option>
									<option value="winner_only" <?php selected( $s['parent_gallery_mode'], 'winner_only' ); ?>><?php esc_html_e( 'Winning Color Angles Only (Switches when customer clicks another color)', 'commerce-ux-studio' ); ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Tie-Breaker (When 2 Colors Have Equal Stock)', 'commerce-ux-studio' ); ?></th>
							<td>
								<select name="cux_settings[tie_breaker]">
									<option value="price_desc" <?php selected( $s['tie_breaker'], 'price_desc' ); ?>><?php esc_html_e( 'Show Higher-Priced Variation First', 'commerce-ux-studio' ); ?></option>
									<option value="menu_order" <?php selected( $s['tie_breaker'], 'menu_order' ); ?>><?php esc_html_e( 'Follow Variation Order in Product Editor', 'commerce-ux-studio' ); ?></option>
								</select>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" name="cux_save_settings" class="button button-primary button-large"><?php esc_html_e( 'Save Autopilot Settings', 'commerce-ux-studio' ); ?></button>
					</p>
				</form>

				<div class="cux-card">
					<h3><?php esc_html_e( 'One-Click Storewide Sync', 'commerce-ux-studio' ); ?></h3>
					<p class="cux-muted"><?php esc_html_e( 'Products sync automatically whenever you save a product, receive an order, import a CSV, or sync with Odoo. Click below anytime to refresh all existing products at once.', 'commerce-ux-studio' ); ?></p>
					<p style="margin-top: 18px;">
						<button type="button" id="cux-run-bulk-sync" class="button button-primary button-hero">
							<?php esc_html_e( 'Sync All Products Now', 'commerce-ux-studio' ); ?>
						</button>
					</p>
					<div id="cux-bulk-sync-status" class="cux-sync-progress" style="display:none;"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab 3: Delivery Box & "Complete Your Ride" Cross-Sells.
	 */
	public static function render_delivery_cross_sell_page(): void {
		$s = CUX_Inheritance_Engine::get_global_settings();
		self::render_header( 'cux-pdp-fulfilment', __( '3. Delivery Box & Complete Your Ride', 'commerce-ux-studio' ) );
		?>
			<div class="cux-help-banner">
				<strong><?php esc_html_e( 'Uses Built-In WooCommerce Cross-Sells:', 'commerce-ux-studio' ); ?></strong>
				<?php esc_html_e( 'To add helmets, shoes, or bottle cages to the "Complete Your Ride" box on any bike, simply select those items inside the standard WooCommerce "Linked Products > Cross-sells" field when editing the product!', 'commerce-ux-studio' ); ?>
			</div>

			<form method="post" class="cux-card">
				<?php wp_nonce_field( 'cux_save_settings_action', 'cux_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show "Ship to Home vs. In-Store Pickup" Box', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="cux_settings[enable_delivery_block]">
								<option value="yes" <?php selected( $s['enable_delivery_block'], 'yes' ); ?>><?php esc_html_e( 'Enabled (Show interactive delivery & showroom pickup cards above Add to Cart)', 'commerce-ux-studio' ); ?></option>
								<option value="no" <?php selected( $s['enable_delivery_block'], 'no' ); ?>><?php esc_html_e( 'Disabled', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Default Ship to Home Time & Shipping Cost', 'commerce-ux-studio' ); ?></th>
						<td>
							<input type="text" name="cux_settings[default_home_lead]" value="<?php echo esc_attr( $s['default_home_lead'] ); ?>" placeholder="e.g., 4-7 days" />
							<input type="text" name="cux_settings[default_home_cost]" value="<?php echo esc_attr( $s['default_home_cost'] ); ?>" placeholder="e.g., $75.00" />
							<p class="description"><?php esc_html_e( 'Shown inside the Ship to Home card (can be customized per Category under Products > Categories).', 'commerce-ux-studio' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Default In-Store Pickup Time', 'commerce-ux-studio' ); ?></th>
						<td>
							<input type="text" name="cux_settings[default_pickup_lead]" value="<?php echo esc_attr( $s['default_pickup_lead'] ); ?>" placeholder="e.g., 6-10 days" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Show "Complete Your Ride" Accessory Box', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="cux_settings[enable_complete_ride]">
								<option value="yes" <?php selected( $s['enable_complete_ride'], 'yes' ); ?>><?php esc_html_e( 'Enabled (Automatically displays Cross-sell products below Add to Cart)', 'commerce-ux-studio' ); ?></option>
								<option value="no" <?php selected( $s['enable_complete_ride'], 'no' ); ?>><?php esc_html_e( 'Disabled', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="cux_save_settings" class="button button-primary button-large"><?php esc_html_e( 'Save Delivery & Cross-Sell Settings', 'commerce-ux-studio' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Collapsible Developer, Odoo ERP, Schema & Git Health Drawer (Hidden from casual users by default).
	 */
	private static function render_developer_drawer(): void {
		$db_health = CUX_Activator::get_database_health();
		$modules   = method_exists( 'CUX_Loader', 'get_module_health' ) ? CUX_Loader::get_module_health() : array();
		$s         = CUX_Inheritance_Engine::get_global_settings();
		?>
		<details class="cux-dev-drawer">
			<summary><?php esc_html_e( 'Developer, Odoo ERP, Google Schema & Git Tools (Click to Expand)', 'commerce-ux-studio' ); ?></summary>
			<div class="cux-grid-2" style="margin-top: 16px;">
				<div class="cux-card">
					<h3><?php esc_html_e( 'System & Git Module Health Check', 'commerce-ux-studio' ); ?></h3>
					<ul class="cux-status-list">
						<li>
							<span><?php esc_html_e( 'Database Tables (Presets & Audit Log):', 'commerce-ux-studio' ); ?></span>
							<strong class="cux-badge <?php echo ( $db_health['rules_table_exists'] && $db_health['audit_table_exists'] ) ? 'cux-badge-success' : 'cux-badge-error'; ?>">
								<?php echo ( $db_health['rules_table_exists'] && $db_health['audit_table_exists'] ) ? esc_html__( 'Installed & Ready', 'commerce-ux-studio' ) : esc_html__( 'Missing Table', 'commerce-ux-studio' ); ?>
							</strong>
						</li>
						<?php foreach ( $modules as $label => $is_loaded ) : ?>
							<li>
								<span><?php echo esc_html( $label ); ?></span>
								<strong class="cux-badge <?php echo $is_loaded ? 'cux-badge-success' : 'cux-badge-error'; ?>">
									<?php echo $is_loaded ? esc_html__( 'Loaded', 'commerce-ux-studio' ) : esc_html__( 'Pending Next Step', 'commerce-ux-studio' ); ?>
								</strong>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="cux-card">
					<form method="post">
						<?php wp_nonce_field( 'cux_save_settings_action', 'cux_settings_nonce' ); ?>
						<h3><?php esc_html_e( 'Google ProductGroup Schema & ERP Triggers', 'commerce-ux-studio' ); ?></h3>
						<p>
							<label>
								<input type="checkbox" name="cux_settings[enable_product_group_schema]" value="yes" <?php checked( $s['enable_product_group_schema'], 'yes' ); ?> />
								<?php esc_html_e( 'Enable Google ProductGroup & hasVariant JSON-LD (Uses native WC GTIN & Brand)', 'commerce-ux-studio' ); ?>
							</label>
						</p>
						<p>
							<label>
								<input type="checkbox" name="cux_settings[enable_deep_link_ssr]" value="yes" <?php checked( $s['enable_deep_link_ssr'], 'yes' ); ?> />
								<?php esc_html_e( 'Enable Server-Side Variant URL Rendering (?attribute_pa_...)', 'commerce-ux-studio' ); ?>
							</label>
						</p>
						<p>
							<button type="submit" name="cux_save_settings" class="button button-secondary"><?php esc_html_e( 'Save Developer Options', 'commerce-ux-studio' ); ?></button>
						</p>
					</form>

					<hr style="margin: 18px 0;" />

					<form method="post">
						<?php wp_nonce_field( 'cux_tools_action', 'cux_tools_nonce' ); ?>
						<h3><?php esc_html_e( 'Git Settings-as-Code (JSON Export / Reset)', 'commerce-ux-studio' ); ?></h3>
						<p>
							<button type="submit" name="cux_export_json" class="button"><?php esc_html_e( 'Export Config JSON', 'commerce-ux-studio' ); ?></button>
							<button type="submit" name="cux_sync_repo_json" class="button"><?php esc_html_e( 'Reset from config/default-presets.json', 'commerce-ux-studio' ); ?></button>
						</p>
					</form>
				</div>
			</div>
		</details>
		<?php
	}
}
