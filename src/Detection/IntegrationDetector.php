<?php

declare(strict_types=1);

namespace ComplyOps\Detection;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Matches active plugins and HTML signatures to known integrations.
 */
final class IntegrationDetector {

	public function __construct(
		private readonly HtmlScanner $scanner = new HtmlScanner(),
	) {
	}

	/**
	 * @param list<string> $active_plugin_slugs
	 * @param list<string> $script_sources
	 * @param list<string> $iframe_sources
	 * @return array<string, array{detected: bool, label: string, sources: list<string>, confidence: string}>
	 */
	public function detect( array $active_plugin_slugs, array $script_sources, array $iframe_sources ): array {
		$integrations = array();

		foreach ( Signatures::integrations() as $id => $definition ) {
			$plugin_hit = $this->plugin_match( $active_plugin_slugs, $definition['plugin_slugs'] );
			$sources    = array();

			if ( $this->scanner->matches_patterns( $script_sources, $definition['patterns'] ) ) {
				$sources[] = 'script';
			}

			if ( $this->scanner->matches_patterns( $iframe_sources, $definition['patterns'] ) ) {
				$sources[] = 'iframe';
			}

			if ( $plugin_hit ) {
				$sources[] = 'plugin';
			}

			$detected = array() !== $sources;
			$confidence = 'high';

			if ( $detected && ! in_array( 'script', $sources, true ) && ! in_array( 'iframe', $sources, true ) ) {
				$confidence = 'medium';
			}

			$integrations[ $id ] = array(
				'detected'   => $detected,
				'label'      => $definition['label'],
				'sources'    => array_values( array_unique( $sources ) ),
				'confidence' => $detected ? $confidence : 'none',
			);
		}

		return $integrations;
	}

	/**
	 * @param list<string> $active_plugin_slugs
	 * @param list<string> $plugin_slugs
	 */
	private function plugin_match( array $active_plugin_slugs, array $plugin_slugs ): bool {
		foreach ( $plugin_slugs as $slug ) {
			if ( in_array( $slug, $active_plugin_slugs, true ) ) {
				return true;
			}
		}

		return false;
	}
}
