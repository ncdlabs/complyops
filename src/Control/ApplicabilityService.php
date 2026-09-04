<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Framework\FrameworkPackService;

/**
 * Stores per-control applicability decisions before evaluation can fail.
 */
final class ApplicabilityService {

	public const OPTION = 'complyops_control_applicability';

	public const STATE_PENDING         = 'pending';
	public const STATE_APPLICABLE      = 'applicable';
	public const STATE_NOT_APPLICABLE  = 'not_applicable';

	public function __construct(
		private readonly FrameworkPackService $packs = new FrameworkPackService(),
	) {
	}

	/**
	 * @return array{state: string, reason: ?string, evidence_id: ?int, updated_at: ?string}
	 */
	public function state_for_control( string $framework_id, string $control_id ): array {
		$stored = $this->all();
		$key    = $this->key( $framework_id, $control_id );

		if ( ! isset( $stored[ $key ] ) || ! is_array( $stored[ $key ] ) ) {
			return array(
				'state'       => self::STATE_PENDING,
				'reason'      => null,
				'evidence_id' => null,
				'updated_at'  => null,
			);
		}

		$row = $stored[ $key ];

		return array(
			'state'       => is_string( $row['state'] ?? null ) ? (string) $row['state'] : self::STATE_PENDING,
			'reason'      => is_string( $row['reason'] ?? null ) ? (string) $row['reason'] : null,
			'evidence_id' => isset( $row['evidence_id'] ) ? (int) $row['evidence_id'] : null,
			'updated_at'  => is_string( $row['updated_at'] ?? null ) ? (string) $row['updated_at'] : null,
		);
	}

	public function requires_confirmation( ControlDefinition $definition ): bool {
		return $definition->applicability->requires_confirmation
			|| ControlVerificationType::Legal === $definition->verification_type;
	}

	public function blocks_evaluation( ControlDefinition $definition ): bool {
		if ( null !== $this->tsc_exclusion_reason( $definition ) ) {
			return false;
		}

		if ( ! $this->requires_confirmation( $definition ) ) {
			return false;
		}

		return self::STATE_PENDING === $this->state_for_control( $definition->framework, $definition->id )['state'];
	}

	public function tsc_exclusion_reason( ControlDefinition $definition ): ?string {
		if ( null === $definition->tsc || '' === $definition->tsc ) {
			return null;
		}

		$in_scope = $this->packs->tsc_in_scope( $definition->framework );

		if ( array() === $in_scope ) {
			return null;
		}

		if ( in_array( $definition->tsc, $in_scope, true ) ) {
			return null;
		}

		return sprintf(
			/* translators: %s: trust service category slug such as availability or confidentiality */
			__( 'Trust service category "%s" is not in scope for this organization.', 'complyops' ),
			$definition->tsc
		);
	}

	/**
	 * @return array{state: string, reason: ?string, evidence_id: ?int, updated_at: ?string}
	 */
	public function set_state(
		string $framework_id,
		string $control_id,
		string $state,
		?string $reason = null,
		?int $evidence_id = null,
	): array {
		if ( self::STATE_NOT_APPLICABLE === $state && ( null === $reason || '' === trim( $reason ) ) ) {
			throw new \InvalidArgumentException( 'NOT_APPLICABLE requires a reason.' );
		}

		$all = $this->all();
		$key = $this->key( $framework_id, $control_id );

		$all[ $key ] = array(
			'state'       => $state,
			'reason'      => $reason,
			'evidence_id' => $evidence_id,
			'updated_at'  => gmdate( 'Y-m-d H:i:s' ),
		);

		update_option( self::OPTION, $all, false );

		return $this->state_for_control( $framework_id, $control_id );
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
