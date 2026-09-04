<?php

declare(strict_types=1);

namespace ComplyOps\Integration\YouTube;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Consent\ConsentCategory;
use ComplyOps\Integration\IntegrationInterface;

/**
 * YouTube embed integration.
 */
final class YouTubeIntegration implements IntegrationInterface {

	public function id(): string {
		return 'youtube';
	}

	public function label(): string {
		return 'YouTube';
	}

	public function consent_category(): string {
		return ConsentCategory::ExternalMedia->value;
	}

	public function script_patterns(): array {
		return array();
	}

	public function iframe_patterns(): array {
		return array(
			'youtube.com/embed',
			'youtube-nocookie.com/embed',
			'youtube.com/watch',
		);
	}

	public function is_detected( array $discovery ): bool {
		$integrations = $discovery['integrations'] ?? array();

		return is_array( $integrations )
			&& ! empty( $integrations['youtube']['detected'] );
	}

	public function is_youtube_url( string $url ): bool {
		$lower = strtolower( $url );

		foreach ( $this->iframe_patterns() as $pattern ) {
			if ( str_contains( $lower, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	public function to_nocookie_url( string $url ): string {
		return str_replace(
			array( '://www.youtube.com/', '://youtube.com/' ),
			'://www.youtube-nocookie.com/',
			$url
		);
	}
}
