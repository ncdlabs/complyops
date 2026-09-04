<?php

declare(strict_types=1);

namespace ComplyOps\Framework;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlRegistry;

/**
 * Registry of enabled compliance frameworks.
 */
final class FrameworkRegistry {

	/** @var array<string, FrameworkInterface> */
	private array $frameworks = array();

	public function register( FrameworkInterface $framework ): void {
		$this->frameworks[ $framework->id() ] = $framework;
	}

	/**
	 * @return list<FrameworkInterface>
	 */
	public function all(): array {
		return array_values( $this->frameworks );
	}

	public function get( string $id ): ?FrameworkInterface {
		return $this->frameworks[ $id ] ?? null;
	}

	public function controls(): ControlRegistry {
		$registry = new ControlRegistry();

		foreach ( $this->frameworks as $framework ) {
			$framework->register_controls( $registry );
		}

		return $registry;
	}
}
