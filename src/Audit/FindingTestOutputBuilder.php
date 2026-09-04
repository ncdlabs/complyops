<?php

declare(strict_types=1);

namespace ComplyOps\Audit;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\Evaluation\EvaluationContext;
use ComplyOps\Evidence\EvidenceTestMethod;

/**
 * Builds the raw technical output shown for a finding in the admin UI.
 */
final class FindingTestOutputBuilder {

	private const URL_LIST_LIMIT = 25;

	/**
	 * @param array<string, mixed>         $finding
	 * @param array<string, mixed>|null    $discovery
	 */
	public function build( array $finding, ?array $discovery, ?ControlDefinition $definition = null ): ?string {
		if ( null === $discovery ) {
			return null;
		}

		$control_id = (string) ( $finding['control_id'] ?? '' );
		$context    = new EvaluationContext( $discovery );
		$sections   = array();

		$test_method = $definition?->test_method ?? EvidenceTestMethod::Static;
		$sections[]  = sprintf(
			/* translators: %s: evaluation method */
			__( 'Test method: %s', 'complyops' ),
			$this->test_method_label( $test_method, $context )
		);

		$payload = $this->payload_for_control( $control_id, $context, $discovery );

		if ( array() === $payload ) {
			$category = (string) ( $finding['category'] ?? '' );
			$payload  = $this->payload_for_category( $category, $context, $discovery );
		}

		if ( array() === $payload ) {
			return implode( "\n", $sections );
		}

		$sections[] = $this->encode_payload( $payload );

		return implode( "\n\n", array_filter( $sections ) );
	}

	private function test_method_label( EvidenceTestMethod $method, EvaluationContext $context ): string {
		if ( EvidenceTestMethod::Runtime === $method || $context->browser_verification_available() ) {
			return __( 'runtime (browser verification)', 'complyops' );
		}

		return match ( $method ) {
			EvidenceTestMethod::Api    => __( 'api', 'complyops' ),
			EvidenceTestMethod::Manual => __( 'manual', 'complyops' ),
			default                    => __( 'static', 'complyops' ),
		};
	}

	/**
	 * @param array<string, mixed> $discovery
	 * @return array<string, mixed>
	 */
	private function payload_for_control( string $control_id, EvaluationContext $context, array $discovery ): array {
		if ( str_starts_with( $control_id, 'GDPR-CONSENT-' ) ) {
			return array(
				'native_consent' => $discovery['native_consent'] ?? null,
				'consent'        => $discovery['consent'] ?? null,
			);
		}

		if ( in_array( $control_id, array( 'GDPR-TRACKING-001', 'GDPR-TRACKING-002', 'GDPR-TRACKING-005' ), true )
			|| in_array( $control_id, array( 'GDPR-GA-001', 'GDPR-GA-002', 'GDPR-GA-003', 'GDPR-GA-004', 'GDPR-GA-005' ), true ) ) {
			return array(
				'enforcement'          => $discovery['enforcement'] ?? null,
				'google_analytics'     => $discovery['google_analytics'] ?? null,
				'google_tag_manager'   => $discovery['google_tag_manager'] ?? null,
				'browser_verification' => $this->browser_payload( $context, array( 'fresh_visitor' ) ),
			);
		}

		return match ( $control_id ) {
			'GDPR-TRACKING-003' => array(
				'unknown_hosts' => $this->limit_list( $context->unknown_hosts() ),
				'third_party'   => $discovery['third_party'] ?? null,
			),
			'GDPR-TRACKING-004' => array(
				'browser_verification' => $this->browser_payload( $context, array( 'fresh_visitor' ) ),
			),
			'GDPR-EMBED-001' => array(
				'enforcement'          => $discovery['enforcement'] ?? null,
				'browser_verification' => $this->browser_payload( $context, array( 'fresh_visitor' ) ),
			),
			'GDPR-EMBED-002' => array(
				'iframe_sources' => $this->limit_list(
					is_array( $discovery['html_scan']['iframe_sources'] ?? null )
						? $discovery['html_scan']['iframe_sources']
						: array()
				),
			),
			'GDPR-FORM-001', 'GDPR-FORM-002', 'GDPR-FORM-003', 'GDPR-FORM-004', 'GDPR-FORM-005' => array(
				'forms' => $discovery['forms'] ?? null,
			),
			'GDPR-PII-001', 'GDPR-PII-002', 'GDPR-PII-003', 'GDPR-PII-004' => array(
				'pii'                  => $discovery['pii'] ?? null,
				'browser_verification' => $this->browser_payload( $context, array( 'analytics_granted', 'fresh_visitor' ) ),
			),
			'GDPR-GA-009', 'GDPR-GA-010' => array(
				'google_analytics'     => $discovery['google_analytics'] ?? null,
				'browser_verification' => $this->browser_payload( $context, array( 'analytics_granted' ) ),
				'script_sources'       => $this->limit_list(
					is_array( $discovery['html_scan']['script_sources'] ?? null )
						? $discovery['html_scan']['script_sources']
						: array()
				),
			),
			'GDPR-GA-006', 'GDPR-GA-007', 'GDPR-GA-008', 'GDPR-RETENTION-001' => array(
				'google_analytics' => $discovery['google_analytics'] ?? null,
				'site_kit'         => $discovery['site_kit'] ?? null,
			),
			'GDPR-RETENTION-002', 'GDPR-RETENTION-003' => array(
				'forms'        => $discovery['forms'] ?? null,
				'native_consent' => $discovery['native_consent'] ?? null,
			),
			'GDPR-SEC-001', 'GDPR-SEC-002', 'GDPR-SEC-003', 'GDPR-SEC-004' => array(
				'wordpress' => $discovery['wordpress'] ?? null,
			),
			'GDPR-WP-006' => array(
				'rest' => $discovery['rest'] ?? null,
			),
			default => array(),
		};
	}

	/**
	 * @param array<string, mixed> $discovery
	 * @return array<string, mixed>
	 */
	private function payload_for_category( string $category, EvaluationContext $context, array $discovery ): array {
		return match ( $category ) {
			'consent' => array(
				'native_consent' => $discovery['native_consent'] ?? null,
				'consent'        => $discovery['consent'] ?? null,
			),
			'tracking', 'google_analytics' => array(
				'enforcement'          => $discovery['enforcement'] ?? null,
				'integrations'         => $this->integration_slice( $discovery ),
				'browser_verification' => $this->browser_payload( $context, array( 'fresh_visitor', 'analytics_granted' ) ),
			),
			'embedded_content' => array(
				'html_scan'            => $this->html_scan_slice( $discovery ),
				'browser_verification' => $this->browser_payload( $context, array( 'fresh_visitor' ) ),
			),
			'forms' => array(
				'forms' => $discovery['forms'] ?? null,
			),
			'wordpress' => array(
				'wordpress' => $discovery['wordpress'] ?? null,
				'rest'      => $discovery['rest'] ?? null,
			),
			'pii' => array(
				'pii'                  => $discovery['pii'] ?? null,
				'browser_verification' => $this->browser_payload( $context, array( 'analytics_granted' ) ),
			),
			'security' => array(
				'wordpress' => $discovery['wordpress'] ?? null,
			),
			default => array(
				'integrations' => $this->integration_slice( $discovery ),
			),
		};
	}

	/**
	 * @param list<string> $scenarios
	 * @return array<string, mixed>|null
	 */
	private function browser_payload( EvaluationContext $context, array $scenarios ): ?array {
		if ( ! $context->browser_verification_available() ) {
			$verification = $context->browser_verification();

			return array(
				'available' => false,
				'error'     => $verification['error'] ?? __( 'Browser verification was not available for this audit.', 'complyops' ),
			);
		}

		$verification = $context->browser_verification();
		$output       = array(
			'available'   => true,
			'mode'        => $verification['mode'] ?? null,
			'url'         => $verification['url'] ?? null,
			'captured_at' => $verification['captured_at'] ?? null,
			'scenarios'   => array(),
		);

		foreach ( $scenarios as $scenario ) {
			$snapshot = $context->browser_scenario( $scenario );

			if ( null === $snapshot ) {
				continue;
			}

			$output['scenarios'][ $scenario ] = $this->sanitize_browser_scenario( $snapshot );
		}

		return $output;
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<string, mixed>
	 */
	private function sanitize_browser_scenario( array $snapshot ): array {
		if ( isset( $snapshot['resource_urls'] ) && is_array( $snapshot['resource_urls'] ) ) {
			$snapshot['resource_urls'] = $this->limit_list( $snapshot['resource_urls'] );
		}

		if ( isset( $snapshot['script_sources'] ) && is_array( $snapshot['script_sources'] ) ) {
			$snapshot['script_sources'] = $this->limit_list( $snapshot['script_sources'] );
		}

		if ( isset( $snapshot['cookie_names'] ) && is_array( $snapshot['cookie_names'] ) ) {
			$snapshot['cookie_names'] = $this->limit_list( $snapshot['cookie_names'] );
		}

		if ( isset( $snapshot['pii_in_urls'] ) && is_array( $snapshot['pii_in_urls'] ) ) {
			$snapshot['pii_in_urls'] = $this->limit_list( $snapshot['pii_in_urls'] );
		}

		return $snapshot;
	}

	/**
	 * @param array<string, mixed> $discovery
	 * @return array<string, mixed>
	 */
	private function integration_slice( array $discovery ): array {
		$integrations = $discovery['integrations'] ?? array();

		if ( ! is_array( $integrations ) ) {
			return array();
		}

		$slice = array();

		foreach ( $integrations as $id => $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['detected'] ) ) {
				continue;
			}

			$slice[ (string) $id ] = array(
				'label'   => $entry['label'] ?? null,
				'summary' => $entry['summary'] ?? null,
				'sources' => isset( $entry['sources'] ) && is_array( $entry['sources'] )
					? $this->limit_list( $entry['sources'] )
					: null,
			);
		}

		return $slice;
	}

	/**
	 * @param array<string, mixed> $discovery
	 * @return array<string, mixed>
	 */
	private function html_scan_slice( array $discovery ): array {
		$html_scan = $discovery['html_scan'] ?? array();

		if ( ! is_array( $html_scan ) ) {
			return array();
		}

		return array(
			'url'            => $html_scan['url'] ?? null,
			'error'          => $html_scan['error'] ?? null,
			'script_count'   => $html_scan['script_count'] ?? null,
			'iframe_count'   => $html_scan['iframe_count'] ?? null,
			'script_sources' => $this->limit_list(
				is_array( $html_scan['script_sources'] ?? null ) ? $html_scan['script_sources'] : array()
			),
			'iframe_sources' => $this->limit_list(
				is_array( $html_scan['iframe_sources'] ?? null ) ? $html_scan['iframe_sources'] : array()
			),
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function encode_payload( array $payload ): string {
		$json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		return is_string( $json ) ? $json : '';
	}

	/**
	 * @param list<mixed> $items
	 * @return list<mixed>
	 */
	private function limit_list( array $items ): array {
		$items = array_values( $items );

		if ( count( $items ) <= self::URL_LIST_LIMIT ) {
			return $items;
		}

		return array_merge(
			array_slice( $items, 0, self::URL_LIST_LIMIT ),
			array(
				sprintf(
					/* translators: %d: number of omitted items */
					__( '… and %d more', 'complyops' ),
					count( $items ) - self::URL_LIST_LIMIT
				),
			)
		);
	}
}
