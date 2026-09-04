<?php

declare(strict_types=1);

namespace ComplyOps\Verification;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calls the ncdLabs browser verification provisioning API after pack activation.
 */
final class BrowserVerificationProvisioner {

	public const DEFAULT_PROVISION_URL = 'https://ncdlabs.com/products/complyops/api/browser-verification/provision';

	public const DEFAULT_SERVICE_URL = 'https://browser-verify.ncdlabs.com';

	public function __construct(
		private readonly BrowserVerificationSettings $settings = new BrowserVerificationSettings(),
		private readonly string $provision_url = self::DEFAULT_PROVISION_URL,
	) {
	}

	/**
	 * Provision hosted browser verification for an activated license.
	 */
	public function provision_for_unlock_key( string $unlock_key ): bool {
		if ( ! function_exists( 'wp_remote_post' ) ) {
			return false;
		}

		$unlock_key = strtolower( preg_replace( '/[^0-9a-f]/', '', $unlock_key ) ?? '' );

		if ( 32 !== strlen( $unlock_key ) ) {
			return false;
		}

		$site_url = home_url( '/' );

		$response = wp_safe_remote_post(
			$this->provision_url,
			array(
				'timeout'            => 20,
				'reject_unsafe_urls' => true,
				'headers'            => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'ComplyOps/' . COMPLYOPS_VERSION,
				),
				'body'    => wp_json_encode(
					array(
						'unlock_key' => $unlock_key,
						'site_url'   => $site_url,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status < 200 || $status >= 300 || ! is_array( $data ) || empty( $data['provisioned'] ) ) {
			return false;
		}

		$service_url = isset( $data['service_url'] ) && is_string( $data['service_url'] )
			? $data['service_url']
			: self::DEFAULT_SERVICE_URL;
		$token = isset( $data['token'] ) && is_string( $data['token'] ) ? trim( $data['token'] ) : '';

		if ( '' === $token ) {
			return false;
		}

		return $this->apply_provision_response(
			array(
				'service_url' => $service_url,
				'token'       => $token,
			)
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function apply_provision_response( array $data ): bool {
		$service_url = isset( $data['service_url'] ) && is_string( $data['service_url'] )
			? $data['service_url']
			: self::DEFAULT_SERVICE_URL;
		$token = isset( $data['token'] ) && is_string( $data['token'] ) ? trim( $data['token'] ) : '';

		if ( '' === $token ) {
			return false;
		}

		$this->settings->save(
			array(
				'enabled'     => true,
				'service_url' => $service_url,
				'token'       => $token,
			)
		);

		return true;
	}
}
