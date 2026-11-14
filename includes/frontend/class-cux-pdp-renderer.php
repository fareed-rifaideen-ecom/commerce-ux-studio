<?php
/**
 * Single Product Swatches, Responsive Layout, Live WC Shipping Sync & Cross-Sell Drawer
 *
 * File Path: includes/frontend/class-cux-pdp-renderer.php
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_PDP_Renderer {

	/**
	 * Register Single Product and AJAX hooks.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', [ __CLASS__, 'render_swatches_html' ], 20, 2 );
		add_action( 'woocommerce_before_add_to_cart_button', [ __CLASS__, 'render_delivery_and_low_stock_block' ], 15 );
		add_action( 'woocommerce_after_add_to_cart_form', [ __CLASS__, 'render_complete_your_ride_block' ], 20 );
		add_action( 'wp_footer', [ __CLASS__, 'render_pdp_json_payload' ], 30 );

		add_action( 'wp_ajax_cux_set_shipping_method', [ __CLASS__, 'ajax_set_shipping_method' ] );
		add_action( 'wp_ajax_nopriv_cux_set_shipping_method', [ __CLASS__, 'ajax_set_shipping_method' ] );
	}

	/**
	 * Enqueue frontend CSS and JS on product and shop views.
	 */
	public static function enqueue_assets(): void {
		if ( ! function_exists( 'is_woocommerce' ) || ( ! is_product() && ! is_shop() && ! is_product_category() && ! is_product_tag() ) ) {
			return;
		}

		$ver = defined( 'CUX_STUDIO_VERSION' ) ? CUX_STUDIO_VERSION : '2.2.0';

		wp_enqueue_style(
			'cux-frontend-ux',
			CUX_STUDIO_URL . 'assets/css/frontend-ux.css',
			[],
			$ver
		);

		wp_enqueue_script(
			'cux-frontend-ux',
			CUX_STUDIO_URL . 'assets/js/frontend-ux.js',
			[ 'jquery' ],
			$ver,
			true
		);

		wp_localize_script(
			'cux-frontend-ux',
			'cuxFrontendConfig',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cux_frontend_nonce' ),
				'i18n'    => [
					'chooseOptions' => __( 'Choose Options', 'commerce-ux-studio' ),
					'addToCart'     => __( 'Add to Cart', 'commerce-ux-studio' ),
					'outOfStock'    => __( 'Out of Stock', 'commerce-ux-studio' ),
				],
			]
		);
	}

	/**
	 * Replace WooCommerce attribute dropdowns with high-contrast Swatches (Color Circles, Size Boxes & Spec Cards).
	 *
	 * @param string               $html Original select HTML.
	 * @param array<string, mixed> $args Dropdown arguments.
	 * @return string
	 */
	public static function render_swatches_html( string $html, array $args ): string {
		if ( ! is_product() || empty( $args['options'] ) || empty( $args['product'] ) || ! ( $args['product'] instanceof WC_Product ) ) {
			return $html;
		}

		$product   = $args['product'];
		$attribute = (string) $args['attribute'];
		$options   = (array) $args['options'];
		$selected  = (string) ( $args['selected'] ?? '' );
		$attr_slug = sanitize_title( str_replace( 'attribute_', '', $attribute ) );
		$attr_name = wc_attribute_label( $attribute, $product );

		$visual_role  = self::detect_attribute_role( $attribute );
		$swatch_style = self::detect_swatch_style( $attribute, $visual_role );

		// Resolve active human-readable label.
		$selected_label = '';
		if ( '' !== $selected ) {
			if ( taxonomy_exists( $attribute ) ) {
				$term_obj       = get_term_by( 'slug', $selected, $attribute );
				$selected_label = $term_obj ? $term_obj->name : $selected;
			} else {
				$selected_label = $selected;
			}
		}

		$size_guide_html = '';
		if ( 'dimensional' === $visual_role ) {
			$guide_slug = self::get_size_guide_target( $product );
			if ( '' !== $guide_slug ) {
				$size_guide_html = sprintf(
					'<a href="#cux-size-guide-%1$s" class="cux-size-guide-trigger" data-guide="%1$s" style="font-size:12px;font-weight:600;text-decoration:underline;color:#0f172a;margin-left:auto;">%2$s</a>',
					esc_attr( $guide_slug ),
					esc_html__( 'Size Guide', 'commerce-ux-studio' )
				);
			}
		}

		// Keep native select hidden for WooCommerce variation form script compatibility.
		$hidden_select = '<div class="cux-hidden-select" style="display:none !important;">' . $html . '</div>';

		ob_start();
		?>
		<div class="cux-attr-block" data-attribute="<?php echo esc_attr( 'attribute_' . $attr_slug ); ?>" data-role="<?php echo esc_attr( $visual_role ); ?>" style="width:100%;margin-bottom:18px;display:block;">
			<div class="cux-attr-header" style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px;width:100%;">
				<div style="font-size:13px;line-height:1.4;color:#0f172a;">
					<strong style="text-transform:uppercase;letter-spacing:0.04em;font-weight:700;color:#0f172a;"><?php echo esc_html( $attr_name ); ?>:</strong>
					<span class="cux-selected-term-label" style="margin-left:6px;font-weight:500;color:#475569;"><?php echo esc_html( $selected_label ); ?></span>
				</div>
				<?php echo $size_guide_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<div class="cux-swatch-group cux-style-<?php echo esc_attr( $swatch_style ); ?>" role="radiogroup" aria-label="<?php echo esc_attr( $attr_name ); ?>" style="display:flex;flex-wrap:wrap;gap:10px;width:100%;">
				<?php
				foreach ( $options as $option_value ) :
					$term_obj   = taxonomy_exists( $attribute ) ? get_term_by( 'slug', $option_value, $attribute ) : null;
					$term_label = $term_obj ? $term_obj->name : (string) $option_value;
					$term_id    = $term_obj ? (int) $term_obj->term_id : 0;
					$is_active  = ( (string) $selected === (string) $option_value );

					if ( 'color_circle' === $swatch_style ) :
						$bg_css = self::build_term_swatch_background( $term_id, $term_label, $product, $attribute, (string) $option_value );
						?>
						<button type="button"
							class="cux-swatch-item cux-swatch-circle <?php echo $is_active ? 'is-selected' : ''; ?>"
							data-value="<?php echo esc_attr( (string) $option_value ); ?>"
							data-label="<?php echo esc_attr( $term_label ); ?>"
							aria-checked="<?php echo $is_active ? 'true' : 'false'; ?>"
							title="<?php echo esc_attr( $term_label ); ?>"
							style="width:36px;height:36px;min-width:36px;padding:0;border-radius:999px;border:2px solid <?php echo $is_active ? '#0f172a' : '#cbd5e1'; ?>;box-shadow:<?php echo $is_active ? '0 0 0 2px #ffffff, 0 0 0 4px #0f172a' : 'none'; ?>;<?php echo esc_attr( $bg_css ); ?>cursor:pointer;">
							<span class="screen-reader-text"><?php echo esc_html( $term_label ); ?></span>
						</button>
					<?php else : ?>
						<button type="button"
							class="cux-swatch-item cux-swatch-box <?php echo $is_active ? 'is-selected' : ''; ?>"
							data-value="<?php echo esc_attr( (string) $option_value ); ?>"
							data-label="<?php echo esc_attr( $term_label ); ?>"
							aria-checked="<?php echo $is_active ? 'true' : 'false'; ?>"
							style="min-width:48px;min-height:42px;padding:8px 16px;border-radius:6px;border:1.5px solid <?php echo $is_active ? '#0f172a' : '#94a3b8'; ?>;background-color:<?php echo $is_active ? '#0f172a' : '#ffffff'; ?>;color:<?php echo $is_active ? '#ffffff' : '#0f172a'; ?>;font-size:13px;font-weight:700;line-height:1.2;text-transform:none;letter-spacing:0;margin:0;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;">
							<?php echo esc_html( $term_label ); ?>
						</button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return $hidden_select . (string) ob_get_clean();
	}

	/**
	 * Render live WooCommerce Shipping Zones / Classes Delivery & Pickup Cards + Low Stock Notice.
	 */
	public static function render_delivery_and_low_stock_block(): void {
		global $product;
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		$settings = get_option( 'cux_studio_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		if ( 'no' !== ( $settings['enable_delivery_box'] ?? 'yes' ) ) {
			$cards   = self::get_live_woocommerce_delivery_options( $product, $settings );
			$heading = $settings['delivery_box_heading'] ?? __( 'Delivery & Pickup Options', 'commerce-ux-studio' );

			if ( ! empty( $cards ) ) :
				?>
				<div class="cux-delivery-wrapper" style="width:100%;margin:16px 0;padding:14px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;">
					<div style="font-size:13px;font-weight:700;color:#0f172a;margin-bottom:10px;">
						<?php echo esc_html( $heading ); ?>
					</div>
					<div class="cux-delivery-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(175px, 1fr));gap:10px;">
						<?php foreach ( $cards as $index => $card ) : ?>
							<div class="cux-delivery-card <?php echo ! empty( $card['selected'] ) ? 'is-selected' : ''; ?>"
								role="button"
								tabindex="0"
								data-rate-id="<?php echo esc_attr( $card['rate_id'] ); ?>"
								style="background:#ffffff;border:2px solid <?php echo ! empty( $card['selected'] ) ? '#0f172a' : '#cbd5e1'; ?>;border-radius:8px;padding:12px;cursor:pointer;text-align:left;">
								<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:6px;margin-bottom:6px;">
									<strong style="font-size:13px;line-height:1.3;color:#0f172a;"><?php echo esc_html( $card['title'] ); ?></strong>
									<?php if ( '' !== $card['eta'] ) : ?>
										<span style="font-size:11px;font-weight:600;color:#475569;white-space:nowrap;"><?php echo esc_html( $card['eta'] ); ?></span>
									<?php endif; ?>
								</div>
								<div style="font-size:12px;color:#334155;line-height:1.4;">
									<?php if ( '' !== $card['price_html'] ) : ?>
										<div style="font-weight:700;color:#0f172a;margin-bottom:2px;"><?php echo wp_kses_post( $card['price_html'] ); ?></div>
									<?php endif; ?>
									<?php if ( '' !== $card['subtitle'] ) : ?>
										<div style="color:#64748b;font-size:11.5px;"><?php echo esc_html( $card['subtitle'] ); ?></div>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
			endif;
		}

		// Render Low Stock warning banner if product/default variation stock is at or below WooCommerce threshold.
		$low_qty = self::get_effective_low_stock_qty( $product );
		if ( $low_qty > 0 ) :
			?>
			<div class="cux-low-stock-banner" style="margin:12px 0;padding:10px 14px;border-radius:6px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:13px;font-weight:600;">
				<?php
				printf(
					/* translators: %d: remaining stock quantity */
					esc_html__( 'Low Stock — Only %d left', 'commerce-ux-studio' ),
					(int) $low_qty
				);
				?>
			</div>
			<style>.single-product .summary p.stock.in-stock { display: none !important; }</style>
			<?php
		endif;
	}

	/**
	 * Render expandable "Complete Your Setup" Cross-Sell drawer using native WooCommerce Cross-sells.
	 */
	public static function render_complete_your_ride_block(): void {
		global $product;
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		$settings = get_option( 'cux_studio_settings', [] );
		if ( 'no' === ( $settings['enable_cross_sell_box'] ?? 'yes' ) ) {
			return;
		}

		$cross_sell_ids = $product->get_cross_sell_ids();
		if ( empty( $cross_sell_ids ) ) {
			return;
		}

		$heading = $settings['cross_sell_heading'] ?? __( 'Complete Your Setup', 'commerce-ux-studio' );
		?>
		<div class="cux-cross-sell-drawer" style="margin-top:22px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;background:#ffffff;">
			<div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:700;font-size:14px;color:#0f172a;">
				<?php echo esc_html( $heading ); ?>
			</div>
			<div style="padding:12px 16px;display:flex;flex-direction:column;gap:12px;">
				<?php
				foreach ( array_slice( $cross_sell_ids, 0, 4 ) as $cs_id ) :
					$cs_product = wc_get_product( $cs_id );
					if ( ! $cs_product || ! $cs_product->is_visible() ) {
						continue;
					}
					$thumb = wp_get_attachment_image_url( (int) $cs_product->get_image_id(), 'woocommerce_thumbnail' );
					?>
					<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;">
						<div style="display:flex;align-items:center;gap:10px;">
							<?php if ( $thumb ) : ?>
								<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $cs_product->get_name() ); ?>" style="width:48px;height:48px;object-fit:contain;border-radius:6px;background:#f8fafc;" />
							<?php endif; ?>
							<div>
								<a href="<?php echo esc_url( $cs_product->get_permalink() ); ?>" style="font-weight:600;font-size:13px;color:#0f172a;text-decoration:none;display:block;">
									<?php echo esc_html( $cs_product->get_name() ); ?>
								</a>
								<span style="font-size:12.5px;color:#475569;"><?php echo wp_kses_post( $cs_product->get_price_html() ); ?></span>
							</div>
						</div>
						<a href="<?php echo esc_url( $cs_product->add_to_cart_url() ); ?>" class="button" style="margin:0;padding:6px 12px;font-size:12px;border-radius:6px;">
							<?php echo esc_html( $cs_product->is_type( 'simple' ) ? __( 'Add', 'commerce-ux-studio' ) : __( 'Select', 'commerce-ux-studio' ) ); ?>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output precomputed gallery & variation JSON payload in footer for zero-lag swatch switching.
	 */
	public static function render_pdp_json_payload(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		global $product;
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		$cache = get_post_meta( $product->get_id(), '_cux_product_cache', true );
		if ( ! is_array( $cache ) ) {
			$cache = [];
		}

		echo '<script id="cux-pdp-data" type="application/json">' . wp_json_encode( $cache ) . '</script>' . "\n";
	}

	/**
	 * AJAX handler to sync shopper's selected shipping method directly into WooCommerce Checkout session.
	 */
	public static function ajax_set_shipping_method(): void {
		check_ajax_referer( 'cux_frontend_nonce', 'nonce' );

		$rate_id = isset( $_POST['rate_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rate_id'] ) ) : '';
		if ( '' !== $rate_id && function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'chosen_shipping_methods', [ $rate_id ] );
			wp_send_json_success( [ 'chosen' => $rate_id ] );
		}

		wp_send_json_error();
	}

	/**
	 * Build live delivery/pickup cards from WooCommerce Shipping Zones, Methods & Product Shipping Classes.
	 *
	 * @param WC_Product           $product  Current product.
	 * @param array<string, mixed> $settings Studio settings.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_live_woocommerce_delivery_options( WC_Product $product, array $settings ): array {
		$cards     = [];
		$sync_wc   = ( 'yes' === ( $settings['sync_wc_shipping'] ?? 'yes' ) );
		$overrides = isset( $settings['shipping_method_overrides'] ) && is_array( $settings['shipping_method_overrides'] ) ? $settings['shipping_method_overrides'] : [];
		$chosen    = ( function_exists( 'WC' ) && WC()->session ) ? (array) WC()->session->get( 'chosen_shipping_methods', [] ) : [];

		if ( $sync_wc && class_exists( 'WC_Shipping_Zones' ) ) {
			try {
				$zone = self::resolve_active_shipping_zone();
				if ( $zone instanceof WC_Shipping_Zone ) {
					$methods = $zone->get_shipping_methods( true );
					foreach ( $methods as $instance_id => $method ) {
						if ( ! is_object( $method ) || ! isset( $method->id ) ) {
							continue;
						}
						$rate_id = $method->id . ':' . $instance_id;
						$ov      = $overrides[ $rate_id ] ?? [];

						if ( isset( $ov['enabled'] ) && 'no' === $ov['enabled'] ) {
							continue;
						}

						$wc_title = method_exists( $method, 'get_title' ) ? $method->get_title() : ( $method->title ?? $method->id );
						$title    = ! empty( $ov['title'] ) ? $ov['title'] : $wc_title;
						$eta      = $ov['eta'] ?? ( 'local_pickup' === $method->id ? ( $settings['default_pickup_eta'] ?? '1-3 days' ) : ( $settings['default_delivery_eta'] ?? '2-5 days' ) );
						$subtitle = $ov['subtitle'] ?? ( 'local_pickup' === $method->id ? ( $settings['default_pickup_note'] ?? '' ) : ( $settings['default_delivery_note'] ?? '' ) );

						$price_html = self::calculate_method_price_for_product( $method, $product );

						$cards[] = [
							'rate_id'    => $rate_id,
							'title'      => (string) $title,
							'eta'        => (string) $eta,
							'price_html' => $price_html,
							'subtitle'   => (string) $subtitle,
							'selected'   => in_array( $rate_id, $chosen, true ) || ( empty( $chosen ) && empty( $cards ) ),
						];
					}
				}
			} catch ( \Throwable $e ) {
				$cards = [];
			}
		}

		// Store-agnostic fallback cards if no WooCommerce Shipping Zones are configured yet.
		if ( empty( $cards ) ) {
			$cards = [
				[
					'rate_id'    => 'flat_rate:default',
					'title'      => $settings['default_delivery_label'] ?? __( 'Standard Delivery', 'commerce-ux-studio' ),
					'eta'        => $settings['default_delivery_eta'] ?? '2-5 days',
					'price_html' => '',
					'subtitle'   => $settings['default_delivery_note'] ?? __( 'Tracked courier shipping', 'commerce-ux-studio' ),
					'selected'   => true,
				],
				[
					'rate_id'    => 'local_pickup:default',
					'title'      => $settings['default_pickup_label'] ?? __( 'In-Store Pickup', 'commerce-ux-studio' ),
					'eta'        => $settings['default_pickup_eta'] ?? '1-3 days',
					'price_html' => __( 'Free', 'commerce-ux-studio' ),
					'subtitle'   => $settings['default_pickup_note'] ?? __( 'Collect from store location', 'commerce-ux-studio' ),
					'selected'   => false,
				],
			];
		}

		return $cards;
	}

	/**
	 * Resolve the customer's active WooCommerce Shipping Zone (or primary store zone).
	 *
	 * @return WC_Shipping_Zone|null
	 */
	private static function resolve_active_shipping_zone(): ?WC_Shipping_Zone {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return null;
		}

		$country  = '';
		$state    = '';
		$postcode = '';

		if ( function_exists( 'WC' ) && WC()->customer ) {
			$country  = (string) WC()->customer->get_shipping_country();
			$state    = (string) WC()->customer->get_shipping_state();
			$postcode = (string) WC()->customer->get_shipping_postcode();
		}

		if ( '' === $country && function_exists( 'WC' ) && WC()->countries ) {
			$country = (string) WC()->countries->get_base_country();
			$state   = (string) WC()->countries->get_base_state();
		}

		if ( '' !== $country ) {
			$matched = WC_Shipping_Zones::get_zone_matching_package(
				[
					'destination' => [
						'country'  => $country,
						'state'    => $state,
						'postcode' => $postcode,
					],
				]
			);
			if ( $matched instanceof WC_Shipping_Zone && count( $matched->get_shipping_methods( true ) ) > 0 ) {
				return $matched;
			}
		}

		// Otherwise return the first configured shipping zone that has active methods.
		$all_zones = WC_Shipping_Zones::get_zones();
		foreach ( $all_zones as $z_data ) {
			$z_id = (int) ( $z_data['zone_id'] ?? $z_data['id'] ?? 0 );
			$z    = new WC_Shipping_Zone( $z_id );
			if ( count( $z->get_shipping_methods( true ) ) > 0 ) {
				return $z;
			}
		}

		return new WC_Shipping_Zone( 0 );
	}

	/**
	 * Calculate the shipping cost for a specific product including its WooCommerce Shipping Class rate.
	 *
	 * @param object     $method  WooCommerce shipping method instance.
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	private static function calculate_method_price_for_product( object $method, WC_Product $product ): string {
		$method_id = (string) ( $method->id ?? '' );

		if ( 'free_shipping' === $method_id ) {
			return esc_html__( 'Free', 'commerce-ux-studio' );
		}

		if ( 'local_pickup' === $method_id ) {
			$pickup_cost = (float) ( $method->cost ?? 0 );
			return $pickup_cost > 0 ? wc_price( $pickup_cost ) : esc_html__( 'Free', 'commerce-ux-studio' );
		}

		if ( 'flat_rate' === $method_id ) {
			$base_cost  = is_numeric( $method->cost ?? '' ) ? (float) $method->cost : 0.0;
			$class_cost = 0.0;
			$class_id   = (int) $product->get_shipping_class_id();

			if ( $class_id > 0 && method_exists( $method, 'get_option' ) ) {
				$raw_class_cost = $method->get_option( 'class_cost_' . $class_id, '' );
				if ( is_numeric( $raw_class_cost ) ) {
					$class_cost = (float) $raw_class_cost;
				}
			} elseif ( method_exists( $method, 'get_option' ) ) {
				$raw_no_class = $method->get_option( 'no_class_cost', '' );
				if ( is_numeric( $raw_no_class ) ) {
					$class_cost = (float) $raw_no_class;
				}
			}

			$total = $base_cost + $class_cost;
			return $total > 0 ? wc_price( $total ) : esc_html__( 'Free', 'commerce-ux-studio' );
		}

		return '';
	}

	/**
	 * Auto-classify attribute role (primary_visual, secondary_visual, or dimensional).
	 *
	 * @param string $attribute Attribute slug.
	 * @return string
	 */
	private static function detect_attribute_role( string $attribute ): string {
		$slug = strtolower( $attribute );
		if ( false !== strpos( $slug, 'color' ) || false !== strpos( $slug, 'colour' ) ) {
			return 'primary_visual';
		}
		if ( false !== strpos( $slug, 'groupset' ) || false !== strpos( $slug, 'wheelset' ) || false !== strpos( $slug, 'spec' ) ) {
			return 'secondary_visual';
		}
		return 'dimensional';
	}

	/**
	 * Determine frontend swatch visual style.
	 *
	 * @param string $attribute   Attribute slug.
	 * @param string $visual_role Detected role.
	 * @return string
	 */
	private static function detect_swatch_style( string $attribute, string $visual_role ): string {
		if ( 'primary_visual' === $visual_role ) {
			return 'color_circle';
		}
		return 'button_box';
	}

	/**
	 * Build CSS background for a color term (supports uploaded texture image, dual-color split, gradient, metallic, or auto-detected name).
	 *
	 * @param int        $term_id    Term ID.
	 * @param string     $term_label Term display name.
	 * @param WC_Product $product    Product object.
	 * @param string     $attribute  Attribute taxonomy.
	 * @param string     $option_val Term slug.
	 * @return string
	 */
	private static function build_term_swatch_background( int $term_id, string $term_label, WC_Product $product, string $attribute, string $option_val ): string {
		if ( $term_id > 0 ) {
			$img_id = (int) get_term_meta( $term_id, '_cux_swatch_image_id', true );
			if ( $img_id > 0 ) {
				$url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
				if ( $url ) {
					return 'background-image:url(' . esc_url( $url ) . ');background-size:cover;background-position:center;';
				}
			}

			$hex1   = (string) get_term_meta( $term_id, '_cux_swatch_hex', true );
			$hex2   = (string) get_term_meta( $term_id, '_cux_swatch_hex_2', true );
			$finish = (string) get_term_meta( $term_id, '_cux_swatch_finish', true );

			if ( '' !== $hex1 ) {
				if ( '' !== $hex2 ) {
					if ( 'ombre_gradient' === $finish ) {
						return "background:linear-gradient(135deg, {$hex1} 0%, {$hex2} 100%);";
					}
					$angle = ( 'angled_45' === $finish ) ? '135deg' : '180deg';
					return "background:linear-gradient({$angle}, {$hex1} 50%, {$hex2} 50%);";
				}
				if ( 'metallic' === $finish ) {
					return "background:radial-gradient(circle at 30% 30%, rgba(255,255,255,0.45), transparent 55%), {$hex1};";
				}
				return "background-color:{$hex1};";
			}
		}

		// Automatic multi-word color keyword detection when client hasn't set custom hexes yet.
		$name = strtolower( $term_label );
		$map  = [
			'black'    => '#18181b',
			'white'    => '#f8fafc',
			'red'      => '#dc2626',
			'blue'     => '#2563eb',
			'navy'     => '#1e3a8a',
			'green'    => '#16a34a',
			'teal'     => '#0f766e',
			'yellow'   => '#eab308',
			'orange'   => '#ea580c',
			'gold'     => '#ca8a04',
			'silver'   => '#cbd5e1',
			'chrome'   => '#94a3b8',
			'grey'     => '#64748b',
			'gray'     => '#64748b',
			'pink'     => '#ec4899',
			'purple'   => '#7c3aed',
			'brown'    => '#78350f',
		];

		$found = [];
		foreach ( $map as $word => $hex ) {
			if ( false !== strpos( $name, $word ) ) {
				$found[] = $hex;
			}
		}

		if ( count( $found ) >= 2 ) {
			return "background:linear-gradient(135deg, {$found[0]} 50%, {$found[1]} 50%);";
		}
		if ( 1 === count( $found ) ) {
			return "background-color:{$found[0]};";
		}

		return 'background-color:#334155;';
	}

	/**
	 * Resolve effective low-stock quantity if stock is at or below WooCommerce threshold.
	 *
	 * @param WC_Product $product Product object.
	 * @return int
	 */
	private static function get_effective_low_stock_qty( WC_Product $product ): int {
		$threshold = (int) $product->get_low_stock_amount();
		if ( $threshold <= 0 ) {
			$threshold = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		}

		if ( $product->is_type( 'variable' ) ) {
			$defaults = $product->get_default_attributes();
			foreach ( $product->get_children() as $child_id ) {
				$var = wc_get_product( $child_id );
				if ( ! $var || ! $var->managing_stock() ) {
					continue;
				}
				$qty = (int) $var->get_stock_quantity();
				if ( $qty > 0 && $qty <= $threshold ) {
					if ( empty( $defaults ) ) {
						return $qty;
					}
					$match = true;
					foreach ( $defaults as $k => $v ) {
						if ( (string) $var->get_attribute( $k ) !== (string) $v ) {
							$match = false;
							break;
						}
					}
					if ( $match ) {
						return $qty;
					}
				}
			}
		} elseif ( $product->managing_stock() ) {
			$qty = (int) $product->get_stock_quantity();
			if ( $qty > 0 && $qty <= $threshold ) {
				return $qty;
			}
		}

		return 0;
	}

	/**
	 * Resolve Size Guide block slug from Product -> Category -> Global setting.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	private static function get_size_guide_target( WC_Product $product ): string {
		$prod_guide = (string) get_post_meta( $product->get_id(), '_cux_size_guide', true );
		if ( '' !== $prod_guide ) {
			return $prod_guide;
		}

		$settings = get_option( 'cux_studio_settings', [] );
		return (string) ( $settings['default_size_guide'] ?? '' );
	}
}
