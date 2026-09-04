<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ApplicabilityService;
use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\ControlStatus;

/**
 * Applies applicability gates before delegating to the underlying evaluator.
 */
final class ApplicabilityGatedEvaluator implements ControlEvaluatorInterface {

	public function __construct(
		private readonly ControlEvaluatorInterface $inner,
		private readonly ApplicabilityService $applicability = new ApplicabilityService(),
	) {
	}

	public function supports( ControlDefinition $definition ): bool {
		return $this->inner->supports( $definition );
	}

	public function evaluate( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		$tsc_reason = $this->applicability->tsc_exclusion_reason( $definition );

		if ( null !== $tsc_reason ) {
			return new ControlResult(
				control_id: $definition->id,
				status: ControlStatus::NotApplicable,
				severity: $definition->severity,
				observed: $tsc_reason,
			);
		}

		if ( $this->applicability->blocks_evaluation( $definition ) ) {
			return new ControlResult(
				control_id: $definition->id,
				status: ControlStatus::Unknown,
				severity: $definition->severity,
				observed: __( 'Confirm applicability before this control can be evaluated.', 'complyops' ),
				expected: $definition->recommended_value,
				recommended: $definition->recommended_value,
			);
		}

		$state = $this->applicability->state_for_control( $definition->framework, $definition->id );

		if ( ApplicabilityService::STATE_NOT_APPLICABLE === $state['state'] ) {
			return new ControlResult(
				control_id: $definition->id,
				status: ControlStatus::NotApplicable,
				severity: $definition->severity,
				observed: $state['reason'] ?? __( 'Marked not applicable by administrator.', 'complyops' ),
			);
		}

		return $this->inner->evaluate( $definition, $context );
	}
}
