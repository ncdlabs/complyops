<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Activity\ActivityLogService;
use ComplyOps\Control\ControlCatalog;
use ComplyOps\Evidence\EvidenceCsvExporter;
use ComplyOps\Evidence\EvidencePdfExporter;
use ComplyOps\Evidence\EvidenceService;
use ComplyOps\Security\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for compliance evidence.
 */
final class EvidenceController {

	public function __construct(
		private readonly EvidenceService $evidence,
		private readonly ActivityLogService $activity_log,
		private readonly EvidenceCsvExporter $csv = new EvidenceCsvExporter(),
		private readonly EvidencePdfExporter $pdf = new EvidencePdfExporter(),
		private readonly \ComplyOps\Evidence\ManualEvidenceService $manual_evidence = new \ComplyOps\Evidence\ManualEvidenceService(),
	) {
	}

	public function register_routes(): void {
		$namespace = COMPLYOPS_REST_NAMESPACE;

		register_rest_route(
			$namespace,
			'/evidence',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => $this->list_args(),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/evidence/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array_merge(
					$this->list_args(),
					array(
						'format' => array(
							'type'    => 'string',
							'default' => 'json',
							'enum'    => array( 'json', 'csv', 'pdf' ),
						),
					)
				),
			)
		);

		register_rest_route(
			$namespace,
			'/evidence/(?P<id>\d+)',
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
			'/evidence/manual',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_manual' ),
				'permission_callback' => static fn (): bool => Capabilities::can_manage(),
				'args'                => array(
					'framework'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'required'          => true,
					),
					'control_id' => array(
						'type'     => 'string',
						'required' => true,
					),
					'status'     => array(
						'type'    => 'string',
						'default' => 'PASS',
					),
					'note'       => array(
						'type'     => 'string',
						'required' => false,
					),
					'reviewed_at' => array(
						'type'     => 'string',
						'required' => false,
					),
					'review_interval_days' => array(
						'type'    => 'integer',
						'default' => 365,
					),
					'attachment_id' => array(
						'type'     => 'integer',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/activity-log',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'activity_log' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'action' => array(
						'type'     => 'string',
						'required' => false,
					),
					'since'  => array(
						'type'     => 'string',
						'required' => false,
					),
					'limit'  => array(
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 500,
					),
					'offset' => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
				),
			)
		);
	}

	public function can_view(): bool {
		return Capabilities::can_view();
	}

	public function list( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->evidence->list( $this->filters_from_request( $request ) ) );
	}

	public function get( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id     = (int) $request->get_param( 'id' );
		$record = $this->evidence->find( $id );

		if ( null === $record ) {
			return new WP_Error(
				'complyops_evidence_not_found',
				__( 'Evidence record not found.', 'complyops' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $record );
	}

	public function export( WP_REST_Request $request ): WP_REST_Response {
		$filters = $this->filters_from_request( $request );
		$format  = (string) $request->get_param( 'format' );
		$package = $this->evidence->export_package( $filters );

		$this->activity_log->record(
			'evidence_exported',
			sprintf(
				/* translators: %s: export format */
				__( 'Evidence package exported as %s.', 'complyops' ),
				strtoupper( $format )
			),
			'evidence',
			null,
			array(
				'format'    => $format,
				'framework' => $filters['framework'] ?? 'gdpr',
				'audit_id'  => $filters['audit_id'] ?? null,
			)
		);

		if ( 'csv' === $format ) {
			return new WP_REST_Response(
				array(
					'format'   => 'csv',
					'filename' => 'complyops-evidence.csv',
					'content'  => $this->csv->export( $package ),
				)
			);
		}

		if ( 'pdf' === $format ) {
			return new WP_REST_Response(
				array(
					'format'   => 'pdf',
					'filename' => 'complyops-evidence.pdf',
					'content'  => base64_encode( $this->pdf->export( $package ) ),
					'encoding' => 'base64',
				)
			);
		}

		return new WP_REST_Response( $package );
	}

	public function activity_log( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			$this->activity_log->list(
				array(
					'action' => $request->get_param( 'action' ),
					'since'  => $request->get_param( 'since' ),
					'limit'  => (int) $request->get_param( 'limit' ),
					'offset' => (int) $request->get_param( 'offset' ),
				)
			)
		);
	}

	public function save_manual( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! Capabilities::can_manage() ) {
			return new WP_Error(
				'complyops_forbidden',
				__( 'You do not have permission to record manual evidence.', 'complyops' ),
				array( 'status' => 403 )
			);
		}

		$framework  = (string) $request->get_param( 'framework' );
		$control_id = (string) $request->get_param( 'control_id' );
		$path       = COMPLYOPS_PLUGIN_DIR . 'data/controls/' . $framework . '.json';

		if ( ! is_readable( $path ) ) {
			$packs = new \ComplyOps\Framework\FrameworkPackService();
			$path  = $packs->catalog_path( $framework );

			if ( ! is_readable( $path ) ) {
				$pack_path = COMPLYOPS_PLUGIN_DIR . 'data/packs/' . $framework . '.json';
				$path      = is_readable( $pack_path ) ? $pack_path : $path;
			}
		}

		if ( ! is_readable( $path ) ) {
			return new WP_Error(
				'complyops_unknown_framework',
				__( 'Unknown compliance framework.', 'complyops' ),
				array( 'status' => 404 )
			);
		}

		$catalog    = ControlCatalog::from_file( $path );
		$definition = null;

		foreach ( $catalog->controls() as $control ) {
			if ( $control->definition()->id === $control_id ) {
				$definition = $control->definition();
				break;
			}
		}

		if ( null === $definition ) {
			return new WP_Error(
				'complyops_unknown_control',
				__( 'Unknown control.', 'complyops' ),
				array( 'status' => 404 )
			);
		}

		try {
			$record = $this->manual_evidence->save_attestation(
				$definition,
				array(
					'status'               => $request->get_param( 'status' ),
					'note'                 => $request->get_param( 'note' ),
					'reviewed_at'          => $request->get_param( 'reviewed_at' ),
					'review_interval_days' => (int) $request->get_param( 'review_interval_days' ),
					'attachment_id'        => $request->get_param( 'attachment_id' ),
				)
			);
		} catch ( \InvalidArgumentException $exception ) {
			return new WP_Error(
				'complyops_invalid_manual_evidence',
				$exception->getMessage(),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response( $record, 201 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function filters_from_request( WP_REST_Request $request ): array {
		return array_filter(
			array(
				'framework'   => $request->get_param( 'framework' ),
				'audit_id'    => $request->get_param( 'audit_id' ),
				'source'      => $request->get_param( 'source' ),
				'test_method' => $request->get_param( 'test_method' ),
				'control_id'  => $request->get_param( 'control_id' ),
				'search'      => $request->get_param( 'search' ),
				'since'       => $request->get_param( 'since' ),
				'limit'       => $request->get_param( 'limit' ),
				'offset'      => $request->get_param( 'offset' ),
			),
			static fn ( mixed $value ): bool => null !== $value && '' !== $value
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function list_args(): array {
		return array(
			'framework'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'required'          => false,
			),
			'audit_id'    => array(
				'type'     => 'integer',
				'required' => false,
			),
			'source'      => array(
				'type'     => 'string',
				'required' => false,
			),
			'test_method' => array(
				'type'     => 'string',
				'required' => false,
			),
			'control_id'  => array(
				'type'     => 'string',
				'required' => false,
			),
			'search'      => array(
				'type'     => 'string',
				'required' => false,
			),
			'since'       => array(
				'type'     => 'string',
				'required' => false,
			),
			'limit'       => array(
				'type'    => 'integer',
				'default' => 50,
				'minimum' => 1,
				'maximum' => 500,
			),
			'offset'      => array(
				'type'    => 'integer',
				'default' => 0,
				'minimum' => 0,
			),
		);
	}
}
