<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of controls available to the compliance engine.
 */
final class ControlRegistry {

	/** @var array<string, ControlInterface> */
	private array $controls = array();

	public function register( ControlInterface $control ): void {
		$id = $control->definition()->id;

		$this->controls[ $id ] = $control;
	}

	/**
	 * @return list<ControlInterface>
	 */
	public function all(): array {
		return array_values( $this->controls );
	}

	public function get( string $id ): ?ControlInterface {
		return $this->controls[ $id ] ?? null;
	}

	/**
	 * @return list<ControlDefinition>
	 */
	public function definitions(): array {
		return array_map(
			static fn ( ControlInterface $control ): ControlDefinition => $control->definition(),
			$this->all()
		);
	}
}
