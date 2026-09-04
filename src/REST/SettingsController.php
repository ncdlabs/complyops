<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Activity\ActivityLogService;
use ComplyOps\Consent\ConsentSettings;
use ComplyOps\Enforcement\EnforcementSettings;
use ComplyOps\Integration\GoogleAnalytics\GoogleAnalyticsSettings;
use ComplyOps\Monitoring\MonitoringScheduler;
use ComplyOps\PublicStatus\PublicStatusSettings;
use ComplyOps\Security\Capabilities;
use ComplyOps\Verification\BrowserVerificationRequestClient;
use ComplyOps\Verification\BrowserVerificationSettings;
use ComplyOps\Verification\RemoteBrowserVerificationClient;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST routes for plugin settings.
 */
final class SettingsController {

	public function __construct(
		private readonly ConsentSettings $consent = new ConsentSettings(),
		private readonly EnforcementSettings $enforcement = new EnforcementSettings(),
		private readonly GoogleAnalyticsSettings $google_analytics = new GoogleAnalyticsSettings(),
		private readonly BrowserVerificationSettings $browser_verification = new BrowserVerificationSettings(),
		private readonly PublicStatusSettings $public_status = new PublicStatusSettings(),
		private readonly ActivityLogService $activity_log = new ActivityLogService(),
	) {
	}

	public const SETUP_COMPLETE_OPTION = 'complyops_setup_complete';
	public const SETUP_DISMISSED_FOREVER_OPTION = 'complyops_setup_dismissed_forever';
	public const SETUP_PENDING_OPTION = 'complyops_setup_pending';
	public const SETUP_BANNER_DECIDED_OPTION = 'complyops_setup_banner_decided';
	public const TUTORIAL_COMPLETE_OPTION = 'complyops_tutorial_complete';
	public const TUTORIAL_DISMISSED_FOREVER_OPTION = 'complyops_tutorial_dismissed_forever';

	public function register_routes(): void {
		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get' ),
					'permission_callback' => static fn (): bool => Capabilities::can_view(),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
				),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/consent/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'consent_config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/browser-verification/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_browser_verification' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/browser-verification/request',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_browser_verification_request' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);

		register_rest_route(
			COMPLYOPS_REST_NAMESPACE,
			'/browser-verification/claim',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'claim_browser_verification_request' ),
				'permission_callback' => static fn (): bool => current_user_can( Capabilities::MANAGE ),
			)
		);
	}

	public function get(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'consent'                => $this->consent->all(),
				'consent_public'         => $this->consent->public_config(),
				'enforcement'            => $this->enforcement->all(),
				'enforcement_public'     => $this->enforcement->public_config(),
				'google_analytics'       => $this->google_analytics->admin_config(),
				'browser_verification'   => $this->browser_verification->admin_config(),
				'monitoring_interval'    => get_option( MonitoringScheduler::OPTION_INTERVAL, 'weekly' ),
				'public_status'          => $this->public_status->admin_config(),
				'setup_wizard'           => $this->setup_wizard_config(),
				'tutorial'               => $this->tutorial_config(),
			)
		);
	}

	/**
	 * @return array<string, bool>
	 */
	private function setup_wizard_config(): array {
		$complete          = (bool) get_option( self::SETUP_COMPLETE_OPTION, false );
		$dismissed_forever = (bool) get_option( self::SETUP_DISMISSED_FOREVER_OPTION, false );
		$pending           = (bool) get_option( self::SETUP_PENDING_OPTION, false );
		$banner_decided    = (bool) get_option( self::SETUP_BANNER_DECIDED_OPTION, false );

		$show = $pending && ! $complete && ! $dismissed_forever;

		return array(
			'complete'          => $complete,
			'dismissed_forever' => $dismissed_forever,
			'pending'           => $pending,
			'banner_decided'    => $banner_decided,
			// Session dismiss is client-side; forever dismiss is tutorial-only.
			'show_wizard'       => $show,
			'show_prompt'       => $show,
		);
	}

	/**
	 * @return array<string, bool>
	 */
	private function tutorial_config(): array {
		$setup_complete    = (bool) get_option( self::SETUP_COMPLETE_OPTION, false );
		$complete          = (bool) get_option( self::TUTORIAL_COMPLETE_OPTION, false );
		$dismissed_forever = (bool) get_option( self::TUTORIAL_DISMISSED_FOREVER_OPTION, false );

		return array(
			'complete'          => $complete,
			'dismissed_forever' => $dismissed_forever,
			'show'              => $setup_complete && ! $complete && ! $dismissed_forever,
		);
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$consent          = $request->get_param( 'consent' );
		$enforcement      = $request->get_param( 'enforcement' );
		$google_analytics     = $request->get_param( 'google_analytics' );
		$browser_verification = $request->get_param( 'browser_verification' );
		$monitoring           = $request->get_param( 'monitoring_interval' );
		$public_status        = $request->get_param( 'public_status' );
		$setup_complete          = $request->get_param( 'setup_complete' );
		$setup_dismissed_forever = $request->get_param( 'setup_dismissed_forever' );
		$setup_banner_decided    = $request->get_param( 'setup_banner_decided' );
		$tutorial_complete       = $request->get_param( 'tutorial_complete' );
		$tutorial_dismissed_forever = $request->get_param( 'tutorial_dismissed_forever' );

		if ( null !== $consent && ! is_array( $consent ) ) {
			return new WP_Error(
				'complyops_invalid_settings',
				__( 'Consent settings must be an object.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		if ( null !== $enforcement && ! is_array( $enforcement ) ) {
			return new WP_Error(
				'complyops_invalid_settings',
				__( 'Enforcement settings must be an object.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		if ( null !== $google_analytics && ! is_array( $google_analytics ) ) {
			return new WP_Error(
				'complyops_invalid_settings',
				__( 'Google Analytics settings must be an object.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		if ( null !== $browser_verification && ! is_array( $browser_verification ) ) {
			return new WP_Error(
				'complyops_invalid_settings',
				__( 'Browser verification settings must be an object.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		if ( null !== $public_status && ! is_array( $public_status ) ) {
			return new WP_Error(
				'complyops_invalid_settings',
				__( 'Public status settings must be an object.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		if ( is_array( $consent ) ) {
			$this->consent->save( $consent );
			$this->activity_log->record(
				'settings_updated',
				__( 'Consent settings were updated.', 'complyops' ),
				'settings',
				'consent'
			);
		}

		if ( is_array( $enforcement ) ) {
			$this->enforcement->save( $enforcement );
			$this->activity_log->record(
				'settings_updated',
				__( 'Enforcement settings were updated.', 'complyops' ),
				'settings',
				'enforcement'
			);
		}

		if ( is_array( $google_analytics ) ) {
			$this->google_analytics->save( $google_analytics );
			$this->activity_log->record(
				'settings_updated',
				__( 'Google Analytics settings were updated.', 'complyops' ),
				'settings',
				'google_analytics'
			);
		}

		if ( is_array( $browser_verification ) ) {
			try {
				$this->browser_verification->save( $browser_verification );
			} catch ( \RuntimeException $exception ) {
				return new WP_Error(
					'complyops_browser_verification_save_failed',
					$exception->getMessage(),
					array( 'status' => 500 )
				);
			}

			$this->activity_log->record(
				'settings_updated',
				__( 'Browser verification settings were updated.', 'complyops' ),
				'settings',
				'browser_verification'
			);
		}

		if ( is_string( $monitoring ) && in_array( $monitoring, array( 'disabled', 'daily', 'weekly', 'monthly' ), true ) ) {
			update_option( MonitoringScheduler::OPTION_INTERVAL, $monitoring, false );
			$this->activity_log->record(
				'monitoring_updated',
				sprintf(
					/* translators: %s: monitoring interval */
					__( 'Monitoring interval set to %s.', 'complyops' ),
					$monitoring
				),
				'settings',
				'monitoring'
			);
		}

		if ( is_array( $public_status ) ) {
			$previous_page_enabled = $this->public_status->page_enabled();
			$this->public_status->save( $public_status );
			$this->activity_log->record(
				'settings_updated',
				__( 'Public compliance status settings were updated.', 'complyops' ),
				'settings',
				'public_status'
			);

			if ( $previous_page_enabled !== $this->public_status->page_enabled() ) {
				flush_rewrite_rules();
			}
		}

		if ( null !== $setup_complete ) {
			$complete = (bool) $setup_complete;
			update_option( self::SETUP_COMPLETE_OPTION, $complete, false );

			if ( $complete ) {
				update_option( self::SETUP_PENDING_OPTION, false, false );
			}
		}

		if ( null !== $setup_banner_decided && (bool) $setup_banner_decided ) {
			update_option( self::SETUP_BANNER_DECIDED_OPTION, true, false );
		}

		if ( null !== $setup_dismissed_forever && (bool) $setup_dismissed_forever ) {
			update_option( self::SETUP_DISMISSED_FOREVER_OPTION, true, false );
			update_option( self::SETUP_PENDING_OPTION, false, false );
		}

		if ( null !== $tutorial_complete ) {
			$complete = (bool) $tutorial_complete;
			update_option( self::TUTORIAL_COMPLETE_OPTION, $complete, false );
		}

		if ( null !== $tutorial_dismissed_forever && (bool) $tutorial_dismissed_forever ) {
			update_option( self::TUTORIAL_DISMISSED_FOREVER_OPTION, true, false );
		}

		return new WP_REST_Response(
			array(
				'consent'              => $this->consent->all(),
				'consent_public'       => $this->consent->public_config(),
				'enforcement'          => $this->enforcement->all(),
				'enforcement_public'   => $this->enforcement->public_config(),
				'google_analytics'       => $this->google_analytics->admin_config(),
				'browser_verification' => $this->browser_verification->admin_config(),
				'monitoring_interval'    => get_option( MonitoringScheduler::OPTION_INTERVAL, 'weekly' ),
				'public_status'          => $this->public_status->admin_config(),
				'setup_wizard'           => $this->setup_wizard_config(),
				'tutorial'               => $this->tutorial_config(),
			)
		);
	}

	public function test_browser_verification( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$service_url = $request->get_param( 'service_url' );
		$token       = $request->get_param( 'token' );

		$result = ( new RemoteBrowserVerificationClient( $this->browser_verification ) )->test_connection(
			is_string( $service_url ) ? $service_url : null,
			is_string( $token ) ? $token : null
		);

		if ( empty( $result['ok'] ) ) {
			return new WP_Error(
				'complyops_browser_verification_test_failed',
				$result['message'],
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response( $result );
	}

	public function start_browser_verification_request( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$return_url = $request->get_param( 'return_url' );
		$return_url = is_string( $return_url ) && '' !== trim( $return_url )
			? esc_url_raw( $return_url )
			: admin_url( 'admin.php?page=complyops-integrations&pack=browser-verification' );

		$current_user = wp_get_current_user();
		$admin_email  = $current_user instanceof \WP_User && is_email( $current_user->user_email )
			? $current_user->user_email
			: null;

		$result = ( new BrowserVerificationRequestClient() )->start_request( $return_url, $admin_email );

		if ( null === $result ) {
			return new WP_Error(
				'complyops_browser_verification_request_failed',
				__( 'Could not start hosted browser verification request.', 'complyops' ),
				array( 'status' => 502 )
			);
		}

		return new WP_REST_Response( $result );
	}

	public function claim_browser_verification_request( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$request_id    = $request->get_param( 'request_id' );
		$exchange_code = $request->get_param( 'exchange_code' );

		if ( ! is_string( $request_id ) || ! is_string( $exchange_code ) ) {
			return new WP_Error(
				'complyops_browser_verification_claim_invalid',
				__( 'Request id and exchange code are required.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$result = ( new BrowserVerificationRequestClient() )->claim_request( $request_id, $exchange_code );

		if ( empty( $result['ok'] ) ) {
			return new WP_Error(
				'complyops_browser_verification_claim_failed',
				$result['message'],
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'ok'                   => true,
				'message'              => __( 'Hosted browser verification credentials saved.', 'complyops' ),
				'browser_verification' => $result['settings'],
			)
		);
	}

	public function consent_config(): WP_REST_Response {
		return new WP_REST_Response( $this->consent->public_config() );
	}
}
