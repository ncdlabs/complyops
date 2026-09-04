<?php

declare(strict_types=1);

namespace ComplyOps\Database;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RuntimeException;

/**
 * Persists remediation actions and outcomes.
 */
final class RemediationRepository {

	public function create_planned(
		string $control_id,
		string $framework,
		?int $audit_id,
		array $before_state,
	): int {
		global $wpdb;

		$table = Schema::table_name( 'remediations' );
		$now   = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			$table,
			array(
				'audit_id'     => $audit_id,
				'control_id'   => $control_id,
				'framework'    => $framework,
				'status'       => 'planned',
				'before_state' => wp_json_encode( $before_state ),
				'created_at'   => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted || (int) $wpdb->insert_id < 1 ) {
			throw new RuntimeException( 'Could not create the remediation record.' );
		}

		return (int) $wpdb->insert_id;
	}

	public function mark_applied(
		int $remediation_id,
		array $after_state,
		?int $applied_by = null,
	): void {
		global $wpdb;

		$table = Schema::table_name( 'remediations' );
		$now   = current_time( 'mysql', true );

		$updated = $wpdb->update(
			$table,
			array(
				'status'       => 'applied',
				'after_state'  => wp_json_encode( $after_state ),
				'applied_by'   => $applied_by,
				'applied_at'   => $now,
			),
			array( 'id' => $remediation_id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated || $updated < 1 ) {
			throw new RuntimeException( 'Could not mark the remediation as applied.' );
		}
	}

	public function mark_verified( int $remediation_id, string $verification_status ): void {
		global $wpdb;

		$table = Schema::table_name( 'remediations' );
		$now   = current_time( 'mysql', true );

		$updated = $wpdb->update(
			$table,
			array(
				'verification_status' => $verification_status,
				'verified_at'         => $now,
			),
			array( 'id' => $remediation_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated || $updated < 1 ) {
			throw new RuntimeException( 'Could not persist remediation verification.' );
		}
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find( int $remediation_id ): ?array {
		global $wpdb;

		$table = Schema::table_name( 'remediations' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $remediation_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}
}
