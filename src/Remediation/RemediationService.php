<?php

declare(strict_types=1);

namespace ComplyOps\Remediation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exceptions; messages escaped at REST/WP_Error boundaries.

use ComplyOps\Activity\ActivityLogService;
use ComplyOps\Audit\AuditService;
use ComplyOps\Control\ControlRegistry;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\ControlStatus;
use ComplyOps\Control\Evaluation\EvaluationContext;
use ComplyOps\Database\RemediationRepository;
use ComplyOps\Database\DatabaseTransaction;
use ComplyOps\Evidence\EvidenceService;
use ComplyOps\Detection\DiscoveryService;
use ComplyOps\Framework\FrameworkRegistry;
use RuntimeException;

/**
 * Plans, applies, and verifies automatic remediations.
 */
final class RemediationService {

	public function __construct(
		private readonly FrameworkRegistry $frameworks,
		private readonly AuditService $audits,
		private readonly DiscoveryService $discovery,
		private readonly RemediationRepository $remediations,
		private readonly EvidenceService $evidence,
		private readonly ActivityLogService $activity_log,
		private readonly RemediationRegistry $registry = new RemediationRegistry(),
		private readonly DatabaseTransaction $transaction = new DatabaseTransaction(),
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function plan( ?int $audit_id = null, ?string $framework_id = 'gdpr' ): array {
		$framework_id = $framework_id ?? 'gdpr';
		$findings     = $this->findings_for_plan( $audit_id, $framework_id );
		$actions      = array();
		$manual       = array();
		$resolved     = array();

		foreach ( $findings as $finding ) {
			$control_id = (string) ( $finding['control_id'] ?? '' );
			$status     = strtoupper( (string) ( $finding['status'] ?? '' ) );

			if ( ControlStatus::Pass->value === $status || ControlStatus::NotApplicable->value === $status ) {
				$resolved[] = $this->serialize_finding( $finding );
				continue;
			}

			$handlers = $this->registry->get_handlers_for_control( $control_id );

			if ( array() === $handlers ) {
				$manual[] = $this->serialize_finding( $finding );
				continue;
			}

			foreach ( $handlers as $handler ) {
				$action_id = $handler->id();

				if ( ! isset( $actions[ $action_id ] ) ) {
					$actions[ $action_id ] = array(
						'action_id'   => $action_id,
						'label'       => $handler->label(),
						'description' => $handler->description(),
						'changes'     => $handler->preview_changes(),
						'control_ids' => array(),
					);
				}

				if ( ! in_array( $control_id, $actions[ $action_id ]['control_ids'], true ) ) {
					$actions[ $action_id ]['control_ids'][] = $control_id;
				}
			}
		}

		return array(
			'audit_id'      => $audit_id,
			'framework'     => $framework_id,
			'automatic'     => array_values( $actions ),
			'manual_review' => $manual,
			'already_ok'    => $resolved,
		);
	}

	/**
	 * @param list<string> $action_ids
	 * @return array<string, mixed>
	 */
	public function apply( array $action_ids, ?int $audit_id = null, ?string $framework_id = 'gdpr' ): array {
		$framework_id   = $framework_id ?? 'gdpr';
		$applied        = array();
		$handlers       = array();
		$rollback_stack = array();

		foreach ( array_values( array_unique( $action_ids ) ) as $action_id ) {
			$handler = $this->registry->get_handler( $action_id );

			if ( null === $handler ) {
				throw new RuntimeException(
					sprintf( 'Unknown remediation action: %s', $action_id )
				);
			}

			$handlers[] = $handler;
		}

		$this->transaction->begin();

		try {
			foreach ( $handlers as $handler ) {
				$before          = $handler->capture_state();
				$remediation_ids = array();
				$rollback_stack[] = array( $handler, $before );

				foreach ( $handler->control_ids() as $control_id ) {
					$remediation_ids[ $control_id ] = $this->remediations->create_planned(
						$control_id,
						$framework_id,
						$audit_id,
						$before
					);
				}

				$after = $handler->apply();

				foreach ( $remediation_ids as $control_id => $remediation_id ) {
					$this->remediations->mark_applied( $remediation_id, $after, $this->current_user_id() );
					$verification = $this->verify_control( $control_id, $framework_id, $remediation_id, $audit_id );
					$applied[]    = $verification;
				}
			}

			if ( array() !== $applied ) {
				$this->activity_log->record(
				'remediation_applied',
				sprintf(
					/* translators: %d: number of remediated controls */
					__( 'Applied remediation to %d control(s).', 'complyops' ),
					count( $applied )
				),
				'remediation',
				null,
				array(
					'audit_id'   => $audit_id,
					'framework'  => $framework_id,
					'action_ids' => array_values( array_unique( $action_ids ) ),
				)
				);
			}

			$this->transaction->commit();
		} catch ( \Throwable $exception ) {
			try {
				$this->transaction->rollback();
			} catch ( \Throwable ) {
				// Continue with compensating state rollback.
			}

			foreach ( array_reverse( $rollback_stack ) as [ $handler, $before ] ) {
				try {
					$handler->rollback( $before );
				} catch ( \Throwable ) {
					// Preserve the original exception for the REST boundary.
				}
			}

			throw $exception;
		}

		return array(
			'audit_id'  => $audit_id,
			'framework' => $framework_id,
			'applied'   => $applied,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function apply_control( string $control_id, ?int $audit_id = null, ?string $framework_id = 'gdpr' ): array {
		$handlers = $this->registry->get_handlers_for_control( $control_id );

		if ( array() === $handlers ) {
			throw new RuntimeException(
				sprintf( 'No automatic remediation is available for control %s.', $control_id )
			);
		}

		return $this->apply(
			array_map( static fn ( RemediationHandlerInterface $handler ): string => $handler->id(), $handlers ),
			$audit_id,
			$framework_id
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function verify_control(
		string $control_id,
		string $framework_id,
		int $remediation_id,
		?int $audit_id = null,
	): array {
		$registry = $this->controls_for_framework( $framework_id );
		$control    = $registry->get( $control_id );

		if ( null === $control ) {
			throw new RuntimeException(
				sprintf( 'Unknown control: %s', $control_id )
			);
		}

		$definition = $control->definition();
		$result     = $this->evaluate_control( $control_id, $framework_id );
		$status   = $result->status->value;
		$verified = ControlStatus::Pass === $result->status ? 'pass' : 'pending';

		if ( null !== $audit_id && $audit_id > 0 ) {
			if ( ! $this->audits->update_result( $audit_id, $control_id, $result ) ) {
				throw new RuntimeException( 'Could not update the audit result after remediation.' );
			}
		}

		$this->remediations->mark_verified( $remediation_id, $verified );
		$this->evidence->record_remediation_verification(
			$remediation_id,
			$definition,
			$result,
			$verified,
			null
		);

		return array(
			'remediation_id'       => $remediation_id,
			'control_id'           => $control_id,
			'verification_status'  => $verified,
			'control_status'       => $status,
			'observed'             => $result->observed,
		);
	}

	private function evaluate_control( string $control_id, string $framework_id ): ControlResult {
		$registry = $this->controls_for_framework( $framework_id );
		$control  = $registry->get( $control_id );

		if ( null === $control ) {
			throw new RuntimeException(
				sprintf( 'Unknown control: %s', $control_id )
			);
		}

		$context = new EvaluationContext( $this->discovery->discover( true ) );

		return $control->evaluate( $context );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function findings_for_plan( ?int $audit_id, string $framework_id ): array {
		if ( null !== $audit_id ) {
			return $this->audits->findings_for( $audit_id );
		}

		$audit = $this->audits->latest( $framework_id );

		if ( null === $audit ) {
			return array();
		}

		return $this->audits->findings_for( $audit->id );
	}

	private function controls_for_framework( string $framework_id ): ControlRegistry {
		$framework = $this->frameworks->get( $framework_id );

		if ( null === $framework ) {
			throw new RuntimeException(
				sprintf( 'Unknown compliance framework: %s', $framework_id )
			);
		}

		$registry = new ControlRegistry();
		$framework->register_controls( $registry );

		return $registry;
	}

	/**
	 * @param array<string, mixed> $finding
	 * @return array<string, mixed>
	 */
	private function serialize_finding( array $finding ): array {
		return array(
			'control_id' => $finding['control_id'] ?? '',
			'title'      => $finding['title'] ?? '',
			'status'     => $finding['status'] ?? '',
			'severity'   => $finding['severity'] ?? '',
			'observed'   => $finding['observed'] ?? '',
		);
	}

	private function current_user_id(): ?int {
		if ( ! function_exists( 'get_current_user_id' ) ) {
			return null;
		}

		$user_id = get_current_user_id();

		return $user_id > 0 ? $user_id : null;
	}
}
