<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Activity\ActivityLogService;
use ComplyOps\Integration\GoogleAnalytics\GoogleAnalyticsSettings;
use ComplyOps\Integration\GoogleTagManager\GoogleTagManagerOAuthService;
use ComplyOps\Integration\GoogleTagManager\GoogleTagManagerSettings;
use ComplyOps\Security\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for Google Tag Manager OAuth.
 */
final class GoogleTagManagerOAuthController {

	public function __construct(
		private readonly GoogleTagManagerOAuthService $oauth = new GoogleTagManagerOAuthService(),
		private readonly GoogleTagManagerSettings $settings = new GoogleTagManagerSettings(),
		private readonly ActivityLogService $activity_log = new ActivityLogService(),
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/google-tag-manager/oauth/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/google-tag-manager/oauth/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/google-tag-manager/oauth/disconnect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/google-tag-manager/containers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_containers' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/google-tag-manager/container',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'select_container' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);
	}

	public function start( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->oauth->start();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result );
	}

	public function complete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$code  = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );

		if ( '' === $code || '' === $state ) {
			return new WP_Error(
				'complyops_gtm_oauth_missing_params',
				__( 'Google sign-in did not return the expected parameters.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$result = $this->oauth->complete( $code, $state );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->activity_log->record(
			'integration_connected',
			__( 'Google Tag Manager was connected with Google sign-in.', 'complyops' ),
			'integrations',
			'google_tag_manager'
		);

		return new WP_REST_Response(
			array(
				'oauth'              => $result,
				'google_tag_manager' => $this->settings->admin_config(),
			)
		);
	}

	public function disconnect( WP_REST_Request $request ): WP_REST_Response {
		$this->oauth->disconnect();

		$this->activity_log->record(
			'integration_disconnected',
			__( 'Google Tag Manager was disconnected.', 'complyops' ),
			'integrations',
			'google_tag_manager'
		);

		return new WP_REST_Response(
			array(
				'oauth'              => $this->oauth->public_status(),
				'google_tag_manager' => $this->settings->admin_config(),
				'google_analytics'   => ( new GoogleAnalyticsSettings() )->admin_config(),
			)
		);
	}

	public function list_containers( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->oauth->list_containers();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'containers' => $result,
			)
		);
	}

	public function select_container( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$container_id = sanitize_text_field( (string) $request->get_param( 'container_id' ) );

		if ( '' === $container_id ) {
			return new WP_Error(
				'complyops_gtm_invalid_container',
				__( 'Choose a valid Google Tag Manager container.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$result = $this->oauth->select_container( $container_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->activity_log->record(
			'integration_updated',
			__( 'Google Tag Manager container was attached.', 'complyops' ),
			'integrations',
			'google_tag_manager'
		);

		return new WP_REST_Response(
			array(
				'oauth'              => $result,
				'google_tag_manager' => $this->settings->admin_config(),
			)
		);
	}
}
