<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Severity assigned to a control or finding.
 */
enum ControlSeverity: string {
	case Info     = 'INFO';
	case Low      = 'LOW';
	case Medium   = 'MEDIUM';
	case High     = 'HIGH';
	case Critical = 'CRITICAL';
}
