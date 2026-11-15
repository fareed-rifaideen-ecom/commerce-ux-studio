<?php
/**
 * Single Product Page (PDP) Swatches, Flatsome Flickity Gallery Bridge,
 * Brand Sync, Size Guide Modal, Multi-Layout Delivery Switcher & "Complete Your Ride" (v2.3).
 *
 * File Path: includes/frontend/class-cux-pdp-renderer.php
 *
 * @package Commerce_UX_Studio
 * @author  Fareed M. Rifaideen <https://fareed-rifaideen.netlify.app/>
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

		// 1. Render Synced WooCommerce Brand (Option 1A Eyebrow or Option 1B Breadcrumb Pill).
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_pdp_brand_header' ), 4 );

		// 2. Render Clean 1-Line Spec Subtitle below Single Product Title (Option 2A vs Option 2B).
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_pdp_spec_subtitle' ), 6 );

		// 3. Enhance Variation Attribute Dropdowns with Visual/Image Swatches, Active Contrast & Size Guide Trigger.
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( __CLASS__, 'render_custom_swatch_controls' ), 20, 2 );

		// 4. Reposition WooCommerce's native "Clear" link so it never overlaps the Colour label.
		add_filter( 'woocommerce_reset_variations_link', array( __CLASS__, 'filter_reset_variations_link' ), 20 );

		// 5. Inject Deduplicated Visual Gallery & Availability JSON payload before Variations Form.
		add_action( 'woocommerce_before_variations_form', array( __CLASS__, 'render_pdp_json_payload' ), 10 );

		// 6. Render Configurable Delivery Switcher (6A Rows / 6B Tabs / 6C Cards) & Low Stock Warning above Add to Cart.
		add_action( 'woocommerce_before_single_variation', array( __CLASS__, 'render_delivery_and_low_stock_block' ), 25 );

		// 7. Sync customer's selected Delivery Method directly into WooCommerce Cart & Checkout Shipping Session.
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'sync_chosen_delivery_method_to_wc_session' ), 20 );
		add_action( 'wp_ajax_cux_set_shipping_method', array( __CLASS__, 'ajax_set_shipping_method' ) );
		add_action( 'wp_ajax_nopriv_cux_set_shipping_method', array( __CLASS__, 'ajax_set_shipping_method' ) );

		// 8. Render In-Page "Complete Your Ride" Cross-Sell Configurator below Add to Cart form.
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'render_complete_your_ride_block' ), 15 );

		// 9. Render Smart Size Guide Lightbox Modal (supports [block id="..."] shortcodes, slugs, or IDs) in Footer.
		add_action( 'wp_footer', array( __CLASS__, 'render_size_guide_modal' ), 25 );

		// 10. AJAX Quick Add-to-Cart endpoint (used by Complete Your Ride & Catalog Quick-Add).
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

		$localized_payload = array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'wcAjaxUrl'      => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( '%%endpoint%%' ) : '',
			'nonce'          => wp_create_nonce( 'cux_frontend_nonce' ),
			'i18nChooseOpts' => __( 'Choose Options', 'commerce-ux-studio' ),
			'i18nAddToCart'  => __( 'Add to Cart', 'commerce-ux-studio' ),
			'i18nOutOfStock' => __( 'Out of Stock', 'commerce-ux-studio' ),
			'i18nAdded'      => __( 'Added to Cart', 'commerce-ux-studio' ),
			'i18nLowStock'   => __( 'Low Stock', 'commerce-ux-studio' ),
			'i18nInStock'    => __( 'In Stock', 'commerce-ux-studio' ),
		);

		wp_localize_script( 'cux-frontend-ux', 'cuxFrontendVars', $localized_payload );
	}

	/**
	 * Add mobile gallery layout, sticky desktop gallery, active swatch contrast, and preset classes to the page body.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public static function add_pdp_body_classes( array $classes ): array {
		if ( function_exists( 'is_product' ) && is_product() ) {
			$product_id = (int) get_the_ID();
			if ( $product_id > 0 ) {
				$config         = CUX_Inheritance_Engine::resolve_product_config( $product_id );
				$mobile_layout  = $config['mobile_gallery_layout'] ?? 'floating_right';
				$preset         = $config['catalog_card_preset'] ?? 'specialized_spec';
				$active_style   = $config['swatch_active_style'] ?? 'checkmark_4a';
				$sticky_gallery = $config['sticky_desktop_gallery'] ?? 'yes';

				$classes[] = 'cux-mobile-gallery-' . sanitize_html_class( $mobile_layout );
				$classes[] = 'cux-pdp-preset-' . sanitize_html_class( $preset );
				$classes[] = 'cux-swatch-contrast-' . sanitize_html_class( $active_style );

				if ( 'yes' === $sticky_gallery ) {
					$classes[] = 'cux-sticky-gallery-enabled';
				}
			}
		}
		return $classes;
	}

	/**
	 * 1. Render Synced WooCommerce Brand on Single Product Header (Option 1A Eyebrow or Option 1B Breadcrumb Pill).
	 */
	public static function render_pdp_brand_header(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id = (int) $product->get_id();
		$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		$brand_mode = $config['brand_sync_display'] ?? 'eyebrow_1a';

		if ( 'cards_schema_1c' === $brand_mode ) {
			return;
		}

		$brand = CUX_Inheritance_Engine::resolve_product_brand( $product_id );
		if ( empty( $brand['name'] ) ) {
			return;
		}

		if ( 'breadcrumb_1b' === $brand_mode ) :
			?>
			<div class="cux-pdp-brand-pill-wrap">
				<?php if ( ! empty( $brand['url'] ) ) : ?>
					<a href="<?php echo esc_url( $brand['url'] ); ?>" class="cux-pdp-brand-pill">
						<?php echo esc_html( $brand['name'] ); ?>
					</a>
				<?php else : ?>
					<span class="cux-pdp-brand-pill"><?php echo esc_html( $brand['name'] ); ?></span>
				<?php endif; ?>
			</div>
			<?php
		else :
			?>
			<div class="cux-pdp-brand-eyebrow">
				<?php if ( ! empty( $brand['url'] ) ) : ?>
					<a href="<?php echo esc_url( $brand['url'] ); ?>" class="cux-pdp-brand-link">
						<?php echo esc_html( $brand['name'] ); ?>
					</a>
				<?php else : ?>
					<span class="cux-pdp-brand-link"><?php echo esc_html( $brand['name'] ); ?></span>
				<?php endif; ?>
			</div>
			<?php
		endif;
	}

	/**
	 * 2. Render Clean 1-Line Technical Spec Subtitle below Single Product Title (when Option 2A is selected).
	 *    Prevents Card Badges and Taglines from stacking and cluttering the area between Title and Price.
	 */
	public static function render_pdp_spec_subtitle(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id  = (int) $product->get_id();
		$config      = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		$header_mode = $config['pdp_header_meta_mode'] ?? 'subtitle_only_2a';

		// Option 2B: Show custom card fields strictly on Shop Cards only.
		if ( 'cards_only_2b' === $header_mode ) {
			return;
		}

		$subtitle = (string) get_post_meta( $product_id, '_cux_spec_subtitle', true );
		if ( '' === trim( $subtitle ) ) {
			return;
		}
		?>
		<div class="cux-pdp-header-meta">
			<div class="cux-pdp-spec-subtitle"><?php echo esc_html( $subtitle ); ?></div>
		</div>
		<?php
	}

	/**
	 * 3. Render visual Swatches (Color Circles, Variation Thumbnail Photos, Size Boxes, or Spec Radio Cards)
	 *    with Selected vs. Deselected Contrast (Option 4A Checkmark vs Option 4B Border + Dot).
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

		$product_id = (int) $product->get_id();
		$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		$cache      = CUX_Cache_Manager::get_product_cache( $product_id );

		$attr_clean        = strtolower( (string) $attribute );
		$role              = 'dimensional';
		$style             = 'size_box';
		$primary_attr      = strtolower( (string) ( $cache['primary_visual_attr'] ?? 'pa_color' ) );
		$visual_attrs      = array_map( 'strtolower', (array) ( $cache['visual_attrs'] ?? array( 'pa_color', 'pa_colour' ) ) );
		$visual_swatch_cfg = $config['visual_swatch_mode'] ?? 'hybrid_auto';
		$active_contrast   = $config['swatch_active_style'] ?? 'checkmark_4a';

		if ( $attr_clean === $primary_attr || false !== strpos( $attr_clean, 'color' ) || false !== strpos( $attr_clean, 'colour' ) ) {
			$role  = 'visual_primary';
			$style = ( 'variation_photo' === $visual_swatch_cfg ) ? 'variation_image' : 'color_circle';
		} elseif ( in_array( $attr_clean, $visual_attrs, true ) || false !== strpos( $attr_clean, 'groupset' ) || false !== strpos( $attr_clean, 'spec' ) || false !== strpos( $attr_clean, 'wheelset' ) ) {
			$role  = 'visual_secondary';
			$style = 'radio_card';
		}

		// Respect explicit per-attribute override if set in Products > Attributes, unless global Variation Photo mode is forced.
		if ( 'variation_photo' !== $visual_swatch_cfg && taxonomy_exists( $attribute ) && function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
			$attr_id    = wc_attribute_taxonomy_id_by_name( $attribute );
			$saved_type = $attr_id ? (string) get_option( "cux_swatch_type_{$attr_id}", '' ) : '';
			if ( ! empty( $saved_type ) ) {
				$style = $saved_type;
			}
		}

		if ( 'dropdown' === $style ) {
			return $html;
		}

		$size_guide      = CUX_Inheritance_Engine::resolve_size_guide_block( $product_id );
		$show_size_guide = ( 'dimensional' === $role && ! empty( $size_guide['shortcode'] ) );

		$option_meta = self::build_attribute_option_meta( $product, $attribute, $options, $cache, $visual_swatch_cfg );
		$selected    = isset( $args['selected'] ) ? (string) $args['selected'] : '';
		$oos_mode    = $config['oos_swatch_behavior'] ?? 'clickable_preview';
		$attr_label  = wc_attribute_label( $attribute, $product );

		ob_start();
		?>
		<div class="cux-swatch-control-wrap cux-contrast-<?php echo esc_attr( $active_contrast ); ?>"
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
							esc_html_e( 'Select an option', 'commerce-ux-studio' );
						}
						?>
					</span>
				</div>
				<?php if ( $show_size_guide ) : ?>
					<button type="button" class="cux-size-guide-trigger" data-size-guide="cux-size-guide-modal">
						<span class="dashicons dashicons-editor-help"></span>
						<span><?php esc_html_e( 'Size Guide', 'commerce-ux-studio' ); ?></span>
					</button>
				<?php endif; ?>
			</div>

			<div class="cux-swatch-items cux-swatch-style-<?php echo esc_attr( $style ); ?> <?php echo ! empty( $selected ) ? 'has-selection' : ''; ?>" role="radiogroup" aria-label="<?php echo esc_attr( $attr_label ); ?>">
				<?php
				foreach ( $options as $option_val ) :
					$meta = $option_meta[ $option_val ] ?? null;
					if ( ! $meta ) {
						continue;
					}

					if ( 'hide_completely' === $oos_mode && ! $meta['has_stock'] ) {
						continue;
					}

					$is_selected  = ( '' !== $selected && sanitize_title( $selected ) === sanitize_title( $option_val ) );
					$item_classes = array( 'cux-swatch-item' );
					if ( $is_selected ) {
						$item_classes[] = 'is-selected';
					} else {
						$item_classes[] = 'is-deselected';
					}
					if ( ! empty( $meta['is_image_swatch'] ) ) {
						$item_classes[] = 'is-image-tile';
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
							<span class="cux-swatch-check-badge" aria-hidden="true">&#10003;</span>
							<span class="cux-swatch-bottom-dot" aria-hidden="true"></span>
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
							<span class="cux-swatch-check-badge" aria-hidden="true">&#10003;</span>
						<?php else : ?>
							<span class="cux-size-box-inner">
								<span class="cux-size-check-inline" aria-hidden="true">&#10003;</span>
								<span class="cux-size-box-text"><?php echo esc_html( $meta['name'] ); ?></span>
							</span>
							<?php if ( ! empty( $meta['restock_eta'] ) && ! $meta['has_stock'] ) : ?>
								<small class="cux-size-eta"><?php echo esc_html( $meta['restock_eta'] ); ?></small>
							<?php endif; ?>
							<span class="cux-swatch-bottom-dot" aria-hidden="true"></span>
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
	 * 4. Wrap WooCommerce's native "Clear" link in a clean block row so it never overlaps the Colour label.
	 *
	 * @param string $link_html Original reset link HTML.
	 * @return string
	 */
	public static function filter_reset_variations_link( string $link_html ): string {
		if ( empty( $link_html ) ) {
			return $link_html;
		}
		return '<div class="cux-reset-variations-wrap">' . $link_html . '</div>';
	}

	/**
	 * Build metadata (label, swatch CSS gradient/image, variation photo, stock status, restock ETA) for each option.
	 *
	 * @param WC_Product $product           Variable product.
	 * @param string     $attribute         Attribute name.
	 * @param array      $options           Option values.
	 * @param array      $cache             Precomputed product cache.
	 * @param string     $visual_swatch_cfg Global visual swatch display mode.
	 * @return array
	 */
	private static function build_attribute_option_meta(
		WC_Product $product,
		string $attribute,
		array $options,
		array $cache,
		string $visual_swatch_cfg = 'hybrid_auto'
	): array {
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
			$slug         = sanitize_title( (string) $opt_val );
			$name         = (string) $opt_val;
			$hex_1        = '';
			$hex_2        = '';
			$split        = 'horizontal';
			$term_img_url = '';
			$tooltip      = '';

			if ( taxonomy_exists( $attribute ) ) {
				$term = get_term_by( 'slug', $opt_val, $attribute );
				if ( ! $term ) {
					$term = get_term_by( 'name', $opt_val, $attribute );
				}
				if ( $term && ! is_wp_error( $term ) ) {
					$name         = $term->name;
					$hex_1        = (string) get_term_meta( $term->term_id, '_cux_hex_primary', true );
					$hex_2        = (string) get_term_meta( $term->term_id, '_cux_hex_secondary', true );
					$split        = (string) get_term_meta( $term->term_id, '_cux_split_style', true );
					$img_id       = (int) get_term_meta( $term->term_id, '_cux_swatch_image_id', true );
					$term_img_url = $img_id > 0 ? (string) wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
					$tooltip      = (string) get_term_meta( $term->term_id, '_cux_term_tooltip', true );
				}
			}

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

			// Resolve whether to display an image swatch vs color/gradient swatch based on Dashboard setting.
			$final_img_url   = '';
			$is_image_swatch = false;

			if ( 'variation_photo' === $visual_swatch_cfg || 'variation_image' === $attr_style ) {
				$final_img_url   = ! empty( $var_thumb_url ) ? $var_thumb_url : $term_img_url;
				$is_image_swatch = ! empty( $final_img_url );
			} elseif ( 'hybrid_auto' === $visual_swatch_cfg ) {
				if ( ! empty( $term_img_url ) ) {
					$final_img_url   = $term_img_url;
					$is_image_swatch = true;
				}
			}

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

			$bg_css = self::compile_swatch_background_css( $hex_1, $hex_2, $split, $final_img_url );

			$result[ $opt_val ] = array(
				'name'            => $name,
				'slug'            => $slug,
				'bg_css'          => $bg_css,
				'is_image_swatch' => $is_image_swatch,
				'tooltip'         => $tooltip,
				'has_stock'       => $has_stock,
				'restock_eta'     => $restock_eta,
				'price_html'      => $price_html,
			);
		}

		return $result;
	}

	/**
	 * Self-contained CSS background compiler for solid, 50/50 split, gradient, metallic, carbon, or texture image swatches.
	 *
	 * @param string $hex_1   Primary hex color.
	 * @param string $hex_2   Secondary hex color.
	 * @param string $split   Split or finish style.
	 * @param string $img_url Optional texture or variation image URL.
	 * @return string
	 */
	public static function compile_swatch_background_css( string $hex_1, string $hex_2, string $split, string $img_url = '' ): string {
		if ( ! empty( $img_url ) ) {
			return 'url(' . esc_url_raw( $img_url ) . ') center/cover no-repeat';
		}

		if ( empty( $hex_1 ) ) {
			$hex_1 = '#334155';
		}

		if ( ! empty( $hex_2 ) ) {
			if ( 'angled' === $split ) {
				return "linear-gradient(135deg, {$hex_1} 50%, {$hex_2} 50%)";
			}
			if ( 'vertical' === $split ) {
				return "linear-gradient(90deg, {$hex_1} 50%, {$hex_2} 50%)";
			}
			if ( 'smooth_gradient' === $split ) {
				return "linear-gradient(135deg, {$hex_1} 0%, {$hex_2} 100%)";
			}
			if ( 'metallic' === $split ) {
				return "radial-gradient(circle at 30% 30%, rgba(255,255,255,0.55), transparent 55%), linear-gradient(135deg, {$hex_1} 45%, {$hex_2} 55%)";
			}
			return "linear-gradient(180deg, {$hex_1} 50%, {$hex_2} 50%)";
		}

		if ( 'metallic' === $split ) {
			return "radial-gradient(circle at 30% 28%, rgba(255,255,255,0.6), {$hex_1} 68%)";
		}
		if ( 'carbon_matte' === $split ) {
			return "repeating-linear-gradient(45deg, rgba(255,255,255,0.07) 0px, rgba(255,255,255,0.07) 2px, transparent 2px, transparent 4px), {$hex_1}";
		}

		return $hex_1;
	}

	/**
	 * 5. Inject Deduplicated Visual Gallery & Availability JSON payload before Variations Form.
	 */
	public static function render_pdp_json_payload(): void {
		global $product;
		if ( ! is_object( $product ) || ! $product->is_type( 'variable' ) ) {
			return;
		}

		$product_id = (int) $product->get_id();
		$cache      = CUX_Cache_Manager::get_product_cache( $product_id );

		if ( empty( $cache ) && class_exists( 'CUX_Stock_Calculator' ) ) {
			CUX_Stock_Calculator::sync_parent_product( $product_id, 'frontend_init' );
			$cache = CUX_Cache_Manager::get_product_cache( $product_id );
		}

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
	 * 6. Render Configurable Delivery Switcher (Option 6A Stacked Rows, Option 6B Segmented Tabs, Option 6C 2-Col Cards)
	 *    Synced with WooCommerce Shipping Zones, Methods & Shipping Classes.
	 */
	public static function render_delivery_and_low_stock_block(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id = (int) $product->get_id();
		$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );

		if ( ( $config['enable_delivery_block'] ?? 'yes' ) !== 'yes' ) {
			return;
		}

		$cards = self::get_product_shipping_cards( $product, $config );
		if ( empty( $cards ) ) {
			return;
		}

		$layout_style   = $config['delivery_layout_style'] ?? 'stacked_rows_6a';
		$heading        = ! empty( $config['delivery_heading_label'] ) ? $config['delivery_heading_label'] : __( 'Delivery Method', 'commerce-ux-studio' );
		$active_card    = $cards[0];
		$active_rate_id = '';

		foreach ( $cards as $idx => $c ) {
			if ( ! empty( $c['is_active'] ) ) {
				$active_rate_id = $c['rate_id'];
				$active_card    = $c;
				break;
			}
		}
		if ( empty( $active_rate_id ) && ! empty( $cards[0]['rate_id'] ) ) {
			$active_rate_id        = $cards[0]['rate_id'];
			$cards[0]['is_active'] = true;
			$active_card           = $cards[0];
		}
		?>
		<div class="cux-fulfilment-wrapper cux-delivery-layout-<?php echo esc_attr( $layout_style ); ?>" id="cux-fulfilment-block" data-layout="<?php echo esc_attr( $layout_style ); ?>">
			<div class="cux-fulfilment-header">
				<strong><?php echo esc_html( $heading ); ?></strong>
			</div>

			<input type="hidden" name="cux_chosen_rate_id" id="cux-chosen-rate-id" value="<?php echo esc_attr( $active_rate_id ); ?>" />

			<?php if ( 'tabs_pill_6b' === $layout_style ) : ?>
				<!-- OPTION 6B: COMPACT 2-TAB SEGMENTED SWITCHER PILL + LIVE SUMMARY LINE -->
				<div class="cux-delivery-tabs-pill" role="tablist">
					<?php foreach ( $cards as $card ) : ?>
						<button type="button"
							class="cux-delivery-card cux-delivery-tab-btn <?php echo ! empty( $card['is_active'] ) ? 'is-active' : ''; ?>"
							data-method="<?php echo esc_attr( $card['type'] ); ?>"
							data-rate-id="<?php echo esc_attr( $card['rate_id'] ); ?>"
							data-lead="<?php echo esc_attr( $card['lead_time'] ); ?>"
							data-sub-html="<?php echo esc_attr( $card['subtitle_html'] ); ?>">
							<input type="radio"
								name="cux_delivery_method"
								value="<?php echo esc_attr( $card['rate_id'] ); ?>"
								<?php checked( ! empty( $card['is_active'] ), true ); ?>
								style="display:none;" />
							<span class="cux-tab-btn-title"><?php echo esc_html( $card['title'] ); ?></span>
							<?php if ( ! empty( $card['lead_time'] ) ) : ?>
								<span class="cux-tab-btn-lead"><?php echo esc_html( $card['lead_time'] ); ?></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>
				<div class="cux-delivery-tab-summary" id="cux-delivery-tab-summary">
					<span class="cux-status-dot is-available"></span>
					<span class="cux-tab-summary-text"><?php echo wp_kses_post( $active_card['subtitle_html'] ); ?></span>
				</div>

			<?php elseif ( 'cards_2col_6c' === $layout_style ) : ?>
				<!-- OPTION 6C: REFINED 2-COLUMN CARDS (FULL-WIDTH TITLE ON TOP + LEAD TIME & PRICE ON BOTTOM) -->
				<div class="cux-delivery-cards-grid cux-grid-2col-refined">
					<?php foreach ( $cards as $card ) : ?>
						<label class="cux-delivery-card <?php echo ! empty( $card['is_active'] ) ? 'is-active' : ''; ?>"
							data-method="<?php echo esc_attr( $card['type'] ); ?>"
							data-rate-id="<?php echo esc_attr( $card['rate_id'] ); ?>">
							<input type="radio"
								name="cux_delivery_method"
								value="<?php echo esc_attr( $card['rate_id'] ); ?>"
								data-rate-id="<?php echo esc_attr( $card['rate_id'] ); ?>"
								<?php checked( ! empty( $card['is_active'] ), true ); ?> />

							<div class="cux-card-6c-title-row">
								<span class="cux-radio-dot-ui"></span>
								<span class="cux-delivery-title"><?php echo esc_html( $card['title'] ); ?></span>
							</div>

							<div class="cux-card-6c-meta-row"
								<?php echo 'pickup' === $card['type'] ? 'id="cux-pickup-status-line" data-default-subtitle="' . esc_attr( $card['subtitle_plain'] ) . '"' : ''; ?>>
								<?php if ( ! empty( $card['lead_time'] ) ) : ?>
									<span class="cux-delivery-lead"><?php echo esc_html( $card['lead_time'] ); ?></span>
								<?php endif; ?>
								<span class="cux-card-6c-sub <?php echo 'pickup' === $card['type'] ? 'cux-pickup-mode-text' : 'cux-home-mode-text'; ?>">
									<?php echo wp_kses_post( $card['subtitle_html'] ); ?>
								</span>
							</div>
						</label>
					<?php endforeach; ?>
				</div>

			<?php else : ?>
				<!-- OPTION 6A (DEFAULT): FULL-WIDTH STACKED HORIZONTAL RADIO ROWS (NO WRAPPING) -->
				<div class="cux-delivery-stacked-list">
					<?php foreach ( $cards as $card ) : ?>
						<label class="cux-delivery-card cux-delivery-row-item <?php echo ! empty( $card['is_active'] ) ? 'is-active' : ''; ?>"
							data-method="<?php echo esc_attr( $card['type'] ); ?>"
							data-rate-id="<?php echo esc_attr( $card['rate_id'] ); ?>">
							<input type="radio"
								name="cux_delivery_method"
								value="<?php echo esc_attr( $card['rate_id'] ); ?>"
								data-rate-id="<?php echo esc_attr( $card['rate_id'] ); ?>"
								<?php checked( ! empty( $card['is_active'] ), true ); ?> />

							<div class="cux-row-left">
								<span class="cux-radio-dot-ui"></span>
								<div class="cux-row-titles">
									<span class="cux-delivery-title"><?php echo esc_html( $card['title'] ); ?></span>
									<span class="cux-row-sub <?php echo 'pickup' === $card['type'] ? 'cux-pickup-mode-text' : 'cux-home-mode-text'; ?>"
										<?php echo 'pickup' === $card['type'] ? 'id="cux-pickup-status-line" data-default-subtitle="' . esc_attr( $card['subtitle_plain'] ) . '"' : ''; ?>>
										<span class="cux-status-dot is-available"></span>
										<?php echo wp_kses_post( $card['subtitle_html'] ); ?>
									</span>
								</div>
							</div>

							<?php if ( ! empty( $card['lead_time'] ) ) : ?>
								<div class="cux-row-right">
									<span class="cux-delivery-lead"><?php echo esc_html( $card['lead_time'] ); ?></span>
								</div>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

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

		$chosen_rate_id = '';
		if ( function_exists( 'WC' ) && isset( WC()->session ) && is_object( WC()->session ) ) {
			$chosen = WC()->session->get( 'chosen_shipping_methods' );
			if ( is_array( $chosen ) && ! empty( $chosen[0] ) ) {
				$chosen_rate_id = (string) $chosen[0];
			}
		}

		$cards = array();

		if ( 'custom_text' !== $sync_mode && class_exists( 'WC_Shipping_Zones' ) ) {
			try {
				$matched_methods = self::find_matching_zone_shipping_methods( $product );

				foreach ( $matched_methods as $rate_id => $method ) {
					if ( ! is_object( $method ) || empty( $method->id ) ) {
						continue;
					}
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

					$cost_amount = self::calculate_method_cost_for_product( $method, $product );
					$cost_html   = $cost_amount > 0 ? wc_price( $cost_amount ) : __( 'Free', 'commerce-ux-studio' );

					if ( ! empty( $custom_cfg['subtitle'] ) ) {
						$subtitle_plain = (string) $custom_cfg['subtitle'];
						$subtitle_html  = esc_html( $subtitle_plain ) . ( $cost_amount > 0 ? ' &bull; <strong>' . $cost_html . '</strong>' : '' );
					} elseif ( $is_pickup ) {
						$default_pickup_sub = ! empty( $config['pickup_card_subtitle'] ) ? $config['pickup_card_subtitle'] : __( 'Available for store pickup', 'commerce-ux-studio' );
						$subtitle_plain     = $default_pickup_sub . ( $cost_amount > 0 ? ' • ' . wp_strip_all_tags( $cost_html ) : '' );
						$subtitle_html      = esc_html( $default_pickup_sub ) . ( $cost_amount > 0 ? ' &bull; <strong>' . $cost_html . '</strong>' : '' );
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
			} catch ( \Throwable $e ) {
				$cards = array();
			}
		}

		// Store-agnostic fallback cards if no WooCommerce Zone methods were found.
		if ( empty( $cards ) ) {
			$home_title      = ! empty( $config['home_card_title'] ) ? $config['home_card_title'] : __( 'Standard Delivery', 'commerce-ux-studio' );
			$home_lead       = $config['default_home_lead'] ?? '4-7 days';
			$home_cost_raw   = $config['default_home_cost'] ?? '';
			$home_cost_html  = is_numeric( $home_cost_raw ) ? wc_price( (float) $home_cost_raw ) : ( ! empty( $home_cost_raw ) ? esc_html( $home_cost_raw ) : __( 'Calculated at checkout', 'commerce-ux-studio' ) );
			$pickup_title    = ! empty( $config['pickup_card_title'] ) ? $config['pickup_card_title'] : __( 'In-Store Pickup', 'commerce-ux-studio' );
			$pickup_lead     = $config['default_pickup_lead'] ?? '6-10 days';
			$pickup_subtitle = ! empty( $config['pickup_card_subtitle'] ) ? $config['pickup_card_subtitle'] : __( 'Available for store pickup', 'commerce-ux-studio' );

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
	 * @param WC_Product $product Product object.
	 * @return array<string, object> Map of rate_id => WC_Shipping_Method instance.
	 */
	private static function find_matching_zone_shipping_methods( WC_Product $product ): array {
		$methods_map = array();

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $methods_map;
		}

		try {
			$base_loc = function_exists( 'wc_get_base_location' ) ? wc_get_base_location() : array( 'country' => '', 'state' => '' );
			$country  = $base_loc['country'] ?? '';
			$state    = $base_loc['state'] ?? '';
			$postcode = '';
			$city     = '';

			if ( function_exists( 'WC' ) && isset( WC()->customer ) && is_object( WC()->customer ) ) {
				$cust_country = WC()->customer->get_shipping_country();
				if ( ! empty( $cust_country ) ) {
					$country  = $cust_country;
					$state    = (string) WC()->customer->get_shipping_state();
					$postcode = (string) WC()->customer->get_shipping_postcode();
					$city     = (string) WC()->customer->get_shipping_city();
				}
			}

			$package = array(
				'contents'        => array(),
				'contents_cost'   => (float) $product->get_price(),
				'applied_coupons' => array(),
				'user'            => array( 'ID' => get_current_user_id() ),
				'destination'     => array(
					'country'   => $country,
					'state'     => $state,
					'postcode'  => $postcode,
					'city'      => $city,
					'address'   => '',
					'address_1' => '',
					'address_2' => '',
				),
			);

			$matched_zone = WC_Shipping_Zones::get_zone_matching_package( $package );
			if ( is_object( $matched_zone ) && method_exists( $matched_zone, 'get_shipping_methods' ) ) {
				$zone_methods = $matched_zone->get_shipping_methods( true );
				foreach ( $zone_methods as $instance_id => $method ) {
					if ( is_object( $method ) && ! empty( $method->id ) ) {
						$methods_map[ $method->id . ':' . $instance_id ] = $method;
					}
				}
			}
		} catch ( \Throwable $e ) {
			$methods_map = array();
		}

		if ( empty( $methods_map ) ) {
			try {
				$all_zones = WC_Shipping_Zones::get_zones();
				if ( is_array( $all_zones ) ) {
					foreach ( $all_zones as $z_key => $z_data ) {
						$z_id = isset( $z_data['zone_id'] ) ? (int) $z_data['zone_id'] : (int) $z_key;
						if ( $z_id <= 0 ) {
							continue;
						}
						$zone         = new WC_Shipping_Zone( $z_id );
						$zone_methods = $zone->get_shipping_methods( true );
						if ( ! empty( $zone_methods ) ) {
							foreach ( $zone_methods as $instance_id => $method ) {
								if ( is_object( $method ) && ! empty( $method->id ) ) {
									$methods_map[ $method->id . ':' . $instance_id ] = $method;
								}
							}
							break;
						}
					}
				}
			} catch ( \Throwable $e ) {
				$methods_map = array();
			}
		}

		return $methods_map;
	}

	/**
	 * Calculate the exact WooCommerce shipping cost for a specific Product, including its WooCommerce Shipping Class.
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

		try {
			$base_cost_raw = method_exists( $method, 'get_option' ) ? (string) $method->get_option( 'cost', '' ) : '';
			if ( '' !== $base_cost_raw ) {
				$total_cost += self::parse_wc_cost_formula( $base_cost_raw, (float) $product->get_price() );
			}

			$shipping_class_id = (int) $product->get_shipping_class_id();

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
		} catch ( \Throwable $e ) {
			return max( 0.0, $total_cost );
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
	 * 7A. When a customer adds a product to the cart, save their selected Delivery Method into the WooCommerce Shipping Session.
	 */
	public static function sync_chosen_delivery_method_to_wc_session(): void {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) || ! is_object( WC()->session ) ) {
			return;
		}

		$rate_id = isset( $_REQUEST['cux_chosen_rate_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cux_chosen_rate_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $rate_id ) ) {
			WC()->session->set( 'chosen_shipping_methods', array( $rate_id ) );
		}
	}

	/**
	 * 7B. AJAX Handler: Immediately update WooCommerce `chosen_shipping_methods` session when a Delivery Option is clicked.
	 */
	public static function ajax_set_shipping_method(): void {
		check_ajax_referer( 'cux_frontend_nonce', 'nonce' );

		$rate_id = isset( $_POST['rate_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rate_id'] ) ) : '';
		if ( ! empty( $rate_id ) && function_exists( 'WC' ) && isset( WC()->session ) && is_object( WC()->session ) ) {
			WC()->session->set( 'chosen_shipping_methods', array( $rate_id ) );
			wp_send_json_success( array( 'rate_id' => $rate_id ) );
		}
		wp_send_json_error();
	}

	/**
	 * 8. Render the In-Page "Complete Your Ride" Cross-Sell Configurator (Powered by Native WooCommerce Cross-Sells).
	 */
	public static function render_complete_your_ride_block(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id = (int) $product->get_id();
		$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );

		if ( ( $config['enable_complete_ride'] ?? 'yes' ) !== 'yes' ) {
			return;
		}

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

		$heading = ! empty( $config['cross_sell_heading'] ) ? $config['cross_sell_heading'] : __( 'Complete Your Ride', 'commerce-ux-studio' );
		?>
		<div class="cux-complete-ride-box" id="cux-complete-your-ride">
			<div class="cux-cr-header">
				<h4><?php echo esc_html( $heading ); ?></h4>
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
	 * 9. Render the Centered Size Guide Lightbox Modal in the Footer (Executes Flatsome `[block id="..."]` shortcodes).
	 */
	public static function render_size_guide_modal(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product_id = (int) get_the_ID();
		if ( $product_id <= 0 ) {
			return;
		}

		$size_guide = CUX_Inheritance_Engine::resolve_size_guide_block( $product_id );
		if ( empty( $size_guide['shortcode'] ) ) {
			return;
		}

		$rendered_content = do_shortcode( $size_guide['shortcode'] );
		if ( empty( trim( $rendered_content ) ) ) {
			return;
		}
		?>
		<div id="cux-size-guide-modal" class="cux-modal-overlay" style="display:none;" aria-hidden="true">
			<div class="cux-modal-backdrop"></div>
			<div class="cux-modal-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Size Guide', 'commerce-ux-studio' ); ?>">
				<div class="cux-modal-header">
					<h3><?php esc_html_e( 'Size & Geometry Guide', 'commerce-ux-studio' ); ?></h3>
					<button type="button" class="cux-modal-close" aria-label="<?php esc_attr_e( 'Close Size Guide', 'commerce-ux-studio' ); ?>">&times;</button>
				</div>
				<div class="cux-modal-body">
					<?php echo $rendered_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
	 * 10. AJAX Quick Add to Cart handler for Complete Your Ride & Catalog Quick-Add Size Pills.
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
