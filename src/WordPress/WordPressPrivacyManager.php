<?php

declare(strict_types=1);

namespace ComplyOps\WordPress;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies WordPress privacy hardening configured by ComplyOps.
 */
final class WordPressPrivacyManager {

	public function __construct(
		private readonly WordPressPrivacySettings $settings = new WordPressPrivacySettings(),
	) {
	}

	public function register(): void {
		add_filter( 'rest_endpoints', array( $this, 'restrict_users_endpoint' ) );
	}

	/**
	 * @param array<string, mixed> $endpoints
	 * @return array<string, mixed>
	 */
	public function restrict_users_endpoint( array $endpoints ): array {
		if ( ! $this->settings->restrict_rest_users() ) {
			return $endpoints;
		}

		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

		return $endpoints;
	}
}
