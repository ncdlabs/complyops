<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation\Tests;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\Evaluation\EvaluationContext;

/**
 * Shared runtime verification messaging for integration tests.
 */
trait RuntimeVerificationHelpers {

	protected function runtime_unknown( ControlDefinition $definition, EvaluationContext $context, string $observed ): ControlResult {
		if ( $context->browser_verification_available() ) {
			return $this->unknown(
				$definition,
				$observed . ' ' . __( 'Browser verification did not confirm compliance.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		$verification = $context->browser_verification();
		$error        = isset( $verification['error'] ) && is_string( $verification['error'] ) ? trim( $verification['error'] ) : '';

		if ( '' !== $error ) {
			return $this->unknown(
				$definition,
				$observed . ' ' . sprintf(
					/* translators: %s: browser verification error */
					__( 'Browser verification unavailable: %s', 'complyops' ),
					$error
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			$observed . ' ' . __( 'Browser verification is unavailable on this host.', 'complyops' ),
			$definition->recommended_value,
		);
	}
}
