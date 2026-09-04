<?php

declare(strict_types=1);

namespace ComplyOps\Verification;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates browser scan targets to prevent SSRF during audits.
 */
final class BrowserScanTarget {

	/**
	 * Resolve and validate a URL for headless browser verification.
	 */
	public static function resolve( string $url ): ?string {
		$url = esc_url_raw( trim( $url ) );

		if ( '' === $url ) {
			return null;
		}

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return null;
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return null;
		}

		if ( ! self::is_allowed_host( (string) $parts['host'] ) ) {
			return null;
		}

		/**
		 * Adjust the browser verification URL for container or proxy environments.
		 *
		 * @param string $url     Resolved public site URL.
		 * @param array  $context Optional discovery context.
		 */
		$filtered = apply_filters( 'complyops_browser_verify_url', $url, array() );

		if ( ! is_string( $filtered ) || '' === trim( $filtered ) ) {
			return $url;
		}

		$filtered = esc_url_raw( trim( $filtered ) );
		$filtered_parts = wp_parse_url( $filtered );

		if ( ! is_array( $filtered_parts ) || empty( $filtered_parts['host'] ) ) {
			return $url;
		}

		$filtered_scheme = strtolower( (string) ( $filtered_parts['scheme'] ?? '' ) );

		if ( ! in_array( $filtered_scheme, array( 'http', 'https' ), true ) ) {
			return $url;
		}

		if ( self::host_matches_site( (string) $filtered_parts['host'] ) ) {
			return $filtered;
		}

		if ( self::is_loopback_host( (string) $filtered_parts['host'] ) && self::site_uses_loopback() ) {
			return $filtered;
		}

		return $url;
	}

	private static function is_allowed_host( string $host ): bool {
		$host = strtolower( $host );

		if ( self::host_matches_site( $host ) ) {
			return true;
		}

		if ( self::is_loopback_host( $host ) ) {
			return self::site_uses_loopback();
		}

		return false;
	}

	private static function host_matches_site( string $host ): bool {
		$allowed = array_filter(
			array(
				self::normalize_host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
				self::normalize_host( (string) wp_parse_url( site_url(), PHP_URL_HOST ) ),
			)
		);

		return in_array( self::normalize_host( $host ), $allowed, true );
	}

	private static function site_uses_loopback(): bool {
		foreach ( array( home_url(), site_url() ) as $site ) {
			$host = wp_parse_url( $site, PHP_URL_HOST );

			if ( is_string( $host ) && self::is_loopback_host( $host ) ) {
				return true;
			}
		}

		return false;
	}

	private static function is_loopback_host( string $host ): bool {
		$host = self::normalize_host( $host );

		return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
	}

	private static function normalize_host( string $host ): string {
		$host = strtolower( trim( $host ) );

		if ( str_starts_with( $host, 'www.' ) ) {
			return substr( $host, 4 );
		}

		return $host;
	}
}
