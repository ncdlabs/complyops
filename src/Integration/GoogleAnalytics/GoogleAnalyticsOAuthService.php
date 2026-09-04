<?php

declare(strict_types=1);

namespace ComplyOps\Integration\GoogleAnalytics;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Integration\Google\GoogleOAuthService;
use WP_Error;

/**
 * Google Analytics OAuth and property selection flows.
 */
final class GoogleAnalyticsOAuthService {

	public function __construct(
		private readonly GoogleOAuthService $oauth = new GoogleOAuthService(),
		private readonly GoogleAnalyticsSettings $settings = new GoogleAnalyticsSettings(),
		private readonly GoogleAnalyticsAdminClient $admin = new GoogleAnalyticsAdminClient(),
	) {
	}

	public function is_connected(): bool {
		return $this->oauth->is_connected();
	}

	/**
	 * @return array{authorization_url: string, state: string, mode: string}|WP_Error
	 */
	public function start(): array|WP_Error {
		return $this->oauth->start( GoogleOAuthService::PACK_GOOGLE_ANALYTICS );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function complete( string $exchange_code, string $state ): array|WP_Error {
		$result = $this->oauth->complete( $exchange_code, $state );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->finalize_connection();
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function handle_direct_callback( string $code, string $state ): array|WP_Error {
		$result = $this->oauth->handle_direct_callback( $code, $state );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->finalize_connection();
	}

	public function disconnect(): void {
		$this->oauth->disconnect();

		$current = $this->settings->all();
		unset( $current['oauth_connected_at'], $current['oauth_property_display_name'] );

		$this->settings->save(
			array_merge(
				$current,
				array(
					'account_email'  => '',
					'property_id'    => '',
					'measurement_id' => '',
				)
			)
		);

		$gtm_settings = new \ComplyOps\Integration\GoogleTagManager\GoogleTagManagerSettings();
		$gtm_current  = $gtm_settings->all();
		unset( $gtm_current['oauth_connected_at'], $gtm_current['oauth_container_display_name'] );
		$gtm_settings->save(
			array_merge(
				$gtm_current,
				array(
					'account_email' => '',
					'account_id'    => '',
					'container_id'  => '',
				)
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function public_status(): array {
		$settings  = $this->settings->all();
		$connected = $this->is_connected();
		$property_selected = '' !== $this->settings->property_id() && '' !== $this->settings->measurement_id();
		$shared    = $this->oauth->public_status();

		return array(
			'connected'                => $connected,
			'property_selected'        => $property_selected,
			'needs_property_selection' => $connected && ! $property_selected,
			'account_email'            => sanitize_email( (string) ( $settings['account_email'] ?? $shared['account_email'] ?? '' ) ),
			'measurement_id'           => $this->settings->measurement_id(),
			'property_id'              => $this->settings->property_id(),
			'property_display_name'    => sanitize_text_field( (string) ( $settings['oauth_property_display_name'] ?? '' ) ),
			'connected_at'             => sanitize_text_field( (string) ( $settings['oauth_connected_at'] ?? $shared['connected_at'] ?? '' ) ),
		);
	}

	/**
	 * @return list<array{
	 *   property_id: string,
	 *   property_display_name: string,
	 *   measurement_id: string,
	 *   account_display_name: string
	 * }>|WP_Error
	 */
	public function list_properties(): array|WP_Error {
		if ( ! $this->is_connected() ) {
			return new WP_Error(
				'complyops_ga_oauth_not_connected',
				__( 'Connect Google Analytics before choosing a property.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$access_token = $this->oauth->api_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		return $this->admin->list_properties( $access_token );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function select_property( string $property_id ): array|WP_Error {
		if ( ! $this->is_connected() ) {
			return new WP_Error(
				'complyops_ga_oauth_not_connected',
				__( 'Connect Google Analytics before choosing a property.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$property_id = preg_replace( '/\D/', '', $property_id );

		if ( '' === $property_id ) {
			return new WP_Error(
				'complyops_ga_invalid_property',
				__( 'Choose a valid Google Analytics property.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$access_token = $this->oauth->api_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$properties = $this->admin->list_properties( $access_token );

		if ( is_wp_error( $properties ) ) {
			return $properties;
		}

		$selected = null;

		foreach ( $properties as $property ) {
			if ( $property['property_id'] === $property_id ) {
				$selected = $property;
				break;
			}
		}

		if ( null === $selected ) {
			return new WP_Error(
				'complyops_ga_invalid_property',
				__( 'That Google Analytics property is not available for the connected account.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		return $this->apply_property_selection( $selected );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function finalize_connection(): array|WP_Error {
		$shared = $this->oauth->public_status();
		$email  = sanitize_email( (string) ( $shared['account_email'] ?? '' ) );

		$access_token = $this->oauth->api_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$properties = $this->admin->list_properties( $access_token );

		if ( is_wp_error( $properties ) ) {
			return $properties;
		}

		if ( 1 === count( $properties ) ) {
			return $this->apply_property_selection( $properties[0], $email );
		}

		$this->settings->save(
			array(
				'account_email'               => $email,
				'property_id'                 => '',
				'measurement_id'              => '',
				'oauth_property_display_name' => '',
				'oauth_connected_at'          => gmdate( 'c' ),
			)
		);

		return $this->public_status();
	}

	/**
	 * @param array{
	 *   property_id: string,
	 *   property_display_name: string,
	 *   measurement_id: string,
	 *   account_display_name?: string
	 * } $property
	 * @return array<string, mixed>
	 */
	private function apply_property_selection( array $property, string $email = '' ): array {
		if ( '' === $email ) {
			$email = $this->settings->account_email();
		}

		if ( '' === $email ) {
			$shared = $this->oauth->public_status();
			$email  = sanitize_email( (string) ( $shared['account_email'] ?? '' ) );
		}

		$this->settings->save(
			array(
				'account_email'               => $email,
				'property_id'                 => $property['property_id'],
				'measurement_id'              => $property['measurement_id'],
				'oauth_property_display_name' => sanitize_text_field( $property['property_display_name'] ),
				'oauth_connected_at'          => gmdate( 'c' ),
			)
		);

		return $this->public_status();
	}
}
