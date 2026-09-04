<?php

declare(strict_types=1);

namespace ComplyOps\Evidence;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlVerificationType;
use ComplyOps\Database\EvidenceRepository;
use ComplyOps\Evidence\EvidenceSource;

/**
 * Manual attestation workflow for human and legal controls.
 */
final class ManualEvidenceService {

	public const OPTION = 'complyops_manual_evidence';

	public function __construct(
		private readonly EvidenceRepository $evidence = new EvidenceRepository(),
	) {
	}

	public function has_valid_attestation( ControlDefinition $definition ): bool {
		if ( ControlVerificationType::Automatic === $definition->verification_type ) {
			return false;
		}

		$record = $this->record_for_control( $definition->framework, $definition->id );

		if ( null === $record ) {
			return false;
		}

		if ( empty( $record['status'] ) || 'PASS' !== strtoupper( (string) $record['status'] ) ) {
			return false;
		}

		if ( $this->is_stale( $record ) ) {
			return false;
		}

		return true;
	}

	public function is_stale( array $record ): bool {
		$reviewed_at = isset( $record['reviewed_at'] ) && is_string( $record['reviewed_at'] )
			? strtotime( $record['reviewed_at'] )
			: false;

		if ( false === $reviewed_at ) {
			return true;
		}

		$interval_days = isset( $record['review_interval_days'] ) ? (int) $record['review_interval_days'] : 365;

		if ( $interval_days <= 0 ) {
			return false;
		}

		return ( time() - $reviewed_at ) > ( $interval_days * DAY_IN_SECONDS );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function record_for_control( string $framework_id, string $control_id ): ?array {
		$all = $this->all();
		$key = $this->key( $framework_id, $control_id );

		if ( ! isset( $all[ $key ] ) || ! is_array( $all[ $key ] ) ) {
			return null;
		}

		return $all[ $key ];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	public function save_attestation( ControlDefinition $definition, array $payload ): array {
		$status = isset( $payload['status'] ) && is_string( $payload['status'] )
			? strtoupper( $payload['status'] )
			: 'PASS';

		if ( ! in_array( $status, array( 'PASS', 'WARNING', 'FAIL' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid manual evidence status.' );
		}

		$note = isset( $payload['note'] ) && is_string( $payload['note'] ) ? trim( $payload['note'] ) : '';
		$reviewed_at = isset( $payload['reviewed_at'] ) && is_string( $payload['reviewed_at'] )
			? $payload['reviewed_at']
			: gmdate( 'Y-m-d H:i:s' );
		$interval = isset( $payload['review_interval_days'] ) ? (int) $payload['review_interval_days'] : 365;
		$attachment_id = isset( $payload['attachment_id'] ) ? (int) $payload['attachment_id'] : null;

		$record = array(
			'framework'            => $definition->framework,
			'control_id'           => $definition->id,
			'control_title'        => $definition->title,
			'status'               => $status,
			'note'                 => $note,
			'reviewed_at'          => $reviewed_at,
			'review_interval_days' => max( 0, $interval ),
			'attachment_id'        => $attachment_id,
			'updated_at'           => gmdate( 'Y-m-d H:i:s' ),
		);

		$all = $this->all();
		$all[ $this->key( $definition->framework, $definition->id ) ] = $record;
		update_option( self::OPTION, $all, false );

		$this->evidence->insert(
			array(
				'control_id'    => $definition->id,
				'control_title' => $definition->title,
				'framework'     => $definition->framework,
				'source'        => EvidenceSource::Manual->value,
				'test_method'   => 'manual',
				'test_type'     => EvidenceSource::Manual->value,
				'status'        => $status,
				'severity'      => $definition->severity->value,
				'observation'   => '' !== $note ? $note : __( 'Manual evidence attestation recorded.', 'complyops' ),
				'expected'      => $definition->recommended_value,
			)
		);

		return $record;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	private function key( string $framework_id, string $control_id ): string {
		return strtolower( $framework_id ) . ':' . $control_id;
	}
}
