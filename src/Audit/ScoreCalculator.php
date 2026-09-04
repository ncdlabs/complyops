<?php

declare(strict_types=1);

namespace ComplyOps\Audit;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlCapability;
use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\ControlSeverity;
use ComplyOps\Control\ControlStatus;
use ComplyOps\Control\ControlVerificationType;
use ComplyOps\Evidence\ManualEvidenceService;

/**
 * Computes technical, evidence, and legal readiness scores from control results.
 */
final class ScoreCalculator {

	public function __construct(
		private readonly ManualEvidenceService $manual_evidence = new ManualEvidenceService(),
	) {
	}

	/**
	 * @param list<array{definition: ControlDefinition, result: ControlResult}> $evaluations
	 */
	public function summarize( array $evaluations ): AuditSummary {
		$passed         = 0;
		$warnings       = 0;
		$failed         = 0;
		$unknowns       = 0;
		$not_applicable = 0;
		$manual_review  = 0;
		$info           = 0;

		$technical = $this->score_bucket( $evaluations, ControlVerificationType::Automatic );
		$evidence  = $this->score_bucket( $evaluations, ControlVerificationType::Human );
		$legal     = $this->score_bucket( $evaluations, ControlVerificationType::Legal );

		foreach ( $evaluations as $evaluation ) {
			$definition = $evaluation['definition'];
			$result     = $evaluation['result'];

			switch ( $result->status ) {
				case ControlStatus::Pass:
					++$passed;
					break;
				case ControlStatus::Warning:
					++$warnings;
					break;
				case ControlStatus::Fail:
					++$failed;
					break;
				case ControlStatus::Unknown:
					if ( ! $this->is_manual_review_capability( $definition->capability ) ) {
						++$unknowns;
					}
					break;
				case ControlStatus::NotApplicable:
					++$not_applicable;
					break;
				case ControlStatus::Info:
					++$info;
					break;
			}

			if ( $this->is_manual_review_capability( $definition->capability ) ) {
				++$manual_review;
			}
		}

		return new AuditSummary(
			score: $technical['score'],
			passed: $passed,
			warnings: $warnings,
			failed: $failed,
			unknowns: $unknowns,
			not_applicable: $not_applicable,
			manual_review: $manual_review,
			info: $info,
			technical_score: $technical['score'],
			evidence_score: $evidence['score'],
			legal_review_score: $legal['score'],
		);
	}

	/**
	 * @param list<array{definition: ControlDefinition, result: ControlResult}> $evaluations
	 * @return array{score: int, earned_points: int, max_points: int}
	 */
	private function score_bucket( array $evaluations, ControlVerificationType $bucket ): array {
		$earned_points = 0;
		$max_points    = 0;

		foreach ( $evaluations as $evaluation ) {
			$definition = $evaluation['definition'];
			$result     = $evaluation['result'];

			if ( $definition->verification_type !== $bucket ) {
				continue;
			}

			if ( ControlVerificationType::Automatic === $bucket
				&& $this->is_manual_review_capability( $definition->capability ) ) {
				continue;
			}

			if ( ControlVerificationType::Automatic !== $bucket
				&& $this->manual_evidence->has_valid_attestation( $definition ) ) {
				$weight      = $this->severity_weight( $definition->severity );
				$max_points += $weight;
				$earned_points += $weight;
				continue;
			}

			if ( $this->is_manual_review_capability( $definition->capability )
				&& ControlVerificationType::Automatic !== $bucket ) {
				$weight      = $this->severity_weight( $definition->severity );
				$max_points += $weight;
				continue;
			}

			if ( ControlStatus::NotApplicable === $result->status || ControlStatus::Info === $result->status ) {
				continue;
			}

			$weight = $this->severity_weight( $definition->severity );
			$max_points += $weight;
			$earned_points += (int) round( $weight * $this->status_multiplier( $result->status ) );
		}

		$score = 0;

		if ( $max_points > 0 ) {
			$score = (int) max( 0, min( 100, round( ( $earned_points / $max_points ) * 100 ) ) );
		}

		return array(
			'score'         => $score,
			'earned_points' => $earned_points,
			'max_points'    => $max_points,
		);
	}

	private function is_manual_review_capability( ControlCapability $capability ): bool {
		return in_array(
			$capability,
			array(
				ControlCapability::ManualReview,
				ControlCapability::LegalReview,
				ControlCapability::Informational,
			),
			true
		);
	}

	private function severity_weight( ControlSeverity $severity ): int {
		return match ( $severity ) {
			ControlSeverity::Critical => 10,
			ControlSeverity::High     => 8,
			ControlSeverity::Medium   => 5,
			ControlSeverity::Low      => 3,
			ControlSeverity::Info     => 1,
		};
	}

	private function status_multiplier( ControlStatus $status ): float {
		return match ( $status ) {
			ControlStatus::Pass           => 1.0,
			ControlStatus::Warning        => 0.5,
			ControlStatus::Fail,
			ControlStatus::Unknown        => 0.0,
			ControlStatus::NotApplicable,
			ControlStatus::Info           => 0.0,
		};
	}
}
