<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\Evaluation\ControlEvaluatorInterface;
use ComplyOps\Control\Evaluation\EvaluationContext;

/**
 * Runtime control backed by a catalog definition and evaluator.
 */
final class CatalogControl implements ControlInterface {

	private ?ControlDefinition $definition = null;

	public function __construct(
		private readonly ControlDefinition $catalog_definition,
		private readonly ControlEvaluatorInterface $evaluator,
		private readonly EvaluationContext $context = new EvaluationContext(),
	) {
	}

	public function definition(): ControlDefinition {
		if ( null === $this->definition ) {
			$this->definition = $this->catalog_definition;
		}

		return $this->definition;
	}

	public function evaluate( ?EvaluationContext $context = null ): ControlResult {
		$ctx = $context ?? $this->context;

		return $this->evaluator->evaluate( $this->catalog_definition, $ctx );
	}
}
