<?php

declare(strict_types=1);

namespace ComplyOps\Remediation\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Evidence\EvidenceRetentionService;
use ComplyOps\Remediation\AbstractRemediationHandler;

/**
 * Configures ComplyOps evidence retention.
 */
final class EnableEvidenceRetentionRemediation extends AbstractRemediationHandler {

	private const DEFAULT_RETENTION_DAYS = 365;

	public function id(): string {
		return 'enable_evidence_retention';
	}

	public function label(): string {
		return __( 'Configure evidence retention', 'complyops' );
	}

	public function description(): string {
		return __( 'Sets ComplyOps evidence retention to 365 days.', 'complyops' );
	}

	public function control_ids(): array {
		return array(
			'GDPR-RETENTION-003',
		);
	}

	public function capture_state(): array {
		return array(
			'retention_days' => get_option( EvidenceRetentionService::OPTION_KEY, null ),
		);
	}

	public function apply(): array {
		update_option( EvidenceRetentionService::OPTION_KEY, self::DEFAULT_RETENTION_DAYS, false );

		return array(
			'retention_days' => self::DEFAULT_RETENTION_DAYS,
		);
	}

	public function rollback( array $before_state ): void {
		$days = $before_state['retention_days'] ?? null;

		if ( is_numeric( $days ) ) {
			update_option( EvidenceRetentionService::OPTION_KEY, (int) $days, false );

			return;
		}

		delete_option( EvidenceRetentionService::OPTION_KEY );
	}

	public function preview_changes(): array {
		$current = get_option( EvidenceRetentionService::OPTION_KEY, null );

		return array(
			array(
				'key'    => 'evidence_retention_days',
				'label'  => __( 'Evidence retention (days)', 'complyops' ),
				'before' => is_numeric( $current ) ? (int) $current : null,
				'after'  => self::DEFAULT_RETENTION_DAYS,
			),
		);
	}
}
