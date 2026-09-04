<?php

declare(strict_types=1);

namespace ComplyOps\Framework;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exceptions; messages escaped at REST/WP_Error boundaries.

use InvalidArgumentException;
use RuntimeException;

/**
 * Calls the ncdLabs ComplyOps activation API to bind unlock keys to this site.
 */
final class FrameworkPackActivationClient {

	public const DEFAULT_ACTIVATE_URL = 'https://ncdlabs.com/products/complyops/api/activate';

	public function __construct(
		private readonly string $activate_url = self::DEFAULT_ACTIVATE_URL,
	) {
	}

	/**
	 * @return array{activated: bool, reused: bool, framework: string, label: string, site_url: string, activated_at: string}
	 */
	public function activate( string $unlock_key, string $framework ): array {
		if ( ! function_exists( 'wp_remote_post' ) ) {
			throw new RuntimeException(
				__( 'WordPress HTTP API is unavailable.', 'complyops' )
			);
		}

		$site_url = function_exists( 'home_url' ) ? home_url() : '';

		if ( '' === $site_url ) {
			throw new RuntimeException(
				__( 'Could not determine this site URL for pack activation.', 'complyops' )
			);
		}

		$response = wp_remote_post(
			$this->activate_url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'ComplyOps/' . ( defined( 'COMPLYOPS_VERSION' ) ? COMPLYOPS_VERSION : 'dev' ),
				),
				'body'    => wp_json_encode(
					array(
						'unlock_key' => $unlock_key,
						'site_url'   => $site_url,
						'framework'  => sanitize_key( $framework ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Could not reach the ComplyOps licensing service: %s', 'complyops' ),
					$response->get_error_message()
				)
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		/** @var array<string, mixed>|null $data */
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			throw new RuntimeException(
				__( 'ComplyOps licensing service returned an invalid response.', 'complyops' )
			);
		}

		if ( $status >= 400 ) {
			$message = isset( $data['message'] ) && is_string( $data['message'] )
				? $data['message']
				: __( 'Pack activation was rejected by the licensing service.', 'complyops' );

			throw new InvalidArgumentException( $message );
		}

		if ( empty( $data['activated'] ) ) {
			throw new InvalidArgumentException(
				__( 'Pack activation was not completed.', 'complyops' )
			);
		}

		return array(
			'activated'    => true,
			'reused'       => ! empty( $data['reused'] ),
			'framework'    => isset( $data['framework'] ) && is_string( $data['framework'] ) ? $data['framework'] : $framework,
			'label'        => isset( $data['label'] ) && is_string( $data['label'] ) ? $data['label'] : strtoupper( $framework ),
			'site_url'     => isset( $data['site_url'] ) && is_string( $data['site_url'] ) ? $data['site_url'] : $site_url,
			'activated_at' => isset( $data['activated_at'] ) && is_string( $data['activated_at'] ) ? $data['activated_at'] : gmdate( 'c' ),
		);
	}
}
