<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Audit\AuditService;
use ComplyOps\Audit\AuditTrigger;
use ComplyOps\Security\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for audit operations.
 */
final class AuditController {

	public function __construct(
		private readonly AuditService $audits,
	) {
	}

	public function register_routes(): void {
		$namespace = COMPLYOPS_REST_NAMESPACE;

		register_rest_route(
			$namespace,
			'/audit/latest',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'latest' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'framework' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'required'          => false,
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/audits',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'framework' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'required'          => false,
						),
						'limit'     => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
						'offset'    => array(
							'type'    => 'integer',
							'default' => 0,
							'minimum' => 0,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'can_run' ),
					'args'                => array(
						'framework' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'default'           => 'gdpr',
						),
						'trigger'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'default'           => 'manual',
							'enum'              => array( 'manual', 'scheduled', 'wizard' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/audits/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/findings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'findings' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'audit_id'  => array(
						'type'     => 'integer',
						'required' => false,
					),
					'framework' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'required'          => false,
					),
					'scope'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'default'           => 'issues',
						'enum'              => array( 'issues', 'all' ),
					),
				),
			)
		);
	}

	public function can_view(): bool {
		return Capabilities::can_view();
	}

	public function can_run(): bool {
		return Capabilities::can_run_audits();
	}

	public function latest( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$framework = $request->get_param( 'framework' );
		$framework = is_string( $framework ) && '' !== $framework ? $framework : null;
		$audit     = $this->audits->latest( $framework );

		if ( null === $audit ) {
			return new WP_Error(
				'complyops_no_audit',
				__( 'No audit runs found.', 'complyops' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $this->serialize_audit( $audit, true ) );
	}

	public function list( WP_REST_Request $request ): WP_REST_Response {
		$framework = $request->get_param( 'framework' );
		$framework = is_string( $framework ) && '' !== $framework ? $framework : null;
		$limit     = (int) $request->get_param( 'limit' );
		$offset    = (int) $request->get_param( 'offset' );

		$runs = $this->audits->history( $limit, $offset, $framework );

		return new WP_REST_Response(
			array(
				'audits' => array_map(
					fn ( $audit ) => $this->serialize_audit( $audit, false ),
					$runs
				),
			)
		);
	}

	public function get( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$audit_id = (int) $request->get_param( 'id' );
		$audit    = $this->audits->find( $audit_id );

		if ( null === $audit ) {
			return new WP_Error(
				'complyops_audit_not_found',
				__( 'Audit run not found.', 'complyops' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $this->serialize_audit( $audit, true ) );
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$framework = (string) $request->get_param( 'framework' );
		$trigger   = AuditTrigger::from( (string) $request->get_param( 'trigger' ) );

		try {
			$audit = $this->audits->run( $framework, $trigger );
		} catch ( \Throwable $exception ) {
			return new WP_Error(
				'complyops_audit_failed',
				$exception->getMessage(),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( $this->serialize_audit( $audit, true ), 201 );
	}

	public function findings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$audit_id = $request->get_param( 'audit_id' );

		if ( null === $audit_id ) {
			$framework = $request->get_param( 'framework' );
			$framework = is_string( $framework ) && '' !== $framework ? $framework : null;
			$audit     = $this->audits->latest( $framework );

			if ( null === $audit ) {
				return new WP_Error(
					'complyops_no_audit',
					__( 'No audit runs found.', 'complyops' ),
					array( 'status' => 404 )
				);
			}

			$audit_id = $audit->id;
		}

		return new WP_REST_Response(
			array(
				'audit_id' => (int) $audit_id,
				'findings' => $this->audits->findings_for(
					(int) $audit_id,
					(string) $request->get_param( 'scope' )
				),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serialize_audit( \ComplyOps\Audit\AuditRun $audit, bool $include_results ): array {
		$data = $audit->to_array();

		if ( $include_results ) {
			$data['results'] = $this->audits->results_for( $audit->id );
		}

		return $data;
	}
}
