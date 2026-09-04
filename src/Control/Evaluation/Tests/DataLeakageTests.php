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
 * Personal data leakage detection checks.
 */
final class DataLeakageTests extends AbstractSharedTests {

	use RuntimeVerificationHelpers;

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'data.pii_in_analytics_urls' => array( $this, 'pii_in_analytics_urls' ),
			'data.pii_in_urls'           => array( $this, 'pii_in_urls' ),
			'data.ga_pii_payload'        => array( $this, 'ga_pii_payload' ),
		);
	}

	public function pii_in_analytics_urls( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		return $this->evaluate_pii_filtering( $definition, $context );
	}

	public function pii_in_urls( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( $context->browser_verification_available() ) {
			$pii_urls = array_merge(
				$context->browser_pii_in_urls( 'fresh_visitor' ),
				$context->browser_pii_in_urls( 'analytics_granted' )
			);

			if ( array() === $pii_urls ) {
				return $this->pass(
					$definition,
					__( 'Browser verification did not detect obvious PII in request URLs.', 'complyops' ),
					$definition->recommended_value,
				);
			}

			return $this->fail(
				$definition,
				sprintf(
					/* translators: %d: number of suspicious URLs */
					__( 'Browser verification detected %d request URLs containing email-like values.', 'complyops' ),
					count( $pii_urls )
				),
				$definition->recommended_value,
			);
		}

		if ( $context->pii_filtering_active() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'ComplyOps PII query-parameter filtering is configured, but browser verification is required before this control can pass.', 'complyops' )
			);
		}

		return $this->runtime_unknown(
			$definition,
			$context,
			__( 'PII URL leakage verification requires browser testing.', 'complyops' )
		);
	}

	public function ga_pii_payload( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_integration( 'google_analytics' ) ) {
			return $this->not_applicable(
				$definition,
				__( 'Google Analytics was not detected on this site.', 'complyops' )
			);
		}

		if ( $context->browser_verification_available() ) {
			$pii_urls = $context->browser_pii_in_urls( 'analytics_granted' );

			if ( array() === $pii_urls ) {
				return $this->pass(
					$definition,
					__( 'Browser verification did not detect obvious PII in analytics request URLs.', 'complyops' ),
					$definition->recommended_value,
				);
			}

			return $this->fail(
				$definition,
				sprintf(
					/* translators: %d: number of suspicious URLs */
					__( 'Browser verification detected %d analytics request URLs containing email-like values.', 'complyops' ),
					count( $pii_urls )
				),
				$definition->recommended_value,
			);
		}

		if ( $context->pii_filtering_active() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'ComplyOps PII query-parameter filtering is configured, but browser verification is required before this control can pass.', 'complyops' )
			);
		}

		return $this->runtime_unknown(
			$definition,
			$context,
			__( 'Google Analytics detected; PII payload verification requires browser testing.', 'complyops' )
		);
	}

	private function evaluate_pii_filtering( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_integration( 'google_analytics' ) ) {
			return $this->not_applicable(
				$definition,
				__( 'Google Analytics was not detected; PII filtering control is not applicable.', 'complyops' )
			);
		}

		if ( $context->browser_verification_available() ) {
			$pii_urls = $context->browser_pii_in_urls( 'analytics_granted' );

			if ( array() === $pii_urls ) {
				return $this->pass(
					$definition,
					__( 'Browser verification did not detect obvious PII in analytics request URLs.', 'complyops' ),
					$definition->recommended_value,
				);
			}

			return $this->fail(
				$definition,
				__( 'Browser verification detected email-like values in analytics request URLs.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( $context->pii_filtering_active() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'ComplyOps PII query-parameter filtering is configured, but browser verification is required before this control can pass.', 'complyops' )
			);
		}

		return $this->runtime_unknown(
			$definition,
			$context,
			__( 'Google Analytics detected; PII URL filtering requires runtime verification.', 'complyops' )
		);
	}
}
