<?php

declare(strict_types=1);

namespace ComplyOps\Enforcement;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds ComplyOps enforcement recommendations from Site Kit state.
 */
final class SiteKitEnforcementAdvisor {

	public function __construct(
		private readonly EnforcementSettings $settings = new EnforcementSettings(),
	) {
	}

	/**
	 * @param array<string, mixed> $site_kit
	 * @return array<string, mixed>
	 */
	public function recommend( array $site_kit ): array {
		$current     = $this->settings->public_config();
		$recommended = $current;
		$reasons     = array();

		if ( empty( $site_kit['plugin_active'] ) ) {
			return array(
				'applicable'           => false,
				'has_changes'          => false,
				'enforcement'          => $recommended,
				'reasons'              => array(),
				'defer_consent_mode'     => false,
			);
		}

		$analytics   = is_array( $site_kit['analytics'] ?? null ) ? $site_kit['analytics'] : array();
		$tag_manager = is_array( $site_kit['tag_manager'] ?? null ) ? $site_kit['tag_manager'] : array();
		$consent     = is_array( $site_kit['consent_mode'] ?? null ) ? $site_kit['consent_mode'] : array();

		if ( ! empty( $analytics['connected'] ) && ! empty( $analytics['use_snippet'] ) ) {
			$recommended['enabled']                 = true;
			$recommended['block_ga_before_consent'] = true;
			$reasons[]                              = __(
				'Site Kit places the GA4 snippet on this site. Blocking Google Analytics until consent is recommended.',
				'complyops'
			);
		}

		if ( ! empty( $tag_manager['connected'] ) && ! empty( $tag_manager['use_snippet'] ) ) {
			$recommended['enabled']                  = true;
			$recommended['block_gtm_before_consent'] = true;
			$reasons[]                                 = __(
				'Site Kit places the Google Tag Manager snippet on this site. Blocking GTM until consent is recommended.',
				'complyops'
			);
		}

		$defer_consent_mode = ! empty( $consent['enabled'] )
			&& ( ! empty( $analytics['connected'] ) || ! empty( $tag_manager['connected'] ) );

		if ( $defer_consent_mode ) {
			$recommended['consent_mode_enabled'] = false;
			$reasons[]                           = __(
				'Site Kit Consent Mode is enabled. ComplyOps should defer consent defaults to Site Kit to avoid duplicate gtag consent snippets.',
				'complyops'
			);
		} elseif ( ! empty( $analytics['connected'] ) || ! empty( $tag_manager['connected'] ) ) {
			$recommended['consent_mode_enabled'] = true;
			$reasons[]                           = __(
				'Site Kit Analytics or Tag Manager is connected without Site Kit Consent Mode. ComplyOps Consent Mode defaults are recommended.',
				'complyops'
			);
		}

		if ( ! empty( $analytics['connected'] ) || ! empty( $tag_manager['connected'] ) ) {
			$recommended['deny_ad_signals_by_default'] = true;
		}

		return array(
			'applicable'       => array() !== $reasons,
			'has_changes'      => $this->has_enforcement_changes( $current, $recommended ),
			'enforcement'      => $recommended,
			'reasons'          => $reasons,
			'defer_consent_mode' => $defer_consent_mode,
		);
	}

	/**
	 * @param array<string, mixed> $site_kit
	 */
	public function should_defer_consent_mode( array $site_kit ): bool {
		$recommendation = $this->recommend( $site_kit );

		return ! empty( $recommendation['defer_consent_mode'] );
	}

	/**
	 * @param array<string, mixed> $current
	 * @param array<string, mixed> $recommended
	 */
	private function has_enforcement_changes( array $current, array $recommended ): bool {
		$keys = array(
			'enabled',
			'consent_mode_enabled',
			'block_ga_before_consent',
			'block_gtm_before_consent',
			'deny_ad_signals_by_default',
		);

		foreach ( $keys as $key ) {
			if ( ! empty( $current[ $key ] ) !== ! empty( $recommended[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}
}
