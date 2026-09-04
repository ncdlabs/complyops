<?php

declare(strict_types=1);

namespace ComplyOps\Integration\Forms;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Detection\FormFieldAnalyzer;
use ComplyOps\Detection\Signatures;

/**
 * Shared helpers for form plugin adapters.
 */
abstract class AbstractFormIntegration implements FormIntegrationInterface {

	public function __construct(
		protected readonly FormFieldAnalyzer $analyzer = new FormFieldAnalyzer(),
	) {
	}

	/**
	 * @param list<string> $active_plugin_slugs
	 */
	public function is_active( array $active_plugin_slugs ): bool {
		$definition = Signatures::form_plugins()[ $this->id() ] ?? null;

		if ( ! is_array( $definition ) ) {
			return false;
		}

		foreach ( $definition['plugin_slugs'] as $slug ) {
			if ( in_array( $slug, $active_plugin_slugs, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param list<array{name: string, label?: string, type?: string}> $raw_fields
	 * @return list<array<string, mixed>>
	 */
	protected function analyze_fields( array $raw_fields ): array {
		$fields = array();

		foreach ( $raw_fields as $field ) {
			$fields[] = $this->analyzer->analyze_field(
				$field['name'],
				$field['label'] ?? '',
				$field['type'] ?? ''
			);
		}

		return $fields;
	}

	/**
	 * @param list<array<string, mixed>> $fields
	 * @return array<string, mixed>
	 */
	protected function form_entry( int|string $id, string $title, array $fields ): array {
		$summary = $this->analyzer->summarize_form( $fields );

		return array(
			'id'      => (string) $id,
			'title'   => $title,
			'plugin'  => $this->id(),
			'fields'  => $fields,
			'summary' => $summary,
		);
	}
}
