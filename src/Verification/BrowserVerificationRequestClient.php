<?php

declare(strict_types=1);

namespace ComplyOps\Verification;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts and completes self-service hosted browser verification requests on ncdLabs.
 */
final class BrowserVerificationRequestClient {

	public const DEFAULT_INIT_URL = 'https://ncdlabs.com/products/complyops/api/browser-verification/request/init';

	public const DEFAULT_CLAIM_URL = 'https://ncdlabs.com/products/complyops/api/browser-verification/request/claim';

	public function __construct(
		private readonly BrowserVerificationProvisioner $provisioner = new BrowserVerificationProvisioner(),
		private readonly string $init_url = self::DEFAULT_INIT_URL,
		private readonly string $claim_url = self::DEFAULT_CLAIM_URL,
	) {
	}

	/**
	 * @return array{request_id: string, form_url: string, site_url: string, expires_at: string}|null
	 */
	public function start_request( string $return_url, ?string $admin_email = null ): ?array {
		if ( ! function_exists( 'wp_remote_post' ) ) {
			return null;
		}

		$return_url = esc_url_raw( trim( $return_url ) );
		if ( '' === $return_url ) {
			return null;
		}

		$body = array(
			'site_url'   => home_url( '/' ),
			'return_url' => $return_url,
		);

		if ( is_string( $admin_email ) && '' !== trim( $admin_email ) ) {
			$body['admin_email'] = sanitize_email( $admin_email );
		}

		$response = wp_safe_remote_post(
			$this->init_url,
			array(
				'timeout'            => 20,
				'reject_unsafe_urls' => true,
				'headers'            => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'ComplyOps/' . COMPLYOPS_VERSION,
				),
				'body'               => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			return null;
		}

		$request_id = isset( $data['request_id'] ) && is_string( $data['request_id'] ) ? trim( $data['request_id'] ) : '';
		$form_url   = isset( $data['form_url'] ) && is_string( $data['form_url'] ) ? esc_url_raw( $data['form_url'] ) : '';
		$site_url   = isset( $data['site_url'] ) && is_string( $data['site_url'] ) ? trim( $data['site_url'] ) : '';
		$expires_at = isset( $data['expires_at'] ) && is_string( $data['expires_at'] ) ? trim( $data['expires_at'] ) : '';

		if ( '' === $request_id || '' === $form_url ) {
			return null;
		}

		return array(
			'request_id' => $request_id,
			'form_url'   => $form_url,
			'site_url'   => $site_url,
			'expires_at' => $expires_at,
		);
	}

	/**
	 * @return array{ok: true, settings: array<string, mixed>}|array{ok: false, message: string}
	 */
	public function claim_request( string $request_id, string $exchange_code ): array {
		if ( ! function_exists( 'wp_remote_post' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'WordPress HTTP API is unavailable.', 'complyops' ),
			);
		}

		$request_id    = strtolower( preg_replace( '/[^0-9a-f]/', '', $request_id ) ?? '' );
		$exchange_code = strtolower( preg_replace( '/[^0-9a-f]/', '', $exchange_code ) ?? '' );

		if ( 32 !== strlen( $request_id ) || '' === $exchange_code ) {
			return array(
				'ok'      => false,
				'message' => __( 'Invalid browser verification request.', 'complyops' ),
			);
		}

		$response = wp_safe_remote_post(
			$this->claim_url,
			array(
				'timeout'            => 20,
				'reject_unsafe_urls' => true,
				'headers'            => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'ComplyOps/' . COMPLYOPS_VERSION,
				),
				'body'               => wp_json_encode(
					array(
						'request_id'     => $request_id,
						'exchange_code'  => $exchange_code,
						'site_url'       => home_url( '/' ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => $response->get_error_message(),
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status < 200 || $status >= 300 || ! is_array( $data ) || empty( $data['provisioned'] ) ) {
			$message = is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] )
				? $data['message']
				: __( 'Could not claim hosted browser verification credentials.', 'complyops' );

			return array(
				'ok'      => false,
				'message' => $message,
			);
		}

		if ( ! $this->provisioner->apply_provision_response( $data ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Could not save hosted browser verification credentials.', 'complyops' ),
			);
		}

		return array(
			'ok'       => true,
			'settings' => ( new BrowserVerificationSettings() )->admin_config(),
		);
	}
}
