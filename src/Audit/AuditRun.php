<?php

declare(strict_types=1);

namespace ComplyOps\Audit;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable representation of a persisted audit run.
 */
final class AuditRun {

	/**
	 * @param array<string, mixed>|null $discovery_snapshot
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $framework,
		public readonly AuditStatus $status,
		public readonly AuditTrigger $trigger,
		public readonly ?int $score,
		public readonly ?int $technical_score,
		public readonly ?int $evidence_score,
		public readonly ?int $legal_review_score,
		public readonly int $passed,
		public readonly int $warnings,
		public readonly int $failed,
		public readonly int $unknowns,
		public readonly int $not_applicable,
		public readonly int $manual_review,
		public readonly int $info,
		public readonly ?array $discovery_snapshot,
		public readonly ?string $site_url,
		public readonly ?string $plugin_version,
		public readonly string $started_at,
		public readonly ?string $completed_at,
		public readonly string $created_at,
	) {
	}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function from_row( array $row ): self {
		$snapshot = null;

		if ( isset( $row['discovery_snapshot'] ) && is_string( $row['discovery_snapshot'] ) && '' !== $row['discovery_snapshot'] ) {
			$decoded = json_decode( $row['discovery_snapshot'], true );
			$snapshot = is_array( $decoded ) ? $decoded : null;
		}

		return new self(
			id: (int) $row['id'],
			framework: (string) $row['framework'],
			status: AuditStatus::from( (string) $row['status'] ),
			trigger: AuditTrigger::from( (string) $row['trigger_source'] ),
			score: isset( $row['score'] ) ? (int) $row['score'] : null,
			technical_score: isset( $row['technical_score'] ) ? (int) $row['technical_score'] : null,
			evidence_score: isset( $row['evidence_score'] ) ? (int) $row['evidence_score'] : null,
			legal_review_score: isset( $row['legal_review_score'] ) ? (int) $row['legal_review_score'] : null,
			passed: (int) ( $row['passed_count'] ?? 0 ),
			warnings: (int) ( $row['warning_count'] ?? 0 ),
			failed: (int) ( $row['failed_count'] ?? 0 ),
			unknowns: (int) ( $row['unknown_count'] ?? 0 ),
			not_applicable: (int) ( $row['not_applicable_count'] ?? 0 ),
			manual_review: (int) ( $row['manual_review_count'] ?? 0 ),
			info: (int) ( $row['info_count'] ?? 0 ),
			discovery_snapshot: $snapshot,
			site_url: isset( $row['site_url'] ) ? (string) $row['site_url'] : null,
			plugin_version: isset( $row['plugin_version'] ) ? (string) $row['plugin_version'] : null,
			started_at: (string) $row['started_at'],
			completed_at: isset( $row['completed_at'] ) ? (string) $row['completed_at'] : null,
			created_at: (string) $row['created_at'],
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                   => $this->id,
			'framework'            => $this->framework,
			'status'               => $this->status->value,
			'trigger'              => $this->trigger->value,
			'score'                => $this->score,
			'technical_score'      => $this->technical_score,
			'evidence_score'       => $this->evidence_score,
			'legal_review_score'   => $this->legal_review_score,
			'passed'               => $this->passed,
			'warnings'             => $this->warnings,
			'failed'               => $this->failed,
			'unknowns'             => $this->unknowns,
			'not_applicable'       => $this->not_applicable,
			'manual_review'        => $this->manual_review,
			'info'                 => $this->info,
			'discovery_snapshot'   => $this->discovery_snapshot,
			'site_url'             => $this->site_url,
			'plugin_version'       => $this->plugin_version,
			'started_at'           => $this->started_at,
			'completed_at'         => $this->completed_at,
			'created_at'           => $this->created_at,
		);
	}
}
