<?php
/**
 * Cart, Flatsome Mini-Cart & Checkout Variation Thumbnail Resolver.
 *
 * File Path: includes/frontend/class-cux-cart-thumbnails.php
 *
 * Ensures that when a customer adds any size variation to the cart, the Cart page,
 * Checkout summary, and Flatsome Off-Canvas Mini-Cart always display the exact
 * matching Color/Spec variation thumbnail (even if images were uploaded once per color).
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Cart_Thumbnails {

	/**
	 * Initialize cart and order item thumbnail filters.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_cart_item_thumbnail', array( __CLASS__, 'filter_cart_item_thumbnail' ), 20, 3 );
	}

	/**
	 * Ensure the cart item thumbnail matches the selected variation's color/spec image.
	 *
	 * @param string $thumbnail     Default image HTML.
	 * @param array  $cart_item     Cart item data array.
	 * @param string $cart_item_key Cart item hash key.
	 * @return string
	 */
	public static function filter_cart_item_thumbnail( $thumbnail, $cart_item, $cart_item_key = '' ): string {
		if ( ! is_array( $cart_item ) || empty( $cart_item['variation_id'] ) ) {
			return (string) $thumbnail;
		}

		$variation_id = (int) $cart_item['variation_id'];
		$product_id   = ! empty( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : wp_get_post_parent_id( $variation_id );

		// 1. Check direct variation thumbnail first.
		$image_id = (int) get_post_meta( $variation_id, '_thumbnail_id', true );

		// 2. If empty, resolve from the precomputed parent product cache (inherited by Colorway / Visual Hash).
		if ( $image_id <= 0 && $product_id > 0 && class_exists( 'CUX_Cache_Manager' ) ) {
			$cache = CUX_Cache_Manager::get_product_cache( $product_id );

			if ( ! empty( $cache['availability_matrix'][ $variation_id ]['main_img_id'] ) ) {
				$image_id = (int) $cache['availability_matrix'][ $variation_id ]['main_img_id'];
			} elseif ( ! empty( $cache['in_stock_colors'] ) && ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
				// Fallback match by selected color attribute in cart item.
				foreach ( $cart_item['variation'] as $attr_key => $attr_val ) {
					$slug = sanitize_title( (string) $attr_val );
					foreach ( $cache['in_stock_colors'] as $color_row ) {
						if ( isset( $color_row['slug'] ) && $color_row['slug'] === $slug && ! empty( $color_row['main_img_id'] ) ) {
							$image_id = (int) $color_row['main_img_id'];
							break 2;
						}
					}
				}
			}
		}

		if ( $image_id > 0 ) {
			$custom_img = wp_get_attachment_image(
				$image_id,
				'woocommerce_thumbnail',
				false,
				array(
					'class' => 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail cux-cart-var-thumb',
				)
			);
			if ( ! empty( $custom_img ) ) {
				return $custom_img;
			}
		}

		return (string) $thumbnail;
	}
}
