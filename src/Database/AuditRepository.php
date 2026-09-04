<?php

declare(strict_types=1);

namespace ComplyOps\Database;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Audit\AuditRun;
use ComplyOps\Audit\AuditStatus;
use ComplyOps\Audit\AuditSummary;
use ComplyOps\Audit\AuditTrigger;
use ComplyOps\Evidence\EvidenceSanitizer;
use RuntimeException;

/**
 * Persists audit run metadata.
 */
final class AuditRepository {

	public function __construct(
		private readonly EvidenceSanitizer $sanitizer = new EvidenceSanitizer(),
	) {
	}

	public function create_running(
		string $framework,
		AuditTrigger $trigger,
		?array $discovery_snapshot = null,
	): int {
		global $wpdb;

		$table = Schema::table_name( 'audits' );
		$now   = current_time( 'mysql', true );

		$persisted_snapshot = null !== $discovery_snapshot
			? $this->sanitizer->sanitize_snapshot( $discovery_snapshot )
			: null;
		$inserted = $wpdb->insert(
			$table,
			array(
				'framework'           => $framework,
				'status'              => AuditStatus::Running->value,
				'trigger_source'      => $trigger->value,
				'discovery_snapshot'  => null !== $persisted_snapshot ? wp_json_encode( $persisted_snapshot ) : null,
				'site_url'            => function_exists( 'home_url' ) ? home_url() : null,
				'plugin_version'      => defined( 'COMPLYOPS_VERSION' ) ? COMPLYOPS_VERSION : null,
				'started_at'          => $now,
				'created_at'          => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted || (int) $wpdb->insert_id < 1 ) {
			throw new RuntimeException( 'Could not create the audit record.' );
		}

		return (int) $wpdb->insert_id;
	}

	public function complete( int $audit_id, AuditSummary $summary ): void {
		global $wpdb;

		$table = Schema::table_name( 'audits' );
		$now   = current_time( 'mysql', true );

		$updated = $wpdb->update(
			$table,
			array_merge(
				$summary->to_counts_array(),
				array(
					'status'       => AuditStatus::Completed->value,
					'completed_at' => $now,
				)
			),
			array( 'id' => $audit_id ),
			null,
			array( '%d' )
		);

		if ( false === $updated ) {
			throw new RuntimeException( 'Could not complete the audit record.' );
		}
	}

	public function mark_failed( int $audit_id ): void {
		global $wpdb;

		$table = Schema::table_name( 'audits' );

		$updated = $wpdb->update(
			$table,
			array(
				'status'       => AuditStatus::Failed->value,
				'completed_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $audit_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			throw new RuntimeException( 'Could not mark the audit as failed.' );
		}
	}

	public function find( int $audit_id ): ?AuditRun {
		global $wpdb;

		$table = Schema::table_name( 'audits' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $audit_id ),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return AuditRun::from_row( $row );
	}

	public function latest( ?string $framework = null ): ?AuditRun {
		global $wpdb;

		$table = Schema::table_name( 'audits' );

		if ( null !== $framework ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE framework = %s ORDER BY id DESC LIMIT 1",
					$framework
				),
				ARRAY_A
			);
		} else {
			$row = $wpdb->get_row(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT 1",
				ARRAY_A
			);
		}

		if ( ! is_array( $row ) ) {
			return null;
		}

		return AuditRun::from_row( $row );
	}

	public function latest_completed( ?string $framework = null ): ?AuditRun {
		global $wpdb;

		$table = Schema::table_name( 'audits' );

		if ( null !== $framework ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE framework = %s AND status = %s ORDER BY id DESC LIMIT 1",
					$framework,
					AuditStatus::Completed->value
				),
				ARRAY_A
			);
		} else {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT 1",
					AuditStatus::Completed->value
				),
				ARRAY_A
			);
		}

		return is_array( $row ) ? AuditRun::from_row( $row ) : null;
	}

	/**
	 * @return list<AuditRun>
	 */
	public function list( int $limit = 20, int $offset = 0, ?string $framework = null ): array {
		global $wpdb;

		$table = Schema::table_name( 'audits' );
		$limit = max( 1, min( 100, $limit ) );
		$offset = max( 0, $offset );

		if ( null !== $framework ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE framework = %s ORDER BY id DESC LIMIT %d OFFSET %d",
					$framework,
					$limit,
					$offset
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
					$limit,
					$offset
				),
				ARRAY_A
			);
		}

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static fn ( array $row ): AuditRun => AuditRun::from_row( $row ),
			$rows
		);
	}
}
