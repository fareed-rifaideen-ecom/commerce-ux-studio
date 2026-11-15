<?php
/**
 * Flatsome Shop & Category Archive Card Renderer (v2.3 Universal Configurable Edition).
 *
 * File Path: includes/frontend/class-cux-catalog-cards.php
 *
 * Responsibilities:
 * 1. Connects to `CUX_Inheritance_Engine::resolve_product_config()` so switching between
 *    Style A (Technical Spec), Style B (Minimalist Showroom), and Style C (Apparel Quick-Add)
 *    in Tab 1 immediately updates Shop & Category cards.
 * 2. Fixes the Flatsome `.box-image` overlay hook (`flatsome_woocommerce_shop_loop_images`)
 *    so Style B overlaid swatches and Style C Quick-Add bars stay strictly inside the image
 *    frame and never overlap the product price (`﷼ ● 5`).
 * 3. Injects the dynamic Hover Image ID (`angle_2` or `second_color`) into
 *    `woocommerce_product_get_gallery_image_ids` so Flatsome's native "Back Image - Fade In"
 *    hover works automatically on every variable product.
 * 4. Syncs Native WooCommerce Brands (`product_brand`) and Variation Image Swatches onto Shop Cards.
 *
 * @package Commerce_UX_Studio
 * @author  Fareed M. Rifaideen <https://fareed-rifaideen.netlify.app/>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Catalog_Cards {

	/**
	 * Guard flag to prevent recursion when filtering gallery image IDs on shop archives.
	 *
	 * @var array<int, bool>
	 */
	private static $filtering_gallery = array();

	/**
	 * Initialize WooCommerce & Flatsome shop loop hooks.
	 */
	public static function init(): void {
		// 1. Add preset, swatch contrast & hover classes to each product card in the catalog loop.
		add_filter( 'woocommerce_post_class', array( __CLASS__, 'add_catalog_card_classes' ), 20, 2 );

		// 2. Dynamically feed the Hover Image ID (2nd Angle or 2nd Color) into Flatsome's "Back Image - Fade In".
		add_filter( 'woocommerce_product_get_gallery_image_ids', array( __CLASS__, 'filter_catalog_hover_gallery_ids' ), 20, 2 );

		// 3. Render Badges, Style B Overlaid Swatches & Style C Quick-Add Size Bar strictly inside Flatsome's `.box-image`.
		add_action( 'init', array( __CLASS__, 'register_image_overlay_hook' ), 20 );

		// 4. Render Synced WooCommerce Brand Eyebrow & Swatch + Compare Row above the card title (outside `.price-wrapper`).
		add_action( 'woocommerce_shop_loop_item_title', array( __CLASS__, 'render_before_card_title_elements' ), 2 );

		// 5. Render Technical Spec Subtitle & Financing Note cleanly after the card price.
		add_action( 'woocommerce_after_shop_loop_item', array( __CLASS__, 'render_after_card_price_meta' ), 5 );

		// 6. Render Floating Side-by-Side Compare Bar & Modal in footer.
		add_action( 'wp_footer', array( __CLASS__, 'render_floating_compare_bar' ), 30 );
	}

	/**
	 * Hook image overlays directly into Flatsome's `.box-image` container so absolute badges/swatches
	 * never sink to the bottom of the card over the price.
	 */
	public static function register_image_overlay_hook(): void {
		if ( has_action( 'flatsome_woocommerce_shop_loop_images' ) ) {
			add_action( 'flatsome_woocommerce_shop_loop_images', array( __CLASS__, 'render_card_image_overlays' ), 25 );
		} else {
			add_action( 'woocommerce_before_shop_loop_item_title', array( __CLASS__, 'render_card_image_overlays' ), 15 );
		}
	}

	/**
	 * 1. Add Commerce UX Studio preset & contrast classes to each catalog product `<div class="product">`.
	 *
	 * @param array      $classes Existing post classes.
	 * @param WC_Product $product Current product object.
	 * @return array
	 */
	public static function add_catalog_card_classes( array $classes, $product ): array {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return $classes;
		}

		// Only apply catalog card classes on archive/loop items (not the main single product wrapper).
		if ( function_exists( 'is_product' ) && is_product() && (int) get_queried_object_id() === (int) $product->get_id() ) {
			return $classes;
		}

		$product_id     = (int) $product->get_id();
		$config         = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		$preset_slug    = $config['catalog_card_preset'] ?? 'specialized_spec';
		$hover_mode     = $config['catalog_hover_mode'] ?? 'angle_2';
		$contrast_style = $config['swatch_active_style'] ?? 'checkmark_4a';
		$swatch_mode    = $config['visual_swatch_mode'] ?? 'hybrid_auto';

		$classes[] = 'cux-card-enhanced';
		$classes[] = 'cux-card-preset-' . sanitize_html_class( $preset_slug );
		$classes[] = 'cux-card-hover-' . sanitize_html_class( $hover_mode );
		$classes[] = 'cux-card-contrast-' . sanitize_html_class( $contrast_style );
		$classes[] = 'cux-card-swatch-mode-' . sanitize_html_class( $swatch_mode );

		return $classes;
	}

	/**
	 * 2. Dynamically inject the Hover Image ID (`angle_2` or `second_color`) as the first gallery image ID
	 *    on Shop & Category archives so Flatsome's native "Back Image - Fade In" works on every variable product.
	 *
	 * @param array      $gallery_ids Current gallery attachment IDs.
	 * @param WC_Product $product     Product object.
	 * @return array
	 */
	public static function filter_catalog_hover_gallery_ids( $gallery_ids, $product ): array {
		$gallery_ids = is_array( $gallery_ids ) ? $gallery_ids : array();

		if ( is_admin() || ! is_object( $product ) || ! method_exists( $product, 'is_type' ) || ! $product->is_type( 'variable' ) ) {
			return $gallery_ids;
		}

		$product_id = (int) $product->get_id();

		// Do not alter the main Single Product Page gallery here; this filter is strictly for Shop/Archive cards & Related loops.
		if ( function_exists( 'is_product' ) && is_product() && (int) get_queried_object_id() === $product_id ) {
			return $gallery_ids;
		}

		if ( ! empty( self::$filtering_gallery[ $product_id ] ) ) {
			return $gallery_ids;
		}

		self::$filtering_gallery[ $product_id ] = true;

		try {
			$cache = self::get_or_build_card_cache( $product );
			if ( ! empty( $cache ) ) {
				$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );
				$hover_mode = $config['catalog_hover_mode'] ?? 'angle_2';
				$main_id    = (int) $product->get_image_id();

				$hover_id = ( 'second_color' === $hover_mode )
					? (int) ( $cache['hover_image_second_color'] ?? 0 )
					: (int) ( $cache['hover_image_angle_2'] ?? 0 );

				if ( $hover_id <= 0 ) {
					$hover_id = (int) ( $cache['hover_image_angle_2'] ?? ( $cache['hover_image_second_color'] ?? 0 ) );
				}

				if ( $hover_id > 0 && $hover_id !== $main_id ) {
					array_unshift( $gallery_ids, $hover_id );
					$gallery_ids = array_values( array_unique( array_filter( array_map( 'absint', $gallery_ids ) ) ) );
				}
			}
		} catch ( \Throwable $e ) {
			// Safe fallback.
		}

		unset( self::$filtering_gallery[ $product_id ] );
		return $gallery_ids;
	}

	/**
	 * 3. Render Top-Left Badge ("Award Winning"), Top-Right Spec ("DISC"),
	 *    Style B Overlaid Swatches, and Style C Quick-Add Size Pills inside Flatsome's `.box-image`.
	 */
	public static function render_card_image_overlays(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id = (int) $product->get_id();
		$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		$preset     = $config['catalog_card_preset'] ?? 'specialized_spec';

		$badge_tl = (string) get_post_meta( $product_id, '_cux_badge_tl', true );
		if ( empty( $badge_tl ) && ! empty( $config['badge_tl'] ) ) {
			$badge_tl = (string) $config['badge_tl'];
		}

		$badge_tr = (string) get_post_meta( $product_id, '_cux_badge_tr', true );

		$cache        = $product->is_type( 'variable' ) ? self::get_or_build_card_cache( $product ) : array();
		$colors       = $cache['in_stock_colors'] ?? array();
		$max_swatches = max( 1, (int) ( $config['catalog_max_swatches'] ?? 4 ) );
		$swatch_mode  = $config['visual_swatch_mode'] ?? 'hybrid_auto';
		?>
		<div class="cux-card-overlays" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>" data-base-url="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
			<?php if ( ! empty( $badge_tl ) ) : ?>
				<span class="cux-card-badge-tl"><?php echo esc_html( $badge_tl ); ?></span>
			<?php endif; ?>

			<?php if ( ! empty( $badge_tr ) ) : ?>
				<span class="cux-card-badge-tr"><?php echo esc_html( $badge_tr ); ?></span>
			<?php endif; ?>

			<?php
			// Style B (Minimalist Showroom): Render Swatches overlaid inside the bottom-left corner of `.box-image`.
			if ( 'pinarello_showroom' === $preset && ! empty( $colors ) ) :
				self::render_swatch_dots_markup( $colors, $max_swatches, 'cux-card-swatches-overlaid', $swatch_mode );
			endif;

			// Style C (Apparel Quick-Add): Render Hover Size Selector Bar inside the bottom of `.box-image`.
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
	 * 4. Render Swatch Circles + Compare Checkbox (for Style A & Style C) and Synced WooCommerce Brand Eyebrow
	 *    above the Product Title (outside Flatsome's `.price-wrapper` so swatches never collide with the price).
	 */
	public static function render_before_card_title_elements(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id   = (int) $product->get_id();
		$config       = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		$preset       = $config['catalog_card_preset'] ?? 'specialized_spec';
		$max_swatches = max( 1, (int) ( $config['catalog_max_swatches'] ?? 4 ) );
		$swatch_mode  = $config['visual_swatch_mode'] ?? 'hybrid_auto';

		$cache  = $product->is_type( 'variable' ) ? self::get_or_build_card_cache( $product ) : array();
		$colors = $cache['in_stock_colors'] ?? array();

		// 4A. For Style A (Technical Spec) & Style C (Quick-Add): Render Swatch Row + Compare Checkbox above title.
		if ( 'pinarello_showroom' !== $preset && ( ! empty( $colors ) || 'specialized_spec' === $preset ) ) :
			$thumb_url = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
			?>
			<div class="cux-card-swatch-compare-row" data-base-url="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
				<div class="cux-card-swatches-left">
					<?php
					if ( ! empty( $colors ) ) {
						self::render_swatch_dots_markup( $colors, $max_swatches, 'cux-card-swatches-inline', $swatch_mode );
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
		endif;

		// 4B. Render Synced Native WooCommerce Brand Eyebrow above the Product Title if assigned.
		$brand = CUX_Inheritance_Engine::resolve_product_brand( $product_id );
		if ( ! empty( $brand['name'] ) ) :
			?>
			<div class="cux-card-brand-eyebrow">
				<?php echo esc_html( $brand['name'] ); ?>
			</div>
			<?php
		endif;
	}

	/**
	 * 5. Render Technical Spec Subtitle ("Shimano Dura-Ace Di2"), Custom Tagline, and Financing Note
	 *    cleanly below the price (`woocommerce_after_shop_loop_item`) so Flatsome's `.price-wrapper` stays untouched.
	 */
	public static function render_after_card_price_meta(): void {
		global $product;
		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id = (int) $product->get_id();
		$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		$preset     = $config['catalog_card_preset'] ?? 'specialized_spec';

		$subtitle  = (string) get_post_meta( $product_id, '_cux_spec_subtitle', true );
		$tagline   = (string) get_post_meta( $product_id, '_cux_tagline', true );
		$financing = (string) get_post_meta( $product_id, '_cux_financing_text', true );

		if ( empty( $subtitle ) && empty( $tagline ) && empty( $financing ) ) {
			return;
		}
		?>
		<div class="cux-card-footer-meta">
			<?php if ( 'pinarello_showroom' === $preset && ! empty( $tagline ) ) : ?>
				<div class="cux-card-tagline"><?php echo esc_html( $tagline ); ?></div>
			<?php elseif ( ! empty( $subtitle ) ) : ?>
				<div class="cux-card-spec-subtitle"><?php echo esc_html( $subtitle ); ?></div>
			<?php endif; ?>

			<?php if ( ! empty( $financing ) ) : ?>
				<div class="cux-card-financing-note"><?php echo esc_html( $financing ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Helper: Output interactive Color Circles or Variation Photo Swatches + "+X more" counter for a Shop Card.
	 *
	 * @param array  $colors        Array of in-stock color metadata from `_cux_product_cache`.
	 * @param int    $max_swatches  Max visible circles before showing "+X".
	 * @param string $wrapper_class CSS class for the container.
	 * @param string $swatch_mode   Global visual swatch mode (`hybrid_auto`, `variation_photo`, `color_circle`).
	 */
	private static function render_swatch_dots_markup(
		array $colors,
		int $max_swatches,
		string $wrapper_class,
		string $swatch_mode = 'hybrid_auto'
	): void {
		$visible   = array_slice( $colors, 0, $max_swatches );
		$remaining = count( $colors ) - count( $visible );
		?>
		<div class="cux-card-swatches <?php echo esc_attr( $wrapper_class ); ?>">
			<?php
			foreach ( $visible as $idx => $color ) :
				$hex_1    = ! empty( $color['hex_1'] ) ? $color['hex_1'] : '#334155';
				$hex_2    = $color['hex_2'] ?? '';
				$split    = $color['split'] ?? 'horizontal';
				$card_img = $color['card_img_url'] ?? '';

				$is_photo_dot = ( 'variation_photo' === $swatch_mode && ! empty( $card_img ) );
				$bg_css       = class_exists( 'CUX_PDP_Renderer' )
					? CUX_PDP_Renderer::compile_swatch_background_css( $hex_1, $hex_2, $split, $is_photo_dot ? $card_img : '' )
					: $hex_1;
				?>
				<button type="button"
					class="cux-card-swatch-dot <?php echo 0 === $idx ? 'is-active' : ''; ?> <?php echo $is_photo_dot ? 'is-photo-swatch' : ''; ?>"
					data-color-slug="<?php echo esc_attr( $color['slug'] ?? '' ); ?>"
					data-color-name="<?php echo esc_attr( $color['name'] ?? '' ); ?>"
					data-img-src="<?php echo esc_url( $card_img ); ?>"
					data-hover-src="<?php echo esc_url( $color['second_angle_url'] ?? '' ); ?>"
					data-deep-link="<?php echo esc_attr( $color['deep_link_param'] ?? '' ); ?>"
					title="<?php echo esc_attr( $color['name'] ?? '' ); ?>"
					aria-label="<?php echo esc_attr( $color['name'] ?? '' ); ?>">
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
	 * Retrieve or auto-build the precomputed cache for a Variable Product on the Shop page.
	 *
	 * @param WC_Product $product Variable product.
	 * @return array
	 */
	private static function get_or_build_card_cache( WC_Product $product ): array {
		$product_id = (int) $product->get_id();
		$cache      = CUX_Cache_Manager::get_product_cache( $product_id );

		if ( empty( $cache ) && class_exists( 'CUX_Stock_Calculator' ) ) {
			CUX_Stock_Calculator::sync_parent_product( $product_id, 'catalog_init' );
			$cache = CUX_Cache_Manager::get_product_cache( $product_id );
		}

		return is_array( $cache ) ? $cache : array();
	}

	/**
	 * Extract in-stock size buttons for Style C (Apparel Quick-Add Catalog Cards).
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
				'var_id'     => $var_id,
				'size_label' => $size_val,
				'color_slug' => $var_color,
				'is_default' => ( $var_color === $winner_slug ),
			);
		}

		return array_slice( $pills, 0, 12 );
	}

	/**
	 * 6. Render the Floating Side-by-Side Compare Drawer at the bottom of Shop / Category pages.
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
