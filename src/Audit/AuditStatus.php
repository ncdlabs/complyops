<?php

declare(strict_types=1);

namespace ComplyOps\Audit;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lifecycle status of an audit run.
 */
enum AuditStatus: string {
	case Running   = 'running';
	case Completed = 'completed';
	case Failed    = 'failed';
}
