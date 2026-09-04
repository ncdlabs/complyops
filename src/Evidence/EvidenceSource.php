<?php

declare(strict_types=1);

namespace ComplyOps\Evidence;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What event produced an evidence record.
 */
enum EvidenceSource: string {

	case Audit       = 'audit';
	case Remediation = 'remediation';
	case Drift       = 'drift';
	case Manual      = 'manual';
}
