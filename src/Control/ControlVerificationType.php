<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a control is automatically verified, human-evidence, or legal review.
 */
enum ControlVerificationType: string {
	case Automatic = 'A';
	case Human       = 'H';
	case Legal       = 'L';

	public static function from_capability( ControlCapability $capability ): self {
		return match ( $capability ) {
			ControlCapability::LegalReview => self::Legal,
			ControlCapability::ManualReview,
			ControlCapability::Informational => self::Human,
			default => self::Automatic,
		};
	}
}
