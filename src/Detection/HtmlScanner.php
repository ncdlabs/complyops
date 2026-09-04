<?php

declare(strict_types=1);

namespace ComplyOps\Detection;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Verification\BrowserScanTarget;

/**
 * Fetches and scans public HTML for scripts and embeds.
 */
final class HtmlScanner {

	private const MAX_REDIRECTS = 3;

	/**
	 * @return array{html: string|null, scripts: list<string>, iframes: list<string>, error: string|null}
	 */
	public function scan_url( string $url ): array {
		if ( ! function_exists( 'wp_safe_remote_get' ) ) {
			return array(
				'html'    => null,
				'scripts' => array(),
				'iframes' => array(),
				'error'   => 'HTTP client unavailable.',
			);
		}

		$current = BrowserScanTarget::resolve( $url );

		if ( null === $current ) {
			return array(
				'html'    => null,
				'scripts' => array(),
				'iframes' => array(),
				'error'   => 'URL is not an allowed site target.',
			);
		}

		for ( $hop = 0; $hop <= self::MAX_REDIRECTS; $hop++ ) {
			$response = wp_safe_remote_get(
				$current,
				array(
					'timeout'            => 15,
					'redirection'        => 0,
					'reject_unsafe_urls' => true,
					'user-agent'         => 'ComplyOps Discovery/1.0',
				)
			);

			if ( is_wp_error( $response ) ) {
				return array(
					'html'    => null,
					'scripts' => array(),
					'iframes' => array(),
					'error'   => $response->get_error_message(),
				);
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( $code >= 300 && $code < 400 ) {
				$location = wp_remote_retrieve_header( $response, 'location' );
				$location = is_array( $location ) ? (string) ( $location[0] ?? '' ) : (string) $location;
				$next     = '' !== $location ? BrowserScanTarget::resolve( $this->absolute_redirect_url( $current, $location ) ) : null;

				if ( null === $next || $hop === self::MAX_REDIRECTS ) {
					return array(
						'html'    => null,
						'scripts' => array(),
						'iframes' => array(),
						'error'   => 'Redirect target is not an allowed site URL.',
					);
				}

				$current = $next;
				continue;
			}

			if ( $code < 200 || $code >= 400 ) {
				return array(
					'html'    => null,
					'scripts' => array(),
					'iframes' => array(),
					'error'   => sprintf( 'HTTP %d response.', $code ),
				);
			}

			$html = (string) wp_remote_retrieve_body( $response );

			return array(
				'html'    => $html,
				'scripts' => $this->extract_script_sources( $html ),
				'iframes' => $this->extract_iframe_sources( $html ),
				'error'   => null,
			);
		}

		return array(
			'html'    => null,
			'scripts' => array(),
			'iframes' => array(),
			'error'   => 'Too many redirects.',
		);
	}

	private function absolute_redirect_url( string $current, string $location ): string {
		$location = trim( $location );

		if ( '' === $location ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $location ) ) {
			return $location;
		}

		$parts = wp_parse_url( $current );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return $location;
		}

		$origin = $parts['scheme'] . '://' . $parts['host'];

		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}

		if ( str_starts_with( $location, '/' ) ) {
			return $origin . $location;
		}

		$path = (string) ( $parts['path'] ?? '/' );
		$base = rtrim( substr( $path, 0, (int) strrpos( $path, '/' ) + 1 ), '/' );

		return $origin . $base . '/' . ltrim( $location, '/' );
	}

	/**
	 * @return list<string>
	 */
	public function extract_script_sources( string $html ): array {
		$sources = array();

		if ( preg_match_all( '/<script[^>]+src=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			foreach ( $matches[1] as $source ) {
				$sources[] = (string) $source;
			}
		}

		if ( preg_match_all( '/<script[^>]*>(.*?)<\/script>/is', $html, $inline_matches ) ) {
			foreach ( $inline_matches[1] as $inline ) {
				if ( preg_match_all( '#https?://[^\s"\']+#i', (string) $inline, $url_matches ) ) {
					foreach ( $url_matches[0] as $url ) {
						$sources[] = (string) $url;
					}
				}
			}
		}

		return array_values( array_unique( $sources ) );
	}

	/**
	 * @return list<string>
	 */
	public function extract_iframe_sources( string $html ): array {
		$sources = array();

		if ( preg_match_all( '/<iframe[^>]+src=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			foreach ( $matches[1] as $source ) {
				$sources[] = (string) $source;
			}
		}

		return array_values( array_unique( $sources ) );
	}

	/**
	 * @param list<string> $sources
	 * @param list<string> $patterns
	 */
	public function matches_patterns( array $sources, array $patterns ): bool {
		foreach ( $sources as $source ) {
			foreach ( $patterns as $pattern ) {
				if ( str_contains( strtolower( $source ), strtolower( $pattern ) ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param list<string>          $sources
	 * @param array<string, string> $known_domains Keyed by integration id.
	 * @return list<string> Unknown hostnames.
	 */
	public function unknown_external_hosts( array $sources, array $known_domains ): array {
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$unknown   = array();

		foreach ( $sources as $source ) {
			$host = wp_parse_url( $source, PHP_URL_HOST );

			if ( ! is_string( $host ) || '' === $host ) {
				continue;
			}

			if ( is_string( $site_host ) && strcasecmp( $host, $site_host ) === 0 ) {
				continue;
			}

			$recognized = false;

			foreach ( $known_domains as $domain ) {
				if ( str_contains( strtolower( $host ), strtolower( $domain ) ) ) {
					$recognized = true;
					break;
				}
			}

			if ( ! $recognized ) {
				$unknown[] = $host;
			}
		}

		return array_values( array_unique( $unknown ) );
	}

	/**
	 * @param list<string> $known_domains
	 * @return list<array{action: string, host: string, method: string}>
	 */
	public function extract_external_form_actions( string $html, array $known_domains ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$forms     = array();

		if ( ! preg_match_all( '/<form\b[^>]*action=(["\'])([^"\']+)\1[^>]*>/i', $html, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		foreach ( $matches as $match ) {
			$action = html_entity_decode( (string) $match[2], ENT_QUOTES );
			$host   = wp_parse_url( $action, PHP_URL_HOST );

			if ( ! is_string( $host ) || '' === $host ) {
				continue;
			}

			if ( is_string( $site_host ) && strcasecmp( $host, $site_host ) === 0 ) {
				continue;
			}

			$recognized = false;

			foreach ( $known_domains as $domain ) {
				if ( str_contains( strtolower( $host ), strtolower( $domain ) ) ) {
					$recognized = true;
					break;
				}
			}

			if ( $recognized ) {
				continue;
			}

			$forms[] = array(
				'action' => $action,
				'host'   => $host,
				'method' => 'post',
			);
		}

		return array_values(
			array_unique( $forms, SORT_REGULAR )
		);
	}
}
