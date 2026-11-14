<?php
/**
 * Flatsome Shop & Category Archive Card Renderer (Specialized, Pinarello & Zara Styles).
 *
 * File Path: includes/frontend/class-cux-catalog-cards.php
 *
 * Responsibilities:
 * 1. Injects Preset Classes onto Flatsome `.product-small` loop items based on the 4-Tier Inheritance Engine.
 * 2. Renders Top-Left Status Badges ("Award Winning") and Top-Right Spec Indicators ("DISC") inside the image box.
 * 3. Renders Pinarello-style overlaid metallic color swatches OR Specialized-style 50/50 split swatches + Compare checkbox.
 * 4. Renders Zara-style Quick-Add Size Bar on card image hover/tap for 1-click catalog Add to Cart.
 * 5. Renders Technical Spec Subtitle ("Shimano Dura-Ace Di2"), Brand Tagline, and Monthly Financing text.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Catalog_Cards {

	/**
	 * Initialize WooCommerce & Flatsome shop loop hooks.
	 */
	public static function init(): void {
		// 1. Add preset & hover classes to each product card in the catalog loop.
		add_filter( 'woocommerce_post_class', array( __CLASS__, 'add_catalog_card_classes' ), 20, 2 );

		// 2. Render Badges, Overlaid Swatches (Pinarello) & Quick-Add Size Bar (Zara) inside the image box.
		add_action( 'woocommerce_before_shop_loop_item_title', array( __CLASS__, 'render_card_image_overlays' ), 15 );

		// 3. Render Swatch Row + Compare Checkbox (Specialized) above the product title, plus Spec Subtitle below title.
		add_action( 'woocommerce_shop_loop_item_title', array( __CLASS__, 'render_before_card_title_swatches' ), 5 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'render_after_card_title_subtitle' ), 6 );

		// 4. Render Financing text below the price and Floating Compare Bar in footer.
		add_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'render_card_financing_note' ), 15 );
		add_action( 'wp_footer', array( __CLASS__, 'render_floating_compare_bar' ), 30 );
	}

	/**
	 * Add Commerce UX Studio preset classes to each catalog product `<div class="product">`.
	 *
	 * @param array      $classes Existing post classes.
	 * @param WC_Product $product Current product object.
	 * @return array
	 */
	public static function add_catalog_card_classes( array $classes, $product ): array {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return $classes;
		}

		$product_id  = (int) $product->get_id();
		$config      = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		$preset_slug = $config['catalog_card_preset'] ?? 'specialized_spec';
		$hover_mode  = $config['catalog_hover_mode'] ?? 'angle_2';

		$classes[] = 'cux-card-enhanced';
		$classes[] = 'cux-card-preset-' . sanitize_html_class( $preset_slug );
		$classes[] = 'cux-card-hover-' . sanitize_html_class( $hover_mode );

		return $classes;
	}

	/**
	 * Render Top-Left Badge ("Award Winning"), Top-Right Spec ("DISC"),
	 * Pinarello Overlaid Swatches, and Zara Quick-Add Size Pills inside the card image container.
	 */
	public static function render_card_image_overlays(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id = (int) $product->get_id();
		$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		$preset     = $config['catalog_card_preset'] ?? 'specialized_spec';

		// Resolve Top-Left Badge (Product meta first, then Tag inheritance).
		$badge_tl = (string) get_post_meta( $product_id, '_cux_badge_tl', true );
		if ( empty( $badge_tl ) && ! empty( $config['badge_tl'] ) ) {
			$badge_tl = (string) $config['badge_tl'];
		}

		// Resolve Top-Right Spec Label (e.g. "DISC").
		$badge_tr = (string) get_post_meta( $product_id, '_cux_badge_tr', true );

		// Ensure cache is available for Variable products.
		$cache = array();
		if ( $product->is_type( 'variable' ) ) {
			$cache = CUX_Cache_Manager::get_product_cache( $product_id );
			if ( empty( $cache ) && class_exists( 'CUX_Stock_Calculator' ) ) {
				CUX_Stock_Calculator::sync_parent_product( $product_id, 'catalog_init' );
				$cache = CUX_Cache_Manager::get_product_cache( $product_id );
			}
		}

		$colors       = $cache['in_stock_colors'] ?? array();
		$max_swatches = max( 1, (int) ( $config['catalog_max_swatches'] ?? 4 ) );
		?>
		<div class="cux-card-overlays" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>" data-base-url="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
			<?php if ( ! empty( $badge_tl ) ) : ?>
				<span class="cux-card-badge-tl"><?php echo esc_html( $badge_tl ); ?></span>
			<?php endif; ?>

			<?php if ( ! empty( $badge_tr ) ) : ?>
				<span class="cux-card-badge-tr"><?php echo esc_html( $badge_tr ); ?></span>
			<?php endif; ?>

			<?php
			// Style B (Pinarello Showroom): Render Metallic Color Circles overlaid in the bottom-left of the image box.
			if ( 'pinarello_showroom' === $preset && ! empty( $colors ) ) :
				self::render_swatch_dots_markup( $colors, $max_swatches, 'cux-card-swatches-overlaid' );
			endif;

			// Style C (Zara Quick-Add): Render Hover Size Selector Bar inside the bottom of the image frame.
			if ( 'zara_quick_add' === $preset && $product->is_type( 'variable' ) ) :
				$quick_sizes = self::get_in_stock_size_pills( $product, $cache );
				if ( ! empty( $quick_sizes ) ) :
					?>
					<div class="cux-zara-quick-add-bar">
						<span class="cux-zara-quick-title"><?php esc_html_e( 'Quick Add Size', 'commerce-ux-studio' ); ?></span>
						<div class="cux-zara-size-list">
							<?php foreach ( $quick_sizes as $sz ) : ?>
								<button type="button"
									class="cux-zara-size-pill"
									data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
									data-variation-id="<?php echo esc_attr( (string) $sz['var_id'] ); ?>"
									data-color-slug="<?php echo esc_attr( $sz['color_slug'] ); ?>">
									<?php echo esc_html( $sz['size_label'] ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					</div>
					<?php
				endif;
			endif;
			?>
		</div>
		<?php
	}

	/**
	 * Render Swatch Circles + Compare Checkbox below the image (for Specialized & Zara presets).
	 */
	public static function render_before_card_title_swatches(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id   = (int) $product->get_id();
		$config       = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		$preset       = $config['catalog_card_preset'] ?? 'specialized_spec';
		$max_swatches = max( 1, (int) ( $config['catalog_max_swatches'] ?? 4 ) );

		// Pinarello renders its swatches overlaid inside the image container.
		if ( 'pinarello_showroom' === $preset ) {
			return;
		}

		$cache  = $product->is_type( 'variable' ) ? CUX_Cache_Manager::get_product_cache( $product_id ) : array();
		$colors = $cache['in_stock_colors'] ?? array();

		if ( empty( $colors ) && 'specialized_spec' !== $preset ) {
			return;
		}

		$thumb_url = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
		?>
		<div class="cux-card-swatch-compare-row" data-base-url="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
			<div class="cux-card-swatches-left">
				<?php
				if ( ! empty( $colors ) ) {
					self::render_swatch_dots_markup( $colors, $max_swatches, 'cux-card-swatches-inline' );
				}
				?>
			</div>

			<?php if ( 'specialized_spec' === $preset ) : ?>
				<label class="cux-card-compare-label" title="<?php esc_attr_e( 'Compare this product', 'commerce-ux-studio' ); ?>">
					<span><?php esc_html_e( 'Compare', 'commerce-ux-studio' ); ?></span>
					<input type="checkbox"
						class="cux-compare-checkbox"
						value="<?php echo esc_attr( (string) $product_id ); ?>"
						data-title="<?php echo esc_attr( $product->get_name() ); ?>"
						data-price="<?php echo esc_attr( wp_strip_all_tags( $product->get_price_html() ) ); ?>"
						data-subtitle="<?php echo esc_attr( (string) get_post_meta( $product_id, '_cux_spec_subtitle', true ) ); ?>"
						data-img="<?php echo esc_url( $thumb_url ? $thumb_url : '' ); ?>"
						data-url="<?php echo esc_url( get_permalink( $product_id ) ); ?>" />
				</label>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Technical Spec Subtitle ("Shimano Dura-Ace Di2") or Brand Tagline ("AMPLIFY EVERY EMOTION") below the card title.
	 */
	public static function render_after_card_title_subtitle(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id = (int) $product->get_id();
		$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		$preset     = $config['catalog_card_preset'] ?? 'specialized_spec';

		$subtitle = (string) get_post_meta( $product_id, '_cux_spec_subtitle', true );
		$tagline  = (string) get_post_meta( $product_id, '_cux_tagline', true );

		// Fallback to native WooCommerce Brand name if Pinarello tagline is empty.
		if ( 'pinarello_showroom' === $preset && empty( $tagline ) && taxonomy_exists( 'product_brand' ) ) {
			$brands = wp_get_post_terms( $product_id, 'product_brand', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $brands ) && ! empty( $brands[0] ) ) {
				$tagline = (string) $brands[0];
			}
		}

		if ( 'pinarello_showroom' === $preset && ! empty( $tagline ) ) :
			?>
			<div class="cux-card-tagline"><?php echo esc_html( $tagline ); ?></div>
			<?php
		elseif ( ! empty( $subtitle ) ) :
			?>
			<div class="cux-card-spec-subtitle"><?php echo esc_html( $subtitle ); ?></div>
			<?php
		endif;
	}

	/**
	 * Render Optional Financing / Installment subtext below the card price.
	 */
	public static function render_card_financing_note(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$financing = (string) get_post_meta( $product->get_id(), '_cux_financing_text', true );
		if ( empty( $financing ) ) {
			return;
		}
		?>
		<div class="cux-card-financing-note"><?php echo esc_html( $financing ); ?></div>
		<?php
	}

	/**
	 * Helper: Output the interactive Color Circle swatches + "+X more" counter for a product card.
	 *
	 * @param array  $colors        Array of in-stock color metadata from `_cux_product_cache`.
	 * @param int    $max_swatches  Max visible circles before showing "+X".
	 * @param string $wrapper_class CSS class for the container.
	 */
	private static function render_swatch_dots_markup( array $colors, int $max_swatches, string $wrapper_class ): void {
		$visible   = array_slice( $colors, 0, $max_swatches );
		$remaining = count( $colors ) - count( $visible );
		?>
		<div class="cux-card-swatches <?php echo esc_attr( $wrapper_class ); ?>">
			<?php
			foreach ( $visible as $idx => $color ) :
				$hex_1 = ! empty( $color['hex_1'] ) ? $color['hex_1'] : '#334155';
				$hex_2 = $color['hex_2'] ?? '';
				$split = $color['split'] ?? 'horizontal';

				$bg_css = $hex_1;
				if ( ! empty( $hex_2 ) ) {
					if ( 'angled' === $split ) {
						$bg_css = "linear-gradient(135deg, {$hex_1} 50%, {$hex_2} 50%)";
					} elseif ( 'vertical' === $split ) {
						$bg_css = "linear-gradient(90deg, {$hex_1} 50%, {$hex_2} 50%)";
					} else {
						// Specialized Horizontal 50/50 split.
						$bg_css = "linear-gradient(180deg, {$hex_1} 50%, {$hex_2} 50%)";
					}
				} elseif ( 'metallic' === $split ) {
					$bg_css = "radial-gradient(circle at 30% 30%, rgba(255,255,255,0.55), {$hex_1} 70%)";
				}
				?>
				<button type="button"
					class="cux-card-swatch-dot <?php echo 0 === $idx ? 'is-active' : ''; ?>"
					data-color-slug="<?php echo esc_attr( $color['slug'] ); ?>"
					data-color-name="<?php echo esc_attr( $color['name'] ); ?>"
					data-img-src="<?php echo esc_url( $color['card_img_url'] ?? '' ); ?>"
					data-hover-src="<?php echo esc_url( $color['second_angle_url'] ?? '' ); ?>"
					data-deep-link="<?php echo esc_attr( $color['deep_link_param'] ?? '' ); ?>"
					title="<?php echo esc_attr( $color['name'] ); ?>"
					aria-label="<?php echo esc_attr( $color['name'] ); ?>">
					<span class="cux-card-swatch-inner" style="background: <?php echo esc_attr( $bg_css ); ?>;"></span>
				</button>
			<?php endforeach; ?>

			<?php if ( $remaining > 0 ) : ?>
				<span class="cux-card-swatch-more">+<?php echo esc_html( (string) $remaining ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Extract in-stock size buttons for Zara Quick-Add Catalog Cards.
	 *
	 * @param WC_Product $product Variable product.
	 * @param array      $cache   Precomputed product cache.
	 * @return array
	 */
	private static function get_in_stock_size_pills( WC_Product $product, array $cache ): array {
		$avail_map   = $cache['availability_matrix'] ?? array();
		$dim_attrs   = $cache['dimensional_attrs'] ?? array( 'pa_size', 'size' );
		$winner_slug = $cache['winner_color_slug'] ?? '';
		$pills       = array();
		$seen_sizes  = array();

		if ( empty( $avail_map ) ) {
			return array();
		}

		foreach ( $avail_map as $var_id => $var_data ) {
			if ( empty( $var_data['is_in_stock'] ) ) {
				continue;
			}

			$var_color  = $var_data['color_slug'] ?? '';
			$attributes = $var_data['attributes'] ?? array();
			$size_val   = '';

			foreach ( $attributes as $k => $v ) {
				$k_clean = strtolower( (string) $k );
				if ( in_array( $k_clean, $dim_attrs, true ) || false !== strpos( $k_clean, 'size' ) ) {
					$size_val = strtoupper( (string) $v );
					break;
				}
			}

			if ( empty( $size_val ) && ! empty( $attributes ) ) {
				$size_val = strtoupper( (string) end( $attributes ) );
			}

			$unique_key = $var_color . '__' . $size_val;
			if ( empty( $size_val ) || isset( $seen_sizes[ $unique_key ] ) ) {
				continue;
			}
			$seen_sizes[ $unique_key ] = true;

			$pills[] = array(
				'var_id'      => $var_id,
				'size_label'  => $size_val,
				'color_slug'  => $var_color,
				'is_default'  => ( $var_color === $winner_slug ),
			);
		}

		return array_slice( $pills, 0, 12 );
	}

	/**
	 * Render the Floating Side-by-Side Compare Drawer at the bottom of Shop / Category pages.
	 */
	public static function render_floating_compare_bar(): void {
		if ( ! function_exists( 'is_woocommerce' ) || ( ! is_shop() && ! is_product_taxonomy() && ! is_front_page() ) ) {
			return;
		}
		?>
		<div id="cux-compare-bar" class="cux-compare-bar" style="display:none;">
			<div class="cux-compare-bar-inner">
				<div class="cux-compare-items" id="cux-compare-items-list"></div>
				<div class="cux-compare-actions">
					<button type="button" class="cux-compare-open-modal-btn" id="cux-compare-launch-btn">
						<?php esc_html_e( 'Compare Selected', 'commerce-ux-studio' ); ?> (<span id="cux-compare-count">0</span>)
					</button>
					<button type="button" class="cux-compare-clear-btn" id="cux-compare-clear-btn">
						<?php esc_html_e( 'Clear', 'commerce-ux-studio' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div id="cux-compare-modal" class="cux-compare-modal" style="display:none;">
			<div class="cux-compare-modal-backdrop"></div>
			<div class="cux-compare-modal-content">
				<div class="cux-compare-modal-header">
					<h3><?php esc_html_e( 'Product Comparison', 'commerce-ux-studio' ); ?></h3>
					<button type="button" class="cux-compare-modal-close">&times;</button>
				</div>
				<div class="cux-compare-modal-body" id="cux-compare-modal-grid"></div>
			</div>
		</div>
		<?php
	}
}
