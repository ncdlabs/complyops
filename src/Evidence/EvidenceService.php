<?php

declare(strict_types=1);

namespace ComplyOps\Evidence;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Audit\AuditRun;
use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Database\AuditRepository;
use ComplyOps\Database\EvidenceRepository;
use ComplyOps\Database\ExpectedStateRepository;

/**
 * Orchestrates enriched evidence capture, listing, and export packages.
 */
final class EvidenceService {

	public function __construct(
		private readonly EvidenceRepository $evidence,
		private readonly ExpectedStateRepository $expected_state,
		private readonly AuditRepository $audits = new AuditRepository(),
		private readonly EvidenceSanitizer $sanitizer = new EvidenceSanitizer(),
	) {
	}

	/**
	 * @param list<array{definition: ControlDefinition, result: ControlResult}> $evaluations
	 */
	public function record_audit_evaluations(
		int $audit_id,
		string $framework,
		array $evaluations,
		?AuditRun $audit = null,
	): void {
		$audit ??= $this->audits->find( $audit_id );
		$context = $this->audit_context( $audit );

		foreach ( $evaluations as $evaluation ) {
			$definition = $evaluation['definition'];
			$result     = $evaluation['result'];

			$this->evidence->insert(
				array_merge(
					$context,
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
				)
			);
		}

		if ( null !== $audit ) {
			$this->capture_expected_state( $framework, $audit );
		}
	}

	public function record_remediation_verification(
		int $remediation_id,
		ControlDefinition $definition,
		ControlResult $result,
		string $verification_status,
		?int $audit_id = null,
	): int {
		$audit   = null !== $audit_id ? $this->audits->find( $audit_id ) : null;
		$context = $this->audit_context( $audit );

		return $this->evidence->insert(
			array_merge(
				$context,
				array(
					'audit_id'             => $audit_id,
					'remediation_id'       => $remediation_id,
					'control_id'           => $definition->id,
					'control_title'        => $definition->title,
					'framework'            => $definition->framework,
					'source'               => EvidenceSource::Remediation->value,
					'test_method'          => $definition->test_method->value,
					'test_type'            => EvidenceSource::Remediation->value,
					'status'               => $result->status->value,
					'severity'             => $result->severity->value,
					'observation'          => $result->observed,
					'expected'             => $result->expected,
					'actual'               => null,
					'verification_status'  => $verification_status,
					'remediation_summary'  => $result->remediation_summary,
					'plugin_version'       => defined( 'COMPLYOPS_VERSION' ) ? COMPLYOPS_VERSION : null,
				)
			)
		);
	}

	/**
	 * @param array<string, string> $drifts
	 */
	public function record_drift_findings(
		string $framework,
		array $drifts,
		?array $discovery_snapshot = null,
	): int {
		$context = array(
			'site_url'           => function_exists( 'home_url' ) ? home_url() : null,
			'discovery_hash'     => null !== $discovery_snapshot ? $this->hash_snapshot( $discovery_snapshot ) : null,
			'plugin_version'     => defined( 'COMPLYOPS_VERSION' ) ? COMPLYOPS_VERSION : null,
		);

		$count = 0;

		foreach ( $drifts as $state_key => $detail ) {
			$this->evidence->insert(
				array_merge(
					$context,
					array(
						'control_id'    => 'DRIFT-' . strtoupper( $state_key ),
						'control_title' => sprintf(
							/* translators: %s: configuration key */
							__( 'Configuration drift: %s', 'complyops' ),
							$state_key
						),
						'framework'     => $framework,
						'source'        => EvidenceSource::Drift->value,
						'test_method'   => EvidenceTestMethod::Static->value,
						'test_type'     => EvidenceSource::Drift->value,
						'status'        => 'FAIL',
						'severity'      => 'HIGH',
						'observation'   => $detail,
						'expected'      => null,
						'actual'        => $detail,
					)
				)
			);
			++$count;
		}

		return $count;
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return array{total: int, records: list<array<string, mixed>>, meta: array<string, mixed>}
	 */
	public function list( array $filters = array() ): array {
		$records = $this->evidence->list( $filters );

		return array(
			'total'   => $this->evidence->count( $filters ),
			'records' => array_map( array( $this, 'serialize_record' ), $records ),
			'meta'    => array(
				'site_url' => function_exists( 'home_url' ) ? home_url() : null,
			),
		);
	}

	public function find( int $id ): ?array {
		$row = $this->evidence->find( $id );

		return null === $row ? null : $this->serialize_record( $row );
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return array<string, mixed>
	 */
	public function export_package( array $filters = array() ): array {
		$framework = is_string( $filters['framework'] ?? null ) ? $filters['framework'] : 'gdpr';
		$audit     = null;

		if ( ! empty( $filters['audit_id'] ) ) {
			$audit = $this->audits->find( (int) $filters['audit_id'] );
		} else {
			$audit = $this->audits->latest( $framework );
		}

		$list_filters          = $filters;
		$list_filters['limit'] = 5000;
		$list                  = $this->list( $list_filters );
		$manual_review         = array();

		if ( null !== $audit ) {
			global $wpdb;
			$results_table = \ComplyOps\Database\Schema::table_name( 'results' );
			$rows          = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT control_id, title, status FROM {$results_table} WHERE audit_id = %d AND status = 'UNKNOWN'",
					$audit->id
				),
				ARRAY_A
			);

			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$manual_review[] = array(
						'control_id' => $row['control_id'],
						'title'      => $row['title'],
						'status'     => $row['status'],
					);
				}
			}
		}

		$audit_summary = $this->serialize_audit_for_export( $audit );

		return array(
			'exported_at'              => gmdate( 'c' ),
			'site_url'                 => function_exists( 'home_url' ) ? home_url() : null,
			'plugin_version'           => defined( 'COMPLYOPS_VERSION' ) ? COMPLYOPS_VERSION : null,
			'framework'                => $framework,
			'audit_summary'            => $audit_summary,
			'records'                  => $list['records'],
			'unresolved_manual_review' => $manual_review,
		);
	}

	/**
	 * Portable export payload for an audit (omits discovery snapshot inventory).
	 *
	 * @return array<string, mixed>|null
	 */
	public function serialize_audit_for_export( ?AuditRun $audit ): ?array {
		if ( null === $audit ) {
			return null;
		}

		$audit_summary = $audit->to_array();
		unset( $audit_summary['discovery_snapshot'] );

		return $audit_summary;
	}

	private function capture_expected_state( string $framework, AuditRun $audit ): void {
		if ( null === $audit->discovery_snapshot ) {
			return;
		}

		$entries = array(
			'discovery_hash' => $this->hash_snapshot( $audit->discovery_snapshot ),
		);

		$consent = $audit->discovery_snapshot['native_consent'] ?? null;
		if ( is_array( $consent ) ) {
			$entries['consent_enabled'] = ! empty( $consent['configured'] ) ? '1' : '0';
			$entries['consent_version'] = (string) ( $consent['version'] ?? '' );
		}

		$enforcement = $audit->discovery_snapshot['enforcement'] ?? null;
		if ( is_array( $enforcement ) ) {
			$entries['enforcement_enabled'] = ! empty( $enforcement['enabled'] ) ? '1' : '0';
		}

		$this->expected_state->replace_framework_state( $framework, $entries, $audit->id );
	}

	/**
	 * @param array<string, mixed>|null $audit
	 * @return array<string, mixed>
	 */
	private function audit_context( ?AuditRun $audit ): array {
		if ( null === $audit ) {
			return array(
				'site_url'       => function_exists( 'home_url' ) ? home_url() : null,
				'discovery_hash' => null,
			);
		}

		$snapshot = $audit->discovery_snapshot;

		return array(
			'site_url'             => $audit->site_url ?? ( function_exists( 'home_url' ) ? home_url() : null ),
			'discovery_hash'       => null !== $snapshot ? $this->hash_snapshot( $snapshot ) : null,
		);
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	public function hash_snapshot( array $snapshot ): string {
		$canonical = $this->canonical_drift_snapshot( $snapshot );
		$encoded   = wp_json_encode( $canonical );

		return hash( 'sha256', is_string( $encoded ) ? $encoded : '' );
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	public function canonical_drift_snapshot( array $snapshot ): array {
		$snapshot = $this->sanitizer->sanitize_snapshot( $snapshot );

		unset( $snapshot['captured_at'], $snapshot['browser_verification'], $snapshot['rest'] );

		if ( isset( $snapshot['html_scan'] ) && is_array( $snapshot['html_scan'] ) ) {
			unset( $snapshot['html_scan']['error'] );
		}

		$canonical = $this->canonicalize( $snapshot );

		return is_array( $canonical ) ? $canonical : array();
	}

	private function canonicalize( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( array_is_list( $value ) ) {
			$items = array_map( array( $this, 'canonicalize' ), $value );
			usort(
				$items,
				static fn ( mixed $left, mixed $right ): int => strcmp(
					(string) wp_json_encode( $left ),
					(string) wp_json_encode( $right )
				)
			);

			return $items;
		}

		ksort( $value );

		foreach ( $value as $key => $item ) {
			if ( 'captured_at' === $key ) {
				unset( $value[ $key ] );
				continue;
			}

			$value[ $key ] = $this->canonicalize( $item );
		}

		return $value;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function serialize_record( array $row ): array {
		if ( isset( $row['discovery_snapshot'] ) && is_string( $row['discovery_snapshot'] ) && '' !== $row['discovery_snapshot'] ) {
			$decoded = json_decode( $row['discovery_snapshot'], true );
			$row['discovery_snapshot'] = is_array( $decoded ) ? $decoded : null;
		}

		if ( empty( $row['source'] ) && ! empty( $row['test_type'] ) ) {
			$row['source'] = $row['test_type'];
		}

		if ( empty( $row['test_method'] ) ) {
			$row['test_method'] = EvidenceTestMethod::Static->value;
		}

		return $row;
	}
}
