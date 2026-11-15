<?php
/**
 * Central Service Container & Crash-Protected Module Loader (v2.3.1).
 *
 * File Path: includes/core/class-cux-loader.php
 *
 * @package Commerce_UX_Studio
 * @author  Fareed M. Rifaideen <https://fareed-rifaideen.netlify.app/>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Loader {

	/**
	 * Track loaded status of each module for the System Health Drawer.
	 *
	 * @var array<string, bool>
	 */
	private static $module_health = array();

	/**
	 * Initialize plugin services once WooCommerce is verified.
	 */
	public static function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_missing_wc_notice' ) );
			return;
		}

		// 1. Core Cache & 4-Tier Inheritance Engine.
		self::safe_load_module( 'Cache Manager', 'includes/core/class-cux-cache-manager.php', 'CUX_Cache_Manager', false );
		self::safe_load_module( '4-Tier Inheritance Engine', 'includes/rules/class-cux-inheritance-engine.php', 'CUX_Inheritance_Engine', true );

		// 2. Admin Studio Menu & Attribute / Product / Category Fields.
		if ( is_admin() ) {
			self::safe_load_module( 'Admin Studio Menu', 'includes/admin/class-cux-admin-menu.php', 'CUX_Admin_Menu', true );
			self::safe_load_module( 'Global Attribute Swatch Fields', 'includes/admin/class-cux-attribute-fields.php', 'CUX_Attribute_Fields', true );
			self::safe_load_module( 'Native WooCommerce Product Fields', 'includes/admin/class-cux-native-wc-fields.php', 'CUX_Native_WC_Fields', true );
			self::safe_load_module( 'Category & Tag UX Fields', 'includes/admin/class-cux-taxonomy-fields.php', 'CUX_Taxonomy_Fields', true );
		}

		// 3. Stock Winner Calculator & Hybrid Queue Dispatcher (runs on both Admin & Frontend/REST/Cron).
		self::safe_load_module( 'Stock Winner & Gallery Calculator', 'includes/sync/class-cux-stock-calculator.php', 'CUX_Stock_Calculator', true );
		self::safe_load_module( 'Hybrid Queue Dispatcher', 'includes/sync/class-cux-queue-dispatcher.php', 'CUX_Queue_Dispatcher', true );

		// 4. Frontend PDP Swatches, Catalog Cards, Cart Thumbnails & Google ProductGroup Schema.
		self::safe_load_module( 'Single Product Swatch & Gallery Renderer', 'includes/frontend/class-cux-pdp-renderer.php', 'CUX_PDP_Renderer', true );
		self::safe_load_module( 'Shop & Category Card Renderer', 'includes/frontend/class-cux-catalog-cards.php', 'CUX_Catalog_Cards', true );
		self::safe_load_module( 'Cart & Mini-Cart Variation Thumbnails', 'includes/frontend/class-cux-cart-thumbnails.php', 'CUX_Cart_Thumbnails', true );
		self::safe_load_module( 'Google ProductGroup Schema & SSR', 'includes/seo/class-cux-schema-renderer.php', 'CUX_Schema_Renderer', true );
	}

	/**
	 * Safely require and initialize a module inside a Throwable crash-shield.
	 *
	 * @param string $label         Human-readable module name.
	 * @param string $relative_path Path relative to plugin root.
	 * @param string $class_name    PHP class name.
	 * @param bool   $call_init     Whether to invoke `$class_name::init()`.
	 */
	private static function safe_load_module( string $label, string $relative_path, string $class_name, bool $call_init = true ): void {
		$full_path = CUX_STUDIO_PATH . $relative_path;
		if ( ! file_exists( $full_path ) ) {
			self::$module_health[ $label ] = false;
			return;
		}

		try {
			require_once $full_path;
			if ( class_exists( $class_name ) ) {
				if ( $call_init && method_exists( $class_name, 'init' ) ) {
					$class_name::init();
				}
				self::$module_health[ $label ] = true;
			} else {
				self::$module_health[ $label ] = false;
			}
		} catch ( \Throwable $e ) {
			self::$module_health[ $label ] = false;
			if ( function_exists( 'cux_studio_record_boot_error' ) ) {
				cux_studio_record_boot_error( $e );
			}
		}
	}

	/**
	 * Return module health map for the Developer Drawer in Commerce UX Studio.
	 *
	 * @return array<string, bool>
	 */
	public static function get_module_health(): array {
		return self::$module_health;
	}

	/**
	 * Admin notice if WooCommerce is inactive.
	 */
	public static function render_missing_wc_notice(): void {
		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'Commerce UX Studio requires WooCommerce to be installed and active.', 'commerce-ux-studio' ); ?></strong></p>
		</div>
		<?php
	}
}
