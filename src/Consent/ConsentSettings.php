<?php

declare(strict_types=1);

namespace ComplyOps\Consent;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Detection\CmpDetector;

/**
 * Reads and writes ComplyOps consent manager settings.
 */
final class ConsentSettings {

	public const OPTION_KEY = 'complyops_consent_settings';

	public const DEFAULT_VERSION = '1.0.0';

	private const MAX_BANNER_REVISIONS = 20;

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		return array_merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
	}

	public function is_enabled(): bool {
		$settings = $this->all();

		return ! empty( $settings['enabled'] );
	}

	public function should_load_native(): bool {
		if ( ! $this->is_enabled() || is_admin() ) {
			return false;
		}

		if ( $this->third_party_cmp_active() ) {
			return false;
		}

		return true;
	}

	public function version(): string {
		return (string) ( $this->all()['version'] ?? self::DEFAULT_VERSION );
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public function save( array $settings ): void {
		if ( array_key_exists( 'show_site_icon', $settings ) ) {
			$settings['show_site_logo'] = ! empty( $settings['show_site_icon'] );
			unset( $settings['show_site_icon'] );
		}

		$current   = $this->all();
		$revert_id = isset( $settings['revert_revision_id'] ) ? sanitize_text_field( (string) $settings['revert_revision_id'] ) : '';
		unset( $settings['revert_revision_id'] );

		if ( '' !== $revert_id ) {
			$restored = $this->revision_banner_fields( $current, $revert_id );
			if ( array() !== $restored ) {
				$settings = array_merge( $settings, $restored );
			}
		}

		$merged = array_merge( $current, $settings );

		if ( $this->banner_changed( $current, $merged ) ) {
			$merged['banner_revisions'] = $this->append_revision(
				is_array( $current['banner_revisions'] ?? null ) ? $current['banner_revisions'] : array(),
				$this->banner_snapshot( $current ),
				(string) $current['version']
			);

			if ( empty( $merged['freeze_version'] ) ) {
				$merged['version'] = $this->increment_version( (string) $current['version'] );
			}
		} elseif ( empty( $merged['freeze_version'] ) ) {
			$merged['version'] = (string) $current['version'];
		}

		$clean = $this->sanitize( $merged );
		update_option( self::OPTION_KEY, $clean, false );
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
			'enabled'    => $this->should_load_native(),
			'version'    => $this->version(),
			'categories' => $this->category_definitions(),
			'banner'     => array(
				'headline'       => (string) $settings['banner_headline'],
				'description'    => (string) $settings['banner_description'],
				'position'       => (string) $settings['banner_position'],
				'show_site_logo' => $this->show_site_logo_enabled( $settings ),
				'site_logo_url'  => $this->site_logo_url(),
				'site_name'      => (string) get_bloginfo( 'name' ),
			),
			'defaults'   => $this->default_category_states(),
		);
	}

	/**
	 * @return array<string, bool>
	 */
	public function default_category_states(): array {
		$states = array();

		foreach ( ConsentCategory::cases() as $category ) {
			$states[ $category->value ] = $category->default_granted();
		}

		return $states;
	}

	/**
	 * @return list<array{id: string, label: string, required: bool, default: bool}>
	 */
	public function category_definitions(): array {
		return array(
			array(
				'id'       => ConsentCategory::Necessary->value,
				'label'    => __( 'Necessary', 'complyops' ),
				'required' => true,
				'default'  => true,
			),
			array(
				'id'       => ConsentCategory::Preferences->value,
				'label'    => __( 'Preferences', 'complyops' ),
				'required' => false,
				'default'  => false,
			),
			array(
				'id'       => ConsentCategory::Analytics->value,
				'label'    => __( 'Analytics', 'complyops' ),
				'required' => false,
				'default'  => false,
			),
			array(
				'id'       => ConsentCategory::Marketing->value,
				'label'    => __( 'Marketing', 'complyops' ),
				'required' => false,
				'default'  => false,
			),
			array(
				'id'       => ConsentCategory::ExternalMedia->value,
				'label'    => __( 'External Media', 'complyops' ),
				'required' => false,
				'default'  => false,
			),
		);
	}

	private function third_party_cmp_active(): bool {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		$active = get_option( 'active_plugins', array() );
		$slugs  = is_array( $active )
			? array_map(
				static function ( string $file ): string {
					$slug = dirname( $file );
					return '.' === $slug ? basename( $file, '.php' ) : $slug;
				},
				array_values( array_filter( $active, 'is_string' ) )
			)
			: array();

		$cmp = ( new CmpDetector() )->detect( $slugs );

		if ( empty( $cmp['detected_any'] ) ) {
			return false;
		}

		$detected = $cmp['detected_ids'] ?? array();

		if ( ! is_array( $detected ) ) {
			return true;
		}

		// Allow ComplyOps native CMP alongside detection of self.
		$external = array_values(
			array_filter(
				$detected,
				static fn ( string $id ): bool => 'complyops' !== $id
			)
		);

		return array() !== $external;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return array(
			// Off until the admin enables during setup (or later in Consent settings).
			'enabled'            => false,
			'version'            => self::DEFAULT_VERSION,
			'banner_headline'      => __( 'Privacy & Cookies', 'complyops' ),
			'banner_description'   => '<p>' . esc_html(
				__(
					'We use cookies and similar technologies for essential site functionality, and optionally for analytics, marketing, and external media. You can accept all, reject nonessential cookies, or manage your preferences.',
					'complyops'
				)
			) . '</p>',
			'banner_position'      => 'bottom',
			'show_site_logo'       => '' !== $this->site_logo_url(),
			'show_reopen_button'   => true,
			'freeze_version'       => false,
			'banner_revisions'     => array(),
			'region_mode'            => 'global_strict',
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function sanitize( array $settings ): array {
		return array(
			'enabled'            => ! empty( $settings['enabled'] ),
			'version'            => sanitize_text_field( (string) ( $settings['version'] ?? self::DEFAULT_VERSION ) ),
			'banner_headline'      => wp_kses( (string) ( $settings['banner_headline'] ?? '' ), $this->banner_inline_tags() ),
			'banner_description'   => $this->normalize_banner_description( (string) ( $settings['banner_description'] ?? '' ) ),
			'banner_position'      => in_array( $settings['banner_position'] ?? '', array( 'bottom' ), true )
				? (string) $settings['banner_position']
				: 'bottom',
			'show_site_logo'       => $this->show_site_logo_enabled( $settings ),
			'show_reopen_button'   => ! empty( $settings['show_reopen_button'] ),
			'freeze_version'       => ! empty( $settings['freeze_version'] ),
			'banner_revisions'     => $this->sanitize_banner_revisions( $settings['banner_revisions'] ?? array() ),
			'region_mode'            => in_array( $settings['region_mode'] ?? '', array( 'global_strict', 'eea' ), true )
				? (string) $settings['region_mode']
				: 'global_strict',
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function show_site_logo_enabled( array $settings ): bool {
		if ( '' === $this->site_logo_url() ) {
			return false;
		}

		if ( array_key_exists( 'show_site_logo', $settings ) ) {
			return ! empty( $settings['show_site_logo'] );
		}

		if ( array_key_exists( 'show_site_icon', $settings ) ) {
			return ! empty( $settings['show_site_icon'] );
		}

		$layout = $settings['banner_layout'] ?? null;

		if ( is_array( $layout ) && is_array( $layout['blocks'] ?? null ) ) {
			if ( in_array( 'site_logo', $layout['blocks'], true ) ) {
				return true;
			}

			return in_array( 'site_icon', $layout['blocks'], true );
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function banner_snapshot( array $settings ): array {
		return array(
			'banner_headline'    => (string) ( $settings['banner_headline'] ?? '' ),
			'banner_description' => (string) ( $settings['banner_description'] ?? '' ),
			'show_site_logo'     => $this->show_site_logo_enabled( $settings ),
			'show_reopen_button' => ! empty( $settings['show_reopen_button'] ),
		);
	}

	/**
	 * @param array<string, mixed> $before
	 * @param array<string, mixed> $after
	 */
	private function banner_changed( array $before, array $after ): bool {
		return $this->banner_snapshot( $before ) !== $this->banner_snapshot( $after );
	}

	private function increment_version( string $version ): string {
		if ( preg_match( '/^(\d+)\.(\d+)\.(\d+)$/', $version, $matches ) ) {
			return $matches[1] . '.' . $matches[2] . '.' . ( (int) $matches[3] + 1 );
		}

		if ( preg_match( '/^(\d+)$/', $version, $matches ) ) {
			return (string) ( (int) $matches[1] + 1 );
		}

		return $version . '.1';
	}

	/**
	 * @param list<array<string, mixed>> $revisions
	 * @param array<string, mixed>      $snapshot
	 * @return list<array<string, mixed>>
	 */
	private function append_revision( array $revisions, array $snapshot, string $version ): array {
		$revision = array_merge(
			$snapshot,
			array(
				'id'       => 'rev_' . bin2hex( random_bytes( 8 ) ),
				'saved_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'version'  => $version,
			)
		);

		array_unshift( $revisions, $revision );

		return array_slice( $revisions, 0, self::MAX_BANNER_REVISIONS );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function revision_banner_fields( array $settings, string $revision_id ): array {
		$revisions = is_array( $settings['banner_revisions'] ?? null ) ? $settings['banner_revisions'] : array();

		foreach ( $revisions as $revision ) {
			if ( ! is_array( $revision ) || ( $revision['id'] ?? '' ) !== $revision_id ) {
				continue;
			}

			return array(
				'banner_headline'    => (string) ( $revision['banner_headline'] ?? '' ),
				'banner_description' => (string) ( $revision['banner_description'] ?? '' ),
				'show_site_logo'     => ! empty( $revision['show_site_logo'] ) || ! empty( $revision['show_site_icon'] ),
				'show_reopen_button' => ! empty( $revision['show_reopen_button'] ),
			);
		}

		return array();
	}

	/**
	 * @param mixed $revisions
	 * @return list<array<string, mixed>>
	 */
	private function sanitize_banner_revisions( mixed $revisions ): array {
		if ( ! is_array( $revisions ) ) {
			return array();
		}

		$clean = array();

		foreach ( $revisions as $revision ) {
			if ( ! is_array( $revision ) ) {
				continue;
			}

			$id = sanitize_text_field( (string) ( $revision['id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}

			$clean[] = array(
				'id'                 => $id,
				'saved_at'           => sanitize_text_field( (string) ( $revision['saved_at'] ?? '' ) ),
				'version'            => sanitize_text_field( (string) ( $revision['version'] ?? self::DEFAULT_VERSION ) ),
				'banner_headline'    => wp_kses( (string) ( $revision['banner_headline'] ?? '' ), $this->banner_inline_tags() ),
				'banner_description' => wp_kses_post( (string) ( $revision['banner_description'] ?? '' ) ),
				'show_site_logo'     => ! empty( $revision['show_site_logo'] ) || ! empty( $revision['show_site_icon'] ),
				'show_reopen_button' => ! empty( $revision['show_reopen_button'] ),
			);

			if ( count( $clean ) >= self::MAX_BANNER_REVISIONS ) {
				break;
			}
		}

		return $clean;
	}

	private function normalize_banner_description( string $description ): string {
		$description = trim( $description );

		if ( '' === $description ) {
			return '';
		}

		if ( preg_match( '/<p[\s>]/i', $description ) ) {
			return wp_kses_post( $description );
		}

		return wp_kses_post( '<p>' . $description . '</p>' );
	}

	private function site_logo_url(): string {
		if ( ! function_exists( 'has_custom_logo' ) || ! has_custom_logo() ) {
			return '';
		}

		if ( ! function_exists( 'get_theme_mod' ) ) {
			return '';
		}

		$logo_id = get_theme_mod( 'custom_logo' );

		if ( empty( $logo_id ) ) {
			return '';
		}

		if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
			return '';
		}

		$image = wp_get_attachment_image_src( (int) $logo_id, 'full' );

		if ( ! is_array( $image ) || empty( $image[0] ) ) {
			return '';
		}

		return is_string( $image[0] ) ? $image[0] : '';
	}

	/**
	 * @return array<string, array<string, bool>>
	 */
	private function banner_inline_tags(): array {
		return array(
			'strong' => array(),
			'em'     => array(),
			'a'      => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			),
			'br'     => array(),
		);
	}
}
