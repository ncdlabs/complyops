<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation\Tests;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\Evaluation\AbstractControlEvaluator;
use ComplyOps\Control\Evaluation\EvaluationContext;

/**
 * Base for shared test handler collections (not direct control evaluators).
 */
abstract class AbstractSharedTests extends AbstractControlEvaluator {

	public function supports( ControlDefinition $definition ): bool {
		return false;
	}

	public function evaluate( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		return $this->unknown(
			$definition,
			__( 'Shared test suites are invoked through TestRegistry, not directly.', 'complyops' ),
		);
	}
}
