<?php
/**
 * Hybrid Debounce Queue Dispatcher & WooCommerce / CSV / Odoo Event Listeners.
 *
 * File Path: includes/sync/class-cux-queue-dispatcher.php
 *
 * Responsibilities:
 * 1. Listens for product saves, variation saves, customer order stock changes,
 *    WooCommerce CSV imports, and direct `_stock` / `_product_image_gallery` meta updates (Odoo ERP).
 * 2. Debounces multiple variation updates for the same parent product into a single queue entry.
 * 3. Hybrid Execution:
 *    - 1 to 5 Parent Products in a request: Syncs immediately once on WordPress `shutdown`.
 *    - 6+ Parent Products (Bulk CSV / Odoo Batch): Offloads to WooCommerce Action Scheduler in chunks.
 * 4. Powers the AJAX Batch Processor for the "Sync All Products Now" button in the Admin Studio.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Queue_Dispatcher {

	/**
	 * Action Scheduler hook name for background batch processing.
	 */
	const AS_HOOK = 'cux_process_queued_parent_batch';

	/**
	 * Per-request debounced queue of parent product IDs => trigger source.
	 *
	 * @var array<int, string>
	 */
	private static $queued_parents = array();

	/**
	 * Register all WooCommerce, CSV Importer, Meta, Shutdown, Action Scheduler & AJAX hooks.
	 */
	public static function init(): void {
		// 1. Admin Product & Variation Save Listeners.
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'on_variation_saved' ), 30, 2 );
		add_action( 'woocommerce_process_product_meta_variable', array( __CLASS__, 'on_parent_saved' ), 30, 1 );

		// 2. Order & Stock Reduction Listeners.
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_product_stock_changed' ), 30, 1 );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_product_stock_changed' ), 30, 1 );
		add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'on_variation_status_changed' ), 30, 3 );

		// 3. WooCommerce Native CSV Importer Listener.
		add_action( 'woocommerce_product_import_inserted_product_object', array( __CLASS__, 'on_csv_product_imported' ), 30, 1 );

		// 4. Direct Post Meta Listener (Catches Odoo ERP webhooks & REST API updates to _stock or galleries).
		add_action( 'updated_post_meta', array( __CLASS__, 'on_direct_meta_updated' ), 30, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_direct_meta_updated' ), 30, 4 );

		// 5. Process queued parent products once at the end of the PHP request.
		add_action( 'shutdown', array( __CLASS__, 'dispatch_queued_parents' ), 20 );

		// 6. Background Action Scheduler / WP-Cron worker hook.
		add_action( self::AS_HOOK, array( __CLASS__, 'process_background_batch' ), 10, 2 );

		// 7. Admin AJAX endpoint for the "Sync All Products Now" button.
		add_action( 'wp_ajax_cux_run_bulk_sync', array( __CLASS__, 'ajax_run_bulk_sync' ) );
	}

	/**
	 * Add a parent variable product ID to the per-request debounce queue.
	 *
	 * @param int    $parent_id Parent product ID.
	 * @param string $source    Trigger source (admin, order, csv, odoo).
	 */
	public static function queue_parent( int $parent_id, string $source = 'admin' ): void {
		if ( $parent_id <= 0 ) {
			return;
		}

		// Do not re-queue if the calculator is currently mid-sync on this product.
		if ( class_exists( 'CUX_Stock_Calculator' ) && CUX_Stock_Calculator::is_syncing( $parent_id ) ) {
			return;
		}

		self::$queued_parents[ $parent_id ] = $source;
	}

	/**
	 * Listener: When a child variation is saved in WP Admin.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $i            Loop index.
	 */
	public static function on_variation_saved( $variation_id, $i = 0 ): void {
		$parent_id = wp_get_post_parent_id( (int) $variation_id );
		if ( $parent_id > 0 ) {
			self::queue_parent( $parent_id, 'admin' );
		}
	}

	/**
	 * Listener: When a parent variable product is saved in WP Admin.
	 *
	 * @param int $post_id Parent product ID.
	 */
	public static function on_parent_saved( $post_id ): void {
		self::queue_parent( (int) $post_id, 'admin' );
	}

	/**
	 * Listener: When variation or parent stock quantity changes (e.g. after checkout order or REST update).
	 *
	 * @param mixed $product WC_Product or WC_Product_Variation object.
	 */
	public static function on_product_stock_changed( $product ): void {
		if ( ! is_object( $product ) || ! method_exists( $product, 'is_type' ) ) {
			return;
		}

		if ( $product->is_type( 'variation' ) ) {
			$parent_id = (int) $product->get_parent_id();
			if ( $parent_id > 0 ) {
				self::queue_parent( $parent_id, 'order_or_stock' );
			}
		} elseif ( $product->is_type( 'variable' ) ) {
			self::queue_parent( (int) $product->get_id(), 'order_or_stock' );
		}
	}

	/**
	 * Listener: When a variation's stock status changes (instock / outofstock / onbackorder).
	 *
	 * @param int    $variation_id Variation ID.
	 * @param string $status       New stock status.
	 * @param mixed  $product      Product object.
	 */
	public static function on_variation_status_changed( $variation_id, $status = '', $product = null ): void {
		$parent_id = wp_get_post_parent_id( (int) $variation_id );
		if ( $parent_id > 0 ) {
			self::queue_parent( $parent_id, 'stock_status' );
		}
	}

	/**
	 * Listener: When a product or variation is imported via the native WooCommerce CSV Importer.
	 *
	 * @param mixed $product Imported WC_Product object.
	 */
	public static function on_csv_product_imported( $product ): void {
		if ( ! is_object( $product ) || ! method_exists( $product, 'is_type' ) ) {
			return;
		}

		if ( $product->is_type( 'variation' ) ) {
			$parent_id = (int) $product->get_parent_id();
			if ( $parent_id > 0 ) {
				self::queue_parent( $parent_id, 'csv_import' );
			}
		} elseif ( $product->is_type( 'variable' ) ) {
			self::queue_parent( (int) $product->get_id(), 'csv_import' );
		}
	}

	/**
	 * Listener: Catch direct `update_post_meta()` calls on variations (e.g. Custom Odoo ERP Connector).
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key updated.
	 * @param mixed  $meta_value New meta value.
	 */
	public static function on_direct_meta_updated( $meta_id, $object_id, $meta_key, $meta_value ): void {
		$watched_keys = array(
			'_stock',
			'_stock_status',
			'_thumbnail_id',
			'_product_image_gallery',
			'_wc_additional_variation_images',
			'_wc_variation_gallery_ids',
		);

		if ( ! in_array( (string) $meta_key, $watched_keys, true ) ) {
			return;
		}

		$object_id = (int) $object_id;
		if ( 'product_variation' === get_post_type( $object_id ) ) {
			$parent_id = wp_get_post_parent_id( $object_id );
			if ( $parent_id > 0 ) {
				self::queue_parent( $parent_id, 'odoo_or_meta' );
			}
		}
	}

	/**
	 * Hybrid Shutdown Dispatcher:
	 * - If 1 to 5 parent products are queued, sync them immediately.
	 * - If 6+ parent products are queued (bulk CSV or Odoo batch), schedule background chunks of 25.
	 */
	public static function dispatch_queued_parents(): void {
		if ( empty( self::$queued_parents ) || ! class_exists( 'CUX_Stock_Calculator' ) ) {
			return;
		}

		$queue             = self::$queued_parents;
		self::$queued_parents = array();

		$total_queued = count( $queue );

		// Small queue (1 to 5 parent products): process immediately on shutdown.
		if ( $total_queued <= 5 ) {
			foreach ( $queue as $parent_id => $source ) {
				CUX_Stock_Calculator::sync_parent_product( (int) $parent_id, (string) $source );
			}
			return;
		}

		// Large batch queue (>5 parent products): offload in chunks of 25 to Action Scheduler / WP-Cron.
		$parent_ids = array_keys( $queue );
		$first_src  = (string) reset( $queue );
		$chunks     = array_chunk( $parent_ids, 25 );

		foreach ( $chunks as $index => $chunk_ids ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action(
					self::AS_HOOK,
					array(
						'parent_ids' => $chunk_ids,
						'source'     => $first_src,
					),
					'commerce-ux-studio'
				);
			} else {
				wp_schedule_single_event(
					time() + ( $index * 5 ),
					self::AS_HOOK,
					array( $chunk_ids, $first_src )
				);
			}
		}
	}

	/**
	 * Background Worker: Process a scheduled chunk of parent product IDs.
	 *
	 * @param array  $parent_ids Array of parent variable product IDs.
	 * @param string $source     Trigger source.
	 */
	public static function process_background_batch( $parent_ids = array(), string $source = 'batch' ): void {
		if ( ! is_array( $parent_ids ) || empty( $parent_ids ) || ! class_exists( 'CUX_Stock_Calculator' ) ) {
			return;
		}

		foreach ( $parent_ids as $parent_id ) {
			CUX_Stock_Calculator::sync_parent_product( absint( $parent_id ), sanitize_key( $source ) );
		}
	}

	/**
	 * Admin AJAX Handler: Powers the "Sync All Products Now" button in batches of 15 products.
	 */
	public static function ajax_run_bulk_sync(): void {
		check_ajax_referer( 'cux_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'commerce-ux-studio' ) ), 403 );
		}

		$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$batch_size = 15;

		// Query Variable Products using WooCommerce's native product query.
		$query = new WC_Product_Query(
			array(
				'type'     => 'variable',
				'status'   => array( 'publish', 'private' ),
				'limit'    => $batch_size,
				'offset'   => $offset,
				'paginate' => true,
				'return'   => 'ids',
				'orderby'  => 'ID',
				'order'    => 'ASC',
			)
		);

		$results    = $query->get_products();
		$parent_ids = is_object( $results ) && isset( $results->products ) ? (array) $results->products : array();
		$total      = is_object( $results ) && isset( $results->total ) ? (int) $results->total : 0;

		$synced_count = 0;
		if ( ! empty( $parent_ids ) && class_exists( 'CUX_Stock_Calculator' ) ) {
			foreach ( $parent_ids as $pid ) {
				if ( CUX_Stock_Calculator::sync_parent_product( (int) $pid, 'bulk_sync' ) ) {
					++$synced_count;
				}
			}
		}

		$processed = min( $total, $offset + count( $parent_ids ) );
		$has_more  = $processed < $total && ! empty( $parent_ids );

		wp_send_json_success(
			array(
				'total'        => $total,
				'processed'    => $processed,
				'synced_batch' => $synced_count,
				'next_offset'  => $processed,
				'has_more'     => $has_more,
				'message'      => $has_more
					? sprintf(
						/* translators: 1: processed products count, 2: total variable products count */
						__( 'Syncing variable products: %1$d of %2$d completed...', 'commerce-ux-studio' ),
						$processed,
						$total
					)
					: sprintf(
						/* translators: %d: total synced variable products count */
						__( 'Complete! All %d variable products have been synced with highest-stock main images and color galleries.', 'commerce-ux-studio' ),
						$total
					),
			)
		);
	}
}
