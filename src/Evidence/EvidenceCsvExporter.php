<?php

declare(strict_types=1);

namespace ComplyOps\Evidence;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats evidence packages for CSV export.
 */
final class EvidenceCsvExporter {

	/**
	 * @param array<string, mixed> $package
	 */
	public function export( array $package ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://temp in-memory buffer for CSV export.
		$handle = fopen( 'php://temp', 'r+' );

		if ( false === $handle ) {
			return '';
		}

		fputcsv(
			$handle,
			array(
				'id',
				'source',
				'test_method',
				'control_id',
				'control_title',
				'status',
				'severity',
				'observation',
				'expected',
				'actual',
				'verification_status',
				'audit_id',
				'remediation_id',
				'site_url',
				'plugin_version',
				'created_at',
			),
			',',
			'"',
			''
		);

		$records = $package['records'] ?? array();

		if ( is_array( $records ) ) {
			foreach ( $records as $record ) {
				if ( ! is_array( $record ) ) {
					continue;
				}

				fputcsv(
					$handle,
					array(
						$record['id'] ?? '',
						$record['source'] ?? '',
						$record['test_method'] ?? '',
						$record['control_id'] ?? '',
						$record['control_title'] ?? '',
						$record['status'] ?? '',
						$record['severity'] ?? '',
						$record['observation'] ?? '',
						$record['expected'] ?? '',
						$record['actual'] ?? '',
						$record['verification_status'] ?? '',
						$record['audit_id'] ?? '',
						$record['remediation_id'] ?? '',
						$record['site_url'] ?? '',
						$record['plugin_version'] ?? '',
						$record['created_at'] ?? '',
					),
					',',
					'"',
					''
				);
			}
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes php://temp handle.
		fclose( $handle );

		return is_string( $csv ) ? $csv : '';
	}
}
