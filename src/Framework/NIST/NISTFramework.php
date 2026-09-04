<?php

declare(strict_types=1);

namespace ComplyOps\Framework\NIST;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlCatalog;
use ComplyOps\Control\ControlRegistry;
use ComplyOps\Framework\FrameworkInterface;

/**
 * NIST CSF 2.0 technical readiness framework (free / built-in).
 */
final class NISTFramework implements FrameworkInterface {

	private const CATALOG_FILE = 'data/controls/nist-csf.json';

	public function id(): string {
		return 'nist-csf';
	}

	public function label(): string {
		return 'NIST CSF 2.0';
	}

	public function register_controls( ControlRegistry $registry ): void {
		$path = COMPLYOPS_PLUGIN_DIR . self::CATALOG_FILE;

		ControlCatalog::from_file( $path )->register( $registry );
	}
}
