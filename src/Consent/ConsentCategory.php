<?php

declare(strict_types=1);

namespace ComplyOps\Consent;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consent category identifiers.
 */
enum ConsentCategory: string {
	case Necessary      = 'necessary';
	case Preferences    = 'preferences';
	case Analytics      = 'analytics';
	case Marketing      = 'marketing';
	case ExternalMedia  = 'external_media';

	/**
	 * @return list<string>
	 */
	public static function ids(): array {
		return array_map(
			static fn ( self $category ): string => $category->value,
			self::cases()
		);
	}

	/**
	 * Default granted state for fresh visitors.
	 */
	public function default_granted(): bool {
		return self::Necessary === $this;
	}
}
