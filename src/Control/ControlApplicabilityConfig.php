<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declarative applicability hints for a control.
 */
final class ControlApplicabilityConfig {

	public function __construct(
		public readonly bool $requires_confirmation = false,
		public readonly ?string $condition = null,
	) {
	}

	/**
	 * @param array<string, mixed>|null $row
	 */
	public static function from_array( ?array $row ): self {
		if ( null === $row ) {
			return new self();
		}

		$requires = ! empty( $row['requires_confirmation'] );
		$condition = isset( $row['condition'] ) && is_string( $row['condition'] ) && '' !== $row['condition']
			? $row['condition']
			: null;

		return new self(
			requires_confirmation: $requires,
			condition: $condition,
		);
	}

	/**
	 * @return array<string, bool|string|null>
	 */
	public function to_array(): array {
		return array(
			'requires_confirmation' => $this->requires_confirmation,
			'condition'               => $this->condition,
		);
	}
}
