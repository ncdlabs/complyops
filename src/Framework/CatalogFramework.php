<?php

declare(strict_types=1);

namespace ComplyOps\Framework;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlCatalog;
use ComplyOps\Control\ControlRegistry;

/**
 * Compliance framework backed by an on-disk control catalog JSON file.
 */
final class CatalogFramework implements FrameworkInterface {

	public function __construct(
		private readonly string $framework_id,
		private readonly string $framework_label,
		private readonly string $catalog_path,
	) {
	}

	public function id(): string {
		return $this->framework_id;
	}

	public function label(): string {
		return $this->framework_label;
	}

	public function catalog_path(): string {
		return $this->catalog_path;
	}

	public function register_controls( ControlRegistry $registry ): void {
		ControlCatalog::from_file( $this->catalog_path )->register( $registry );
	}
}
