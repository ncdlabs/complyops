<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the platform can do for a given control.
 */
enum ControlCapability: string {
	case AutomaticVerify   = 'AUTOMATIC_VERIFY';
	case AutomaticRemediate = 'AUTOMATIC_REMEDIATE';
	case ManualReview      = 'MANUAL_REVIEW';
	case LegalReview       = 'LEGAL_REVIEW';
	case Informational     = 'INFORMATIONAL';
}
