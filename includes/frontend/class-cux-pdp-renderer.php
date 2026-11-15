<?php
/**
 * Single Product Swatches, Cross-Attribute Availability Matrix, Strict Variation Gallery & Delivery UI (v2.3.0)
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
	 * Recursion guard for gallery filtering.
	 *
	 * @var bool
	 */
	private static $is_filtering_gallery = false;

	/**
	 * Register Single Product and AJAX hooks.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'body_class', array( __CLASS__, 'add_pdp_body_classes' ) );

		// Brand Eyebrow & Spec Subtitle on Single Product Page.
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_pdp_brand_header' ), 4 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_pdp_spec_subtitle' ), 6 );

		// Strict Single-Variation Gallery Isolation on initial server load (recursion-guarded).
		add_filter( 'woocommerce_product_get_gallery_image_ids', array( __CLASS__, 'filter_pdp_strict_gallery_ids' ), 25, 2 );

		// Swatches, Delivery Switcher, Complete Your Ride & Footer JSON/Modal.
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( __CLASS__, 'render_swatches_html' ), 20, 2 );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_delivery_and_low_stock_block' ), 15 );
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'render_complete_your_ride_block' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'render_pdp_json_and_modals' ), 30 );

		// AJAX handlers.
		add_action( 'wp_ajax_cux_set_shipping_method', array( __CLASS__, 'ajax_set_shipping_method' ) );
		add_action( 'wp_ajax_nopriv_cux_set_shipping_method', array( __CLASS__, 'ajax_set_shipping_method' ) );
		add_action( 'wp_ajax_cux_quick_add_to_cart', array( __CLASS__, 'ajax_quick_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_cux_quick_add_to_cart', array( __CLASS__, 'ajax_quick_add_to_cart' ) );
	}

	/**
	 * Enqueue frontend CSS and JS.
	 */
	public static function enqueue_assets(): void {
		if ( is_admin() || ! function_exists( 'is_woocommerce' ) ) {
			return;
		}

		$ver      = defined( 'CUX_STUDIO_VERSION' ) ? CUX_STUDIO_VERSION : '2.3.0';
		$settings = self::get_settings_safe();

		wp_enqueue_style(
			'cux-frontend-ux',
			CUX_STUDIO_URL . 'assets/css/frontend-ux.css',
			array(),
			$ver
		);

		wp_enqueue_script(
			'cux-frontend-ux',
			CUX_STUDIO_URL . 'assets/js/frontend-ux.js',
			array( 'jquery' ),
			$ver,
			true
		);

		$localized = array(
			'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
			'nonce'               => wp_create_nonce( 'cux_frontend_nonce' ),
			'crossAttrOosMode'    => $settings['cross_attr_oos_mode'] ?? 'smart_switch',
			'pdpGalleryIsolation' => $settings['pdp_gallery_isolation'] ?? 'strict_variation',
			'swatchActiveStyle'   => $settings['swatch_active_style'] ?? 'option_4a',
			'deliveryLayoutStyle' => $settings['delivery_layout_style'] ?? 'option_6a',
			'i18n'                => array(
				'chooseOptions' => __( 'Choose Options', 'commerce-ux-studio' ),
				'addToCart'     => __( 'Add to Cart', 'commerce-ux-studio' ),
				'outOfStock'    => __( 'Out of Stock', 'commerce-ux-studio' ),
				'added'         => __( 'Added to Cart', 'commerce-ux-studio' ),
				'lowStock'      => __( 'Low Stock', 'commerce-ux-studio' ),
				'inStock'       => __( 'In Stock', 'commerce-ux-studio' ),
				'unavailableIn' => __( 'Unavailable in selected option', 'commerce-ux-studio' ),
			),
		);

		wp_localize_script( 'cux-frontend-ux', 'cuxFrontendConfig', $localized );
		wp_localize_script( 'cux-frontend-ux', 'cuxFrontendVars', $localized );
	}

	/**
	 * Add body classes for Sticky Desktop Gallery and Active Swatch Style (Option 4A / 4B).
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public static function add_pdp_body_classes( $classes ) {
		if ( ! is_array( $classes ) ) {
			return $classes;
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			$settings = self::get_settings_safe();
			if ( 'yes' === ( $settings['sticky_desktop_gallery'] ?? 'yes' ) ) {
				$classes[] = 'cux-sticky-gallery-enabled';
			}
			$active_style = $settings['swatch_active_style'] ?? 'option_4a';
			$classes[]    = 'cux-swatch-contrast-' . sanitize_html_class( $active_style );
		}
		return $classes;
	}

	/**
	 * Render Synced WooCommerce Brand above Single Product Title (Option 1A or 1B).
	 */
	public static function render_pdp_brand_header(): void {
		global $product;
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		$settings   = self::get_settings_safe();
		$brand_mode = $settings['brand_sync_display'] ?? 'option_1a';
		if ( 'option_1c' === $brand_mode ) {
			return;
		}

		$brand = ( class_exists( 'CUX_Inheritance_Engine' ) && method_exists( 'CUX_Inheritance_Engine', 'resolve_product_brand' ) )
			? CUX_Inheritance_Engine::resolve_product_brand( $product->get_id() )
			: array( 'name' => '', 'url' => '', 'tagline' => '' );

		$label = ! empty( $brand['name'] ) ? $brand['name'] : ( $brand['tagline'] ?? '' );
		if ( '' === $label ) {
			return;
		}

		if ( 'option_1b' === $brand_mode ) {
			echo '<div class="cux-pdp-brand-pill-row">';
			if ( ! empty( $brand['url'] ) ) {
				echo '<a href="' . esc_url( $brand['url'] ) . '" class="cux-pdp-brand-pill">' . esc_html( $label ) . '</a>';
			} else {
				echo '<span class="cux-pdp-brand-pill">' . esc_html( $label ) . '</span>';
			}
			echo '</div>';
			return;
		}

		// Option 1A: Clickable Brand Eyebrow above Title.
		echo '<div class="cux-pdp-brand-eyebrow">';
		if ( ! empty( $brand['url'] ) ) {
			echo '<a href="' . esc_url( $brand['url'] ) . '">' . esc_html( $label ) . '</a>';
		} else {
			echo '<span>' . esc_html( $label ) . '</span>';
		}
		echo '</div>';
	}

	/**
	 * Render clean 1-line Technical Spec Subtitle below Title when Option 2A is active.
	 */
	public static function render_pdp_spec_subtitle(): void {
		global $product;
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		$settings  = self::get_settings_safe();
		$meta_mode = $settings['pdp_header_meta_mode'] ?? 'option_2a';
		if ( 'option_2b' === $meta_mode ) {
			return;
		}

		$subtitle = trim( (string) get_post_meta( $product->get_id(), '_cux_spec_subtitle', true ) );
		if ( '' !== $subtitle ) {
			echo '<div class="cux-pdp-spec-subtitle">' . esc_html( $subtitle ) . '</div>';
		}
	}

	/**
	 * Filter parent gallery IDs on Single Product Page when Strict Selected-Variation Isolation is active.
	 *
	 * @param array      $gallery_ids Gallery attachment IDs.
	 * @param WC_Product $product     Product object.
	 * @return array
	 */
	public static function filter_pdp_strict_gallery_ids( $gallery_ids, $product ) {
		if ( self::$is_filtering_gallery || is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return is_array( $gallery_ids ) ? $gallery_ids : array();
		}

		if ( ! ( $product instanceof WC_Product ) || ! $product->is_type( 'variable' ) ) {
			return is_array( $gallery_ids ) ? $gallery_ids : array();
		}

		$queried_id = get_queried_object_id();
		if ( $queried_id > 0 && (int) $product->get_id() !== (int) $queried_id ) {
			return is_array( $gallery_ids ) ? $gallery_ids : array();
		}

		$config = ( class_exists( 'CUX_Inheritance_Engine' ) && method_exists( 'CUX_Inheritance_Engine', 'resolve_product_config' ) )
			? CUX_Inheritance_Engine::resolve_product_config( $product->get_id() )
			: self::get_settings_safe();

		if ( 'strict_variation' !== ( $config['pdp_gallery_isolation'] ?? 'strict_variation' ) ) {
			return is_array( $gallery_ids ) ? $gallery_ids : array();
		}

		self::$is_filtering_gallery = true;
		$strict_extra_ids           = array();

		try {
			$target_var_id = self::resolve_initial_selected_variation_id( $product );
			if ( $target_var_id > 0 ) {
				$strict_extra_ids = self::get_variation_extra_gallery_ids( $target_var_id );
			}
		} catch ( \Throwable $e ) {
			$strict_extra_ids = is_array( $gallery_ids ) ? $gallery_ids : array();
		}

		self::$is_filtering_gallery = false;
		return $strict_extra_ids;
	}

	/**
	 * Replace WooCommerce attribute dropdowns with Image, Colour, or Text Swatches + Cross-Attribute Filtering support.
	 *
	 * @param string $html Original select HTML.
	 * @param array  $args Dropdown arguments.
	 * @return string
	 */
	public static function render_swatches_html( $html, $args = array() ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() || empty( $args['options'] ) || empty( $args['product'] ) || ! ( $args['product'] instanceof WC_Product ) ) {
			return (string) $html;
		}

		$product      = $args['product'];
		$attribute    = (string) $args['attribute'];
		$options      = (array) $args['options'];
		$selected     = (string) ( $args['selected'] ?? '' );
		$attr_slug    = sanitize_title( str_replace( 'attribute_', '', $attribute ) );
		$attr_Key     = 'attribute_' . $attr_slug;
		$attr_name    = wc_attribute_label( $attribute, $product );
		$visual_role  = self::detect_attribute_role( $attribute );
		$display_mode = self::resolve_attribute_display_mode( $product, $attribute, $visual_role );

		$selected_label = '';
		if ( '' !== $selected ) {
			if ( taxonomy_exists( $attribute ) ) {
				$term_obj       = get_term_by( 'slug', $selected, $attribute );
				$selected_label = ( $term_obj && ! is_wp_error( $term_obj ) ) ? $term_obj->name : $selected;
			} else {
				$selected_label = $selected;
			}
		}

		$right_action_html = '';
		if ( 'dimensional' === $visual_role ) {
			$guide = ( class_exists( 'CUX_Inheritance_Engine' ) && method_exists( 'CUX_Inheritance_Engine', 'resolve_size_guide_block' ) )
				? CUX_Inheritance_Engine::resolve_size_guide_block( $product->get_id() )
				: array( 'id_or_slug' => '' );

			if ( ! empty( $guide['id_or_slug'] ) ) {
				$right_action_html = sprintf(
					'<button type="button" class="cux-size-guide-trigger" data-guide="%1$s"><span class="cux-sg-icon">?</span> %2$s</button>',
					esc_attr( $guide['id_or_slug'] ),
					esc_html__( 'Size Guide', 'commerce-ux-studio' )
				);
			}
		} elseif ( 'primary_visual' === $visual_role && count( $options ) > 1 ) {
			$right_action_html = sprintf(
				'<button type="button" class="cux-inline-reset-btn" style="%1$s">%2$s</button>',
				'' !== $selected ? '' : 'display:none;',
				esc_html__( 'Clear', 'commerce-ux-studio' )
			);
		}

		$var_thumb_map = self::build_attribute_option_thumbnail_map( $product, $attr_Key );
		$has_selection = ( '' !== $selected );
		$hidden_select = '<div class="cux-hidden-select" style="display:none !important;">' . $html . '</div>';

		ob_start();
		?>
		<div class="cux-attr-block cux-swatch-control-wrap <?php echo $has_selection ? 'has-selection' : ''; ?>"
			data-attribute="<?php echo esc_attr( $attr_Key ); ?>"
			data-role="<?php echo esc_attr( $visual_role ); ?>"
			data-display-mode="<?php echo esc_attr( $display_mode ); ?>">

			<div class="cux-attr-header">
				<div class="cux-attr-title-wrap">
					<strong class="cux-attr-name"><?php echo esc_html( $attr_name ); ?>:</strong>
					<span class="cux-selected-term-label"><?php echo esc_html( $selected_label ); ?></span>
				</div>
				<?php echo $right_action_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<div class="cux-swatch-group cux-style-<?php echo esc_attr( $display_mode ); ?>" role="radiogroup" aria-label="<?php echo esc_attr( $attr_name ); ?>">
				<?php
				foreach ( $options as $option_value ) :
					$option_str = (string) $option_value;
					$term_obj   = taxonomy_exists( $attribute ) ? get_term_by( 'slug', $option_str, $attribute ) : null;
					$term_label = ( $term_obj && ! is_wp_error( $term_obj ) ) ? $term_obj->name : $option_str;
					$term_id    = ( $term_obj && ! is_wp_error( $term_obj ) ) ? (int) $term_obj->term_id : 0;
					$is_active  = ( (string) $selected === $option_str );
					$state_cls  = $is_active ? 'is-selected' : ( $has_selection ? 'is-deselected' : '' );

					$img_url = self::resolve_swatch_image_url( $display_mode, $term_id, $option_str, $var_thumb_map );

					if ( '' !== $img_url && in_array( $display_mode, array( 'auto_variation_image', 'term_image', 'variation_image' ), true ) ) :
						?>
						<button type="button"
							class="cux-swatch-item cux-swatch-image-tile <?php echo esc_attr( $state_cls ); ?>"
							data-value="<?php echo esc_attr( $option_str ); ?>"
							data-slug="<?php echo esc_attr( sanitize_title( $option_str ) ); ?>"
							data-label="<?php echo esc_attr( $term_label ); ?>"
							data-name="<?php echo esc_attr( $term_label ); ?>"
							aria-checked="<?php echo $is_active ? 'true' : 'false'; ?>"
							title="<?php echo esc_attr( $term_label ); ?>">
							<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $term_label ); ?>" loading="lazy" />
							<span class="cux-swatch-check-badge" aria-hidden="true">✓</span>
							<span class="cux-swatch-bottom-dot" aria-hidden="true"></span>
						</button>
						<?php
					elseif ( 'color_circle' === $display_mode ) :
						$bg_css = self::build_term_swatch_background( $term_id, $term_label, $img_url );
						?>
						<button type="button"
							class="cux-swatch-item cux-swatch-circle <?php echo esc_attr( $state_cls ); ?>"
							data-value="<?php echo esc_attr( $option_str ); ?>"
							data-slug="<?php echo esc_attr( sanitize_title( $option_str ) ); ?>"
							data-label="<?php echo esc_attr( $term_label ); ?>"
							data-name="<?php echo esc_attr( $term_label ); ?>"
							aria-checked="<?php echo $is_active ? 'true' : 'false'; ?>"
							title="<?php echo esc_attr( $term_label ); ?>"
							style="<?php echo esc_attr( $bg_css ); ?>">
							<span class="cux-swatch-check-badge" aria-hidden="true">✓</span>
							<span class="cux-swatch-bottom-dot" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php echo esc_html( $term_label ); ?></span>
						</button>
						<?php
					else :
						?>
						<button type="button"
							class="cux-swatch-item cux-swatch-box <?php echo esc_attr( $state_cls ); ?>"
							data-value="<?php echo esc_attr( $option_str ); ?>"
							data-slug="<?php echo esc_attr( sanitize_title( $option_str ) ); ?>"
							data-label="<?php echo esc_attr( $term_label ); ?>"
							data-name="<?php echo esc_attr( $term_label ); ?>"
							aria-checked="<?php echo $is_active ? 'true' : 'false'; ?>">
							<span class="cux-box-check-inline" aria-hidden="true">✓</span>
							<span class="cux-box-text"><?php echo esc_html( $term_label ); ?></span>
							<span class="cux-swatch-bottom-dot" aria-hidden="true"></span>
						</button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return $hidden_select . (string) ob_get_clean();
	}

	/**
	 * Render Configurable Delivery & Pickup Switcher (Option 6A Rows, Option 6B Segmented Tabs, or Option 6C Cards) + Low Stock Notice.
	 */
	public static function render_delivery_and_low_stock_block(): void {
		global $product;
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		$settings = self::get_settings_safe();

		if ( 'no' !== ( $settings['enable_delivery_block'] ?? 'yes' ) ) {
			$cards        = self::get_live_woocommerce_delivery_options( $product, $settings );
			$heading      = $settings['delivery_heading_label'] ?? __( 'Delivery Method', 'commerce-ux-studio' );
			$layout_style = $settings['delivery_layout_style'] ?? 'option_6a';

			if ( ! empty( $cards ) ) :
				$active_card = $cards[0];
				foreach ( $cards as $c ) {
					if ( ! empty( $c['selected'] ) ) {
						$active_card = $c;
						break;
					}
				}
				?>
				<div class="cux-delivery-wrapper cux-delivery-<?php echo esc_attr( $layout_style ); ?>">
					<div class="cux-delivery-heading"><?php echo esc_html( $heading ); ?></div>

					<?php if ( 'option_6b' === $layout_style ) : ?>
						<div class="cux-delivery-tabs-pill" role="tablist">
							<?php foreach ( $cards as $card ) : ?>
								<button type="button"
									class="cux-delivery-card cux-delivery-tab-btn <?php echo ! empty( $card['selected'] ) ? 'is-selected is-active' : ''; ?>"
									data-rate-id="<?php echo esc_attr( $card['rate_id'] ); ?>"
									data-eta="<?php echo esc_attr( $card['eta'] ); ?>"
									data-price="<?php echo esc_attr( wp_strip_all_tags( $card['price_html'] ) ); ?>"
									data-subtitle="<?php echo esc_attr( $card['subtitle'] ); ?>">
									<?php echo esc_html( $card['title'] ); ?>
								</button>
							<?php endforeach; ?>
						</div>
						<div id="cux-delivery-tab-summary" class="cux-delivery-tab-summary">
							<span class="cux-status-dot is-available"></span>
							<strong class="cux-tab-sum-price"><?php echo wp_kses_post( $active_card['price_html'] ); ?></strong>
							<?php if ( '' !== $active_card['eta'] ) : ?>
								<span class="cux-tab-sum-eta">&bull; <?php echo esc_html( $active_card['eta'] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $active_card['subtitle'] ) : ?>
								<span class="cux-tab-sum-sub">&bull; <?php echo esc_html( $active_card['subtitle'] ); ?></span>
							<?php endif; ?>
						</div>

					<?php elseif ( 'option_6c' === $layout_style ) : ?>
						<div class="cux-delivery-grid-2col">
							<?php foreach ( $cards as $card ) : ?>
								<div class="cux-delivery-card cux-delivery-card-2col <?php echo ! empty( $card['selected'] ) ? 'is-selected is-active' : ''; ?>"
									role="button"
									tabindex="0"
									data-rate-id="<?php echo esc_attr( $card['rate_id'] ); ?>">
									<div class="cux-card-top-title"><?php echo esc_html( $card['title'] ); ?></div>
									<div class="cux-card-bottom-meta">
										<span class="cux-status-dot is-available"></span>
										<?php if ( '' !== $card['eta'] ) : ?>
											<span class="cux-eta-pill"><?php echo esc_html( $card['eta'] ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $card['price_html'] ) : ?>
											<strong class="cux-rate-price"><?php echo wp_kses_post( $card['price_html'] ); ?></strong>
										<?php endif; ?>
									</div>
									<?php if ( '' !== $card['subtitle'] ) : ?>
										<div class="cux-card-subnote"><?php echo esc_html( $card['subtitle'] ); ?></div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>

					<?php else : ?>
						<div class="cux-delivery-rows-stack">
							<?php foreach ( $cards as $card ) : ?>
								<div class="cux-delivery-card cux-delivery-row-item <?php echo ! empty( $card['selected'] ) ? 'is-selected is-active' : ''; ?>"
									role="button"
									tabindex="0"
									data-rate-id="<?php echo esc_attr( $card['rate_id'] ); ?>">
									<div class="cux-row-left">
										<span class="cux-radio-circle"></span>
										<div>
											<div class="cux-row-title"><?php echo esc_html( $card['title'] ); ?></div>
											<?php if ( '' !== $card['subtitle'] ) : ?>
												<div class="cux-row-subtitle"><?php echo esc_html( $card['subtitle'] ); ?></div>
											<?php endif; ?>
										</div>
									</div>
									<div class="cux-row-right">
										<?php if ( '' !== $card['eta'] ) : ?>
											<span class="cux-eta-pill"><?php echo esc_html( $card['eta'] ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $card['price_html'] ) : ?>
											<strong class="cux-row-price"><?php echo wp_kses_post( $card['price_html'] ); ?></strong>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<input type="hidden" id="cux-chosen-rate-id" name="cux_chosen_rate_id" value="<?php echo esc_attr( $active_card['rate_id'] ?? '' ); ?>" />
				</div>
				<?php
			endif;
		}

		$low_qty = self::get_effective_low_stock_qty( $product );
		?>
		<div id="cux-stock-alert-banner" class="cux-low-stock-banner" style="<?php echo $low_qty > 0 ? '' : 'display:none;'; ?>">
			<span class="cux-low-stock-dot"></span>
			<span class="cux-stock-alert-text">
				<?php
				if ( $low_qty > 0 ) {
					printf(
						/* translators: %d: remaining stock quantity */
						esc_html__( 'Low Stock — Only %d left', 'commerce-ux-studio' ),
						(int) $low_qty
					);
				}
				?>
			</span>
		</div>
		<?php
	}

	/**
	 * Render expandable "Complete Your Ride" Cross-Sell drawer.
	 */
	public static function render_complete_your_ride_block(): void {
		global $product;
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		$settings = self::get_settings_safe();
		if ( 'no' === ( $settings['enable_complete_ride'] ?? 'yes' ) ) {
			return;
		}

		$cross_sell_ids = $product->get_cross_sell_ids();
		if ( empty( $cross_sell_ids ) ) {
			return;
		}
		?>
		<div class="cux-complete-ride-box">
			<div class="cux-cr-header">
				<strong><?php esc_html_e( 'Complete Your Ride', 'commerce-ux-studio' ); ?></strong>
				<span class="cux-cr-toggle-icon">▾</span>
			</div>
			<div class="cux-cr-body">
				<?php
				foreach ( array_slice( $cross_sell_ids, 0, 4 ) as $cs_id ) :
					$cs_product = wc_get_product( $cs_id );
					if ( ! $cs_product || ! $cs_product->is_visible() ) {
						continue;
					}
					$thumb = wp_get_attachment_image_url( (int) $cs_product->get_image_id(), 'woocommerce_thumbnail' );
					?>
					<div class="cux-cr-item">
						<div class="cux-cr-summary-row">
							<div class="cux-cr-thumb-info">
								<?php if ( $thumb ) : ?>
									<span class="cux-cr-thumb"><img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $cs_product->get_name() ); ?>" /></span>
								<?php endif; ?>
								<div>
									<a href="<?php echo esc_url( $cs_product->get_permalink() ); ?>" class="cux-cr-title"><?php echo esc_html( $cs_product->get_name() ); ?></a>
									<div class="cux-cr-price"><?php echo wp_kses_post( $cs_product->get_price_html() ); ?></div>
								</div>
							</div>
							<?php if ( $cs_product->is_type( 'simple' ) && $cs_product->is_in_stock() ) : ?>
								<button type="button" class="button cux-cr-direct-add-btn" data-product-id="<?php echo esc_attr( (string) $cs_product->get_id() ); ?>">
									<?php esc_html_e( 'Add', 'commerce-ux-studio' ); ?>
								</button>
							<?php else : ?>
								<a href="<?php echo esc_url( $cs_product->get_permalink() ); ?>" class="button is-outline cux-cr-view-btn">
									<?php esc_html_e( 'Select', 'commerce-ux-studio' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output PDP JSON payload (Cross-Attribute Stock Matrix + Per-Variation Gallery Slides) and Size Guide Modal in footer.
	 */
	public static function render_pdp_json_and_modals(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		global $product;
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		$payload = self::build_live_pdp_payload( $product );

		echo '<script id="cux-pdp-json-data" type="application/json">' . wp_json_encode( $payload ) . '</script>' . "\n";

		// Render Size Guide Modal if configured.
		$guide = ( class_exists( 'CUX_Inheritance_Engine' ) && method_exists( 'CUX_Inheritance_Engine', 'resolve_size_guide_block' ) )
			? CUX_Inheritance_Engine::resolve_size_guide_block( $product->get_id() )
			: array( 'id_or_slug' => '', 'shortcode' => '' );

		if ( ! empty( $guide['id_or_slug'] ) && ! empty( $guide['shortcode'] ) ) :
			?>
			<div id="cux-size-guide-modal" class="cux-modal-overlay" style="display:none;" aria-hidden="true">
				<div class="cux-modal-backdrop"></div>
				<div class="cux-modal-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Size Guide', 'commerce-ux-studio' ); ?>">
					<div class="cux-modal-header">
						<h3><?php esc_html_e( 'Size Guide', 'commerce-ux-studio' ); ?></h3>
						<button type="button" class="cux-modal-close" aria-label="<?php esc_attr_e( 'Close', 'commerce-ux-studio' ); ?>">&times;</button>
					</div>
					<div class="cux-modal-body">
						<?php echo do_shortcode( $guide['shortcode'] ); ?>
					</div>
				</div>
			</div>
			<?php
		endif;
	}

	/**
	 * Build complete variation availability matrix and strict per-variation slide arrays.
	 *
	 * @param WC_Product $product Product object.
	 * @return array<string, mixed>
	 */
	private static function build_live_pdp_payload( WC_Product $product ): array {
		$config = ( class_exists( 'CUX_Inheritance_Engine' ) && method_exists( 'CUX_Inheritance_Engine', 'resolve_product_config' ) )
			? CUX_Inheritance_Engine::resolve_product_config( $product->get_id() )
			: self::get_settings_safe();

		$payload = array(
			'product_id'          => $product->get_id(),
			'gallery_isolation'   => $config['pdp_gallery_isolation'] ?? 'strict_variation',
			'cross_attr_oos_mode' => $config['cross_attr_oos_mode'] ?? 'smart_switch',
			'variations_matrix'   => array(),
			'availability_matrix' => array(),
			'slides_by_variation' => array(),
			'slides_by_hash'      => array(),
		);

		if ( ! $product->is_type( 'variable' ) ) {
			return $payload;
		}

		$threshold = (int) $product->get_low_stock_amount();
		if ( $threshold <= 0 ) {
			$threshold = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		}

		$children = $product->get_children();
		foreach ( $children as $child_id ) {
			$var = wc_get_product( $child_id );
			if ( ! ( $var instanceof WC_Product_Variation ) || ! $var->variation_is_visible() ) {
				continue;
			}

			$var_id     = (int) $var->get_id();
			$raw_attrs  = (array) $var->get_attributes();
			$norm_attrs = array();
			$color_slug = '';

			foreach ( $raw_attrs as $tax_key => $val ) {
				$clean_attr_key                = 'attribute_' . sanitize_title( str_replace( 'attribute_', '', (string) $tax_key ) );
				$norm_attrs[ $clean_attr_key ] = (string) $val;
				if ( '' === $color_slug && ( false !== strpos( $clean_attr_key, 'color' ) || false !== strpos( $clean_attr_key, 'colour' ) ) ) {
					$color_slug = sanitize_title( (string) $val );
				}
			}

			$is_in_stock  = $var->is_in_stock();
			$managing     = $var->managing_stock();
			$stock_qty    = $managing ? (int) $var->get_stock_quantity() : ( $is_in_stock ? 999 : 0 );
			$is_low_stock = ( $is_in_stock && $managing && $stock_qty > 0 && $stock_qty <= $threshold );

			$var_info = array(
				'variation_id'  => $var_id,
				'attributes'    => $norm_attrs,
				'is_in_stock'   => $is_in_stock,
				'is_Low_stock'  => $is_low_stock,
				'is_low_stock'  => $is_low_stock,
				'stock_qty'     => $stock_qty,
				'pickup_status' => (string) get_post_meta( $var_id, '_cux_pickup_status', true ),
				'restock_eta'   => (string) get_post_meta( $var_id, '_cux_restock_eta', true ),
			);

			$payload['variations_matrix'][]            = $var_info;
			$payload['availability_matrix'][ $var_id ] = $var_info;

			// Build strict slide list for this specific variation: [Main Image] + [Variation Extra Gallery Images].
			$var_img_id = (int) $var->get_image_id( 'edit' );
			if ( $var_img_id <= 0 ) {
				$var_img_id = (int) $product->get_image_id( 'edit' );
			}

			$var_extra_ids = self::get_variation_extra_gallery_ids( $var_id );
			$all_img_ids   = array_values( array_unique( array_filter( array_merge( array( $var_img_id ), $var_extra_ids ) ) ) );

			$slides = array();
			foreach ( $all_img_ids as $att_id ) {
				$slide_data = self::build_single_slide_data( (int) $att_id );
				if ( ! empty( $slide_data ) ) {
					$slides[] = $slide_data;
				}
			}

			if ( ! empty( $slides ) ) {
				$payload['slides_by_variation'][ $var_id ] = $slides;
				if ( '' !== $color_slug && ( empty( $payload['slides_by_hash'][ $color_slug ] ) || count( $slides ) > count( $payload['slides_by_hash'][ $color_slug ] ) ) ) {
					$payload['slides_by_hash'][ $color_slug ] = $slides;
				}
			}
		}

		return $payload;
	}

	/**
	 * Build HTML/URL data for a single gallery attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, string>
	 */
	private static function build_single_slide_data( int $attachment_id ): array {
		if ( $attachment_id <= 0 ) {
			return array();
		}

		$full_url  = (string) wp_get_attachment_image_url( $attachment_id, 'full' );
		$thumb_url = (string) wp_get_attachment_image_url( $attachment_id, 'woocommerce_gallery_thumbnail' );
		if ( '' === $full_url ) {
			return array();
		}

		$main_img_html  = wp_get_attachment_image(
			$attachment_id,
			'woocommerce_single',
			false,
			array(
				'class'                   => 'wp-post-image skip-lazy',
				'data-large_image'        => $full_url,
				'data-large_image_width'  => 1200,
				'data-large_image_height' => 1200,
			)
		);
		$thumb_img_html = wp_get_attachment_image(
			$attachment_id,
			'woocommerce_gallery_thumbnail',
			false,
			array( 'class' => 'attachment-woocommerce_thumbnail' )
		);

		return array(
			'id'        => (string) $attachment_id,
			'full_url'  => $full_url,
			'thumb_url' => $thumb_url ?: $full_url,
			'main_img'  => $main_img_html,
			'thumb'     => $thumb_img_html,
		);
	}

	/**
	 * Read extra gallery attachment IDs stored directly on a variation.
	 *
	 * @param int $variation_id Variation ID.
	 * @return array<int, int>
	 */
	private static function get_variation_extra_gallery_ids( int $variation_id ): array {
		$raw = (string) get_post_meta( $variation_id, '_product_image_gallery', true );
		if ( '' === $raw ) {
			$raw = (string) get_post_meta( $variation_id, '_wc_additional_variation_images', true );
		}
		if ( '' === $raw ) {
			return array();
		}
		$parts = array_filter( array_map( 'absint', explode( ',', $raw ) ) );
		return array_values( array_unique( $parts ) );
	}

	/**
	 * Find the initial active variation ID on page load (from URL query or default attributes).
	 *
	 * @param WC_Product $product Variable product.
	 * @return int
	 */
	private static function resolve_initial_selected_variation_id( WC_Product $product ): int {
		$defaults = (array) $product->get_default_attributes();
		foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 0 === strpos( (string) $k, 'attribute_' ) ) {
				$clean_k              = str_replace( 'attribute_', '', sanitize_title( (string) $k ) );
				$defaults[ $clean_k ] = sanitize_text_field( wp_unslash( (string) $v ) );
			}
		}

		$children = $product->get_children();
		if ( empty( $children ) ) {
			return 0;
		}

		foreach ( $children as $child_id ) {
			$var = wc_get_product( $child_id );
			if ( ! ( $var instanceof WC_Product_Variation ) || ! $var->is_in_stock() ) {
				continue;
			}
			$match = true;
			foreach ( $defaults as $attr_key => $attr_val ) {
				$var_val = (string) $var->get_attribute( $attr_key );
				if ( '' !== $var_val && strtolower( $var_val ) !== strtolower( (string) $attr_val ) ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				return (int) $child_id;
			}
		}

		return (int) $children[0];
	}

	/**
	 * Resolve whether an attribute should display as `auto_variation_image`, `term_image`, `color_circle`, or `text_box`.
	 *
	 * @param WC_Product $product     Product object.
	 * @param string     $attribute   Attribute taxonomy/slug.
	 * @param string     $visual_role Detected attribute role.
	 * @return string
	 */
	private static function resolve_attribute_display_mode( WC_Product $product, string $attribute, string $visual_role ): string {
		$tax_key  = sanitize_key( str_replace( 'attribute_', '', $attribute ) );
		$settings = self::get_settings_safe();

		// 1. Per-Product Override in Product data > General (applies to primary visual / colour attribute).
		if ( 'primary_visual' === $visual_role ) {
			$prod_override = (string) get_post_meta( $product->get_id(), '_cux_product_swatch_mode', true );
			if ( '' !== $prod_override && 'inherit' !== $prod_override ) {
				return $prod_override;
			}
		}

		// 2. Per-Attribute Mapper Table in Tab 1 (`attribute_display_map`).
		$attr_map = isset( $settings['attribute_display_map'] ) && is_array( $settings['attribute_display_map'] ) ? $settings['attribute_display_map'] : array();
		if ( ! empty( $attr_map[ $tax_key ] ) && 'auto_detect' !== $attr_map[ $tax_key ] ) {
			return (string) $attr_map[ $tax_key ];
		}

		// 3. Global Default Visual Swatch Mode.
		if ( 'primary_visual' === $visual_role ) {
			$global_mode = (string) ( $settings['visual_swatch_mode'] ?? 'smart_hybrid' );
			if ( 'variation_image' === $global_mode || 'auto_variation_image' === $global_mode ) {
				return 'auto_variation_image';
			}
			if ( 'text_box' === $global_mode ) {
				return 'text_box';
			}
			return 'color_circle';
		}

		return 'text_box';
	}

	/**
	 * Build a lookup map of attribute option slug -> Variation Thumbnail Image URL.
	 *
	 * @param WC_Product $product  Product object.
	 * @param string     $attr_key Normalized attribute key (`attribute_pa_colour`).
	 * @return array<string, string>
	 */
	private static function build_attribute_option_thumbnail_map( WC_Product $product, string $attr_key ): array {
		$map = array();
		if ( ! $product->is_type( 'variable' ) ) {
			return $map;
		}

		foreach ( $product->get_children() as $child_id ) {
			$var = wc_get_product( $child_id );
			if ( ! ( $var instanceof WC_Product_Variation ) ) {
				continue;
			}
			$attrs = (array) $var->get_attributes();
			foreach ( $attrs as $k => $v ) {
				$clean_k = 'attribute_' . sanitize_title( str_replace( 'attribute_', '', (string) $k ) );
				if ( $clean_k === $attr_key && '' !== (string) $v && empty( $map[ (string) $v ] ) ) {
					$img_id = (int) $var->get_image_id( 'edit' );
					if ( $img_id > 0 ) {
						$url = wp_get_attachment_image_url( $img_id, 'woocommerce_gallery_thumbnail' );
						if ( $url ) {
							$map[ (string) $v ] = (string) $url;
						}
					}
				}
			}
		}

		return $map;
	}

	/**
	 * Resolve image URL for an attribute option when Image Mode is selected.
	 *
	 * @param string $display_mode  Selected display mode.
	 * @param int    $term_id       Term ID.
	 * @param string $option_val    Option slug.
	 * @param array  $var_thumb_map Map of option slug -> variation image URL.
	 * @return string
	 */
	private static function resolve_swatch_image_url( string $display_mode, int $term_id, string $option_val, array $var_thumb_map ): string {
		$term_img_url = '';
		if ( $term_id > 0 ) {
			$img_id = (int) get_term_meta( $term_id, '_cux_swatch_image_id', true );
			if ( $img_id > 0 ) {
				$term_img_url = (string) wp_get_attachment_image_url( $img_id, 'thumbnail' );
			}
		}

		if ( 'auto_variation_image' === $display_mode || 'variation_image' === $display_mode ) {
			return $var_thumb_map[ $option_val ] ?? $term_img_url;
		}

		if ( 'term_image' === $display_mode ) {
			return '' !== $term_img_url ? $term_img_url : ( $var_thumb_map[ $option_val ] ?? '' );
		}

		return $term_img_url;
	}

	/**
	 * AJAX handler to sync shopper's selected shipping method into WooCommerce session.
	 */
	public static function ajax_set_shipping_method(): void {
		check_ajax_referer( 'cux_frontend_nonce', 'nonce' );

		$rate_id = isset( $_POST['rate_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rate_id'] ) ) : '';
		if ( '' !== $rate_id && function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'chosen_shipping_methods', array( $rate_id ) );
			wp_send_json_success( array( 'chosen' => $rate_id ) );
		}

		wp_send_json_error();
	}

	/**
	 * AJAX handler for catalog quick-add and Complete Your Ride add-to-cart.
	 */
	public static function ajax_quick_add_to_cart(): void {
		check_ajax_referer( 'cux_frontend_nonce', 'nonce' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$quantity     = isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 1;

		if ( $product_id > 0 && function_exists( 'WC' ) && WC()->cart ) {
			$added = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
			if ( $added ) {
				WC_AJAX::get_refreshed_fragments();
				return;
			}
		}

		wp_send_json_error();
	}

	/**
	 * Build live delivery/pickup options from WooCommerce Shipping Zones, Methods & Product Shipping Classes.
	 *
	 * @param WC_Product $product  Current product.
	 * @param array      $settings Studio settings.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_live_woocommerce_delivery_options( WC_Product $product, array $settings ): array {
		$cards       = array();
		$sync_mode   = $settings['wc_shipping_sync_mode'] ?? 'auto_wc';
		$method_meta = isset( $settings['shipping_method_meta'] ) && is_array( $settings['shipping_method_meta'] ) ? $settings['shipping_method_meta'] : array();
		$chosen      = ( function_exists( 'WC' ) && WC()->session ) ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();

		if ( 'auto_wc' === $sync_mode && class_exists( 'WC_Shipping_Zones' ) ) {
			try {
				$zone = self::resolve_active_shipping_zone();
				if ( $zone instanceof WC_Shipping_Zone ) {
					$methods = $zone->get_shipping_methods( true );
					foreach ( $methods as $instance_id => $method ) {
						if ( ! is_object( $method ) || empty( $method->id ) ) {
							continue;
						}
						$rate_id = $method->id . ':' . $instance_id;
						$ov      = $method_meta[ $rate_id ] ?? array();

						if ( isset( $ov['enabled'] ) && 'no' === $ov['enabled'] ) {
							continue;
						}

						$wc_title   = method_exists( $method, 'get_title' ) && ! empty( $method->get_title() ) ? $method->get_title() : ( $method->title ?? $method->id );
						$is_pickup  = ( 'local_pickup' === $method->id );
						$title      = ! empty( $ov['title'] ) ? $ov['title'] : $wc_title;
						$eta        = ! empty( $ov['lead_time'] ) ? $ov['lead_time'] : ( $is_pickup ? ( $settings['default_pickup_lead'] ?? '6-10 days' ) : ( $settings['default_home_lead'] ?? '4-7 days' ) );
						$subtitle   = ! empty( $ov['subtitle'] ) ? $ov['subtitle'] : ( $is_pickup ? __( 'Available for store pickup', 'commerce-ux-studio' ) : __( 'Available', 'commerce-ux-studio' ) );
						$price_html = self::calculate_method_price_for_product( $method, $product );

						$cards[] = array(
							'rate_id'    => $rate_id,
							'title'      => (string) $title,
							'eta'        => (string) $eta,
							'price_html' => $price_html,
							'subtitle'   => (string) $subtitle,
							'selected'   => in_array( $rate_id, $chosen, true ) || ( empty( $chosen ) && empty( $cards ) ),
						);
					}
				}
			} catch ( \Throwable $e ) {
				$cards = array();
			}
		}

		if ( empty( $cards ) ) {
			$cards = array(
				array(
					'rate_id'    => 'flat_rate:default',
					'title'      => $settings['home_card_title'] ?? __( 'Standard Delivery', 'commerce-ux-studio' ),
					'eta'        => $settings['default_home_lead'] ?? '4-7 days',
					'price_html' => '',
					'subtitle'   => __( 'Tracked courier delivery', 'commerce-ux-studio' ),
					'selected'   => true,
				),
				array(
					'rate_id'    => 'local_pickup:default',
					'title'      => $settings['pickup_card_title'] ?? __( 'In-Store Pickup', 'commerce-ux-studio' ),
					'eta'        => $settings['default_pickup_lead'] ?? '6-10 days',
					'price_html' => __( 'Free', 'commerce-ux-studio' ),
					'subtitle'   => __( 'Available for store pickup', 'commerce-ux-studio' ),
					'selected'   => false,
				),
			);
		}

		return $cards;
	}

	/**
	 * Resolve active WooCommerce Shipping Zone for visitor or store base country.
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
				array(
					'destination' => array(
						'country'  => $country,
						'state'    => $state,
						'postcode' => $postcode,
					),
				)
			);
			if ( $matched instanceof WC_Shipping_Zone && count( $matched->get_shipping_methods( true ) ) > 0 ) {
				return $matched;
			}
		}

		$all_zones = WC_Shipping_Zones::get_zones();
		if ( is_array( $all_zones ) ) {
			foreach ( $all_zones as $z_data ) {
				$z_id = (int) ( $z_data['zone_id'] ?? $z_data['id'] ?? 0 );
				$z    = new WC_Shipping_Zone( $z_id );
				if ( count( $z->get_shipping_methods( true ) ) > 0 ) {
					return $z;
				}
			}
		}

		return new WC_Shipping_Zone( 0 );
	}

	/**
	 * Calculate shipping cost for a product including its WooCommerce Shipping Class fee.
	 *
	 * @param object     $method  Shipping method instance.
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	private static function calculate_method_price_for_product( object $method, WC_Product $product ): string {
		$method_id = (string) ( $method->id ?? '' );

		if ( 'free_shipping' === $method_id ) {
			return esc_html__( 'Free', 'commerce-ux-studio' );
		}

		if ( 'local_pickup' === $method_id ) {
			$pickup_cost = is_numeric( $method->cost ?? '' ) ? (float) $method->cost : 0.0;
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
	 * Classify attribute role (`primary_visual`, `secondary_visual`, or `dimensional`).
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
	 * Build CSS background for a colour circle swatch.
	 *
	 * @param int    $term_id      Term ID.
	 * @param string $term_label   Term name.
	 * @param string $fallback_img Optional image URL.
	 * @return string
	 */
	public static function build_term_swatch_background( int $term_id, string $term_label, string $fallback_img = '' ): string {
		if ( '' !== $fallback_img ) {
			return 'background-image:url(' . esc_url( $fallback_img ) . ');background-size:cover;background-position:center;';
		}

		if ( $term_id > 0 ) {
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

		$name = strtolower( $term_label );
		$map  = array(
			'black'  => '#18181b',
			'white'  => '#f8fafc',
			'red'    => '#dc2626',
			'blue'   => '#2563eb',
			'navy'   => '#1e3a8a',
			'green'  => '#16a34a',
			'teal'   => '#0f766e',
			'yellow' => '#eab308',
			'orange' => '#ea580c',
			'gold'   => '#ca8a04',
			'silver' => '#cbd5e1',
			'chrome' => '#94a3b8',
			'grey'   => '#64748b',
			'gray'   => '#64748b',
			'pink'   => '#ec4899',
			'purple' => '#7c3aed',
			'brown'  => '#78350f',
		);

		$found = array();
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
	 * Resolve effective low-stock quantity if at or below threshold.
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
			$target_id = self::resolve_initial_selected_variation_id( $product );
			if ( $target_id > 0 ) {
				$var = wc_get_product( $target_id );
				if ( $var && $var->managing_stock() ) {
					$qty = (int) $var->get_stock_quantity();
					if ( $qty > 0 && $qty <= $threshold ) {
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
	 * Safely retrieve global settings even if CUX_Inheritance_Engine is an older version.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_settings_safe(): array {
		if ( class_exists( 'CUX_Inheritance_Engine' ) && method_exists( 'CUX_Inheritance_Engine', 'get_global_settings' ) ) {
			return CUX_Inheritance_Engine::get_global_settings();
		}
		$s = get_option( 'cux_global_settings', array() );
		return is_array( $s ) ? $s : array();
	}
}
