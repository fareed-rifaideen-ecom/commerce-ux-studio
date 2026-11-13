<?php
/**
 * 4-Tier Configuration & Layout Inheritance Engine.
 *
 * Priority Order:
 * Tier 1 (Highest): Single Product Metabox Override (_cux_product_overrides)
 * Tier 2:           Conditional Rule Engine Match ({prefix}cux_rules_presets where rule_type = 'rule')
 * Tier 3:           Category / Tag Taxonomy Override (_cux_cat_preset_id, _cux_cat_fulfilment, _cux_tag_atmosphere)
 * Tier 4 (Base):    Global Store Settings (cux_global_settings)
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Inheritance_Engine {

	/**
	 * Runtime static memory cache per request.
	 *
	 * @var array
	 */
	private static $resolved_cache = array();

	/**
	 * Cached global settings.
	 *
	 * @var array|null
	 */
	private static $global_settings = null;

	/**
	 * Get all Global Store Settings merged with safe defaults.
	 *
	 * @return array
	 */
	public static function get_global_settings(): array {
		if ( null !== self::$global_settings ) {
			return self::$global_settings;
		}

		$defaults = array(
			'stock_calc_mode'             => 'grouped_color',
			'primary_visual_attr'         => 'pa_color',
			'secondary_visual_attrs'      => array( 'pa_groupset', 'pa_wheelset', 'pa_spec' ),
			'dimensional_attrs'           => array( 'pa_size', 'pa_frame-size' ),
			'tie_breaker'                 => 'price_desc',
			'include_backorders'          => 'yes',
			'zero_stock_behavior'         => 'freeze_last',
			'parent_gallery_mode'         => 'winner_plus_colors',
			'catalog_hover_mode'          => 'angle_2',
			'catalog_card_preset'         => 'specialized_spec',
			'catalog_max_swatches'        => 4,
			'oos_swatch_behavior'         => 'clickable_preview',
			'auto_select_winner'          => 'yes',
			'mobile_gallery_layout'       => 'floating_right',
			'show_slide_counter'          => 'yes',
			'enable_delivery_block'       => 'yes',
			'default_home_lead'           => '4-7 days',
			'default_home_cost'           => '$75.00',
			'default_pickup_lead'         => '6-10 days',
			'default_low_stock_limit'     => 2,
			'enable_complete_ride'        => 'yes',
			'enable_product_group_schema' => 'yes',
			'enable_deep_link_ssr'        => 'yes',
			'trigger_on_order'            => 'yes',
			'trigger_on_admin_save'       => 'yes',
			'trigger_on_csv_import'       => 'yes',
			'trigger_on_odoo_webhook'     => 'yes',
		);

		$saved = get_option( 'cux_global_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
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
	}

	/**
	 * Resolve effective configuration for a specific Product ID across all 4 tiers.
	 *
	 * @param int $product_id Parent product ID.
	 * @return array Resolved configuration array.
	 */
	public static function resolve_product_config( int $product_id ): array {
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

		self::$resolved_cache[ $product_id ] = $config;
		return $config;
	}

	/**
	 * Get Tier 3 Category and Tag overrides for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array
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

				$size_guide = (int) get_term_meta( $cat_id, '_cux_cat_size_guide_id', true );
				if ( $size_guide > 0 ) {
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
	 * @return array
	 */
	private static function get_matching_rule_overrides( int $product_id ): array {
		global $wpdb;
		$rules_table = $wpdb->prefix . 'cux_rules_presets';

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
	 * @param int   $product_id Product ID.
	 * @param array $conditions Condition group definition.
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
					$price = (float) get_post_meta( $product_id, '_price', true );
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
	 * @param array $base      Base array.
	 * @param array $overrides Override array.
	 * @return array
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
