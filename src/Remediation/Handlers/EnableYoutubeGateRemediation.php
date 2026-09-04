<?php

declare(strict_types=1);

namespace ComplyOps\Remediation\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Consent\ConsentSettings;
use ComplyOps\Enforcement\EnforcementSettings;
use ComplyOps\Remediation\AbstractRemediationHandler;

/**
 * Enables YouTube embed gating.
 */
final class EnableYoutubeGateRemediation extends AbstractRemediationHandler {

	public function __construct(
		private readonly EnforcementSettings $settings = new EnforcementSettings(),
		private readonly ConsentSettings $consent = new ConsentSettings(),
	) {
	}

	public function id(): string {
		return 'enable_youtube_gate';
	}

	public function label(): string {
		return __( 'Enable YouTube privacy gate', 'complyops' );
	}

	public function description(): string {
		return __( 'Enables native consent and replaces YouTube iframes with placeholders until External Media consent is granted.', 'complyops' );
	}

	public function control_ids(): array {
		return array(
			'GDPR-EMBED-001',
		);
	}

	public function capture_state(): array {
		return array(
			'consent'     => $this->consent->all(),
			'enforcement' => $this->settings->all(),
		);
	}

	public function apply(): array {
		$this->consent->save(
			array_merge(
				$this->consent->all(),
				array(
					'enabled' => true,
				)
			)
		);

		$this->settings->save(
			array_merge(
				$this->settings->all(),
				array(
					'enabled'                    => true,
					'gate_youtube_before_consent'=> true,
					'use_youtube_nocookie'       => true,
				)
			)
		);

		return array(
			'consent'     => $this->consent->all(),
			'enforcement' => $this->settings->all(),
		);
	}

	public function rollback( array $before_state ): void {
		$consent = $before_state['consent'] ?? null;

		if ( is_array( $consent ) ) {
			$this->consent->save( $consent );
		}

		$enforcement = $before_state['enforcement'] ?? null;

		if ( is_array( $enforcement ) ) {
			$this->settings->save( $enforcement );
		}
	}

	public function preview_changes(): array {
		$current = $this->settings->all();

		return array(
			array(
				'key'    => 'consent.enabled',
				'label'  => __( 'Native consent manager', 'complyops' ),
				'before' => $this->consent->is_enabled(),
				'after'  => true,
			),
			array(
				'key'    => 'enforcement.enabled',
				'label'  => __( 'Enforcement enabled', 'complyops' ),
				'before' => ! empty( $current['enabled'] ),
				'after'  => true,
			),
			array(
				'key'    => 'enforcement.gate_youtube_before_consent',
				'label'  => __( 'Gate YouTube embeds', 'complyops' ),
				'before' => ! empty( $current['gate_youtube_before_consent'] ),
				'after'  => true,
			),
		);
	}
}
