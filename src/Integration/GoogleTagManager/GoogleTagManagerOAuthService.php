<?php

declare(strict_types=1);

namespace ComplyOps\Integration\GoogleTagManager;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Integration\Google\GoogleOAuthService;
use ComplyOps\Integration\GoogleAnalytics\GoogleAnalyticsSettings;
use WP_Error;

/**
 * Google Tag Manager OAuth and container selection flows.
 */
final class GoogleTagManagerOAuthService {

	public function __construct(
		private readonly GoogleOAuthService $oauth = new GoogleOAuthService(),
		private readonly GoogleTagManagerSettings $settings = new GoogleTagManagerSettings(),
		private readonly GoogleTagManagerAdminClient $admin = new GoogleTagManagerAdminClient(),
	) {
	}

	public function is_connected(): bool {
		return $this->oauth->is_connected();
	}

	/**
	 * @return array{authorization_url: string, state: string, mode: string}|WP_Error
	 */
	public function start(): array|WP_Error {
		return $this->oauth->start( GoogleOAuthService::PACK_GOOGLE_TAG_MANAGER );
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
		unset( $current['oauth_connected_at'], $current['oauth_container_display_name'] );

		$this->settings->save(
			array_merge(
				$current,
				array(
					'account_email' => '',
					'account_id'    => '',
					'container_id'  => '',
				)
			)
		);

		$ga_settings = new GoogleAnalyticsSettings();
		$ga_current  = $ga_settings->all();
		unset( $ga_current['oauth_connected_at'], $ga_current['oauth_property_display_name'] );
		$ga_settings->save(
			array_merge(
				$ga_current,
				array(
					'account_email'  => '',
					'property_id'    => '',
					'measurement_id' => '',
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
		$container_selected = '' !== $this->settings->container_id();
		$shared    = $this->oauth->public_status();

		return array(
			'connected'                 => $connected,
			'container_selected'          => $container_selected,
			'needs_container_selection'   => $connected && ! $container_selected,
			'account_email'               => sanitize_email( (string) ( $settings['account_email'] ?? $shared['account_email'] ?? '' ) ),
			'container_id'                => $this->settings->container_id(),
			'account_id'                  => $this->settings->account_id(),
			'container_display_name'      => sanitize_text_field( (string) ( $settings['oauth_container_display_name'] ?? '' ) ),
			'connected_at'                => sanitize_text_field( (string) ( $settings['oauth_connected_at'] ?? $shared['connected_at'] ?? '' ) ),
		);
	}

	/**
	 * @return list<array{
	 *   container_id: string,
	 *   container_display_name: string,
	 *   account_id: string,
	 *   account_display_name: string
	 * }>|WP_Error
	 */
	public function list_containers(): array|WP_Error {
		if ( ! $this->is_connected() ) {
			return new WP_Error(
				'complyops_gtm_oauth_not_connected',
				__( 'Connect Google Tag Manager before choosing a container.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$access_token = $this->oauth->api_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		return $this->admin->list_containers( $access_token );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function select_container( string $container_id ): array|WP_Error {
		if ( ! $this->is_connected() ) {
			return new WP_Error(
				'complyops_gtm_oauth_not_connected',
				__( 'Connect Google Tag Manager before choosing a container.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$container_id = strtoupper( trim( $container_id ) );

		if ( ! preg_match( '/^GTM-[A-Z0-9]+$/', $container_id ) ) {
			return new WP_Error(
				'complyops_gtm_invalid_container',
				__( 'Choose a valid Google Tag Manager container.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$access_token = $this->oauth->api_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$containers = $this->admin->list_containers( $access_token );

		if ( is_wp_error( $containers ) ) {
			return $containers;
		}

		$selected = null;

		foreach ( $containers as $container ) {
			if ( $container['container_id'] === $container_id ) {
				$selected = $container;
				break;
			}
		}

		if ( null === $selected ) {
			return new WP_Error(
				'complyops_gtm_invalid_container',
				__( 'That Google Tag Manager container is not available for the connected account.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		return $this->apply_container_selection( $selected );
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

		$containers = $this->admin->list_containers( $access_token );

		if ( is_wp_error( $containers ) ) {
			return $containers;
		}

		if ( 1 === count( $containers ) ) {
			return $this->apply_container_selection( $containers[0], $email );
		}

		$this->settings->save(
			array(
				'account_email'                => $email,
				'account_id'                   => '',
				'container_id'                 => '',
				'oauth_container_display_name' => '',
				'oauth_connected_at'           => gmdate( 'c' ),
			)
		);

		return $this->public_status();
	}

	/**
	 * @param array{
	 *   container_id: string,
	 *   container_display_name: string,
	 *   account_id: string,
	 *   account_display_name?: string
	 * } $container
	 * @return array<string, mixed>
	 */
	private function apply_container_selection( array $container, string $email = '' ): array {
		if ( '' === $email ) {
			$email = $this->settings->account_email();
		}

		if ( '' === $email ) {
			$shared = $this->oauth->public_status();
			$email  = sanitize_email( (string) ( $shared['account_email'] ?? '' ) );
		}

		$this->settings->save(
			array(
				'account_email'                => $email,
				'account_id'                   => (string) ( $container['account_id'] ?? '' ),
				'container_id'                 => (string) ( $container['container_id'] ?? '' ),
				'oauth_container_display_name' => sanitize_text_field( (string) ( $container['container_display_name'] ?? '' ) ),
				'oauth_connected_at'           => gmdate( 'c' ),
			)
		);

		return $this->public_status();
	}
}
