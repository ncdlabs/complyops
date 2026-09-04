<?php

declare(strict_types=1);

namespace ComplyOps\Database;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Evidence\EvidenceRetentionService;

/**
 * Installs and upgrades ComplyOps database tables.
 */
final class Installer {

	public static function install(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$previous = get_option( Schema::OPTION_KEY, '' );
		$schemas  = Schema::tables();

		foreach ( $schemas as $sql ) {
			dbDelta( $sql );
		}

		if ( is_string( $previous ) && version_compare( $previous, '1.1.0', '<' ) ) {
			self::migrate_to_1_1_0();
		}

		update_option( Schema::OPTION_KEY, Schema::DB_VERSION, false );
		self::ensure_default_options();
	}

	private static function ensure_default_options(): void {
		if ( null === get_option( EvidenceRetentionService::OPTION_KEY, null ) ) {
			add_option( EvidenceRetentionService::OPTION_KEY, 365 );
		}
	}

	public static function maybe_upgrade(): void {
		$installed = get_option( Schema::OPTION_KEY, '' );

		if ( version_compare( (string) $installed, Schema::DB_VERSION, '>=' ) ) {
			return;
		}

		self::install();
	}

	private static function migrate_to_1_1_0(): void {
		global $wpdb;

		$evidence = Schema::table_name( 'evidence' );
		$results  = Schema::table_name( 'results' );

		$wpdb->query(
			"UPDATE {$evidence}
			SET source = test_type
			WHERE ( source IS NULL OR source = '' ) AND test_type <> ''"
		);

		$wpdb->query(
			"UPDATE {$evidence}
			SET test_method = 'static'
			WHERE test_method IS NULL OR test_method = ''"
		);

		$wpdb->query(
			"UPDATE {$evidence} AS e
			INNER JOIN {$results} AS r ON r.audit_id = e.audit_id AND r.control_id = e.control_id
			SET e.control_title = r.title
			WHERE e.control_title IS NULL OR e.control_title = ''"
		);

		$wpdb->query(
			"UPDATE {$evidence}
			SET control_title = control_id
			WHERE control_title IS NULL OR control_title = ''"
		);
	}
}
