<?php

declare(strict_types=1);

namespace ComplyOps\Remediation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for a safe automatic remediation handler.
 */
interface RemediationHandlerInterface {

	public function id(): string;

	public function label(): string;

	public function description(): string;

	/**
	 * @return list<string>
	 */
	public function control_ids(): array;

	/**
	 * Shared test keys handled by this remediation (derived from controls by default).
	 *
	 * @return list<string>
	 */
	public function test_keys(): array;

	/**
	 * @return array<string, mixed>
	 */
	public function capture_state(): array;

	/**
	 * @return array<string, mixed>
	 */
	public function apply(): array;

	/**
	 * @param array<string, mixed> $before_state
	 */
	public function rollback( array $before_state ): void;

	/**
	 * @return list<array{key: string, before: mixed, after: mixed, label: string}>
	 */
	public function preview_changes(): array;
}
