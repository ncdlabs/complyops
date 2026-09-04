<?php

declare(strict_types=1);

namespace ComplyOps\Enforcement;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Consent\ConsentSettings;

/**
 * Boots GA/GTM enforcement on the public site.
 */
final class EnforcementManager {

	public function __construct(
		private readonly EnforcementSettings $settings = new EnforcementSettings(),
		private readonly ConsentSettings $consent = new ConsentSettings(),
	) {
	}

	public function register(): void {
		add_action( 'init', array( $this->settings, 'activate_defaults' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 5 );
		( new ConsentModeInjector( $this->settings, $this->consent ) )->register();
		( new ScriptBlocker( $this->settings, $this->consent ) )->register();
		( new YouTubeEmbedGate( $this->settings, $this->consent ) )->register();
	}

	public function enqueue_assets(): void {
		if ( ! $this->consent->should_load_native() || ! $this->settings->is_enabled() ) {
			return;
		}

		$this->enqueue_script_bundle( 'complyops-enforcement', 'enforcement.js', false );

		if ( $this->settings->gate_youtube_before_consent() ) {
			$this->enqueue_script_bundle( 'complyops-youtube-gate', 'youtube-gate.js', true, array( 'complyops-consent' ) );
			wp_enqueue_style(
				'complyops-youtube-gate',
				COMPLYOPS_PLUGIN_URL . 'build/youtube-gate.css',
				array(),
				COMPLYOPS_VERSION
			);

			wp_localize_script(
				'complyops-youtube-gate',
				'complyopsYoutubeGate',
				array(
					'category' => 'external_media',
					'config'   => $this->settings->public_config(),
				)
			);
		}
	}

	private function enqueue_script_bundle( string $handle, string $filename, bool $in_footer, array $deps = array() ): void {
		$asset_file = COMPLYOPS_PLUGIN_DIR . 'build/' . str_replace( '.js', '', $filename ) . '.asset.php';
		$script_url = COMPLYOPS_PLUGIN_URL . 'build/' . $filename;

		$asset = array(
			'dependencies' => $deps,
			'version'      => COMPLYOPS_VERSION,
		);

		if ( is_readable( $asset_file ) ) {
			/** @var array{dependencies?: list<string>, version?: string} $generated */
			$generated = require $asset_file;
			$asset     = array_merge( $asset, $generated );
			$asset['dependencies'] = array_values( array_unique( array_merge( $deps, $asset['dependencies'] ?? array() ) ) );
		}

		wp_enqueue_script(
			$handle,
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			$in_footer
		);
	}
}
