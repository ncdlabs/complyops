<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Audit\AuditService;
use ComplyOps\Enforcement\EnforcementSettings;
use ComplyOps\Enforcement\SiteKitEnforcementAdvisor;
use ComplyOps\Integration\GoogleAnalytics\GoogleAnalyticsSettings;
use ComplyOps\Integration\GoogleTagManager\GoogleTagManagerSettings;
use ComplyOps\Security\Capabilities;
use ComplyOps\Verification\BrowserVerificationSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for site discovery.
 */
final class DiscoveryController {

	public function __construct(
		private readonly AuditService $audits,
		private readonly EnforcementSettings $enforcement = new EnforcementSettings(),
		private readonly SiteKitEnforcementAdvisor $site_kit_advisor = new SiteKitEnforcementAdvisor(),
	) {
	}

	public function register_routes(): void {
		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/discovery',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get' ),
				'permission_callback' => static fn (): bool => Capabilities::can_view(),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'integrations' ),
				'permission_callback' => static fn (): bool => Capabilities::can_view(),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/integrations/site-kit/apply-recommended',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply_site_kit_recommended' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/forms',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'forms' ),
				'permission_callback' => static fn (): bool => Capabilities::can_view(),
			)
		);
	}

	public function get( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->audits->discover() );
	}

	public function integrations( WP_REST_Request $request ): WP_REST_Response {
		$discovery = $this->audits->discover();

		return new WP_REST_Response(
			array(
				'integrations'       => $discovery['integrations'] ?? array(),
				'forms'              => $discovery['forms'] ?? array(),
				'consent'            => $discovery['consent'] ?? array(),
				'enforcement'        => $discovery['enforcement'] ?? array(),
				'google_analytics'   => array_merge(
					is_array( $discovery['google_analytics'] ?? null ) ? $discovery['google_analytics'] : array(),
					array(
						'manual' => ( new GoogleAnalyticsSettings() )->admin_config(),
					)
				),
				'google_tag_manager'   => array_merge(
					is_array( $discovery['google_tag_manager'] ?? null ) ? $discovery['google_tag_manager'] : array(),
					array(
						'manual' => ( new GoogleTagManagerSettings() )->admin_config(),
					)
				),
				'third_party'        => $discovery['third_party'] ?? array(),
				'site_kit'           => $discovery['site_kit'] ?? array(),
				'browser_verification' => array_merge(
					is_array( $discovery['browser_verification'] ?? null ) ? $discovery['browser_verification'] : array(),
					array(
						'settings' => ( new BrowserVerificationSettings() )->admin_config(),
					)
				),
				'captured_at'          => $discovery['captured_at'] ?? null,
			)
		);
	}

	public function apply_site_kit_recommended( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$discovery      = $this->audits->discover();
		$site_kit       = is_array( $discovery['site_kit'] ?? null ) ? $discovery['site_kit'] : array();
		$recommendation = $this->site_kit_advisor->recommend( $site_kit );

		if ( empty( $recommendation['applicable'] ) ) {
			return new WP_Error(
				'complyops_site_kit_not_applicable',
				__( 'No Site Kit recommendations are available for this site.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$recommended = is_array( $recommendation['enforcement'] ?? null ) ? $recommendation['enforcement'] : array();
		$this->enforcement->save( $recommended );

		return new WP_REST_Response(
			array(
				'enforcement'      => $this->enforcement->all(),
				'enforcement_public' => $this->enforcement->public_config(),
				'site_kit'         => $discovery['site_kit'] ?? array(),
				'recommendation'   => $this->site_kit_advisor->recommend( $site_kit ),
			)
		);
	}

	public function forms( WP_REST_Request $request ): WP_REST_Response {
		$discovery = $this->audits->discover();
		$forms     = $discovery['forms'] ?? array();

		return new WP_REST_Response(
			array(
				'forms'       => is_array( $forms ) ? ( $forms['inventory']['forms'] ?? array() ) : array(),
				'summary'     => is_array( $forms ) ? ( $forms['inventory']['summary'] ?? array() ) : array(),
				'plugins'     => is_array( $forms ) ? ( $forms['plugins'] ?? array() ) : array(),
				'captured_at' => $discovery['captured_at'] ?? null,
			)
		);
	}
}
