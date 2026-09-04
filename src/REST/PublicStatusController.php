<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\PublicStatus\PublicStatusService;
use ComplyOps\PublicStatus\PublicStatusSettings;
use ComplyOps\Security\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Public compliance status REST endpoints (disabled by default).
 */
final class PublicStatusController {

	public function __construct(
		private readonly PublicStatusSettings $settings = new PublicStatusSettings(),
		private readonly PublicStatusService $service = new PublicStatusService(),
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/public/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/public/status.json',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status_json' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->respond( $request );
	}

	public function status_json( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->respond( $request );
	}

	private function respond( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$is_preview = rest_sanitize_boolean( $request->get_param( 'preview' ) )
			&& current_user_can( Capabilities::MANAGE );

		if ( ! $this->settings->api_enabled() && ! $is_preview ) {
			return new WP_Error(
				'complyops_public_status_disabled',
				__( 'Public compliance status API is disabled.', 'complyops' ),
				array( 'status' => 404 )
			);
		}

		$payload = $this->service->payload( $is_preview );

		if ( null === $payload || array() === $payload ) {
			return new WP_Error(
				'complyops_public_status_unavailable',
				__( 'Public compliance status is temporarily unavailable.', 'complyops' ),
				array( 'status' => 503 )
			);
		}

		$response = new WP_REST_Response( $payload );
		$response->set_headers(
			$is_preview
				? array(
					'Cache-Control' => 'private, no-store, max-age=0',
					'Pragma'        => 'no-cache',
				)
				: array(
					'Cache-Control' => 'public, max-age=300',
				)
		);

		return $response;
	}
}
