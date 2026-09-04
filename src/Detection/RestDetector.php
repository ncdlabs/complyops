<?php

declare(strict_types=1);

namespace ComplyOps\Detection;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Verification\PublicEndpointChecker;
use ComplyOps\Verification\PublicEndpointCheckService;

/**
 * Reviews WordPress REST API user exposure via the hosted browser service.
 */
final class RestDetector {

	public function __construct(
		private readonly ?PublicEndpointChecker $endpoint_checker = null,
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function detect(): array {
		$url = $this->users_endpoint_url();

		if ( null === $url ) {
			return array(
				'users_endpoint_public' => null,
				'error'                   => 'REST users endpoint URL could not be resolved.',
			);
		}

		$checker = $this->endpoint_checker ?? new PublicEndpointCheckService();
		$result  = $checker->check( $url );

		if ( empty( $result['available'] ) ) {
			return array(
				'users_endpoint_public' => null,
				'error'                 => is_string( $result['error'] ?? null ) ? (string) $result['error'] : 'Public endpoint check unavailable.',
				'method'                => 'hosted_browser',
			);
		}

		$public = $result['public'] ?? null;

		if ( null === $public ) {
			return array(
				'users_endpoint_public' => null,
				'http_status'           => isset( $result['http_status'] ) ? (int) $result['http_status'] : null,
				'method'                => 'hosted_browser',
			);
		}

		$response = array(
			'users_endpoint_public' => (bool) $public,
			'http_status'           => isset( $result['http_status'] ) ? (int) $result['http_status'] : null,
			'method'                => 'hosted_browser',
		);

		if ( (bool) $public ) {
			$response['exposed_user_count'] = (int) ( $result['exposed_count'] ?? 0 );
		}

		return $response;
	}

	private function users_endpoint_url(): ?string {
		if ( function_exists( 'rest_url' ) ) {
			$url = rest_url( 'wp/v2/users' );
		} else {
			$url = home_url( '/wp-json/wp/v2/users' );
		}

		return add_query_arg( 'per_page', 100, $url );
	}
}
