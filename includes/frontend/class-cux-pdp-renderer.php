<?php
/**
 * Single Product Page (PDP) Swatches, Flatsome Flickity Gallery Bridge,
 * Universal WooCommerce Shipping Zone/Class Delivery Switcher & "Complete Your Ride" Configurator.
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
	 * Initialize frontend PDP hooks and AJAX handlers.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ), 30 );
		add_filter( 'body_class', array( __CLASS__, 'add_pdp_body_classes' ) );

		// 1. Render Technical Spec Subtitle & Badges below Single Product Title.
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_pdp_spec_subtitle' ), 6 );

		// 2. Enhance Variation Attribute Dropdowns with Visual Swatches, Size Guide & Spec Radio Cards.
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( __CLASS__, 'render_custom_swatch_controls' ), 20, 2 );

		// 3. Inject Deduplicated Visual Gallery & Availability JSON payload before Variations Form.
		add_action( 'woocommerce_before_variations_form', array( __CLASS__, 'render_pdp_json_payload' ), 10 );

		// 4. Render Universal WooCommerce Delivery Method Switcher & Low Stock Warning above Add to Cart button.
		add_action( 'woocommerce_before_single_variation', array( __CLASS__, 'render_delivery_and_low_stock_block' ), 25 );

		// 5. Sync customer's selected Delivery Method Card directly into WooCommerce Cart & Checkout Shipping Session.
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'sync_chosen_delivery_method_to_wc_session' ), 20 );
		add_action( 'wp_ajax_cux_set_shipping_method', array( __CLASS__, 'ajax_set_shipping_method' ) );
		add_action( 'wp_ajax_nopriv_cux_set_shipping_method', array( __CLASS__, 'ajax_set_shipping_method' ) );

		// 6. Render In-Page "Complete Your Ride" Cross-Sell Configurator below Add to Cart form.
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'render_complete_your_ride_block' ), 15 );

		// 7. AJAX Quick Add-to-Cart endpoint (used by Complete Your Ride & Catalog Quick-Add).
		add_action( 'wp_ajax_cux_quick_add_to_cart', array( __CLASS__, 'ajax_quick_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_cux_quick_add_to_cart', array( __CLASS__, 'ajax_quick_add_to_cart' ) );
	}

	/**
	 * Enqueue frontend CSS and JS on WooCommerce pages (uses filemtime for automatic cache busting).
	 */
	public static function enqueue_frontend_assets(): void {
		if ( ! function_exists( 'is_woocommerce' ) || ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_front_page() ) ) {
			return;
		}

		$css_ver = file_exists( CUX_STUDIO_PATH . 'assets/css/frontend-ux.css' ) ? (string) filemtime( CUX_STUDIO_PATH . 'assets/css/frontend-ux.css' ) : CUX_STUDIO_VERSION;
		$js_ver  = file_exists( CUX_STUDIO_PATH . 'assets/js/frontend-ux.js' ) ? (string) filemtime( CUX_STUDIO_PATH . 'assets/js/frontend-ux.js' ) : CUX_STUDIO_VERSION;

		wp_enqueue_style(
			'cux-frontend-ux',
			CUX_STUDIO_URL . 'assets/css/frontend-ux.css',
			array(),
			$css_ver
		);

		wp_enqueue_script(
			'cux-frontend-ux',
			CUX_STUDIO_URL . 'assets/js/frontend-ux.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		wp_localize_script(
			'cux-frontend-ux',
			'cuxFrontendVars',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'wcAjaxUrl'      => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( '%%endpoint%%' ) : '',
				'nonce'          => wp_create_nonce( 'cux_frontend_nonce' ),
				'i18nChooseOpts' => __( 'Choose Options', 'commerce-ux-studio' ),
				'i18nAddToCart'  => __( 'Add to Cart', 'commerce-ux-studio' ),
				'i18nOutOfStock' => __( 'Out of Stock', 'commerce-ux-studio' ),
				'i18nAdded'      => __( 'Added to Cart', 'commerce-ux-studio' ),
				'i18nLowStock'   => __( 'Low Stock', 'commerce-ux-studio' ),
				'i18nInStock'    => __( 'In Stock', 'commerce-ux-studio' ),
			)
		);
	}

	/**
	 * Add mobile gallery layout and preset classes to the page body.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public static function add_pdp_body_classes( array $classes ): array {
		if ( function_exists( 'is_product' ) && is_product() ) {
			$product_id = get_the_ID();
			if ( $product_id ) {
				$config        = CUX_Inheritance_Engine::resolve_product_config( (int) $product_id );
				$mobile_layout = $config['mobile_gallery_layout'] ?? 'floating_right';
				$preset        = $config['catalog_card_preset'] ?? 'specialized_spec';
				$classes[]     = 'cux-mobile-gallery-' . sanitize_html_class( $mobile_layout );
				$classes[]     = 'cux-pdp-preset-' . sanitize_html_class( $preset );
			}
		}
		return $classes;
	}

	/**
	 * 1. Render Technical Spec Subtitle, Tagline, and Badges below the Single Product Title.
	 */
	public static function render_pdp_spec_subtitle(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id = $product->get_id();
		$subtitle   = (string) get_post_meta( $product_id, '_cux_spec_subtitle', true );
		$tagline    = (string) get_post_meta( $product_id, '_cux_tagline', true );
		$badge_tl   = (string) get_post_meta( $product_id, '_cux_badge_tl', true );

		if ( empty( $subtitle ) && empty( $tagline ) && empty( $badge_tl ) ) {
			return;
		}
		?>
		<div class="cux-pdp-header-meta">
			<?php if ( ! empty( $badge_tl ) ) : ?>
				<span class="cux-pdp-badge-pill"><?php echo esc_html( $badge_tl ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $subtitle ) ) : ?>
				<div class="cux-pdp-spec-subtitle"><?php echo esc_html( $subtitle ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $tagline ) ) : ?>
				<div class="cux-pdp-tagline"><?php echo esc_html( $tagline ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * 2. Render visual Swatches (Color Circles, Size Boxes, or Rich Spec Radio Cards) alongside the hidden native <select>.
	 *
	 * @param string $html Original WooCommerce <select> HTML.
	 * @param array  $args Dropdown arguments.
	 * @return string
	 */
	public static function render_custom_swatch_controls( string $html, array $args ): string {
		$product   = $args['product'] ?? null;
		$attribute = $args['attribute'] ?? '';
		$options   = $args['options'] ?? array();

		if ( ! is_object( $product ) || empty( $attribute ) || empty( $options ) ) {
			return $html;
		}

		$product_id = $product->get_id();
		$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		$cache      = CUX_Cache_Manager::get_product_cache( $product_id );

		// Determine role and swatch style automatically.
		$attr_clean   = strtolower( (string) $attribute );
		$role         = 'dimensional';
		$style        = 'size_box';
		$primary_attr = strtolower( (string) ( $cache['primary_visual_attr'] ?? 'pa_color' ) );
		$visual_attrs = array_map( 'strtolower', (array) ( $cache['visual_attrs'] ?? array( 'pa_color', 'pa_colour' ) ) );

		if ( $attr_clean === $primary_attr || false !== strpos( $attr_clean, 'color' ) || false !== strpos( $attr_clean, 'colour' ) ) {
			$role  = 'visual_primary';
			$style = 'color_circle';
		} elseif ( in_array( $attr_clean, $visual_attrs, true ) || false !== strpos( $attr_clean, 'groupset' ) || false !== strpos( $attr_clean, 'spec' ) || false !== strpos( $attr_clean, 'wheelset' ) ) {
			$role  = 'visual_secondary';
			$style = 'radio_card';
		}

		// Allow explicit override from Products > Attributes if configured.
		if ( taxonomy_exists( $attribute ) && function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
			$attr_id    = wc_attribute_taxonomy_id_by_name( $attribute );
			$saved_type = $attr_id ? (string) get_option( "cux_swatch_type_{$attr_id}", '' ) : '';
			if ( ! empty( $saved_type ) ) {
				$style = $saved_type;
			}
		}

		if ( 'dropdown' === $style ) {
			return $html;
		}

		// Check if Size Guide link should be rendered on this attribute row.
		$size_guide_block = (string) get_post_meta( $product_id, '_cux_size_guide_id', true );
		if ( empty( $size_guide_block ) ) {
			$size_guide_block = (string) ( $config['size_guide_id'] ?? '' );
		}
		$show_size_guide = ( 'dimensional' === $role && ! empty( $size_guide_block ) );

		// Build map of option statuses (stock, ETA, price delta) from precomputed cache.
		$option_meta = self::build_attribute_option_meta( $product, $attribute, $options, $cache );
		$selected    = isset( $args['selected'] ) ? (string) $args['selected'] : '';
		$oos_mode    = $config['oos_swatch_behavior'] ?? 'clickable_preview';
		$attr_label  = wc_attribute_label( $attribute, $product );

		ob_start();
		?>
		<div class="cux-swatch-control-wrap"
			data-attribute="<?php echo esc_attr( 'attribute_' . sanitize_title( $attribute ) ); ?>"
			data-attr-raw="<?php echo esc_attr( $attribute ); ?>"
			data-role="<?php echo esc_attr( $role ); ?>"
			data-style="<?php echo esc_attr( $style ); ?>"
			data-oos-mode="<?php echo esc_attr( $oos_mode ); ?>">

			<div class="cux-swatch-header-bar">
				<div class="cux-swatch-title-group">
					<span class="cux-attr-name-label"><?php echo esc_html( $attr_label ); ?>:</span>
					<span class="cux-selected-term-label">
						<?php
						if ( ! empty( $selected ) && isset( $option_meta[ $selected ]['name'] ) ) {
							echo esc_html( $option_meta[ $selected ]['name'] );
						} else {
							esc_html_e( 'Select', 'commerce-ux-studio' );
						}
						?>
					</span>
				</div>
				<?php if ( $show_size_guide ) : ?>
					<button type="button" class="cux-size-guide-trigger" data-size-guide="<?php echo esc_attr( $size_guide_block ); ?>">
						<span class="dashicons dashicons-editor-help"></span>
						<?php esc_html_e( 'Size Guide', 'commerce-ux-studio' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div class="cux-swatch-items cux-swatch-style-<?php echo esc_attr( $style ); ?>" role="radiogroup" aria-label="<?php echo esc_attr( $attr_label ); ?>">
				<?php
				foreach ( $options as $option_val ) :
					$meta = $option_meta[ $option_val ] ?? null;
					if ( ! $meta ) {
						continue;
					}

					if ( 'hide_completely' === $oos_mode && ! $meta['has_stock'] ) {
						continue;
					}

					$is_selected  = ( sanitize_title( $selected ) === sanitize_title( $option_val ) );
					$item_classes = array( 'cux-swatch-item' );
					if ( $is_selected ) {
						$item_classes[] = 'is-selected';
					}
					if ( ! $meta['has_stock'] ) {
						$item_classes[] = 'is-out-of-stock';
						if ( 'crossed_disabled' === $oos_mode ) {
							$item_classes[] = 'is-disabled';
						}
					}
					?>
					<button type="button"
						class="<?php echo esc_attr( implode( ' ', $item_classes ) ); ?>"
						data-value="<?php echo esc_attr( $option_val ); ?>"
						data-name="<?php echo esc_attr( $meta['name'] ); ?>"
						data-slug="<?php echo esc_attr( sanitize_title( $option_val ) ); ?>"
						role="radio"
						aria-checked="<?php echo $is_selected ? 'true' : 'false'; ?>"
						<?php echo ( ! $meta['has_stock'] && 'crossed_disabled' === $oos_mode ) ? 'disabled="disabled"' : ''; ?>
						title="<?php echo esc_attr( ! empty( $meta['tooltip'] ) ? $meta['name'] . ' — ' . $meta['tooltip'] : $meta['name'] ); ?>">

						<?php if ( 'color_circle' === $style || 'variation_image' === $style ) : ?>
							<span class="cux-color-circle-inner" style="background: <?php echo esc_attr( $meta['bg_css'] ); ?>;"></span>
						<?php elseif ( 'radio_card' === $style ) : ?>
							<span class="cux-radio-card-top">
								<strong class="cux-radio-card-title"><?php echo esc_html( $meta['name'] ); ?></strong>
								<?php if ( ! empty( $meta['restock_eta'] ) && ! $meta['has_stock'] ) : ?>
									<span class="cux-eta-pill"><?php echo esc_html( $meta['restock_eta'] ); ?></span>
								<?php elseif ( $meta['has_stock'] ) : ?>
									<span class="cux-stock-pill"><?php esc_html_e( 'In Stock', 'commerce-ux-studio' ); ?></span>
								<?php else : ?>
									<span class="cux-oos-pill"><?php esc_html_e( 'Out of Stock', 'commerce-ux-studio' ); ?></span>
								<?php endif; ?>
							</span>
							<?php if ( ! empty( $meta['price_html'] ) ) : ?>
								<span class="cux-radio-card-price"><?php echo wp_kses_post( $meta['price_html'] ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<span class="cux-size-box-text"><?php echo esc_html( $meta['name'] ); ?></span>
							<?php if ( ! empty( $meta['restock_eta'] ) && ! $meta['has_stock'] ) : ?>
								<small class="cux-size-eta"><?php echo esc_html( $meta['restock_eta'] ); ?></small>
							<?php endif; ?>
						<?php endif; ?>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="cux-native-select-hidden" style="display:none !important;">
				<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Build metadata (label, swatch CSS gradient/image, stock status, restock ETA) for each option of an attribute.
	 *
	 * @param WC_Product $product   Variable product.
	 * @param string     $attribute Attribute name.
	 * @param array      $options   Option values.
	 * @param array      $cache     Precomputed product cache.
	 * @return array
	 */
	private static function build_attribute_option_meta( WC_Product $product, string $attribute, array $options, array $cache ): array {
		$result      = array();
		$avail_map   = $cache['availability_matrix'] ?? array();
		$attr_lookup = strtolower( (string) $attribute );
		$no_pa       = 0 === strpos( $attr_lookup, 'pa_' ) ? substr( $attr_lookup, 3 ) : $attr_lookup;

		$attr_style = '';
		if ( taxonomy_exists( $attribute ) && function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
			$attr_id    = wc_attribute_taxonomy_id_by_name( $attribute );
			$attr_style = $attr_id ? (string) get_option( "cux_swatch_type_{$attr_id}", '' ) : '';
		}

		foreach ( $options as $opt_val ) {
			$slug    = sanitize_title( (string) $opt_val );
			$name    = (string) $opt_val;
			$hex_1   = '';
			$hex_2   = '';
			$split   = 'horizontal';
			$img_url = '';
			$tooltip = '';

			if ( taxonomy_exists( $attribute ) ) {
				$term = get_term_by( 'slug', $opt_val, $attribute );
				if ( ! $term ) {
					$term = get_term_by( 'name', $opt_val, $attribute );
				}
				if ( $term && ! is_wp_error( $term ) ) {
					$name    = $term->name;
					$hex_1   = (string) get_term_meta( $term->term_id, '_cux_hex_primary', true );
					$hex_2   = (string) get_term_meta( $term->term_id, '_cux_hex_secondary', true );
					$split   = (string) get_term_meta( $term->term_id, '_cux_split_style', true );
					$img_id  = (int) get_term_meta( $term->term_id, '_cux_swatch_image_id', true );
					$img_url = $img_id > 0 ? (string) wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
					$tooltip = (string) get_term_meta( $term->term_id, '_cux_term_tooltip', true );
				}
			}

			// Check stock, ETA, and variation thumbnail across availability matrix.
			$has_stock     = false;
			$restock_eta   = '';
			$price_html    = '';
			$var_thumb_url = '';

			if ( ! empty( $avail_map ) ) {
				foreach ( $avail_map as $var_data ) {
					$var_attrs = $var_data['attributes'] ?? array();
					foreach ( $var_attrs as $k => $v ) {
						$k_low = strtolower( (string) $k );
						if ( ( $k_low === $attr_lookup || $k_low === $no_pa || $k_low === 'pa_' . $no_pa ) && ( '' === $v || sanitize_title( (string) $v ) === $slug ) ) {
							if ( ! empty( $var_data['is_in_stock'] ) ) {
								$has_stock = true;
							}
							if ( empty( $restock_eta ) && ! empty( $var_data['restock_eta'] ) ) {
								$restock_eta = (string) $var_data['restock_eta'];
							}
							if ( empty( $price_html ) && ! empty( $var_data['price_html'] ) ) {
								$price_html = (string) $var_data['price_html'];
							}
							if ( empty( $var_thumb_url ) && ! empty( $var_data['main_img_id'] ) ) {
								$var_thumb_url = (string) wp_get_attachment_image_url( (int) $var_data['main_img_id'], 'woocommerce_gallery_thumbnail' );
							}
						}
					}
				}
			} else {
				$has_stock = true;
			}

			// If attribute is set to "Auto Variation Thumbnail Swatch", use the variation's own photo.
			if ( 'variation_image' === $attr_style && empty( $img_url ) && ! empty( $var_thumb_url ) ) {
				$img_url = $var_thumb_url;
			}

			// Fallback color lookup from precomputed in_stock_colors or intelligent multi-color slug guesser.
			if ( empty( $hex_1 ) && ! empty( $cache['in_stock_colors'] ) ) {
				foreach ( $cache['in_stock_colors'] as $c_row ) {
					if ( isset( $c_row['slug'] ) && $c_row['slug'] === $slug ) {
						$hex_1 = $c_row['hex_1'] ?? '';
						$hex_2 = $c_row['hex_2'] ?? '';
						$split = $c_row['split'] ?? 'horizontal';
						break;
					}
				}
			}
			if ( empty( $hex_1 ) ) {
				$guessed = self::fallback_colors_from_slug( $slug );
				$hex_1   = $guessed['hex_1'];
				if ( empty( $hex_2 ) && ! empty( $guessed['hex_2'] ) ) {
					$hex_2 = $guessed['hex_2'];
				}
				if ( ! empty( $guessed['split'] ) ) {
					$split = $guessed['split'];
				}
			}

			$bg_css = class_exists( 'CUX_Attribute_Fields' )
				? CUX_Attribute_Fields::compile_swatch_background_css( $hex_1, $hex_2, $split, $img_url )
				: $hex_1;

			$result[ $opt_val ] = array(
				'name'        => $name,
				'slug'        => $slug,
				'bg_css'      => $bg_css,
				'tooltip'     => $tooltip,
				'has_stock'   => $has_stock,
				'restock_eta' => $restock_eta,
				'price_html'  => $price_html,
			);
		}

		return $result;
	}

	/**
	 * 3. Inject Deduplicated Visual Gallery & Availability JSON payload before Variations Form.
	 */
	public static function render_pdp_json_payload(): void {
		global $product;
		if ( ! is_object( $product ) || ! $product->is_type( 'variable' ) ) {
			return;
		}

		$product_id = $product->get_id();
		$cache      = CUX_Cache_Manager::get_product_cache( $product_id );

		// If product has not been synced yet, run a one-time sync now so cache is ready.
		if ( empty( $cache ) && class_exists( 'CUX_Stock_Calculator' ) ) {
			CUX_Stock_Calculator::sync_parent_product( $product_id, 'frontend_init' );
			$cache = CUX_Cache_Manager::get_product_cache( $product_id );
		}

		// Build pre-rendered Flatsome slide HTML per unique visual hash (deduplicated across sizes!).
		$slides_by_hash = array();
		if ( ! empty( $cache['visual_galleries'] ) && is_array( $cache['visual_galleries'] ) ) {
			foreach ( $cache['visual_galleries'] as $hash => $img_ids ) {
				$slides = array();
				foreach ( (array) $img_ids as $att_id ) {
					$att_id = (int) $att_id;
					if ( $att_id <= 0 ) {
						continue;
					}
					$full_src   = wp_get_attachment_image_src( $att_id, 'full' );
					$single_img = wp_get_attachment_image(
						$att_id,
						'woocommerce_single',
						false,
						array(
							'class' => 'wp-post-image skip-lazy',
						)
					);
					$thumb_img  = wp_get_attachment_image(
						$att_id,
						'woocommerce_gallery_thumbnail',
						false,
						array(
							'class' => 'attachment-woocommerce_thumbnail',
						)
					);

					if ( $single_img && $full_src ) {
						$slides[] = array(
							'id'       => $att_id,
							'full_url' => $full_src[0],
							'full_w'   => $full_src[1] ?? 1200,
							'full_h'   => $full_src[2] ?? 1200,
							'main_img' => $single_img,
							'thumb'    => $thumb_img,
						);
					}
				}
				if ( ! empty( $slides ) ) {
					$slides_by_hash[ $hash ] = $slides;
				}
			}
		}

		$frontend_payload = array(
			'product_id'          => $product_id,
			'winner_var_id'       => $cache['winner_var_id'] ?? 0,
			'winner_visual_hash'  => $cache['winner_visual_hash'] ?? '',
			'primary_visual_attr' => $cache['primary_visual_attr'] ?? 'pa_color',
			'visual_attrs'        => $cache['visual_attrs'] ?? array( 'pa_color', 'pa_colour' ),
			'dimensional_attrs'   => $cache['dimensional_attrs'] ?? array( 'pa_size' ),
			'slides_by_hash'      => $slides_by_hash,
			'availability_matrix' => $cache['availability_matrix'] ?? array(),
		);
		?>
		<script type="application/json" id="cux-pdp-json-data">
			<?php echo wp_json_encode( $frontend_payload ); ?>
		</script>
		<?php
	}

	/**
	 * 4. Render Universal WooCommerce Delivery Method Cards (Synced with WC Shipping Zones, Methods & Classes).
	 */
	public static function render_delivery_and_low_stock_block(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id = $product->get_id();
		$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );

		if ( ( $config['enable_delivery_block'] ?? 'yes' ) !== 'yes' ) {
			return;
		}

		$cards = self::get_product_shipping_cards( $product, $config );
		if ( empty( $cards ) ) {
			return;
		}

		$heading        = ! empty( $config['delivery_heading_label'] ) ? $config['delivery_heading_label'] : __( 'Delivery Method', 'commerce-ux-studio' );
		$active_rate_id = '';
		foreach ( $cards as $c ) {
			if ( ! empty( $c['is_active'] ) ) {
				$active_rate_id = $c['rate_id'];
				break;
			}
		}
		if ( empty( $active_rate_id ) && ! empty( $cards[0]['rate_id'] ) ) {
			$active_rate_id          = $cards[0]['rate_id'];
			$cards[0]['is_active'] = true;
		}
		?>
		<div class="cux-fulfilment-wrapper" id="cux-fulfilment-block">
			<div class="cux-fulfilment-header">
				<strong><?php echo esc_html( $heading ); ?></strong>
			</div>

			<input type="hidden" name="cux_chosen_rate_id" id="cux-chosen-rate-id" value="<?php echo esc_attr( $active_rate_id ); ?>" />

			<div class="cux-delivery-cards-grid">
				<?php foreach ( $cards as $idx => $card ) : ?>
					<label class="cux-delivery-card <?php echo ! empty( $card['is_active'] ) ? 'is-active' : ''; ?>"
						data-method="<?php echo esc_attr( $card['type'] ); ?>"
						data-rate-id="<?php echo esc_attr( $card['rate_id'] ); ?>">
						<input type="radio"
							name="cux_delivery_method"
							value="<?php echo esc_attr( $card['rate_id'] ); ?>"
							data-rate-id="<?php echo esc_attr( $card['rate_id'] ); ?>"
							<?php checked( ! empty( $card['is_active'] ), true ); ?> />

						<div class="cux-delivery-card-top">
							<span class="cux-delivery-title"><?php echo esc_html( $card['title'] ); ?></span>
							<?php if ( ! empty( $card['lead_time'] ) ) : ?>
								<span class="cux-delivery-lead"><?php echo esc_html( $card['lead_time'] ); ?></span>
							<?php endif; ?>
						</div>

						<div class="cux-delivery-card-sub"
							<?php echo 'pickup' === $card['type'] ? 'id="cux-pickup-status-line" data-default-subtitle="' . esc_attr( $card['subtitle_plain'] ) . '"' : ''; ?>>
							<span class="cux-status-dot is-available"></span>
							<span class="<?php echo 'pickup' === $card['type'] ? 'cux-pickup-mode-text' : 'cux-home-mode-text'; ?>">
								<?php echo wp_kses_post( $card['subtitle_html'] ); ?>
							</span>
						</div>
					</label>
				<?php endforeach; ?>
			</div>

			<!-- Dynamic Low Stock / Out of Stock Status Banner -->
			<div class="cux-stock-alert-banner" id="cux-stock-alert-banner" style="display:none;">
				<span class="cux-stock-alert-dot"></span>
				<strong class="cux-stock-alert-text"><?php esc_html_e( 'Low Stock', 'commerce-ux-studio' ); ?></strong>
			</div>
		</div>
		<?php
	}

	/**
	 * Dynamically resolve all active WooCommerce Shipping Methods for the customer's Shipping Zone
	 * and calculate the exact cost for this Product's WooCommerce Shipping Class.
	 *
	 * @param WC_Product $product Current product.
	 * @param array      $config  Resolved Studio settings.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_product_shipping_cards( WC_Product $product, array $config ): array {
		$sync_mode       = $config['wc_shipping_sync_mode'] ?? 'auto_wc';
		$method_meta_map = isset( $config['shipping_method_meta'] ) && is_array( $config['shipping_method_meta'] ) ? $config['shipping_method_meta'] : array();

		// Session chosen shipping method.
		$chosen_rate_id = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$chosen = WC()->session->get( 'chosen_shipping_methods' );
			if ( is_array( $chosen ) && ! empty( $chosen[0] ) ) {
				$chosen_rate_id = (string) $chosen[0];
			}
		}

		$cards = array();

		// 1. Auto-Discover from WooCommerce Shipping Zones & Shipping Classes.
		if ( 'custom_text' !== $sync_mode && class_exists( 'WC_Shipping_Zones' ) ) {
			$matched_methods = self::find_matching_zone_shipping_methods();

			foreach ( $matched_methods as $rate_id => $method ) {
				$custom_cfg = isset( $method_meta_map[ $rate_id ] ) && is_array( $method_meta_map[ $rate_id ] ) ? $method_meta_map[ $rate_id ] : array();
				if ( isset( $custom_cfg['enabled'] ) && 'no' === $custom_cfg['enabled'] ) {
					continue;
				}

				$is_pickup = ( 'local_pickup' === $method->id || false !== stripos( $method->id, 'pickup' ) );
				$type      = $is_pickup ? 'pickup' : 'home';

				$wc_title = method_exists( $method, 'get_title' ) ? (string) $method->get_title() : '';
				if ( empty( $wc_title ) && method_exists( $method, 'get_method_title' ) ) {
					$wc_title = (string) $method->get_method_title();
				}

				$title     = ! empty( $custom_cfg['title'] ) ? $custom_cfg['title'] : $wc_title;
				$lead_time = ! empty( $custom_cfg['lead_time'] )
					? $custom_cfg['lead_time']
					: ( $is_pickup ? ( $config['default_pickup_lead'] ?? '6-10 days' ) : ( $config['default_home_lead'] ?? '4-7 days' ) );

				// Calculate exact shipping cost for this product (including WooCommerce Shipping Classes!).
				$cost_amount = self::calculate_method_cost_for_product( $method, $product );
				$cost_html   = $cost_amount > 0 ? wc_price( $cost_amount ) : __( 'Free', 'commerce-ux-studio' );

				if ( ! empty( $custom_cfg['subtitle'] ) ) {
					$subtitle_plain = (string) $custom_cfg['subtitle'];
					$subtitle_html  = esc_html( $subtitle_plain ) . ( $cost_amount > 0 ? ' • <strong>' . $cost_html . '</strong>' : '' );
				} elseif ( $is_pickup ) {
					$default_pickup_sub = ! empty( $config['pickup_card_subtitle'] ) ? $config['pickup_card_subtitle'] : __( 'Available for store pickup', 'commerce-ux-studio' );
					$subtitle_plain     = $default_pickup_sub . ( $cost_amount > 0 ? ' • ' . wp_strip_all_tags( $cost_html ) : '' );
					$subtitle_html      = esc_html( $default_pickup_sub ) . ( $cost_amount > 0 ? ' • <strong>' . $cost_html . '</strong>' : '' );
				} else {
					$subtitle_plain = sprintf( __( 'Available • Shipping: %s', 'commerce-ux-studio' ), wp_strip_all_tags( $cost_html ) );
					$subtitle_html  = sprintf(
						/* translators: %s: Formatted WooCommerce shipping cost */
						esc_html__( 'Available • Shipping: %s', 'commerce-ux-studio' ),
						'<strong>' . $cost_html . '</strong>'
					);
				}

				$cards[] = array(
					'rate_id'        => $rate_id,
					'type'           => $type,
					'title'          => $title,
					'lead_time'      => $lead_time,
					'subtitle_plain' => $subtitle_plain,
					'subtitle_html'  => $subtitle_html,
					'is_active'      => ( ! empty( $chosen_rate_id ) && $chosen_rate_id === $rate_id ),
				);
			}
		}

		// 2. Fallback or Manual Custom Wording Mode if no WooCommerce Zone methods were found.
		if ( empty( $cards ) ) {
			$home_title      = ! empty( $config['home_card_title'] ) ? $config['home_card_title'] : __( 'Standard Delivery', 'commerce-ux-studio' );
			$home_lead       = $config['default_home_lead'] ?? '4-7 days';
			$home_cost_raw   = $config['default_home_cost'] ?? '';
			$home_cost_html  = is_numeric( $home_cost_raw ) ? wc_price( (float) $home_cost_raw ) : ( ! empty( $home_cost_raw ) ? esc_html( $home_cost_raw ) : __( 'Calculated at checkout', 'commerce-ux-studio' ) );
			$pickup_title    = ! empty( $config['pickup_card_title'] ) ? $config['pickup_card_title'] : __( 'In-Store Pickup', 'commerce-ux-studio' );
			$pickup_lead     = $config['default_pickup_lead'] ?? '6-10 days';
			$pickup_subtitle = ! empty( $config['pickup_card_subtitle'] ) ? $config['pickup_card_subtitle'] : __( 'Available for branch pickup', 'commerce-ux-studio' );

			$cards[] = array(
				'rate_id'        => 'flat_rate',
				'type'           => 'home',
				'title'          => $home_title,
				'lead_time'      => $home_lead,
				'subtitle_plain' => wp_strip_all_tags( $home_cost_html ),
				'subtitle_html'  => sprintf( esc_html__( 'Available • Shipping: %s', 'commerce-ux-studio' ), '<strong>' . $home_cost_html . '</strong>' ),
				'is_active'      => ( false === strpos( $chosen_rate_id, 'local_pickup' ) ),
			);

			$cards[] = array(
				'rate_id'        => 'local_pickup',
				'type'           => 'pickup',
				'title'          => $pickup_title,
				'lead_time'      => $pickup_lead,
				'subtitle_plain' => $pickup_subtitle,
				'subtitle_html'  => esc_html( $pickup_subtitle ),
				'is_active'      => ( false !== strpos( $chosen_rate_id, 'local_pickup' ) ),
			);
		}

		return $cards;
	}

	/**
	 * Match the customer's active WooCommerce Shipping Zone (or fallback to store base country / first configured zone)
	 * and return all enabled shipping method instances.
	 *
	 * @return array<string, object> Map of rate_id => WC_Shipping_Method instance.
	 */
	private static function find_matching_zone_shipping_methods(): array {
		$methods_map = array();

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $methods_map;
		}

		// 1. Try matching the customer's current shipping destination (or store base location).
		$base_loc = function_exists( 'wc_get_base_location' ) ? wc_get_base_location() : array( 'country' => '', 'state' => '' );
		$country  = $base_loc['country'] ?? '';
		$state    = $base_loc['state'] ?? '';
		$postcode = '';

		if ( function_exists( 'WC' ) && isset( WC()->customer ) && is_object( WC()->customer ) ) {
			$cust_country = WC()->customer->get_shipping_country();
			if ( ! empty( $cust_country ) ) {
				$country  = $cust_country;
				$state    = WC()->customer->get_shipping_state();
				$postcode = WC()->customer->get_shipping_postcode();
			}
		}

		$package = array(
			'destination' => array(
				'country'  => $country,
				'state'    => $state,
				'postcode' => $postcode,
			),
		);

		$matched_zone = WC_Shipping_Zones::get_zone_matching_package( $package );
		if ( is_object( $matched_zone ) && method_exists( $matched_zone, 'get_shipping_methods' ) ) {
			$zone_methods = $matched_zone->get_shipping_methods( true );
			foreach ( $zone_methods as $instance_id => $method ) {
				if ( is_object( $method ) ) {
					$methods_map[ $method->id . ':' . $instance_id ] = $method;
				}
			}
		}

		// 2. If the customer's initial IP/address didn't match a zone with methods yet, inspect configured zones in order.
		if ( empty( $methods_map ) ) {
			$all_zones = WC_Shipping_Zones::get_zones();
			foreach ( $all_zones as $z_data ) {
				if ( empty( $z_data['zone_id'] ) ) {
					continue;
				}
				$zone         = new WC_Shipping_Zone( (int) $z_data['zone_id'] );
				$zone_methods = $zone->get_shipping_methods( true );
				if ( ! empty( $zone_methods ) ) {
					foreach ( $zone_methods as $instance_id => $method ) {
						if ( is_object( $method ) ) {
							$methods_map[ $method->id . ':' . $instance_id ] = $method;
						}
					}
					break;
				}
			}
		}

		return $methods_map;
	}

	/**
	 * Calculate the exact WooCommerce shipping cost for a specific Product, including its WooCommerce Shipping Class!
	 *
	 * Evaluates:
	 * 1. Product's own Shipping Class ID (`$product->get_shipping_class_id()`), or first variation's Shipping Class ID.
	 * 2. Flat Rate's `class_cost_{term_id}` setting, `no_class_cost` setting, and base `cost` setting.
	 *
	 * @param object     $method  WC_Shipping_Method instance.
	 * @param WC_Product $product Product object.
	 * @return float Calculated shipping cost.
	 */
	public static function calculate_method_cost_for_product( object $method, WC_Product $product ): float {
		if ( 'free_shipping' === ( $method->id ?? '' ) ) {
			return 0.0;
		}

		$total_cost = 0.0;

		// 1. Base Method Cost.
		$base_cost_raw = method_exists( $method, 'get_option' ) ? (string) $method->get_option( 'cost', '' ) : '';
		if ( '' !== $base_cost_raw ) {
			$total_cost += self::parse_wc_cost_formula( $base_cost_raw, (float) $product->get_price() );
		}

		// 2. Shipping Class Cost (if this is a Flat Rate method with shipping classes configured).
		$shipping_class_id = (int) $product->get_shipping_class_id();

		// If parent variable product has no shipping class set directly, check its first child variation.
		if ( $shipping_class_id <= 0 && $product->is_type( 'variable' ) ) {
			$children = $product->get_children();
			if ( ! empty( $children[0] ) ) {
				$first_var = wc_get_product( $children[0] );
				if ( $first_var ) {
					$shipping_class_id = (int) $first_var->get_shipping_class_id();
				}
			}
		}

		if ( method_exists( $method, 'get_option' ) ) {
			if ( $shipping_class_id > 0 ) {
				$class_cost_raw = (string) $method->get_option( 'class_cost_' . $shipping_class_id, '' );
				if ( '' === $class_cost_raw ) {
					$term = get_term( $shipping_class_id, 'product_shipping_class' );
					if ( $term && ! is_wp_error( $term ) ) {
						$class_cost_raw = (string) $method->get_option( 'class_cost_' . $term->slug, '' );
					}
				}
				if ( '' !== $class_cost_raw ) {
					$total_cost += self::parse_wc_cost_formula( $class_cost_raw, (float) $product->get_price() );
				}
			} else {
				$no_class_raw = (string) $method->get_option( 'no_class_cost', '' );
				if ( '' !== $no_class_raw ) {
					$total_cost += self::parse_wc_cost_formula( $no_class_raw, (float) $product->get_price() );
				}
			}
		}

		return max( 0.0, $total_cost );
	}

	/**
	 * Safely evaluate a WooCommerce Flat Rate cost string (handles numbers, `[qty]`, and `[cost]`).
	 *
	 * @param string $formula       Raw cost string from WooCommerce Flat Rate settings.
	 * @param float  $product_price Product price for `[cost]` placeholders.
	 * @return float
	 */
	private static function parse_wc_cost_formula( string $formula, float $product_price ): float {
		$clean = trim( $formula );
		if ( '' === $clean ) {
			return 0.0;
		}
		if ( is_numeric( $clean ) ) {
			return (float) $clean;
		}

		// Replace [qty] with 1 and [cost] with product price, then extract leading numeric value.
		$replaced = str_ireplace( array( '[qty]', '[cost]' ), array( '1', (string) $product_price ), $clean );
		if ( is_numeric( $replaced ) ) {
			return (float) $replaced;
		}
		if ( preg_match( '/([0-9]+(?:\.[0-9]+)?)/', $replaced, $matches ) ) {
			return (float) $matches[1];
		}

		return 0.0;
	}

	/**
	 * 5A. When a customer adds a product to the cart, save their selected Delivery Method card into the WooCommerce Shipping Session.
	 */
	public static function sync_chosen_delivery_method_to_wc_session(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$rate_id = isset( $_REQUEST['cux_chosen_rate_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cux_chosen_rate_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $rate_id ) ) {
			WC()->session->set( 'chosen_shipping_methods', array( $rate_id ) );
		}
	}

	/**
	 * 5B. AJAX Handler: Immediately update WooCommerce `chosen_shipping_methods` session when a Delivery Card is clicked.
	 */
	public static function ajax_set_shipping_method(): void {
		check_ajax_referer( 'cux_frontend_nonce', 'nonce' );

		$rate_id = isset( $_POST['rate_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rate_id'] ) ) : '';
		if ( ! empty( $rate_id ) && function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'chosen_shipping_methods', array( $rate_id ) );
			wp_send_json_success( array( 'rate_id' => $rate_id ) );
		}
		wp_send_json_error();
	}

	/**
	 * 6. Render the In-Page "Complete Your Ride" Cross-Sell Configurator (Powered by Native WooCommerce Cross-Sells).
	 */
	public static function render_complete_your_ride_block(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id = $product->get_id();
		$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );

		if ( ( $config['enable_complete_ride'] ?? 'yes' ) !== 'yes' ) {
			return;
		}

		// Pull from Native WooCommerce Cross-Sells first, with fallback to _cux_cross_sell_ids.
		$cross_sell_ids = $product->get_cross_sell_ids();
		if ( empty( $cross_sell_ids ) ) {
			$custom_cs = get_post_meta( $product_id, '_cux_cross_sell_ids', true );
			if ( is_array( $custom_cs ) ) {
				$cross_sell_ids = $custom_cs;
			}
		}

		if ( empty( $cross_sell_ids ) ) {
			return;
		}

		$items = array();
		foreach ( $cross_sell_ids as $cs_id ) {
			$cs_prod = wc_get_product( $cs_id );
			if ( $cs_prod && 'publish' === $cs_prod->get_status() && $cs_prod->is_in_stock() ) {
				$items[] = $cs_prod;
			}
		}

		if ( empty( $items ) ) {
			return;
		}
		?>
		<div class="cux-complete-ride-box" id="cux-complete-your-ride">
			<div class="cux-cr-header">
				<h4><?php esc_html_e( 'Complete Your Ride', 'commerce-ux-studio' ); ?></h4>
				<span class="cux-cr-toggle-icon">&mdash;</span>
			</div>

			<div class="cux-cr-body">
				<div class="cux-cr-list">
					<?php
					foreach ( $items as $idx => $cs_prod ) :
						$cs_id     = $cs_prod->get_id();
						$cs_cache  = CUX_Cache_Manager::get_product_cache( $cs_id );
						$cs_colors = $cs_cache['in_stock_colors'] ?? array();
						$thumb_url = wp_get_attachment_image_url( $cs_prod->get_image_id(), 'woocommerce_thumbnail' );
						$sizes     = self::get_product_quick_options( $cs_prod );
						?>
						<div class="cux-cr-item"
							data-index="<?php echo esc_attr( (string) $idx ); ?>"
							data-product-id="<?php echo esc_attr( (string) $cs_id ); ?>"
							data-is-variable="<?php echo $cs_prod->is_type( 'variable' ) ? '1' : '0'; ?>">

							<div class="cux-cr-summary-row">
								<div class="cux-cr-thumb">
									<?php if ( $thumb_url ) : ?>
										<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $cs_prod->get_name() ); ?>" />
									<?php endif; ?>
								</div>

								<div class="cux-cr-info">
									<?php if ( ! empty( $cs_colors ) ) : ?>
										<div class="cux-cr-mini-swatches">
											<?php foreach ( array_slice( $cs_colors, 0, 5 ) as $c_dot ) : ?>
												<span class="cux-cr-dot"
													style="background: <?php echo esc_attr( $c_dot['hex_1'] ); ?>;"
													data-img="<?php echo esc_url( $c_dot['card_img_url'] ); ?>"
													data-color-slug="<?php echo esc_attr( $c_dot['slug'] ); ?>"
													title="<?php echo esc_attr( $c_dot['name'] ); ?>"></span>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>

									<a href="<?php echo esc_url( get_permalink( $cs_id ) ); ?>" class="cux-cr-title">
										<?php echo esc_html( $cs_prod->get_name() ); ?>
									</a>
									<div class="cux-cr-price"><?php echo wp_kses_post( $cs_prod->get_price_html() ); ?></div>
								</div>

								<div class="cux-cr-actions">
									<?php if ( $cs_prod->is_type( 'variable' ) && ! empty( $sizes ) ) : ?>
										<button type="button" class="cux-cr-choose-btn">
											<?php esc_html_e( 'Choose Options', 'commerce-ux-studio' ); ?>
										</button>
									<?php else : ?>
										<button type="button" class="cux-cr-direct-add-btn" data-product-id="<?php echo esc_attr( (string) $cs_id ); ?>">
											<?php esc_html_e( 'Add to Cart', 'commerce-ux-studio' ); ?>
										</button>
									<?php endif; ?>
								</div>
							</div>

							<?php if ( $cs_prod->is_type( 'variable' ) && ! empty( $sizes ) ) : ?>
								<div class="cux-cr-drawer" style="display:none;">
									<div class="cux-cr-drawer-main">
										<div class="cux-cr-drawer-img">
											<?php if ( $thumb_url ) : ?>
												<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $cs_prod->get_name() ); ?>" />
											<?php endif; ?>
										</div>
										<div class="cux-cr-drawer-controls">
											<strong><?php echo esc_html( $cs_prod->get_name() ); ?></strong>
											<div class="cux-cr-price"><?php echo wp_kses_post( $cs_prod->get_price_html() ); ?></div>

											<label class="cux-cr-size-label"><?php esc_html_e( 'Select Option:', 'commerce-ux-studio' ); ?></label>
											<div class="cux-cr-size-pills">
												<?php foreach ( $sizes as $sz ) : ?>
													<button type="button"
														class="cux-cr-var-pill"
														data-variation-id="<?php echo esc_attr( (string) $sz['var_id'] ); ?>"
														data-img="<?php echo esc_url( $sz['img_url'] ); ?>">
														<?php echo esc_html( $sz['label'] ); ?>
													</button>
												<?php endforeach; ?>
											</div>

											<button type="button" class="cux-cr-confirm-add-btn" disabled="disabled" data-product-id="<?php echo esc_attr( (string) $cs_id ); ?>">
												<?php esc_html_e( 'Add To Cart', 'commerce-ux-studio' ); ?>
											</button>
										</div>
									</div>

									<div class="cux-cr-drawer-footer">
										<button type="button" class="cux-cr-back-btn">
											&larr; <?php esc_html_e( 'Back to List', 'commerce-ux-studio' ); ?>
										</button>
										<div class="cux-cr-nav-arrows">
											<button type="button" class="cux-cr-prev-btn" title="<?php esc_attr_e( 'Previous Item', 'commerce-ux-studio' ); ?>">&lsaquo;</button>
											<button type="button" class="cux-cr-next-btn" title="<?php esc_attr_e( 'Next Item', 'commerce-ux-studio' ); ?>">&rsaquo;</button>
										</div>
									</div>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Build quick variation pills for a Variable Cross-Sell product.
	 *
	 * @param WC_Product $cs_prod Cross-sell variable product.
	 * @return array
	 */
	private static function get_product_quick_options( WC_Product $cs_prod ): array {
		$pills = array();
		foreach ( $cs_prod->get_children() as $var_id ) {
			$var = wc_get_product( $var_id );
			if ( ! $var || ! $var->is_in_stock() ) {
				continue;
			}
			$attrs = $var->get_variation_attributes( false );
			$label = implode( ' / ', array_filter( $attrs ) );
			if ( empty( $label ) ) {
				$label = '#' . $var_id;
			}
			$img_url = wp_get_attachment_image_url( $var->get_image_id(), 'woocommerce_thumbnail' );
			$pills[] = array(
				'var_id'  => $var_id,
				'label'   => strtoupper( $label ),
				'img_url' => $img_url ? $img_url : '',
			);
		}
		return $pills;
	}

	/**
	 * 7. AJAX Quick Add to Cart handler for Complete Your Ride & Catalog Quick-Add Size Pills.
	 */
	public static function ajax_quick_add_to_cart(): void {
		check_ajax_referer( 'cux_frontend_nonce', 'nonce' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$quantity     = isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 1;

		if ( $product_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'commerce-ux-studio' ) ) );
		}

		$added = false;
		if ( $variation_id > 0 ) {
			$variation  = wc_get_product( $variation_id );
			$attributes = $variation ? $variation->get_variation_attributes() : array();
			$added      = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes );
		} else {
			$added = WC()->cart->add_to_cart( $product_id, $quantity );
		}

		if ( $added ) {
			WC_AJAX::get_refreshed_fragments();
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not add item to cart.', 'commerce-ux-studio' ) ) );
		}
	}

	/**
	 * Intelligent multi-color & metallic finish fallback resolver for complex color names.
	 *
	 * @param string $slug Color slug.
	 * @return array{hex_1: string, hex_2: string, split: string}
	 */
	private static function fallback_colors_from_slug( string $slug ): array {
		$map = array(
			'black'    => '#18181b',
			'charcoal' => '#27272a',
			'chrome'   => '#71717a',
			'silver'   => '#cbd5e1',
			'metal'    => '#3f3f46',
			'white'    => '#f8fafc',
			'red'      => '#dc2626',
			'crimson'  => '#991b1b',
			'blue'     => '#2563eb',
			'navy'     => '#1e3a8a',
			'teal'     => '#0d9488',
			'green'    => '#16a34a',
			'olive'    => '#4d7c0f',
			'yellow'   => '#eab308',
			'gold'     => '#ca8a04',
			'orange'   => '#ea580c',
			'grey'     => '#64748b',
			'gray'     => '#64748b',
			'pink'     => '#db2777',
			'purple'   => '#7c3aed',
			'beige'    => '#d6d3d1',
			'sand'     => '#d6d3d1',
			'brown'    => '#78350f',
			'bronze'   => '#92400e',
		);

		$found = array();
		foreach ( $map as $word => $hex ) {
			if ( false !== strpos( $slug, $word ) && ! in_array( $hex, $found, true ) ) {
				$found[] = $hex;
			}
		}

		$is_metallic = ( false !== strpos( $slug, 'metal' ) || false !== strpos( $slug, 'chrome' ) || false !== strpos( $slug, 'foil' ) || false !== strpos( $slug, 'pearl' ) );

		return array(
			'hex_1' => $found[0] ?? '#334155',
			'hex_2' => $found[1] ?? '',
			'split' => $is_metallic ? 'metallic' : 'horizontal',
		);
	}
}
