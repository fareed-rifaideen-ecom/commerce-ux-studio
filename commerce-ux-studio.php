<?php
/**
 * Plugin Name:       Commerce UX Studio (Flatsome & Odoo Edition)
 * Plugin URI:        https://example.com/commerce-ux-studio
 * Description:       Dynamic Variation Stock-Winner Gallery Sync, Multi-Attribute Swatch Switcher, Omnichannel Fulfilment UI, Complete Your Ride Configurator, and Google ProductGroup Schema tailored for Flatsome & Odoo ERP.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Custom Engineering
 * Text Domain:       commerce-ux-studio
 * WC requires at least: 8.0
 * WC tested up to:   9.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Core Plugin Constants.
define( 'CUX_STUDIO_VERSION', '1.0.0' );
define( 'CUX_STUDIO_DB_VERSION', '1.0.0' );
define( 'CUX_STUDIO_FILE', __FILE__ );
define( 'CUX_STUDIO_PATH', plugin_dir_path( __FILE__ ) );
define( 'CUX_STUDIO_URL', plugin_dir_url( __FILE__ ) );
define( 'CUX_STUDIO_BASENAME', plugin_basename( __FILE__ ) );

// Load Core Activator & Loader.
require_once CUX_STUDIO_PATH . 'includes/core/class-cux-activator.php';
require_once CUX_STUDIO_PATH . 'includes/core/class-cux-loader.php';

/**
 * Activation & Deactivation Hooks.
 */
register_activation_hook( __FILE__, array( 'CUX_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CUX_Activator', 'deactivate' ) );

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
 * Initialize Plugin & Run Automated Git Deployment DB Migration Check.
 */
add_action(
	'plugins_loaded',
	function () {
		// Automatically run DB migrations when deployed via Git Deploy Manager.
		CUX_Activator::maybe_upgrade_db();

		// Boot the central loader.
		CUX_Loader::init();
	},
	15
);
