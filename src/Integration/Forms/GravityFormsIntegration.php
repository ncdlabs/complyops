<?php

declare(strict_types=1);

namespace ComplyOps\Integration\Forms;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gravity Forms inventory adapter.
 */
final class GravityFormsIntegration extends AbstractFormIntegration {

	public function id(): string {
		return 'gravity_forms';
	}

	public function label(): string {
		return 'Gravity Forms';
	}

	public function inventory(): array {
		if ( ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_forms' ) ) {
			return array();
		}

		$raw_forms = \GFAPI::get_forms();

		if ( ! is_array( $raw_forms ) ) {
			return array();
		}

		$forms = array();

		foreach ( $raw_forms as $form ) {
			if ( ! is_array( $form ) ) {
				continue;
			}

			$id     = $form['id'] ?? null;
			$title  = $form['title'] ?? 'Untitled form';
			$fields = $form['fields'] ?? array();

			if ( null === $id || ! is_array( $fields ) ) {
				continue;
			}

			$forms[] = $this->form_entry(
				(int) $id,
				(string) $title,
				$this->parse_gravity_fields( $fields )
			);
		}

		return $forms;
	}

	/**
	 * @param list<mixed> $fields
	 * @return list<array{name: string, label: string, type: string}>
	 */
	private function parse_gravity_fields( array $fields ): array {
		$parsed = array();

		foreach ( $fields as $field ) {
			if ( ! is_object( $field ) && ! is_array( $field ) ) {
				continue;
			}

			$data = is_object( $field ) ? get_object_vars( $field ) : $field;
			$type = strtolower( (string) ( $data['type'] ?? 'text' ) );

			if ( in_array( $type, array( 'html', 'section', 'page', 'captcha' ), true ) ) {
				continue;
			}

			$parsed[] = array(
				'name'  => (string) ( $data['inputName'] ?? $data['label'] ?? $data['id'] ?? 'field' ),
				'label' => (string) ( $data['label'] ?? '' ),
				'type'  => $type,
			);
		}

		return $parsed;
	}
}
