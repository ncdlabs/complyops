<?php

declare(strict_types=1);

namespace ComplyOps\Enforcement;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Consent\ConsentSettings;
use ComplyOps\Detection\SiteKitDetector;
use ComplyOps\Enforcement\EnforcementSettings;
use ComplyOps\Enforcement\PiiSettings;
use ComplyOps\Integration\IntegrationRegistry;

/**
 * Injects Google Consent Mode v2 defaults before other tags load.
 */
final class ConsentModeInjector {

	public function __construct(
		private readonly EnforcementSettings $settings = new EnforcementSettings(),
		private readonly ConsentSettings $consent = new ConsentSettings(),
		private readonly PiiSettings $pii = new PiiSettings(),
	) {
	}

	public function register(): void {
		add_action( 'wp_head', array( $this, 'render' ), 1 );
	}

	public function render(): void {
		if ( ! $this->should_inject() ) {
			return;
		}

		$config = wp_json_encode(
			array(
				'enforcement' => $this->settings->public_config(),
				'pii'         => $this->pii->public_config(),
			)
		);
		$wait   = (int) ( $this->settings->all()['wait_for_update_ms'] ?? 500 );

		printf(
			'<script id="complyops-consent-mode">%s</script>',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline bootstrap script.
			$this->inline_script( $wait, is_string( $config ) ? $config : '{}' )
		);
	}

	private function should_inject(): bool {
		if ( ! $this->consent->should_load_native()
			|| ! $this->settings->is_enabled()
			|| ! $this->settings->consent_mode_enabled() ) {
			return false;
		}

		$site_kit = ( new SiteKitDetector() )->detect();
		if ( ! empty( $site_kit['consent_mode']['enabled'] )
			&& ( ! empty( $site_kit['analytics']['connected'] ) || ! empty( $site_kit['tag_manager']['connected'] ) ) ) {
			return false;
		}

		return true;
	}

	private function inline_script( int $wait_ms, string $config_json ): string {
		return 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
			. 'window.ComplyOps=window.ComplyOps||{};'
			. 'var complyopsBootstrap=' . $config_json . ';'
			. 'window.ComplyOps.enforcement=complyopsBootstrap.enforcement||{};'
			. 'window.ComplyOps.pii=complyopsBootstrap.pii||{};'
			. 'if(window.ComplyOps.pii.enabled){try{'
			. 'var complyopsUrl=new URL(window.location.href);'
			. 'var complyopsBlocked=(window.ComplyOps.pii.blocked_parameters||[]).map(function(key){return String(key).toLowerCase();});'
			. 'var complyopsChanged=false;Array.from(complyopsUrl.searchParams.keys()).forEach(function(key){'
			. 'if(complyopsBlocked.indexOf(key.toLowerCase())!==-1){complyopsUrl.searchParams.delete(key);complyopsChanged=true;}});'
			. "if(complyopsChanged){gtag('set',{page_location:complyopsUrl.toString()});}"
			. '}catch(complyopsError){}}'
			. "gtag('consent','default',{"
			. "analytics_storage:'denied',"
			. "ad_storage:'denied',"
			. "ad_user_data:'denied',"
			. "ad_personalization:'denied',"
			. "functionality_storage:'denied',"
			. "personalization_storage:'denied',"
			. "security_storage:'granted',"
			. 'wait_for_update:' . $wait_ms
			. '});';
	}
}
