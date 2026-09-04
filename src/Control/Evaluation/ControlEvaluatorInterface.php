<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;

/**
 * Evaluates a single control against the current environment.
 */
interface ControlEvaluatorInterface {

	public function supports( ControlDefinition $definition ): bool;

	public function evaluate( ControlDefinition $definition, EvaluationContext $context ): ControlResult;
}
