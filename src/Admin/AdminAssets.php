<?php

declare(strict_types=1);

namespace ComplyOps\Admin;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Security\Capabilities;

/**
 * Enqueues the ComplyOps admin React application.
 */
final class AdminAssets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( string $hook ): void {
		if ( ! in_array( $hook, $this->admin_hooks(), true ) ) {
			return;
		}

		$asset_file = COMPLYOPS_PLUGIN_DIR . 'build/admin.asset.php';
		$script_url = COMPLYOPS_PLUGIN_URL . 'build/admin.js';
		$style_url  = COMPLYOPS_PLUGIN_URL . 'build/style-admin.css';

		$asset = array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			'version'      => COMPLYOPS_VERSION,
		);

		if ( is_readable( $asset_file ) ) {
			/** @var array{dependencies?: list<string>, version?: string} $generated */
			$generated = require $asset_file;
			$asset     = array_merge( $asset, $generated );
		}

		wp_enqueue_style(
			'complyops-admin',
			$style_url,
			array( 'wp-components', 'wp-block-editor' ),
			$asset['version']
		);

		wp_enqueue_style( 'wp-block-editor' );

		wp_enqueue_script(
			'complyops-admin',
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'complyops-admin', 'complyops', COMPLYOPS_PLUGIN_DIR . 'languages' );

		wp_localize_script(
			'complyops-admin',
			'complyopsAdmin',
			array(
				'page'        => AdminMenu::current_page(),
				'restUrl'     => rest_url( COMPLYOPS_REST_NAMESPACE . '/' ),
				'restNonce'   => wp_create_nonce( 'wp_rest' ),
				'canRunAudit' => current_user_can( Capabilities::RUN_AUDITS ) || current_user_can( Capabilities::MANAGE ),
				'canRemediate'=> current_user_can( Capabilities::REMEDIATE ) || current_user_can( Capabilities::MANAGE ),
				'canManage'   => current_user_can( Capabilities::MANAGE ),
				'brandMarkUrl' => COMPLYOPS_PLUGIN_URL . 'assets/brand/complyops-mark.svg',
				'storeUrl'    => 'https://ncdlabs.com/products/complyops/store/',
				'activationUrl' => apply_filters(
					'complyops_pack_activation_url',
					'https://ncdlabs.com/products/complyops/api/activate'
				),
			)
		);
	}

	/**
	 * @return list<string>
	 */
	private function admin_hooks(): array {
		$hooks = array( 'toplevel_page_complyops' );

		foreach ( AdminMenu::page_slugs() as $slug ) {
			if ( 'complyops' === $slug ) {
				continue;
			}

			$hooks[] = 'complyops_page_' . $slug;
		}

		return $hooks;
	}
}
