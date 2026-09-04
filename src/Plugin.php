<?php

declare(strict_types=1);

namespace ComplyOps;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Audit\AuditService;
use ComplyOps\Audit\ScoreCalculator;
use ComplyOps\Activity\ActivityLogService;
use ComplyOps\Database\AuditRepository;
use ComplyOps\Database\AuditResultRepository;
use ComplyOps\Database\EvidenceRepository;
use ComplyOps\Database\ExpectedStateRepository;
use ComplyOps\Database\Installer;
use ComplyOps\Database\RemediationRepository;
use ComplyOps\Detection\DiscoveryService;
use ComplyOps\Evidence\EvidenceService;
use ComplyOps\Monitoring\DriftDetectionService;
use ComplyOps\Monitoring\MonitoringScheduler;
use ComplyOps\Notification\NotificationDispatcher;
use ComplyOps\PublicStatus\PublicStatusPage;
use ComplyOps\PublicStatus\PublicStatusSettings;
use ComplyOps\Remediation\RemediationService;
use ComplyOps\Admin\AdminAssets;
use ComplyOps\Admin\AdminMenu;
use ComplyOps\Admin\DashboardWidget;
use ComplyOps\Consent\ConsentManager;
use ComplyOps\Consent\ConsentSettings;
use ComplyOps\Consent\WpConsentApiBridge;
use ComplyOps\Enforcement\EnforcementManager;
use ComplyOps\Enforcement\EnforcementSettings;
use ComplyOps\Enforcement\PiiSettings;
use ComplyOps\Framework\CatalogFramework;
use ComplyOps\Framework\FrameworkPackService;
use ComplyOps\Framework\FrameworkRegistry;
use ComplyOps\Framework\GDPR\GDPRFramework;
use ComplyOps\Framework\NIST\NISTFramework;
use ComplyOps\Framework\OWASP\OWASPFramework;
use ComplyOps\REST\RestRegistrar;
use ComplyOps\REST\SettingsController;
use ComplyOps\Security\Capabilities;
use ComplyOps\WordPress\WordPressPrivacyManager;

/**
 * Main plugin bootstrap.
 */
final class Plugin {

	private static ?self $instance = null;

	private FrameworkRegistry $frameworks;

	private ?AuditService $audit_service = null;

	private ?RemediationService $remediation_service = null;

	private ?EvidenceService $evidence_service = null;

	private ?ActivityLogService $activity_log_service = null;

	private ?DriftDetectionService $drift_service = null;

	private ?MonitoringScheduler $monitoring_scheduler = null;

	private ?NotificationDispatcher $notification_dispatcher = null;

	private function __construct() {
		$this->frameworks = new FrameworkRegistry();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		register_activation_hook( COMPLYOPS_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( COMPLYOPS_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function activate(): void {
		Installer::install();
		Capabilities::register();
		( new ConsentSettings() )->activate_defaults();
		( new EnforcementSettings() )->activate_defaults();
		( new PiiSettings() )->activate_defaults();
		( new PublicStatusSettings() )->activate_defaults();
		if ( ! get_option( SettingsController::SETUP_DISMISSED_FOREVER_OPTION, false )
			&& ! get_option( SettingsController::SETUP_COMPLETE_OPTION, false ) ) {
			update_option( SettingsController::SETUP_PENDING_OPTION, true, false );
		}
		update_option( MonitoringScheduler::OPTION_INTERVAL, 'weekly', false );
		( new PublicStatusPage() )->register_rewrite();
		$this->monitoring()->activate();
		flush_rewrite_rules();
	}

	public function deactivate(): void {
		$this->monitoring()->deactivate();
		flush_rewrite_rules();
	}

	public function init(): void {
		Installer::maybe_upgrade();
		$this->register_frameworks();
		( new RestRegistrar() )->register();
		( new AdminMenu() )->register();
		( new AdminAssets() )->register();
		( new DashboardWidget() )->register();
		( new ConsentManager() )->register();
		( new WpConsentApiBridge() )->register();
		( new EnforcementManager() )->register();
		( new WordPressPrivacyManager() )->register();
		( new PublicStatusPage() )->register();
		$this->monitoring()->register();
		$this->register_browser_verification_hooks();
		add_action( 'admin_init', array( $this->notifications(), 'handle_dismiss_request' ) );
	}

	public function notifications(): NotificationDispatcher {
		if ( null === $this->notification_dispatcher ) {
			$this->notification_dispatcher = new NotificationDispatcher();
		}

		return $this->notification_dispatcher;
	}

	public function frameworks(): FrameworkRegistry {
		return $this->frameworks;
	}

	public function audits(): AuditService {
		if ( null === $this->audit_service ) {
			$this->audit_service = new AuditService(
				$this->frameworks,
				new AuditRepository(),
				new AuditResultRepository(),
				$this->evidence(),
				new ScoreCalculator(),
				new DiscoveryService(),
				$this->activity_log(),
			);
		}

		return $this->audit_service;
	}

	public function remediations(): RemediationService {
		if ( null === $this->remediation_service ) {
			$this->remediation_service = new RemediationService(
				$this->frameworks,
				$this->audits(),
				new DiscoveryService(),
				new RemediationRepository(),
				$this->evidence(),
				$this->activity_log(),
			);
		}

		return $this->remediation_service;
	}

	public function evidence(): EvidenceService {
		if ( null === $this->evidence_service ) {
			$this->evidence_service = new EvidenceService(
				new EvidenceRepository(),
				new ExpectedStateRepository(),
				new AuditRepository(),
			);
		}

		return $this->evidence_service;
	}

	public function activity_log(): ActivityLogService {
		if ( null === $this->activity_log_service ) {
			$this->activity_log_service = new ActivityLogService();
		}

		return $this->activity_log_service;
	}

	public function drift(): DriftDetectionService {
		if ( null === $this->drift_service ) {
			$this->drift_service = new DriftDetectionService(
				new ExpectedStateRepository(),
				new DiscoveryService(),
				$this->evidence(),
				$this->activity_log(),
			);
		}

		return $this->drift_service;
	}

	public function monitoring(): MonitoringScheduler {
		if ( null === $this->monitoring_scheduler ) {
			$this->monitoring_scheduler = new MonitoringScheduler(
				$this->drift(),
				$this->audits(),
			);
		}

		return $this->monitoring_scheduler;
	}

	private function register_browser_verification_hooks(): void {
		$verify_url = getenv( 'COMPLYOPS_BROWSER_VERIFY_URL' );

		if ( ! is_string( $verify_url ) || '' === trim( $verify_url ) ) {
			return;
		}

		$verify_url = esc_url_raw( trim( $verify_url ) );

		if ( '' === $verify_url ) {
			return;
		}

		add_filter(
			'complyops_browser_verify_url',
			static function ( string $url ) use ( $verify_url ): string {
				unset( $url );

				return $verify_url;
			}
		);
	}

	private function register_frameworks(): void {
		$packs = new FrameworkPackService();

		if ( $packs->is_active( 'gdpr' ) ) {
			$this->frameworks->register( new GDPRFramework() );
		}

		if ( $packs->is_active( 'owasp' ) ) {
			$this->frameworks->register( new OWASPFramework() );
		}

		if ( $packs->is_active( 'nist-csf' ) ) {
			$this->frameworks->register( new NISTFramework() );
		}

		foreach ( $packs->installed() as $installed ) {
			if ( ! $packs->is_active( $installed['id'] ) ) {
				continue;
			}

			$this->frameworks->register(
				new CatalogFramework(
					$installed['id'],
					$installed['label'],
					$installed['path']
				)
			);
		}
	}
}
