<?php
/**
 * Top-Level Admin Menu & Beginner-Friendly 3-Tab Visual Studio (v2.2 Universal WooCommerce Sync)
 *
 * File Path: includes/admin/class-cux-admin-menu.php
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Admin_Menu {

	/**
	 * Hook into WordPress admin.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_form_saves' ] );
	}

	/**
	 * Register top-level menu and 3 beginner-friendly visual tabs.
	 */
	public static function register_menus(): void {
		add_menu_page(
			__( 'Commerce UX Studio', 'commerce-ux-studio' ),
			__( 'Commerce UX Studio', 'commerce-ux-studio' ),
			'manage_woocommerce',
			'commerce-ux-studio',
			[ __CLASS__, 'render_page' ],
			'dashicons-art',
			56
		);

		add_submenu_page(
			'commerce-ux-studio',
			__( '1. Look & Feel', 'commerce-ux-studio' ),
			__( '1. Look & Feel', 'commerce-ux-studio' ),
			'manage_woocommerce',
			'commerce-ux-studio',
			[ __CLASS__, 'render_page' ]
		);

		add_submenu_page(
			'commerce-ux-studio',
			__( '2. Stock Autopilot', 'commerce-ux-studio' ),
			__( '2. Stock Autopilot', 'commerce-ux-studio' ),
			'manage_woocommerce',
			'commerce-ux-studio&tab=autopilot',
			[ __CLASS__, 'render_page' ]
		);

		add_submenu_page(
			'commerce-ux-studio',
			__( '3. Delivery & Cross-Sells', 'commerce-ux-studio' ),
			__( '3. Delivery & Cross-Sells', 'commerce-ux-studio' ),
			'manage_woocommerce',
			'commerce-ux-studio&tab=delivery',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Enqueue admin CSS, JS, and WordPress Color Picker on Studio screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'commerce-ux-studio' ) && 'term.php' !== $hook && 'edit-tags.php' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();

		$ver = defined( 'CUX_STUDIO_VERSION' ) ? CUX_STUDIO_VERSION : '2.2.0';

		wp_enqueue_style(
			'cux-admin-studio',
			CUX_STUDIO_URL . 'assets/css/admin-studio.css',
			[ 'wp-color-picker' ],
			$ver
		);

		wp_enqueue_script(
			'cux-admin-studio',
			CUX_STUDIO_URL . 'assets/js/admin-studio.js',
			[ 'jquery', 'wp-color-picker' ],
			$ver,
			true
		);

		wp_localize_script(
			'cux-admin-studio',
			'cuxAdminConfig',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cux_admin_ajax_nonce' ),
			]
		);
	}

	/**
	 * Save settings safely with capability check, nonce check, and sanitization.
	 */
	public static function handle_form_saves(): void {
		if ( ! isset( $_POST['cux_save_settings_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to update Commerce UX Studio settings.', 'commerce-ux-studio' ) );
		}

		check_admin_referer( 'cux_save_studio_settings', 'cux_nonce' );

		$settings = get_option( 'cux_studio_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$tab = isset( $_POST['cux_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['cux_active_tab'] ) ) : 'look';

		if ( 'look' === $tab ) {
			$settings['card_preset']            = isset( $_POST['card_preset'] ) ? sanitize_key( wp_unslash( $_POST['card_preset'] ) ) : 'specialized_spec';
			$settings['shop_hover_mode']        = isset( $_POST['shop_hover_mode'] ) ? sanitize_key( wp_unslash( $_POST['shop_hover_mode'] ) ) : 'angle_2';
			$settings['oos_swatch_mode']        = isset( $_POST['oos_swatch_mode'] ) ? sanitize_key( wp_unslash( $_POST['oos_swatch_mode'] ) ) : 'clickable_preview';
			$settings['max_card_swatches']      = isset( $_POST['max_card_swatches'] ) ? max( 2, min( 12, absint( $_POST['max_card_swatches'] ) ) ) : 4;
			$settings['mobile_gallery_style']   = isset( $_POST['mobile_gallery_style'] ) ? sanitize_key( wp_unslash( $_POST['mobile_gallery_style'] ) ) : 'floating_right_pill';
			$settings['enable_product_schema']  = ! empty( $_POST['enable_product_schema'] ) ? 'yes' : 'no';
			$settings['enable_deeplink_ssr']    = ! empty( $_POST['enable_deeplink_ssr'] ) ? 'yes' : 'no';
		} elseif ( 'autopilot' === $tab ) {
			$settings['stock_calc_mode']        = isset( $_POST['stock_calc_mode'] ) ? sanitize_key( wp_unslash( $_POST['stock_calc_mode'] ) ) : 'grouped_color';
			$settings['auto_copy_color_gallery']= ! empty( $_POST['auto_copy_color_gallery'] ) ? 'yes' : 'no';
			$settings['parent_gallery_mode']    = isset( $_POST['parent_gallery_mode'] ) ? sanitize_key( wp_unslash( $_POST['parent_gallery_mode'] ) ) : 'winning_plus_instock_colors';
			$settings['tie_breaker']            = isset( $_POST['tie_breaker'] ) ? sanitize_key( wp_unslash( $_POST['tie_breaker'] ) ) : 'price_desc';
			$settings['sync_on_csv_import']     = ! empty( $_POST['sync_on_csv_import'] ) ? 'yes' : 'no';
		} elseif ( 'delivery' === $tab ) {
			$settings['enable_delivery_box']    = ! empty( $_POST['enable_delivery_box'] ) ? 'yes' : 'no';
			$settings['sync_wc_shipping']       = ! empty( $_POST['sync_wc_shipping'] ) ? 'yes' : 'no';
			$settings['delivery_box_heading']   = isset( $_POST['delivery_box_heading'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_box_heading'] ) ) : __( 'Delivery & Pickup Options', 'commerce-ux-studio' );
			$settings['default_delivery_label'] = isset( $_POST['default_delivery_label'] ) ? sanitize_text_field( wp_unslash( $_POST['default_delivery_label'] ) ) : __( 'Standard Delivery', 'commerce-ux-studio' );
			$settings['default_delivery_eta']   = isset( $_POST['default_delivery_eta'] ) ? sanitize_text_field( wp_unslash( $_POST['default_delivery_eta'] ) ) : '2-5 days';
			$settings['default_delivery_note']  = isset( $_POST['default_delivery_note'] ) ? sanitize_text_field( wp_unslash( $_POST['default_delivery_note'] ) ) : __( 'Tracked courier shipping', 'commerce-ux-studio' );
			$settings['default_pickup_label']   = isset( $_POST['default_pickup_label'] ) ? sanitize_text_field( wp_unslash( $_POST['default_pickup_label'] ) ) : __( 'In-Store Pickup', 'commerce-ux-studio' );
			$settings['default_pickup_eta']     = isset( $_POST['default_pickup_eta'] ) ? sanitize_text_field( wp_unslash( $_POST['default_pickup_eta'] ) ) : '1-3 days';
			$settings['default_pickup_note']    = isset( $_POST['default_pickup_note'] ) ? sanitize_text_field( wp_unslash( $_POST['default_pickup_note'] ) ) : __( 'Collect from store location', 'commerce-ux-studio' );
			$settings['enable_cross_sell_box']  = ! empty( $_POST['enable_cross_sell_box'] ) ? 'yes' : 'no';
			$settings['cross_sell_heading']     = isset( $_POST['cross_sell_heading'] ) ? sanitize_text_field( wp_unslash( $_POST['cross_sell_heading'] ) ) : __( 'Complete Your Setup', 'commerce-ux-studio' );
			$settings['default_size_guide']     = isset( $_POST['default_size_guide'] ) ? sanitize_text_field( wp_unslash( $_POST['default_size_guide'] ) ) : '';

			// Per-method WooCommerce shipping overrides (custom title, lead time, subtitle, visibility).
			$overrides = [];
			if ( isset( $_POST['wc_method_overrides'] ) && is_array( $_POST['wc_method_overrides'] ) ) {
				foreach ( $_POST['wc_method_overrides'] as $rate_key => $row ) {
					$clean_key = sanitize_text_field( wp_unslash( (string) $rate_key ) );
					if ( '' === $clean_key || ! is_array( $row ) ) {
						continue;
					}
					$overrides[ $clean_key ] = [
						'enabled'  => ! empty( $row['enabled'] ) ? 'yes' : 'no',
						'title'    => isset( $row['title'] ) ? sanitize_text_field( wp_unslash( $row['title'] ) ) : '',
						'eta'      => isset( $row['eta'] ) ? sanitize_text_field( wp_unslash( $row['eta'] ) ) : '',
						'subtitle' => isset( $row['subtitle'] ) ? sanitize_text_field( wp_unslash( $row['subtitle'] ) ) : '',
					];
				}
			}
			$settings['shipping_method_overrides'] = $overrides;
		}

		update_option( 'cux_studio_settings', $settings );

		if ( class_exists( 'CUX_Cache_Manager' ) && method_exists( 'CUX_Cache_Manager', 'flush_all' ) ) {
			CUX_Cache_Manager::flush_all();
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'        => 'commerce-ux-studio',
					'tab'         => $tab,
					'cux_updated' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the 3-Tab Visual Studio with defensive error boundaries.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'look';
		if ( ! in_array( $active_tab, [ 'look', 'autopilot', 'delivery' ], true ) ) {
			$active_tab = 'look';
		}

		$settings = get_option( 'cux_studio_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$ver = defined( 'CUX_STUDIO_VERSION' ) ? CUX_STUDIO_VERSION : '2.2.0';
		?>
		<div class="wrap cux-studio-wrap">
			<div class="cux-studio-header">
				<div class="cux-studio-title-group">
					<span class="dashicons dashicons-art cux-logo-badge"></span>
					<div>
						<h1><?php esc_html_e( 'Commerce UX Studio', 'commerce-ux-studio' ); ?> <span class="cux-ver-pill">v<?php echo esc_html( $ver ); ?></span></h1>
						<p><?php esc_html_e( 'Smart Stock-Synced Variation Galleries, Swatches & WooCommerce Fulfilment UX', 'commerce-ux-studio' ); ?></p>
					</div>
				</div>
			</div>

			<?php if ( isset( $_GET['cux_updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><strong><?php esc_html_e( 'Settings saved!', 'commerce-ux-studio' ); ?></strong> <?php esc_html_e( 'Your storefront cards, swatches, and shipping options have been updated.', 'commerce-ux-studio' ); ?></p>
				</div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper cux-nav-tabs">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=commerce-ux-studio&tab=look' ) ); ?>" class="nav-tab <?php echo 'look' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( '1. Store Look & Feel', 'commerce-ux-studio' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=commerce-ux-studio&tab=autopilot' ) ); ?>" class="nav-tab <?php echo 'autopilot' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( '2. Smart Stock & Gallery Autopilot', 'commerce-ux-studio' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=commerce-ux-studio&tab=delivery' ) ); ?>" class="nav-tab <?php echo 'delivery' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( '3. Delivery & Cross-Sells', 'commerce-ux-studio' ); ?>
				</a>
			</nav>

			<div class="cux-studio-body" style="margin-top:20px;">
				<?php
				try {
					if ( 'look' === $active_tab ) {
						self::render_tab_look_and_feel( $settings );
						self::render_developer_drawer( $settings );
					} elseif ( 'autopilot' === $active_tab ) {
						self::render_tab_stock_autopilot( $settings );
					} elseif ( 'delivery' === $active_tab ) {
						self::render_tab_delivery_and_cross_sells( $settings );
					}
				} catch ( \Throwable $e ) {
					echo '<div class="notice notice-error"><p><strong>Commerce UX Studio Notice:</strong> ' . esc_html( $e->getMessage() ) . '</p></div>';
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab 1: Store Look & Feel.
	 *
	 * @param array<string, mixed> $settings Saved settings.
	 */
	private static function render_tab_look_and_feel( array $settings ): void {
		$card_preset   = $settings['card_preset'] ?? 'specialized_spec';
		$hover_mode    = $settings['shop_hover_mode'] ?? 'angle_2';
		$oos_mode      = $settings['oos_swatch_mode'] ?? 'clickable_preview';
		$max_swatches  = (int) ( $settings['max_card_swatches'] ?? 4 );
		$mobile_gal    = $settings['mobile_gallery_style'] ?? 'floating_right_pill';
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'cux_save_studio_settings', 'cux_nonce' ); ?>
			<input type="hidden" name="cux_save_settings_action" value="1" />
			<input type="hidden" name="cux_active_tab" value="look" />

			<div class="postbox" style="padding:20px; border-radius:8px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Choose Your Shop & Category Card Layout', 'commerce-ux-studio' ); ?></h2>
				<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px; margin-bottom:24px;">
					<?php
					$presets = [
						'specialized_spec'   => [
							'title' => __( 'Style A: Technical Spec Card', 'commerce-ux-studio' ),
							'desc'  => __( 'Soft grey studio card, status pill, dual-color swatches below image, compare box & spec subtitle.', 'commerce-ux-studio' ),
						],
						'pinarello_showroom' => [
							'title' => __( 'Style B: Minimalist Showroom Card', 'commerce-ux-studio' ),
							'desc'  => __( 'Clean white studio frame, top-right spec badge, swatches overlaid inside bottom-left corner.', 'commerce-ux-studio' ),
						],
						'zara_quick_add'     => [
							'title' => __( 'Style C: Apparel Quick-Add Card', 'commerce-ux-studio' ),
							'desc'  => __( 'Color swatches below title plus hover/tap size pills (XS–XXL) for 1-click catalog add to cart.', 'commerce-ux-studio' ),
						],
					];
					foreach ( $presets as $slug => $info ) :
						$checked = ( $card_preset === $slug );
						?>
						<label style="border:2px solid <?php echo $checked ? '#2271b1' : '#dcdcde'; ?>; background:<?php echo $checked ? '#f0f6fc' : '#fff'; ?>; border-radius:8px; padding:16px; cursor:pointer; display:block;">
							<input type="radio" name="card_preset" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $checked ); ?> style="margin-right:6px;" />
							<strong><?php echo esc_html( $info['title'] ); ?></strong>
							<p style="margin:8px 0 0; color:#50575e; font-size:13px;"><?php echo esc_html( $info['desc'] ); ?></p>
						</label>
					<?php endforeach; ?>
				</div>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Card Hover Image Rule', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="shop_hover_mode" class="regular-text">
								<option value="angle_2" <?php selected( $hover_mode, 'angle_2' ); ?>><?php esc_html_e( 'Show 2nd Angle of the Main In-Stock Color', 'commerce-ux-studio' ); ?></option>
								<option value="second_color" <?php selected( $hover_mode, 'second_color' ); ?>><?php esc_html_e( 'Show Main Photo of 2nd In-Stock Color', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Out-of-Stock Swatch Behavior', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="oos_swatch_mode" class="regular-text">
								<option value="clickable_preview" <?php selected( $oos_mode, 'clickable_preview' ); ?>><?php esc_html_e( 'Clickable (Let shoppers view photos + show Out of Stock notice)', 'commerce-ux-studio' ); ?></option>
								<option value="crossed_disabled" <?php selected( $oos_mode, 'crossed_disabled' ); ?>><?php esc_html_e( 'Crossed Out & Disabled', 'commerce-ux-studio' ); ?></option>
								<option value="hidden" <?php selected( $oos_mode, 'hidden' ); ?>><?php esc_html_e( 'Completely Hidden', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Max Color Dots on Shop Cards', 'commerce-ux-studio' ); ?></th>
						<td>
							<input type="number" name="max_card_swatches" value="<?php echo esc_attr( (string) $max_swatches ); ?>" min="2" max="12" class="small-text" />
							<span class="description"><?php esc_html_e( 'Extra colors automatically show a "+X more" counter.', 'commerce-ux-studio' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Mobile Product Gallery Thumbnails', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="mobile_gallery_style" class="regular-text">
								<option value="floating_right_pill" <?php selected( $mobile_gal, 'floating_right_pill' ); ?>><?php esc_html_e( 'Floating Vertical Glass Rail on Right Edge', 'commerce-ux-studio' ); ?></option>
								<option value="floating_bottom_bar" <?php selected( $mobile_gal, 'floating_bottom_bar' ); ?>><?php esc_html_e( 'Floating Bottom Thumbnail Strip', 'commerce-ux-studio' ); ?></option>
								<option value="standard_below" <?php selected( $mobile_gal, 'standard_below' ); ?>><?php esc_html_e( 'Standard Rail Below Main Image', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<p class="submit" style="margin-bottom:0;">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Look & Feel Settings', 'commerce-ux-studio' ); ?></button>
				</p>
			</div>
		</form>
		<?php
	}

	/**
	 * Tab 2: Smart Stock & Gallery Autopilot.
	 *
	 * @param array<string, mixed> $settings Saved settings.
	 */
	private static function render_tab_stock_autopilot( array $settings ): void {
		$calc_mode   = $settings['stock_calc_mode'] ?? 'grouped_color';
		$auto_copy   = $settings['auto_copy_color_gallery'] ?? 'yes';
		$gal_mode    = $settings['parent_gallery_mode'] ?? 'winning_plus_instock_colors';
		$tie_breaker = $settings['tie_breaker'] ?? 'price_desc';
		?>
		<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:20px;">
			<form method="post" action="" class="postbox" style="padding:20px; border-radius:8px; margin:0;">
				<?php wp_nonce_field( 'cux_save_studio_settings', 'cux_nonce' ); ?>
				<input type="hidden" name="cux_save_settings_action" value="1" />
				<input type="hidden" name="cux_active_tab" value="autopilot" />

				<h2 style="margin-top:0;"><?php esc_html_e( 'Autopilot Rules', 'commerce-ux-studio' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'How to Pick the Main Image Winner', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="stock_calc_mode" style="max-width:100%;">
								<option value="grouped_color" <?php selected( $calc_mode, 'grouped_color' ); ?>><?php esc_html_e( 'Best-Stocked Colorway (Sum stock across all sizes of each color)', 'commerce-ux-studio' ); ?></option>
								<option value="single_highest_sku" <?php selected( $calc_mode, 'single_highest_sku' ); ?>><?php esc_html_e( 'Single Highest-Stock Variation', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-Copy Gallery Across Sizes', 'commerce-ux-studio' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="auto_copy_color_gallery" value="yes" <?php checked( $auto_copy, 'yes' ); ?> />
								<?php esc_html_e( 'Upload photos to 1 size of a color -> automatically fill all other sizes of that color', 'commerce-ux-studio' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Main Product Gallery Contents', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="parent_gallery_mode" style="max-width:100%;">
								<option value="winning_plus_instock_colors" <?php selected( $gal_mode, 'winning_plus_instock_colors' ); ?>><?php esc_html_e( 'Winning Color Angles + Main Photo of Other In-Stock Colors (Hides Out-of-Stock)', 'commerce-ux-studio' ); ?></option>
								<option value="winning_angles_only" <?php selected( $gal_mode, 'winning_angles_only' ); ?>><?php esc_html_e( 'Winning Color Angles Only', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tie-Breaker (Equal Stock)', 'commerce-ux-studio' ); ?></th>
						<td>
							<select name="tie_breaker" style="max-width:100%;">
								<option value="price_desc" <?php selected( $tie_breaker, 'price_desc' ); ?>><?php esc_html_e( 'Show Higher-Priced Variation First', 'commerce-ux-studio' ); ?></option>
								<option value="menu_order" <?php selected( $tie_breaker, 'menu_order' ); ?>><?php esc_html_e( 'Follow Variation Menu Order', 'commerce-ux-studio' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<p class="submit" style="margin-bottom:0;">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Autopilot Settings', 'commerce-ux-studio' ); ?></button>
				</p>
			</form>

			<div class="postbox" style="padding:20px; border-radius:8px; margin:0;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'One-Click Storewide Sync', 'commerce-ux-studio' ); ?></h2>
				<p style="color:#50575e;"><?php esc_html_e( 'Products sync automatically whenever you save a product, receive an order, or import a CSV. Click below anytime to recalculate all variable products immediately.', 'commerce-ux-studio' ); ?></p>
				<button type="button" id="cux-bulk-sync-btn" class="button button-primary button-hero">
					<?php esc_html_e( 'Sync All Products Now', 'commerce-ux-studio' ); ?>
				</button>
				<div id="cux-bulk-sync-status" style="margin-top:16px;"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab 3: Universal WooCommerce Shipping Zones, Methods, Classes & Cross-Sells.
	 *
	 * @param array<string, mixed> $settings Saved settings.
	 */
	private static function render_tab_delivery_and_cross_sells( array $settings ): void {
		$enable_box      = $settings['enable_delivery_box'] ?? 'yes';
		$sync_wc         = $settings['sync_wc_shipping'] ?? 'yes';
		$box_heading     = $settings['delivery_box_heading'] ?? __( 'Delivery & Pickup Options', 'commerce-ux-studio' );
		$def_del_label   = $settings['default_delivery_label'] ?? __( 'Standard Delivery', 'commerce-ux-studio' );
		$def_del_eta     = $settings['default_delivery_eta'] ?? '2-5 days';
		$def_del_note    = $settings['default_delivery_note'] ?? __( 'Tracked courier shipping', 'commerce-ux-studio' );
		$def_pick_label  = $settings['default_pickup_label'] ?? __( 'In-Store Pickup', 'commerce-ux-studio' );
		$def_pick_eta    = $settings['default_pickup_eta'] ?? '1-3 days';
		$def_pick_note   = $settings['default_pickup_note'] ?? __( 'Collect from store location', 'commerce-ux-studio' );
		$enable_cs       = $settings['enable_cross_sell_box'] ?? 'yes';
		$cs_heading      = $settings['cross_sell_heading'] ?? __( 'Complete Your Setup', 'commerce-ux-studio' );
		$size_guide      = $settings['default_size_guide'] ?? '';
		$overrides       = isset( $settings['shipping_method_overrides'] ) && is_array( $settings['shipping_method_overrides'] ) ? $settings['shipping_method_overrides'] : [];

		$wc_zones_methods = self::get_all_wc_shipping_methods_safe();
		$wc_classes       = self::get_all_wc_shipping_classes_safe();
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'cux_save_studio_settings', 'cux_nonce' ); ?>
			<input type="hidden" name="cux_save_settings_action" value="1" />
			<input type="hidden" name="cux_active_tab" value="delivery" />

			<div class="postbox" style="padding:20px; border-radius:8px; margin-bottom:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Product Page Delivery & Pickup Cards (Synced with WooCommerce Shipping)', 'commerce-ux-studio' ); ?></h2>
				<p style="color:#50575e;">
					<?php esc_html_e( 'Automatically pulls your active Shipping Zones, Shipping Methods, and Shipping Class rates from WooCommerce > Settings > Shipping, and pre-selects the shopper’s choice on Cart & Checkout.', 'commerce-ux-studio' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ); ?>" target="_blank" style="margin-left:8px;"><?php esc_html_e( 'Open WooCommerce Shipping Settings ↗', 'commerce-ux-studio' ); ?></a>
				</p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show Delivery & Pickup Cards', 'commerce-ux-studio' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_delivery_box" value="yes" <?php checked( $enable_box, 'yes' ); ?> />
								<?php esc_html_e( 'Display interactive Delivery & Pickup cards above Add to Cart on Single Product Pages', 'commerce-ux-studio' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Section Heading Wording', 'commerce-ux-studio' ); ?></th>
						<td>
							<input type="text" name="delivery_box_heading" value="<?php echo esc_attr( $box_heading ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WooCommerce Shipping Sync', 'commerce-ux-studio' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="sync_wc_shipping" value="yes" <?php checked( $sync_wc, 'yes' ); ?> />
								<?php esc_html_e( 'Automatically sync method names & live Shipping Class costs from WooCommerce Shipping Zones', 'commerce-ux-studio' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php if ( ! empty( $wc_zones_methods ) ) : ?>
					<h3 style="margin-top:20px;"><?php esc_html_e( 'Detected WooCommerce Shipping Zones & Methods (Customize Wording & Lead Times)', 'commerce-ux-studio' ); ?></h3>
					<table class="widefat striped" style="margin-bottom:20px;">
						<thead>
							<tr>
								<th style="width:60px;"><?php esc_html_e( 'Show', 'commerce-ux-studio' ); ?></th>
								<th><?php esc_html_e( 'WooCommerce Zone', 'commerce-ux-studio' ); ?></th>
								<th><?php esc_html_e( 'WC Shipping Method', 'commerce-ux-studio' ); ?></th>
								<th><?php esc_html_e( 'Custom Display Label (Optional)', 'commerce-ux-studio' ); ?></th>
								<th><?php esc_html_e( 'Lead Time Badge', 'commerce-ux-studio' ); ?></th>
								<th><?php esc_html_e( 'Subtitle / Note', 'commerce-ux-studio' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $wc_zones_methods as $item ) :
								$rate_key = $item['rate_key'];
								$ov       = $overrides[ $rate_key ] ?? [];
								$enabled  = $ov['enabled'] ?? 'yes';
								$c_title  = $ov['title'] ?? '';
								$c_eta    = $ov['eta'] ?? ( 'local_pickup' === $item['method_id'] ? $def_pick_eta : $def_del_eta );
								$c_sub    = $ov['subtitle'] ?? ( 'local_pickup' === $item['method_id'] ? $def_pick_note : $def_del_note );
								?>
								<tr>
									<td>
										<input type="checkbox" name="wc_method_overrides[<?php echo esc_attr( $rate_key ); ?>][enabled]" value="yes" <?php checked( $enabled, 'yes' ); ?> />
									</td>
									<td><strong><?php echo esc_html( $item['zone_name'] ); ?></strong></td>
									<td>
										<code><?php echo esc_html( $item['wc_title'] ); ?></code>
										<div style="font-size:11px; color:#646970;"><?php echo esc_html( $rate_key ); ?></div>
									</td>
									<td>
										<input type="text" name="wc_method_overrides[<?php echo esc_attr( $rate_key ); ?>][title]" value="<?php echo esc_attr( $c_title ); ?>" placeholder="<?php echo esc_attr( $item['wc_title'] ); ?>" class="regular-text" style="width:100%;" />
									</td>
									<td>
										<input type="text" name="wc_method_overrides[<?php echo esc_attr( $rate_key ); ?>][eta]" value="<?php echo esc_attr( $c_eta ); ?>" placeholder="2-5 days" style="width:110px;" />
									</td>
									<td>
										<input type="text" name="wc_method_overrides[<?php echo esc_attr( $rate_key ); ?>][subtitle]" value="<?php echo esc_attr( $c_sub ); ?>" class="regular-text" style="width:100%;" />
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<?php if ( ! empty( $wc_classes ) ) : ?>
					<div style="background:#f6f7f7; border:1px solid #dcdcde; border-radius:6px; padding:12px 16px; margin-bottom:20px;">
						<strong><?php esc_html_e( 'Active WooCommerce Shipping Classes Detected:', 'commerce-ux-studio' ); ?></strong>
						<?php
						$class_labels = [];
						foreach ( $wc_classes as $sc ) {
							$class_labels[] = esc_html( $sc->name ) . ' (<code>' . esc_html( $sc->slug ) . '</code>)';
						}
						echo wp_kses_post( implode( ' &bull; ', $class_labels ) );
						?>
						<p style="margin:6px 0 0; font-size:12px; color:#50575e;">
							<?php esc_html_e( 'When a product or variation uses one of these Shipping Classes, its exact Flat Rate class fee is automatically calculated and shown on the product page.', 'commerce-ux-studio' ); ?>
						</p>
					</div>
				<?php endif; ?>

				<details style="margin-bottom:16px; border:1px solid #dcdcde; border-radius:6px; padding:12px 16px;">
					<summary style="cursor:pointer; font-weight:600;"><?php esc_html_e( 'Fallback Wording (Used if no WooCommerce Shipping Zone matches a visitor)', 'commerce-ux-studio' ); ?></summary>
					<table class="form-table" style="margin-top:10px;">
						<tr>
							<th scope="row"><?php esc_html_e( 'Fallback Delivery Title & Lead Time', 'commerce-ux-studio' ); ?></th>
							<td>
								<input type="text" name="default_delivery_label" value="<?php echo esc_attr( $def_del_label ); ?>" class="regular-text" />
								<input type="text" name="default_delivery_eta" value="<?php echo esc_attr( $def_del_eta ); ?>" style="width:120px;" />
								<input type="text" name="default_delivery_note" value="<?php echo esc_attr( $def_del_note ); ?>" class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Fallback Pickup Title & Lead Time', 'commerce-ux-studio' ); ?></th>
							<td>
								<input type="text" name="default_pickup_label" value="<?php echo esc_attr( $def_pick_label ); ?>" class="regular-text" />
								<input type="text" name="default_pickup_eta" value="<?php echo esc_attr( $def_pick_eta ); ?>" style="width:120px;" />
								<input type="text" name="default_pickup_note" value="<?php echo esc_attr( $def_pick_note ); ?>" class="regular-text" />
							</td>
						</tr>
					</table>
				</details>
			</div>

			<div class="postbox" style="padding:20px; border-radius:8px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Cross-Sell Accessory Drawer & Size Guide', 'commerce-ux-studio' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Inline Cross-Sell Drawer', 'commerce-ux-studio' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_cross_sell_box" value="yes" <?php checked( $enable_cs, 'yes' ); ?> />
								<?php esc_html_e( 'Turn WooCommerce Linked Products > Cross-sells into an interactive expandable drawer below Add to Cart', 'commerce-ux-studio' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cross-Sell Drawer Heading', 'commerce-ux-studio' ); ?></th>
						<td>
							<input type="text" name="cross_sell_heading" value="<?php echo esc_attr( $cs_heading ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Global Size Guide Block Slug / ID', 'commerce-ux-studio' ); ?></th>
						<td>
							<input type="text" name="default_size_guide" value="<?php echo esc_attr( $size_guide ); ?>" placeholder="e.g., size-guide" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Optional Flatsome UX Block slug or Page ID shown when shoppers click "Size Guide" next to Size options.', 'commerce-ux-studio' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit" style="margin-bottom:0;">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Delivery & Cross-Sell Settings', 'commerce-ux-studio' ); ?></button>
				</p>
			</div>
		</form>
		<?php
	}

	/**
	 * Collapsed Developer, Schema & Module Health Drawer.
	 *
	 * @param array<string, mixed> $settings Saved settings.
	 */
	private static function render_developer_drawer( array $settings ): void {
		$schema_on   = $settings['enable_product_schema'] ?? 'yes';
		$deeplink_on = $settings['enable_deeplink_ssr'] ?? 'yes';
		$health      = ( class_exists( 'CUX_Loader' ) && method_exists( 'CUX_Loader', 'get_module_health' ) )
			? CUX_Loader::get_module_health()
			: [];
		?>
		<details class="postbox" style="padding:16px 20px; border-radius:8px; margin-top:20px;">
			<summary style="cursor:pointer; font-weight:600; font-size:14px;">
				<?php esc_html_e( 'Developer, SEO Schema & System Health Tools (Click to Expand)', 'commerce-ux-studio' ); ?>
			</summary>
			<div style="margin-top:16px;">
				<?php if ( ! empty( $health ) ) : ?>
					<h3><?php esc_html_e( 'Installed Plugin Modules', 'commerce-ux-studio' ); ?></h3>
					<table class="widefat striped" style="max-width:720px; margin-bottom:20px;">
						<tbody>
							<?php foreach ( $health as $label => $ok ) : ?>
								<tr>
									<td><?php echo esc_html( $label ); ?></td>
									<td style="width:110px; text-align:right;">
										<?php if ( $ok ) : ?>
											<span style="background:#dcfce7; color:#166534; padding:2px 8px; border-radius:999px; font-weight:600; font-size:12px;">Loaded</span>
										<?php else : ?>
											<span style="background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:999px; font-weight:600; font-size:12px;">Missing</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<p style="color:#50575e; font-size:13px;">
					<?php
					printf(
						/* translators: 1: Schema status, 2: Deep link status */
						esc_html__( 'Google ProductGroup JSON-LD Schema: %1$s | Server-Side Variant Deep-Linking: %2$s', 'commerce-ux-studio' ),
						'<strong>' . esc_html( strtoupper( $schema_on ) ) . '</strong>',
						'<strong>' . esc_html( strtoupper( $deeplink_on ) ) . '</strong>'
					);
					?>
				</p>
			</div>
		</details>
		<?php
	}

	/**
	 * Safely retrieve all configured WooCommerce Shipping Zones and their enabled methods.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_all_wc_shipping_methods_safe(): array {
		$results = [];
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $results;
		}

		try {
			$zones = WC_Shipping_Zones::get_zones();
			// Include Zone 0 ("Locations not covered by your other zones").
			$zones[0] = [ 'zone_id' => 0 ];

			foreach ( $zones as $zone_data ) {
				$zone_id = isset( $zone_data['zone_id'] ) ? (int) $zone_data['zone_id'] : (int) ( $zone_data['id'] ?? 0 );
				$zone    = new WC_Shipping_Zone( $zone_id );
				$methods = $zone->get_shipping_methods( true );

				foreach ( $methods as $instance_id => $method ) {
					if ( ! is_object( $method ) || ! isset( $method->id ) ) {
						continue;
					}
					$rate_key  = $method->id . ':' . $instance_id;
					$wc_title  = method_exists( $method, 'get_title' ) ? $method->get_title() : ( $method->title ?? $method->id );
					$results[] = [
						'zone_id'     => (string) $zone_id,
						'zone_name'   => $zone->get_zone_name(),
						'method_id'   => (string) $method->id,
						'instance_id' => (string) $instance_id,
						'rate_key'    => $rate_key,
						'wc_title'    => (string) $wc_title,
					];
				}
			}
		} catch ( \Throwable $e ) {
			return [];
		}

		return $results;
	}

	/**
	 * Safely retrieve WooCommerce Shipping Classes.
	 *
	 * @return array<int, object>
	 */
	private static function get_all_wc_shipping_classes_safe(): array {
		try {
			if ( function_exists( 'WC' ) && WC()->shipping() ) {
				$classes = WC()->shipping()->get_shipping_classes();
				return is_array( $classes ) ? $classes : [];
			}
		} catch ( \Throwable $e ) {
			return [];
		}
		return [];
	}
}
