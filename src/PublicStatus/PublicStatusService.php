<?php

declare(strict_types=1);

namespace ComplyOps\PublicStatus;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Audit\AuditRun;
use ComplyOps\Database\AuditRepository;
use ComplyOps\Database\AuditResultRepository;
use ComplyOps\Monitoring\MonitoringScheduler;

/**
 * Builds administrator-approved public compliance posture payloads.
 */
final class PublicStatusService {

	public function __construct(
		private readonly PublicStatusSettings $settings = new PublicStatusSettings(),
		private readonly AuditRepository $audits = new AuditRepository(),
		private readonly AuditResultRepository $results = new AuditResultRepository(),
	) {
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function payload( bool $allow_disabled = false ): ?array {
		if ( ! $allow_disabled && ! $this->settings->is_public_enabled() ) {
			return null;
		}

		$framework = $this->settings->framework_id();
		$audit     = $this->latest_completed_audit( $framework );

		if ( null === $audit ) {
			return null;
		}

		$severity_counts = $this->results->count_failed_by_severity( $audit->id );
		$critical        = (int) ( $severity_counts['CRITICAL'] ?? 0 );
		$high            = (int) ( $severity_counts['HIGH'] ?? 0 );
		$interval        = get_option( MonitoringScheduler::OPTION_INTERVAL, 'weekly' );
		$monitoring      = is_string( $interval ) && 'disabled' !== $interval ? 'active' : 'inactive';
		$technical_status = ( 0 === $audit->failed && 0 === $critical && 0 === $high )
			? 'passing'
			: 'attention_required';

		$full = array(
			'framework'          => strtoupper( $framework ),
			'technical_status'   => $technical_status,
			'score'              => $audit->score,
			'monitoring'         => $monitoring,
			'last_verified'      => $this->format_verified_at( $audit ),
			'critical_findings'  => $critical,
			'high_findings'      => $high,
		);

		return $this->filter_published_fields( $full );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function filter_published_fields( array $payload ): array {
		$allowed = $this->settings->published_fields();

		if ( array() === $allowed ) {
			return array();
		}

		return array_intersect_key( $payload, array_flip( $allowed ) );
	}

	private function latest_completed_audit( string $framework ): ?AuditRun {
		return $this->audits->latest_completed( $framework );
	}

	private function format_verified_at( AuditRun $audit ): ?string {
		$timestamp = $audit->completed_at ?? $audit->started_at;

		if ( '' === $timestamp ) {
			return null;
		}

		$unix = strtotime( $timestamp . ' UTC' );

		if ( false === $unix ) {
			return null;
		}

		return gmdate( 'c', $unix );
	}
}
