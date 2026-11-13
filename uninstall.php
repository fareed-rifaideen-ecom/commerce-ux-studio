<?php
/**
 * Safe Uninstall Handler for Commerce UX Studio.
 *
 * By default, preserves custom tables and product gallery metadata on plugin deletion
 * so accidental uninstalls or Git re-installs never wipe store data.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'cux_global_settings', array() );

// Only drop tables and options if the admin explicitly enabled full data wipe on uninstall.
if ( ! empty( $settings['delete_data_on_uninstall'] ) && 'yes' === $settings['delete_data_on_uninstall'] ) {
	global $wpdb;

	$rules_table = $wpdb->prefix . 'cux_rules_presets';
	$audit_table = $wpdb->prefix . 'cux_sync_audit_log';

	$wpdb->query( "DROP TABLE IF EXISTS {$rules_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$audit_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	delete_option( 'cux_global_settings' );
	delete_option( 'cux_db_version' );
}
