<?php

declare(strict_types=1);

namespace ComplyOps\Detection;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Consent\ConsentSettings;
use ComplyOps\Enforcement\EnforcementSettings;
use ComplyOps\Enforcement\PiiSettings;
use ComplyOps\Enforcement\SiteKitEnforcementAdvisor;
use ComplyOps\Integration\GoogleAnalytics\GoogleAnalyticsIntegration;
use ComplyOps\Integration\GoogleTagManager\GoogleTagManagerIntegration;
use ComplyOps\Verification\BrowserVerificationService;

/**
 * Orchestrates site discovery for audits and setup.
 */
final class DiscoveryService {

	public function __construct(
		private readonly WordPressSiteDetector $wordpress = new WordPressSiteDetector(),
		private readonly HtmlScanner $html_scanner = new HtmlScanner(),
		private readonly IntegrationDetector $integrations = new IntegrationDetector(),
		private readonly FormDetector $forms = new FormDetector(),
		private readonly CmpDetector $cmp = new CmpDetector(),
		private readonly RestDetector $rest = new RestDetector(),
		private readonly ThirdPartyDiscoveryService $third_party = new ThirdPartyDiscoveryService(),
	) {
	}

	/**
	 * @param bool $include_runtime_checks When false, skips hosted browser verification and REST endpoint probes.
	 * @return array<string, mixed>
	 */
	public function discover( bool $include_runtime_checks = false ): array {
		$wordpress   = $this->wordpress->detect();
		$active      = $wordpress['active_plugins'] ?? array();
		$active      = is_array( $active ) ? $active : array();
		$site_url    = is_string( $wordpress['site_url'] ?? null ) ? $wordpress['site_url'] : home_url();
		$html_scan   = $this->html_scanner->scan_url( $site_url );
		$scripts     = $html_scan['scripts'];
		$iframes     = $html_scan['iframes'];
		$scripts     = is_array( $scripts ) ? $scripts : array();
		$iframes     = is_array( $iframes ) ? $iframes : array();

		$integration_map = $this->integrations->detect( $active, $scripts, $iframes );
		$known_domains   = $this->known_domains();
		$unknown_hosts   = $this->html_scanner->unknown_external_hosts(
			array_merge( $scripts, $iframes ),
			$known_domains
		);
		$third_party     = $this->third_party->discover(
			$scripts,
			$iframes,
			$known_domains,
			$integration_map
		);
		$html            = is_string( $html_scan['html'] ?? null ) ? $html_scan['html'] : '';

		if ( '' !== $html ) {
			$third_party['external_forms'] = $this->html_scanner->extract_external_form_actions( $html, $known_domains );
		}
		$consent_settings = new ConsentSettings();
		$enforcement      = new EnforcementSettings();
		$ga_integration   = new GoogleAnalyticsIntegration();
		$gtm_integration  = new GoogleTagManagerIntegration();
		$site_kit         = ( new SiteKitDetector() )->detect( $active );
		$site_kit_advisor = ( new SiteKitEnforcementAdvisor() )->recommend( $site_kit );
		$discovery_context = array(
			'html_scan' => array(
				'script_sources' => $scripts,
				'iframe_sources' => $iframes,
			),
			'site_kit' => $site_kit,
		);

		$browser_verification = $include_runtime_checks
			? ( new BrowserVerificationService() )->verify(
				$site_url,
				array(
					'consent_version' => $consent_settings->version(),
				)
			)
			: $this->skipped_browser_verification();

		return array(
			'captured_at'   => gmdate( 'c' ),
			'wordpress'     => $wordpress,
			'html_scan'     => array(
				'url'            => $site_url,
				'error'          => $html_scan['error'],
				'script_count'   => count( $scripts ),
				'iframe_count'   => count( $iframes ),
				'script_sources' => $scripts,
				'iframe_sources' => $iframes,
			),
			'integrations'  => $integration_map,
			'forms'         => $this->forms->detect( $active ),
			'third_party'   => $third_party,
			'consent'       => $this->cmp->detect( $active ),
			'native_consent'=> array(
				'configured' => $consent_settings->is_enabled(),
				'active'     => $consent_settings->should_load_native(),
				'version'    => $consent_settings->version(),
			),
			'enforcement'   => $enforcement->public_config(),
			'site_kit'      => array_merge(
				$site_kit,
				array(
					'recommendation' => $site_kit_advisor,
				)
			),
			'google_analytics' => array(
				'measurement_ids'       => $ga_integration->detect_measurement_ids( $discovery_context ),
				'implementation_count'  => $ga_integration->count_implementations( $discovery_context ),
			),
			'google_tag_manager' => array(
				'container_ids' => $gtm_integration->detect_container_ids( $discovery_context ),
			),
			'pii'           => ( new PiiSettings() )->public_config(),
			'rest'          => $include_runtime_checks ? $this->rest->detect() : $this->skipped_rest_detection(),
			'unknown_hosts' => $unknown_hosts,
			'browser_verification' => $browser_verification,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function skipped_browser_verification(): array {
		return array(
			'available'   => false,
			'captured_at' => gmdate( 'c' ),
			'url'         => null,
			'binary'      => null,
			'scenarios'   => array(),
			'error'       => null,
			'skipped'     => true,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function skipped_rest_detection(): array {
		return array(
			'users_endpoint_public' => null,
			'method'                => 'hosted_browser',
			'skipped'               => true,
		);
	}

	/**
	 * @return list<string>
	 */
	private function known_domains(): array {
		// phpcs:disable PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Domain fragments for third-party detection, not enqueued assets.
		return array(
			'wordpress.org',
			'w.org',
			'gravatar.com',
			'google-analytics.com',
			'googletagmanager.com',
			'google.com',
			'gstatic.com',
			'youtube.com',
			'youtube-nocookie.com',
			'youtu.be',
			'vimeo.com',
			'facebook.com',
			'facebook.net',
			'licdn.com',
			'hotjar.com',
			'clarity.ms',
			'hs-scripts.com',
			'hubspot.com',
			'mailchimp.com',
			'chimpstatic.com',
			'list-manage.com',
			'sibautomation.com',
			'sendinblue.com',
			'stripe.com',
			'jquery.com',
			'cloudflare.com',
			'jsdelivr.net',
			'unpkg.com',
			'cdnjs.cloudflare.com',
		);
		// phpcs:enable PluginCheck.CodeAnalysis.Offloading.OffloadedContent
	}
}
