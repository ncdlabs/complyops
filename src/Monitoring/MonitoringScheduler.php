<?php

declare(strict_types=1);

namespace ComplyOps\Monitoring;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Audit\AuditService;
use ComplyOps\Audit\AuditTrigger;

/**
 * Schedules drift checks and optional compliance audits.
 */
final class MonitoringScheduler {

	public const CRON_HOOK = 'complyops_scheduled_monitor';
	public const OPTION_INTERVAL = 'complyops_monitoring_interval';

	public function __construct(
		private readonly DriftDetectionService $drift,
		private readonly AuditService $audits,
	) {
	}

	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( 'update_option_' . self::OPTION_INTERVAL, array( $this, 'reschedule' ), 10, 0 );
	}

	public function activate(): void {
		$this->reschedule();
	}

	public function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * @param array<string, array<string, int|string>> $schedules
	 * @return array<string, array<string, int|string>>
	 */
	public function schedules( array $schedules ): array {
		$schedules['complyops_daily']   = array(
			'interval' => DAY_IN_SECONDS,
			'display'  => __( 'Once daily (ComplyOps)', 'complyops' ),
		);
		$schedules['complyops_weekly']  = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once weekly (ComplyOps)', 'complyops' ),
		);
		$schedules['complyops_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once monthly (ComplyOps)', 'complyops' ),
		);

		return $schedules;
	}

	public function reschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );

		$interval = get_option( self::OPTION_INTERVAL, 'weekly' );

		if ( ! is_string( $interval ) || 'disabled' === $interval ) {
			return;
		}

		$recurrence = match ( $interval ) {
			'daily'   => 'complyops_daily',
			'monthly' => 'complyops_monthly',
			default   => 'complyops_weekly',
		};

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, self::CRON_HOOK );
		}
	}

	public function run(): void {
		( new \ComplyOps\Evidence\EvidenceRetentionService() )->apply();

		$frameworks = \ComplyOps\Plugin::instance()->frameworks()->all();

		foreach ( $frameworks as $framework ) {
			$this->drift->check( $framework->id() );
		}

		$interval = get_option( self::OPTION_INTERVAL, 'weekly' );

		if ( is_string( $interval ) && in_array( $interval, array( 'daily', 'weekly', 'monthly' ), true ) ) {
			foreach ( $frameworks as $framework ) {
				$this->audits->run( $framework->id(), AuditTrigger::Scheduled );
			}
		}
	}
}
