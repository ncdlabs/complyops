<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Result status for a control evaluation.
 */
enum ControlStatus: string {
	case Pass           = 'PASS';
	case Fail           = 'FAIL';
	case Warning        = 'WARNING';
	case Info           = 'INFO';
	case Unknown        = 'UNKNOWN';
	case NotApplicable  = 'NOT_APPLICABLE';
}
