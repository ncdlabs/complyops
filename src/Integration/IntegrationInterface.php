<?php

declare(strict_types=1);

namespace ComplyOps\Integration;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for a third-party service integration.
 */
interface IntegrationInterface {

	public function id(): string;

	public function label(): string;

	/**
	 * Consent category required before this integration may run.
	 */
	public function consent_category(): string;

	/**
	 * @return list<string> URL/host patterns used to identify scripts.
	 */
	public function script_patterns(): array;

	public function is_detected( array $discovery ): bool;
}
