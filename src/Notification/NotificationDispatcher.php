<?php

declare(strict_types=1);

namespace ComplyOps\Notification;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Audit\AuditRun;
use ComplyOps\Audit\AuditStatus;
use ComplyOps\Audit\AuditTrigger;
use ComplyOps\Database\AuditResultRepository;
use ComplyOps\Security\Capabilities;

/**
 * Creates administrator notifications from audit and monitoring events.
 */
final class NotificationDispatcher {

	public function __construct(
		private readonly NotificationService $notifications = new NotificationService(),
		private readonly AuditResultRepository $results = new AuditResultRepository(),
	) {
	}

	public function audit_completed( AuditRun $audit ): void {
		if ( AuditStatus::Completed !== $audit->status ) {
			return;
		}

		$severity_counts = $this->results->count_failed_by_severity( $audit->id );
		$critical        = (int) ( $severity_counts['CRITICAL'] ?? 0 );
		$high            = (int) ( $severity_counts['HIGH'] ?? 0 );

		if ( $critical > 0 || $high > 0 ) {
			$this->notifications->queue(
				'finding',
				$critical > 0 ? 'critical' : 'high',
				sprintf(
					/* translators: 1: framework, 2: audit id, 3: critical count, 4: high count */
					__( 'ComplyOps %1$s audit #%2$d reported %3$d critical and %4$d high-severity failures.', 'complyops' ),
					strtoupper( $audit->framework ),
					$audit->id,
					$critical,
					$high
				),
				sprintf( 'audit_findings_%1$d', $audit->id ),
				array(
					'audit_id'  => $audit->id,
					'framework' => $audit->framework,
					'critical'  => $critical,
					'high'      => $high,
				)
			);
		}

		if ( AuditTrigger::Scheduled === $audit->trigger && $audit->failed > 0 ) {
			$this->notifications->queue(
				'scan_failed',
				'warning',
				sprintf(
					/* translators: 1: framework, 2: audit id, 3: failed count */
					__( 'Scheduled %1$s audit #%2$d completed with %3$d failed controls.', 'complyops' ),
					strtoupper( $audit->framework ),
					$audit->id,
					$audit->failed
				),
				sprintf( 'scheduled_audit_failures_%1$d', $audit->id ),
				array(
					'audit_id'  => $audit->id,
					'framework' => $audit->framework,
				)
			);
		}
	}

	public function audit_failed( int $audit_id, string $framework, AuditTrigger $trigger ): void {
		$this->notifications->queue(
			'scan_failed',
			'error',
			sprintf(
				/* translators: 1: framework, 2: audit id */
				__( 'ComplyOps %1$s audit #%2$d failed before completion.', 'complyops' ),
				strtoupper( $framework ),
				$audit_id
			),
			sprintf( 'audit_failed_%1$d', $audit_id ),
			array(
				'audit_id'  => $audit_id,
				'framework' => $framework,
				'trigger'   => $trigger->value,
			)
		);
	}

	public function drift_detected( int $count ): void {
		if ( $count <= 0 ) {
			return;
		}

		$this->notifications->queue(
			'drift',
			'warning',
			sprintf(
				/* translators: %d: drift count */
				__( 'ComplyOps detected configuration drift across %d settings.', 'complyops' ),
				$count
			),
			'drift_' . gmdate( 'Y-m-d' ),
			array(
				'count' => $count,
			)
		);
	}

	public function consent_enforcement_failure( string $message ): void {
		$this->notifications->queue(
			'consent_failure',
			'warning',
			$message,
			'consent_enforcement_' . md5( $message ),
		);
	}

	public function handle_dismiss_request(): void {
		if ( ! is_admin() || ! current_user_can( Capabilities::VIEW ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below with check_admin_referer.
		$dedupe_key = isset( $_GET['complyops_dismiss_notice'] )
			? sanitize_key( wp_unslash( (string) $_GET['complyops_dismiss_notice'] ) )
			: '';

		if ( '' === $dedupe_key ) {
			return;
		}

		check_admin_referer( 'complyops_dismiss_notice_' . $dedupe_key );
		$this->notifications->dismiss( $dedupe_key );
		delete_transient( 'complyops_drift_notice' );

		wp_safe_redirect( remove_query_arg( array( 'complyops_dismiss_notice', '_wpnonce' ) ) );
		exit;
	}
}
