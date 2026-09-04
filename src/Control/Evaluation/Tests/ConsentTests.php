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
 * Consent mechanism and preference checks.
 */
final class ConsentTests extends AbstractSharedTests {

	use RuntimeVerificationHelpers;

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'consent.mechanism_present'              => array( $this, 'mechanism_present' ),
			'consent.nonessential_blocked_pre_consent' => array( $this, 'nonessential_blocked_pre_consent' ),
			'consent.analytics_default_denied'       => fn ( $d, $c ) => $this->consent_default( $d, $c, 'analytics' ),
			'consent.marketing_default_denied'       => fn ( $d, $c ) => $this->consent_default( $d, $c, 'marketing' ),
			'consent.withdrawal_available'           => fn ( $d, $c ) => $this->consent_feature( $d, $c, 'withdrawal' ),
			'consent.preferences_persisted'          => fn ( $d, $c ) => $this->consent_feature( $d, $c, 'persistence' ),
			'consent.version_recorded'               => fn ( $d, $c ) => $this->consent_feature( $d, $c, 'versioning' ),
			'consent.categories_separated'             => fn ( $d, $c ) => $this->consent_feature( $d, $c, 'categories' ),
			'consent.accept_reject_parity'           => array( $this, 'accept_reject_parity' ),
			'consent.withdrawal_stops_processing'    => array( $this, 'withdrawal_stops_processing' ),
			'consent.record_completeness'            => array( $this, 'record_completeness' ),
		);
	}

	public function mechanism_present( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( $context->browser_verification_available() ) {
			$fresh = $context->browser_scenario( 'fresh_visitor' );

			if ( is_array( $fresh ) && ! empty( $fresh['consent_banner_visible'] ) ) {
				return $this->pass(
					$definition,
					__( 'Browser verification confirmed a consent banner is visible to fresh visitors.', 'complyops' ),
					$definition->recommended_value,
				);
			}

			if ( $context->external_cmp_active() ) {
				return $this->unknown(
					$definition,
					__( 'Third-party consent provider detected; banner visibility could not be confirmed automatically.', 'complyops' ),
					$definition->recommended_value,
				);
			}
		}

		if ( $context->external_cmp_active() ) {
			$provider = $context->consent_provider();

			return $this->runtime_unknown(
				$definition,
				$context,
				sprintf(
					/* translators: %s: consent provider id */
					__( 'Third-party consent provider detected (%s); runtime verification is required.', 'complyops' ),
					$provider ?? 'unknown'
				)
			);
		}

		if ( $context->native_consent_active() ) {
			return $this->pass(
				$definition,
				__( 'Native ComplyOps consent manager is enabled and active.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'Native consent manager is disabled or unavailable.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function nonessential_blocked_pre_consent( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( $context->browser_verification_available() ) {
			if ( ! $context->browser_fresh_has_tracking() ) {
				return $this->pass(
					$definition,
					__( 'Fresh-visitor browser verification found no nonessential tracking activity before consent.', 'complyops' ),
					$definition->recommended_value,
				);
			}

			return $this->fail(
				$definition,
				__( 'Fresh-visitor browser verification detected tracking activity before consent.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( $context->external_cmp_active() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'Third-party CMP active; runtime script blocking verification is required.', 'complyops' )
			);
		}

		if ( $context->native_consent_active() ) {
			if ( $context->enforcement_active() ) {
				return $this->pass(
					$definition,
					__( 'ComplyOps script blocker defers GA/GTM scripts until consent is granted.', 'complyops' ),
					$definition->recommended_value,
				);
			}

			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'ComplyOps consent manager active; runtime verification of script blocking is required.', 'complyops' )
			);
		}

		return $this->unknown(
			$definition,
			__( 'No active consent enforcement layer detected.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function consent_default( ControlDefinition $definition, EvaluationContext $context, string $category ): ControlResult {
		if ( $context->external_cmp_active() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				sprintf(
					/* translators: %s: consent category */
					__( 'Third-party CMP active; %s default state requires runtime verification.', 'complyops' ),
					$category
				)
			);
		}

		if ( $context->browser_verification_available() && ! $context->browser_fresh_has_tracking() ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %s: consent category */
					__( 'Fresh-visitor browser verification found no %s activity before consent.', 'complyops' ),
					$category
				),
				$definition->recommended_value,
			);
		}

		if ( $context->native_consent_active() ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %s: consent category */
					__( 'ComplyOps consent manager defaults %s to denied.', 'complyops' ),
					$category
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'Consent manager not active; default states cannot be verified.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function consent_feature( ControlDefinition $definition, EvaluationContext $context, string $feature ): ControlResult {
		if ( $context->external_cmp_active() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'Third-party CMP active; runtime verification is required.', 'complyops' )
			);
		}

		if ( ! $context->native_consent_active() ) {
			return $this->unknown(
				$definition,
				__( 'Native consent manager is not active.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		$messages = array(
			'withdrawal'  => __( 'ComplyOps consent manager supports consent withdrawal via preferences.', 'complyops' ),
			'persistence' => __( 'ComplyOps stores consent preferences client-side with localStorage.', 'complyops' ),
			'versioning'  => __( 'ComplyOps records consent policy version with visitor preferences.', 'complyops' ),
			'categories'  => __( 'ComplyOps consent manager exposes separated consent categories.', 'complyops' ),
		);

		return $this->pass(
			$definition,
			$messages[ $feature ] ?? __( 'ComplyOps consent feature configured.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function accept_reject_parity( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->browser_verification_available() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'Accept/reject parity requires browser verification.', 'complyops' )
			);
		}

		$fresh = $context->browser_scenario( 'fresh_visitor' );

		if ( ! is_array( $fresh ) || empty( $fresh['consent_banner_visible'] ) ) {
			return $this->unknown(
				$definition,
				__( 'Consent banner was not visible during browser verification.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( ! empty( $fresh['consent_reject_visible'] ) && ! empty( $fresh['consent_accept_visible'] ) ) {
			return $this->pass(
				$definition,
				__( 'Browser verification found both accept and reject controls visible on the consent banner.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'Accept and reject control parity could not be confirmed automatically.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function withdrawal_stops_processing( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->browser_verification_available() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'Consent withdrawal behavior requires browser verification.', 'complyops' )
			);
		}

		$withdrawn = $context->browser_scenario( 'consent_withdrawn' );

		if ( is_array( $withdrawn ) && empty( $withdrawn['tracking_detected'] ) ) {
			return $this->pass(
				$definition,
				__( 'Browser verification found no tracking after consent withdrawal.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( is_array( $withdrawn ) && ! empty( $withdrawn['tracking_detected'] ) ) {
			return $this->fail(
				$definition,
				__( 'Browser verification detected tracking after consent withdrawal.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'Consent withdrawal scenario is not available; enable browser verification.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function record_completeness( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->native_consent_active() ) {
			return $this->unknown(
				$definition,
				__( 'Native consent manager is not active.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->pass(
			$definition,
			__( 'ComplyOps records consent version, categories, and timestamp with visitor preferences.', 'complyops' ),
			$definition->recommended_value,
		);
	}
}
