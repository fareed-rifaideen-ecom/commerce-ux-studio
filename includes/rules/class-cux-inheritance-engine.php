<?php
/**
 * 4-Tier Configuration, Brand Sync & Layout Inheritance Engine (v2.3.2 Universal Edition).
 *
 * File Path: includes/rules/class-cux-inheritance-engine.php
 *
 * Priority Order:
 * Tier 1 (Highest): Single Product Overrides (_cux_product_overrides, _cux_product_swatch_mode, _cux_pdp_gallery_isolation)
 * Tier 2:           Conditional Rule Engine Match ({prefix}cux_rules_presets where rule_type = 'rule')
 * Tier 3:           Category / Tag Taxonomy Override (_cux_cat_card_preset, _cux_cat_fulfilment, _cux_tag_atmosphere)
 * Tier 4 (Base):    Global Store Settings (cux_global_settings)
 *
 * @package Commerce_UX_Studio
 * @author  Fareed M. Rifaideen <https://fareed-rifaideen.netlify.app/>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Inheritance_Engine {

	/**
	 * Runtime static memory cache per request.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static $resolved_cache = array();

	/**
	 * Cached global settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $global_settings = null;

	/**
	 * Runtime cache for resolved product brand data.
	 *
	 * @var array<int, array<string, string>>
	 */
	private static $brand_cache = array();

	/**
	 * Initialize service (called by CUX_Loader).
	 */
	public static function init(): void {
		// Ready for 4-tier configuration resolution.
	}

	/**
	 * Get all Global Store Settings merged with safe v2.3.2 defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_global_settings(): array {
		if ( null !== self::$global_settings ) {
			return self::$global_settings;
		}

		$defaults = array(
			// Tab 1: Store Look & Feel + Multi-Option UI/UX Selectors.
			'catalog_card_preset'         => 'specialized_spec',
			'catalog_hover_mode'          => 'angle_2',
			'oos_swatch_behavior'         => 'clickable_preview',
			'cross_attr_oos_mode'         => 'smart_switch',      // smart_switch | strict_lock
			'pdp_gallery_isolation'       => 'strict_variation',  // strict_variation | colorway_group
			'catalog_max_swatches'        => 4,
			'mobile_gallery_layout'       => 'floating_right',
			'show_slide_counter'          => 'yes',
			'brand_sync_display'          => 'option_1a',         // option_1a | option_1b | option_1c
			'pdp_header_meta_mode'        => 'option_2a',         // option_2a | option_2b
			'swatch_active_style'         => 'option_4a',         // option_4a | option_4b
			'visual_swatch_mode'          => 'smart_hybrid',      // smart_hybrid | variation_image | color_circle | text_box
			'sticky_desktop_gallery'      => 'yes',               // yes | no
			'attribute_display_map'       => array(),

			// Tab 2: Smart Stock & Gallery Autopilot.
			'stock_calc_mode'             => 'grouped_color',
			'auto_copy_color_gallery'     => 'yes',
			'primary_visual_attr'         => 'pa_color',
			'secondary_visual_attrs'      => array( 'pa_groupset', 'pa_wheelset', 'pa_spec' ),
			'dimensional_attrs'           => array( 'pa_size', 'pa_frame-size' ),
			'tie_breaker'                 => 'price_desc',
			'include_backorders'          => 'yes',
			'zero_stock_behavior'         => 'freeze_last',
			'parent_gallery_mode'         => 'winner_only',
			'auto_select_winner'          => 'yes',

			// Tab 3: Delivery Box, Shipping Sync, Size Guide & Cross-Sells.
			'enable_delivery_block'       => 'yes',
			'wc_shipping_sync_mode'       => 'auto_wc',
			'delivery_layout_style'       => 'option_6a',         // option_6a | option_6b | option_6c
			'delivery_heading_label'      => 'Delivery Method',
			'home_card_title'             => 'Standard Delivery',
			'pickup_card_title'           => 'In-Store Pickup',
			'pickup_card_subtitle'        => 'Available for store pickup',
			'default_home_lead'           => '4-7 days',
			'default_home_cost'           => '',
			'default_pickup_lead'         => '6-10 days',
			'default_low_stock_limit'     => 2,
			'default_size_guide'          => '',
			'enable_complete_ride'        => 'yes',
			'cross_sell_heading'          => 'Complete Your Ride',
			'shipping_method_meta'        => array(),

			// Developer & SEO Schema Options.
			'enable_product_group_schema' => 'yes',
			'enable_deep_link_ssr'        => 'yes',
			'trigger_on_order'            => 'yes',
			'trigger_on_admin_save'       => 'yes',
			'trigger_on_csv_import'       => 'yes',
			'trigger_on_rest_webhook'     => 'yes',
		);

		$saved = get_option( 'cux_global_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		// Bridge any legacy keys saved in `cux_studio_settings` so settings never get out of sync.
		$legacy = get_option( 'cux_studio_settings', array() );
		if ( is_array( $legacy ) && ! empty( $legacy ) ) {
			if ( empty( $saved['catalog_card_preset'] ) && ! empty( $legacy['card_preset'] ) ) {
				$saved['catalog_card_preset'] = $legacy['card_preset'];
			}
			if ( empty( $saved['catalog_hover_mode'] ) && ! empty( $legacy['shop_hover_mode'] ) ) {
				$saved['catalog_hover_mode'] = $legacy['shop_hover_mode'];
			}
			if ( empty( $saved['oos_swatch_behavior'] ) && ! empty( $legacy['oos_swatch_mode'] ) ) {
				$saved['oos_swatch_behavior'] = $legacy['oos_swatch_mode'];
			}
		}

		self::$global_settings = wp_parse_args( $saved, $defaults );
		return self::$global_settings;
	}

	/**
	 * Flush runtime static cache when settings are updated.
	 */
	public static function flush_runtime_cache(): void {
		self::$global_settings = null;
		self::$resolved_cache  = array();
		self::$brand_cache     = array();
	}

	/**
	 * Resolve effective configuration for a specific Product ID across all 4 tiers.
	 *
	 * @param int $product_id Parent product ID.
	 * @return array<string, mixed> Resolved configuration array.
	 */
	public static function resolve_product_config( int $product_id ): array {
		$product_id = (int) $product_id;
		if ( isset( self::$resolved_cache[ $product_id ] ) ) {
			return self::$resolved_cache[ $product_id ];
		}

		// Start with Tier 4: Global Store Defaults.
		$config = self::get_global_settings();

		if ( $product_id <= 0 ) {
			return $config;
		}

		// Layer Tier 3: Category & Tag Taxonomy Overrides.
		$taxonomy_overrides = self::get_taxonomy_overrides( $product_id );
		$config             = self::merge_non_empty( $config, $taxonomy_overrides );

		// Layer Tier 2: Conditional Rules Engine Matches.
		$rule_overrides = self::get_matching_rule_overrides( $product_id );
		$config         = self::merge_non_empty( $config, $rule_overrides );

		// Layer Tier 1 (Highest): Single Product Metabox Overrides.
		$product_overrides = get_post_meta( $product_id, '_cux_product_overrides', true );
		if ( is_array( $product_overrides ) && ! empty( $product_overrides ) ) {
			$config = self::merge_non_empty( $config, $product_overrides );
		}

		$prod_swatch_mode = (string) get_post_meta( $product_id, '_cux_product_swatch_mode', true );
		if ( '' !== $prod_swatch_mode && 'inherit' !== $prod_swatch_mode ) {
			$config['visual_swatch_mode'] = sanitize_key( $prod_swatch_mode );
		}

		$prod_gal_iso = (string) get_post_meta( $product_id, '_cux_pdp_gallery_isolation', true );
		if ( '' !== $prod_gal_iso && 'inherit' !== $prod_gal_iso ) {
			$config['pdp_gallery_isolation'] = sanitize_key( $prod_gal_iso );
		}

		self::$resolved_cache[ $product_id ] = $config;
		return $config;
	}

	/**
	 * Resolve Native WooCommerce Brand (`product_brand` or fallback brand taxonomies) for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array{name: string, slug: string, url: string, logo_url: string, tagline: string}
	 */
	public static function resolve_product_brand( int $product_id ): array {
		$product_id = (int) $product_id;
		if ( isset( self::$brand_cache[ $product_id ] ) ) {
			return self::$brand_cache[ $product_id ];
		}

		$custom_tagline = $product_id > 0 ? trim( (string) get_post_meta( $product_id, '_cux_tagline', true ) ) : '';
		if ( '' === $custom_tagline && $product_id > 0 ) {
			$custom_tagline = trim( (string) get_post_meta( $product_id, '_cux_brand_tagline', true ) );
		}

		$brand_data = array(
			'name'     => '',
			'slug'     => '',
			'url'      => '',
			'logo_url' => '',
			'tagline'  => $custom_tagline,
		);

		if ( $product_id <= 0 ) {
			return $brand_data;
		}

		$taxonomies = array( 'product_brand', 'pa_brand', 'pwb-brand', 'yith_product_brand' );
		foreach ( $taxonomies as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}

			$terms = wp_get_post_terms( $product_id, $tax );
			if ( ! is_wp_error( $terms ) && ! empty( $terms[0] ) && is_object( $terms[0] ) ) {
				$term      = $terms[0];
				$term_link = get_term_link( $term );
				$thumb_id  = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
				if ( $thumb_id <= 0 ) {
					$thumb_id = (int) get_term_meta( $term->term_id, 'brand_image_id', true );
				}
				$logo_url = $thumb_id > 0 ? (string) wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';

				$brand_data = array(
					'name'     => (string) $term->name,
					'slug'     => (string) $term->slug,
					'url'      => ! is_wp_error( $term_link ) ? (string) $term_link : '',
					'logo_url' => $logo_url,
					'tagline'  => '' !== $custom_tagline ? $custom_tagline : (string) $term->name,
				);
				break;
			}
		}

		self::$brand_cache[ $product_id ] = $brand_data;
		return $brand_data;
	}

	/**
	 * Normalize any Size Guide input (full `[block id="..."]` shortcode, UX Block slug, or post ID)
	 * into a clean identifier and rendered shortcode for the Size Guide Lightbox Modal.
	 *
	 * @param int $product_id Product ID.
	 * @return array{slug: string, id_or_slug: string, shortcode: string}
	 */
	public static function resolve_size_guide_block( int $product_id ): array {
		$product_id = (int) $product_id;
		$raw        = '';

		if ( $product_id > 0 ) {
			$raw = trim( (string) get_post_meta( $product_id, '_cux_size_guide_id', true ) );
			if ( '' === $raw ) {
				$raw = trim( (string) get_post_meta( $product_id, '_cux_size_guide_block', true ) );
			}
			if ( '' === $raw ) {
				$raw = trim( (string) get_post_meta( $product_id, '_cux_size_guide', true ) );
			}
		}

		if ( '' === $raw ) {
			$config = self::resolve_product_config( $product_id );
			$raw    = trim( (string) ( $config['size_guide_id'] ?? ( $config['default_size_guide'] ?? '' ) ) );
		}

		if ( '' === $raw ) {
			return array(
				'slug'       => '',
				'id_or_slug' => '',
				'shortcode'  => '',
			);
		}

		// Case 1: Admin pasted a full or partial Flatsome shortcode like [block id="specialized-helmet-sizing-chart"].
		if ( preg_match( '/id=["\']?([^"\'\]]+)["\']?/i', $raw, $matches ) ) {
			$extracted = sanitize_text_field( trim( $matches[1] ) );
			return array(
				'slug'       => $extracted,
				'id_or_slug' => $extracted,
				'shortcode'  => '[block id="' . esc_attr( $extracted ) . '"]',
			);
		}

		// Case 2: Admin entered another full shortcode.
		if ( 0 === strpos( $raw, '[' ) && substr( $raw, -1 ) === ']' ) {
			$slug_key = sanitize_title( $raw );
			return array(
				'slug'       => $slug_key,
				'id_or_slug' => $slug_key,
				'shortcode'  => $raw,
			);
		}

		// Case 3: Admin entered a clean UX Block slug or numeric ID.
		$clean_slug = sanitize_text_field( $raw );
		return array(
			'slug'       => $clean_slug,
			'id_or_slug' => $clean_slug,
			'shortcode'  => '[block id="' . esc_attr( $clean_slug ) . '"]',
		);
	}

	/**
	 * Get Tier 3 Category and Tag overrides for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	private static function get_taxonomy_overrides( int $product_id ): array {
		$overrides = array();

		// Product Categories.
		$cat_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $cat_ids ) && ! empty( $cat_ids ) ) {
			foreach ( $cat_ids as $cat_id ) {
				$preset_slug = get_term_meta( $cat_id, '_cux_cat_card_preset', true );
				if ( ! empty( $preset_slug ) && 'inherit' !== $preset_slug ) {
					$overrides['catalog_card_preset'] = $preset_slug;
				}

				$hover_mode = get_term_meta( $cat_id, '_cux_cat_hover_mode', true );
				if ( ! empty( $hover_mode ) && 'inherit' !== $hover_mode ) {
					$overrides['catalog_hover_mode'] = $hover_mode;
				}

				$size_guide = (string) get_term_meta( $cat_id, '_cux_cat_size_guide_id', true );
				if ( '' !== trim( $size_guide ) ) {
					$overrides['size_guide_id'] = $size_guide;
				}

				$fulfilment = get_term_meta( $cat_id, '_cux_cat_fulfilment', true );
				if ( is_array( $fulfilment ) ) {
					$overrides = self::merge_non_empty( $overrides, $fulfilment );
				}
			}
		}

		// Product Tags (Badges, Pre-Order rules, Campaign Atmosphere).
		$tag_ids = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $tag_ids ) && ! empty( $tag_ids ) ) {
			foreach ( $tag_ids as $tag_id ) {
				$tag_badge = get_term_meta( $tag_id, '_cux_tag_badge_tl', true );
				if ( ! empty( $tag_badge ) ) {
					$overrides['badge_tl'] = $tag_badge;
				}

				$atmosphere = get_term_meta( $tag_id, '_cux_tag_atmosphere', true );
				if ( is_array( $atmosphere ) ) {
					$overrides = self::merge_non_empty( $overrides, $atmosphere );
				}
			}
		}

		return $overrides;
	}

	/**
	 * Evaluate Tier 2 Conditional Rules stored in {prefix}cux_rules_presets.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	private static function get_matching_rule_overrides( int $product_id ): array {
		global $wpdb;
		$rules_table = $wpdb->prefix . 'cux_rules_presets';

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rules_table ) );
		if ( $table_exists !== $rules_table ) {
			return array();
		}

		$rules = $wpdb->get_results(
			"SELECT conditions_json, settings_json FROM {$rules_table}
			 WHERE rule_type = 'rule' AND status = 'active'
			 ORDER BY priority ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( empty( $rules ) ) {
			return array();
		}

		$matched_settings = array();
		foreach ( $rules as $rule ) {
			$conditions = json_decode( $rule['conditions_json'] ?? '{}', true );
			$settings   = json_decode( $rule['settings_json'] ?? '{}', true );

			if ( is_array( $conditions ) && is_array( $settings ) && self::evaluate_conditions( $product_id, $conditions ) ) {
				$matched_settings = self::merge_non_empty( $matched_settings, $settings );
			}
		}

		return $matched_settings;
	}

	/**
	 * Evaluate a rule's condition group against a product.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $conditions Condition group definition.
	 * @return bool
	 */
	private static function evaluate_conditions( int $product_id, array $conditions ): bool {
		if ( empty( $conditions['rules'] ) || ! is_array( $conditions['rules'] ) ) {
			return false;
		}

		$match_type = $conditions['match'] ?? 'all';
		$results    = array();

		foreach ( $conditions['rules'] as $clause ) {
			$field    = $clause['field'] ?? '';
			$operator = $clause['operator'] ?? 'equals';
			$value    = $clause['value'] ?? '';
			$passed   = false;

			switch ( $field ) {
				case 'product_cat':
					$passed = has_term( $value, 'product_cat', $product_id );
					if ( 'not_equals' === $operator ) {
						$passed = ! $passed;
					}
					break;

				case 'product_tag':
					$passed = has_term( $value, 'product_tag', $product_id );
					if ( 'not_equals' === $operator ) {
						$passed = ! $passed;
					}
					break;

				case 'price':
					$price  = (float) get_post_meta( $product_id, '_price', true );
					$target = (float) $value;
					if ( 'greater_than' === $operator ) {
						$passed = $price > $target;
					} elseif ( 'less_than' === $operator ) {
						$passed = $price < $target;
					} else {
						$passed = abs( $price - $target ) < 0.01;
					}
					break;

				case 'stock_status':
					$status = get_post_meta( $product_id, '_stock_status', true );
					$passed = ( $status === $value );
					break;
			}

			$results[] = $passed;
		}

		if ( 'any' === $match_type ) {
			return in_array( true, $results, true );
		}

		return ! in_array( false, $results, true );
	}

	/**
	 * Merge override values only when they are not empty or set to 'inherit'.
	 *
	 * @param array<string, mixed> $base      Base array.
	 * @param array<string, mixed> $overrides Override array.
	 * @return array<string, mixed>
	 */
	private static function merge_non_empty( array $base, array $overrides ): array {
		foreach ( $overrides as $key => $value ) {
			if ( '' !== $value && null !== $value && 'inherit' !== $value ) {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}
}
