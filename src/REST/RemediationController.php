<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Remediation\RemediationService;
use ComplyOps\Security\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for remediation planning and application.
 */
final class RemediationController {

	public function __construct(
		private readonly RemediationService $remediations,
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/remediation/plan',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'plan' ),
				'permission_callback' => static fn (): bool => Capabilities::can_remediate(),
				'args'                => array(
					'audit_id'  => array(
						'type'     => 'integer',
						'required' => false,
					),
					'framework' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'default'           => 'gdpr',
					),
				),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/remediation/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply' ),
				'permission_callback' => static fn (): bool => Capabilities::can_remediate(),
				'args'                => array(
					'audit_id'   => array(
						'type'     => 'integer',
						'required' => false,
					),
					'framework'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'default'           => 'gdpr',
					),
					'action_ids' => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array(
							'type' => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/remediation/(?P<control>[a-zA-Z0-9\-_]+)/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_control' ),
				'permission_callback' => static fn (): bool => Capabilities::can_remediate(),
				'args'                => array(
					'control'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'audit_id'  => array(
						'type'     => 'integer',
						'required' => false,
					),
					'framework' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'default'           => 'gdpr',
					),
				),
			)
		);
	}

	public function plan( WP_REST_Request $request ): WP_REST_Response {
		$audit_id = $request->get_param( 'audit_id' );
		$audit_id = is_numeric( $audit_id ) ? (int) $audit_id : null;
		$framework = (string) $request->get_param( 'framework' );

		return new WP_REST_Response(
			$this->remediations->plan( $audit_id, $framework )
		);
	}

	public function apply( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$action_ids = $request->get_param( 'action_ids' );
		$action_ids = is_array( $action_ids ) ? array_values( array_filter( $action_ids, 'is_string' ) ) : array();

		if ( array() === $action_ids ) {
			return new WP_Error(
				'complyops_invalid_remediation',
				__( 'At least one remediation action is required.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		try {
			$audit_id  = $request->get_param( 'audit_id' );
			$audit_id  = is_numeric( $audit_id ) ? (int) $audit_id : null;
			$framework = (string) $request->get_param( 'framework' );

			return new WP_REST_Response(
				$this->remediations->apply( $action_ids, $audit_id, $framework )
			);
		} catch ( \Throwable $exception ) {
			return new WP_Error(
				'complyops_remediation_failed',
				$exception->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	public function apply_control( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$control = (string) $request->get_param( 'control' );

		try {
			$audit_id  = $request->get_param( 'audit_id' );
			$audit_id  = is_numeric( $audit_id ) ? (int) $audit_id : null;
			$framework = (string) $request->get_param( 'framework' );

			return new WP_REST_Response(
				$this->remediations->apply_control( $control, $audit_id, $framework )
			);
		} catch ( \Throwable $exception ) {
			return new WP_Error(
				'complyops_remediation_failed',
				$exception->getMessage(),
				array( 'status' => 500 )
			);
		}
	}
}
