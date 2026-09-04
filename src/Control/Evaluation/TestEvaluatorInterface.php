<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;

/**
 * A reusable shared test implementation keyed by test_key.
 */
interface TestEvaluatorInterface {

	public function test_key(): string;

	public function evaluate( ControlDefinition $definition, EvaluationContext $context ): ControlResult;
}
