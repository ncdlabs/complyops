<?php

declare(strict_types=1);

namespace ComplyOps\Framework;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlRegistry;

/**
 * Contract for a compliance framework (e.g. GDPR).
 */
interface FrameworkInterface {

	public function id(): string;

	public function label(): string;

	public function register_controls( ControlRegistry $registry ): void;
}
