<?php

declare(strict_types=1);

namespace ComplyOps\Enforcement;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for script enforcement and Consent Mode.
 */
final class EnforcementSettings {

	public const OPTION_KEY = 'complyops_enforcement_settings';

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		return array_merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
	}

	public function is_enabled(): bool {
		return ! empty( $this->all()['enabled'] );
	}

	public function consent_mode_enabled(): bool {
		return ! empty( $this->all()['consent_mode_enabled'] );
	}

	public function block_ga_before_consent(): bool {
		return ! empty( $this->all()['block_ga_before_consent'] );
	}

	public function block_gtm_before_consent(): bool {
		return ! empty( $this->all()['block_gtm_before_consent'] );
	}

	public function deny_ad_signals_by_default(): bool {
		return ! empty( $this->all()['deny_ad_signals_by_default'] );
	}

	public function gate_youtube_before_consent(): bool {
		return ! empty( $this->all()['gate_youtube_before_consent'] );
	}

	public function use_youtube_nocookie(): bool {
		return ! empty( $this->all()['use_youtube_nocookie'] );
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public function save( array $settings ): void {
		update_option( self::OPTION_KEY, $this->sanitize( array_merge( $this->all(), $settings ) ), false );
	}

	public function activate_defaults(): void {
		if ( false !== get_option( self::OPTION_KEY, false ) ) {
			return;
		}

		update_option( self::OPTION_KEY, $this->defaults(), false );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function public_config(): array {
		$settings = $this->all();

		return array(
			'enabled'                  => $this->is_enabled(),
			'consent_mode_enabled'     => $this->consent_mode_enabled(),
			'block_ga_before_consent'  => $this->block_ga_before_consent(),
			'block_gtm_before_consent' => $this->block_gtm_before_consent(),
			'deny_ad_signals_by_default'=> $this->deny_ad_signals_by_default(),
			'gate_youtube_before_consent'=> $this->gate_youtube_before_consent(),
			'use_youtube_nocookie'     => $this->use_youtube_nocookie(),
			'youtube_placeholder_message'=> (string) ( $settings['youtube_placeholder_message'] ?? '' ),
			'wait_for_update_ms'       => (int) ( $settings['wait_for_update_ms'] ?? 500 ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return array(
			// Off until the admin enables consent during setup (or later in settings).
			'enabled'                   => false,
			'consent_mode_enabled'      => true,
			'block_ga_before_consent'   => true,
			'block_gtm_before_consent'  => true,
			'deny_ad_signals_by_default'=> true,
			'gate_youtube_before_consent'=> true,
			'use_youtube_nocookie'      => true,
			'youtube_placeholder_message'=> '',
			'wait_for_update_ms'        => 500,
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function sanitize( array $settings ): array {
		return array(
			'enabled'                   => ! empty( $settings['enabled'] ),
			'consent_mode_enabled'      => ! empty( $settings['consent_mode_enabled'] ),
			'block_ga_before_consent'   => ! empty( $settings['block_ga_before_consent'] ),
			'block_gtm_before_consent'  => ! empty( $settings['block_gtm_before_consent'] ),
			'deny_ad_signals_by_default'=> ! empty( $settings['deny_ad_signals_by_default'] ),
			'gate_youtube_before_consent'=> ! empty( $settings['gate_youtube_before_consent'] ),
			'use_youtube_nocookie'      => ! empty( $settings['use_youtube_nocookie'] ),
			'youtube_placeholder_message'=> sanitize_text_field( (string) ( $settings['youtube_placeholder_message'] ?? '' ) ),
			'wait_for_update_ms'        => max( 0, min( 2000, (int) ( $settings['wait_for_update_ms'] ?? 500 ) ) ),
		);
	}
}
