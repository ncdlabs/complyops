<?php

declare(strict_types=1);

namespace ComplyOps\Detection;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects installed plugins, themes, and WordPress configuration.
 */
final class WordPressSiteDetector {

	/**
	 * @return array<string, mixed>
	 */
	public function detect(): array {
		global $wp_version;

		$privacy_page_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );

		return array(
			'wordpress_version'        => is_string( $wp_version ?? null ) ? $wp_version : null,
			'site_url'                 => function_exists( 'home_url' ) ? home_url() : null,
			'home_https'               => str_starts_with( (string) get_option( 'home', '' ), 'https://' ),
			'active_theme'             => $this->active_theme(),
			'installed_plugins'        => $this->installed_plugins(),
			'active_plugins'           => $this->active_plugins(),
			'privacy_page_id'          => $privacy_page_id > 0 ? $privacy_page_id : null,
			'privacy_page_published'   => $privacy_page_id > 0 && 'publish' === get_post_status( $privacy_page_id ),
			'users_can_register'       => (bool) get_option( 'users_can_register', false ),
			'default_comment_status'   => (string) get_option( 'default_comment_status', 'closed' ),
			'show_avatars'             => (bool) get_option( 'show_avatars', true ),
		);
	}

	/**
	 * @return array<string, string|null>
	 */
	private function active_theme(): array {
		$theme = wp_get_theme();

		return array(
			'name'    => $theme->get( 'Name' ) ?: null,
			'version' => $theme->get( 'Version' ) ?: null,
			'slug'    => $theme->get_stylesheet() ?: null,
		);
	}

	/**
	 * @return list<array{slug: string, name: string, version: string|null, active: bool}>
	 */
	private function installed_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active  = $this->active_plugin_files();
		$plugins = get_plugins();
		$result  = array();

		foreach ( $plugins as $file => $data ) {
			$slug = dirname( $file );

			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}

			$result[] = array(
				'slug'    => $slug,
				'name'    => (string) ( $data['Name'] ?? $slug ),
				'version' => isset( $data['Version'] ) ? (string) $data['Version'] : null,
				'active'  => in_array( $file, $active, true ),
			);
		}

		usort(
			$result,
			static fn ( array $a, array $b ): int => strcmp( $a['slug'], $b['slug'] )
		);

		return $result;
	}

	/**
	 * @return list<string>
	 */
	private function active_plugins(): array {
		return array_values(
			array_map(
				static function ( string $file ): string {
					$slug = dirname( $file );
					return '.' === $slug ? basename( $file, '.php' ) : $slug;
				},
				$this->active_plugin_files()
			)
		);
	}

	/**
	 * @return list<string>
	 */
	private function active_plugin_files(): array {
		$active = get_option( 'active_plugins', array() );

		return is_array( $active ) ? array_values( array_filter( $active, 'is_string' ) ) : array();
	}
}
