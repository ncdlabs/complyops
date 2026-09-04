<?php

declare(strict_types=1);

namespace ComplyOps\Framework\GDPR;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlCatalog;
use ComplyOps\Control\ControlRegistry;
use ComplyOps\Framework\FrameworkInterface;

/**
 * GDPR technical controls framework (MVP).
 */
final class GDPRFramework implements FrameworkInterface {

	private const CATALOG_FILE = 'data/controls/gdpr.json';

	public function id(): string {
		return 'gdpr';
	}

	public function label(): string {
		return 'GDPR';
	}

	public function register_controls( ControlRegistry $registry ): void {
		$path = COMPLYOPS_PLUGIN_DIR . self::CATALOG_FILE;

		ControlCatalog::from_file( $path )->register( $registry );
	}
}
