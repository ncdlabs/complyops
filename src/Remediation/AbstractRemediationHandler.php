<?php

declare(strict_types=1);

namespace ComplyOps\Remediation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlTestKeyMap;

/**
 * Base remediation handler with shared test_key declarations.
 */
abstract class AbstractRemediationHandler implements RemediationHandlerInterface {

	/**
	 * @return list<string>
	 */
	public function test_keys(): array {
		$keys = array();

		foreach ( $this->control_ids() as $control_id ) {
			$test_key = ControlTestKeyMap::for_control_id( $control_id );

			if ( null !== $test_key && '' !== $test_key ) {
				$keys[] = $test_key;
			}
		}

		return array_values( array_unique( $keys ) );
	}
}
