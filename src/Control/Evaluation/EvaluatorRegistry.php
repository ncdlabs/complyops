<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\Evaluation\IntegrationControlEvaluator;
use ComplyOps\Control\Evaluation\WordPress\WordPressControlEvaluator;

/**
 * Resolves the most specific evaluator for a control definition.
 */
final class EvaluatorRegistry {

	/** @var list<ControlEvaluatorInterface> */
	private array $evaluators;

	private UnknownEvaluator $fallback;

	public function __construct() {
		$this->fallback    = new UnknownEvaluator();
		$this->evaluators  = array(
			new TestKeyControlEvaluator( TestRegistry::instance() ),
			new WordPressControlEvaluator(),
			new IntegrationControlEvaluator(),
		);
	}

	public function for_control( ControlDefinition $definition ): ControlEvaluatorInterface {
		foreach ( $this->evaluators as $evaluator ) {
			if ( $evaluator->supports( $definition ) && ! ( $evaluator instanceof UnknownEvaluator ) ) {
				return $evaluator;
			}
		}

		return $this->fallback;
	}
}
