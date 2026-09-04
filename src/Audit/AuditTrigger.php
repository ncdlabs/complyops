<?php

declare(strict_types=1);

namespace ComplyOps\Audit;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How an audit run was initiated.
 */
enum AuditTrigger: string {
	case Manual    = 'manual';
	case Scheduled = 'scheduled';
	case Wizard    = 'wizard';
}
