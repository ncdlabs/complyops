<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outcome of a single control evaluation.
 */
final class ControlResult {

	public function __construct(
		public readonly string $control_id,
		public readonly ControlStatus $status,
		public readonly ControlSeverity $severity,
		public readonly string $observed,
		public readonly ?string $expected = null,
		public readonly ?string $recommended = null,
		public readonly ?string $remediation_summary = null,
		public readonly bool $remediation_available = false,
	) {
	}

	public function to_array(): array {
		return array(
			'control_id'            => $this->control_id,
			'status'                => $this->status->value,
			'severity'              => $this->severity->value,
			'observed'              => $this->observed,
			'expected'              => $this->expected,
			'recommended'           => $this->recommended,
			'remediation_summary'   => $this->remediation_summary,
			'remediation_available' => $this->remediation_available,
		);
	}
}
