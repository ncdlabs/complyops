<?php

declare(strict_types=1);

namespace ComplyOps\Remediation\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Enforcement\PiiSettings;
use ComplyOps\Remediation\AbstractRemediationHandler;

/**
 * Enables analytics PII query-parameter filtering.
 */
final class EnablePiiFilteringRemediation extends AbstractRemediationHandler {

	public function __construct(
		private readonly PiiSettings $settings = new PiiSettings(),
	) {
	}

	public function id(): string {
		return 'enable_pii_filtering';
	}

	public function label(): string {
		return __( 'Enable PII query-parameter filtering', 'complyops' );
	}

	public function description(): string {
		return __( 'Strips configured sensitive URL parameters before analytics page-location data is sent.', 'complyops' );
	}

	public function control_ids(): array {
		return array(
			'GDPR-PII-001',
			'GDPR-PII-002',
		);
	}

	public function capture_state(): array {
		return array(
			'pii' => $this->settings->all(),
		);
	}

	public function apply(): array {
		$this->settings->save( $this->settings->recommended_profile() );

		return array(
			'pii' => $this->settings->all(),
		);
	}

	public function rollback( array $before_state ): void {
		$pii = $before_state['pii'] ?? null;

		if ( is_array( $pii ) ) {
			$this->settings->save( $pii );
		}
	}

	public function preview_changes(): array {
		return array(
			array(
				'key'    => 'pii.enabled',
				'label'  => __( 'PII filtering', 'complyops' ),
				'before' => $this->settings->is_enabled(),
				'after'  => true,
			),
			array(
				'key'    => 'pii.blocked_parameters',
				'label'  => __( 'Blocked query parameters', 'complyops' ),
				'before' => count( $this->settings->blocked_parameters() ),
				'after'  => count( $this->settings->recommended_profile()['blocked_parameters'] ),
			),
		);
	}
}
