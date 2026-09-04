<?php

declare(strict_types=1);

namespace ComplyOps\Database;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RuntimeException;

/**
 * Append-only administrator activity log.
 */
final class ActivityLogRepository {

	/**
	 * @param array<string, mixed> $metadata
	 */
	public function insert(
		string $action,
		string $summary,
		?int $user_id = null,
		?string $object_type = null,
		?string $object_id = null,
		array $metadata = array(),
	): int {
		global $wpdb;

		$table = Schema::table_name( 'activity_log' );
		$now   = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'     => $user_id,
				'action'      => $action,
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'summary'     => $summary,
				'metadata'    => array() === $metadata ? null : wp_json_encode( $metadata ),
				'created_at'  => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted || (int) $wpdb->insert_id < 1 ) {
			throw new RuntimeException( 'Could not persist the activity log record.' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return list<array<string, mixed>>
	 */
	public function list( array $filters = array() ): array {
		global $wpdb;

		$table  = Schema::table_name( 'activity_log' );
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['action'] ) && is_string( $filters['action'] ) ) {
			$where[]  = 'action = %s';
			$params[] = $filters['action'];
		}

		if ( ! empty( $filters['since'] ) && is_string( $filters['since'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $filters['since'];
		}

		$limit  = max( 1, min( 500, (int) ( $filters['limit'] ?? 50 ) ) );
		$offset = max( 0, (int) ( $filters['offset'] ?? 0 ) );

		$where_sql = implode( ' AND ', $where );
		$query_params = array_merge( array( $table ), $params, array( $limit, $offset ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE fragments use fixed column placeholders only.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",
				...$query_params
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $this->decode_rows( $rows ) : array();
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	public function count( array $filters = array() ): int {
		global $wpdb;

		$table  = Schema::table_name( 'activity_log' );
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['action'] ) && is_string( $filters['action'] ) ) {
			$where[]  = 'action = %s';
			$params[] = $filters['action'];
		}

		if ( ! empty( $filters['since'] ) && is_string( $filters['since'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $filters['since'];
		}

		$where_sql = implode( ' AND ', $where );

		if ( empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE fragments use fixed column placeholders only.
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE {$where_sql}",
					$table
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE fragments use fixed column placeholders only.
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE {$where_sql}",
					$table,
					...$params
				)
			);
		}

		return (int) $count;
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<array<string, mixed>>
	 */
	private function decode_rows( array $rows ): array {
		return array_map(
			static function ( array $row ): array {
				if ( isset( $row['metadata'] ) && is_string( $row['metadata'] ) && '' !== $row['metadata'] ) {
					$decoded = json_decode( $row['metadata'], true );
					$row['metadata'] = is_array( $decoded ) ? $decoded : null;
				}

				return $row;
			},
			$rows
		);
	}
}
