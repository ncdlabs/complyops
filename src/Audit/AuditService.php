<?php

declare(strict_types=1);

namespace ComplyOps\Audit;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exceptions; messages escaped at REST/WP_Error boundaries.

use ComplyOps\Activity\ActivityLogService;
use ComplyOps\Control\ApplicabilityService;
use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlOverrideService;
use ComplyOps\Control\ControlRegistry;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\Evaluation\EvaluationContext;
use ComplyOps\Database\AuditRepository;
use ComplyOps\Database\AuditResultRepository;
use ComplyOps\Database\DatabaseTransaction;
use ComplyOps\Evidence\EvidenceService;
use ComplyOps\Evidence\ManualEvidenceService;
use ComplyOps\Detection\DiscoveryService;
use ComplyOps\Framework\FrameworkRegistry;
use ComplyOps\Notification\NotificationDispatcher;
use RuntimeException;

/**
 * Orchestrates compliance audit runs.
 */
final class AuditService {

	public function __construct(
		private readonly FrameworkRegistry $frameworks,
		private readonly AuditRepository $audits,
		private readonly AuditResultRepository $results,
		private readonly EvidenceService $evidence,
		private readonly ScoreCalculator $scoring,
		private readonly DiscoveryService $discovery,
		private readonly ActivityLogService $activity_log,
		private readonly ControlOverrideService $overrides = new ControlOverrideService(),
		private readonly FindingExplanationCatalog $finding_explanations = new FindingExplanationCatalog(),
		private readonly FindingTestOutputBuilder $finding_test_output = new FindingTestOutputBuilder(),
		private readonly FindingResolveCatalog $finding_resolve = new FindingResolveCatalog(),
		private readonly NotificationDispatcher $notifications = new NotificationDispatcher(),
		private readonly DatabaseTransaction $transaction = new DatabaseTransaction(),
		private readonly ApplicabilityService $applicability = new ApplicabilityService(),
		private readonly ManualEvidenceService $manual_evidence = new ManualEvidenceService(),
	) {
	}

	public function run( string $framework_id, AuditTrigger $trigger = AuditTrigger::Manual ): AuditRun {
		$framework = $this->frameworks->get( $framework_id );

		if ( null === $framework ) {
			throw new RuntimeException(
				sprintf( 'Unknown compliance framework: %s', $framework_id )
			);
		}

		$snapshot = $this->discovery->discover( true );
		$context  = new EvaluationContext( $snapshot );

		$audit_id = $this->audits->create_running(
			$framework_id,
			$trigger,
			$snapshot
		);

		try {
			$registry    = $this->controls_for_framework( $framework_id );
			$evaluations = $this->evaluate_controls( $registry, $context, $framework_id );
			$summary     = $this->scoring->summarize( $evaluations );

			$this->transaction->begin();
			$this->results->insert_many( $audit_id, $evaluations );
			$this->audits->complete( $audit_id, $summary );
			$run = $this->audits->find( $audit_id );

			if ( null === $run ) {
				throw new RuntimeException( 'Audit was created but could not be loaded.' );
			}

			$this->evidence->record_audit_evaluations( $audit_id, $framework_id, $evaluations, $run );
			$this->activity_log->record(
				'audit_completed',
				sprintf(
					/* translators: 1: framework id, 2: audit id, 3: score */
					__( '%1$s audit #%2$d completed with a score of %3$d%%.', 'complyops' ),
					strtoupper( $framework_id ),
					$audit_id,
					(int) $summary->score
				),
				'audit',
				(string) $audit_id,
				array(
					'framework' => $framework_id,
					'score'     => $summary->score,
					'trigger'   => $trigger->value,
				)
			);
			$this->transaction->commit();
		} catch ( \Throwable $exception ) {
			if ( $this->transaction->is_active() ) {
				try {
					$this->transaction->rollback();
				} catch ( \Throwable ) {
					// Preserve the original audit failure.
				}
			}

			try {
				$this->audits->mark_failed( $audit_id );
			} catch ( \Throwable ) {
				// Preserve the original audit failure.
			}
			$this->notifications->audit_failed( $audit_id, $framework_id, $trigger );
			throw $exception;
		}

		$run = $this->audits->find( $audit_id );

		if ( null === $run ) {
			throw new RuntimeException( 'Audit completed but could not be loaded.' );
		}

		$this->notifications->audit_completed( $run );

		return $run;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function discover(): array {
		return $this->discovery->discover();
	}

	public function latest( ?string $framework_id = null ): ?AuditRun {
		return $this->audits->latest( $framework_id );
	}

	public function find( int $audit_id ): ?AuditRun {
		return $this->audits->find( $audit_id );
	}

	/**
	 * @return list<AuditRun>
	 */
	public function history( int $limit = 20, int $offset = 0, ?string $framework_id = null ): array {
		return $this->audits->list( $limit, $offset, $framework_id );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function results_for( int $audit_id ): array {
		return $this->results->for_audit( $audit_id );
	}

	public function update_result( int $audit_id, string $control_id, ControlResult $result ): bool {
		return $this->results->update_result( $audit_id, $control_id, $result );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function findings_for( int $audit_id, string $scope = 'issues' ): array {
		$findings = 'all' === $scope
			? $this->results->for_audit( $audit_id )
			: $this->results->findings_for_audit( $audit_id );
		$registry = $this->frameworks->controls();
		$audit    = $this->audits->find( $audit_id );
		$snapshot = $audit?->discovery_snapshot;

		return array_map(
			function ( array $finding ) use ( $registry, $snapshot ): array {
				$control_id  = (string) ( $finding['control_id'] ?? '' );
				$control     = $registry->get( $control_id );
				$definition  = $control?->definition();
				$finding['remediation_available'] = ! empty( $finding['remediation_available'] );
				$finding['control_description']   = $this->finding_explanations->describe_control( $definition );
				$finding['failure_explanation']   = $this->finding_explanations->explain_failure( $finding, $definition );
				$finding['explanation']           = $finding['control_description'];
				$finding['more_info']             = $this->finding_explanations->explain( $finding, $definition, true );
				$finding['test_output']           = $this->finding_test_output->build( $finding, $snapshot, $definition );
				$finding['test_method']           = $definition?->test_method->value ?? 'static';
				$resolve                          = $this->finding_resolve->resolve( $definition, $finding );
				$finding['resolve_url']           = $resolve['url'] ?? null;
				$finding['resolve_label']         = $resolve['label'] ?? null;

				if ( null !== $definition ) {
					$finding['framework']          = $definition->framework;
					$finding['verification_type']  = $definition->verification_type->value;
					$finding['applicability']      = array_merge(
						$definition->applicability->to_array(),
						$this->applicability->state_for_control( $definition->framework, $definition->id )
					);
					$manual_record                 = $this->manual_evidence->record_for_control(
						$definition->framework,
						$definition->id
					);

					if ( null !== $manual_record ) {
						$finding['manual_evidence'] = array_merge(
							$manual_record,
							array(
								'stale' => $this->manual_evidence->is_stale( $manual_record ),
							)
						);
					}
				}

				return $finding;
			},
			$findings
		);
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
	 * @return list<array{definition: ControlDefinition, result: ControlResult}>
	 */
	private function evaluate_controls( ControlRegistry $registry, EvaluationContext $context, string $framework_id ): array {
		$evaluations = array();

		foreach ( $registry->all() as $control ) {
			$definition = $control->definition();

			if ( ! $this->overrides->is_enabled( $framework_id, $definition->id ) ) {
				continue;
			}

			$evaluations[] = array(
				'definition' => $definition,
				'result'     => $control->evaluate( $context ),
			);
		}

		return $evaluations;
	}
}
