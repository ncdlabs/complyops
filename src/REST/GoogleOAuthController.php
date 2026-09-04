<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Integration\Google\GoogleOAuthService;
use ComplyOps\Integration\GoogleAnalytics\GoogleAnalyticsOAuthService;
use ComplyOps\Integration\GoogleTagManager\GoogleTagManagerOAuthService;
use ComplyOps\Security\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Shared REST routes for Google OAuth callbacks.
 */
final class GoogleOAuthController {

	public function __construct(
		private readonly GoogleAnalyticsOAuthService $ga_oauth = new GoogleAnalyticsOAuthService(),
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/google/oauth/callback',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'callback' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
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

		$pack   = $this->resolve_pack_from_state( $state );
		$result = GoogleOAuthService::PACK_GOOGLE_TAG_MANAGER === $pack
			? ( new GoogleTagManagerOAuthService() )->handle_direct_callback( $code, $state )
			: $this->ga_oauth->handle_direct_callback( $code, $state );

		if ( is_wp_error( $result ) ) {
			return $this->redirect_with_notice( 'error', $result->get_error_message(), $pack );
		}

		return $this->redirect_with_notice(
			'success',
			GoogleOAuthService::PACK_GOOGLE_TAG_MANAGER === $pack
				? __( 'Google Tag Manager is connected.', 'complyops' )
				: __( 'Google Analytics is connected.', 'complyops' ),
			$pack
		);
	}

	private function resolve_pack_from_state( string $state ): string {
		$state   = (string) preg_replace( '/[^A-Za-z0-9]/', '', $state );
		$payload = get_transient( 'complyops_google_oauth_state_' . $state );

		if ( is_array( $payload ) && is_string( $payload['pack'] ?? null ) ) {
			return $payload['pack'];
		}

		return GoogleOAuthService::PACK_GOOGLE_ANALYTICS;
	}

	private function redirect_with_notice( string $status, string $message, string $pack = GoogleOAuthService::PACK_GOOGLE_ANALYTICS ): WP_REST_Response {
		$url = add_query_arg(
			array(
				'page'                     => 'complyops-integrations',
				'pack'                     => $pack,
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
