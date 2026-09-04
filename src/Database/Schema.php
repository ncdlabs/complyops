<?php

declare(strict_types=1);

namespace ComplyOps\Database;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database table definitions for dbDelta.
 */
final class Schema {

	public const DB_VERSION = '1.2.0';

	public const OPTION_KEY = 'complyops_db_version';

	public static function table_name( string $suffix ): string {
		global $wpdb;

		return $wpdb->prefix . 'complyops_' . $suffix;
	}

	/**
	 * @return array<string, string>
	 */
	public static function tables(): array {
		$audits        = self::table_name( 'audits' );
		$results       = self::table_name( 'results' );
		$evidence      = self::table_name( 'evidence' );
		$remediations  = self::table_name( 'remediations' );
		$activity_log  = self::table_name( 'activity_log' );
		$expected_state = self::table_name( 'expected_state' );
		$charset_collate = self::charset_collate();

		return array(
			$audits => "CREATE TABLE {$audits} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				framework varchar(32) NOT NULL,
				status varchar(16) NOT NULL DEFAULT 'running',
				trigger_source varchar(16) NOT NULL DEFAULT 'manual',
				score smallint(5) unsigned DEFAULT NULL,
				technical_score smallint(5) unsigned DEFAULT NULL,
				evidence_score smallint(5) unsigned DEFAULT NULL,
				legal_review_score smallint(5) unsigned DEFAULT NULL,
				passed_count int(10) unsigned NOT NULL DEFAULT 0,
				warning_count int(10) unsigned NOT NULL DEFAULT 0,
				failed_count int(10) unsigned NOT NULL DEFAULT 0,
				unknown_count int(10) unsigned NOT NULL DEFAULT 0,
				not_applicable_count int(10) unsigned NOT NULL DEFAULT 0,
				manual_review_count int(10) unsigned NOT NULL DEFAULT 0,
				info_count int(10) unsigned NOT NULL DEFAULT 0,
				discovery_snapshot longtext NULL,
				site_url varchar(255) DEFAULT NULL,
				plugin_version varchar(32) DEFAULT NULL,
				started_at datetime NOT NULL,
				completed_at datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY framework (framework),
				KEY status (status),
				KEY started_at (started_at)
			) {$charset_collate};",
			$results => "CREATE TABLE {$results} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				audit_id bigint(20) unsigned NOT NULL,
				control_id varchar(64) NOT NULL,
				framework varchar(32) NOT NULL,
				category varchar(64) NOT NULL,
				title varchar(255) NOT NULL,
				status varchar(20) NOT NULL,
				severity varchar(16) NOT NULL,
				capability varchar(32) NOT NULL,
				observed longtext NOT NULL,
				expected longtext NULL,
				recommended longtext NULL,
				remediation_summary longtext NULL,
				remediation_available tinyint(1) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY audit_id (audit_id),
				KEY control_id (control_id),
				KEY status (status)
			) {$charset_collate};",
			$evidence => "CREATE TABLE {$evidence} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				audit_id bigint(20) unsigned DEFAULT NULL,
				remediation_id bigint(20) unsigned DEFAULT NULL,
				control_id varchar(64) NOT NULL,
				control_title varchar(255) NOT NULL DEFAULT '',
				framework varchar(32) NOT NULL,
				source varchar(16) NOT NULL DEFAULT 'audit',
				test_method varchar(16) NOT NULL DEFAULT 'static',
				test_type varchar(16) NOT NULL DEFAULT 'audit',
				status varchar(20) NOT NULL,
				severity varchar(16) NOT NULL,
				observation longtext NOT NULL,
				expected longtext NULL,
				actual longtext NULL,
				remediation_summary varchar(512) DEFAULT NULL,
				verification_status varchar(20) DEFAULT NULL,
				site_url varchar(255) DEFAULT NULL,
				discovery_hash char(64) DEFAULT NULL,
				discovery_snapshot longtext NULL,
				plugin_version varchar(32) DEFAULT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY audit_id (audit_id),
				KEY remediation_id (remediation_id),
				KEY control_id (control_id),
				KEY source (source),
				KEY created_at (created_at)
			) {$charset_collate};",
			$remediations => "CREATE TABLE {$remediations} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				audit_id bigint(20) unsigned DEFAULT NULL,
				control_id varchar(64) NOT NULL,
				framework varchar(32) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'planned',
				before_state longtext NULL,
				after_state longtext NULL,
				applied_by bigint(20) unsigned DEFAULT NULL,
				applied_at datetime DEFAULT NULL,
				verified_at datetime DEFAULT NULL,
				verification_status varchar(20) DEFAULT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY audit_id (audit_id),
				KEY control_id (control_id),
				KEY status (status)
			) {$charset_collate};",
			$activity_log => "CREATE TABLE {$activity_log} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned DEFAULT NULL,
				action varchar(64) NOT NULL,
				object_type varchar(32) DEFAULT NULL,
				object_id varchar(64) DEFAULT NULL,
				summary text NOT NULL,
				metadata longtext NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY action (action),
				KEY created_at (created_at)
			) {$charset_collate};",
			$expected_state => "CREATE TABLE {$expected_state} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				framework varchar(32) NOT NULL,
				state_key varchar(128) NOT NULL,
				expected_value longtext NOT NULL,
				audit_id bigint(20) unsigned DEFAULT NULL,
				captured_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY framework_state_key (framework, state_key),
				KEY audit_id (audit_id)
			) {$charset_collate};",
		);
	}

	private static function charset_collate(): string {
		global $wpdb;

		return $wpdb->get_charset_collate();
	}
}
