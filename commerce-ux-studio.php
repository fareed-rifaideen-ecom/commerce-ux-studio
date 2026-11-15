<?php
/**
 * Plugin Name:       Commerce UX Studio
 * Plugin URI:        https://github.com/fareed-rifaideen-ecom
 * Description:       Dynamic Variation Stock-Winner Gallery Sync, Multi-Option Visual & Image Swatches, Universal WooCommerce Shipping & Brand Sync, Complete Your Ride Configurator, and Google ProductGroup Schema.
 * Version:           2.3.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Fareed M. Rifaideen
 * Author URI:        https://fareed-rifaideen.netlify.app/
 * Text Domain:       commerce-ux-studio
 * WC requires at least: 8.0
 * WC tested up to:   11.3
 *
 * @package Commerce_UX_Studio
 * @author  Fareed M. Rifaideen <https://fareed-rifaideen.netlify.app/>
 * @link    https://www.linkedin.com/in/fareed03/
 * @link    https://github.com/fareed-rifaideen-ecom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Core Plugin Constants.
define( 'CUX_STUDIO_VERSION', '2.3.1' );
define( 'CUX_STUDIO_DB_VERSION', '1.0.0' );
define( 'CUX_STUDIO_FILE', __FILE__ );
define( 'CUX_STUDIO_PATH', plugin_dir_path( __FILE__ ) );
define( 'CUX_STUDIO_URL', plugin_dir_url( __FILE__ ) );
define( 'CUX_STUDIO_BASENAME', plugin_basename( __FILE__ ) );

// Author Metadata Constants.
define( 'CUX_AUTHOR_NAME', 'Fareed M. Rifaideen' );
define( 'CUX_AUTHOR_WEBSITE', 'https://fareed-rifaideen.netlify.app/' );
define( 'CUX_AUTHOR_LINKEDIN', 'https://www.linkedin.com/in/fareed03/' );
define( 'CUX_AUTHOR_GITHUB', 'https://github.com/fareed-rifaideen-ecom' );

/**
 * Global Crash-Shield Error Collector so a syntax or runtime error in any module
 * never triggers the WordPress "Critical Error" screen.
 *
 * @param Throwable $e Caught error or exception.
 */
function cux_studio_record_boot_error( \Throwable $e ): void {
	$GLOBALS['cux_studio_boot_error'] = sprintf(
		'%s in %s on line %d',
		$e->getMessage(),
		basename( $e->getFile() ),
		$e->getLine()
	);

	add_action(
		'admin_notices',
		function () {
			if ( ! empty( $GLOBALS['cux_studio_boot_error'] ) ) {
				echo '<div class="notice notice-error"><p><strong>Commerce UX Studio Diagnostic:</strong> ' . esc_html( $GLOBALS['cux_studio_boot_error'] ) . '</p></div>';
			}
		}
	);
}

// Safely load Core Activator & Loader inside a Throwable shield.
try {
	if ( file_exists( CUX_STUDIO_PATH . 'includes/core/class-cux-activator.php' ) ) {
		require_once CUX_STUDIO_PATH . 'includes/core/class-cux-activator.php';
	}
	if ( file_exists( CUX_STUDIO_PATH . 'includes/core/class-cux-loader.php' ) ) {
		require_once CUX_STUDIO_PATH . 'includes/core/class-cux-loader.php';
	}

	if ( class_exists( 'CUX_Activator' ) ) {
		register_activation_hook( __FILE__, array( 'CUX_Activator', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'CUX_Activator', 'deactivate' ) );
	}
} catch ( \Throwable $e ) {
	cux_studio_record_boot_error( $e );
}

/**
 * Add Website, LinkedIn, and GitHub links to the WordPress Plugins list row meta.
 */
add_filter(
	'plugin_row_meta',
	function ( $links, $file ) {
		if ( ! is_array( $links ) ) {
			return $links;
		}
		if ( CUX_STUDIO_BASENAME === $file ) {
			$links[] = '<a href="' . esc_url( CUX_AUTHOR_WEBSITE ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Website', 'commerce-ux-studio' ) . '</a>';
			$links[] = '<a href="' . esc_url( CUX_AUTHOR_LINKEDIN ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'LinkedIn', 'commerce-ux-studio' ) . '</a>';
			$links[] = '<a href="' . esc_url( CUX_AUTHOR_GITHUB ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'GitHub', 'commerce-ux-studio' ) . '</a>';
		}
		return $links;
	},
	10,
	2
);

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Initialize Plugin Safely on plugins_loaded.
 */
add_action(
	'plugins_loaded',
	function () {
		try {
			if ( class_exists( 'CUX_Activator' ) && method_exists( 'CUX_Activator', 'maybe_upgrade_db' ) ) {
				CUX_Activator::maybe_upgrade_db();
			}
			if ( class_exists( 'CUX_Loader' ) && method_exists( 'CUX_Loader', 'init' ) ) {
				CUX_Loader::init();
			}
		} catch ( \Throwable $e ) {
			cux_studio_record_boot_error( $e );
		}
	},
	15
);
