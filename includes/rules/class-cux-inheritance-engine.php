<?php
/**
 * 4-Tier Settings Inheritance Engine & Universal Brand / Size Guide Resolver (v2.3.0)
 *
 * File Path: includes/rules/class-cux-inheritance-engine.php
 *
 * @package Commerce_UX_Studio
 * @author  Fareed M. Rifaideen <https://fareed-rifaideen.netlify.app/>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Inheritance_Engine {

	/**
	 * In-memory runtime cache.
	 *
	 * @var array<string, mixed>
	 */
	private static $runtime_cache = array();

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		// Ready for rule evaluation.
	}

	/**
	 * Return merged global settings with v2.3 defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_global_settings(): array {
		if ( isset( self::$runtime_cache['global_settings'] ) && is_array( self::$runtime_cache['global_settings'] ) ) {
			return self::$runtime_cache['global_settings'];
		}

		$defaults = array(
			'catalog_card_preset'         => 'specialized_spec',
			'catalog_hover_mode'          => 'angle_2',
			'oos_swatch_behavior'         => 'clickable_preview',
			'catalog_max_swatches'        => 4,
			'mobile_gallery_layout'       => 'floating_right',
			'cross_attr_oos_mode'         => 'smart_switch',
			'pdp_gallery_isolation'       => 'strict_variation',
			'visual_swatch_mode'          => 'smart_hybrid',
			'swatch_active_style'         => 'option_4a',
			'brand_sync_display'          => 'option_1a',
			'pdp_header_meta_mode'        => 'option_2a',
			'sticky_desktop_gallery'      => 'yes',
			'stock_calc_mode'             => 'grouped_color',
			'auto_copy_color_gallery'     => 'yes',
			'parent_gallery_mode'         => 'winner_only',
			'tie_breaker'                 => 'price_desc',
			'enable_delivery_block'       => 'yes',
			'delivery_layout_style'       => 'option_6a',
			'wc_shipping_sync_mode'       => 'auto_wc',
			'delivery_heading_label'      => 'Delivery Method',
			'default_home_lead'           => '4-7 days',
			'default_pickup_lead'         => '6-10 days',
			'home_card_title'             => 'Standard Delivery',
			'pickup_card_title'           => 'In-Store Pickup',
			'default_size_guide'          => '',
			'enable_complete_ride'        => 'yes',
			'enable_product_group_schema' => 'yes',
			'enable_deep_link_ssr'        => 'yes',
			'attribute_display_map'       => array(),
			'shipping_method_meta'        => array(),
		);

		$saved = get_option( 'cux_global_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		// Backward compatibility with legacy option key if present.
		$legacy = get_option( 'cux_studio_settings', array() );
		if ( is_array( $legacy ) && ! empty( $legacy ) ) {
			if ( empty( $saved['catalog_card_preset'] ) && ! empty( $legacy['card_preset'] ) ) {
				$saved['catalog_card_preset'] = $legacy['card_preset'];
			}
		}

		$merged = array_merge( $defaults, $saved );
		self::$runtime_cache['global_settings'] = $merged;

		return $merged;
	}

	/**
	 * Flush in-memory runtime settings cache.
	 */
	public static function flush_runtime_cache(): void {
		self::$runtime_cache = array();
	}

	/**
	 * Resolve effective configuration for a specific product (Product -> Category -> Global).
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public static function resolve_product_config( int $product_id ): array {
		$cache_key = 'prod_' . $product_id;
		if ( isset( self::$runtime_cache[ $cache_key ] ) && is_array( self::$runtime_cache[ $cache_key ] ) ) {
			return self::$runtime_cache[ $cache_key ];
		}

		$config = self::get_global_settings();

		if ( $product_id > 0 ) {
			// Category-level overrides if configured.
			$terms = get_the_terms( $product_id, 'product_cat' );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$cat_preset = get_term_meta( $term->term_id, '_cux_cat_card_preset', true );
					if ( ! empty( $cat_preset ) && 'inherit' !== $cat_preset ) {
						$config['catalog_card_preset'] = sanitize_key( (string) $cat_preset );
					}
					$cat_hover = get_term_meta( $term->term_id, '_cux_cat_hover_mode', true );
					if ( ! empty( $cat_hover ) && 'inherit' !== $cat_hover ) {
						$config['catalog_hover_mode'] = sanitize_key( (string) $cat_hover );
					}
				}
			}

			// Product-level overrides.
			$prod_swatch_mode = get_post_meta( $product_id, '_cux_product_swatch_mode', true );
			if ( ! empty( $prod_swatch_mode ) && 'inherit' !== $prod_swatch_mode ) {
				$config['visual_swatch_mode'] = sanitize_key( (string) $prod_swatch_mode );
			}

			$prod_gal_iso = get_post_meta( $product_id, '_cux_pdp_gallery_isolation', true );
			if ( ! empty( $prod_gal_iso ) && 'inherit' !== $prod_gal_iso ) {
				$config['pdp_gallery_isolation'] = sanitize_key( (string) $prod_gal_iso );
			}
		}

		self::$runtime_cache[ $cache_key ] = $config;
		return $config;
	}

	/**
	 * Resolve native WooCommerce Brand (`product_brand`) or fallback tagline for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, string>
	 */
	public static function resolve_product_brand( int $product_id ): array {
		$result = array(
			'name'     => '',
			'url'      => '',
			'logo_url' => '',
			'tagline'  => '',
		);

		if ( $product_id <= 0 ) {
			return $result;
		}

		$custom_tagline = (string) get_post_meta( $product_id, '_cux_brand_tagline', true );
		$result['tagline'] = trim( $custom_tagline );

		$taxonomies = array( 'product_brand', 'pwb-brand', 'yith_product_brand' );
		foreach ( $taxonomies as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$terms = wp_get_post_terms( $product_id, $tax );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) && is_object( $terms[0] ) ) {
					$brand_term     = $terms[0];
					$result['name'] = (string) $brand_term->name;
					$term_link      = get_term_link( $brand_term );
					if ( ! is_wp_error( $term_link ) ) {
						$result['url'] = (string) $term_link;
					}
					$thumb_id = (int) get_term_meta( $brand_term->term_id, 'thumbnail_id', true );
					if ( $thumb_id > 0 ) {
						$logo = wp_get_attachment_image_url( $thumb_id, 'medium' );
						if ( $logo ) {
							$result['logo_url'] = (string) $logo;
						}
					}
					break;
				}
			}
		}

		if ( '' === $result['tagline'] && '' !== $result['name'] ) {
			$result['tagline'] = $result['name'];
		}

		return $result;
	}

	/**
	 * Resolve Size Guide UX Block shortcode or slug from Product -> Category -> Global setting.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, string>
	 */
	public static function resolve_size_guide_block( int $product_id ): array {
		$raw = '';
		if ( $product_id > 0 ) {
			$raw = trim( (string) get_post_meta( $product_id, '_cux_size_guide_block', true ) );
			if ( '' === $raw ) {
				$raw = trim( (string) get_post_meta( $product_id, '_cux_size_guide', true ) );
			}
		}

		if ( '' === $raw && $product_id > 0 ) {
			$terms = get_the_terms( $product_id, 'product_cat' );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$cat_guide = trim( (string) get_term_meta( $term->term_id, '_cux_cat_size_guide', true ) );
					if ( '' !== $cat_guide ) {
						$raw = $cat_guide;
						break;
					}
				}
			}
		}

		if ( '' === $raw ) {
			$global = self::get_global_settings();
			$raw    = trim( (string) ( $global['default_size_guide'] ?? '' ) );
		}

		if ( '' === $raw ) {
			return array(
				'id_or_slug' => '',
				'shortcode'  => '',
			);
		}

		// Extract ID/slug if user pasted full Flatsome shortcode like [block id="my-size-guide"].
		$slug = $raw;
		if ( preg_match( '/id=["\']([^"\']+)["\']/i', $raw, $matches ) ) {
			$slug = trim( $matches[1] );
		} else {
			$slug = trim( str_replace( array( '[block', ']', '"', "'" ), '', $raw ) );
		}

		return array(
			'id_or_slug' => $slug,
			'shortcode'  => '[block id="' . esc_attr( $slug ) . '"]',
		);
	}
}
