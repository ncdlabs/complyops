<?php

declare(strict_types=1);

namespace ComplyOps\Consent;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers ComplyOps with the WP Consent API for Site Kit and other CMP bridges.
 */
final class WpConsentApiBridge {

	public function register(): void {
		if ( ! defined( 'COMPLYOPS_PLUGIN_FILE' ) ) {
			return;
		}

		add_filter(
			'wp_consent_api_registered_' . plugin_basename( COMPLYOPS_PLUGIN_FILE ),
			'__return_true'
		);
	}
}
