<?php

declare(strict_types=1);

namespace ComplyOps\Monitoring;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Activity\ActivityLogService;
use ComplyOps\Consent\ConsentSettings;
use ComplyOps\Database\ExpectedStateRepository;
use ComplyOps\Detection\DiscoveryService;
use ComplyOps\Enforcement\EnforcementSettings;
use ComplyOps\Evidence\EvidenceService;
use ComplyOps\Notification\NotificationDispatcher;

/**
 * Compares expected compliance state against the live environment.
 */
final class DriftDetectionService {

	public function __construct(
		private readonly ExpectedStateRepository $expected_state,
		private readonly DiscoveryService $discovery,
		private readonly EvidenceService $evidence,
		private readonly ActivityLogService $activity_log,
		private readonly ConsentSettings $consent = new ConsentSettings(),
		private readonly EnforcementSettings $enforcement = new EnforcementSettings(),
	) {
	}

	/**
	 * @return array{drift_detected: bool, drifts: array<string, string>, evidence_count: int}
	 */
	public function check( string $framework = 'gdpr' ): array {
		$expected = $this->expected_state->for_framework( $framework );

		if ( array() === $expected ) {
			return array(
				'drift_detected' => false,
				'drifts'         => array(),
				'evidence_count' => 0,
			);
		}

		$snapshot = $this->discovery->discover();
		$observed = $this->observed_state( $snapshot );
		$drifts   = array();

		foreach ( $expected as $state_key => $expected_value ) {
			$current = $observed[ $state_key ] ?? null;

			if ( null === $current || $current !== $expected_value ) {
				$drifts[ $state_key ] = sprintf(
					/* translators: 1: configuration key, 2: expected value, 3: observed value */
					__( 'Expected %1$s=%2$s but observed %3$s.', 'complyops' ),
					$state_key,
					$expected_value,
					null === $current ? __( 'missing', 'complyops' ) : $current
				);
			}
		}

		if ( array() === $drifts ) {
			return array(
				'drift_detected' => false,
				'drifts'         => array(),
				'evidence_count' => 0,
			);
		}

		$count = $this->evidence->record_drift_findings( $framework, $drifts, $snapshot );

		$this->activity_log->record(
			'drift_detected',
			sprintf(
				/* translators: %d: number of drift findings */
				__( 'Configuration drift detected across %d settings.', 'complyops' ),
				count( $drifts )
			),
			'framework',
			$framework,
			array(
				'drifts' => array_keys( $drifts ),
			)
		);

		if ( function_exists( 'set_transient' ) ) {
			set_transient(
				'complyops_drift_notice',
				array(
					'framework' => $framework,
					'count'     => count( $drifts ),
					'detected_at' => gmdate( 'c' ),
				),
				DAY_IN_SECONDS
			);
		}

		( new NotificationDispatcher() )->drift_detected( count( $drifts ) );

		return array(
			'drift_detected' => true,
			'drifts'         => $drifts,
			'evidence_count' => $count,
		);
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, string>
	 */
	private function observed_state( array $snapshot ): array {
		$consent     = $this->consent->all();
		$enforcement = $this->enforcement->all();

		return array(
			'discovery_hash'      => $this->evidence->hash_snapshot( $snapshot ),
			'consent_enabled'       => ! empty( $consent['enabled'] ) ? '1' : '0',
			'consent_version'       => (string) ( $consent['version'] ?? '' ),
			'enforcement_enabled'   => ! empty( $enforcement['enabled'] ) ? '1' : '0',
		);
	}
}
