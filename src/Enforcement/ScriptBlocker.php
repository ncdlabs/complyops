<?php

declare(strict_types=1);

namespace ComplyOps\Enforcement;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Consent\ConsentSettings;
use ComplyOps\Integration\IntegrationRegistry;

/**
 * Defers third-party scripts until consent is granted.
 */
final class ScriptBlocker {

	public function __construct(
		private readonly EnforcementSettings $settings = new EnforcementSettings(),
		private readonly ConsentSettings $consent = new ConsentSettings(),
		private readonly IntegrationRegistry $integrations = new IntegrationRegistry(),
	) {
	}

	public function register(): void {
		add_filter( 'script_loader_tag', array( $this, 'filter_script_tag' ), 999, 3 );
	}

	public function filter_script_tag( string $tag, string $handle, string $src ): string {
		if ( ! $this->should_block() || '' === $src ) {
			return $tag;
		}

		$category = $this->category_for_url( $src );

		if ( null === $category ) {
			return $tag;
		}

		if ( ! $this->category_blocked( $category ) ) {
			return $tag;
		}

		$escaped_src = esc_url( $src );
		$escaped_cat = esc_attr( $category );

		$tag = preg_replace(
			'/^<script(\s)/i',
			'<script type="text/plain" data-complyops-blocked="1" data-complyops-category="' . $escaped_cat . '" data-complyops-src="' . $escaped_src . '"$1',
			$tag,
			1
		);

		return is_string( $tag ) ? $tag : '';
	}

	private function should_block(): bool {
		return $this->consent->should_load_native() && $this->settings->is_enabled();
	}

	private function category_for_url( string $src ): ?string {
		$lower = strtolower( $src );

		foreach ( $this->integrations->all() as $integration ) {
			if ( ! $this->integration_blocking_enabled( $integration->id() ) ) {
				continue;
			}

			foreach ( $integration->script_patterns() as $pattern ) {
				if ( str_contains( $lower, strtolower( $pattern ) ) ) {
					return $integration->consent_category();
				}
			}
		}

		return null;
	}

	private function integration_blocking_enabled( string $id ): bool {
		return match ( $id ) {
			'google_analytics'   => $this->settings->block_ga_before_consent(),
			'google_tag_manager' => $this->settings->block_gtm_before_consent(),
			default              => false,
		};
	}

	private function category_blocked( string $category ): bool {
		return in_array( $category, array( 'analytics', 'marketing', 'external_media', 'preferences' ), true );
	}
}
