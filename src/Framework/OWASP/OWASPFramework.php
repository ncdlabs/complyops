<?php

declare(strict_types=1);

namespace ComplyOps\Framework\OWASP;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlCatalog;
use ComplyOps\Control\ControlRegistry;
use ComplyOps\Framework\FrameworkInterface;

/**
 * OWASP Top 10 technical controls framework (WordPress-focused).
 */
final class OWASPFramework implements FrameworkInterface {

	private const CATALOG_FILE = 'data/controls/owasp.json';

	public function id(): string {
		return 'owasp';
	}

	public function label(): string {
		return 'OWASP Top 10:2025';
	}

	public function register_controls( ControlRegistry $registry ): void {
		$path = COMPLYOPS_PLUGIN_DIR . self::CATALOG_FILE;

		ControlCatalog::from_file( $path )->register( $registry );
	}
}
