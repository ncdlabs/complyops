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

use ComplyOps\Consent\ConsentSettings;
use ComplyOps\Control\ControlOverrideService;
use ComplyOps\Database\Schema;
use ComplyOps\Enforcement\EnforcementSettings;
use ComplyOps\Enforcement\PiiSettings;
use ComplyOps\Integration\Google\GoogleOAuthTokens;
use ComplyOps\Integration\GoogleAnalytics\GoogleAnalyticsSettings;
use ComplyOps\Integration\GoogleTagManager\GoogleTagManagerSettings;
use ComplyOps\Evidence\EvidenceRetentionService;
use ComplyOps\Framework\FrameworkPackService;
use ComplyOps\Monitoring\MonitoringScheduler;
use ComplyOps\PublicStatus\PublicStatusSettings;
use ComplyOps\REST\SettingsController;
use ComplyOps\Security\Capabilities;
use ComplyOps\Verification\BrowserVerificationSettings;
use ComplyOps\WordPress\WordPressPrivacySettings;

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
delete_transient( 'complyops_drift_notice' );

$complyops_options = array(
	Schema::OPTION_KEY,
	ConsentSettings::OPTION_KEY,
	EnforcementSettings::OPTION_KEY,
	GoogleAnalyticsSettings::OPTION_KEY,
	GoogleOAuthTokens::OPTION_KEY,
	GoogleTagManagerSettings::OPTION_KEY,
	PiiSettings::OPTION_KEY,
	FrameworkPackService::OPTION_INSTALLED,
	FrameworkPackService::OPTION_ACTIVATIONS,
	FrameworkPackService::OPTION_INACTIVE,
	ControlOverrideService::OPTION,
	MonitoringScheduler::OPTION_INTERVAL,
	EvidenceRetentionService::OPTION_KEY,
	BrowserVerificationSettings::OPTION_KEY,
	WordPressPrivacySettings::OPTION_KEY,
	PublicStatusSettings::OPTION_KEY,
	SettingsController::SETUP_COMPLETE_OPTION,
	SettingsController::SETUP_DISMISSED_FOREVER_OPTION,
	SettingsController::SETUP_PENDING_OPTION,
	SettingsController::SETUP_BANNER_DECIDED_OPTION,
	SettingsController::TUTORIAL_COMPLETE_OPTION,
	SettingsController::TUTORIAL_DISMISSED_FOREVER_OPTION,
	\ComplyOps\Notification\NotificationService::OPTION_KEY,
);

foreach ( $complyops_options as $complyops_option ) {
	delete_option( $complyops_option );
}

global $wpdb;

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
