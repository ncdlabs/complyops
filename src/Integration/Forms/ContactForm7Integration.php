<?php

declare(strict_types=1);

namespace ComplyOps\Integration\Forms;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact Form 7 inventory adapter.
 */
final class ContactForm7Integration extends AbstractFormIntegration {

	public function id(): string {
		return 'contact_form_7';
	}

	public function label(): string {
		return 'Contact Form 7';
	}

	public function inventory(): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
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
			if ( ! is_object( $post ) || ! isset( $post->ID, $post->post_title, $post->post_content ) ) {
				continue;
			}

			$forms[] = $this->form_entry(
				(int) $post->ID,
				(string) $post->post_title,
				$this->parse_cf7_markup( (string) $post->post_content )
			);
		}

		return $forms;
	}

	/**
	 * @return list<array{name: string, label: string, type: string}>
	 */
	private function parse_cf7_markup( string $content ): array {
		$fields = array();

		if ( preg_match_all( '/\[(?:\/)?(?:\w+\*?\s+)?([^\s\]"\'\]]+)/', $content, $matches ) ) {
			foreach ( $matches[0] as $index => $token ) {
				$name = (string) ( $matches[1][ $index ] ?? '' );

				if ( '' === $name || in_array( $name, array( 'submit', 'response', 'captchar', 'quiz' ), true ) ) {
					continue;
				}

				$type = $this->cf7_field_type( $token );

				if ( 'submit' === $type ) {
					continue;
				}

				$fields[] = array(
					'name'  => $name,
					'label' => $name,
					'type'  => $type,
				);
			}
		}

		return $fields;
	}

	private function cf7_field_type( string $token ): string {
		if ( preg_match( '/^\[(?:(\w+)\*?)/', $token, $matches ) ) {
			return strtolower( $matches[1] );
		}

		return 'text';
	}
}
