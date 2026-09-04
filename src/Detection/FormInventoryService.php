<?php

declare(strict_types=1);

namespace ComplyOps\Detection;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Integration\Forms\ContactForm7Integration;
use ComplyOps\Integration\Forms\ElementorFormsIntegration;
use ComplyOps\Integration\Forms\FormIntegrationInterface;
use ComplyOps\Integration\Forms\GravityFormsIntegration;
use ComplyOps\Integration\Forms\WpformsIntegration;

/**
 * Builds a cross-plugin form inventory with field analysis.
 */
final class FormInventoryService {

	public function __construct(
		private readonly FormFieldAnalyzer $analyzer = new FormFieldAnalyzer(),
	) {
	}

	/**
	 * @param list<string> $active_plugin_slugs
	 * @return array<string, mixed>
	 */
	public function inventory( array $active_plugin_slugs ): array {
		$forms = array();

		foreach ( $this->adapters() as $adapter ) {
			if ( ! $adapter->is_active( $active_plugin_slugs ) ) {
				continue;
			}

			$forms = array_merge( $forms, $adapter->inventory() );
		}

		return array(
			'forms'   => $forms,
			'summary' => $this->analyzer->summarize_inventory( $forms ),
		);
	}

	/**
	 * @return list<FormIntegrationInterface>
	 */
	private function adapters(): array {
		return array(
			new ContactForm7Integration( $this->analyzer ),
			new GravityFormsIntegration( $this->analyzer ),
			new WpformsIntegration( $this->analyzer ),
			new ElementorFormsIntegration( $this->analyzer ),
		);
	}
}
