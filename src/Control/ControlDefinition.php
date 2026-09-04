<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Evidence\EvidenceTestMethod;

/**
 * Immutable metadata describing a control in the catalog.
 */
final class ControlDefinition {

	/**
	 * @param list<string> $integrations
	 * @param list<string> $references
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $framework,
		public readonly string $title,
		public readonly string $description,
		public readonly string $rationale,
		public readonly string $category,
		public readonly ControlSeverity $severity,
		public readonly ControlCapability $capability,
		public readonly array $integrations = array(),
		public readonly ?string $recommended_value = null,
		public readonly ?string $manual_review_instructions = null,
		public readonly EvidenceTestMethod $test_method = EvidenceTestMethod::Static,
		public readonly array $references = array(),
		public readonly ?string $test_key = null,
		public readonly ControlVerificationType $verification_type = ControlVerificationType::Automatic,
		public readonly ?ControlSource $source = null,
		public readonly ControlApplicabilityConfig $applicability = new ControlApplicabilityConfig(),
		public readonly ?string $tsc = null,
	) {
	}
}
