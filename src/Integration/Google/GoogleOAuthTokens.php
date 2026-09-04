<?php

declare(strict_types=1);

namespace ComplyOps\Integration\Google;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypted storage for shared Google OAuth tokens.
 */
class GoogleOAuthTokens {

	public const OPTION_KEY          = 'complyops_google_oauth_tokens';
	private const LEGACY_OPTION_KEY  = 'complyops_google_analytics_oauth_tokens';

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, '' );

		if ( ! is_string( $stored ) || '' === $stored ) {
			$stored = get_option( self::LEGACY_OPTION_KEY, '' );

			if ( is_string( $stored ) && '' !== $stored ) {
				update_option( self::OPTION_KEY, $stored, false );
				delete_option( self::LEGACY_OPTION_KEY );
			}
		}

		if ( ! is_string( $stored ) || '' === $stored ) {
			return array();
		}

		$decoded = $this->decrypt( $stored );

		return is_array( $decoded ) ? $decoded : array();
	}

	public function is_connected(): bool {
		$tokens = $this->all();

		return '' !== (string) ( $tokens['refresh_token'] ?? '' )
			|| '' !== (string) ( $tokens['access_token'] ?? '' );
	}

	public function access_token(): string {
		return (string) ( $this->all()['access_token'] ?? '' );
	}

	public function refresh_token(): string {
		return (string) ( $this->all()['refresh_token'] ?? '' );
	}

	public function expires_at(): int {
		return (int) ( $this->all()['expires_at'] ?? 0 );
	}

	/**
	 * @param array<string, mixed> $tokens
	 */
	public function save( array $tokens ): void {
		$current = $this->all();
		$merged  = array_merge( $current, $tokens );
		$payload = $this->encrypt( $merged );

		if ( false === $payload ) {
			return;
		}

		update_option( self::OPTION_KEY, $payload, false );
		delete_option( self::LEGACY_OPTION_KEY );
	}

	public function clear(): void {
		delete_option( self::OPTION_KEY );
		delete_option( self::LEGACY_OPTION_KEY );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function encrypt( array $data ): string|false {
		$json = wp_json_encode( $data );

		if ( ! is_string( $json ) ) {
			return false;
		}

		$key = hash( 'sha256', (string) wp_salt( 'auth' ), true );
		$iv  = random_bytes( 16 );

		$ciphertext = openssl_encrypt( $json, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return false;
		}

		return base64_encode( $iv . $ciphertext );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function decrypt( string $payload ): ?array {
		$decoded = base64_decode( $payload, true );

		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return null;
		}

		$iv         = substr( $decoded, 0, 16 );
		$ciphertext = substr( $decoded, 16 );
		$key        = hash( 'sha256', (string) wp_salt( 'auth' ), true );
		// nosemgrep: php.lang.security.audit.openssl-decrypt-validate.openssl-decrypt-validate -- Failure is handled immediately below.
		$json       = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $json || ! is_string( $json ) || '' === $json ) {
			return null;
		}

		$data = json_decode( $json, true );

		return is_array( $data ) ? $data : null;
	}
}
