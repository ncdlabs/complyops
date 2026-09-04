<?php

declare(strict_types=1);

namespace ComplyOps\Integration\Forms;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPForms inventory adapter.
 */
final class WpformsIntegration extends AbstractFormIntegration {

	public function id(): string {
		return 'wpforms';
	}

	public function label(): string {
		return 'WPForms';
	}

	public function inventory(): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'wpforms',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
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

			$forms[] = $this->form_entry(
				(int) $post->ID,
				(string) $post->post_title,
				$this->parse_wpforms_fields( (int) $post->ID )
			);
		}

		return $forms;
	}

	/**
	 * @return list<array{name: string, label: string, type: string}>
	 */
	private function parse_wpforms_fields( int $post_id ): array {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return array();
		}

		$encoded = get_post_meta( $post_id, 'wpforms_form_data', true );

		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return array();
		}

		$data = json_decode( $encoded, true );

		if ( ! is_array( $data ) || ! isset( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
			return array();
		}

		$parsed = array();

		foreach ( $data['fields'] as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$type = strtolower( (string) ( $field['type'] ?? 'text' ) );

			if ( in_array( $type, array( 'divider', 'html', 'pagebreak' ), true ) ) {
				continue;
			}

			$parsed[] = array(
				'name'  => (string) ( $field['meta']['name'] ?? $field['label'] ?? $field['id'] ?? 'field' ),
				'label' => (string) ( $field['label'] ?? '' ),
				'type'  => $type,
			);
		}

		return $parsed;
	}
}
