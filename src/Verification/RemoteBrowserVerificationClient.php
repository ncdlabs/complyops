<?php

declare(strict_types=1);

namespace ComplyOps\Verification;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RuntimeException;

/**
 * Calls a remote complyops-browser service for headless verification.
 */
final class RemoteBrowserVerificationClient {

	public function __construct(
		private readonly BrowserVerificationSettings $settings = new BrowserVerificationSettings(),
	) {
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public function verify( string $url, array $options = array() ): array {
		if ( '' === $this->settings->service_url() ) {
			return $this->unavailable(
				__( 'Hosted browser verification service URL is not configured.', 'complyops' )
			);
		}

		$body = $this->encode_site_request(
			$url,
			array(
				'consent_version' => isset( $options['consent_version'] ) ? (string) $options['consent_version'] : '1',
			)
		);

		if ( null === $body ) {
			return $this->unavailable(
				__( 'Could not encode browser verification request.', 'complyops' )
			);
		}

		$result = $this->post_service(
			'/verify',
			$body,
			120,
			/* translators: %d: HTTP status code */
			__( 'Hosted browser verification returned HTTP %d.', 'complyops' ),
			__( 'Hosted browser verification returned invalid JSON.', 'complyops' )
		);

		if ( ! $result['ok'] ) {
			return $this->unavailable( $result['message'] );
		}

		return $this->normalize_result( $result['parsed'], $url );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function check_public_endpoint( string $url ): array {
		if ( '' === $this->settings->service_url() ) {
			return $this->endpoint_unavailable(
				__( 'Hosted browser verification service URL is not configured.', 'complyops' )
			);
		}

		$body = $this->encode_site_request( $url );

		if ( null === $body ) {
			return $this->endpoint_unavailable(
				__( 'Could not encode public endpoint check request.', 'complyops' )
			);
		}

		$result = $this->post_service(
			'/check-endpoint',
			$body,
			30,
			/* translators: %d: HTTP status code */
			__( 'Hosted browser public endpoint check returned HTTP %d.', 'complyops' ),
			__( 'Hosted browser public endpoint check returned invalid JSON.', 'complyops' )
		);

		if ( ! $result['ok'] ) {
			return $this->endpoint_unavailable( $result['message'] );
		}

		return $this->normalize_endpoint_result( $result['parsed'], $url );
	}

	/**
	 * @return array{ok: bool, message: string}
	 */
	public function test_connection( ?string $service_url = null, ?string $token = null ): array {
		try {
			$service_url = is_string( $service_url ) && '' !== trim( $service_url )
				? $this->settings->validate_service_url( $service_url )
				: $this->settings->service_url();
		} catch ( RuntimeException $exception ) {
			return array(
				'ok'      => false,
				'message' => $exception->getMessage(),
			);
		}
		$token = null !== $token ? trim( $token ) : $this->settings->token();

		if ( '' === $service_url ) {
			return array(
				'ok'      => false,
				'message' => __( 'Service URL is required.', 'complyops' ),
			);
		}

		$response = wp_safe_remote_get(
			add_query_arg(
				array(
					'site_url' => home_url( '/' ),
				),
				$service_url . '/health'
			),
			array(
				'timeout'            => 15,
				'reject_unsafe_urls' => true,
				'headers'            => $this->request_headers( $token ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => $response->get_error_message(),
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$parsed = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			return array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Health check returned HTTP %d.', 'complyops' ),
					$status
				),
			);
		}

		if ( ! is_array( $parsed ) || empty( $parsed['ok'] ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Health check did not return ok.', 'complyops' ),
			);
		}

		return array(
			'ok'      => true,
			'message' => __( 'Connected to hosted browser verification service.', 'complyops' ),
		);
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	private function encode_site_request( string $url, array $extra = array() ): ?string {
		$body = wp_json_encode(
			array_merge(
				array(
					'site_url'           => home_url( '/' ),
					'wordpress_site_url' => site_url( '/' ),
					'url'                => $url,
				),
				$extra
			)
		);

		return is_string( $body ) ? $body : null;
	}

	/**
	 * @return array{ok: true, parsed: array<string, mixed>}|array{ok: false, message: string}
	 */
	private function post_service(
		string $path,
		string $body,
		int $timeout,
		string $http_error_template,
		string $invalid_json_message
	): array {
		$response = wp_safe_remote_post(
			$this->settings->service_url() . $path,
			array(
				'timeout'            => $timeout,
				'reject_unsafe_urls' => true,
				'headers'            => $this->request_headers(),
				'body'               => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => $response->get_error_message(),
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$parsed = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $parsed ) && isset( $parsed['error'] )
				? (string) $parsed['error']
				: sprintf( $http_error_template, $status );

			return array(
				'ok'      => false,
				'message' => $message,
			);
		}

		if ( ! is_array( $parsed ) ) {
			return array(
				'ok'      => false,
				'message' => $invalid_json_message,
			);
		}

		return array(
			'ok'     => true,
			'parsed' => $parsed,
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function request_headers( ?string $token = null ): array {
		$headers = array(
			'Content-Type' => 'application/json',
			'User-Agent'   => 'ComplyOps/' . COMPLYOPS_VERSION,
		);

		$token = null !== $token ? trim( $token ) : $this->settings->token();

		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		return $headers;
	}

	/**
	 * @param array<string, mixed> $parsed
	 * @return array<string, mixed>
	 */
	private function normalize_result( array $parsed, string $url ): array {
		$available = (bool) ( $parsed['available'] ?? false );
		$scenarios = is_array( $parsed['scenarios'] ?? null ) ? $parsed['scenarios'] : array();

		return array(
			'available'   => $available,
			'captured_at' => isset( $parsed['captured_at'] ) ? (string) $parsed['captured_at'] : gmdate( 'c' ),
			'url'         => isset( $parsed['url'] ) ? (string) $parsed['url'] : $url,
			'binary'      => isset( $parsed['binary'] ) ? (string) $parsed['binary'] : 'remote',
			'scenarios'   => $scenarios,
			'error'       => isset( $parsed['error'] ) && is_string( $parsed['error'] ) ? $parsed['error'] : null,
		);
	}

	/**
	 * @param array<string, mixed> $parsed
	 * @return array<string, mixed>
	 */
	private function normalize_endpoint_result( array $parsed, string $url ): array {
		$available = (bool) ( $parsed['available'] ?? false );

		return array(
			'available'     => $available,
			'captured_at'   => isset( $parsed['captured_at'] ) ? (string) $parsed['captured_at'] : gmdate( 'c' ),
			'url'           => isset( $parsed['url'] ) ? (string) $parsed['url'] : $url,
			'http_status'   => isset( $parsed['http_status'] ) ? (int) $parsed['http_status'] : null,
			'public'        => array_key_exists( 'public', $parsed ) ? $parsed['public'] : null,
			'exposed_count' => isset( $parsed['exposed_count'] ) ? (int) $parsed['exposed_count'] : null,
			'method'        => isset( $parsed['method'] ) ? (string) $parsed['method'] : 'hosted_browser',
			'error'         => isset( $parsed['error'] ) && is_string( $parsed['error'] ) ? $parsed['error'] : null,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function endpoint_unavailable( string $reason ): array {
		return array(
			'available'     => false,
			'captured_at'   => gmdate( 'c' ),
			'url'           => null,
			'http_status'   => null,
			'public'        => null,
			'exposed_count' => null,
			'method'        => 'hosted_browser',
			'error'         => $reason,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function unavailable( string $reason ): array {
		return array(
			'available'   => false,
			'captured_at' => gmdate( 'c' ),
			'url'         => null,
			'binary'      => null,
			'scenarios'   => array(),
			'error'       => $reason,
		);
	}
}
