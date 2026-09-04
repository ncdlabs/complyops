<?php

declare(strict_types=1);

namespace ComplyOps\Remediation\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Remediation\AbstractRemediationHandler;

use ComplyOps\Consent\ConsentSettings;

/**
 * Enables the native ComplyOps consent manager.
 */
final class EnableNativeConsentRemediation extends AbstractRemediationHandler {

	public function __construct(
		private readonly ConsentSettings $settings = new ConsentSettings(),
	) {
	}

	public function id(): string {
		return 'enable_native_consent';
	}

	public function label(): string {
		return __( 'Enable Native Consent Manager', 'complyops' );
	}

	public function description(): string {
		return __( 'Turns on the ComplyOps consent banner with analytics and marketing defaults denied.', 'complyops' );
	}

	public function control_ids(): array {
		return array(
			'GDPR-CONSENT-001',
			'GDPR-CONSENT-002',
			'GDPR-CONSENT-003',
			'GDPR-CONSENT-004',
		);
	}

	public function capture_state(): array {
		return array(
			'consent' => $this->settings->all(),
		);
	}

	public function apply(): array {
		$current = $this->settings->all();
		$this->settings->save(
			array_merge(
				$current,
				array(
					'enabled' => true,
				)
			)
		);

		return array(
			'consent' => $this->settings->all(),
		);
	}

	public function rollback( array $before_state ): void {
		$consent = $before_state['consent'] ?? null;

		if ( is_array( $consent ) ) {
			$this->settings->save( $consent );
		}
	}

	public function preview_changes(): array {
		return array(
			array(
				'key'    => 'consent.enabled',
				'label'  => __( 'Native consent manager', 'complyops' ),
				'before' => $this->settings->is_enabled(),
				'after'  => true,
			),
		);
	}
}
