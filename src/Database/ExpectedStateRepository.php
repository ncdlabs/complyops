<?php

declare(strict_types=1);

namespace ComplyOps\Database;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RuntimeException;

/**
 * Stores expected compliance configuration for drift detection.
 */
final class ExpectedStateRepository {

	/**
	 * @param array<string, string> $entries
	 */
	public function replace_framework_state( string $framework, array $entries, ?int $audit_id = null ): void {
		global $wpdb;

		$table = Schema::table_name( 'expected_state' );
		$now   = current_time( 'mysql', true );

		$deleted = $wpdb->delete(
			$table,
			array( 'framework' => $framework ),
			array( '%s' )
		);

		if ( false === $deleted ) {
			throw new RuntimeException( 'Could not clear the previous expected state.' );
		}

		foreach ( $entries as $state_key => $expected_value ) {
			$inserted = $wpdb->insert(
				$table,
				array(
					'framework'      => $framework,
					'state_key'      => $state_key,
					'expected_value' => $expected_value,
					'audit_id'       => $audit_id,
					'captured_at'    => $now,
				),
				array( '%s', '%s', '%s', '%d', '%s' )
			);

			if ( false === $inserted || (int) $wpdb->insert_id < 1 ) {
				throw new RuntimeException( 'Could not persist the expected state.' );
			}
		}
	}

	/**
	 * @return array<string, string>
	 */
	public function for_framework( string $framework ): array {
		global $wpdb;

		$table = Schema::table_name( 'expected_state' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT state_key, expected_value FROM {$table} WHERE framework = %s ORDER BY state_key ASC",
				$framework
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$entries = array();

		foreach ( $rows as $row ) {
			$entries[ (string) $row['state_key'] ] = (string) $row['expected_value'];
		}

		return $entries;
	}
}
