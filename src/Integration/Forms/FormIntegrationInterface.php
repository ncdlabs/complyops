<?php

declare(strict_types=1);

namespace ComplyOps\Integration\Forms;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for form plugin inventory adapters.
 */
interface FormIntegrationInterface {

	public function id(): string;

	public function label(): string;

	/**
	 * @param list<string> $active_plugin_slugs
	 */
	public function is_active( array $active_plugin_slugs ): bool;

	/**
	 * @return list<array<string, mixed>>
	 */
	public function inventory(): array;
}
