<?php

declare(strict_types=1);

namespace ComplyOps\PublicStatus;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Administrator settings for the optional public compliance status surface.
 */
final class PublicStatusSettings {

	public const OPTION_KEY = 'complyops_public_status_settings';

	/** @var list<string> */
	public const PUBLISHABLE_FIELDS = array(
		'framework',
		'technical_status',
		'score',
		'monitoring',
		'last_verified',
		'critical_findings',
		'high_findings',
	);

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		return array_merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
	}

	public function page_enabled(): bool {
		return ! empty( $this->all()['page_enabled'] );
	}

	public function api_enabled(): bool {
		return ! empty( $this->all()['api_enabled'] );
	}

	public function is_public_enabled(): bool {
		return $this->page_enabled() || $this->api_enabled();
	}

	/**
	 * @return list<string>
	 */
	public function published_fields(): array {
		$fields = $this->all()['published_fields'] ?? array();

		if ( ! is_array( $fields ) ) {
			return self::PUBLISHABLE_FIELDS;
		}

		return array_values(
			array_filter(
				$fields,
				static fn ( mixed $field ): bool => is_string( $field ) && in_array( $field, self::PUBLISHABLE_FIELDS, true )
			)
		);
	}

	public function framework_id(): string {
		$framework = sanitize_key( (string) ( $this->all()['framework'] ?? 'gdpr' ) );

		return '' !== $framework ? $framework : 'gdpr';
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
	public function admin_config(): array {
		$settings        = $this->all();
		$api_url         = rest_url( COMPLYOPS_REST_NAMESPACE . '/public/status.json' );
		$api_preview_url = add_query_arg(
			array(
				'preview'  => '1',
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			),
			$api_url
		);

		return array(
			'page_enabled'       => $this->page_enabled(),
			'api_enabled'        => $this->api_enabled(),
			'framework'          => $this->framework_id(),
			'published_fields'   => $this->published_fields(),
			'page_title'         => (string) ( $settings['page_title'] ?? '' ),
			'page_url'           => PublicStatusPage::public_url(),
			'page_preview_url'   => PublicStatusPage::preview_url(),
			'api_url'            => $api_url,
			'api_preview_url'    => $api_preview_url,
			'publishable_fields' => self::PUBLISHABLE_FIELDS,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return array(
			'page_enabled'     => false,
			'api_enabled'      => false,
			'framework'        => 'gdpr',
			'published_fields' => self::PUBLISHABLE_FIELDS,
			'page_title'       => __( 'Compliance Status', 'complyops' ),
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function sanitize( array $settings ): array {
		$published = $settings['published_fields'] ?? self::PUBLISHABLE_FIELDS;
		$published = is_array( $published ) ? $published : self::PUBLISHABLE_FIELDS;

		return array(
			'page_enabled'     => ! empty( $settings['page_enabled'] ),
			'api_enabled'      => ! empty( $settings['api_enabled'] ),
			'framework'        => sanitize_key( (string) ( $settings['framework'] ?? 'gdpr' ) ) ?: 'gdpr',
			'published_fields' => array_values(
				array_filter(
					$published,
					static fn ( mixed $field ): bool => is_string( $field ) && in_array( $field, self::PUBLISHABLE_FIELDS, true )
				)
			),
			'page_title'       => sanitize_text_field( (string) ( $settings['page_title'] ?? __( 'Compliance Status', 'complyops' ) ) ),
		);
	}
}
