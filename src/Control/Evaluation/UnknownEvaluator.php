<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;

/**
 * Default evaluator used until discovery and verification are wired.
 */
final class UnknownEvaluator extends AbstractControlEvaluator {

	public function supports( ControlDefinition $definition ): bool {
		return true;
	}

	public function evaluate( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( null !== $definition->manual_review_instructions ) {
			return $this->unknown(
				$definition,
				$definition->manual_review_instructions,
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'No automated evaluator is available for this control.', 'complyops' ),
			$definition->recommended_value,
		);
	}
}
