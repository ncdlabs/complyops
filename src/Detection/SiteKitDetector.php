<?php

declare(strict_types=1);

namespace ComplyOps\Detection;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads Google Site Kit configuration from WordPress options.
 */
final class SiteKitDetector {

	public const PLUGIN_SLUG = 'google-site-kit/google-site-kit.php';

	private const OPTION_ANALYTICS   = 'googlesitekit_analytics-4_settings';
	private const OPTION_TAG_MANAGER = 'googlesitekit_tagmanager_settings';
	private const OPTION_ACTIVE      = 'googlesitekit_active_modules';
	private const OPTION_ACTIVE_LEGACY = 'googlesitekit-active-modules';

	/**
	 * @param list<string> $active_plugin_slugs
	 * @return array<string, mixed>
	 */
	public function detect( array $active_plugin_slugs = array() ): array {
		$plugin_active = $this->is_plugin_active( $active_plugin_slugs );

		if ( ! $plugin_active ) {
			return $this->empty_result();
		}

		$analytics   = $this->read_option_array( self::OPTION_ANALYTICS );
		$tag_manager = $this->read_option_array( self::OPTION_TAG_MANAGER );
		$consent     = $this->read_consent_mode_settings();

		$measurement_id = $this->normalize_measurement_id( (string) ( $analytics['measurementID'] ?? '' ) );
		$container_id   = $this->normalize_container_id( (string) ( $tag_manager['containerID'] ?? '' ) );
		$analytics_active = $this->is_module_active( 'analytics-4' ) || $this->is_module_active( 'analytics' );
		$tag_manager_active = $this->is_module_active( 'tagmanager' ) || $this->is_module_active( 'tag-manager' );

		return array(
			'plugin_active' => true,
			'plugin_slug'   => self::PLUGIN_SLUG,
			'analytics'     => array(
				'module_active'  => $analytics_active,
				'connected'      => $analytics_active && '' !== $measurement_id,
				'measurement_id' => $measurement_id,
				'property_id'    => (string) ( $analytics['propertyID'] ?? '' ),
				'google_tag_id'  => (string) ( $analytics['googleTagID'] ?? '' ),
				'use_snippet'    => ! array_key_exists( 'useSnippet', $analytics ) || ! empty( $analytics['useSnippet'] ),
				'tracking_disabled' => is_array( $analytics['trackingDisabled'] ?? null )
					? array_values( $analytics['trackingDisabled'] )
					: array(),
			),
			'tag_manager'   => array(
				'module_active' => $tag_manager_active,
				'connected'     => $tag_manager_active && '' !== $container_id,
				'container_id'  => $container_id,
				'use_snippet'   => ! array_key_exists( 'useSnippet', $tag_manager ) || ! empty( $tag_manager['useSnippet'] ),
			),
			'consent_mode'  => array(
				'enabled' => ! empty( $consent['enabled'] ),
				'regions' => is_array( $consent['regions'] ?? null ) ? array_values( $consent['regions'] ) : array(),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function empty_result(): array {
		return array(
			'plugin_active' => false,
			'plugin_slug'   => self::PLUGIN_SLUG,
			'analytics'       => array(
				'module_active'     => false,
				'connected'         => false,
				'measurement_id'    => '',
				'property_id'       => '',
				'google_tag_id'     => '',
				'use_snippet'       => false,
				'tracking_disabled' => array(),
			),
			'tag_manager'     => array(
				'module_active' => false,
				'connected'     => false,
				'container_id'  => '',
				'use_snippet'   => false,
			),
			'consent_mode'    => array(
				'enabled' => false,
				'regions' => array(),
			),
		);
	}

	/**
	 * @param list<string> $active_plugin_slugs
	 */
	private function is_plugin_active( array $active_plugin_slugs ): bool {
		if ( in_array( self::PLUGIN_SLUG, $active_plugin_slugs, true ) ) {
			return true;
		}

		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( self::PLUGIN_SLUG );
		}

		return false;
	}

	private function is_module_active( string $slug ): bool {
		$modules = $this->get_active_modules();

		return in_array( $slug, $modules, true );
	}

	/**
	 * @return list<string>
	 */
	private function get_active_modules(): array {
		$modules = $this->read_option_array( self::OPTION_ACTIVE );

		if ( array() === $modules ) {
			$modules = $this->read_option_array( self::OPTION_ACTIVE_LEGACY );
		}

		return array_values(
			array_filter(
				$modules,
				static fn ( mixed $slug ): bool => is_string( $slug ) && '' !== $slug
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_consent_mode_settings(): array {
		foreach ( array( 'googlesitekit_consent-mode', 'googlesitekit_consent_mode_settings' ) as $option ) {
			$value = $this->read_option_array( $option );

			if ( array() !== $value ) {
				return $value;
			}
		}

		return array();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_option_array( string $option ): array {
		$value = get_option( $option, array() );

		return is_array( $value ) ? $value : array();
	}

	private function normalize_measurement_id( string $value ): string {
		$value = strtoupper( trim( $value ) );

		return 1 === preg_match( '/^G-[A-Z0-9]+$/', $value ) ? $value : '';
	}

	private function normalize_container_id( string $value ): string {
		$value = strtoupper( trim( $value ) );

		return 1 === preg_match( '/^GTM-[A-Z0-9]+$/', $value ) ? $value : '';
	}
}
