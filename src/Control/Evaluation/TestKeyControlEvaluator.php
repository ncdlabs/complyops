<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;

/**
 * Evaluates controls via shared test_key implementations.
 */
final class TestKeyControlEvaluator extends AbstractControlEvaluator {

	public function __construct(
		private readonly TestRegistry $registry,
	) {
	}

	public function supports( ControlDefinition $definition ): bool {
		return null !== $definition->test_key && $this->registry->has( $definition->test_key );
	}

	public function evaluate( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( null === $definition->test_key ) {
			return $this->unknown(
				$definition,
				__( 'Control is missing a test_key mapping.', 'complyops' ),
			);
		}

		return $this->registry->evaluate( $definition->test_key, $definition, $context );
	}
}
