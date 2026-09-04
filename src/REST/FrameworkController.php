<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlCatalog;
use ComplyOps\Control\ControlOverrideService;
use ComplyOps\Framework\FrameworkPackService;
use ComplyOps\Plugin;
use ComplyOps\Security\Capabilities;
use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for compliance frameworks.
 */
final class FrameworkController {

	public function __construct(
		private readonly FrameworkPackService $packs = new FrameworkPackService(),
		private readonly ControlOverrideService $overrides = new ControlOverrideService(),
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/frameworks',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list' ),
				'permission_callback' => static fn (): bool => Capabilities::can_view(),
				'args'                => array(
					'include_inactive' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/frameworks/(?P<id>[a-z0-9-]+)/activate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'activate' ),
				'permission_callback' => static fn (): bool => Capabilities::can_manage(),
				'args'                => array(
					'id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/frameworks/(?P<id>[a-z0-9-]+)/deactivate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'deactivate' ),
				'permission_callback' => static fn (): bool => Capabilities::can_manage(),
				'args'                => array(
					'id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	public function list( WP_REST_Request $request ): WP_REST_Response {
		$include_inactive = (bool) $request->get_param( 'include_inactive' );

		if ( $include_inactive && Capabilities::can_manage() ) {
			return new WP_REST_Response(
				array(
					'frameworks' => $this->packs->cataloged_frameworks( $this->overrides ),
				)
			);
		}

		$frameworks = Plugin::instance()->frameworks()->all();

		return new WP_REST_Response(
			array(
				'frameworks' => array_map(
					static fn ( $framework ): array => array(
						'id'    => $framework->id(),
						'label' => $framework->label(),
					),
					$frameworks
				),
			)
		);
	}

	public function activate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->set_status( (string) $request->get_param( 'id' ), true );
	}

	public function deactivate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->set_status( (string) $request->get_param( 'id' ), false );
	}

	private function set_status( string $framework_id, bool $active ): WP_REST_Response|WP_Error {
		if ( ! $this->framework_exists( $framework_id ) ) {
			return new WP_Error(
				'complyops_unknown_framework',
				__( 'Unknown compliance framework.', 'complyops' ),
				array( 'status' => 404 )
			);
		}

		$this->packs->set_active( $framework_id, $active );

		$summary = null;

		foreach ( $this->packs->cataloged_frameworks( $this->overrides ) as $framework ) {
			if ( $framework['id'] === $framework_id ) {
				$summary = $framework;
				break;
			}
		}

		return new WP_REST_Response(
			array(
				'framework' => $summary,
				'active'    => $active,
			)
		);
	}

	private function framework_exists( string $framework_id ): bool {
		foreach ( $this->packs->cataloged_frameworks( $this->overrides ) as $framework ) {
			if ( $framework['id'] === $framework_id ) {
				return true;
			}
		}

		return false;
	}
}
