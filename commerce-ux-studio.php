<?php
/**
 * Plugin Name:       Commerce UX Studio
 * Plugin URI:        https://github.com/fareed-rifaideen-ecom
 * Description:       Dynamic Variation Stock-Winner Gallery Sync, Multi-Option Visual & Image Swatches, Universal WooCommerce Shipping & Brand Sync, Complete Your Ride Configurator, and Google ProductGroup Schema.
 * Version:           2.3.2
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
define( 'CUX_STUDIO_VERSION', '2.3.2' );
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

// Global array to hold any module boot diagnostics so they always display in WP Admin.
$GLOBALS['cux_studio_boot_errors'] = array();

/**
 * Record any module load error and display it clearly in WP Admin Notices.
 *
 * @param Throwable $e     Caught exception or parse error.
 * @param string    $label Module name.
 */
function cux_studio_record_boot_error( \Throwable $e, string $label = 'Core' ): void {
	if ( ! isset( $GLOBALS['cux_studio_boot_errors'] ) || ! is_array( $GLOBALS['cux_studio_boot_errors'] ) ) {
		$GLOBALS['cux_studio_boot_errors'] = array();
	}

	$GLOBALS['cux_studio_boot_errors'][] = sprintf(
		'[%s] %s in %s on line %d',
		$label,
		$e->getMessage(),
		basename( $e->getFile() ),
		$e->getLine()
	);
}

add_action(
	'admin_notices',
	function () {
		if ( ! empty( $GLOBALS['cux_studio_boot_errors'] ) && is_array( $GLOBALS['cux_studio_boot_errors'] ) ) {
			foreach ( $GLOBALS['cux_studio_boot_errors'] as $err_msg ) {
				echo '<div class="notice notice-error"><p><strong>Commerce UX Studio Diagnostic:</strong> ' . esc_html( $err_msg ) . '</p></div>';
			}
		}
	}
);

// Load Core Activator & Central Loader safely.
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
	cux_studio_record_boot_error( $e, 'Bootstrap' );
}

// Add Website, LinkedIn, and GitHub links to the WordPress Plugins list row meta.
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

// Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

// Initialize Plugin on plugins_loaded.
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
			cux_studio_record_boot_error( $e, 'Init' );
		}
	},
	15
);
