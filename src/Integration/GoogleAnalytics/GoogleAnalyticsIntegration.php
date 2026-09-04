<?php

declare(strict_types=1);

namespace ComplyOps\Integration\GoogleAnalytics;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Consent\ConsentCategory;
use ComplyOps\Integration\IntegrationInterface;

/**
 * Google Analytics 4 integration.
 */
final class GoogleAnalyticsIntegration implements IntegrationInterface {

	public function id(): string {
		return 'google_analytics';
	}

	public function label(): string {
		return 'Google Analytics';
	}

	public function consent_category(): string {
		return ConsentCategory::Analytics->value;
	}

	public function script_patterns(): array {
		return array(
			'googletagmanager.com/gtag/js',
			'google-analytics.com/analytics.js',
			'google-analytics.com/g/collect',
		);
	}

	public function is_detected( array $discovery ): bool {
		$integrations = $discovery['integrations'] ?? array();

		if ( is_array( $integrations ) && ! empty( $integrations['google_analytics']['detected'] ) ) {
			return true;
		}

		return ( new GoogleAnalyticsSettings() )->is_configured();
	}

	/**
	 * @return list<string>
	 */
	public function detect_measurement_ids( array $discovery ): array {
		$ids     = array();
		$sources = $discovery['html_scan']['script_sources'] ?? array();

		if ( is_array( $sources ) ) {
			foreach ( $sources as $source ) {
				if ( ! is_string( $source ) ) {
					continue;
				}

				if ( preg_match( '/[?&]id=(G-[A-Z0-9]+)/i', $source, $matches ) ) {
					$ids[] = strtoupper( $matches[1] );
				}
			}
		}

		$site_kit = $discovery['site_kit']['analytics']['measurement_id'] ?? '';
		if ( is_string( $site_kit ) && '' !== $site_kit ) {
			$ids[] = strtoupper( $site_kit );
		}

		$manual = ( new GoogleAnalyticsSettings() )->measurement_id();
		if ( '' !== $manual ) {
			$ids[] = $manual;
		}

		return array_values( array_unique( $ids ) );
	}

	public function count_implementations( array $discovery ): int {
		return count( $this->detect_measurement_ids( $discovery ) );
	}
}
