<?php

declare(strict_types=1);

namespace ComplyOps\Database;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use RuntimeException;

/**
 * Persists per-control audit results.
 */
final class AuditResultRepository {

	/**
	 * @param list<array{definition: ControlDefinition, result: ControlResult}> $evaluations
	 */
	public function insert_many( int $audit_id, array $evaluations ): void {
		global $wpdb;

		$table = Schema::table_name( 'results' );
		$now   = current_time( 'mysql', true );

		foreach ( $evaluations as $evaluation ) {
			$definition = $evaluation['definition'];
			$result     = $evaluation['result'];

			$inserted = $wpdb->insert(
				$table,
				array(
					'audit_id'              => $audit_id,
					'control_id'            => $definition->id,
					'framework'             => $definition->framework,
					'category'              => $definition->category,
					'title'                 => $definition->title,
					'status'                => $result->status->value,
					'severity'              => $result->severity->value,
					'capability'            => $definition->capability->value,
					'observed'              => $result->observed,
					'expected'              => $result->expected,
					'recommended'           => $result->recommended,
					'remediation_summary'   => $result->remediation_summary,
					'remediation_available' => $result->remediation_available ? 1 : 0,
					'created_at'            => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);

			if ( false === $inserted || (int) $wpdb->insert_id < 1 ) {
				throw new RuntimeException( 'Could not persist the audit result.' );
			}
		}
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function for_audit( int $audit_id ): array {
		global $wpdb;

		$table = Schema::table_name( 'results' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE audit_id = %d ORDER BY control_id ASC",
				$audit_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	public function update_result( int $audit_id, string $control_id, ControlResult $result ): bool {
		global $wpdb;

		$table = Schema::table_name( 'results' );
		$updated = $wpdb->update(
			$table,
			array(
				'status'   => $result->status->value,
				'observed' => $result->observed,
			),
			array(
				'audit_id'   => $audit_id,
				'control_id' => $control_id,
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		return false !== $updated;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function findings_for_audit( int $audit_id ): array {
		global $wpdb;

		$table = Schema::table_name( 'results' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE audit_id = %d AND status IN ('FAIL', 'WARNING', 'UNKNOWN') ORDER BY FIELD(severity, 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'INFO'), control_id ASC",
				$audit_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<string, int>
	 */
	public function count_failed_by_severity( int $audit_id ): array {
		global $wpdb;

		$table = Schema::table_name( 'results' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT severity, COUNT(*) AS total FROM {$table} WHERE audit_id = %d AND status = 'FAIL' GROUP BY severity",
				$audit_id
			),
			ARRAY_A
		);

		$counts = array(
			'CRITICAL' => 0,
			'HIGH'     => 0,
			'MEDIUM'   => 0,
			'LOW'      => 0,
			'INFO'     => 0,
		);

		if ( ! is_array( $rows ) ) {
			return $counts;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$severity = (string) ( $row['severity'] ?? '' );

			if ( isset( $counts[ $severity ] ) ) {
				$counts[ $severity ] = (int) ( $row['total'] ?? 0 );
			}
		}

		return $counts;
	}
}
