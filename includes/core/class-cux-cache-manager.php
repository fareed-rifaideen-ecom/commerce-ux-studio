<?php
/**
 * Precomputed Product UX Cache & Third-Party Page Cache Purge Manager.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Cache_Manager {

	/**
	 * Meta key storing the precomputed frontend JSON payload on parent variable products.
	 */
	const CACHE_META_KEY = '_cux_product_cache';

	/**
	 * Retrieve the precomputed UX cache for a parent product (zero SQL child queries).
	 *
	 * @param int $product_id Parent product ID.
	 * @return array
	 */
	public static function get_product_cache( int $product_id ): array {
		$cached = get_post_meta( $product_id, self::CACHE_META_KEY, true );
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}
		if ( is_string( $cached ) && ! empty( $cached ) ) {
			$decoded = json_decode( $cached, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		return array();
	}

	/**
	 * Store the precomputed UX cache on a parent product.
	 *
	 * @param int   $product_id Parent product ID.
	 * @param array $payload    Precomputed cache array.
	 */
	public static function set_product_cache( int $product_id, array $payload ): void {
		$payload['updated_at'] = time();
		$payload['version']    = CUX_STUDIO_VERSION;
		update_post_meta( $product_id, self::CACHE_META_KEY, $payload );
	}

	/**
	 * Purge WooCommerce product transients and invalidate page caches for a specific product ID.
	 *
	 * @param int $product_id Parent product ID.
	 */
	public static function purge_product_cache( int $product_id ): void {
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}
		clean_post_cache( $product_id );

		// 1. WP Rocket targeted post purge.
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $product_id );
		}

		// 2. LiteSpeed Cache targeted post purge.
		if ( has_action( 'litespeed_purge_post' ) ) {
			do_action( 'litespeed_purge_post', $product_id );
		}

		// 3. W3 Total Cache targeted post purge.
		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $product_id );
		}

		// 4. SiteGround Optimizer targeted URL purge.
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			$permalink = get_permalink( $product_id );
			if ( $permalink ) {
				sg_cachepress_purge_cache( $permalink );
			}
		}

		// 5. WP Fastest Cache single post purge.
		if ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'singleDeleteCache' ) ) {
			$GLOBALS['wp_fastest_cache']->singleDeleteCache( false, $product_id );
		}

		do_action( 'cux_after_product_cache_purged', $product_id );
	}

	/**
	 * Record an automated stock-winner image switch in the audit log table.
	 *
	 * @param int    $parent_id     Parent product ID.
	 * @param string $source        Trigger source (admin, order, csv, odoo, bulk).
	 * @param int    $old_var_id    Previous winning variation ID.
	 * @param int    $new_var_id    New winning variation ID.
	 * @param int    $old_image_id  Previous main attachment ID.
	 * @param int    $new_image_id  New main attachment ID.
	 * @param int    $stock_qty     Winning stock quantity snapshot.
	 */
	public static function log_sync_event(
		int $parent_id,
		string $source,
		int $old_var_id,
		int $new_var_id,
		int $old_image_id,
		int $new_image_id,
		int $stock_qty
	): void {
		global $wpdb;
		$audit_table = $wpdb->prefix . 'cux_sync_audit_log';

		$wpdb->insert(
			$audit_table,
			array(
				'parent_id'         => $parent_id,
				'trigger_source'    => sanitize_key( $source ),
				'old_winner_var_id' => $old_var_id,
				'new_winner_var_id' => $new_var_id,
				'old_image_id'      => $old_image_id,
				'new_image_id'      => $new_image_id,
				'stock_snapshot'    => $stock_qty,
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s' )
		);

		// Keep audit log table lean (prune records beyond the latest 500 entries).
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$audit_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 550 ) {
			$wpdb->query(
				"DELETE FROM {$audit_table} WHERE id NOT IN (
					SELECT id FROM (
						SELECT id FROM {$audit_table} ORDER BY id DESC LIMIT 500
					) AS keep_rows
				)" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}
	}
}
