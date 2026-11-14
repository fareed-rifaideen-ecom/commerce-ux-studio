<?php
/**
 * Central Service Loader & WooCommerce Compatibility Bridge (v2.0 Simplified Edition)
 *
 * File Path: includes/core/class-cux-loader.php
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Loader {

	/**
	 * Boot all plugin services and WooCommerce compatibility hooks.
	 */
	public static function init(): void {
		// Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
		add_action( 'before_woocommerce_init', [ __CLASS__, 'declare_wc_compatibility' ] );

		// Load core services after all plugins (including WooCommerce) are loaded.
		add_action( 'plugins_loaded', [ __CLASS__, 'boot_services' ], 20 );
	}

	/**
	 * Inform WooCommerce 11.3+ that this plugin supports HPOS & custom order tables.
	 */
	public static function declare_wc_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				CUX_STUDIO_FILE,
				true
			);
		}
	}

	/**
	 * Load and initialize each module class if the file exists.
	 */
	public static function boot_services(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// 1. Core Cache & 4-Tier Inheritance Engine.
		require_once CUX_STUDIO_PATH . 'includes/core/class-cux-cache-manager.php';
		require_once CUX_STUDIO_PATH . 'includes/rules/class-cux-inheritance-engine.php';

		// 2. Admin Studio & Native WooCommerce 11.3+ Field Integrations.
		if ( is_admin() ) {
			self::load_module( 'includes/admin/class-cux-admin-menu.php', 'CUX_Admin_Menu' );
			self::load_module( 'includes/admin/class-cux-attribute-fields.php', 'CUX_Attribute_Fields' );
			self::load_module( 'includes/admin/class-cux-taxonomy-fields.php', 'CUX_Taxonomy_Fields' );
			self::load_module( 'includes/admin/class-cux-native-wc-fields.php', 'CUX_Native_WC_Fields' );
		}

		// 3. Stock Winner Calculator & Auto-Copy Gallery Queue Engine (Runs on Admin, Frontend, REST & Cron).
		self::load_module( 'includes/sync/class-cux-stock-calculator.php', 'CUX_Stock_Calculator' );
		self::load_module( 'includes/sync/class-cux-queue-dispatcher.php', 'CUX_Queue_Dispatcher' );

		// 4. Frontend Flatsome UI, Catalog Cards, Delivery Block & Cart Thumbnails.
		if ( ! is_admin() || wp_doing_ajax() ) {
			self::load_module( 'includes/frontend/class-cux-catalog-cards.php', 'CUX_Catalog_Cards' );
			self::load_module( 'includes/frontend/class-cux-pdp-renderer.php', 'CUX_PDP_Renderer' );
			self::load_module( 'includes/frontend/class-cux-cart-thumbnails.php', 'CUX_Cart_Thumbnails' );
		}

		// 5. Google ProductGroup JSON-LD Schema & Deep-Link SSR Handler.
		self::load_module( 'includes/seo/class-cux-schema-renderer.php', 'CUX_Schema_Renderer' );
	}

	/**
	 * Safely require a module file and call its init() method if present.
	 *
	 * @param string $relative_path Relative path from plugin root.
	 * @param string $class_name    Expected PHP class name.
	 */
	private static function load_module( string $relative_path, string $class_name ): void {
		$full_path = CUX_STUDIO_PATH . $relative_path;
		if ( file_exists( $full_path ) ) {
			require_once $full_path;
			if ( class_exists( $class_name ) && method_exists( $class_name, 'init' ) ) {
				$class_name::init();
			}
		}
	}

	/**
	 * Return module installation status for the Admin Developer Health Drawer.
	 *
	 * @return array<string, bool>
	 */
	public static function get_module_health(): array {
		$modules = [
			'Admin 3-Tab Visual Studio (class-cux-admin-menu.php)'        => 'includes/admin/class-cux-admin-menu.php',
			'Attribute Color/Split Swatches (class-cux-attribute-fields.php)' => 'includes/admin/class-cux-attribute-fields.php',
			'Category & Tag Settings (class-cux-taxonomy-fields.php)'     => 'includes/admin/class-cux-taxonomy-fields.php',
			'Native WC 11.3+ Fields (class-cux-native-wc-fields.php)'     => 'includes/admin/class-cux-native-wc-fields.php',
			'Stock Winner Calculator (class-cux-stock-calculator.php)'    => 'includes/sync/class-cux-stock-calculator.php',
			'Queue & Auto-Copy Engine (class-cux-queue-dispatcher.php)'   => 'includes/sync/class-cux-queue-dispatcher.php',
			'Flatsome Shop Cards (class-cux-catalog-cards.php)'           => 'includes/frontend/class-cux-catalog-cards.php',
			'Flatsome PDP & Delivery UX (class-cux-pdp-renderer.php)'     => 'includes/frontend/class-cux-pdp-renderer.php',
			'Cart Variation Thumbnails (class-cux-cart-thumbnails.php)'   => 'includes/frontend/class-cux-cart-thumbnails.php',
			'Google ProductGroup Schema (class-cux-schema-renderer.php)'  => 'includes/seo/class-cux-schema-renderer.php',
		];

		$status = [];
		foreach ( $modules as $label => $path ) {
			$status[ $label ] = file_exists( CUX_STUDIO_PATH . $path );
		}
		return $status;
	}
}
