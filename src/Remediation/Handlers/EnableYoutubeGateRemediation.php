<?php

declare(strict_types=1);

namespace ComplyOps\Remediation\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Enforcement\EnforcementSettings;
use ComplyOps\Remediation\AbstractRemediationHandler;

/**
 * Enables YouTube embed gating.
 */
final class EnableYoutubeGateRemediation extends AbstractRemediationHandler {

	public function __construct(
		private readonly EnforcementSettings $settings = new EnforcementSettings(),
	) {
	}

	public function id(): string {
		return 'enable_youtube_gate';
	}

	public function label(): string {
		return __( 'Enable YouTube privacy gate', 'complyops' );
	}

	public function description(): string {
		return __( 'Replaces YouTube iframes with placeholders until External Media consent is granted.', 'complyops' );
	}

	public function control_ids(): array {
		return array(
			'GDPR-EMBED-001',
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
					'gate_youtube_before_consent'=> true,
					'use_youtube_nocookie'      => true,
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
				'key'    => 'enforcement.gate_youtube_before_consent',
				'label'  => __( 'Gate YouTube embeds', 'complyops' ),
				'before' => ! empty( $current['gate_youtube_before_consent'] ),
				'after'  => true,
			),
		);
	}
}
