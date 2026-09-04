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
 * Runtime exceptional condition and security header checks.
 */
final class RuntimeTests extends AbstractSharedTests {

	use RuntimeVerificationHelpers;

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'runtime.php_error_leakage'   => array( $this, 'php_error_leakage' ),
			'runtime.http_500_pattern'    => array( $this, 'http_500_pattern' ),
			'runtime.failed_cron_jobs'    => array( $this, 'failed_cron_jobs' ),
			'transport.hsts_present'      => array( $this, 'hsts_present' ),
			'transport.csp_present'       => array( $this, 'csp_present' ),
			'transport.security_headers'  => array( $this, 'security_headers' ),
		);
	}

	public function php_error_leakage( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return $this->fail(
				$definition,
				__( 'WP_DEBUG may expose PHP errors to visitors.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
			return $this->fail(
				$definition,
				__( 'WP_DEBUG_DISPLAY is enabled.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->pass(
			$definition,
			__( 'WordPress debug display settings do not expose errors publicly.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function http_500_pattern( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		return $this->unknown(
			$definition,
			__( 'Repeated HTTP 500 responses require external monitoring review.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function failed_cron_jobs( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return $this->unknown(
				$definition,
				__( 'WP-Cron is disabled; verify system cron executes scheduled tasks.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		$missed = (int) get_option( 'complyops_missed_cron_count', 0 );

		if ( $missed > 0 ) {
			return $this->unknown(
				$definition,
				sprintf(
					/* translators: %d: missed cron count */
					__( '%d missed cron events recorded; review scheduled task health.', 'complyops' ),
					$missed
				),
				$definition->recommended_value,
			);
		}

		return $this->pass(
			$definition,
			__( 'No missed cron events were recorded by ComplyOps.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function hsts_present( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		return $this->header_check( $definition, $context, 'strict-transport-security', 'HSTS' );
	}

	public function csp_present( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		return $this->header_check( $definition, $context, 'content-security-policy', 'Content-Security-Policy' );
	}

	public function security_headers( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->browser_verification_available() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'Security header verification requires browser testing.', 'complyops' )
			);
		}

		$headers = $context->browser_security_headers();

		if ( ! is_array( $headers ) || array() === $headers ) {
			return $this->unknown(
				$definition,
				__( 'Security headers were not captured during browser verification.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		$missing = array();

		foreach ( array( 'strict-transport-security', 'x-frame-options', 'referrer-policy' ) as $header ) {
			if ( empty( $headers[ $header ] ) ) {
				$missing[] = $header;
			}
		}

		if ( array() === $missing ) {
			return $this->pass(
				$definition,
				__( 'Browser verification captured HSTS, framing, and referrer-policy headers.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: %s: comma-separated header names */
				__( 'Missing or empty security headers: %s.', 'complyops' ),
				implode( ', ', $missing )
			),
			$definition->recommended_value,
		);
	}

	private function header_check(
		ControlDefinition $definition,
		EvaluationContext $context,
		string $header_key,
		string $label,
	): ControlResult {
		if ( ! $context->browser_verification_available() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				sprintf(
					/* translators: %s: header label */
					__( '%s header verification requires browser testing.', 'complyops' ),
					$label
				)
			);
		}

		$headers = $context->browser_security_headers();

		if ( is_array( $headers ) && ! empty( $headers[ $header_key ] ) ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %s: header label */
					__( 'Browser verification detected %s response header.', 'complyops' ),
					$label
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: %s: header label */
				__( '%s response header was not detected.', 'complyops' ),
				$label
			),
			$definition->recommended_value,
		);
	}
}
