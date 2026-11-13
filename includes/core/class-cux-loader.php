<?php
/**
 * Central Service Container & Phase Bootstrap Loader.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Loader {

	/**
	 * Initialize plugin services once WooCommerce is verified.
	 */
	public static function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_missing_wc_notice' ) );
			return;
		}

		// Phase 1 Core Modules.
		require_once CUX_STUDIO_PATH . 'includes/core/class-cux-cache-manager.php';
		require_once CUX_STUDIO_PATH . 'includes/rules/class-cux-inheritance-engine.php';

		if ( is_admin() ) {
			require_once CUX_STUDIO_PATH . 'includes/admin/class-cux-admin-menu.php';
			CUX_Admin_Menu::init();
		}

		// Phase 2 through Phase 6 modules will automatically load below as we build each step.
		self::maybe_load_phase_modules();
	}

	/**
	 * Dynamically load subsequent phase classes if their files exist in the repository.
	 */
	private static function maybe_load_phase_modules(): void {
		$optional_modules = array(
			// Phase 2: Custom Fields, Visual Gallery Matrix & CSV/Odoo Maps.
			'includes/admin/class-cux-attribute-fields.php' => 'CUX_Attribute_Fields',
			'includes/admin/class-cux-product-metabox.php'  => 'CUX_Product_Metabox',
			'includes/admin/class-cux-variation-fields.php' => 'CUX_Variation_Fields',
			'includes/admin/class-cux-taxonomy-fields.php'  => 'CUX_Taxonomy_Fields',
			'includes/admin/class-cux-bulk-quick-edit.php'  => 'CUX_Bulk_Quick_Edit',
			// Phase 3: Stock Calculator & Hybrid Queue Dispatcher.
			'includes/sync/class-cux-stock-calculator.php'  => 'CUX_Stock_Calculator',
			'includes/sync/class-cux-queue-dispatcher.php'  => 'CUX_Queue_Dispatcher',
			'includes/sync/class-cux-odoo-csv-bridge.php'   => 'CUX_Odoo_CSV_Bridge',
			// Phase 4 & 5: Frontend Swatches, Flatsome Bridge, Fulfilment & Cross-Sell.
			'includes/frontend/class-cux-swatch-renderer.php'   => 'CUX_Swatch_Renderer',
			'includes/frontend/class-cux-catalog-cards.php'     => 'CUX_Catalog_Cards',
			'includes/frontend/class-cux-fulfilment-block.php'  => 'CUX_Fulfilment_Block',
			'includes/frontend/class-cux-cross-sell-drawer.php' => 'CUX_Cross_Sell_Drawer',
			'includes/frontend/class-cux-cart-thumbnails.php'   => 'CUX_Cart_Thumbnails',
			// Phase 6: Google ProductGroup Schema & Deep-Link SSR.
			'includes/seo/class-cux-schema-renderer.php'        => 'CUX_Schema_Renderer',
		);

		foreach ( $optional_modules as $relative_path => $class_name ) {
			$full_path = CUX_STUDIO_PATH . $relative_path;
			if ( file_exists( $full_path ) ) {
				require_once $full_path;
				if ( class_exists( $class_name ) && method_exists( $class_name, 'init' ) ) {
					$class_name::init();
				}
			}
		}
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
