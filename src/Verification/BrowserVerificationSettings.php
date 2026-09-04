<?php

declare(strict_types=1);

namespace ComplyOps\Verification;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exceptions; messages escaped at REST/WP_Error boundaries.

use RuntimeException;

/**
 * Settings for the hosted browser verification integration.
 */
final class BrowserVerificationSettings {

	public const OPTION_KEY = 'complyops_browser_verification_settings';

	public const DEFAULT_SERVICE_URL = 'https://browser-verify.ncdlabs.com';

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		return array_merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Settings safe to expose in the admin UI (token is never returned).
	 *
	 * @return array<string, mixed>
	 */
	public function admin_config(): array {
		$settings = $this->all();
		unset( $settings['token'] );

		$launcher = new ChromiumLauncher();
		$token    = $this->token();

		$settings['token_set']          = '' !== $token;
		$settings['token_preview']      = '' !== $token ? self::obfuscate_token_preview( $token ) : '';
		$settings['configured']         = $this->is_configured();
		$settings['local_available']    = $launcher->is_available();
		$settings['default_service_url'] = $this->default_service_url();

		return $settings;
	}

	public static function obfuscate_token_preview( string $token ): string {
		$token = trim( $token );
		$length = strlen( $token );

		if ( 0 === $length ) {
			return '';
		}

		if ( $length <= 6 ) {
			return str_repeat( '•', $length );
		}

		return substr( $token, 0, 3 )
			. str_repeat( '•', $length - 6 )
			. substr( $token, -3 );
	}

	public function is_enabled(): bool {
		return (bool) ( $this->all()['enabled'] ?? false );
	}

	public function service_url(): string {
		try {
			$url = $this->validate_service_url( (string) ( $this->all()['service_url'] ?? '' ) );
		} catch ( RuntimeException ) {
			$url = '';
		}

		return '' !== $url ? $url : $this->default_service_url();
	}

	public function token(): string {
		$stored = (string) ( $this->all()['token'] ?? '' );

		if ( '' === $stored ) {
			return '';
		}

		return $this->decrypt_token( $stored );
	}

	public function is_configured(): bool {
		return $this->is_enabled() && '' !== $this->token();
	}

	public function default_service_url(): string {
		/**
		 * Filter the default hosted browser verification service URL.
		 *
		 * @param string $url Default public service URL.
		 */
		$filtered = (string) apply_filters(
			'complyops_browser_verification_default_service_url',
			self::DEFAULT_SERVICE_URL
		);

		try {
			return $this->validate_service_url( $filtered );
		} catch ( RuntimeException ) {
			return self::DEFAULT_SERVICE_URL;
		}
	}

	public function validate_service_url( string $url ): string {
		$url = $this->normalize_service_url( $url );

		if ( '' === $url ) {
			return '';
		}

		$parts  = wp_parse_url( $url );
		$scheme = is_array( $parts ) ? strtolower( (string) ( $parts['scheme'] ?? '' ) ) : '';
		$host   = is_array( $parts ) ? strtolower( (string) ( $parts['host'] ?? '' ) ) : '';

		if ( 'https' !== $scheme || '' === $host || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			throw new RuntimeException(
				__( 'Browser verification must use an approved HTTPS service URL without embedded credentials.', 'complyops' )
			);
		}

		if ( function_exists( 'wp_http_validate_url' ) && false === wp_http_validate_url( $url ) ) {
			throw new RuntimeException(
				__( 'Browser verification service URL is not safe for outbound requests.', 'complyops' )
			);
		}

		$default_url  = (string) apply_filters(
			'complyops_browser_verification_default_service_url',
			self::DEFAULT_SERVICE_URL
		);
		$default_parts = wp_parse_url( $default_url );
		$allowed_hosts = array( 'browser-verify.ncdlabs.com' );

		if ( is_array( $default_parts ) && ! empty( $default_parts['host'] ) ) {
			$allowed_hosts[] = strtolower( (string) $default_parts['host'] );
		}

		/**
		 * Filter explicitly approved hosted-browser service hostnames.
		 *
		 * @param list<string> $allowed_hosts Approved exact hostnames.
		 */
		$allowed_hosts = apply_filters(
			'complyops_browser_verification_allowed_hosts',
			array_values( array_unique( $allowed_hosts ) )
		);
		$allowed_hosts = is_array( $allowed_hosts )
			? array_map( static fn ( mixed $allowed ): string => strtolower( (string) $allowed ), $allowed_hosts )
			: array();

		if ( ! in_array( $host, $allowed_hosts, true ) ) {
			throw new RuntimeException(
				__( 'Browser verification service host is not on the approved allowlist.', 'complyops' )
			);
		}

		return $url;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public function save( array $settings ): void {
		$current = $this->all();
		$merged  = array_merge( $current, $settings );

		if ( array_key_exists( 'token', $settings ) ) {
			$token = trim( (string) $settings['token'] );
			if ( '' === $token ) {
				$merged['token'] = $current['token'] ?? '';
			} else {
				$merged['token'] = $this->encrypt_token( $token );
			}
		}

		update_option( self::OPTION_KEY, $this->sanitize( $merged ), false );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return array(
			'enabled'     => false,
			'service_url' => '',
			'token'       => '',
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function sanitize( array $settings ): array {
		return array(
			'enabled'     => (bool) ( $settings['enabled'] ?? false ),
			'service_url' => $this->validate_service_url( (string) ( $settings['service_url'] ?? '' ) ),
			'token'       => sanitize_text_field( (string) ( $settings['token'] ?? '' ) ),
		);
	}

	private function normalize_service_url( string $url ): string {
		$url = esc_url_raw( trim( $url ) );

		if ( '' === $url ) {
			return '';
		}

		if ( str_ends_with( $url, '/verify' ) ) {
			$url = substr( $url, 0, -7 );
		}

		return rtrim( $url, '/' );
	}

	private function encrypt_token( string $token ): string {
		if ( str_starts_with( $token, 'enc:' ) ) {
			return $token;
		}

		$key = hash( 'sha256', (string) wp_salt( 'auth' ), true );
		$iv  = random_bytes( 16 );
		$ciphertext = openssl_encrypt( $token, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			throw new RuntimeException(
				__( 'Could not encrypt the browser verification token.', 'complyops' )
			);
		}

		return 'enc:' . base64_encode( $iv . $ciphertext );
	}

	private function decrypt_token( string $payload ): string {
		if ( ! str_starts_with( $payload, 'enc:' ) ) {
			return $payload;
		}

		$encoded = substr( $payload, 4 );
		$decoded = base64_decode( $encoded, true );

		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return '';
		}

		$iv         = substr( $decoded, 0, 16 );
		$ciphertext = substr( $decoded, 16 );
		$key        = hash( 'sha256', (string) wp_salt( 'auth' ), true );
		// nosemgrep: php.lang.security.audit.openssl-decrypt-validate.openssl-decrypt-validate -- Failure is handled immediately below.
		$plain      = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $plain || ! is_string( $plain ) ) {
			return '';
		}

		return $plain;
	}
}
