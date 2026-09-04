<?php

declare(strict_types=1);

namespace ComplyOps\Integration;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Integration\GoogleAnalytics\GoogleAnalyticsIntegration;
use ComplyOps\Integration\GoogleTagManager\GoogleTagManagerIntegration;
use ComplyOps\Integration\YouTube\YouTubeIntegration;

/**
 * Registry of service integrations.
 */
final class IntegrationRegistry {

	/** @var array<string, IntegrationInterface> */
	private array $integrations = array();

	public function __construct() {
		$this->register( new GoogleAnalyticsIntegration() );
		$this->register( new GoogleTagManagerIntegration() );
		$this->register( new YouTubeIntegration() );
	}

	public function register( IntegrationInterface $integration ): void {
		$this->integrations[ $integration->id() ] = $integration;
	}

	/**
	 * @return list<IntegrationInterface>
	 */
	public function all(): array {
		return array_values( $this->integrations );
	}

	public function get( string $id ): ?IntegrationInterface {
		return $this->integrations[ $id ] ?? null;
	}

	/**
	 * @return list<IntegrationInterface>
	 */
	public function detected( array $discovery ): array {
		return array_values(
			array_filter(
				$this->integrations,
				static fn ( IntegrationInterface $integration ): bool => $integration->is_detected( $discovery )
			)
		);
	}
}
