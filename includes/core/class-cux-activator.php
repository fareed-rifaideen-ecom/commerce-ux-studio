<?php
/**
 * Database Installer, Default Seeder & Git Deployment Migration Handler.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		self::create_tables();
		self::seed_defaults_if_empty();
		update_option( 'cux_db_version', CUX_STUDIO_DB_VERSION );
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		// Preserve settings and custom tables on deactivation for safe Git deployments.
	}

	/**
	 * Automatically check if code DB version is newer than stored DB version (Git Deploy safe).
	 */
	public static function maybe_upgrade_db(): void {
		$installed_version = get_option( 'cux_db_version', '0.0.0' );
		if ( version_compare( $installed_version, CUX_STUDIO_DB_VERSION, '<' ) ) {
			self::create_tables();
			self::seed_defaults_if_empty();
			update_option( 'cux_db_version', CUX_STUDIO_DB_VERSION );
		}
	}

	/**
	 * Create custom database tables via dbDelta.
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$rules_table     = $wpdb->prefix . 'cux_rules_presets';
		$audit_table     = $wpdb->prefix . 'cux_sync_audit_log';

		$sql_rules = "CREATE TABLE {$rules_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rule_type varchar(32) NOT NULL DEFAULT 'preset',
			title varchar(191) NOT NULL,
			priority int(11) NOT NULL DEFAULT 10,
			status varchar(20) NOT NULL DEFAULT 'active',
			conditions_json longtext NOT NULL,
			settings_json longtext NOT NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_type_status_priority (rule_type, status, priority)
		) {$charset_collate};";

		$sql_audit = "CREATE TABLE {$audit_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			parent_id bigint(20) unsigned NOT NULL,
			trigger_source varchar(50) NOT NULL,
			old_winner_var_id bigint(20) unsigned DEFAULT 0,
			new_winner_var_id bigint(20) unsigned NOT NULL,
			old_image_id bigint(20) unsigned DEFAULT 0,
			new_image_id bigint(20) unsigned NOT NULL,
			stock_snapshot int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_parent_date (parent_id, created_at)
		) {$charset_collate};";

		dbDelta( $sql_rules );
		dbDelta( $sql_audit );
	}

	/**
	 * Seed default global settings and presets from config/default-presets.json if not yet set.
	 *
	 * @param bool $force Whether to overwrite existing settings from the repository JSON file.
	 */
	public static function seed_defaults_if_empty( bool $force = false ): bool {
		$json_file = CUX_STUDIO_PATH . 'config/default-presets.json';
		if ( ! file_exists( $json_file ) ) {
			return false;
		}

		$raw_json = file_get_contents( $json_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$decoded  = json_decode( $raw_json, true );
		if ( ! is_array( $decoded ) ) {
			return false;
		}

		$existing_settings = get_option( 'cux_global_settings', array() );
		if ( empty( $existing_settings ) || $force ) {
			if ( ! empty( $decoded['global_settings'] ) && is_array( $decoded['global_settings'] ) ) {
				update_option( 'cux_global_settings', $decoded['global_settings'] );
			}
		}

		global $wpdb;
		$rules_table = $wpdb->prefix . 'cux_rules_presets';

		// Check if table exists before querying.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rules_table ) );
		if ( $table_exists !== $rules_table ) {
			return false;
		}

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rules_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ( 0 === $count || $force ) && ! empty( $decoded['presets'] ) ) {
			if ( $force ) {
				$wpdb->query( "DELETE FROM {$rules_table} WHERE rule_type = 'preset'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			foreach ( $decoded['presets'] as $preset ) {
				$wpdb->insert(
					$rules_table,
					array(
						'rule_type'       => sanitize_key( $preset['rule_type'] ?? 'preset' ),
						'title'           => sanitize_text_field( $preset['title'] ?? 'Preset' ),
						'priority'        => absint( $preset['priority'] ?? 10 ),
						'status'          => sanitize_key( $preset['status'] ?? 'active' ),
						'conditions_json' => wp_json_encode( $preset['conditions'] ?? array() ),
						'settings_json'   => wp_json_encode( $preset['settings'] ?? array() ),
						'updated_at'      => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
				);
			}
		}

		return true;
	}

	/**
	 * Check health status of custom database tables.
	 *
	 * @return array
	 */
	public static function get_database_health(): array {
		global $wpdb;
		$rules_table = $wpdb->prefix . 'cux_rules_presets';
		$audit_table = $wpdb->prefix . 'cux_sync_audit_log';

		$rules_ok = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rules_table ) ) === $rules_table );
		$audit_ok = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $audit_table ) ) === $audit_table );

		$rules_count = $rules_ok ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rules_table}" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$audit_count = $audit_ok ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$audit_table}" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'rules_table_exists' => $rules_ok,
			'audit_table_exists' => $audit_ok,
			'rules_count'        => $rules_count,
			'audit_count'        => $audit_count,
			'db_version'         => get_option( 'cux_db_version', '0.0.0' ),
		);
	}
}
