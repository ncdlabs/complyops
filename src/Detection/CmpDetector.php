<?php

declare(strict_types=1);

namespace ComplyOps\Detection;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects third-party consent management platforms.
 */
final class CmpDetector {

	/**
	 * @param list<string> $active_plugin_slugs
	 * @return array<string, mixed>
	 */
	public function detect( array $active_plugin_slugs ): array {
		$providers = array();

		foreach ( Signatures::consent_providers() as $id => $definition ) {
			$detected = $this->has_active_plugin( $active_plugin_slugs, $definition['plugin_slugs'] );

			$providers[ $id ] = array(
				'detected' => $detected,
				'label'    => $definition['label'],
			);
		}

		$detected = array_values(
			array_filter(
				$providers,
				static fn ( array $provider ): bool => $provider['detected']
			)
		);

		$active = array_keys(
			array_filter(
				$providers,
				static fn ( array $provider ): bool => $provider['detected']
			)
		);

		return array(
			'providers'      => $providers,
			'detected_any'   => array() !== $detected,
			'detected_ids'   => $active,
			'primary'        => $active[0] ?? null,
		);
	}

	/**
	 * @param list<string> $active_plugin_slugs
	 * @param list<string> $plugin_slugs
	 */
	private function has_active_plugin( array $active_plugin_slugs, array $plugin_slugs ): bool {
		foreach ( $plugin_slugs as $slug ) {
			if ( in_array( $slug, $active_plugin_slugs, true ) ) {
				return true;
			}
		}

		return false;
	}
}
