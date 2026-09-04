<?php

declare(strict_types=1);

namespace ComplyOps\Remediation\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Enforcement\EnforcementSettings;
use ComplyOps\Remediation\AbstractRemediationHandler;

/**
 * Enables GA/GTM enforcement and Consent Mode defaults.
 */
final class EnableEnforcementRemediation extends AbstractRemediationHandler {

	public function __construct(
		private readonly EnforcementSettings $settings = new EnforcementSettings(),
	) {
	}

	public function id(): string {
		return 'enable_ga_enforcement';
	}

	public function label(): string {
		return __( 'Enable GA/GTM enforcement profile', 'complyops' );
	}

	public function description(): string {
		return __( 'Enables Consent Mode v2, blocks GA/GTM before consent, and denies advertising signals by default.', 'complyops' );
	}

	public function control_ids(): array {
		return array(
			'GDPR-CONSENT-002',
			'GDPR-TRACKING-001',
			'GDPR-TRACKING-002',
			'GDPR-TRACKING-005',
			'GDPR-GA-001',
			'GDPR-GA-002',
			'GDPR-GA-003',
			'GDPR-GA-004',
			'GDPR-GA-005',
		);
	}

	public function capture_state(): array {
		return array(
			'enforcement' => $this->settings->all(),
		);
	}

	public function apply(): array {
		$this->settings->save(
			array_merge(
				$this->settings->all(),
				array(
					'enabled'                   => true,
					'consent_mode_enabled'      => true,
					'block_ga_before_consent'   => true,
					'block_gtm_before_consent'  => true,
					'deny_ad_signals_by_default'=> true,
				)
			)
		);

		return array(
			'enforcement' => $this->settings->all(),
		);
	}

	public function rollback( array $before_state ): void {
		$enforcement = $before_state['enforcement'] ?? null;

		if ( is_array( $enforcement ) ) {
			$this->settings->save( $enforcement );
		}
	}

	public function preview_changes(): array {
		$current = $this->settings->all();

		return array(
			array(
				'key'    => 'enforcement.enabled',
				'label'  => __( 'Enforcement enabled', 'complyops' ),
				'before' => ! empty( $current['enabled'] ),
				'after'  => true,
			),
			array(
				'key'    => 'enforcement.consent_mode_enabled',
				'label'  => __( 'Consent Mode v2', 'complyops' ),
				'before' => ! empty( $current['consent_mode_enabled'] ),
				'after'  => true,
			),
			array(
				'key'    => 'enforcement.block_ga_before_consent',
				'label'  => __( 'Block Google Analytics', 'complyops' ),
				'before' => ! empty( $current['block_ga_before_consent'] ),
				'after'  => true,
			),
			array(
				'key'    => 'enforcement.block_gtm_before_consent',
				'label'  => __( 'Block Google Tag Manager', 'complyops' ),
				'before' => ! empty( $current['block_gtm_before_consent'] ),
				'after'  => true,
			),
		);
	}
}
