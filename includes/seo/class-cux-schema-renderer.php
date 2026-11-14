<?php
/**
 * Google Merchant ProductGroup JSON-LD Schema & Server-Side Deep-Link Variant Renderer.
 *
 * File Path: includes/seo/class-cux-schema-renderer.php
 *
 * Responsibilities:
 * 1. Outputs Google `ProductGroup`, `variesBy`, and `hasVariant` structured data in `<head>`
 *    using native WooCommerce 11.3+ GTIN (`_global_unique_id`), Brand (`product_brand`), SKU, Price, and Stock.
 * 2. Server-Side Variant Deep-Linking: When a shopper or Googlebot visits a URL with `?attribute_pa_colour=...`,
 *    pre-selects that color's main image for OpenGraph (`og:image`) and initial server-side rendering.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Schema_Renderer {

	/**
	 * Initialize SEO Schema and Deep-Link SSR hooks.
	 */
	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'render_product_group_json_ld' ), 25 );
		add_filter( 'woocommerce_product_get_image_id', array( __CLASS__, 'filter_deep_link_main_image' ), 20, 2 );
		add_action( 'wp_head', array( __CLASS__, 'render_deep_link_opengraph_image' ), 5 );
	}

	/**
	 * 1. Output Google ProductGroup & hasVariant JSON-LD structured data on Single Variable Product pages.
	 */
	public static function render_product_group_json_ld(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		global $product;
		if ( ! is_object( $product ) ) {
			$product = wc_get_product( get_the_ID() );
		}
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return;
		}

		$product_id = (int) $product->get_id();
		$config     = CUX_Inheritance_Engine::resolve_product_config( $product_id );

		if ( ( $config['enable_product_group_schema'] ?? 'yes' ) !== 'yes' ) {
			return;
		}

		$cache = CUX_Cache_Manager::get_product_cache( $product_id );
		if ( empty( $cache ) && class_exists( 'CUX_Stock_Calculator' ) ) {
			CUX_Stock_Calculator::sync_parent_product( $product_id, 'schema_init' );
			$cache = CUX_Cache_Manager::get_product_cache( $product_id );
		}

		$avail_map = $cache['availability_matrix'] ?? array();
		if ( empty( $avail_map ) ) {
			return;
		}

		$permalink  = get_permalink( $product_id );
		$currency   = get_woocommerce_currency();
		$brand_name = self::resolve_product_brand_name( $product_id );
		$parent_img = wp_get_attachment_image_url( $product->get_image_id(), 'full' );

		// Determine variesBy properties.
		$varies_by    = array();
		$primary_attr = strtolower( (string) ( $cache['primary_visual_attr'] ?? 'pa_color' ) );
		$dim_attrs    = (array) ( $cache['dimensional_attrs'] ?? array() );

		if ( false !== strpos( $primary_attr, 'color' ) || false !== strpos( $primary_attr, 'colour' ) ) {
			$varies_by[] = 'https://schema.org/color';
		}
		if ( ! empty( $dim_attrs ) ) {
			$varies_by[] = 'https://schema.org/size';
		}
		if ( empty( $varies_by ) ) {
			$varies_by[] = 'https://schema.org/color';
		}

		$variants = array();
		foreach ( $avail_map as $var_id => $var_data ) {
			$var_img_id  = (int) ( $var_data['main_img_id'] ?? 0 );
			$var_img_url = $var_img_id > 0 ? wp_get_attachment_image_url( $var_img_id, 'full' ) : $parent_img;

			// Build deep-link URL for this specific variation.
			$query_args = array();
			if ( ! empty( $var_data['attributes'] ) && is_array( $var_data['attributes'] ) ) {
				foreach ( $var_data['attributes'] as $attr_k => $attr_v ) {
					if ( '' !== $attr_v ) {
						$param_key                = 0 === strpos( $attr_k, 'attribute_' ) ? $attr_k : 'attribute_' . sanitize_title( $attr_k );
						$query_args[ $param_key ] = $attr_v;
					}
				}
			}
			$variant_url = ! empty( $query_args ) ? add_query_arg( $query_args, $permalink ) : $permalink;

			$availability_schema = 'https://schema.org/OutOfStock';
			if ( ! empty( $var_data['is_in_stock'] ) ) {
				$availability_schema = ! empty( $var_data['is_backorder'] )
					? 'https://schema.org/BackOrder'
					: 'https://schema.org/InStock';
			}

			$attr_summary = ! empty( $var_data['attributes'] ) ? implode( ' / ', array_filter( $var_data['attributes'] ) ) : (string) $var_id;

			$variant_node = array(
				'@type'       => 'Product',
				'@id'         => $permalink . '#variant-' . $var_id,
				'name'        => $product->get_name() . ' - ' . strtoupper( $attr_summary ),
				'sku'         => ! empty( $var_data['sku'] ) ? $var_data['sku'] : (string) $var_id,
				'image'       => $var_img_url ? $var_img_url : '',
				'color'       => ! empty( $var_data['color_slug'] ) ? ucwords( str_replace( '-', ' ', $var_data['color_slug'] ) ) : '',
				'offers'      => array(
					'@type'         => 'Offer',
					'url'           => $variant_url,
					'priceCurrency' => $currency,
					'price'         => number_format( (float) ( $var_data['display_price'] ?? 0 ), 2, '.', '' ),
					'availability'  => $availability_schema,
					'itemCondition' => 'https://schema.org/NewCondition',
				),
			);

			// Inject Native WooCommerce 9.2+ / 11.3+ GTIN (`_global_unique_id`) if present.
			if ( ! empty( $var_data['gtin'] ) ) {
				$variant_node['gtin'] = (string) $var_data['gtin'];
			}

			$variants[] = $variant_node;
		}

		$schema = array(
			'@context'       => 'https://schema.org/',
			'@type'          => 'ProductGroup',
			'@id'            => $permalink . '#productgroup',
			'name'           => $product->get_name(),
			'description'    => wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ),
			'url'            => $permalink,
			'productGroupID' => $product->get_sku() ? $product->get_sku() : (string) $product_id,
			'image'          => $parent_img ? $parent_img : '',
			'variesBy'       => $varies_by,
			'hasVariant'     => $variants,
		);

		if ( ! empty( $brand_name ) ) {
			$schema['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand_name,
			);
		}
		?>
		<!-- Commerce UX Studio: Google Merchant ProductGroup JSON-LD -->
		<script type="application/ld+json" id="cux-productgroup-schema">
		<?php echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>
		</script>
		<?php
	}

	/**
	 * 2. Server-Side Variant Deep-Linking: When a URL has `?attribute_pa_colour=gloss-white`,
	 *    return that colorway's primary attachment ID on the server so the first byte matches the deep link.
	 *
	 * @param int        $image_id Current image attachment ID.
	 * @param WC_Product $product  Product object.
	 * @return int
	 */
	public static function filter_deep_link_main_image( $image_id, $product ) {
		if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return $image_id;
		}

		if ( ! is_object( $product ) || ! $product->is_type( 'variable' ) ) {
			return $image_id;
		}

		$deep_img_id = self::get_requested_deep_link_image_id( (int) $product->get_id() );
		if ( $deep_img_id > 0 ) {
			return $deep_img_id;
		}

		return $image_id;
	}

	/**
	 * Output OpenGraph `og:image` tag when visiting a deep-linked variation URL (for WhatsApp / Social shares).
	 */
	public static function render_deep_link_opengraph_image(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product_id  = (int) get_the_ID();
		$deep_img_id = self::get_requested_deep_link_image_id( $product_id );
		if ( $deep_img_id <= 0 ) {
			return;
		}

		$img_url = wp_get_attachment_image_url( $deep_img_id, 'full' );
		if ( $img_url ) {
			echo '<meta property="og:image" content="' . esc_url( $img_url ) . '" />' . "\n";
		}
	}

	/**
	 * Inspect `$_GET` for `?attribute_pa_*` parameters and return the matching color's main image ID.
	 *
	 * @param int $product_id Parent product ID.
	 * @return int Matching attachment ID or 0.
	 */
	private static function get_requested_deep_link_image_id( int $product_id ): int {
		if ( empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 0;
		}

		$config = CUX_Inheritance_Engine::resolve_product_config( $product_id );
		if ( ( $config['enable_deep_link_ssr'] ?? 'yes' ) !== 'yes' ) {
			return 0;
		}

		$cache = CUX_Cache_Manager::get_product_cache( $product_id );
		if ( empty( $cache['in_stock_colors'] ) ) {
			return 0;
		}

		foreach ( $_GET as $key => $val ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 0 === strpos( (string) $key, 'attribute_' ) ) {
				$req_slug = sanitize_title( wp_unslash( (string) $val ) );
				foreach ( $cache['in_stock_colors'] as $color_row ) {
					if ( isset( $color_row['slug'] ) && $color_row['slug'] === $req_slug && ! empty( $color_row['main_img_id'] ) ) {
						return (int) $color_row['main_img_id'];
					}
				}
			}
		}

		return 0;
	}

	/**
	 * Resolve Brand Name from Native WooCommerce 9.4+ `product_brand` taxonomy or `pa_brand` attribute.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private static function resolve_product_brand_name( int $product_id ): string {
		$taxonomies = array( 'product_brand', 'pa_brand', 'pwb-brand', 'yith_product_brand' );
		foreach ( $taxonomies as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$terms = wp_get_post_terms( $product_id, $tax, array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $terms ) && ! empty( $terms[0] ) ) {
					return (string) $terms[0];
				}
			}
		}
		return get_bloginfo( 'name' );
	}
}
