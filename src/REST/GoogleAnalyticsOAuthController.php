<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Activity\ActivityLogService;
use ComplyOps\Integration\GoogleAnalytics\GoogleAnalyticsOAuthService;
use ComplyOps\Integration\GoogleAnalytics\GoogleAnalyticsSettings;
use ComplyOps\Integration\GoogleTagManager\GoogleTagManagerSettings;
use ComplyOps\Security\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for Google Analytics OAuth.
 */
final class GoogleAnalyticsOAuthController {

	public function __construct(
		private readonly GoogleAnalyticsOAuthService $oauth = new GoogleAnalyticsOAuthService(),
		private readonly GoogleAnalyticsSettings $settings = new GoogleAnalyticsSettings(),
		private readonly ActivityLogService $activity_log = new ActivityLogService(),
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/google-analytics/oauth/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/google-analytics/oauth/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/google-analytics/oauth/disconnect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/google-analytics/oauth/callback',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'callback' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/google-analytics/properties',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_properties' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/google-analytics/property',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'select_property' ),
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
				'complyops_ga_oauth_missing_params',
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
			__( 'Google Analytics was connected with Google sign-in.', 'complyops' ),
			'integrations',
			'google_analytics'
		);

		return new WP_REST_Response(
			array(
				'oauth'              => $result,
				'google_analytics'   => $this->settings->admin_config(),
			)
		);
	}

	public function disconnect( WP_REST_Request $request ): WP_REST_Response {
		$this->oauth->disconnect();

		$this->activity_log->record(
			'integration_disconnected',
			__( 'Google Analytics was disconnected.', 'complyops' ),
			'integrations',
			'google_analytics'
		);

		return new WP_REST_Response(
			array(
				'oauth'              => $this->oauth->public_status(),
				'google_analytics'   => $this->settings->admin_config(),
				'google_tag_manager' => ( new GoogleTagManagerSettings() )->admin_config(),
			)
		);
	}

	public function list_properties( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->oauth->list_properties();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'properties' => $result,
			)
		);
	}

	public function select_property( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$property_id = sanitize_text_field( (string) $request->get_param( 'property_id' ) );

		if ( '' === $property_id ) {
			return new WP_Error(
				'complyops_ga_invalid_property',
				__( 'Choose a valid Google Analytics property.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$result = $this->oauth->select_property( $property_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->activity_log->record(
			'integration_updated',
			__( 'Google Analytics property was attached.', 'complyops' ),
			'integrations',
			'google_analytics'
		);

		return new WP_REST_Response(
			array(
				'oauth'            => $result,
				'google_analytics' => $this->settings->admin_config(),
			)
		);
	}

	public function callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$error = sanitize_text_field( (string) $request->get_param( 'error' ) );

		if ( '' !== $error ) {
			return $this->redirect_with_notice(
				'error',
				__( 'Google sign-in was cancelled or denied.', 'complyops' )
			);
		}

		$code  = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );

		if ( '' === $code || '' === $state ) {
			return $this->redirect_with_notice(
				'error',
				__( 'Google sign-in did not return the expected parameters.', 'complyops' )
			);
		}

		$result = $this->oauth->handle_direct_callback( $code, $state );

		if ( is_wp_error( $result ) ) {
			return $this->redirect_with_notice( 'error', $result->get_error_message() );
		}

		$this->activity_log->record(
			'integration_connected',
			__( 'Google Analytics was connected with Google sign-in.', 'complyops' ),
			'integrations',
			'google_analytics'
		);

		return $this->redirect_with_notice( 'success', __( 'Google Analytics is connected.', 'complyops' ) );
	}

	private function redirect_with_notice( string $status, string $message ): WP_REST_Response {
		$url = add_query_arg(
			array(
				'page'                     => 'complyops-integrations',
				'pack'                     => 'google-analytics',
				'complyops_google_oauth'   => $status,
				'complyops_google_message' => rawurlencode( $message ),
			),
			admin_url( 'admin.php' )
		);

		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', $url );

		return $response;
	}
}
