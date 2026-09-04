<?php

declare(strict_types=1);

namespace ComplyOps\Detection;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classifies unknown third-party scripts and embeds for administrator review.
 */
final class ThirdPartyDiscoveryService {

	public function __construct(
		private readonly HtmlScanner $scanner = new HtmlScanner(),
	) {
	}

	/**
	 * @param list<string>               $scripts
	 * @param list<string>               $iframes
	 * @param list<string>               $known_domains
	 * @param array<string, mixed>       $integrations
	 * @return array<string, mixed>
	 */
	public function discover( array $scripts, array $iframes, array $known_domains, array $integrations ): array {
		$sources       = array_merge( $scripts, $iframes );
		$unknown_hosts = $this->scanner->unknown_external_hosts( $sources, $known_domains );
		$review_items  = array();

		foreach ( $unknown_hosts as $host ) {
			$review_items[] = array(
				'host'           => $host,
				'classification' => 'unknown',
				'status'         => 'review',
				'sources'        => $this->sources_for_host( $sources, $host ),
				'message'        => __( 'Unknown third-party service detected; administrator review required.', 'complyops' ),
			);
		}

		$external_forms = $this->scanner->extract_external_form_actions( '', $known_domains );

		return array(
			'unknown_hosts'    => $unknown_hosts,
			'review_items'     => $review_items,
			'review_count'     => count( $review_items ),
			'external_forms'   => $external_forms,
			'detected_services'=> $this->detected_service_labels( $integrations ),
		);
	}

	/**
	 * @param list<string>         $sources
	 * @param array<string, mixed> $integrations
	 * @return list<string>
	 */
	private function detected_service_labels( array $integrations ): array {
		$labels = array();

		foreach ( $integrations as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['detected'] ) ) {
				continue;
			}

			$labels[] = (string) ( $entry['label'] ?? 'Unknown service' );
		}

		return array_values( array_unique( $labels ) );
	}

	/**
	 * @param list<string> $sources
	 * @return list<string>
	 */
	private function sources_for_host( array $sources, string $host ): array {
		$matched = array();

		foreach ( $sources as $source ) {
			$source_host = wp_parse_url( $source, PHP_URL_HOST );

			if ( is_string( $source_host ) && strcasecmp( $source_host, $host ) === 0 ) {
				$matched[] = $source;
			}
		}

		return array_values( array_unique( $matched ) );
	}
}
