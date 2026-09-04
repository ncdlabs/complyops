<?php

declare(strict_types=1);

namespace ComplyOps\Admin;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Audit\AuditRun;
use ComplyOps\Database\AuditRepository;
use ComplyOps\Database\AuditResultRepository;
use ComplyOps\Monitoring\MonitoringScheduler;
use ComplyOps\Security\Capabilities;

/**
 * WordPress admin Dashboard widget for technical readiness at a glance.
 */
final class DashboardWidget {

	public const WIDGET_ID = 'complyops_dashboard_widget';

	private const ASSET_CSS = 'assets/admin-dashboard/dashboard-widget.css';

	private const TOP_FINDINGS_LIMIT = 3;

	public function __construct(
		private readonly AuditRepository $audits = new AuditRepository(),
		private readonly AuditResultRepository $results = new AuditResultRepository(),
	) {
	}

	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'add_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_widget(): void {
		if ( ! Capabilities::can_view() ) {
			return;
		}

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'ComplyOps', 'complyops' ),
			array( $this, 'render' ),
			null,
			null,
			'normal',
			'high'
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'index.php' !== $hook || ! Capabilities::can_view() ) {
			return;
		}

		$css_path = COMPLYOPS_PLUGIN_DIR . self::ASSET_CSS;
		if ( ! is_readable( $css_path ) ) {
			return;
		}

		wp_enqueue_style(
			'complyops-dashboard-widget',
			COMPLYOPS_PLUGIN_URL . self::ASSET_CSS,
			array(),
			COMPLYOPS_VERSION . '.' . (string) filemtime( $css_path )
		);
	}

	public function render(): void {
		$audit = $this->audits->latest_completed();
		$view  = $this->build_view_model(
			$audit,
			null !== $audit ? $this->results->count_failed_by_severity( $audit->id ) : array(),
			null !== $audit ? $this->results->findings_for_audit( $audit->id ) : array(),
			(string) get_option( MonitoringScheduler::OPTION_INTERVAL, 'weekly' )
		);

		$this->render_view( $view );
	}

	/**
	 * @param array<string, int>           $severity_counts
	 * @param list<array<string, mixed>>   $findings
	 * @return array{
	 *     has_audit: bool,
	 *     framework: string,
	 *     score: int|null,
	 *     passed: int,
	 *     warnings: int,
	 *     failed: int,
	 *     manual_review: int,
	 *     critical: int,
	 *     high: int,
	 *     monitoring_active: bool,
	 *     last_verified: string|null,
	 *     top_findings: list<array{title: string, severity: string}>,
	 *     dashboard_url: string,
	 *     findings_url: string,
	 *     audit_url: string
	 * }
	 */
	public function build_view_model(
		?AuditRun $audit,
		array $severity_counts,
		array $findings,
		string $interval,
	): array {
		$dashboard_url = admin_url( 'admin.php?page=complyops' );
		$findings_url  = admin_url( 'admin.php?page=complyops-audit&tab=findings' );
		$audit_url     = admin_url( 'admin.php?page=complyops-audit' );

		if ( null === $audit ) {
			return array(
				'has_audit'          => false,
				'framework'          => '',
				'score'              => null,
				'passed'             => 0,
				'warnings'           => 0,
				'failed'             => 0,
				'manual_review'      => 0,
				'critical'           => 0,
				'high'               => 0,
				'monitoring_active'  => $this->is_monitoring_active( $interval ),
				'last_verified'      => null,
				'top_findings'       => array(),
				'dashboard_url'      => $dashboard_url,
				'findings_url'       => $findings_url,
				'audit_url'          => $audit_url,
			);
		}

		return array(
			'has_audit'         => true,
			'framework'         => strtoupper( $audit->framework ),
			'score'             => $audit->score,
			'passed'            => $audit->passed,
			'warnings'          => $audit->warnings,
			'failed'            => $audit->failed,
			'manual_review'     => $audit->manual_review,
			'critical'          => (int) ( $severity_counts['CRITICAL'] ?? 0 ),
			'high'              => (int) ( $severity_counts['HIGH'] ?? 0 ),
			'monitoring_active' => $this->is_monitoring_active( $interval ),
			'last_verified'     => $this->format_verified_at( $audit ),
			'top_findings'      => $this->top_findings( $findings ),
			'dashboard_url'     => $dashboard_url,
			'findings_url'      => $findings_url,
			'audit_url'         => $audit_url,
		);
	}

	/**
	 * @param array{
	 *     has_audit: bool,
	 *     framework: string,
	 *     score: int|null,
	 *     passed: int,
	 *     warnings: int,
	 *     failed: int,
	 *     manual_review: int,
	 *     critical: int,
	 *     high: int,
	 *     monitoring_active: bool,
	 *     last_verified: string|null,
	 *     top_findings: list<array{title: string, severity: string}>,
	 *     dashboard_url: string,
	 *     findings_url: string,
	 *     audit_url: string
	 * } $view
	 */
	private function render_view( array $view ): void {
		$mark_url = COMPLYOPS_PLUGIN_URL . 'assets/brand/complyops-mark.svg';

		echo '<div class="complyops-dash-widget">';

		echo '<div class="complyops-dash-widget__header">';
		echo '<img class="complyops-dash-widget__mark" src="' . esc_url( $mark_url ) . '" alt="" width="28" height="28" />';
		echo '<div class="complyops-dash-widget__intro">';
		echo '<p class="complyops-dash-widget__eyebrow">' . esc_html__( 'Technical readiness', 'complyops' ) . '</p>';
		if ( $view['has_audit'] && '' !== $view['framework'] ) {
			echo '<p class="complyops-dash-widget__framework">' . esc_html( $view['framework'] ) . '</p>';
		}
		echo '</div></div>';

		if ( ! $view['has_audit'] ) {
			echo '<p class="complyops-dash-widget__empty">';
			echo esc_html__( 'No completed audit yet. Run a technical readiness audit to populate this widget.', 'complyops' );
			echo '</p>';
			echo '<p class="complyops-dash-widget__actions">';
			echo '<a class="button button-primary" href="' . esc_url( $view['dashboard_url'] ) . '">';
			echo esc_html__( 'Open ComplyOps', 'complyops' );
			echo '</a></p>';
			echo '<p class="complyops-dash-widget__disclaimer">';
			echo esc_html__( 'Scores describe technical readiness only — not legal certification.', 'complyops' );
			echo '</p></div>';
			return;
		}

		$score_class = 'complyops-dash-widget__score';
		if ( null !== $view['score'] ) {
			if ( $view['score'] >= 90 ) {
				$score_class .= ' is-good';
			} elseif ( $view['score'] >= 70 ) {
				$score_class .= ' is-warn';
			} else {
				$score_class .= ' is-bad';
			}
		}

		echo '<div class="complyops-dash-widget__hero">';
		echo '<div class="' . esc_attr( $score_class ) . '">';
		echo '<span class="complyops-dash-widget__score-value">';
		echo null !== $view['score'] ? esc_html( (string) $view['score'] ) . '%' : '&mdash;';
		echo '</span>';
		echo '<span class="complyops-dash-widget__score-label">' . esc_html__( 'Score', 'complyops' ) . '</span>';
		echo '</div>';
		echo '<ul class="complyops-dash-widget__stats">';
		$this->render_stat( __( 'Passed', 'complyops' ), $view['passed'] );
		$this->render_stat( __( 'Warnings', 'complyops' ), $view['warnings'] );
		$this->render_stat( __( 'Failed', 'complyops' ), $view['failed'] );
		$this->render_stat( __( 'Manual review', 'complyops' ), $view['manual_review'] );
		echo '</ul></div>';

		echo '<ul class="complyops-dash-widget__meta">';
		echo '<li><span>' . esc_html__( 'Critical / high', 'complyops' ) . '</span>';
		echo '<strong>' . esc_html( (string) $view['critical'] ) . ' / ' . esc_html( (string) $view['high'] ) . '</strong></li>';
		echo '<li><span>' . esc_html__( 'Monitoring', 'complyops' ) . '</span>';
		echo '<strong>' . esc_html(
			$view['monitoring_active']
				? __( 'Active', 'complyops' )
				: __( 'Inactive', 'complyops' )
		) . '</strong></li>';
		if ( null !== $view['last_verified'] ) {
			echo '<li><span>' . esc_html__( 'Last audit', 'complyops' ) . '</span>';
			echo '<strong>' . esc_html( $view['last_verified'] ) . '</strong></li>';
		}
		echo '</ul>';

		if ( array() !== $view['top_findings'] ) {
			echo '<div class="complyops-dash-widget__findings">';
			echo '<p class="complyops-dash-widget__section-title">' . esc_html__( 'Top findings', 'complyops' ) . '</p>';
			echo '<ul>';
			foreach ( $view['top_findings'] as $finding ) {
				echo '<li>';
				echo '<span class="complyops-dash-widget__sev complyops-dash-widget__sev--' . esc_attr( strtolower( $finding['severity'] ) ) . '">';
				echo esc_html( $finding['severity'] );
				echo '</span>';
				echo '<span class="complyops-dash-widget__finding-title">' . esc_html( $finding['title'] ) . '</span>';
				echo '</li>';
			}
			echo '</ul></div>';
		}

		echo '<p class="complyops-dash-widget__actions">';
		echo '<a class="button button-primary" href="' . esc_url( $view['dashboard_url'] ) . '">';
		echo esc_html__( 'Open dashboard', 'complyops' );
		echo '</a>';
		echo '<a class="button" href="' . esc_url( $view['findings_url'] ) . '">';
		echo esc_html__( 'View findings', 'complyops' );
		echo '</a>';
		echo '<a class="button" href="' . esc_url( $view['audit_url'] ) . '">';
		echo esc_html__( 'Audits', 'complyops' );
		echo '</a>';
		echo '</p>';

		echo '<p class="complyops-dash-widget__disclaimer">';
		echo esc_html__( 'Scores describe technical readiness only — not legal certification.', 'complyops' );
		echo '</p></div>';
	}

	private function render_stat( string $label, int $value ): void {
		echo '<li><span class="complyops-dash-widget__stat-value">' . esc_html( (string) $value ) . '</span>';
		echo '<span class="complyops-dash-widget__stat-label">' . esc_html( $label ) . '</span></li>';
	}

	/**
	 * @param list<array<string, mixed>> $findings
	 * @return list<array{title: string, severity: string}>
	 */
	private function top_findings( array $findings ): array {
		$priority = array( 'CRITICAL' => 0, 'HIGH' => 1 );
		$selected = array();

		foreach ( $findings as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$status   = (string) ( $row['status'] ?? '' );
			$severity = strtoupper( (string) ( $row['severity'] ?? '' ) );

			if ( 'FAIL' !== $status || ! isset( $priority[ $severity ] ) ) {
				continue;
			}

			$title = trim( (string) ( $row['title'] ?? '' ) );
			if ( '' === $title ) {
				$title = (string) ( $row['control_id'] ?? __( 'Untitled control', 'complyops' ) );
			}

			$selected[] = array(
				'title'    => $title,
				'severity' => $severity,
			);
		}

		usort(
			$selected,
			static function ( array $a, array $b ) use ( $priority ): int {
				return $priority[ $a['severity'] ] <=> $priority[ $b['severity'] ];
			}
		);

		return array_slice( $selected, 0, self::TOP_FINDINGS_LIMIT );
	}

	private function is_monitoring_active( string $interval ): bool {
		return '' !== $interval && 'disabled' !== $interval;
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

		if ( function_exists( 'wp_date' ) ) {
			$date_format = (string) get_option( 'date_format', 'F j, Y' );
			$time_format = (string) get_option( 'time_format', 'g:i a' );

			return wp_date( $date_format . ' ' . $time_format, $unix );
		}

		return gmdate( 'M j, Y', $unix );
	}
}
