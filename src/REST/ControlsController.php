<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Activity\ActivityLogService;
use ComplyOps\Control\ApplicabilityService;
use ComplyOps\Control\ControlCatalog;
use ComplyOps\Control\ControlOverrideService;
use ComplyOps\Framework\FrameworkPackService;
use ComplyOps\Security\Capabilities;
use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for the control catalog.
 */
final class ControlsController {

	public function __construct(
		private readonly ControlOverrideService $overrides = new ControlOverrideService(),
		private readonly ActivityLogService $activity_log = new ActivityLogService(),
		private readonly ApplicabilityService $applicability = new ApplicabilityService(),
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/controls',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list' ),
				'permission_callback' => static fn (): bool => Capabilities::can_view(),
				'args'                => array(
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
			'/controls/(?P<control_id>[a-zA-Z0-9\-_]+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update' ),
				'permission_callback' => static fn (): bool => Capabilities::can_manage(),
				'args'                => array(
					'control_id' => array(
						'type'     => 'string',
						'required' => true,
					),
					'framework'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'default'           => 'gdpr',
					),
					'enabled'    => array(
						'type'     => 'boolean',
						'required' => true,
					),
					'comment'    => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/controls/(?P<control_id>[a-zA-Z0-9\-_]+)/applicability',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_applicability' ),
				'permission_callback' => static fn (): bool => Capabilities::can_manage(),
				'args'                => array(
					'control_id' => array(
						'type'     => 'string',
						'required' => true,
					),
					'framework'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'default'           => 'gdpr',
					),
					'state'      => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array(
							ApplicabilityService::STATE_PENDING,
							ApplicabilityService::STATE_APPLICABLE,
							ApplicabilityService::STATE_NOT_APPLICABLE,
						),
					),
					'reason'     => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);
	}

	public function list( WP_REST_Request $request ): WP_REST_Response {
		$framework = (string) $request->get_param( 'framework' );
		$path      = $this->catalog_path( $framework );

		if ( null === $path ) {
			return new WP_REST_Response( array( 'controls' => array() ) );
		}

		$catalog = ControlCatalog::from_file( $path );

		$packs       = new FrameworkPackService();
		$disclaimer  = $packs->pack_disclaimer( $framework );

		return new WP_REST_Response(
			array(
				'framework'  => $catalog->framework,
				'version'    => $catalog->version,
				'disclaimer' => $disclaimer,
				'controls'   => array_map(
					fn ( $control ): array => array_merge(
						array(
							'id'                => $control->definition()->id,
							'title'             => $control->definition()->title,
							'description'       => $control->definition()->description,
							'category'          => $control->definition()->category,
							'severity'          => $control->definition()->severity->value,
							'capability'        => $control->definition()->capability->value,
							'test_key'          => $control->definition()->test_key,
							'verification_type' => $control->definition()->verification_type->value,
							'references'        => $control->definition()->references,
							'applicability'     => $this->applicability->state_for_control(
								$framework,
								$control->definition()->id
							),
						),
						$this->overrides->state_for_control( $framework, $control->definition()->id )
					),
					$catalog->controls()
				),
			)
		);
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$framework  = (string) $request->get_param( 'framework' );
		$control_id = (string) $request->get_param( 'control_id' );
		$enabled    = (bool) $request->get_param( 'enabled' );
		$comment    = $request->get_param( 'comment' );
		$comment    = is_string( $comment ) ? trim( $comment ) : '';

		$path = $this->catalog_path( $framework );

		if ( null === $path ) {
			return new WP_Error(
				'complyops_unknown_framework',
				__( 'Unknown compliance framework.', 'complyops' ),
				array( 'status' => 404 )
			);
		}

		$catalog = ControlCatalog::from_file( $path );
		$exists  = false;

		foreach ( $catalog->controls() as $control ) {
			if ( $control->definition()->id === $control_id ) {
				$exists = true;
				break;
			}
		}

		if ( ! $exists ) {
			return new WP_Error(
				'complyops_unknown_control',
				__( 'Unknown control.', 'complyops' ),
				array( 'status' => 404 )
			);
		}

		try {
			$state = $enabled
				? $this->overrides->enable( $framework, $control_id )
				: $this->overrides->disable( $framework, $control_id, $comment );
		} catch ( InvalidArgumentException $exception ) {
			return new WP_Error(
				'complyops_invalid_control_override',
				$exception->getMessage(),
				array( 'status' => 400 )
			);
		}

		$this->activity_log->record(
			$enabled ? 'control_enabled' : 'control_disabled',
			$enabled
				? sprintf(
					/* translators: 1: control id, 2: framework id */
					__( 'Control %1$s enabled for %2$s.', 'complyops' ),
					$control_id,
					strtoupper( $framework )
				)
				: sprintf(
					/* translators: 1: control id, 2: framework id */
					__( 'Control %1$s disabled for %2$s.', 'complyops' ),
					$control_id,
					strtoupper( $framework )
				),
			'control',
			$control_id,
			array(
				'framework' => $framework,
				'comment'   => $enabled ? null : $comment,
			)
		);

		return new WP_REST_Response(
			array(
				'control_id' => $control_id,
				'framework'  => $framework,
				'state'      => $state,
			)
		);
	}

	public function update_applicability( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$framework  = (string) $request->get_param( 'framework' );
		$control_id = (string) $request->get_param( 'control_id' );
		$state      = (string) $request->get_param( 'state' );
		$reason     = $request->get_param( 'reason' );
		$reason     = is_string( $reason ) ? trim( $reason ) : null;

		try {
			$record = $this->applicability->set_state( $framework, $control_id, $state, $reason );
		} catch ( \InvalidArgumentException $exception ) {
			return new WP_Error(
				'complyops_invalid_applicability',
				$exception->getMessage(),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'control_id'    => $control_id,
				'framework'     => $framework,
				'applicability' => $record,
			)
		);
	}

	private function catalog_path( string $framework ): ?string {
		$builtin = COMPLYOPS_PLUGIN_DIR . 'data/controls/' . $framework . '.json';

		if ( is_readable( $builtin ) ) {
			return $builtin;
		}

		$packs = new FrameworkPackService();
		$path  = $packs->catalog_path( $framework );

		return is_readable( $path ) ? $path : null;
	}
}
