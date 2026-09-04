<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation\Tests;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlCapability;
use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\Evaluation\AbstractControlEvaluator;
use ComplyOps\Control\Evaluation\EvaluationContext;

/**
 * Third-party embed gating checks.
 */
final class EmbedTests extends AbstractSharedTests {

	use RuntimeVerificationHelpers;

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'embed.youtube_gated'           => array( $this, 'youtube_gated' ),
			'embed.third_party_gated'       => array( $this, 'youtube_gated' ),
			'embed.third_party_inventory'   => array( $this, 'third_party_inventory' ),
		);
	}

	public function youtube_gated( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_integration( 'youtube' ) ) {
			return $this->not_applicable(
				$definition,
				__( 'YouTube embeds were not detected.', 'complyops' )
			);
		}

		if ( $context->browser_verification_available() ) {
			if ( $context->browser_youtube_gated() ) {
				return $this->pass(
					$definition,
					__( 'Browser verification confirmed YouTube embeds are replaced with ComplyOps placeholders before consent.', 'complyops' ),
					$definition->recommended_value,
				);
			}

			return $this->fail(
				$definition,
				__( 'Browser verification found live YouTube iframes before External Media consent.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( $context->native_consent_active() && $context->enforcement_active() && $context->enforcement_youtube_gate() ) {
			return $this->pass(
				$definition,
				__( 'ComplyOps replaces YouTube iframes with placeholders until External Media consent is granted.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->runtime_unknown(
			$definition,
			$context,
			__( 'YouTube embeds detected; runtime verification of External Media gating is required.', 'complyops' )
		);
	}

	public function third_party_inventory( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		$detected = array();

		if ( $context->has_integration( 'youtube' ) ) {
			$detected[] = 'YouTube';
		}

		if ( $context->has_integration( 'vimeo' ) ) {
			$detected[] = 'Vimeo';
		}

		if ( array() === $detected ) {
			return $this->pass(
				$definition,
				__( 'No third-party video embeds detected on the homepage scan.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		$observed = sprintf(
			/* translators: %s: comma-separated embed providers */
			__( 'Third-party embeds detected: %s', 'complyops' ),
			implode( ', ', $detected )
		);

		if ( ControlCapability::Informational === $definition->capability ) {
			return $this->info(
				$definition,
				$observed,
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			$observed,
			$definition->recommended_value,
		);
	}
}
