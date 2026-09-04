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
 * Third-party tracking, analytics, and advertising checks.
 */
final class TrackingTests extends AbstractSharedTests {

	use RuntimeVerificationHelpers;

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'tracking.ga_blocked_pre_consent'        => array( $this, 'ga_blocked_pre_consent' ),
			'tracking.gtm_blocked_pre_consent'       => array( $this, 'gtm_blocked_pre_consent' ),
			'tracking.unknown_scripts_review'      => array( $this, 'unknown_scripts_review' ),
			'tracking.cookie_inventory'              => array( $this, 'cookie_inventory' ),
			'tracking.ad_defaults_denied'            => array( $this, 'ad_defaults_denied' ),
			'tracking.ga_consent_mode'               => array( $this, 'ga_consent_mode' ),
			'tracking.ga_analytics_storage_denied'   => fn ( $d, $c ) => $this->ga_consent_default( $d, $c, 'analytics_storage' ),
			'tracking.ga_ad_storage_denied'          => fn ( $d, $c ) => $this->ga_consent_default( $d, $c, 'ad_storage' ),
			'tracking.ga_ad_user_data_denied'        => fn ( $d, $c ) => $this->ga_consent_default( $d, $c, 'ad_user_data' ),
			'tracking.ga_ad_personalization_denied'  => fn ( $d, $c ) => $this->ga_consent_default( $d, $c, 'ad_personalization' ),
			'tracking.ga_duplicate_implementation'   => array( $this, 'ga_duplicate_implementation' ),
			'tracking.gpc_honored'                   => array( $this, 'gpc_honored' ),
		);
	}

	public function ga_blocked_pre_consent( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_integration( 'google_analytics' ) ) {
			return $this->not_applicable(
				$definition,
				__( 'Google Analytics was not detected on this site.', 'complyops' )
			);
		}

		if ( $context->browser_verification_available() ) {
			if ( ! $context->browser_fresh_has_tracking() && ! $context->browser_fresh_has_ga_cookie() ) {
				return $this->pass(
					$definition,
					__( 'Fresh-visitor browser verification found no Google Analytics activity before consent.', 'complyops' ),
					$definition->recommended_value,
				);
			}

			return $this->fail(
				$definition,
				__( 'Fresh-visitor browser verification detected Google Analytics cookies or requests before consent.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( $context->native_consent_active() && $context->enforcement_active() ) {
			return $this->pass(
				$definition,
				__( 'ComplyOps enforcement layer is active for Google Analytics.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->runtime_unknown(
			$definition,
			$context,
			sprintf(
				/* translators: %s: integration label */
				__( '%s detected; runtime consent verification is required.', 'complyops' ),
				$context->integration_label( 'google_analytics' ) ?? 'Google Analytics'
			)
		);
	}

	public function gtm_blocked_pre_consent( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_integration( 'google_tag_manager' ) ) {
			return $this->not_applicable(
				$definition,
				__( 'Google Tag Manager was not detected on this site.', 'complyops' )
			);
		}

		if ( $context->browser_verification_available() ) {
			if ( ! $context->browser_fresh_has_tracking() ) {
				return $this->pass(
					$definition,
					__( 'Fresh-visitor browser verification found no Google Tag Manager activity before consent.', 'complyops' ),
					$definition->recommended_value,
				);
			}

			return $this->fail(
				$definition,
				__( 'Fresh-visitor browser verification detected Google Tag Manager activity before consent.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( $context->native_consent_active() && $context->enforcement_active() ) {
			return $this->pass(
				$definition,
				__( 'ComplyOps enforcement layer is active for Google Tag Manager.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->runtime_unknown(
			$definition,
			$context,
			sprintf(
				/* translators: %s: integration label */
				__( '%s detected; runtime consent-gating verification is required.', 'complyops' ),
				$context->integration_label( 'google_tag_manager' ) ?? 'Google Tag Manager'
			)
		);
	}

	public function unknown_scripts_review( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		$unknown = $context->unknown_hosts();

		if ( array() === $unknown ) {
			return $this->pass(
				$definition,
				__( 'No unknown third-party script hosts were detected on the homepage scan.', 'complyops' ),
				__( 'All external tracking scripts are identified or flagged for review.', 'complyops' ),
			);
		}

		$review_count = $context->third_party_review_count();

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: 1: comma-separated hostnames, 2: number of review items */
				__( 'Unknown third-party hosts detected: %1$s (%2$d flagged for administrator review).', 'complyops' ),
				implode( ', ', array_slice( $unknown, 0, 5 ) ),
				max( $review_count, count( $unknown ) )
			),
			$definition->recommended_value,
		);
	}

	public function cookie_inventory( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( $context->browser_verification_available() ) {
			$cookies = $context->browser_cookie_names( 'fresh_visitor' );

			return $this->pass(
				$definition,
				sprintf(
					/* translators: %s: comma-separated cookie names */
					__( 'Browser verification inventoried cookies on a fresh visit: %s.', 'complyops' ),
					array() === $cookies ? __( 'none', 'complyops' ) : implode( ', ', $cookies )
				),
				$definition->recommended_value,
			);
		}

		$script_count = (int) ( $context->discovery['html_scan']['script_count'] ?? 0 );
		$observed     = sprintf(
			/* translators: %d: number of external scripts */
			__( 'Homepage scan found %d external script references; cookie inventory requires browser verification.', 'complyops' ),
			$script_count
		);

		if ( ControlCapability::Informational === $definition->capability ) {
			return $this->info( $definition, $observed, $definition->recommended_value );
		}

		return $this->unknown( $definition, $observed, $definition->recommended_value );
	}

	public function ad_defaults_denied( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_integration( 'google_analytics' ) && ! $context->has_integration( 'meta_pixel' ) ) {
			return $this->not_applicable(
				$definition,
				__( 'No advertising integrations were detected.', 'complyops' )
			);
		}

		if ( $context->browser_verification_available() ) {
			if ( ! $context->browser_fresh_has_tracking() && ! $context->browser_fresh_has_ga_cookie() ) {
				return $this->pass(
					$definition,
					__( 'Fresh-visitor browser verification found no advertising activity before consent.', 'complyops' ),
					$definition->recommended_value,
				);
			}

			return $this->fail(
				$definition,
				__( 'Fresh-visitor browser verification detected advertising activity before consent.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( $context->native_consent_active() && $context->enforcement_consent_mode() ) {
			return $this->pass(
				$definition,
				__( 'ComplyOps Consent Mode defaults advertising signals to denied.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->runtime_unknown(
			$definition,
			$context,
			__( 'Advertising integrations detected; runtime verification of default consent states is required.', 'complyops' )
		);
	}

	public function ga_consent_mode( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_integration( 'google_analytics' ) ) {
			return $this->not_applicable(
				$definition,
				__( 'Google Analytics was not detected on this site.', 'complyops' )
			);
		}

		if ( $context->browser_verification_available() ) {
			if ( ! $context->browser_fresh_has_tracking() && ! $context->browser_fresh_has_ga_cookie() ) {
				return $this->pass(
					$definition,
					__( 'Fresh-visitor browser verification found no Google Analytics activity before consent.', 'complyops' ),
					$definition->recommended_value,
				);
			}

			return $this->fail(
				$definition,
				__( 'Fresh-visitor browser verification detected Google Analytics activity before consent.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( $context->native_consent_active() && $context->enforcement_active() && $context->enforcement_consent_mode() ) {
			return $this->pass(
				$definition,
				__( 'ComplyOps injects Google Consent Mode v2 defaults before tags load.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->runtime_unknown(
			$definition,
			$context,
			__( 'Google Analytics detected; Consent Mode v2 configuration requires runtime verification.', 'complyops' )
		);
	}

	public function ga_consent_default( ControlDefinition $definition, EvaluationContext $context, string $consent_type ): ControlResult {
		if ( ! $context->has_integration( 'google_analytics' ) ) {
			return $this->not_applicable(
				$definition,
				__( 'Google Analytics was not detected on this site.', 'complyops' )
			);
		}

		if ( $context->browser_verification_available() ) {
			if ( ! $context->browser_fresh_has_tracking() && ! $context->browser_fresh_has_ga_cookie() ) {
				return $this->pass(
					$definition,
					sprintf(
						/* translators: %s: Consent Mode consent type */
						__( 'Fresh-visitor browser verification found no %s activity before consent.', 'complyops' ),
						$consent_type
					),
					$definition->recommended_value,
				);
			}

			return $this->fail(
				$definition,
				sprintf(
					/* translators: %s: Consent Mode consent type */
					__( 'Fresh-visitor browser verification detected %s activity before consent.', 'complyops' ),
					$consent_type
				),
				$definition->recommended_value,
			);
		}

		if ( $context->native_consent_active() && $context->enforcement_consent_mode() ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %s: Consent Mode consent type */
					__( 'ComplyOps Consent Mode defaults %s to denied.', 'complyops' ),
					$consent_type
				),
				$definition->recommended_value,
			);
		}

		return $this->runtime_unknown(
			$definition,
			$context,
			sprintf(
				/* translators: %s: Consent Mode consent type */
				__( 'Google Analytics detected; %s default state requires runtime verification.', 'complyops' ),
				$consent_type
			)
		);
	}

	public function ga_duplicate_implementation( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_integration( 'google_analytics' ) ) {
			return $this->not_applicable(
				$definition,
				__( 'Google Analytics was not detected on this site.', 'complyops' )
			);
		}

		$count = $context->ga_implementation_count();

		if ( $count <= 1 ) {
			return $this->pass(
				$definition,
				__( 'No duplicate GA4 measurement IDs were detected on the homepage scan.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: %d: number of GA4 measurement IDs */
				__( '%d GA4 measurement IDs detected; review for duplicate implementations.', 'complyops' ),
				$count
			),
			$definition->recommended_value,
		);
	}

	public function gpc_honored( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->browser_verification_available() ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'Global Privacy Control honor requires browser verification.', 'complyops' )
			);
		}

		$gpc = $context->browser_scenario( 'gpc_signal' );

		if ( is_array( $gpc ) && isset( $gpc['error'] ) && is_string( $gpc['error'] ) ) {
			return $this->unknown(
				$definition,
				sprintf(
					/* translators: %s: browser error message */
					__( 'Global Privacy Control verification failed: %s', 'complyops' ),
					$gpc['error']
				),
				$definition->recommended_value,
			);
		}

		if ( is_array( $gpc ) && isset( $gpc['tracking_request_count'] ) && 0 === (int) $gpc['tracking_request_count'] ) {
			return $this->pass(
				$definition,
				__( 'Browser verification detected no tracking network requests when Global Privacy Control was enabled.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( is_array( $gpc ) && ! empty( $gpc['gpc_honored'] ) ) {
			return $this->pass(
				$definition,
				__( 'Browser verification indicates Global Privacy Control is honored.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'Global Privacy Control handling could not be confirmed.', 'complyops' ),
			$definition->recommended_value,
		);
	}
}
