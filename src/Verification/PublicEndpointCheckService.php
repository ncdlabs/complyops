<?php

declare(strict_types=1);

namespace ComplyOps\Verification;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks public endpoints via the hosted browser service (external traffic simulation).
 */
final class PublicEndpointCheckService implements PublicEndpointChecker {

	public function __construct(
		private readonly BrowserVerificationSettings $settings = new BrowserVerificationSettings(),
		private readonly RemoteBrowserVerificationClient $remote = new RemoteBrowserVerificationClient(),
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function check( string $url ): array {
		$target = BrowserScanTarget::resolve( $url );

		if ( null === $target ) {
			return $this->unavailable(
				__( 'Public endpoint URL is invalid or not allowed.', 'complyops' )
			);
		}

		if ( ! $this->settings->is_configured() ) {
			return $this->unavailable(
				__( 'Hosted browser verification is not configured. Public endpoint checks require the hosted browser service.', 'complyops' )
			);
		}

		$result = $this->remote->check_public_endpoint( $target );
		$result['mode'] = 'remote';

		return $result;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function unavailable( string $reason ): array {
		return array(
			'available'     => false,
			'captured_at'   => gmdate( 'c' ),
			'url'           => null,
			'http_status'   => null,
			'public'        => null,
			'exposed_count' => null,
			'method'        => 'hosted_browser',
			'mode'          => null,
			'error'         => $reason,
		);
	}
}
