<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation\Tests;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\Evaluation\AbstractControlEvaluator;
use ComplyOps\Control\Evaluation\EvaluationContext;

/**
 * HTTPS and transport security checks.
 */
final class TransportSecurityTests extends AbstractSharedTests {

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'transport.https_enabled' => array( $this, 'https_enabled' ),
		);
	}

	public function https_enabled( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$home    = (string) get_option( 'home', '' );
		$siteurl = (string) get_option( 'siteurl', '' );

		$home_https    = str_starts_with( $home, 'https://' );
		$siteurl_https = str_starts_with( $siteurl, 'https://' );

		if ( $home_https && $siteurl_https ) {
			return $this->pass(
				$definition,
				__( 'Site URL and Home URL are configured to use HTTPS.', 'complyops' ),
				__( 'Site is served over HTTPS.', 'complyops' ),
			);
		}

		return $this->fail(
			$definition,
			__( 'WordPress Home URL or Site URL is not configured for HTTPS.', 'complyops' ),
			__( 'Site is served over HTTPS.', 'complyops' ),
		);
	}
}
