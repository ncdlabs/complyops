<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\Evaluation\Tests\AccessAccountTests;
use ComplyOps\Control\Evaluation\Tests\BackupRetentionTests;
use ComplyOps\Control\Evaluation\Tests\CompliancePackTests;
use ComplyOps\Control\Evaluation\Tests\ConsentTests;
use ComplyOps\Control\Evaluation\Tests\DataCollectionTests;
use ComplyOps\Control\Evaluation\Tests\DataLeakageTests;
use ComplyOps\Control\Evaluation\Tests\EmbedTests;
use ComplyOps\Control\Evaluation\Tests\ExposureTests;
use ComplyOps\Control\Evaluation\Tests\GovernanceTests;
use ComplyOps\Control\Evaluation\Tests\LoggingMonitoringTests;
use ComplyOps\Control\Evaluation\Tests\PlatformInventoryTests;
use ComplyOps\Control\Evaluation\Tests\RuntimeTests;
use ComplyOps\Control\Evaluation\Tests\TrackingTests;
use ComplyOps\Control\Evaluation\Tests\TransportSecurityTests;
use ComplyOps\Control\Evaluation\Tests\WordPressPrivacyTests;
use InvalidArgumentException;
use RuntimeException;

/**
 * Resolves shared test_key implementations used across framework packs.
 */
final class TestRegistry {

	private static ?self $instance = null;

	/** @var array<string, callable(ControlDefinition, EvaluationContext): ControlResult> */
	private array $handlers = array();

	public function __construct() {
		$this->register_suite( new TransportSecurityTests() );
		$this->register_suite( new AccessAccountTests() );
		$this->register_suite( new ExposureTests() );
		$this->register_suite( new PlatformInventoryTests() );
		$this->register_suite( new WordPressPrivacyTests() );
		$this->register_suite( new LoggingMonitoringTests() );
		$this->register_suite( new ConsentTests() );
		$this->register_suite( new TrackingTests() );
		$this->register_suite( new DataCollectionTests() );
		$this->register_suite( new DataLeakageTests() );
		$this->register_suite( new EmbedTests() );
		$this->register_suite( new BackupRetentionTests() );
		$this->register_suite( new GovernanceTests() );
		$this->register_suite( new RuntimeTests() );
		$this->register_suite( new CompliancePackTests() );
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param object $suite Object exposing handlers(): array<string, callable>
	 */
	private function register_suite( object $suite ): void {
		if ( ! method_exists( $suite, 'handlers' ) ) {
			return;
		}

		/** @var array<string, callable(ControlDefinition, EvaluationContext): ControlResult> $handlers */
		$handlers = $suite->handlers();

		foreach ( $handlers as $key => $handler ) {
			if ( isset( $this->handlers[ $key ] ) ) {
				throw new RuntimeException(
					esc_html(
						sprintf(
							/* translators: %s: shared test_key identifier */
							__( 'Duplicate shared test_key registered: %s', 'complyops' ),
							(string) $key
						)
					)
				);
			}

			$this->handlers[ $key ] = $handler;
		}
	}

	public function has( string $test_key ): bool {
		return isset( $this->handlers[ $test_key ] );
	}

	/**
	 * @return list<string>
	 */
	public function keys(): array {
		return array_keys( $this->handlers );
	}

	public function evaluate(
		string $test_key,
		ControlDefinition $definition,
		EvaluationContext $context,
	): ControlResult {
		if ( ! isset( $this->handlers[ $test_key ] ) ) {
			throw new InvalidArgumentException(
				esc_html(
					sprintf(
						/* translators: %s: shared test_key identifier */
						__( 'Unknown shared test_key: %s', 'complyops' ),
						$test_key
					)
				)
			);
		}

		return ( $this->handlers[ $test_key ] )( $definition, $context );
	}
}
