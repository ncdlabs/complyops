<?php

declare(strict_types=1);

namespace ComplyOps\Verification;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exceptions; messages escaped at REST/WP_Error boundaries.

use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use ComplyOps\Evidence\EvidenceSanitizer;
use RuntimeException;
use Throwable;

/**
 * Runs headless Chromium scenarios during discovery/audit.
 */
final class BrowserVerificationService {

	/** @var list<string> */
	private const TRACKING_HOST_FRAGMENTS = array(
		'google-analytics.com',
		'analytics.google.com',
		'googletagmanager.com',
		'doubleclick.net',
		'googleadservices.com',
		'facebook.net',
		'connect.facebook.net',
	);

	public function __construct(
		private readonly ChromiumLauncher $launcher = new ChromiumLauncher(),
		private readonly BrowserVerificationSettings $settings = new BrowserVerificationSettings(),
		private readonly RemoteBrowserVerificationClient $remote = new RemoteBrowserVerificationClient(),
		private readonly EvidenceSanitizer $sanitizer = new EvidenceSanitizer(),
	) {
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	public function verify( string $url, array $options = array() ): array {
		$target = BrowserScanTarget::resolve( $url );

		if ( null === $target ) {
			return $this->unavailable(
				__( 'Browser verification URL is invalid or not allowed.', 'complyops' )
			);
		}

		if ( $this->settings->is_configured() ) {
			$remote = $this->remote->verify( $target, $options );

			if ( ! empty( $remote['available'] ) ) {
				$remote['mode'] = 'remote';

				return $remote;
			}

			if ( $this->local_can_run() ) {
				$local = $this->verify_local( $target, $options );

				if ( ! empty( $local['available'] ) ) {
					$local['mode']        = 'local';
					$local['remote_error'] = $remote['error'] ?? null;

					return $local;
				}

				$remote['mode'] = 'remote';

				return $remote;
			}

			$remote['mode'] = 'remote';

			return $remote;
		}

		if ( ! $this->local_can_run() ) {
			return $this->unavailable(
				__( 'Browser verification is unavailable. Enable the hosted browser service or install Chromium on this host.', 'complyops' )
			);
		}

		$local = $this->verify_local( $target, $options );
		$local['mode'] = ! empty( $local['available'] ) ? 'local' : null;

		return $local;
	}

	private function local_can_run(): bool {
		if ( ! class_exists( BrowserFactory::class ) ) {
			return false;
		}

		if ( ! function_exists( 'proc_open' ) ) {
			return false;
		}

		return null !== $this->launcher->binary_path();
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private function verify_local( string $target, array $options ): array {
		$binary = $this->launcher->binary_path();

		if ( null === $binary ) {
			return $this->unavailable(
				__( 'No Chromium binary was found for browser verification.', 'complyops' )
			);
		}

		try {
			$scenarios = $this->run_scenarios( $binary, $target, $options );

			return array(
				'available'   => true,
				'captured_at' => gmdate( 'c' ),
				'url'         => $target,
				'binary'      => $binary,
				'scenarios'   => $scenarios,
				'error'       => null,
			);
		} catch ( Throwable $exception ) {
			return array(
				'available'   => false,
				'captured_at' => gmdate( 'c' ),
				'url'         => $target,
				'binary'      => $binary,
				'scenarios'   => array(),
				'error'       => $exception->getMessage(),
			);
		}
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>
	 */
	private function run_scenarios( string $binary, string $url, array $options ): array {
		$factory = new BrowserFactory( $binary );
		$browser = $factory->createBrowser( $this->browser_options() );

		try {
			$page = $browser->createPage();

			$fresh = $this->capture_scenario(
				$page,
				$url,
				'fresh_visitor',
				static function ( Page $active_page ) use ( $url ): void {
					self::navigate_and_settle( $active_page, $url );
				}
			);

			$consent_denied = $this->capture_scenario(
				$page,
				$url,
				'consent_denied',
				static function ( Page $active_page ) use ( $url ): void {
					self::navigate_and_settle( $active_page, $url );
					self::click_complyops_reject( $active_page );
					usleep( 1_500_000 );
				}
			);

			$analytics_granted = $this->capture_scenario(
				$page,
				$url,
				'analytics_granted',
				function ( Page $active_page ) use ( $url, $options ): void {
					self::navigate_and_settle( $active_page, $url );
					self::grant_complyops_analytics( $active_page, $options );
					self::navigate_and_settle( $active_page, $url );
				}
			);

			return array(
				'fresh_visitor'     => $fresh,
				'consent_denied'    => $consent_denied,
				'analytics_granted' => $analytics_granted,
				'consent_withdrawn' => $this->capture_scenario(
					$page,
					$url,
					'consent_withdrawn',
					function ( Page $active_page ) use ( $url, $options ): void {
						self::navigate_and_settle( $active_page, $url );
						self::grant_complyops_analytics( $active_page, $options );
						self::navigate_and_settle( $active_page, $url );
						self::withdraw_complyops_consent( $active_page );
						usleep( 1_500_000 );
					}
				),
				'gpc_signal'          => $this->local_gpc_signal( $page, $url ),
				'accessibility_scan'  => $this->hosted_only_scenario(
					__( 'Automated WCAG axe scanning requires the hosted browser service.', 'complyops' )
				),
				'checkout_scan'       => $this->hosted_only_scenario(
					__( 'Checkout journey scanning requires the hosted browser service.', 'complyops' )
				),
			);
		} finally {
			$browser->close();
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function browser_options(): array {
		$options = array(
			'headless'    => true,
			'userAgent'   => 'ComplyOps BrowserVerify/1.0',
			'customFlags' => array(
				'--disable-crash-reporter',
				'--disable-breakpad',
				'--no-crash-upload',
				'--disable-dev-shm-usage',
				'--disable-gpu',
			),
		);

		if ( defined( 'COMPLYOPS_BROWSER_ALLOW_NO_SANDBOX' ) && true === COMPLYOPS_BROWSER_ALLOW_NO_SANDBOX ) {
			$options['noSandbox'] = true;
		}

		return $options;
	}

	/**
	 * @param callable(Page): void $prepare
	 * @return array<string, mixed>
	 */
	private function capture_scenario( Page $page, string $url, string $name, callable $prepare ): array {
		unset( $url, $name );

		try {
			$page->getSession()->sendMessageSync(
				new \HeadlessChromium\Communication\Message( 'Network.enable' )
			);
		} catch ( Throwable ) {
			// Network domain is best-effort.
		}

		try {
			$prepare( $page );
		} catch ( Throwable $exception ) {
			return array(
				'error' => $exception->getMessage(),
			);
		}

		try {
			$snapshot = $this->read_snapshot( $page );
			$cookies  = $this->read_cookie_names( $page );

			$snapshot['cookie_names']      = $cookies;
			$snapshot['tracking_detected'] = $this->tracking_detected( $snapshot );
			$snapshot['ga_cookie_detected']  = $this->ga_cookie_detected( $cookies );
			$snapshot['pii_in_urls']       = $this->detect_pii_in_urls( $snapshot['resource_urls'] ?? array() );

			return $this->sanitizer->sanitize_snapshot( $snapshot );
		} catch ( Throwable $exception ) {
			return array(
				'error' => $exception->getMessage(),
			);
		}
	}

	private static function navigate_and_settle( Page $page, string $url ): void {
		$navigation = $page->navigate( $url );

		try {
			$navigation->waitForNavigation( Page::NETWORK_IDLE, 20_000 );
		} catch ( Throwable ) {
			try {
				$navigation->waitForNavigation( Page::LOAD, 20_000 );
			} catch ( Throwable ) {
				// Continue with best-effort snapshot.
			}
		}

		$evaluation = $page->evaluate( 'window.location.href' );
		$final_url  = is_string( $evaluation->getReturnValue() ) ? (string) $evaluation->getReturnValue() : '';

		if ( '' === $final_url || null === BrowserScanTarget::resolve( $final_url ) ) {
			throw new RuntimeException(
				__( 'Navigation left allowed host.', 'complyops' )
			);
		}

		usleep( 2_000_000 );
	}

	private static function click_complyops_reject( Page $page ): void {
		$page->evaluate(
			'(function () {'
			. 'const banner = document.querySelector(".complyops-consent-banner");'
			. 'if (!banner) { return false; }'
			. 'const reject = banner.querySelector(".complyops-consent-btn:not(.complyops-consent-btn--primary):not(.complyops-consent-btn--link)");'
			. 'if (!reject) { return false; }'
			. 'reject.click();'
			. 'return true;'
			. '})()'
		)->waitForResponse( 5_000 );
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private static function grant_complyops_analytics( Page $page, array $options ): void {
		$version = isset( $options['consent_version'] ) ? (string) $options['consent_version'] : '1';

		$page->evaluate(
			sprintf(
				'(function () {'
				. 'const version = %1$s;'
				. 'const payload = {'
				. 'version,'
				. 'categories: {'
				. 'necessary: true,'
				. 'preferences: false,'
				. 'analytics: true,'
				. 'marketing: false,'
				. 'external_media: false,'
				. '},'
				. 'timestamp: new Date().toISOString(),'
				. '};'
				. 'try { localStorage.setItem("complyops_consent", JSON.stringify(payload)); }'
				. 'catch (error) { return false; }'
				. 'document.cookie = "complyops_consent=1; path=/; max-age=31536000; SameSite=Lax";'
				. 'window.dispatchEvent(new CustomEvent("complyops-consent-updated", { detail: payload }));'
				. 'return true;'
				. '})()',
				wp_json_encode( $version ) ?: '"1"'
			)
		)->waitForResponse( 5_000 );
	}

	private static function withdraw_complyops_consent( Page $page ): void {
		$page->evaluate(
			'(function () {'
			. 'const payload = {'
			. 'version: "1",'
			. 'categories: {'
			. 'necessary: true,'
			. 'preferences: false,'
			. 'analytics: false,'
			. 'marketing: false,'
			. 'external_media: false,'
			. '},'
			. 'timestamp: new Date().toISOString(),'
			. '};'
			. 'try { localStorage.setItem("complyops_consent", JSON.stringify(payload)); }'
			. 'catch (error) { return false; }'
			. 'document.cookie = "complyops_consent=1; path=/; max-age=31536000; SameSite=Lax";'
			. 'window.dispatchEvent(new CustomEvent("complyops-consent-updated", { detail: payload }));'
			. 'return true;'
			. '})()'
		)->waitForResponse( 5_000 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_snapshot( Page $page ): array {
		$evaluation = $page->evaluate(
			'(function () {'
			. 'const resources = performance.getEntriesByType("resource").map((entry) => entry.name);'
			. 'const scripts = [...document.scripts].map((script) => script.src || "").filter(Boolean);'
			. 'let complyops_consent_present = false;'
			. 'try { complyops_consent_present = !!localStorage.getItem("complyops_consent"); }'
			. 'catch (error) { complyops_consent_present = false; }'
			. 'return {'
			. 'resource_urls: resources,'
			. 'script_sources: scripts,'
			. 'youtube_iframes: document.querySelectorAll("iframe[src*=\\"youtube\\"]").length,'
			. 'youtube_gates: document.querySelectorAll("[data-complyops-youtube-blocked=\\"1\\"]").length,'
			. 'consent_banner_visible: !!document.querySelector(".complyops-consent-banner"),'
			. 'complyops_consent_present,'
			. '};'
			. '})()'
		);

		$value = $evaluation->getReturnValue();

		return is_array( $value ) ? $value : array();
	}

	/**
	 * @return list<string>
	 */
	private function read_cookie_names( Page $page ): array {
		try {
			$cookies = $page->getCookies( 5_000 );
			$names   = array();

			foreach ( $cookies as $cookie ) {
				$name = method_exists( $cookie, 'getName' ) ? (string) $cookie->getName() : '';

				if ( '' !== $name ) {
					$names[] = $name;
				}
			}

			return array_values( array_unique( $names ) );
		} catch ( Throwable ) {
			return array();
		}
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	private function tracking_detected( array $snapshot ): bool {
		$urls = array();

		foreach ( array( 'resource_urls', 'script_sources' ) as $key ) {
			if ( ! isset( $snapshot[ $key ] ) || ! is_array( $snapshot[ $key ] ) ) {
				continue;
			}

			$urls = array_merge( $urls, $snapshot[ $key ] );
		}

		foreach ( $urls as $url ) {
			if ( ! is_string( $url ) ) {
				continue;
			}

			$lower = strtolower( $url );

			foreach ( self::TRACKING_HOST_FRAGMENTS as $fragment ) {
				if ( str_contains( $lower, $fragment ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param list<string> $cookie_names
	 */
	private function ga_cookie_detected( array $cookie_names ): bool {
		foreach ( $cookie_names as $name ) {
			if ( str_starts_with( strtolower( $name ), '_ga' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param list<mixed> $urls
	 * @return list<string>
	 */
	private function detect_pii_in_urls( array $urls ): array {
		$matches = array();

		foreach ( $urls as $url ) {
			if ( ! is_string( $url ) ) {
				continue;
			}

			if ( preg_match( '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $url ) ) {
				$matches[] = $this->sanitizer->redact_url( $url );
			}
		}

		return array_values( array_unique( $matches ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function unavailable( string $reason ): array {
		return array(
			'available'   => false,
			'captured_at' => gmdate( 'c' ),
			'url'         => null,
			'binary'      => null,
			'scenarios'   => array(),
			'error'       => $reason,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function hosted_only_scenario( string $message ): array {
		return array(
			'error' => $message,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function local_gpc_signal( Page $page, string $url ): array {
		$snapshot = $this->capture_scenario(
			$page,
			$url,
			'gpc_signal',
			static function ( Page $active_page ) use ( $url ): void {
				self::navigate_and_settle( $active_page, $url );
			}
		);

		if ( isset( $snapshot['error'] ) ) {
			return $snapshot;
		}

		$snapshot['tracking_request_count'] = 0;
		$snapshot['tracking_request_hosts'] = array();
		$snapshot['gpc_honored']           = empty( $snapshot['tracking_detected'] );
		$snapshot['opt_out_honored']        = $snapshot['gpc_honored'];

		return $snapshot;
	}
}
