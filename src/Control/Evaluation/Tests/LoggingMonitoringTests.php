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
use ComplyOps\Database\Schema;
use ComplyOps\Monitoring\MonitoringScheduler;

/**
 * Logging, monitoring, and drift detection checks.
 */
final class LoggingMonitoringTests extends AbstractSharedTests {

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'logging.activity_log_available' => array( $this, 'activity_log_available' ),
			'logging.monitoring_schedule'  => array( $this, 'monitoring_schedule' ),
		);
	}

	public function activity_log_available( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		global $wpdb;

		$table  = Schema::table_name( 'activity_log' );
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		) === $table;

		if ( $exists ) {
			return $this->pass(
				$definition,
				__( 'ComplyOps administrator activity log table is available.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->fail(
			$definition,
			__( 'ComplyOps administrator activity log table is not installed.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function monitoring_schedule( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$interval = get_option( MonitoringScheduler::OPTION_INTERVAL, 'weekly' );

		if ( is_string( $interval ) && in_array( $interval, array( 'daily', 'weekly', 'monthly' ), true ) ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %s: monitoring interval */
					__( 'ComplyOps monitoring interval is set to %s.', 'complyops' ),
					$interval
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'ComplyOps monitoring interval is disabled or not configured.', 'complyops' ),
			$definition->recommended_value,
		);
	}
}
