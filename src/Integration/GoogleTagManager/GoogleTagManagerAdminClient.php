<?php

declare(strict_types=1);

namespace ComplyOps\Integration\GoogleTagManager;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;

/**
 * Google Tag Manager API client.
 */
final class GoogleTagManagerAdminClient {

	private const API_BASE = 'https://tagmanager.googleapis.com/tagmanager/v2';

	/**
	 * List GTM containers available to the connected Google account.
	 *
	 * @return list<array{
	 *   container_id: string,
	 *   container_display_name: string,
	 *   account_id: string,
	 *   account_display_name: string
	 * }>|WP_Error
	 */
	public function list_containers( string $access_token ): array|WP_Error {
		$accounts = $this->accounts( $access_token );

		if ( is_wp_error( $accounts ) ) {
			return $accounts;
		}

		$containers = array();

		foreach ( $accounts as $account ) {
			$account_id = (string) ( $account['account_id'] ?? '' );

			if ( '' === $account_id ) {
				continue;
			}

			$account_containers = $this->containers_for_account( $access_token, $account_id );

			if ( is_wp_error( $account_containers ) ) {
				continue;
			}

			foreach ( $account_containers as $container ) {
				$containers[] = array(
					'container_id'           => (string) ( $container['container_id'] ?? '' ),
					'container_display_name' => (string) ( $container['container_display_name'] ?? '' ),
					'account_id'             => $account_id,
					'account_display_name'   => (string) ( $account['account_display_name'] ?? '' ),
				);
			}
		}

		if ( array() === $containers ) {
			return new WP_Error(
				'complyops_gtm_no_containers',
				__( 'No Google Tag Manager containers were found for this Google account.', 'complyops' )
			);
		}

		return $containers;
	}

	/**
	 * @return list<array{account_id: string, account_display_name: string}>|WP_Error
	 */
	private function accounts( string $access_token ): array|WP_Error {
		$response = $this->decode_response(
			wp_remote_get(
				self::API_BASE . '/accounts',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
					),
					'timeout' => 20,
				)
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items    = is_array( $response['account'] ?? null ) ? $response['account'] : array();
		$accounts = array();

		foreach ( $items as $account ) {
			if ( ! is_array( $account ) ) {
				continue;
			}

			$path = (string) ( $account['path'] ?? '' );
			$id   = preg_replace( '/^accounts\//', '', $path );
			$id   = is_string( $id ) ? preg_replace( '/\D/', '', $id ) : '';

			if ( '' === $id ) {
				continue;
			}

			$accounts[] = array(
				'account_id'           => $id,
				'account_display_name' => (string) ( $account['name'] ?? '' ),
			);
		}

		return $accounts;
	}

	/**
	 * @return list<array{container_id: string, container_display_name: string}>|WP_Error
	 */
	private function containers_for_account( string $access_token, string $account_id ): array|WP_Error {
		$response = $this->decode_response(
			wp_remote_get(
				self::API_BASE . '/accounts/' . rawurlencode( $account_id ) . '/containers',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
					),
					'timeout' => 20,
				)
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items      = is_array( $response['container'] ?? null ) ? $response['container'] : array();
		$containers = array();

		foreach ( $items as $container ) {
			if ( ! is_array( $container ) ) {
				continue;
			}

			$public_id = strtoupper( trim( (string) ( $container['publicId'] ?? '' ) ) );

			if ( ! preg_match( '/^GTM-[A-Z0-9]+$/', $public_id ) ) {
				continue;
			}

			$containers[] = array(
				'container_id'           => $public_id,
				'container_display_name' => (string) ( $container['name'] ?? '' ),
			);
		}

		return $containers;
	}

	/**
	 * @param array<string, mixed>|\WP_Error $response
	 * @return array<string, mixed>|WP_Error
	 */
	private function decode_response( array|WP_Error $response ): array|WP_Error {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? $body ) : $body;

			return new WP_Error(
				'complyops_gtm_api_error',
				$message !== '' ? $message : __( 'Google Tag Manager API request failed.', 'complyops' ),
				array( 'status' => $code )
			);
		}

		return is_array( $data ) ? $data : array();
	}
}
