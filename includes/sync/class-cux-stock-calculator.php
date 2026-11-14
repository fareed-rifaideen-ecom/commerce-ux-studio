<?php
/**
 * Automated Stock Winner Calculator & Color-Gallery Auto-Copy Engine.
 *
 * File Path: includes/sync/class-cux-stock-calculator.php
 *
 * Responsibilities:
 * 1. Auto-Detects Color, Secondary Spec, and Size attributes (supports both pa_color and pa_colour).
 * 2. Auto-Copies Main Image & Variation Gallery across all sizes of the same color when uploaded to just one size.
 * 3. Calculates the Highest-Stock Winner (Grouped by Colorway or Single Highest SKU) with deterministic tie-breakers.
 * 4. Updates Parent Main Image, Parent Gallery (strictly excluding out-of-stock colors), and Default Variation Attributes.
 * 5. Compiles the zero-query `_cux_product_cache` JSON payload for Flatsome Shop Cards and Single Product Pages.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Stock_Calculator {

	/**
	 * Guard flag to prevent recursive save loops while updating parent/child metadata.
	 *
	 * @var array<int, bool>
	 */
	private static $syncing_products = array();

	/**
	 * Initialize calculator hooks.
	 */
	public static function init(): void {
		// Ready to be invoked by CUX_Queue_Dispatcher or manual bulk sync.
	}

	/**
	 * Check if a parent product is currently mid-sync.
	 *
	 * @param int $parent_id Parent product ID.
	 * @return bool
	 */
	public static function is_syncing( int $parent_id ): bool {
		return ! empty( self::$syncing_products[ $parent_id ] );
	}

	/**
	 * Run the complete Auto-Copy Gallery & Highest-Stock Winner Sync for a Variable Product.
	 *
	 * @param int    $parent_id Parent variable product ID.
	 * @param string $source    Trigger source (admin, order, csv, odoo, bulk).
	 * @return bool True if synced successfully.
	 */
	public static function sync_parent_product( int $parent_id, string $source = 'admin' ): bool {
		if ( $parent_id <= 0 || self::is_syncing( $parent_id ) ) {
			return false;
		}

		$parent = wc_get_product( $parent_id );
		if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
			return false;
		}

		self::$syncing_products[ $parent_id ] = true;

		try {
			$config       = CUX_Inheritance_Engine::resolve_product_config( $parent_id );
			$children_ids = $parent->get_children();

			if ( empty( $children_ids ) ) {
				unset( self::$syncing_products[ $parent_id ] );
				return false;
			}

			// 1. Auto-detect Primary Visual (Color), Secondary Visual (Spec), and Dimensional (Size) attributes.
			$attr_map     = self::detect_product_attribute_roles( $parent, $config );
			$primary_attr = $attr_map['primary'];
			$visual_attrs = $attr_map['visual_all'];

			// 2. Step A: Auto-Copy Main Image & Variation Gallery across sizes of the same Color/Visual group.
			$auto_copy_enabled = ( $config['auto_copy_color_gallery'] ?? 'yes' ) === 'yes';
			$visual_galleries  = self::collect_and_autocopy_galleries( $parent_id, $children_ids, $primary_attr, $visual_attrs, $auto_copy_enabled );

			// 3. Step B: Evaluate stock across all variations and build candidate list.
			$include_backorders = ( $config['include_backorders'] ?? 'yes' ) === 'yes';
			$low_stock_default  = (int) $parent->get_low_stock_amount();
			if ( $low_stock_default <= 0 ) {
				$low_stock_default = (int) get_option( 'woocommerce_notify_low_stock_amount', (int) ( $config['default_low_stock_limit'] ?? 2 ) );
			}

			$candidates         = array();
			$color_stock_pools  = array();
			$color_first_images = array();
			$availability_map   = array();
			$in_stock_colors    = array();

			foreach ( $children_ids as $var_id ) {
				$variation = wc_get_product( $var_id );
				if ( ! $variation || 'publish' !== $variation->get_status() ) {
					continue;
				}

				$attrs       = $variation->get_variation_attributes( false );
				$color_slug  = self::extract_attribute_value( $attrs, $primary_attr );
				$visual_hash = self::build_visual_hash( $attrs, $visual_attrs );

				// Determine stock status and quantity.
				$is_in_stock = $variation->is_in_stock() || ( $include_backorders && $variation->is_on_backorder() );
				$stock_qty   = $variation->get_manage_stock() ? max( 0, (int) $variation->get_stock_quantity() ) : ( $is_in_stock ? 1 : 0 );

				// Resolve variation images (Main + Extra Angles) from our collected pool.
				$var_gallery = $visual_galleries[ $visual_hash ] ?? ( $visual_galleries[ $color_slug ] ?? array() );
				if ( empty( $var_gallery ) ) {
					$own_main = (int) get_post_meta( $var_id, '_thumbnail_id', true );
					$own_ext  = self::get_variation_extra_image_ids( $var_id );
					$var_gallery = array_values( array_unique( array_filter( array_merge( array( $own_main ), $own_ext ) ) ) );
				}

				$main_img_id = ! empty( $var_gallery[0] ) ? (int) $var_gallery[0] : 0;
				$extra_ids   = count( $var_gallery ) > 1 ? array_slice( $var_gallery, 1 ) : array();

				// Record availability matrix entry for frontend Delivery & Swatch UI (includes both in-stock and out-of-stock).
				$var_low_limit = get_post_meta( $var_id, '_cux_low_stock_limit', true );
				$low_limit     = ( '' !== $var_low_limit ) ? (int) $var_low_limit : $low_stock_default;
				$is_low_stock  = $is_in_stock && $variation->get_manage_stock() && $stock_qty > 0 && $stock_qty <= $low_limit;

				$pickup_status = (string) get_post_meta( $var_id, '_cux_pickup_status', true );
				if ( empty( $pickup_status ) ) {
					$pickup_status = 'ship_to_store';
				}

				$availability_map[ $var_id ] = array(
					'var_id'        => $var_id,
					'attributes'    => $attrs,
					'color_slug'    => $color_slug,
					'visual_hash'   => $visual_hash,
					'is_in_stock'   => $is_in_stock,
					'is_backorder'  => $variation->is_on_backorder(),
					'stock_qty'     => $stock_qty,
					'is_low_stock'  => $is_low_stock,
					'price_html'    => $variation->get_price_html(),
					'display_price' => (float) wc_get_price_to_display( $variation ),
					'regular_price' => (float) wc_get_price_to_display( $variation, array( 'price' => $variation->get_regular_price() ) ),
					'sku'           => (string) $variation->get_sku(),
					'gtin'          => method_exists( $variation, 'get_global_unique_id' ) ? (string) $variation->get_global_unique_id() : '',
					'pickup_status' => $pickup_status,
					'restock_eta'   => (string) get_post_meta( $var_id, '_cux_restock_eta', true ),
					'main_img_id'   => $main_img_id,
					'gallery_ids'   => $var_gallery,
				);

				// Only in-stock variations with a valid image can compete to be the Parent Main Image & Gallery Winner.
				if ( ! $is_in_stock || $main_img_id <= 0 ) {
					continue;
				}

				$color_stock_pools[ $color_slug ] = ( $color_stock_pools[ $color_slug ] ?? 0 ) + $stock_qty;
				if ( ! isset( $color_first_images[ $color_slug ] ) ) {
					$color_first_images[ $color_slug ] = $main_img_id;
				}

				$candidates[] = array(
					'var_id'      => $var_id,
					'color_slug'  => $color_slug,
					'visual_hash' => $visual_hash,
					'stock_qty'   => $stock_qty,
					'price'       => (float) $variation->get_price(),
					'menu_order'  => (int) $variation->get_menu_order(),
					'main_img'    => $main_img_id,
					'extra_imgs'  => $extra_ids,
					'all_imgs'    => $var_gallery,
					'attributes'  => $attrs,
				);
			}

			// If 100% of variations are out of stock (or have no images yet), keep last known parent image and save availability map.
			if ( empty( $candidates ) ) {
				$existing_cache                        = CUX_Cache_Manager::get_product_cache( $parent_id );
				$existing_cache['availability_matrix'] = $availability_map;
				CUX_Cache_Manager::set_product_cache( $parent_id, $existing_cache );
				unset( self::$syncing_products[ $parent_id ] );
				return false;
			}

			// 4. Step C: Sort candidates to find the Winning Variation.
			$calc_mode   = $config['stock_calc_mode'] ?? 'grouped_color';
			$tie_breaker = $config['tie_breaker'] ?? 'price_desc';

			usort(
				$candidates,
				function ( array $a, array $b ) use ( $calc_mode, $tie_breaker, $color_stock_pools ): int {
					$score_a = ( 'grouped_color' === $calc_mode ) ? ( $color_stock_pools[ $a['color_slug'] ] ?? $a['stock_qty'] ) : $a['stock_qty'];
					$score_b = ( 'grouped_color' === $calc_mode ) ? ( $color_stock_pools[ $b['color_slug'] ] ?? $b['stock_qty'] ) : $b['stock_qty'];

					// 1. Primary Stock Score (Color Pool or Single SKU).
					if ( $score_b !== $score_a ) {
						return $score_b <=> $score_a;
					}

					// 2. Individual Variation Stock Quantity (Picks the best-stocked size within the winning color).
					if ( $b['stock_qty'] !== $a['stock_qty'] ) {
						return $b['stock_qty'] <=> $a['stock_qty'];
					}

					// 3. Tie-Breaker: Highest Price vs Menu Order.
					if ( 'price_desc' === $tie_breaker && abs( $b['price'] - $a['price'] ) > 0.01 ) {
						return $b['price'] <=> $a['price'];
					}
					if ( $a['menu_order'] !== $b['menu_order'] ) {
						return $a['menu_order'] <=> $b['menu_order'];
					}

					// 4. Deterministic Final Fallback: Lowest Variation ID.
					return $a['var_id'] <=> $b['var_id'];
				}
			);

			$winner           = $candidates[0];
			$old_cache        = CUX_Cache_Manager::get_product_cache( $parent_id );
			$old_winner_id    = (int) ( $old_cache['winner_var_id'] ?? 0 );
			$old_parent_image = (int) $parent->get_image_id( 'edit' );

			// Determine Hover Image Option A (2nd Angle of Winning Variation) & Option B (Main Image of 2nd In-Stock Color).
			$hover_angle_2      = ! empty( $winner['extra_imgs'][0] ) ? (int) $winner['extra_imgs'][0] : $winner['main_img'];
			$hover_second_color = $hover_angle_2;
			foreach ( $candidates as $cand ) {
				if ( $cand['color_slug'] !== $winner['color_slug'] && $cand['main_img'] !== $winner['main_img'] ) {
					$hover_second_color = (int) $cand['main_img'];
					break;
				}
			}

			// 5. Step D: Update Parent Main Image & Parent Gallery (Unless "Lock Main Image" is checked).
			$hero_locked = 'yes' === (string) get_post_meta( $parent_id, '_cux_hero_lock', true );
			if ( ! $hero_locked ) {
				$parent->set_image_id( $winner['main_img'] );

				$gallery_pool = array();
				$hover_mode   = $config['catalog_hover_mode'] ?? 'angle_2';

				// Place preferred hover image first so Flatsome's native catalog flip shows the chosen hover mode.
				if ( 'second_color' === $hover_mode && $hover_second_color !== $winner['main_img'] ) {
					$gallery_pool[] = $hover_second_color;
				}

				// Add Winning Variation's Extra Angles.
				foreach ( $winner['extra_imgs'] as $ext_id ) {
					$gallery_pool[] = (int) $ext_id;
				}

				// Add Main Images of Other In-Stock Variations (Out-of-stock colors are completely excluded!).
				if ( ( $config['parent_gallery_mode'] ?? 'winner_plus_colors' ) === 'winner_plus_colors' ) {
					foreach ( $candidates as $cand ) {
						if ( $cand['main_img'] !== $winner['main_img'] ) {
							$gallery_pool[] = (int) $cand['main_img'];
						}
					}
				}

				$gallery_pool = array_values( array_unique( array_diff( $gallery_pool, array( $winner['main_img'] ) ) ) );
				$parent->set_gallery_image_ids( $gallery_pool );
			}

			// 6. Step E: Set Default Selected Variation Attributes to the Winner.
			if ( ( $config['auto_select_winner'] ?? 'yes' ) === 'yes' ) {
				$parent->set_default_attributes( $winner['attributes'] );
			}

			$parent->save();

			// 7. Step F: Build In-Stock Color Swatch Metadata for Catalog Cards & PDP.
			$seen_colors = array();
			foreach ( $candidates as $cand ) {
				$c_slug = $cand['color_slug'];
				if ( empty( $c_slug ) || isset( $seen_colors[ $c_slug ] ) ) {
					continue;
				}
				$seen_colors[ $c_slug ] = true;

				$term_info          = self::get_color_term_swatch_data( $primary_attr, $c_slug );
				$card_img_src       = wp_get_attachment_image_src( $cand['main_img'], 'woocommerce_thumbnail' );
				$second_angle_id    = ! empty( $cand['extra_imgs'][0] ) ? $cand['extra_imgs'][0] : $cand['main_img'];
				$second_angle_src   = wp_get_attachment_image_src( $second_angle_id, 'woocommerce_thumbnail' );
				$attr_query_key     = 'attribute_' . ( 0 === strpos( $primary_attr, 'pa_' ) ? $primary_attr : sanitize_title( $primary_attr ) );

				$in_stock_colors[] = array(
					'slug'             => $c_slug,
					'name'             => $term_info['name'],
					'hex_1'            => $term_info['hex_1'],
					'hex_2'            => $term_info['hex_2'],
					'split'            => $term_info['split'],
					'tooltip'          => $term_info['tooltip'],
					'total_stock'      => $color_stock_pools[ $c_slug ] ?? $cand['stock_qty'],
					'main_img_id'      => $cand['main_img'],
					'card_img_url'     => $card_img_src ? $card_img_src[0] : '',
					'second_angle_url' => $second_angle_src ? $second_angle_src[0] : '',
					'deep_link_param'  => '?' . rawurlencode( $attr_query_key ) . '=' . rawurlencode( $c_slug ),
				);
			}

			// 8. Save Precomputed Cache Object on Parent Product.
			$cache_payload = array(
				'winner_var_id'            => $winner['var_id'],
				'winner_color_slug'        => $winner['color_slug'],
				'winner_visual_hash'       => $winner['visual_hash'],
				'winner_main_img_id'       => $winner['main_img'],
				'hover_image_angle_2'      => $hover_angle_2,
				'hover_image_second_color' => $hover_second_color,
				'primary_visual_attr'      => $primary_attr,
				'visual_attrs'             => $visual_attrs,
				'dimensional_attrs'        => $attr_map['dimensional'],
				'in_stock_colors'          => $in_stock_colors,
				'visual_galleries'         => $visual_galleries,
				'availability_matrix'      => $availability_map,
			);

			CUX_Cache_Manager::set_product_cache( $parent_id, $cache_payload );
			CUX_Cache_Manager::purge_product_cache( $parent_id );

			// 9. Log Audit Event if Winner or Main Image Changed.
			if ( $old_winner_id !== (int) $winner['var_id'] || $old_parent_image !== (int) $winner['main_img'] ) {
				CUX_Cache_Manager::log_sync_event(
					$parent_id,
					$source,
					$old_winner_id,
					(int) $winner['var_id'],
					$old_parent_image,
					(int) $winner['main_img'],
					(int) $winner['stock_qty']
				);
			}
		} finally {
			unset( self::$syncing_products[ $parent_id ] );
		}

		return true;
	}

	/**
	 * Auto-Copy Main Image & Extra Gallery Images across variations sharing the same Color/Visual group.
	 *
	 * If a user uploads a main image and/or variation gallery to ONE size of "Black", this method
	 * automatically copies those images to all other sizes of "Black" that don't have images yet.
	 *
	 * @param int    $parent_id         Parent product ID.
	 * @param array  $children_ids      Variation IDs.
	 * @param string $primary_attr      Primary visual attribute slug (e.g. pa_colour).
	 * @param array  $visual_attrs      All visual attribute slugs.
	 * @param bool   $auto_copy_enabled Whether to write missing images to sibling variations.
	 * @return array<string, array<int>> Map of visual_hash => array of attachment IDs (Main image first).
	 */
	private static function collect_and_autocopy_galleries(
		int $parent_id,
		array $children_ids,
		string $primary_attr,
		array $visual_attrs,
		bool $auto_copy_enabled
	): array {
		$visual_galleries = array();
		$color_base_pool  = array();

		// Also include any shared galleries saved in _cux_visual_matrix if present.
		$matrix_meta = get_post_meta( $parent_id, '_cux_visual_matrix', true );
		if ( is_array( $matrix_meta ) ) {
			foreach ( $matrix_meta as $hash => $csv ) {
				$ids = array_values( array_filter( array_map( 'absint', explode( ',', (string) $csv ) ) ) );
				if ( ! empty( $ids ) ) {
					$visual_galleries[ $hash ] = $ids;
					$color_base_pool[ $hash ]  = $ids;
				}
			}
		}

		// Pass 1: Scan all variations to discover uploaded main images & extra gallery images per Color / Visual Hash.
		$variation_records = array();
		foreach ( $children_ids as $var_id ) {
			$variation = wc_get_product( $var_id );
			if ( ! $variation ) {
				continue;
			}

			$attrs       = $variation->get_variation_attributes( false );
			$color_slug  = self::extract_attribute_value( $attrs, $primary_attr );
			$visual_hash = self::build_visual_hash( $attrs, $visual_attrs );

			$own_main  = (int) get_post_meta( $var_id, '_thumbnail_id', true );
			$own_extra = self::get_variation_extra_image_ids( $var_id );

			$variation_records[ $var_id ] = array(
				'color_slug'  => $color_slug,
				'visual_hash' => $visual_hash,
				'own_main'    => $own_main,
				'own_extra'   => $own_extra,
			);

			$combined = array_values( array_unique( array_filter( array_merge( array( $own_main ), $own_extra ) ) ) );
			if ( ! empty( $combined ) ) {
				if ( empty( $visual_galleries[ $visual_hash ] ) || count( $combined ) > count( $visual_galleries[ $visual_hash ] ) ) {
					$visual_galleries[ $visual_hash ] = $combined;
				}
				if ( ! empty( $color_slug ) && ( empty( $color_base_pool[ $color_slug ] ) || count( $combined ) > count( $color_base_pool[ $color_slug ] ) ) ) {
					$color_base_pool[ $color_slug ] = $combined;
				}
			}
		}

		// Pass 2: Auto-Copy discovered Color/Visual galleries to sibling size variations that are missing images.
		if ( $auto_copy_enabled ) {
			foreach ( $variation_records as $var_id => $rec ) {
				$hash       = $rec['visual_hash'];
				$color_slug = $rec['color_slug'];
				$shared     = $visual_galleries[ $hash ] ?? ( $color_base_pool[ $color_slug ] ?? array() );

				if ( empty( $shared ) ) {
					continue;
				}

				$shared_main  = (int) $shared[0];
				$shared_extra = count( $shared ) > 1 ? array_slice( $shared, 1 ) : array();
				$shared_csv   = implode( ',', $shared_extra );

				// If this size variation has no main image, copy the Color's main image to it.
				if ( $rec['own_main'] <= 0 && $shared_main > 0 ) {
					update_post_meta( $var_id, '_thumbnail_id', $shared_main );
				}

				// If this size variation has no extra gallery angles, copy the Color's extra gallery angles to it.
				if ( empty( $rec['own_extra'] ) && ! empty( $shared_csv ) ) {
					update_post_meta( $var_id, '_product_image_gallery', $shared_csv );
					update_post_meta( $var_id, '_wc_additional_variation_images', $shared_csv );
					update_post_meta( $var_id, '_wc_variation_gallery_ids', $shared_csv );
				}
			}
		}

		return $visual_galleries;
	}

	/**
	 * Read extra gallery image IDs from a variation across WooCommerce 11.1+ native, Flatsome, and CUX meta keys.
	 *
	 * @param int $variation_id Variation ID.
	 * @return array<int>
	 */
	public static function get_variation_extra_image_ids( int $variation_id ): array {
		$keys = array(
			'_product_image_gallery',          // Native WooCommerce 11.1+ Variation Gallery.
			'_wc_additional_variation_images', // Flatsome native Variation Gallery.
			'_wc_variation_gallery_ids',       // Commerce UX Studio meta key.
		);

		foreach ( $keys as $meta_key ) {
			$raw = (string) get_post_meta( $variation_id, $meta_key, true );
			if ( ! empty( $raw ) ) {
				$ids = array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
				if ( ! empty( $ids ) ) {
					return $ids;
				}
			}
		}

		return array();
	}

	/**
	 * Auto-detect Primary Visual (Color), Secondary Visual (Groupset/Spec), and Dimensional (Size) attributes on a product.
	 *
	 * @param WC_Product $parent Parent variable product.
	 * @param array      $config Resolved product configuration.
	 * @return array{primary: string, visual_all: array<string>, dimensional: array<string>}
	 */
	public static function detect_product_attribute_roles( WC_Product $parent, array $config ): array {
		$attributes  = $parent->get_attributes();
		$primary     = '';
		$secondary   = array();
		$dimensional = array();

		$color_keywords = array( 'pa_color', 'pa_colour', 'color', 'colour', 'shade', 'finish' );
		$size_keywords  = array( 'pa_size', 'pa_frame-size', 'size', 'frame-size', 'length', 'width' );
		$spec_keywords  = array( 'pa_groupset', 'pa_wheelset', 'pa_spec', 'groupset', 'wheelset', 'spec', 'package' );

		foreach ( $attributes as $attr_key => $attr_obj ) {
			if ( ! is_object( $attr_obj ) || ! $attr_obj->get_variation() ) {
				continue;
			}

			$name       = strtolower( (string) $attr_obj->get_name() );
			$saved_role = '';

			if ( $attr_obj->is_taxonomy() && function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
				$attr_id    = wc_attribute_taxonomy_id_by_name( $name );
				$saved_role = $attr_id ? (string) get_option( "cux_attr_role_{$attr_id}", '' ) : '';
			}

			if ( 'visual_primary' === $saved_role || in_array( $name, $color_keywords, true ) || false !== strpos( $name, 'color' ) || false !== strpos( $name, 'colour' ) ) {
				if ( empty( $primary ) ) {
					$primary = $name;
				} else {
					$secondary[] = $name;
				}
			} elseif ( 'dimensional' === $saved_role || in_array( $name, $size_keywords, true ) || false !== strpos( $name, 'size' ) ) {
				$dimensional[] = $name;
			} elseif ( 'visual_secondary' === $saved_role || in_array( $name, $spec_keywords, true ) ) {
				$secondary[] = $name;
			} else {
				// Default fallback: if we haven't found a primary visual attribute yet, use the first non-size attribute.
				if ( empty( $primary ) ) {
					$primary = $name;
				} else {
					$dimensional[] = $name;
				}
			}
		}

		if ( empty( $primary ) && ! empty( $secondary ) ) {
			$primary = array_shift( $secondary );
		}

		if ( empty( $primary ) ) {
			$primary = $config['primary_visual_attr'] ?? 'pa_color';
		}

		$visual_all = array_values( array_unique( array_filter( array_merge( array( $primary ), $secondary ) ) ) );

		return array(
			'primary'     => $primary,
			'visual_all'  => $visual_all,
			'dimensional' => $dimensional,
		);
	}

	/**
	 * Extract a specific attribute value from a variation's unprefixed attribute array.
	 *
	 * @param array  $attrs     Unprefixed variation attributes.
	 * @param string $attr_name Target attribute name.
	 * @return string
	 */
	private static function extract_attribute_value( array $attrs, string $attr_name ): string {
		$clean = strtolower( $attr_name );
		$no_pa = 0 === strpos( $clean, 'pa_' ) ? substr( $clean, 3 ) : $clean;

		foreach ( $attrs as $k => $v ) {
			$k_low = strtolower( (string) $k );
			if ( $k_low === $clean || $k_low === $no_pa || $k_low === 'pa_' . $no_pa ) {
				return sanitize_title( (string) $v );
			}
		}

		return 'default';
	}

	/**
	 * Build a deduplicated visual hash string (e.g. "gloss-white" or "gloss-white__sram-red-axs").
	 *
	 * @param array $attrs        Variation attributes.
	 * @param array $visual_attrs Visual attribute slugs.
	 * @return string
	 */
	public static function build_visual_hash( array $attrs, array $visual_attrs ): string {
		$parts = array();
		foreach ( $visual_attrs as $v_attr ) {
			$val = self::extract_attribute_value( $attrs, $v_attr );
			if ( '' !== $val && 'default' !== $val ) {
				$parts[] = $val;
			}
		}
		return ! empty( $parts ) ? implode( '__', $parts ) : 'default';
	}

	/**
	 * Retrieve term name, primary hex, secondary hex, split style & tooltip for a Color term.
	 *
	 * @param string $taxonomy   Attribute taxonomy (e.g. pa_colour).
	 * @param string $term_slug  Term slug.
	 * @return array{name: string, hex_1: string, hex_2: string, split: string, tooltip: string}
	 */
	private static function get_color_term_swatch_data( string $taxonomy, string $term_slug ): array {
		$tax_name = 0 === strpos( $taxonomy, 'pa_' ) ? $taxonomy : 'pa_' . $taxonomy;
		$term     = get_term_by( 'slug', $term_slug, $tax_name );

		if ( $term && ! is_wp_error( $term ) ) {
			$hex_1   = (string) get_term_meta( $term->term_id, '_cux_hex_primary', true );
			$hex_2   = (string) get_term_meta( $term->term_id, '_cux_hex_secondary', true );
			$split   = (string) get_term_meta( $term->term_id, '_cux_split_style', true );
			$tooltip = (string) get_term_meta( $term->term_id, '_cux_term_tooltip', true );

			return array(
				'name'    => $term->name,
				'hex_1'   => ! empty( $hex_1 ) ? $hex_1 : self::guess_hex_from_slug( $term_slug ),
				'hex_2'   => $hex_2,
				'split'   => ! empty( $split ) ? $split : 'horizontal',
				'tooltip' => $tooltip,
			);
		}

		return array(
			'name'    => ucwords( str_replace( '-', ' ', $term_slug ) ),
			'hex_1'   => self::guess_hex_from_slug( $term_slug ),
			'hex_2'   => '',
			'split'   => 'horizontal',
			'tooltip' => '',
		);
	}

	/**
	 * Fallback helper so even if a beginner forgets to pick a Hex color for "Black" or "White",
	 * the swatch circle automatically shows a sensible color instead of a blank circle.
	 *
	 * @param string $slug Color slug.
	 * @return string Hex color.
	 */
	private static function guess_hex_from_slug( string $slug ): string {
		$map = array(
			'black'    => '#18181b',
			'white'    => '#f8fafc',
			'red'      => '#dc2626',
			'blue'     => '#2563eb',
			'navy'     => '#1e3a8a',
			'green'    => '#16a34a',
			'olive'    => '#4d7c0f',
			'yellow'   => '#eab308',
			'orange'   => '#ea580c',
			'grey'     => '#64748b',
			'gray'     => '#64748b',
			'silver'   => '#cbd5e1',
			'smoke'    => '#71717a',
			'charcoal' => '#334155',
			'gold'     => '#ca8a04',
			'bronze'   => '#92400e',
			'pink'     => '#db2777',
			'purple'   => '#7c3aed',
			'teal'     => '#0d9488',
			'cyan'     => '#06b6d4',
			'beige'    => '#d6d3d1',
			'sand'     => '#d6d3d1',
			'brown'    => '#78350f',
		);

		foreach ( $map as $word => $hex ) {
			if ( false !== strpos( $slug, $word ) ) {
				return $hex;
			}
		}

		return '#475569';
	}
}
