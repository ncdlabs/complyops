<?php

declare(strict_types=1);

namespace ComplyOps\Evidence;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How a control was evaluated when evidence was captured.
 */
enum EvidenceTestMethod: string {

	case Static = 'static';
	case Runtime = 'runtime';
	case Api    = 'api';
	case Manual = 'manual';
}
