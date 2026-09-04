<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authoritative reference metadata for a control.
 */
final class ControlSource {

	public function __construct(
		public readonly string $authority,
		public readonly string $version,
		public readonly ?string $reference = null,
		public readonly ?string $last_legal_review = null,
	) {
	}

	/**
	 * @param array<string, mixed>|null $row
	 */
	public static function from_array( ?array $row ): ?self {
		if ( null === $row || ! isset( $row['authority'] ) || ! is_string( $row['authority'] ) || '' === $row['authority'] ) {
			return null;
		}

		$version = isset( $row['version'] ) && is_string( $row['version'] ) ? $row['version'] : '';
		$reference = isset( $row['reference'] ) && is_string( $row['reference'] ) ? $row['reference'] : null;
		$review    = isset( $row['last_legal_review'] ) && is_string( $row['last_legal_review'] )
			? $row['last_legal_review']
			: null;

		return new self(
			authority: $row['authority'],
			version: $version,
			reference: $reference,
			last_legal_review: $review,
		);
	}

	/**
	 * @return array<string, string|null>
	 */
	public function to_array(): array {
		return array(
			'authority'         => $this->authority,
			'version'           => $this->version,
			'reference'         => $this->reference,
			'last_legal_review' => $this->last_legal_review,
		);
	}
}
