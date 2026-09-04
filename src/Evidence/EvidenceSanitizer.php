<?php

declare(strict_types=1);

namespace ComplyOps\Evidence;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes sensitive values from discovery data before it is persisted.
 */
final class EvidenceSanitizer {

	/** @var list<string> */
	private const SENSITIVE_KEYS = array(
		'authorization',
		'cookie',
		'cookies',
		'password',
		'passwd',
		'secret',
		'client_secret',
		'token',
		'access_token',
		'refresh_token',
		'api_key',
	);

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	public function sanitize_snapshot( array $snapshot ): array {
		$sanitized = $this->sanitize_value( $snapshot );

		return is_array( $sanitized ) ? $sanitized : array();
	}

	public function redact_url( string $url ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) ) {
			$segments = preg_split( '/[?#]/', $url, 2 );

			return $this->redact_text( is_array( $segments ) ? ( $segments[0] ?? '' ) : '' );
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path   = $this->redact_text( (string) ( $parts['path'] ?? '' ) );

		if ( '' !== $host ) {
			return ( '' !== $scheme ? $scheme . '://' : '//' ) . $host . $port . $path;
		}

		return $path;
	}

	private function sanitize_value( mixed $value, ?string $key = null ): mixed {
		if ( null !== $key && in_array( strtolower( $key ), self::SENSITIVE_KEYS, true ) ) {
			return '[redacted]';
		}

		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $child_key => $child_value ) {
				$sanitized[ $child_key ] = $this->sanitize_value(
					$child_value,
					is_string( $child_key ) ? $child_key : null
				);
			}

			return $sanitized;
		}

		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( preg_match( '#^(?:https?:)?//#i', $value ) || str_starts_with( $value, '/' ) ) {
			return $this->redact_url( $value );
		}

		return $this->redact_text( $value );
	}

	private function redact_text( string $value ): string {
		$redacted = preg_replace(
			'/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',
			'[redacted-email]',
			$value
		);

		if ( ! is_string( $redacted ) ) {
			$redacted = $value;
		}

		$redacted = preg_replace(
			'/\b(?:\d[ -]*?){13,19}\b/',
			'[redacted-pan-location]',
			$redacted
		);

		return is_string( $redacted ) ? $redacted : '';
	}
}
