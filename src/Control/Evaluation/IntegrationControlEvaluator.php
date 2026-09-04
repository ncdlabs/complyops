<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlCapability;
use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Monitoring\MonitoringScheduler;

/**
 * Evaluates integration-dependent controls using discovery data.
 */
final class IntegrationControlEvaluator extends AbstractControlEvaluator {

	public function supports( ControlDefinition $definition ): bool {
		$id = $definition->id;

		if ( in_array( $id, array( 'SOC2-CC4-004', 'SOC2-CC4-005', 'OWASP-A09-003' ), true )
			|| 'SOC2-CC6-011' === $id
			|| 'OWASP-A02-002' === $id
			|| in_array(
				$id,
				array( 'SOC2-CC9-005', 'SOC2-C1-003', 'SOC2-P3-002', 'SOC2-P3-003', 'HIPAA-DATA-001', 'HIPAA-DATA-002', 'OWASP-A04-001' ),
				true
			) ) {
			return true;
		}

		return str_starts_with( $id, 'GDPR-GA-' )
			|| str_starts_with( $id, 'GDPR-TRACKING-' )
			|| str_starts_with( $id, 'GDPR-EMBED-' )
			|| str_starts_with( $id, 'GDPR-FORM-' )
			|| str_starts_with( $id, 'GDPR-CONSENT-' )
			|| str_starts_with( $id, 'GDPR-PII-' )
			|| str_starts_with( $id, 'GDPR-RETENTION-' );
	}

	public function evaluate( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		return match ( $definition->id ) {
			'SOC2-CC4-004', 'OWASP-A09-003' => $this->evaluate_monitoring_schedule( $definition ),
			'SOC2-CC4-005', 'GDPR-RETENTION-003' => $this->evaluate_plugin_retention( $definition, $context ),
			'SOC2-CC6-011', 'SOC2-C1-003', 'GDPR-PII-001', 'GDPR-PII-002', 'OWASP-A02-002' => $this->evaluate_pii_filtering( $definition, $context ),
			'SOC2-CC9-005', 'SOC2-P3-002', 'GDPR-FORM-001', 'HIPAA-DATA-001', 'OWASP-A04-001' => $this->evaluate_form_inventory( $definition, $context ),
			'SOC2-P3-003', 'HIPAA-DATA-002' => $this->evaluate_sensitive_fields( $definition, $context ),
			'GDPR-TRACKING-001' => $this->evaluate_ga_runtime( $definition, $context ),
			'GDPR-TRACKING-002' => $this->evaluate_gtm_runtime( $definition, $context ),
			'GDPR-TRACKING-003' => $this->evaluate_unknown_scripts( $definition, $context ),
			'GDPR-TRACKING-004' => $this->evaluate_cookie_inventory( $definition, $context ),
			'GDPR-TRACKING-005' => $this->evaluate_ad_defaults( $definition, $context ),
			'GDPR-EMBED-001'    => $this->evaluate_youtube_gate( $definition, $context ),
			'GDPR-EMBED-002'    => $this->evaluate_embed_inventory( $definition, $context ),
			'GDPR-FORM-002'     => $this->evaluate_form_marketing_consent( $definition, $context ),
			'GDPR-FORM-003'     => $this->evaluate_form_privacy_notice( $definition, $context ),
			'GDPR-FORM-004'     => $this->evaluate_sensitive_fields( $definition, $context ),
			'GDPR-FORM-005'     => $this->evaluate_form_retention( $definition, $context ),
			'GDPR-CONSENT-001'  => $this->evaluate_consent_mechanism( $definition, $context ),
			'GDPR-CONSENT-002'  => $this->evaluate_consent_blocking( $definition, $context ),
			'GDPR-CONSENT-003'  => $this->evaluate_consent_default( $definition, $context, 'analytics' ),
			'GDPR-CONSENT-004'  => $this->evaluate_consent_default( $definition, $context, 'marketing' ),
			'GDPR-CONSENT-005'  => $this->evaluate_consent_feature( $definition, $context, 'withdrawal' ),
			'GDPR-CONSENT-006'  => $this->evaluate_consent_feature( $definition, $context, 'persistence' ),
			'GDPR-CONSENT-007'  => $this->evaluate_consent_feature( $definition, $context, 'versioning' ),
			'GDPR-CONSENT-008'  => $this->evaluate_consent_feature( $definition, $context, 'categories' ),
			'GDPR-GA-001'       => $this->evaluate_ga_consent_mode( $definition, $context ),
			'GDPR-GA-002'       => $this->evaluate_ga_consent_default( $definition, $context, 'analytics_storage' ),
			'GDPR-GA-003'       => $this->evaluate_ga_consent_default( $definition, $context, 'ad_storage' ),
			'GDPR-GA-004'       => $this->evaluate_ga_consent_default( $definition, $context, 'ad_user_data' ),
			'GDPR-GA-005'       => $this->evaluate_ga_consent_default( $definition, $context, 'ad_personalization' ),
			'GDPR-GA-010'       => $this->evaluate_ga_duplicate( $definition, $context ),
			'GDPR-GA-009'       => $this->evaluate_ga_pii_payload( $definition, $context ),
			default             => $this->evaluate_integration_default( $definition, $context ),
		};
	}

	private function evaluate_monitoring_schedule( ControlDefinition $definition ): ControlResult {
		$interval = get_option( MonitoringScheduler::OPTION_INTERVAL, 'weekly' );

		if ( is_string( $interval ) && in_array( $interval, array( 'daily', 'weekly', 'monthly' ), true ) ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %s: monitoring interval */
					__( 'ComplyOps monitoring interval is set to %s.', 'complyops' ),
					$interval
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'ComplyOps monitoring interval is disabled or not configured.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	private function evaluate_ga_runtime( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_gtm_runtime( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_unknown_scripts( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_cookie_inventory( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_ad_defaults( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_youtube_gate( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_embed_inventory( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_form_inventory( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_forms() ) {
			$observed = __( 'No supported form plugins were detected.', 'complyops' );

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

		$count = $context->form_inventory_count();
		$ids   = $context->discovery['forms']['detected_ids'] ?? array();
		$ids   = is_array( $ids ) ? implode( ', ', $ids ) : '';

		if ( $count > 0 ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: 1: number of forms, 2: plugin ids */
					__( 'Inventoried %1$d forms from plugins: %2$s.', 'complyops' ),
					$count,
					$ids
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: %s: form plugin ids */
				__( 'Form plugins detected (%s); field-level inventory is pending.', 'complyops' ),
				$ids
			),
			$definition->recommended_value,
		);
	}

	private function evaluate_form_marketing_consent( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_forms() ) {
			return $this->not_applicable(
				$definition,
				__( 'No supported form plugins were detected.', 'complyops' )
			);
		}

		$summary = $context->form_inventory_summary();
		$with    = (int) ( $summary['forms_with_marketing_consent'] ?? 0 );
		$total   = max( 1, $context->form_inventory_count() );

		if ( 0 === $with ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %d: total forms inventoried */
					__( 'None of the %d inventoried forms include marketing consent fields.', 'complyops' ),
					$total
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: 1: forms with marketing fields, 2: total forms inventoried */
				__( '%1$d of %2$d inventoried forms include marketing consent fields; manual review is required to confirm they are not bundled with necessary processing.', 'complyops' ),
				$with,
				$total
			),
			$definition->manual_review_instructions ?? $definition->recommended_value,
		);
	}

	private function evaluate_form_privacy_notice( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_forms() ) {
			return $this->not_applicable(
				$definition,
				__( 'No supported form plugins were detected.', 'complyops' )
			);
		}

		$summary = $context->form_inventory_summary();
		$with    = (int) ( $summary['forms_with_privacy_acknowledgement'] ?? 0 );
		$total   = $context->form_inventory_count();

		if ( $total > 0 && $with === $total ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %d: number of forms */
					__( 'All %d inventoried forms include privacy or consent acknowledgement fields.', 'complyops' ),
					$total
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: 1: forms with privacy fields, 2: total forms */
				__( '%1$d of %2$d inventoried forms include privacy or consent acknowledgement fields; manual review is required.', 'complyops' ),
				$with,
				$total
			),
			$definition->manual_review_instructions ?? $definition->recommended_value,
		);
	}

	private function evaluate_form_retention( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_forms() ) {
			return $this->not_applicable(
				$definition,
				__( 'No supported form plugins were detected.', 'complyops' )
			);
		}

		return $this->unknown(
			$definition,
			$definition->manual_review_instructions ?? __( 'Review retention settings in supported form plugins and document periods.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	private function evaluate_sensitive_fields( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_forms() ) {
			return $this->not_applicable(
				$definition,
				__( 'No supported form plugins were detected.', 'complyops' )
			);
		}

		if ( ! $context->forms_have_sensitive_fields() ) {
			return $this->pass(
				$definition,
				__( 'No sensitive form fields were flagged in the automated inventory.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		$sensitive_count = (int) ( $context->form_inventory_summary()['sensitive_field_count'] ?? 0 );

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: %d: number of sensitive fields */
				__( '%d sensitive form fields were flagged for review.', 'complyops' ),
				$sensitive_count
			),
			$definition->recommended_value,
		);
	}

	private function evaluate_consent_mechanism( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_consent_blocking( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_consent_default(
		ControlDefinition $definition,
		EvaluationContext $context,
		string $category,
	): ControlResult {
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

	private function evaluate_consent_feature(
		ControlDefinition $definition,
		EvaluationContext $context,
		string $feature,
	): ControlResult {
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

	private function evaluate_ga_consent_mode( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_ga_consent_default(
		ControlDefinition $definition,
		EvaluationContext $context,
		string $consent_type,
	): ControlResult {
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

	private function evaluate_ga_pii_payload( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_ga_duplicate( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_plugin_retention( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		$retention = get_option( 'complyops_evidence_retention_days', null );

		if ( is_numeric( $retention ) && (int) $retention > 0 ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %d: retention days */
					__( 'Evidence retention is configured for %d days.', 'complyops' ),
					(int) $retention
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'Evidence retention policy is not yet configured.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	private function evaluate_integration_default( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( str_starts_with( $definition->id, 'GDPR-GA-' ) ) {
			if ( ! $context->has_integration( 'google_analytics' ) ) {
				return $this->not_applicable(
					$definition,
					__( 'Google Analytics was not detected on this site.', 'complyops' )
				);
			}

			if ( ControlCapability::ManualReview === $definition->capability ) {
				return $this->unknown(
					$definition,
					__( 'Google Analytics detected; manual Admin API or policy review is required.', 'complyops' ),
					$definition->recommended_value,
				);
			}

			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'Google Analytics detected; runtime or configuration verification is required.', 'complyops' )
			);
		}

		if ( str_starts_with( $definition->id, 'GDPR-FORM-' ) ) {
			if ( ! $context->has_forms() ) {
				return $this->not_applicable(
					$definition,
					__( 'No supported form plugins were detected.', 'complyops' )
				);
			}

			if ( ControlCapability::ManualReview === $definition->capability ) {
				return $this->unknown(
					$definition,
					$definition->manual_review_instructions ?? __( 'Manual form review is required.', 'complyops' ),
					$definition->recommended_value,
				);
			}
		}

		if ( str_starts_with( $definition->id, 'GDPR-PII-' )
			|| in_array( $definition->id, array( 'SOC2-CC6-011', 'OWASP-A02-002' ), true ) ) {
			if ( ! $context->has_integration( 'google_analytics' ) ) {
				return $this->not_applicable(
					$definition,
					__( 'Google Analytics was not detected; PII filtering control is not applicable.', 'complyops' )
				);
			}

			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'Google Analytics detected; PII URL filtering requires runtime verification.', 'complyops' )
			);
		}

		if ( str_starts_with( $definition->id, 'GDPR-RETENTION-' ) && 'GDPR-RETENTION-001' === $definition->id ) {
			if ( ! $context->has_integration( 'google_analytics' ) ) {
				return $this->not_applicable(
					$definition,
					__( 'Google Analytics was not detected.', 'complyops' )
				);
			}

			return $this->unknown(
				$definition,
				$definition->manual_review_instructions ?? __( 'Review GA4 retention settings.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( str_starts_with( $definition->id, 'GDPR-RETENTION-002' ) ) {
			if ( ! $context->has_forms() ) {
				return $this->not_applicable(
					$definition,
					__( 'No supported form plugins were detected.', 'complyops' )
				);
			}

			return $this->unknown(
				$definition,
				$definition->manual_review_instructions ?? __( 'Review form retention settings.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( str_starts_with( $definition->id, 'GDPR-CONSENT-' ) ) {
			return $this->runtime_unknown(
				$definition,
				$context,
				__( 'Consent control requires runtime verification.', 'complyops' )
			);
		}

		return $this->unknown(
			$definition,
			__( 'Integration control evaluation is pending.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	private function runtime_unknown( ControlDefinition $definition, EvaluationContext $context, string $observed ): ControlResult {
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
