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
				if ( 'shipping_method_meta' === $clean_key && is_array( $val ) ) {
					$clean_methods = array();
					foreach ( $val as $rate_key => $m_fields ) {
						if ( is_array( $m_fields ) ) {
							$clean_methods[ sanitize_text_field( (string) $rate_key ) ] = array(
								'enabled'   => sanitize_key( $m_fields['enabled'] ?? 'yes' ),
								'title'     => sanitize_text_field( $m_fields['title'] ?? '' ),
								'lead_time' => sanitize_text_field( $m_fields['lead_time'] ?? '' ),
								'subtitle'  => sanitize_text_field( $m_fields['subtitle'] ?? '' ),
							);
						}
					}
					$current['shipping_method_meta'] = $clean_methods;
				} elseif ( is_array( $val ) ) {
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
	 * Tab 3: Delivery Box & "Complete Your Ride" Cross-Sells (Universal WooCommerce Shipping Zones, Methods & Classes Sync).
	 */
	public static function render_delivery_cross_sell_page(): void {
		$s           = CUX_Inheritance_Engine::get_global_settings();
		$method_meta = isset( $s['shipping_method_meta'] ) && is_array( $s['shipping_method_meta'] ) ? $s['shipping_method_meta'] : array();

		// Discover all WooCommerce Shipping Zones, Methods, and Shipping Classes dynamically.
		$discovered_zones   = array();
		$shipping_classes   = array();

		if ( class_exists( 'WC_Shipping_Zones' ) ) {
			$raw_zones = WC_Shipping_Zones::get_zones();
			foreach ( $raw_zones as $z_id => $z_info ) {
				$zone_obj = new WC_Shipping_Zone( (int) $z_id );
				$discovered_zones[] = array(
					'zone_name' => $zone_obj->get_zone_name(),
					'methods'   => $zone_obj->get_shipping_methods( false ),
				);
			}
			// Also include Zone 0 ("Rest of the World").
			$zone_zero      = new WC_Shipping_Zone( 0 );
			$zero_methods   = $zone_zero->get_shipping_methods( false );
			if ( ! empty( $zero_methods ) ) {
				$discovered_zones[] = array(
					'zone_name' => $zone_zero->get_zone_name(),
					'methods'   => $zero_methods,
				);
			}
		}

		if ( function_exists( 'WC' ) && is_object( WC()->shipping() ) ) {
			$shipping_classes = WC()->shipping()->get_shipping_classes();
		}

		self::render_header( 'cux-pdp-fulfilment', __( '3. Delivery Box & Complete Your Ride', 'commerce-ux-studio' ) );
		?>
			<div class="cux-help-banner">
				<strong><?php esc_html_e( '100% Synced with WooCommerce Shipping Zones, Methods & Classes:', 'commerce-ux-studio' ); ?></strong>
				<?php esc_html_e( 'This plugin automatically reads your active Shipping Zones, Shipping Methods, and Product Shipping Classes from WooCommerce > Settings > Shipping. When a customer selects a delivery or pickup card on the product page, that exact shipping method is pre-selected in their Cart and Checkout!', 'commerce-ux-studio' ); ?>
				<span style="margin-left: 8px;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ); ?>" class="button button-small"><?php esc_html_e( 'Manage WooCommerce Shipping Zones', 'commerce-ux-studio' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=classes' ) ); ?>" class="button button-small"><?php esc_html_e( 'Manage Shipping Classes', 'commerce-ux-studio' ); ?></a>
				</span>
			</div>

			<form method="post" class="cux-card">
				<?php wp_nonce_field( 'cux_save_settings_action', 'cux_settings_nonce' ); ?>
				<h3><?php esc_html_e( 'General Delivery & Cross-Sell Settings', 'commerce-ux-studio' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show Delivery Method Box on Product Pages', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="cux_settings[enable_delivery_block]">
								<option value="yes" <?php selected( $s['enable_delivery_block'], 'yes' ); ?>><?php esc_html_e( 'Enabled (Show interactive Delivery & Pickup cards above Add to Cart)', 'commerce-ux-studio' ); ?></option>
								<option value="no" <?php selected( $s['enable_delivery_block'], 'no' ); ?>><?php esc_html_e( 'Disabled', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'WooCommerce Shipping Sync Mode', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="cux_settings[wc_shipping_sync_mode]">
								<option value="auto_wc" <?php selected( $s['wc_shipping_sync_mode'] ?? 'auto_wc', 'auto_wc' ); ?>><?php esc_html_e( 'Auto-Sync Methods & Shipping Class Costs from WooCommerce Shipping Zones (Recommended)', 'commerce-ux-studio' ); ?></option>
								<option value="custom_text" <?php selected( $s['wc_shipping_sync_mode'] ?? 'auto_wc', 'custom_text' ); ?>><?php esc_html_e( 'Manual Fallback Cards Only', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Section Heading Label', 'commerce-ux-studio' ); ?></th>
						<td>
							<input type="text" name="cux_settings[delivery_heading_label]" value="<?php echo esc_attr( $s['delivery_heading_label'] ?? 'Delivery Method' ); ?>" class="regular-text" placeholder="e.g., Delivery Method" />
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Default Lead Time Badges (Used when not overridden below)', 'commerce-ux-studio' ); ?></th>
						<td>
							<label style="margin-right: 16px;">
								<?php esc_html_e( 'Standard Delivery Lead Time:', 'commerce-ux-studio' ); ?>
								<input type="text" name="cux_settings[default_home_lead]" value="<?php echo esc_attr( $s['default_home_lead'] ?? '4-7 days' ); ?>" class="regular-text" style="width:140px;" placeholder="e.g., 4-7 days" />
							</label>
							<label>
								<?php esc_html_e( 'In-Store Pickup Lead Time:', 'commerce-ux-studio' ); ?>
								<input type="text" name="cux_settings[default_pickup_lead]" value="<?php echo esc_attr( $s['default_pickup_lead'] ?? '6-10 days' ); ?>" class="regular-text" style="width:140px;" placeholder="e.g., 6-10 days" />
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Show "Complete Your Ride" Cross-Sell Box', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="cux_settings[enable_complete_ride]">
								<option value="yes" <?php selected( $s['enable_complete_ride'], 'yes' ); ?>><?php esc_html_e( 'Enabled (Automatically displays native WooCommerce Cross-sells below Add to Cart)', 'commerce-ux-studio' ); ?></option>
								<option value="no" <?php selected( $s['enable_complete_ride'], 'no' ); ?>><?php esc_html_e( 'Disabled', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<h3 style="margin-top: 24px;"><?php esc_html_e( 'Active WooCommerce Shipping Zones & Methods (Customize Wording & Lead Times)', 'commerce-ux-studio' ); ?></h3>
				<p class="cux-muted"><?php esc_html_e( 'Every shipping method from WooCommerce > Settings > Shipping is listed below. Leave "Custom Card Title" blank to use the exact title from WooCommerce, or customize the wording and lead time for each method.', 'commerce-ux-studio' ); ?></p>

				<?php if ( empty( $discovered_zones ) ) : ?>
					<p><?php esc_html_e( 'No WooCommerce Shipping Zones configured yet. Add a zone under WooCommerce > Settings > Shipping.', 'commerce-ux-studio' ); ?></p>
				<?php else : ?>
					<table class="widefat striped" style="margin-top: 12px; border-radius: 6px; overflow: hidden;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Shipping Zone', 'commerce-ux-studio' ); ?></th>
								<th><?php esc_html_e( 'WooCommerce Method & Status', 'commerce-ux-studio' ); ?></th>
								<th><?php esc_html_e( 'Show on Product Page', 'commerce-ux-studio' ); ?></th>
								<th><?php esc_html_e( 'Custom Card Title (Optional)', 'commerce-ux-studio' ); ?></th>
								<th><?php esc_html_e( 'Lead Time Badge', 'commerce-ux-studio' ); ?></th>
								<th><?php esc_html_e( 'Subtitle / Note (Optional)', 'commerce-ux-studio' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $discovered_zones as $z_group ) :
								foreach ( $z_group['methods'] as $instance_id => $method ) :
									if ( ! is_object( $method ) ) {
										continue;
									}
									$rate_key   = $method->id . ':' . $instance_id;
									$saved_row  = $method_meta[ $rate_key ] ?? array();
									$is_enabled = method_exists( $method, 'is_enabled' ) ? $method->is_enabled() : true;
									$wc_title   = method_exists( $method, 'get_title' ) && ! empty( $method->get_title() ) ? $method->get_title() : $method->get_method_title();
									$is_pickup  = ( 'local_pickup' === $method->id );
									?>
									<tr>
										<td><strong><?php echo esc_html( $z_group['zone_name'] ); ?></strong></td>
										<td>
											<strong><?php echo esc_html( $wc_title ); ?></strong>
											<br/>
											<code style="font-size:11px;"><?php echo esc_html( $rate_key ); ?></code>
											<?php if ( $is_enabled ) : ?>
												<span class="cux-badge cux-badge-success" style="margin-left:4px;"><?php esc_html_e( 'Enabled in WC', 'commerce-ux-studio' ); ?></span>
											<?php else : ?>
												<span class="cux-badge cux-badge-error" style="margin-left:4px;"><?php esc_html_e( 'Disabled in WC', 'commerce-ux-studio' ); ?></span>
											<?php endif; ?>
										</td>
										<td>
											<select name="cux_settings[shipping_method_meta][<?php echo esc_attr( $rate_key ); ?>][enabled]">
												<option value="yes" <?php selected( $saved_row['enabled'] ?? 'yes', 'yes' ); ?>><?php esc_html_e( 'Show Card', 'commerce-ux-studio' ); ?></option>
												<option value="no" <?php selected( $saved_row['enabled'] ?? 'yes', 'no' ); ?>><?php esc_html_e( 'Hide Card', 'commerce-ux-studio' ); ?></option>
											</select>
										</td>
										<td>
											<input type="text"
												name="cux_settings[shipping_method_meta][<?php echo esc_attr( $rate_key ); ?>][title]"
												value="<?php echo esc_attr( $saved_row['title'] ?? '' ); ?>"
												placeholder="<?php echo esc_attr( $wc_title ); ?>"
												class="regular-text" style="width:100%;" />
										</td>
										<td>
											<input type="text"
												name="cux_settings[shipping_method_meta][<?php echo esc_attr( $rate_key ); ?>][lead_time]"
												value="<?php echo esc_attr( $saved_row['lead_time'] ?? '' ); ?>"
												placeholder="<?php echo esc_attr( $is_pickup ? ( $s['default_pickup_lead'] ?? '6-10 days' ) : ( $s['default_home_lead'] ?? '4-7 days' ) ); ?>"
												style="width:110px;" />
										</td>
										<td>
											<input type="text"
												name="cux_settings[shipping_method_meta][<?php echo esc_attr( $rate_key ); ?>][subtitle]"
												value="<?php echo esc_attr( $saved_row['subtitle'] ?? '' ); ?>"
												placeholder="<?php echo esc_attr( $is_pickup ? __( 'Available for store pickup', 'commerce-ux-studio' ) : __( 'Auto-calculates class shipping rate', 'commerce-ux-studio' ) ); ?>"
												class="regular-text" style="width:100%;" />
										</td>
									</tr>
									<?php
								endforeach;
							endforeach;
							?>
						</tbody>
					</table>
				<?php endif; ?>

				<?php if ( ! empty( $shipping_classes ) ) : ?>
					<h3 style="margin-top: 24px;"><?php esc_html_e( 'Detected WooCommerce Product Shipping Classes (Auto-Calculated per Product)', 'commerce-ux-studio' ); ?></h3>
					<p class="cux-muted"><?php esc_html_e( 'When a product is assigned to any of these WooCommerce Shipping Classes, its Delivery Card on the product page automatically displays the exact class cost configured inside your Flat Rate shipping method.', 'commerce-ux-studio' ); ?></p>
					<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
						<?php foreach ( $shipping_classes as $sc ) : ?>
							<span style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:6px;padding:6px 12px;font-size:12.5px;">
								<strong><?php echo esc_html( $sc->name ); ?></strong>
								<code style="margin-left:4px;color:#475569;"><?php echo esc_html( $sc->slug ); ?></code>
							</span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<p class="submit" style="margin-top: 20px;">
					<button type="submit" name="cux_save_settings" class="button button-primary button-large"><?php esc_html_e( 'Save Delivery & Shipping Sync Settings', 'commerce-ux-studio' ); ?></button>
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
