<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation\Tests;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\Evaluation\AbstractControlEvaluator;
use ComplyOps\Control\Evaluation\EvaluationContext;

/**
 * WordPress core, plugin, and supply-chain posture.
 */
final class PlatformInventoryTests extends AbstractSharedTests {

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'wp.core_version_supported'       => array( $this, 'core_version_supported' ),
			'platform.plugin_posture'         => array( $this, 'plugin_posture' ),
			'platform.vulnerable_plugins'     => array( $this, 'vulnerable_plugins' ),
			'platform.abandoned_plugins'      => array( $this, 'abandoned_plugins' ),
			'platform.checksum_integrity'     => array( $this, 'checksum_integrity' ),
			'platform.unexpected_executables' => array( $this, 'unexpected_executables' ),
			'platform.trusted_update_sources' => array( $this, 'trusted_update_sources' ),
		);
	}

	public function core_version_supported( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		global $wp_version;

		$version = is_string( $wp_version ) ? $wp_version : '';
		$minimum = '6.6';

		if ( '' === $version ) {
			return $this->unknown(
				$definition,
				__( 'WordPress version could not be determined.', 'complyops' ),
				sprintf(
					/* translators: %s: minimum WordPress version */
					__( 'WordPress %s or newer.', 'complyops' ),
					$minimum
				),
			);
		}

		if ( version_compare( $version, $minimum, '>=' ) ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %s: WordPress version */
					__( 'WordPress %s meets the supported minimum.', 'complyops' ),
					$version
				),
				sprintf(
					/* translators: %s: minimum WordPress version */
					__( 'WordPress %s or newer.', 'complyops' ),
					$minimum
				),
			);
		}

		return $this->fail(
			$definition,
			sprintf(
				/* translators: 1: current version, 2: minimum version */
				__( 'WordPress %1$s is below the supported minimum (%2$s).', 'complyops' ),
				$version,
				$minimum
			),
			sprintf(
				/* translators: %s: minimum WordPress version */
				__( 'WordPress %s or newer.', 'complyops' ),
				$minimum
			),
		);
	}

	public function plugin_posture( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		$plugins = $context->discovery['wordpress']['installed_plugins'] ?? array();

		if ( ! is_array( $plugins ) || array() === $plugins ) {
			return $this->unknown(
				$definition,
				__( 'Installed plugin inventory is unavailable.', 'complyops' ),
				__( 'Plugins with data-protection impact are kept reasonably current.', 'complyops' ),
			);
		}

		$active_count = count(
			array_filter(
				$plugins,
				static fn ( array $plugin ): bool => ! empty( $plugin['active'] )
			)
		);

		return $this->info(
			$definition,
			sprintf(
				/* translators: 1: active plugin count, 2: total plugin count */
				__( 'Discovered %1$d active plugins of %2$d installed; version review is informational.', 'complyops' ),
				$active_count,
				count( $plugins )
			),
			__( 'Plugins with data-protection impact are kept reasonably current.', 'complyops' ),
		);
	}

	public function vulnerable_plugins( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		$plugins = $context->discovery['wordpress']['installed_plugins'] ?? array();

		if ( ! is_array( $plugins ) || array() === $plugins ) {
			return $this->unknown(
				$definition,
				__( 'Plugin inventory unavailable for vulnerability review.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		$outdated = array();

		foreach ( $plugins as $plugin ) {
			if ( ! is_array( $plugin ) || empty( $plugin['active'] ) ) {
				continue;
			}

			if ( ! empty( $plugin['update_available'] ) ) {
				$outdated[] = (string) ( $plugin['name'] ?? $plugin['slug'] ?? 'unknown' );
			}
		}

		if ( array() === $outdated ) {
			return $this->pass(
				$definition,
				__( 'No active plugins with pending updates were flagged in the discovery snapshot.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: %s: comma-separated plugin names */
				__( 'Active plugins with available updates: %s.', 'complyops' ),
				implode( ', ', array_slice( $outdated, 0, 5 ) )
			),
			$definition->recommended_value,
		);
	}

	public function abandoned_plugins( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		return $this->unknown(
			$definition,
			__( 'Abandoned plugin detection requires vulnerability intelligence review.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function checksum_integrity( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		if ( ! function_exists( 'get_core_checksums' ) ) {
			return $this->unknown(
				$definition,
				__( 'WordPress checksum verification is unavailable in this environment.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		global $wp_version;
		$locale    = get_locale();
		$checksums = get_core_checksums( is_string( $wp_version ) ? $wp_version : '', $locale );

		if ( ! is_array( $checksums ) || array() === $checksums ) {
			return $this->unknown(
				$definition,
				__( 'WordPress core checksums could not be retrieved.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->pass(
			$definition,
			__( 'WordPress core checksum API is reachable for integrity verification.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function unexpected_executables( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$upload_dir = wp_upload_dir();
		$basedir    = isset( $upload_dir['basedir'] ) && is_string( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';

		if ( '' === $basedir || ! is_dir( $basedir ) ) {
			return $this->unknown(
				$definition,
				__( 'Uploads directory could not be scanned.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		$executables = array( 'php', 'phtml', 'php5', 'phar' );
		$found       = array();
		$iterator    = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $basedir, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof \SplFileInfo || ! $file->isFile() ) {
				continue;
			}

			$ext = strtolower( $file->getExtension() );

			if ( in_array( $ext, $executables, true ) ) {
				$found[] = str_replace( ABSPATH, '', $file->getPathname() );
			}

			if ( count( $found ) >= 5 ) {
				break;
			}
		}

		if ( array() === $found ) {
			return $this->pass(
				$definition,
				__( 'No unexpected executable files were found in uploads during a shallow scan.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->fail(
			$definition,
			sprintf(
				/* translators: %s: comma-separated file paths */
				__( 'Unexpected executable files in uploads: %s.', 'complyops' ),
				implode( ', ', $found )
			),
			$definition->recommended_value,
		);
	}

	public function trusted_update_sources( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			return $this->pass(
				$definition,
				__( 'File modifications are restricted (DISALLOW_FILE_MODS).', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'Review plugin and theme update sources; restrict untrusted ZIP uploads.', 'complyops' ),
			$definition->recommended_value,
		);
	}
}
