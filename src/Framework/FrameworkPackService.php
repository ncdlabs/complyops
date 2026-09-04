<?php

declare(strict_types=1);

namespace ComplyOps\Framework;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exceptions; messages escaped at REST/WP_Error boundaries.

use ComplyOps\Control\ControlCatalog;
use ComplyOps\Control\ControlOverrideService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Validates, previews, and installs standardized ComplyOps framework packs.
 */
final class FrameworkPackService {

	public const PACK_FORMAT = 'complyops-framework-pack';

	/** @var list<string> */
	public const BUILTIN_FRAMEWORKS = array( 'gdpr', 'owasp', 'nist-csf' );

	public const OPTION_INSTALLED = 'complyops_installed_frameworks';

	public const OPTION_ACTIVATIONS = 'complyops_pack_activations';

	public const OPTION_INACTIVE = 'complyops_inactive_frameworks';

	public function __construct(
		private readonly FrameworkPackCipher $cipher = new FrameworkPackCipher(),
		private readonly FrameworkPackActivationClient $activation = new FrameworkPackActivationClient(),
	) {
	}

	/**
	 * @return list<array{id: string, label: string, path: string, version: string, control_count: int}>
	 */
	public function installed(): array {
		$ids = $this->installed_ids();
		$out = array();

		foreach ( $ids as $id ) {
			$path = $this->catalog_path( $id );

			if ( ! is_readable( $path ) ) {
				continue;
			}

			try {
				$catalog = ControlCatalog::from_file( $path );
			} catch ( \Throwable ) {
				continue;
			}

			$out[] = array(
				'id'            => $catalog->framework,
				'label'         => $this->label_for_catalog( $path, $catalog->framework ),
				'path'          => $path,
				'version'       => $catalog->version,
				'control_count' => $catalog->count(),
			);
		}

		return $out;
	}

	/**
	 * @return list<string>
	 */
	public function installed_ids(): array {
		$stored = get_option( self::OPTION_INSTALLED, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$ids = array();

		foreach ( $stored as $id ) {
			if ( is_string( $id ) && $this->is_valid_framework_id( $id ) ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @return list<array{
	 *     id: string,
	 *     label: string,
	 *     version: string,
	 *     control_count: int,
	 *     active: bool,
	 *     builtin: bool,
	 *     enabled_control_count: int,
	 *     disabled_control_count: int
	 * }>
	 */
	public function cataloged_frameworks( ?ControlOverrideService $overrides = null ): array {
		$overrides ??= new ControlOverrideService();
		$frameworks = array();

		foreach ( self::BUILTIN_FRAMEWORKS as $framework_id ) {
			$frameworks[] = $this->builtin_framework_summary( $framework_id, $overrides );
		}

		foreach ( $this->installed() as $installed ) {
			$frameworks[] = $this->framework_summary(
				$installed['id'],
				$installed['label'],
				$installed['version'],
				$installed['control_count'],
				false,
				$overrides
			);
		}

		return $frameworks;
	}

	public function is_active( string $framework_id ): bool {
		return ! in_array( $framework_id, $this->inactive_ids(), true );
	}

	public function set_active( string $framework_id, bool $active ): void {
		$inactive = $this->inactive_ids();

		if ( $active ) {
			$inactive = array_values(
				array_filter(
					$inactive,
					static fn ( string $id ): bool => $id !== $framework_id
				)
			);
		} elseif ( ! in_array( $framework_id, $inactive, true ) ) {
			$inactive[] = $framework_id;
		}

		update_option( self::OPTION_INACTIVE, $inactive, false );
	}

	/**
	 * @return list<string>
	 */
	public function inactive_ids(): array {
		$stored = get_option( self::OPTION_INACTIVE, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$ids = array();

		foreach ( $stored as $id ) {
			if ( is_string( $id ) && $this->is_valid_framework_id( $id ) ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	public function is_installed( string $framework_id ): bool {
		return in_array( $framework_id, $this->installed_ids(), true );
	}

	/**
	 * @return list<string>
	 */
	public function tsc_in_scope( string $framework_id ): array {
		$option_key = 'complyops_tsc_in_scope_' . sanitize_key( $framework_id );
		$stored     = get_option( $option_key );

		if ( is_array( $stored ) ) {
			$categories = array();

			foreach ( $stored as $category ) {
				if ( is_string( $category ) && '' !== trim( $category ) ) {
					$categories[] = sanitize_key( $category );
				}
			}

			return array_values( array_unique( $categories ) );
		}

		$metadata = $this->read_pack_metadata( $framework_id );
		$in_scope = $metadata['tsc_in_scope'] ?? array();

		if ( ! is_array( $in_scope ) ) {
			return array();
		}

		$categories = array();

		foreach ( $in_scope as $category ) {
			if ( is_string( $category ) && '' !== trim( $category ) ) {
				$categories[] = sanitize_key( $category );
			}
		}

		return array_values( array_unique( $categories ) );
	}

	/**
	 * @param list<string> $categories
	 */
	public function set_tsc_in_scope( string $framework_id, array $categories ): void {
		$normalized = array();

		foreach ( $categories as $category ) {
			if ( is_string( $category ) && '' !== trim( $category ) ) {
				$normalized[] = sanitize_key( $category );
			}
		}

		update_option(
			'complyops_tsc_in_scope_' . sanitize_key( $framework_id ),
			array_values( array_unique( $normalized ) ),
			false
		);
	}

	public function pack_disclaimer( string $framework_id ): ?string {
		$metadata = $this->read_pack_metadata( $framework_id );
		$text     = $metadata['disclaimer'] ?? null;

		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			return null;
		}

		return trim( $text );
	}

	public function catalog_path( string $framework_id ): string {
		return $this->storage_dir() . '/' . $framework_id . '.json';
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function preview( array $input, string $unlock_key ): array {
		$resolved = $this->resolve_input( $input, $unlock_key );
		$pack     = $resolved['pack'];
		$catalog  = $this->parse_pack( $pack );

		return array(
			'framework'         => $catalog->framework,
			'label'             => $this->label_from_pack( $pack, $catalog->framework ),
			'version'           => $catalog->version,
			'pack_version'      => $this->pack_version_from_data( $pack ),
			'control_count'     => $catalog->count(),
			'already_installed' => $this->is_installed( $catalog->framework ),
			'activation'        => $resolved['activation'],
			'controls'          => array_map(
				static fn ( $control ): array => array(
					'id'          => $control->definition()->id,
					'title'       => $control->definition()->title,
					'description' => $control->definition()->description,
					'category'    => $control->definition()->category,
					'severity'    => $control->definition()->severity->value,
					'capability'  => $control->definition()->capability->value,
					'references'  => $control->definition()->references,
				),
				$catalog->controls()
			),
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function install( array $input, string $unlock_key ): array {
		$resolved  = $this->resolve_input( $input, $unlock_key );
		$pack      = $resolved['pack'];
		$catalog   = $this->parse_pack( $pack );
		$framework = $catalog->framework;
		$label     = $this->label_from_pack( $pack, $framework );
		$destination = $this->catalog_path( $framework );

		if ( $this->is_builtin_framework( $framework ) ) {
			throw new InvalidArgumentException(
				__( 'This framework is built into ComplyOps and cannot be installed from a pack.', 'complyops' )
			);
		}

		if ( ! wp_mkdir_p( $this->storage_dir() ) ) {
			throw new RuntimeException(
				__( 'ComplyOps could not create the framework storage directory.', 'complyops' )
			);
		}

		$normalized = $this->normalize_pack_for_storage( $pack, $catalog );
		$encoded    = wp_json_encode( $normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( ! is_string( $encoded ) ) {
			throw new RuntimeException(
				__( 'ComplyOps could not encode the framework pack.', 'complyops' )
			);
		}

		$written = file_put_contents( $destination, $encoded . "\n" );

		if ( false === $written ) {
			throw new RuntimeException(
				__( 'ComplyOps could not save the framework pack.', 'complyops' )
			);
		}

		$this->mark_installed( $framework );
		$this->store_activation( $framework, $unlock_key, $resolved['activation'] );

		if ( empty( $resolved['activation']['skipped'] ) ) {
			( new \ComplyOps\Verification\BrowserVerificationProvisioner() )->provision_for_unlock_key( $unlock_key );
		}

		return array(
			'framework'     => $framework,
			'label'         => $label,
			'version'       => $catalog->version,
			'control_count' => $catalog->count(),
			'installed'     => true,
			'activation'    => $resolved['activation'],
		);
	}

	/**
	 * @param array<string, mixed> $pack
	 */
	public function parse_pack( array $pack ): ControlCatalog {
		$this->assert_pack_format( $pack );

		$framework = isset( $pack['framework'] ) && is_string( $pack['framework'] )
			? sanitize_key( $pack['framework'] )
			: '';

		if ( ! $this->is_valid_framework_id( $framework ) ) {
			throw new InvalidArgumentException(
				__( 'Framework pack requires a valid framework identifier.', 'complyops' )
			);
		}

		return ControlCatalog::from_array( $pack );
	}

	public function cipher(): FrameworkPackCipher {
		return $this->cipher;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{pack: array<string, mixed>, activation: array<string, mixed>}
	 */
	private function resolve_input( array $input, string $unlock_key ): array {
		if ( $this->cipher->is_encrypted_envelope( $input ) ) {
			return $this->resolve_encrypted_input( $input, $unlock_key );
		}

		if ( ! $this->allow_plaintext_packs() ) {
			throw new InvalidArgumentException(
				__( 'Compliance packs must be encrypted. Upload a .complyops-pack file from the ncdLabs store.', 'complyops' )
			);
		}

		return array(
			'pack'       => $input,
			'activation' => array(
				'activated' => true,
				'reused'    => true,
				'skipped'   => true,
			),
		);
	}

	/**
	 * @param array<string, mixed> $envelope
	 * @return array{pack: array<string, mixed>, activation: array<string, mixed>}
	 */
	private function resolve_encrypted_input( array $envelope, string $unlock_key ): array {
		$framework = isset( $envelope['framework_hint'] ) && is_string( $envelope['framework_hint'] )
			? sanitize_key( $envelope['framework_hint'] )
			: '';

		if ( ! $this->is_valid_framework_id( $framework ) ) {
			throw new InvalidArgumentException(
				__( 'Encrypted compliance pack is missing a valid framework identifier.', 'complyops' )
			);
		}

		$activation = $this->activation->activate( $unlock_key, $framework );
		$pack       = $this->cipher->decrypt_envelope( $envelope, $unlock_key );

		return array(
			'pack'       => $pack,
			'activation' => $activation,
		);
	}

	private function allow_plaintext_packs(): bool {
		/**
		 * Allow importing unencrypted framework packs.
		 *
		 * Defaults to false. Development and tests may enable this via the filter.
		 * Do not enable on production WordPress.org installs.
		 *
		 * @param bool $allow Whether plaintext packs are accepted.
		 */
		return (bool) apply_filters( 'complyops_allow_plaintext_framework_packs', false );
	}

	/**
	 * @param array<string, mixed> $pack
	 */
	private function assert_pack_format( array $pack ): void {
		$format = isset( $pack['pack_format'] ) && is_string( $pack['pack_format'] )
			? $pack['pack_format']
			: '';

		if ( self::PACK_FORMAT !== $format ) {
			throw new InvalidArgumentException(
				__( 'Unrecognized framework pack format.', 'complyops' )
			);
		}
	}

	private function is_valid_framework_id( string $framework_id ): bool {
		return '' !== $framework_id && (bool) preg_match( '/^[a-z0-9-]+$/', $framework_id );
	}

	private function storage_dir(): string {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			throw new RuntimeException(
				__( 'WordPress uploads directory is unavailable.', 'complyops' )
			);
		}

		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			throw new RuntimeException( (string) $uploads['error'] );
		}

		return trailingslashit( (string) $uploads['basedir'] ) . 'complyops/frameworks';
	}

	private function mark_installed( string $framework_id ): void {
		$ids = $this->installed_ids();

		if ( ! in_array( $framework_id, $ids, true ) ) {
			$ids[] = $framework_id;
		}

		update_option( self::OPTION_INSTALLED, $ids, false );
	}

	/**
	 * @param array<string, mixed> $activation
	 */
	private function store_activation( string $framework, string $unlock_key, array $activation ): void {
		$stored = get_option( self::OPTION_ACTIVATIONS, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$normalized = strtolower( preg_replace( '/[^0-9a-f]/', '', $unlock_key ) ?? '' );

		$stored[ $framework ] = array(
			'unlock_key_hash' => hash( 'sha256', $normalized ),
			'site_url'        => $activation['site_url'] ?? home_url(),
			'activated_at'    => $activation['activated_at'] ?? gmdate( 'c' ),
		);

		update_option( self::OPTION_ACTIVATIONS, $stored, false );
	}

	/**
	 * @param array<string, mixed> $pack
	 */
	private function label_from_pack( array $pack, string $framework ): string {
		if ( isset( $pack['label'] ) && is_string( $pack['label'] ) && '' !== trim( $pack['label'] ) ) {
			return trim( $pack['label'] );
		}

		return strtoupper( $framework );
	}

	private function label_for_catalog( string $path, string $framework ): string {
		if ( ! is_readable( $path ) ) {
			return strtoupper( $framework );
		}

		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			return strtoupper( $framework );
		}

		/** @var array<string, mixed>|null $data */
		$data = json_decode( $contents, true );

		if ( ! is_array( $data ) ) {
			return strtoupper( $framework );
		}

		return $this->label_from_pack( $data, $framework );
	}

	/**
	 * @param array<string, mixed> $pack
	 */
	private function pack_version_from_data( array $pack ): string {
		return isset( $pack['pack_version'] ) && is_string( $pack['pack_version'] )
			? $pack['pack_version']
			: '1.0.0';
	}

	/**
	 * @param array<string, mixed> $pack
	 * @return array<string, mixed>
	 */
	private function normalize_pack_for_storage( array $pack, ControlCatalog $catalog ): array {
		$normalized              = $pack;
		$normalized['framework'] = $catalog->framework;
		$normalized['version']   = $catalog->version;
		$normalized['label']     = $this->label_from_pack( $pack, $catalog->framework );
		unset( $normalized['pack_format'] );

		$normalized['pack_format'] = self::PACK_FORMAT;

		return $normalized;
	}

	/**
	 * @return array{
	 *     id: string,
	 *     label: string,
	 *     version: string,
	 *     control_count: int,
	 *     active: bool,
	 *     builtin: bool,
	 *     enabled_control_count: int,
	 *     disabled_control_count: int
	 * }
	 */
	private function is_builtin_framework( string $framework_id ): bool {
		return in_array( $framework_id, self::BUILTIN_FRAMEWORKS, true );
	}

	private function builtin_framework_summary( string $framework_id, ControlOverrideService $overrides ): array {
		$path = COMPLYOPS_PLUGIN_DIR . 'data/controls/' . $framework_id . '.json';

		try {
			$catalog = ControlCatalog::from_file( $path );
		} catch ( \Throwable ) {
			return $this->framework_summary(
				$framework_id,
				$this->builtin_framework_label( $framework_id ),
				'1.0.0',
				0,
				true,
				$overrides
			);
		}

		return $this->framework_summary(
			$catalog->framework,
			$this->builtin_framework_label( $framework_id ),
			$catalog->version,
			$catalog->count(),
			true,
			$overrides
		);
	}

	private function builtin_framework_label( string $framework_id ): string {
		return match ( $framework_id ) {
			'gdpr'     => 'GDPR',
			'owasp'    => 'OWASP Top 10:2025',
			'nist-csf' => 'NIST CSF 2.0',
			default    => strtoupper( $framework_id ),
		};
	}

	/**
	 * @return array{
	 *     id: string,
	 *     label: string,
	 *     version: string,
	 *     control_count: int,
	 *     active: bool,
	 *     builtin: bool,
	 *     enabled_control_count: int,
	 *     disabled_control_count: int
	 * }
	 */
	private function framework_summary(
		string $id,
		string $label,
		string $version,
		int $control_count,
		bool $builtin,
		ControlOverrideService $overrides,
	): array {
		$disabled = 0;

		foreach ( $overrides->overrides_for_framework( $id ) as $override ) {
			if ( is_array( $override ) && empty( $override['enabled'] ) ) {
				++$disabled;
			}
		}

		return array(
			'id'                     => $id,
			'label'                  => $label,
			'version'                => $version,
			'control_count'          => $control_count,
			'active'                 => $this->is_active( $id ),
			'builtin'                => $builtin,
			'enabled_control_count'  => max( 0, $control_count - $disabled ),
			'disabled_control_count' => $disabled,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_pack_metadata( string $framework_id ): array {
		foreach ( $this->catalog_paths_to_try( $framework_id ) as $path ) {
			if ( ! is_readable( $path ) ) {
				continue;
			}

			$contents = file_get_contents( $path );

			if ( false === $contents ) {
				continue;
			}

			/** @var array<string, mixed>|null $data */
			$data = json_decode( $contents, true );

			if ( is_array( $data ) ) {
				return $data;
			}
		}

		return array();
	}

	/**
	 * @return list<string>
	 */
	private function catalog_paths_to_try( string $framework_id ): array {
		return array(
			$this->catalog_path( $framework_id ),
			COMPLYOPS_PLUGIN_DIR . 'data/packs/' . $framework_id . '.json',
			COMPLYOPS_PLUGIN_DIR . 'data/controls/' . $framework_id . '.json',
		);
	}
}
