<?php

declare(strict_types=1);

namespace ComplyOps\REST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Framework\FrameworkPackService;
use ComplyOps\Plugin;

/**
 * Registers ComplyOps REST API routes.
 */
final class RestRegistrar {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$plugin = Plugin::instance();
		( new AuditController( $plugin->audits() ) )->register_routes();
		( new DiscoveryController( $plugin->audits() ) )->register_routes();
		( new RemediationController( $plugin->remediations() ) )->register_routes();
		( new EvidenceController( $plugin->evidence(), $plugin->activity_log() ) )->register_routes();
		( new FrameworkController() )->register_routes();
		( new FrameworkPackController( new FrameworkPackService() ) )->register_routes();
		( new ControlsController() )->register_routes();
		( new SettingsController() )->register_routes();
		( new GoogleAnalyticsOAuthController() )->register_routes();
		( new GoogleOAuthController() )->register_routes();
		( new GoogleTagManagerOAuthController() )->register_routes();
		( new PublicStatusController() )->register_routes();
		( new NotificationsController() )->register_routes();
	}
}
