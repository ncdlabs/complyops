<?php

declare(strict_types=1);

namespace ComplyOps\Integration\Forms;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor form widget inventory adapter.
 */
final class ElementorFormsIntegration extends AbstractFormIntegration {

	public function id(): string {
		return 'elementor_forms';
	}

	public function label(): string {
		return 'Elementor';
	}

	public function inventory(): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => array( 'page', 'post', 'elementor_library' ),
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_elementor_data',
						'compare' => 'LIKE',
						'value'   => '"widgetType":"form"',
					),
				),
			)
		);

		if ( ! is_array( $posts ) ) {
			return array();
		}

		$forms = array();

		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) || ! isset( $post->ID, $post->post_title ) ) {
				continue;
			}

			$fields = $this->parse_elementor_fields( (int) $post->ID );

			if ( array() === $fields ) {
				continue;
			}

			$forms[] = $this->form_entry(
				(int) $post->ID,
				(string) $post->post_title,
				$fields
			);
		}

		return $forms;
	}

	/**
	 * @return list<array{name: string, label: string, type: string}>
	 */
	private function parse_elementor_fields( int $post_id ): array {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return array();
		}

		$encoded = get_post_meta( $post_id, '_elementor_data', true );

		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return array();
		}

		$data = json_decode( $encoded, true );

		if ( ! is_array( $data ) ) {
			return array();
		}

		return $this->collect_elementor_form_fields( $data );
	}

	/**
	 * @param list<mixed> $elements
	 * @return list<array{name: string, label: string, type: string}>
	 */
	private function collect_elementor_form_fields( array $elements ): array {
		$fields = array();

		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( ( $element['widgetType'] ?? '' ) === 'form' ) {
				$form_fields = $element['settings']['form_fields'] ?? array();

				if ( is_array( $form_fields ) ) {
					foreach ( $form_fields as $field ) {
						if ( ! is_array( $field ) ) {
							continue;
						}

						$type = strtolower( (string) ( $field['field_type'] ?? 'text' ) );

						$fields[] = array(
							'name'  => (string) ( $field['custom_id'] ?? $field['_id'] ?? $field['field_label'] ?? 'field' ),
							'label' => (string) ( $field['field_label'] ?? '' ),
							'type'  => $type,
						);
					}
				}
			}

			$children = $element['elements'] ?? array();

			if ( is_array( $children ) && array() !== $children ) {
				$fields = array_merge( $fields, $this->collect_elementor_form_fields( $children ) );
			}
		}

		return $fields;
	}
}
