<?php

declare(strict_types=1);

namespace ComplyOps\Consent;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the public consent banner and frontend assets.
 */
final class ConsentManager {

	public function __construct(
		private readonly ConsentSettings $settings = new ConsentSettings(),
	) {
	}

	public function register(): void {
		add_action( 'init', array( $this->settings, 'activate_defaults' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_mount_points' ), 5 );
		add_action( 'wp_footer', array( $this, 'render_reopen_button' ), 100 );
	}

	public function enqueue_assets(): void {
		if ( ! $this->settings->should_load_native() ) {
			return;
		}

		$asset_file = COMPLYOPS_PLUGIN_DIR . 'build/consent.asset.php';
		$script_url = COMPLYOPS_PLUGIN_URL . 'build/consent.js';
		$style_url  = COMPLYOPS_PLUGIN_URL . 'build/consent.css';

		$asset = array(
			'dependencies' => array( 'wp-i18n' ),
			'version'      => COMPLYOPS_VERSION,
		);

		if ( is_readable( $asset_file ) ) {
			/** @var array{dependencies?: list<string>, version?: string} $generated */
			$generated = require $asset_file;
			$asset     = array_merge( $asset, $generated );
		}

		wp_enqueue_style(
			'complyops-consent',
			$style_url,
			array(),
			$asset['version']
		);

		wp_enqueue_script(
			'complyops-consent',
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'complyops-consent', 'complyops', COMPLYOPS_PLUGIN_DIR . 'languages' );

		wp_localize_script(
			'complyops-consent',
			'complyopsConsent',
			$this->settings->public_config()
		);
	}

	public function render_mount_points(): void {
		if ( ! $this->settings->should_load_native() ) {
			return;
		}

		echo '<div id="complyops-consent-root" class="complyops-consent-root" aria-live="polite"></div>';
	}

	public function render_reopen_button(): void {
		if ( ! $this->settings->should_load_native() ) {
			return;
		}

		$settings = $this->settings->all();

		if ( empty( $settings['show_reopen_button'] ) ) {
			return;
		}

		printf(
			'<button type="button" class="complyops-consent-reopen" id="complyops-consent-reopen" aria-haspopup="dialog" style="display:none;">%s</button>',
			esc_html__( 'Privacy settings', 'complyops' )
		);
	}
}
