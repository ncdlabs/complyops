<?php

declare(strict_types=1);

namespace ComplyOps\Integration\GoogleTagManager;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Consent\ConsentCategory;
use ComplyOps\Integration\IntegrationInterface;

/**
 * Google Tag Manager integration.
 */
final class GoogleTagManagerIntegration implements IntegrationInterface {

	public function id(): string {
		return 'google_tag_manager';
	}

	public function label(): string {
		return 'Google Tag Manager';
	}

	public function consent_category(): string {
		return ConsentCategory::Analytics->value;
	}

	public function script_patterns(): array {
		return array(
			'googletagmanager.com/gtm.js',
			'googletagmanager.com/ns.html',
		);
	}

	public function is_detected( array $discovery ): bool {
		$integrations = $discovery['integrations'] ?? array();

		return is_array( $integrations )
			&& ! empty( $integrations['google_tag_manager']['detected'] );
	}

	/**
	 * @return list<string>
	 */
	public function detect_container_ids( array $discovery ): array {
		$ids     = array();
		$sources = $discovery['html_scan']['script_sources'] ?? array();

		if ( is_array( $sources ) ) {
			foreach ( $sources as $source ) {
				if ( ! is_string( $source ) ) {
					continue;
				}

				if ( preg_match( '/[?&]id=(GTM-[A-Z0-9]+)/i', $source, $matches ) ) {
					$ids[] = strtoupper( $matches[1] );
				}
			}
		}

		$site_kit = $discovery['site_kit']['tag_manager']['container_id'] ?? '';
		if ( is_string( $site_kit ) && '' !== $site_kit ) {
			$ids[] = strtoupper( $site_kit );
		}

		return array_values( array_unique( $ids ) );
	}
}
