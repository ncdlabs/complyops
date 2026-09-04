<?php

declare(strict_types=1);

namespace ComplyOps\Integration\GoogleAnalytics;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Integration\Google\GoogleOAuthConfig;
use WP_Error;

/**
 * Google Analytics Admin API client.
 */
final class GoogleAnalyticsAdminClient {

	public function __construct(
		private readonly GoogleOAuthConfig $config = new GoogleOAuthConfig(),
	) {
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function account_summaries( string $access_token ): array|WP_Error {
		$url = add_query_arg(
			array(
				'pageSize' => 200,
			),
			$this->config::GA_ADMIN_ACCOUNT_SUMMARIES
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
				'timeout' => 20,
			)
		);

		return $this->decode_response( $response );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function data_streams( string $access_token, string $property_id ): array|WP_Error {
		$property = preg_replace( '/\D/', '', $property_id );

		if ( '' === $property ) {
			return new WP_Error(
				'complyops_ga_invalid_property',
				__( 'A valid Google Analytics property ID is required.', 'complyops' )
			);
		}

		$url = sprintf(
			'https://analyticsadmin.googleapis.com/v1beta/properties/%s/dataStreams',
			rawurlencode( $property )
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
				'timeout' => 20,
			)
		);

		return $this->decode_response( $response );
	}

	/**
	 * List GA4 properties available to the connected Google account.
	 *
	 * @return list<array{
	 *   property_id: string,
	 *   property_display_name: string,
	 *   measurement_id: string,
	 *   account_display_name: string
	 * }>|WP_Error
	 */
	public function list_properties( string $access_token ): array|WP_Error {
		$summaries = $this->account_summaries( $access_token );

		if ( is_wp_error( $summaries ) ) {
			return $summaries;
		}

		$items      = is_array( $summaries['accountSummaries'] ?? null ) ? $summaries['accountSummaries'] : array();
		$properties = array();

		foreach ( $items as $account ) {
			if ( ! is_array( $account ) ) {
				continue;
			}

			$account_name = (string) ( $account['displayName'] ?? '' );
			$property_rows = is_array( $account['propertySummaries'] ?? null ) ? $account['propertySummaries'] : array();

			foreach ( $property_rows as $property ) {
				if ( ! is_array( $property ) ) {
					continue;
				}

				$property_name = (string) ( $property['property'] ?? '' );
				$property_id   = preg_replace( '/^properties\//', '', $property_name );
				$property_id   = is_string( $property_id ) ? preg_replace( '/\D/', '', $property_id ) : '';

				if ( '' === $property_id ) {
					continue;
				}

				$measurement_id = $this->first_measurement_id( $access_token, $property_id );

				if ( is_wp_error( $measurement_id ) ) {
					continue;
				}

				$properties[] = array(
					'property_id'           => $property_id,
					'property_display_name' => (string) ( $property['displayName'] ?? '' ),
					'measurement_id'        => $measurement_id,
					'account_display_name'  => $account_name,
				);
			}
		}

		if ( array() === $properties ) {
			return new WP_Error(
				'complyops_ga_no_properties',
				__( 'No Google Analytics 4 properties were found for this Google account.', 'complyops' )
			);
		}

		return $properties;
	}

	/**
	 * Pick the first GA4 property and measurement ID available to the account.
	 *
	 * @return array{
	 *   property_id: string,
	 *   property_display_name: string,
	 *   measurement_id: string,
	 *   account_display_name: string
	 * }|WP_Error
	 */
	public function resolve_primary_property( string $access_token ): array|WP_Error {
		$properties = $this->list_properties( $access_token );

		if ( is_wp_error( $properties ) ) {
			return $properties;
		}

		$first = $properties[0];

		return array(
			'property_id'           => $first['property_id'],
			'property_display_name'   => $first['property_display_name'],
			'measurement_id'          => $first['measurement_id'],
			'account_display_name'    => $first['account_display_name'],
		);
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
				'complyops_ga_api_error',
				$message !== '' ? $message : __( 'Google Analytics API request failed.', 'complyops' ),
				array( 'status' => $code )
			);
		}

		return is_array( $data ) ? $data : array();
	}

	private function first_measurement_id( string $access_token, string $property_id ): string|WP_Error {
		$streams = $this->data_streams( $access_token, $property_id );

		if ( is_wp_error( $streams ) ) {
			return $streams;
		}

		$items = is_array( $streams['dataStreams'] ?? null ) ? $streams['dataStreams'] : array();

		foreach ( $items as $stream ) {
			if ( ! is_array( $stream ) ) {
				continue;
			}

			$web = is_array( $stream['webStreamData'] ?? null ) ? $stream['webStreamData'] : array();
			$id  = strtoupper( trim( (string) ( $web['measurementId'] ?? '' ) ) );

			if ( preg_match( '/^G-[A-Z0-9]+$/', $id ) ) {
				return $id;
			}
		}

		return new WP_Error(
			'complyops_ga_no_measurement_id',
			__( 'No GA4 measurement ID was found for the selected property.', 'complyops' )
		);
	}
}
