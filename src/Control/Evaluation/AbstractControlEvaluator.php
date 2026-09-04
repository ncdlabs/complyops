<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlCapability;
use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\ControlStatus;
use ComplyOps\Remediation\RemediationRegistry;

/**
 * Base helper for control evaluators.
 */
abstract class AbstractControlEvaluator implements ControlEvaluatorInterface {

	private static ?RemediationRegistry $remediation_registry = null;

	protected function remediation_available( ControlDefinition $definition ): bool {
		return array() !== $this->remediation_registry()->get_handlers_for_control( $definition->id );
	}

	protected function remediation_summary( ControlDefinition $definition ): ?string {
		if ( ! $this->remediation_available( $definition ) ) {
			return null;
		}

		return $this->remediation_registry()->summary_for_control( $definition->id );
	}

	private function remediation_registry(): RemediationRegistry {
		if ( null === self::$remediation_registry ) {
			self::$remediation_registry = new RemediationRegistry();
		}

		return self::$remediation_registry;
	}

	protected function unknown(
		ControlDefinition $definition,
		string $observed,
		?string $expected = null,
		?string $recommended = null,
	): ControlResult {
		return new ControlResult(
			control_id: $definition->id,
			status: ControlStatus::Unknown,
			severity: $definition->severity,
			observed: $observed,
			expected: $expected ?? $definition->recommended_value,
			recommended: $recommended ?? $definition->recommended_value,
			remediation_summary: $this->remediation_summary( $definition ),
			remediation_available: $this->remediation_available( $definition ),
		);
	}

	protected function pass(
		ControlDefinition $definition,
		string $observed,
		?string $expected = null,
	): ControlResult {
		return new ControlResult(
			control_id: $definition->id,
			status: ControlStatus::Pass,
			severity: $definition->severity,
			observed: $observed,
			expected: $expected ?? $definition->recommended_value,
			recommended: $definition->recommended_value,
			remediation_summary: $this->remediation_summary( $definition ),
			remediation_available: $this->remediation_available( $definition ),
		);
	}

	protected function fail(
		ControlDefinition $definition,
		string $observed,
		?string $expected = null,
	): ControlResult {
		return new ControlResult(
			control_id: $definition->id,
			status: ControlStatus::Fail,
			severity: $definition->severity,
			observed: $observed,
			expected: $expected ?? $definition->recommended_value,
			recommended: $definition->recommended_value,
			remediation_summary: $this->remediation_summary( $definition ),
			remediation_available: $this->remediation_available( $definition ),
		);
	}

	protected function not_applicable( ControlDefinition $definition, string $observed ): ControlResult {
		return new ControlResult(
			control_id: $definition->id,
			status: ControlStatus::NotApplicable,
			severity: $definition->severity,
			observed: $observed,
		);
	}

	protected function info(
		ControlDefinition $definition,
		string $observed,
		?string $expected = null,
		?string $recommended = null,
	): ControlResult {
		return new ControlResult(
			control_id: $definition->id,
			status: ControlStatus::Info,
			severity: $definition->severity,
			observed: $observed,
			expected: $expected ?? $definition->recommended_value,
			recommended: $recommended ?? $definition->recommended_value,
		);
	}
}
