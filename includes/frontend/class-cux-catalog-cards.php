<?php
/**
 * Flatsome Shop & Category Card Renderer (Style A, B, C + Hover Image & Brand Sync - v2.3.0)
 *
 * File Path: includes/frontend/class-cux-catalog-cards.php
 *
 * @package Commerce_UX_Studio
 * @author  Fareed M. Rifaideen <https://fareed-rifaideen.netlify.app/>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Catalog_Cards {

	/**
	 * Recursion guard for catalog gallery hover filter.
	 *
	 * @var bool
	 */
	private static $in_hover_filter = false;

	/**
	 * Register catalog hooks.
	 */
	public static function init(): void {
		// Inject hover image ID for Flatsome's "Back Image - Fade In" on Shop/Category archives.
		add_filter( 'woocommerce_product_get_gallery_image_ids', array( __CLASS__, 'inject_catalog_hover_gallery_id' ), 20, 2 );

		// Render corner badges & Style B overlaid swatches inside Flatsome's .box-image container.
		add_action( 'flatsome_woocommerce_shop_loop_images', array( __CLASS__, 'render_card_image_overlays' ), 25 );

		// Render Brand, Spec Subtitle, Swatch Row (Style A & C) in their own dedicated full-width block below price.
		add_action( 'woocommerce_after_shop_loop_item', array( __CLASS__, 'render_card_footer_block' ), 15 );

		// Floating Compare Bar in footer on shop/category pages.
		add_action( 'wp_footer', array( __CLASS__, 'render_floating_compare_drawer' ), 35 );
	}

	/**
	 * Ensure Flatsome's "Back Image - Fade In" always has a valid hover image ID on variable products.
	 *
	 * @param array      $gallery_ids Existing gallery IDs.
	 * @param WC_Product $product     Product object.
	 * @return array
	 */
	public static function inject_catalog_hover_gallery_id( $gallery_ids, $product ) {
		if ( self::$in_hover_filter || is_admin() || ( function_exists( 'is_product' ) && is_product() ) ) {
			return is_array( $gallery_ids ) ? $gallery_ids : array();
		}

		if ( ! ( $product instanceof WC_Product ) || ! $product->is_type( 'variable' ) ) {
			return is_array( $gallery_ids ) ? $gallery_ids : array();
		}

		$gallery_ids = is_array( $gallery_ids ) ? array_values( array_filter( array_map( 'absint', $gallery_ids ) ) ) : array();
		if ( ! empty( $gallery_ids ) ) {
			return $gallery_ids;
		}

		self::$in_hover_filter = true;
		$hover_id              = 0;

		try {
			$main_id  = (int) $product->get_image_id( 'edit' );
			$children = $product->get_children();

			foreach ( $children as $child_id ) {
				$extra_raw = (string) get_post_meta( (int) $child_id, '_product_image_gallery', true );
				if ( '' === $extra_raw ) {
					$extra_raw = (string) get_post_meta( (int) $child_id, '_wc_additional_variation_images', true );
				}
				if ( '' !== $extra_raw ) {
					$parts = array_filter( array_map( 'absint', explode( ',', $extra_raw ) ) );
					foreach ( $parts as $pid ) {
						if ( $pid > 0 && $pid !== $main_id ) {
							$hover_id = $pid;
							break 2;
						}
					}
				}
			}

			if ( $hover_id <= 0 ) {
				foreach ( $children as $child_id ) {
					$var_img = (int) get_post_thumbnail_id( (int) $child_id );
					if ( $var_img > 0 && $var_img !== $main_id ) {
						$hover_id = $var_img;
						break;
					}
				}
			}
		} catch ( \Throwable $e ) {
			$hover_id = 0;
		}

		self::$in_hover_filter = false;

		if ( $hover_id > 0 ) {
			return array( $hover_id );
		}

		return $gallery_ids;
	}

	/**
	 * Render Top-Left / Top-Right Badges and Style B Overlaid Swatches inside Flatsome's `.box-image`.
	 */
	public static function render_card_image_overlays(): void {
		global $product;
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		$product_id  = $product->get_id();
		$config      = ( class_exists( 'CUX_Inheritance_Engine' ) && method_exists( 'CUX_Inheritance_Engine', 'resolve_product_config' ) )
			? CUX_Inheritance_Engine::resolve_product_config( $product_id )
			: array();
		$preset      = $config['catalog_card_preset'] ?? 'specialized_spec';
		$badge_left  = trim( (string) get_post_meta( $product_id, '_cux_badge_left', true ) );
		$badge_right = trim( (string) get_post_meta( $product_id, '_cux_badge_right', true ) );

		if ( '' === $badge_left && '' === $badge_right && 'pinarello_showroom' !== $preset && 'zara_quick_add' !== $preset ) {
			return;
		}

		echo '<div class="cux-card-overlays" data-base-url="' . esc_url( $product->get_permalink() ) . '">';

		if ( '' !== $badge_left ) {
			echo '<span class="cux-card-badge-left">' . esc_html( $badge_left ) . '</span>';
		}
		if ( '' !== $badge_right ) {
			echo '<span class="cux-card-badge-right">' . esc_html( $badge_right ) . '</span>';
		}

		if ( 'pinarello_showroom' === $preset && $product->is_type( 'variable' ) ) {
			$swatches = self::get_card_color_swatches( $product, (int) ( $config['catalog_max_swatches'] ?? 4 ) );
			if ( ! empty( $swatches['items'] ) ) {
				echo '<div class="cux-card-overlay-swatches">';
				self::render_swatch_dots_list( $swatches );
				echo '</div>';
			}
		}

		if ( 'zara_quick_add' === $preset && $product->is_type( 'variable' ) ) {
			$sizes = self::get_card_quick_add_sizes( $product );
			if ( ! empty( $sizes ) ) {
				echo '<div class="cux-zara-quick-bar">';
				foreach ( $sizes as $sz ) {
					printf(
						'<button type="button" class="cux-zara-size-pill" data-product-id="%1$d" data-variation-id="%2$d">%3$s</button>',
						(int) $product_id,
						(int) $sz['variation_id'],
						esc_html( $sz['label'] )
					);
				}
				echo '</div>';
			}
		}

		echo '</div>';
	}

	/**
	 * Render isolated full-width footer block below price (prevents `﷼ ● 5` price collision).
	 */
	public static function render_card_footer_block(): void {
		global $product;
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		$product_id = $product->get_id();
		$config     = ( class_exists( 'CUX_Inheritance_Engine' ) && method_exists( 'CUX_Inheritance_Engine', 'resolve_product_config' ) )
			? CUX_Inheritance_Engine::resolve_product_config( $product_id )
			: array();
		$preset     = $config['catalog_card_preset'] ?? 'specialized_spec';
		$subtitle   = trim( (string) get_post_meta( $product_id, '_cux_spec_subtitle', true ) );
		$brand      = ( class_exists( 'CUX_Inheritance_Engine' ) && method_exists( 'CUX_Inheritance_Engine', 'resolve_product_brand' ) )
			? CUX_Inheritance_Engine::resolve_product_brand( $product_id )
			: array( 'name' => '', 'tagline' => '' );

		echo '<div class="cux-card-meta-footer cux-preset-' . esc_attr( $preset ) . '" data-base-url="' . esc_url( $product->get_permalink() ) . '">';

		if ( ! empty( $brand['tagline'] ) ) {
			echo '<div class="cux-card-brand-line">' . esc_html( $brand['tagline'] ) . '</div>';
		}

		if ( '' !== $subtitle ) {
			echo '<div class="cux-card-spec-subtitle">' . esc_html( $subtitle ) . '</div>';
		}

		if ( 'pinarello_showroom' !== $preset && $product->is_type( 'variable' ) ) {
			$swatches = self::get_card_color_swatches( $product, (int) ( $config['catalog_max_swatches'] ?? 4 ) );
			if ( ! empty( $swatches['items'] ) ) {
				echo '<div class="cux-card-swatch-row">';
				self::render_swatch_dots_list( $swatches );
				echo '</div>';
			}
		}

		if ( 'specialized_spec' === $preset ) {
			$thumb_url = (string) wp_get_attachment_image_url( (int) $product->get_image_id(), 'woocommerce_thumbnail' );
			?>
			<label class="cux-card-compare-label">
				<input type="checkbox"
					class="cux-compare-checkbox"
					value="<?php echo esc_attr( (string) $product_id ); ?>"
					data-title="<?php echo esc_attr( $product->get_name() ); ?>"
					data-price="<?php echo esc_attr( wp_strip_all_tags( $product->get_price_html() ) ); ?>"
					data-subtitle="<?php echo esc_attr( $subtitle ); ?>"
					data-img="<?php echo esc_url( $thumb_url ); ?>"
					data-url="<?php echo esc_url( $product->get_permalink() ); ?>" />
				<span><?php esc_html_e( 'Compare', 'commerce-ux-studio' ); ?></span>
			</label>
			<?php
		}

		echo '</div>';
	}

	/**
	 * Output swatch dots HTML and `+X` overflow badge (only when overflow > 0).
	 *
	 * @param array $swatches Swatch items and overflow count.
	 */
	private static function render_swatch_dots_list( array $swatches ): void {
		foreach ( $swatches['items'] as $idx => $item ) {
			printf(
				'<button type="button" class="cux-card-swatch-dot %1$s" style="%2$s" data-img-src="%3$s" data-hover-src="%4$s" data-deep-link="%5$s" title="%6$s" aria-label="%6$s"></button>',
				0 === $idx ? 'is-active' : '',
				esc_attr( $item['bg_css'] ),
				esc_url( $item['img_src'] ),
				esc_url( $item['hover_src'] ),
				esc_attr( $item['deep_link'] ),
				esc_attr( $item['label'] )
			);
		}

		if ( ! empty( $swatches['overflow'] ) && (int) $swatches['overflow'] > 0 ) {
			echo '<span class="cux-card-swatch-more">+' . (int) $swatches['overflow'] . '</span>';
		}
	}

	/**
	 * Gather unique in-stock color swatches for a variable product on Shop Cards.
	 *
	 * @param WC_Product $product Variable product.
	 * @param int        $max     Maximum swatches to display before `+X`.
	 * @return array<string, mixed>
	 */
	private static function get_card_color_swatches( WC_Product $product, int $max = 4 ): array {
		$items  = array();
		$seen   = array();
		$max    = max( 2, $max );

		foreach ( $product->get_children() as $child_id ) {
			$var = wc_get_product( $child_id );
			if ( ! ( $var instanceof WC_Product_Variation ) || ! $var->is_in_stock() ) {
				continue;
			}

			$attrs = (array) $var->get_attributes();
			foreach ( $attrs as $tax => $val ) {
				$slug_key = strtolower( (string) $tax );
				if ( false === strpos( $slug_key, 'color' ) && false === strpos( $slug_key, 'colour' ) ) {
					continue;
				}
				$val_str = (string) $val;
				if ( '' === $val_str || isset( $seen[ $val_str ] ) ) {
					continue;
				}
				$seen[ $val_str ] = true;

				$term_obj   = taxonomy_exists( (string) $tax ) ? get_term_by( 'slug', $val_str, (string) $tax ) : null;
				$term_id    = ( $term_obj && ! is_wp_error( $term_obj ) ) ? (int) $term_obj->term_id : 0;
				$term_label = ( $term_obj && ! is_wp_error( $term_obj ) ) ? $term_obj->name : $val_str;

				$img_id    = (int) $var->get_image_id( 'edit' );
				$img_src   = $img_id > 0 ? (string) wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : '';
				$bg_css    = class_exists( 'CUX_PDP_Renderer' ) ? CUX_PDP_Renderer::build_term_swatch_background( $term_id, $term_label ) : 'background-color:#1e293b;';
				$attr_q    = 'attribute_' . sanitize_title( str_replace( 'attribute_', '', (string) $tax ) );

				$items[] = array(
					'label'     => $term_label,
					'bg_css'    => $bg_css,
					'img_src'   => $img_src,
					'hover_src' => $img_src,
					'deep_link' => '?' . rawurlencode( $attr_q ) . '=' . rawurlencode( $val_str ),
				);
			}
		}

		$total    = count( $items );
		$overflow = $total > $max ? ( $total - $max ) : 0;

		return array(
			'items'    => array_slice( $items, 0, $max ),
			'overflow' => $overflow,
		);
	}

	/**
	 * Gather in-stock size variations for Style C (Zara Quick-Add).
	 *
	 * @param WC_Product $product Variable product.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_card_quick_add_sizes( WC_Product $product ): array {
		$sizes = array();
		$seen  = array();

		foreach ( $product->get_children() as $child_id ) {
			$var = wc_get_product( $child_id );
			if ( ! ( $var instanceof WC_Product_Variation ) || ! $var->is_in_stock() ) {
				continue;
			}
			foreach ( (array) $var->get_attributes() as $tax => $val ) {
				$slug_key = strtolower( (string) $tax );
				if ( false !== strpos( $slug_key, 'size' ) && '' !== (string) $val && ! isset( $seen[ (string) $val ] ) ) {
					$seen[ (string) $val ] = true;
					$sizes[]               = array(
						'variation_id' => (int) $child_id,
						'label'        => strtoupper( (string) $val ),
					);
				}
			}
		}

		return array_slice( $sizes, 0, 6 );
	}

	/**
	 * Render floating Compare Bar & Modal in footer on Shop/Category archives.
	 */
	public static function render_floating_compare_drawer(): void {
		if ( ! function_exists( 'is_woocommerce' ) || ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) ) {
			return;
		}
		?>
		<div id="cux-compare-bar" class="cux-compare-bar" style="display:none;">
			<div class="cux-compare-bar-inner">
				<div class="cux-compare-left">
					<strong><?php esc_html_e( 'Compare Products', 'commerce-ux-studio' ); ?> (<span id="cux-compare-count">0</span>/4)</strong>
					<div id="cux-compare-items-list" class="cux-compare-thumbs"></div>
				</div>
				<div class="cux-compare-actions">
					<button type="button" id="cux-compare-clear-btn" class="button is-outline is-small"><?php esc_html_e( 'Clear', 'commerce-ux-studio' ); ?></button>
					<button type="button" id="cux-compare-launch-btn" class="button primary is-small"><?php esc_html_e( 'Compare Now', 'commerce-ux-studio' ); ?></button>
				</div>
			</div>
		</div>

		<div id="cux-compare-modal" class="cux-modal-overlay" style="display:none;">
			<div class="cux-modal-backdrop cux-compare-modal-backdrop"></div>
			<div class="cux-modal-dialog cux-compare-dialog">
				<div class="cux-modal-header">
					<h3><?php esc_html_e( 'Side-by-Side Product Comparison', 'commerce-ux-studio' ); ?></h3>
					<button type="button" class="cux-modal-close cux-compare-modal-close">&times;</button>
				</div>
				<div class="cux-modal-body">
					<div id="cux-compare-modal-grid" class="cux-compare-grid"></div>
				</div>
			</div>
		</div>
		<?php
	}
}
