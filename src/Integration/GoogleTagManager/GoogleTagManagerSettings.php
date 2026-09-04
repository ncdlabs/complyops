<?php

declare(strict_types=1);

namespace ComplyOps\Integration\GoogleTagManager;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Integration\Google\GoogleOAuthTokens;

/**
 * Google Tag Manager connection settings.
 */
final class GoogleTagManagerSettings {

	public const OPTION_KEY = 'complyops_google_tag_manager_settings';

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		return array_merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function admin_config(): array {
		$settings = $this->all();
		$oauth    = ( new GoogleTagManagerOAuthService() )->public_status();
		$settings['oauth']      = $oauth;
		$settings['configured'] = $this->is_configured();

		return $settings;
	}

	public function container_id(): string {
		return $this->normalize_container_id( (string) ( $this->all()['container_id'] ?? '' ) );
	}

	public function account_id(): string {
		return preg_replace( '/\D/', '', (string) ( $this->all()['account_id'] ?? '' ) );
	}

	public function account_email(): string {
		return sanitize_email( (string) ( $this->all()['account_email'] ?? '' ) );
	}

	public function is_configured(): bool {
		if ( ( new GoogleOAuthTokens() )->is_connected() && '' !== $this->container_id() ) {
			return true;
		}

		return '' !== $this->container_id();
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public function save( array $settings ): void {
		$current = $this->all();
		$merged  = array_merge( $current, $settings );

		update_option( self::OPTION_KEY, $this->sanitize( $merged ), false );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return array(
			'container_id'                 => '',
			'account_id'                   => '',
			'account_email'                => '',
			'oauth_container_display_name' => '',
			'oauth_connected_at'           => '',
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function sanitize( array $settings ): array {
		return array(
			'container_id'                 => $this->normalize_container_id( (string) ( $settings['container_id'] ?? '' ) ),
			'account_id'                   => preg_replace( '/\D/', '', (string) ( $settings['account_id'] ?? '' ) ),
			'account_email'                => sanitize_email( (string) ( $settings['account_email'] ?? '' ) ),
			'oauth_container_display_name' => sanitize_text_field( (string) ( $settings['oauth_container_display_name'] ?? '' ) ),
			'oauth_connected_at'           => sanitize_text_field( (string) ( $settings['oauth_connected_at'] ?? '' ) ),
		);
	}

	private function normalize_container_id( string $value ): string {
		$value = strtoupper( trim( $value ) );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^GTM-[A-Z0-9]+$/', $value ) ) {
			return $value;
		}

		if ( preg_match( '/(GTM-[A-Z0-9]+)/', $value, $matches ) ) {
			return $matches[1];
		}

		return '';
	}
}
