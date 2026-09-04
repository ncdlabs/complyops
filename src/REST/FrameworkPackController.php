<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Framework\FrameworkPackService;
use ComplyOps\Security\Capabilities;
use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for framework pack preview and installation.
 */
final class FrameworkPackController {

	public function __construct(
		private readonly FrameworkPackService $packs,
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/frameworks/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => static fn (): bool => Capabilities::can_manage(),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/frameworks/install',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'install' ),
				'permission_callback' => static fn (): bool => Capabilities::can_manage(),
			)
		);
	}

	public function preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$extracted = $this->extract_request( $request );

		if ( is_wp_error( $extracted ) ) {
			return $extracted;
		}

		try {
			return new WP_REST_Response(
				$this->packs->preview( $extracted['pack'], $extracted['unlock_key'] )
			);
		} catch ( InvalidArgumentException $exception ) {
			return new WP_Error(
				'complyops_invalid_pack',
				$exception->getMessage(),
				array( 'status' => 400 )
			);
		}
	}

	public function install( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$extracted = $this->extract_request( $request );

		if ( is_wp_error( $extracted ) ) {
			return $extracted;
		}

		try {
			return new WP_REST_Response(
				$this->packs->install( $extracted['pack'], $extracted['unlock_key'] )
			);
		} catch ( InvalidArgumentException $exception ) {
			return new WP_Error(
				'complyops_invalid_pack',
				$exception->getMessage(),
				array( 'status' => 400 )
			);
		} catch ( \RuntimeException $exception ) {
			return new WP_Error(
				'complyops_install_failed',
				$exception->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * @return array{pack: array<string, mixed>, unlock_key: string}|WP_Error
	 */
	private function extract_request( WP_REST_Request $request ): array|WP_Error {
		/** @var mixed $body */
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new WP_Error(
				'complyops_invalid_pack',
				__( 'Request body must be a JSON object.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$unlock_key = isset( $body['unlock_key'] ) && is_string( $body['unlock_key'] )
			? trim( $body['unlock_key'] )
			: '';

		if ( '' === $unlock_key ) {
			return new WP_Error(
				'complyops_missing_unlock_key',
				__( 'A pack unlock key from your purchase is required.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		if ( isset( $body['pack'] ) && is_array( $body['pack'] ) ) {
			return array(
				'pack'       => $body['pack'],
				'unlock_key' => $unlock_key,
			);
		}

		/** @var array<string, mixed> $body */
		return array(
			'pack'       => $body,
			'unlock_key' => $unlock_key,
		);
	}
}
