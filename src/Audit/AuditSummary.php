<?php

declare(strict_types=1);

namespace ComplyOps\Audit;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregate counts and score for an audit run.
 */
final class AuditSummary {

	public function __construct(
		public readonly int $score,
		public readonly int $passed,
		public readonly int $warnings,
		public readonly int $failed,
		public readonly int $unknowns,
		public readonly int $not_applicable,
		public readonly int $manual_review,
		public readonly int $info,
		public readonly int $technical_score = 0,
		public readonly int $evidence_score = 0,
		public readonly int $legal_review_score = 0,
	) {
	}

	/**
	 * @return array<string, int>
	 */
	public function to_counts_array(): array {
		return array(
			'score'                => $this->score,
			'technical_score'      => $this->technical_score,
			'evidence_score'       => $this->evidence_score,
			'legal_review_score'   => $this->legal_review_score,
			'passed_count'         => $this->passed,
			'warning_count'        => $this->warnings,
			'failed_count'         => $this->failed,
			'unknown_count'        => $this->unknowns,
			'not_applicable_count' => $this->not_applicable,
			'manual_review_count'  => $this->manual_review,
			'info_count'           => $this->info,
		);
	}
}
