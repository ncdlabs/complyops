<?php

declare(strict_types=1);

namespace ComplyOps\WordPress;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress privacy hardening settings applied by remediation.
 */
final class WordPressPrivacySettings {

	public const OPTION_KEY = 'complyops_wordpress_privacy_settings';

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		return array_merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
	}

	public function restrict_rest_users(): bool {
		return ! empty( $this->all()['restrict_rest_users'] );
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public function save( array $settings ): void {
		update_option( self::OPTION_KEY, $this->sanitize( array_merge( $this->all(), $settings ) ), false );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return array(
			'restrict_rest_users' => false,
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function sanitize( array $settings ): array {
		return array(
			'restrict_rest_users' => ! empty( $settings['restrict_rest_users'] ),
		);
	}
}
