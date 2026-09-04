<?php

declare(strict_types=1);

namespace ComplyOps\Database;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlResult;
use ComplyOps\Evidence\EvidenceSource;
use ComplyOps\Evidence\EvidenceTestMethod;
use RuntimeException;

/**
 * Append-only evidence persistence.
 */
final class EvidenceRepository {

	/**
	 * @param array<string, mixed> $row
	 */
	public function insert( array $row ): int {
		global $wpdb;

		$table = Schema::table_name( 'evidence' );
		$now   = current_time( 'mysql', true );

		$data = array_merge(
			array(
				'created_at' => $now,
			),
			$row
		);

		$formats = $this->formats_for( $data );

		$inserted = $wpdb->insert( $table, $data, $formats );

		if ( false === $inserted || (int) $wpdb->insert_id < 1 ) {
			throw new RuntimeException( 'Could not persist the evidence record.' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param list<array{definition: \ComplyOps\Control\ControlDefinition, result: ControlResult}> $evaluations
	 */
	public function insert_audit_evidence( int $audit_id, string $framework, array $evaluations ): void {
		foreach ( $evaluations as $evaluation ) {
			$definition = $evaluation['definition'];
			$result     = $evaluation['result'];

			$this->insert(
				array(
					'audit_id'            => $audit_id,
					'control_id'          => $definition->id,
					'control_title'       => $definition->title,
					'framework'           => $framework,
					'source'              => EvidenceSource::Audit->value,
					'test_method'         => $definition->test_method->value,
					'test_type'           => EvidenceSource::Audit->value,
					'status'              => $result->status->value,
					'severity'            => $result->severity->value,
					'observation'         => $result->observed,
					'expected'            => $result->expected,
					'actual'              => null,
					'remediation_summary' => $result->remediation_summary,
					'plugin_version'      => defined( 'COMPLYOPS_VERSION' ) ? COMPLYOPS_VERSION : null,
				)
			);
		}
	}

	public function insert_remediation_evidence(
		int $remediation_id,
		string $control_id,
		string $framework,
		ControlResult $result,
		string $verification_status,
	): void {
		$this->insert(
			array(
				'remediation_id'       => $remediation_id,
				'control_id'           => $control_id,
				'control_title'        => $control_id,
				'framework'            => $framework,
				'source'               => EvidenceSource::Remediation->value,
				'test_method'          => EvidenceTestMethod::Static->value,
				'test_type'            => EvidenceSource::Remediation->value,
				'status'               => $result->status->value,
				'severity'             => $result->severity->value,
				'observation'          => $result->observed,
				'expected'             => $result->expected,
				'actual'               => null,
				'verification_status'  => $verification_status,
				'plugin_version'       => defined( 'COMPLYOPS_VERSION' ) ? COMPLYOPS_VERSION : null,
			)
		);
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return list<array<string, mixed>>
	 */
	public function list( array $filters = array() ): array {
		global $wpdb;

		$table = Schema::table_name( 'evidence' );
		list( $where, $params ) = $this->where_clause( $filters );

		$limit  = max( 1, min( 500, (int) ( $filters['limit'] ?? 50 ) ) );
		$offset = max( 0, (int) ( $filters['offset'] ?? 0 ) );

		$query_params = array_merge( array( $table ), $params, array( $limit, $offset ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE fragments are allowlisted placeholders from where_clause().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
				...$query_params
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	public function count( array $filters = array() ): int {
		global $wpdb;

		$table = Schema::table_name( 'evidence' );
		list( $where, $params ) = $this->where_clause( $filters );

		if ( empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE fragments are allowlisted placeholders from where_clause().
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE {$where}",
					$table
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE fragments are allowlisted placeholders from where_clause().
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE {$where}",
					$table,
					...$params
				)
			);
		}

		return (int) $count;
	}

	public function find( int $id ): ?array {
		global $wpdb;

		$table = Schema::table_name( 'evidence' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function for_audit( int $audit_id, int $limit = 100, int $offset = 0 ): array {
		return $this->list(
			array(
				'audit_id' => $audit_id,
				'limit'    => $limit,
				'offset'   => $offset,
			)
		);
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return array{0: string, 1: list<mixed>}
	 */
	private function where_clause( array $filters ): array {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['framework'] ) && is_string( $filters['framework'] ) ) {
			$where[]  = 'framework = %s';
			$params[] = $filters['framework'];
		}

		if ( ! empty( $filters['audit_id'] ) ) {
			$where[]  = 'audit_id = %d';
			$params[] = (int) $filters['audit_id'];
		}

		if ( ! empty( $filters['source'] ) && is_string( $filters['source'] ) ) {
			$where[]  = 'source = %s';
			$params[] = $filters['source'];
		}

		if ( ! empty( $filters['test_method'] ) && is_string( $filters['test_method'] ) ) {
			$where[]  = 'test_method = %s';
			$params[] = $filters['test_method'];
		}

		if ( ! empty( $filters['control_id'] ) && is_string( $filters['control_id'] ) ) {
			$where[]  = 'control_id = %s';
			$params[] = $filters['control_id'];
		}

		if ( ! empty( $filters['since'] ) && is_string( $filters['since'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $filters['since'];
		}

		if ( ! empty( $filters['search'] ) && is_string( $filters['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where[]  = '(control_id LIKE %s OR control_title LIKE %s OR observation LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return list<string>
	 */
	private function formats_for( array $data ): array {
		$map = array(
			'audit_id'            => '%d',
			'remediation_id'      => '%d',
			'control_id'          => '%s',
			'control_title'       => '%s',
			'framework'           => '%s',
			'source'              => '%s',
			'test_method'         => '%s',
			'test_type'           => '%s',
			'status'              => '%s',
			'severity'            => '%s',
			'observation'         => '%s',
			'expected'            => '%s',
			'actual'              => '%s',
			'remediation_summary' => '%s',
			'verification_status' => '%s',
			'site_url'            => '%s',
			'discovery_hash'      => '%s',
			'discovery_snapshot'  => '%s',
			'plugin_version'      => '%s',
			'created_at'          => '%s',
		);

		$formats = array();

		foreach ( array_keys( $data ) as $key ) {
			$formats[] = $map[ $key ] ?? '%s';
		}

		return $formats;
	}
}
