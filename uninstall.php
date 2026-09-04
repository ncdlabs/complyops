<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package ComplyOps
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/src/Support/Autoloader.php';

ComplyOps\Support\Autoloader::register( __DIR__ . '/src/' );

use ComplyOps\Database\Schema;
use ComplyOps\Monitoring\MonitoringScheduler;
use ComplyOps\Security\Capabilities;

/**
 * Recursively remove a directory under uploads.
 *
 * @param string $directory Absolute path.
 */
function complyops_uninstall_remove_directory( string $directory ): void {
	if ( ! is_dir( $directory ) ) {
		return;
	}

	$complyops_entries = scandir( $directory );

	if ( false === $complyops_entries ) {
		return;
	}

	foreach ( $complyops_entries as $complyops_entry ) {
		if ( '.' === $complyops_entry || '..' === $complyops_entry ) {
			continue;
		}

		$complyops_path = $directory . DIRECTORY_SEPARATOR . $complyops_entry;

		if ( is_dir( $complyops_path ) ) {
			complyops_uninstall_remove_directory( $complyops_path );
			continue;
		}

		wp_delete_file( $complyops_path );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- uninstall cleanup.
	rmdir( $directory );
}

wp_clear_scheduled_hook( MonitoringScheduler::CRON_HOOK );

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall removes all plugin options/transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'complyops_' ) . '%',
		$wpdb->esc_like( '_transient_complyops_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_complyops_' ) . '%'
	)
);

if ( function_exists( 'delete_metadata' ) ) {
	delete_metadata( 'user', 0, 'complyops_last_login', '', true );
}

foreach ( array_keys( Schema::tables() ) as $complyops_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall drops plugin-owned tables from Schema.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $complyops_table ) );
}

if ( function_exists( 'wp_roles' ) ) {
	$complyops_roles = wp_roles();

	if ( $complyops_roles instanceof WP_Roles ) {
		foreach ( array_keys( $complyops_roles->roles ) as $complyops_role_name ) {
			$complyops_role = get_role( $complyops_role_name );

			if ( null === $complyops_role ) {
				continue;
			}

			foreach ( Capabilities::all() as $complyops_capability ) {
				$complyops_role->remove_cap( $complyops_capability );
			}
		}
	}
}

if ( function_exists( 'wp_upload_dir' ) ) {
	$complyops_uploads = wp_upload_dir();

	if ( empty( $complyops_uploads['error'] ) ) {
		complyops_uninstall_remove_directory(
			trailingslashit( (string) $complyops_uploads['basedir'] ) . 'complyops'
		);
	}
}
