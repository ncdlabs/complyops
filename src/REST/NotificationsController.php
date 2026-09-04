<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Notification\NotificationService;
use ComplyOps\Security\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for administrator notification bell items.
 */
final class NotificationsController {

	public function __construct(
		private readonly NotificationService $notifications = new NotificationService(),
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/notifications',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list' ),
				'permission_callback' => static fn (): bool => Capabilities::can_view(),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/notifications/dismiss-all',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'dismiss_all' ),
				'permission_callback' => static fn (): bool => Capabilities::can_view(),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/notifications/(?P<id>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/dismiss',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'dismiss' ),
				'permission_callback' => static fn (): bool => Capabilities::can_view(),
				'args'                => array(
					'id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	public function list(): WP_REST_Response {
		$items = $this->notifications->active_payload();

		return new WP_REST_Response(
			array(
				'items' => $items,
				'count' => count( $items ),
			)
		);
	}

	public function dismiss( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = sanitize_text_field( (string) $request->get_param( 'id' ) );
		if ( ! $this->notifications->dismiss_by_id( $id ) ) {
			return new WP_Error(
				'complyops_notification_not_found',
				__( 'Notification not found.', 'complyops' ),
				array( 'status' => 404 )
			);
		}

		delete_transient( 'complyops_drift_notice' );

		return new WP_REST_Response(
			array(
				'dismissed' => true,
				'id'        => $id,
				'count'     => count( $this->notifications->active_payload() ),
			)
		);
	}

	public function dismiss_all(): WP_REST_Response {
		$count = $this->notifications->dismiss_all();
		delete_transient( 'complyops_drift_notice' );

		return new WP_REST_Response(
			array(
				'dismissed' => true,
				'count'     => $count,
			)
		);
	}
}
