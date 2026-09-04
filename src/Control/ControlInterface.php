<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\Evaluation\EvaluationContext;

/**
 * Contract for a runnable control evaluation.
 */
interface ControlInterface {

	public function definition(): ControlDefinition;

	/**
	 * Evaluate the current site state against this control.
	 *
	 * Must return UNKNOWN when verification is not possible — never PASS by default.
	 */
	public function evaluate( ?EvaluationContext $context = null ): ControlResult;
}
