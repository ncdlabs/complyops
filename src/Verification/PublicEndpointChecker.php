<?php

declare(strict_types=1);

namespace ComplyOps\Verification;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * External public endpoint probing (hosted browser service).
 */
interface PublicEndpointChecker {

	/**
	 * @return array<string, mixed>
	 */
	public function check( string $url ): array;
}
