<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation\Tests;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\Evaluation\AbstractControlEvaluator;
use ComplyOps\Control\Evaluation\EvaluationContext;

/**
 * Backup and retention configuration checks.
 */
final class BackupRetentionTests extends AbstractSharedTests {

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'retention.evidence_retention_configured' => array( $this, 'evidence_retention_configured' ),
			'retention.jobs_configured'               => array( $this, 'jobs_configured' ),
		);
	}

	public function evidence_retention_configured( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
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

	public function jobs_configured( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$retention = get_option( 'complyops_evidence_retention_days', null );

		if ( is_numeric( $retention ) && (int) $retention > 0 ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %d: retention days */
					__( 'ComplyOps evidence retention is configured for %d days.', 'complyops' ),
					(int) $retention
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'Automated retention jobs are not configured; review form, consent, and evidence retention.', 'complyops' ),
			$definition->recommended_value,
		);
	}
}
