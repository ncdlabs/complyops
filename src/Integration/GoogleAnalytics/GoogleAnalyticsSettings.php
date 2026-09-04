<?php

declare(strict_types=1);

namespace ComplyOps\Integration\GoogleAnalytics;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Integration\Google\GoogleOAuthConfig;

/**
 * Manual Google Analytics connection settings for Admin API integration.
 */
final class GoogleAnalyticsSettings {

	public const OPTION_KEY = 'complyops_google_analytics_settings';

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		$merged = array_merge( $this->defaults(), is_array( $stored ) ? $stored : array() );

		if ( '' !== (string) ( $merged['oauth_client_secret'] ?? '' ) ) {
			$merged['oauth_client_secret'] = '';
			update_option( self::OPTION_KEY, $this->sanitize( $merged ), false );
		}

		return $merged;
	}

	/**
	 * Settings safe to expose in the admin UI (secrets are never returned).
	 *
	 * @return array<string, mixed>
	 */
	public function admin_config(): array {
		$settings = $this->all();
		unset( $settings['oauth_client_secret'] );

		$oauth                               = ( new GoogleAnalyticsOAuthService() )->public_status();
		$settings['oauth']                   = $oauth;
		// Secrets come from wp-config / filters only; never report option-stored secrets.
		$settings['oauth_client_secret_set'] = '' !== $this->oauth_client_secret();
		$settings['configured']              = $this->is_configured();

		return $settings;
	}

	public function measurement_id(): string {
		return $this->normalize_measurement_id( (string) ( $this->all()['measurement_id'] ?? '' ) );
	}

	public function property_id(): string {
		return preg_replace( '/\D/', '', (string) ( $this->all()['property_id'] ?? '' ) );
	}

	public function account_email(): string {
		return sanitize_email( (string) ( $this->all()['account_email'] ?? '' ) );
	}

	public function oauth_client_id(): string {
		return sanitize_text_field( (string) ( $this->all()['oauth_client_id'] ?? '' ) );
	}

	public function oauth_client_secret(): string {
		$config = ( new GoogleOAuthConfig() )->resolve();

		return (string) ( $config['client_secret'] ?? '' );
	}

	public function is_configured(): bool {
		if ( ( new GoogleAnalyticsOAuthTokens() )->is_connected() ) {
			return true;
		}

		return '' !== $this->measurement_id()
			|| '' !== $this->property_id()
			|| '' !== $this->oauth_client_id();
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public function save( array $settings ): void {
		$current = $this->all();
		$merged  = array_merge( $current, $settings );
		// Never persist OAuth client secrets in options (use constants / filters).
		$merged['oauth_client_secret'] = '';

		update_option( self::OPTION_KEY, $this->sanitize( $merged ), false );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return array(
			'measurement_id'              => '',
			'property_id'                 => '',
			'account_email'               => '',
			'oauth_client_id'             => '',
			'oauth_client_secret'         => '',
			'oauth_connected_at'          => '',
			'oauth_property_display_name' => '',
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function sanitize( array $settings ): array {
		return array(
			'measurement_id'              => $this->normalize_measurement_id( (string) ( $settings['measurement_id'] ?? '' ) ),
			'property_id'                 => preg_replace( '/\D/', '', (string) ( $settings['property_id'] ?? '' ) ),
			'account_email'               => sanitize_email( (string) ( $settings['account_email'] ?? '' ) ),
			'oauth_client_id'             => sanitize_text_field( (string) ( $settings['oauth_client_id'] ?? '' ) ),
			'oauth_client_secret'         => '',
			'oauth_connected_at'          => sanitize_text_field( (string) ( $settings['oauth_connected_at'] ?? '' ) ),
			'oauth_property_display_name' => sanitize_text_field( (string) ( $settings['oauth_property_display_name'] ?? '' ) ),
		);
	}

	private function normalize_measurement_id( string $value ): string {
		$value = strtoupper( trim( $value ) );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^G-[A-Z0-9]+$/', $value ) ) {
			return $value;
		}

		if ( preg_match( '/^(G-[A-Z0-9]+)/', $value, $matches ) ) {
			return $matches[1];
		}

		return '';
	}
}
