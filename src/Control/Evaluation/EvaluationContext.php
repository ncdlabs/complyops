<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snapshot of site state available during control evaluation.
 */
final class EvaluationContext {

	/**
	 * @param array<string, mixed> $discovery
	 */
	public function __construct(
		public readonly array $discovery = array(),
	) {
	}

	public function has_integration( string $id ): bool {
		$integrations = $this->discovery['integrations'] ?? array();

		if ( ! is_array( $integrations ) || ! isset( $integrations[ $id ] ) ) {
			return false;
		}

		$entry = $integrations[ $id ];

		return is_array( $entry ) && ! empty( $entry['detected'] );
	}

	public function integration_label( string $id ): ?string {
		$integrations = $this->discovery['integrations'] ?? array();

		if ( ! is_array( $integrations ) || ! isset( $integrations[ $id ] ) ) {
			return null;
		}

		$entry = $integrations[ $id ];

		return is_array( $entry ) && isset( $entry['label'] ) ? (string) $entry['label'] : null;
	}

	public function consent_provider(): ?string {
		$consent = $this->discovery['consent'] ?? null;

		if ( ! is_array( $consent ) ) {
			return null;
		}

		$primary = $consent['primary'] ?? null;

		return is_string( $primary ) && '' !== $primary ? $primary : null;
	}

	public function has_consent_provider(): bool {
		$consent = $this->discovery['consent'] ?? null;

		return is_array( $consent ) && ! empty( $consent['detected_any'] );
	}

	public function has_forms(): bool {
		$forms = $this->discovery['forms'] ?? null;

		return is_array( $forms ) && ! empty( $forms['detected_any'] );
	}

	/**
	 * @return list<string>
	 */
	public function unknown_hosts(): array {
		$hosts = $this->discovery['unknown_hosts'] ?? array();

		return is_array( $hosts ) ? array_values( array_filter( $hosts, 'is_string' ) ) : array();
	}

	public function rest_users_public(): ?bool {
		$rest = $this->discovery['rest'] ?? null;

		if ( ! is_array( $rest ) || ! array_key_exists( 'users_endpoint_public', $rest ) ) {
			return null;
		}

		$value = $rest['users_endpoint_public'];

		return is_bool( $value ) ? $value : null;
	}

	public function native_consent_active(): bool {
		$native = $this->discovery['native_consent'] ?? null;

		return is_array( $native ) && ! empty( $native['active'] );
	}

	public function external_cmp_active(): bool {
		if ( ! $this->has_consent_provider() ) {
			return false;
		}

		$consent = $this->discovery['consent'] ?? array();
		$detected = is_array( $consent ) && isset( $consent['detected_ids'] ) && is_array( $consent['detected_ids'] )
			? $consent['detected_ids']
			: array();

		$external = array_values(
			array_filter(
				$detected,
				static fn ( string $id ): bool => 'complyops' !== $id
			)
		);

		return array() !== $external;
	}

	public function enforcement_active(): bool {
		$enforcement = $this->discovery['enforcement'] ?? null;

		return is_array( $enforcement ) && ! empty( $enforcement['enabled'] );
	}

	public function enforcement_consent_mode(): bool {
		$enforcement = $this->discovery['enforcement'] ?? null;

		return is_array( $enforcement ) && ! empty( $enforcement['consent_mode_enabled'] );
	}

	public function enforcement_youtube_gate(): bool {
		$enforcement = $this->discovery['enforcement'] ?? null;

		return is_array( $enforcement ) && ! empty( $enforcement['gate_youtube_before_consent'] );
	}

	public function ga_implementation_count(): int {
		$ga = $this->discovery['google_analytics'] ?? null;

		if ( ! is_array( $ga ) ) {
			return 0;
		}

		return (int) ( $ga['implementation_count'] ?? 0 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function form_inventory_summary(): array {
		$forms = $this->discovery['forms'] ?? array();

		if ( ! is_array( $forms ) ) {
			return array();
		}

		$inventory = $forms['inventory'] ?? array();

		return is_array( $inventory['summary'] ?? null ) ? $inventory['summary'] : array();
	}

	public function form_inventory_count(): int {
		return (int) ( $this->form_inventory_summary()['total_forms'] ?? 0 );
	}

	public function forms_have_sensitive_fields(): bool {
		return (int) ( $this->form_inventory_summary()['forms_with_sensitive_fields'] ?? 0 ) > 0;
	}

	public function third_party_review_count(): int {
		$third_party = $this->discovery['third_party'] ?? array();

		return is_array( $third_party ) ? (int) ( $third_party['review_count'] ?? 0 ) : 0;
	}

	public function pii_filtering_active(): bool {
		$pii = $this->discovery['pii'] ?? null;

		return is_array( $pii ) && ! empty( $pii['enabled'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function browser_verification(): array {
		$verification = $this->discovery['browser_verification'] ?? null;

		return is_array( $verification ) ? $verification : array();
	}

	public function browser_verification_available(): bool {
		return ! empty( $this->browser_verification()['available'] );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function browser_scenario( string $name ): ?array {
		$scenarios = $this->browser_verification()['scenarios'] ?? array();

		if ( ! is_array( $scenarios ) || ! isset( $scenarios[ $name ] ) || ! is_array( $scenarios[ $name ] ) ) {
			return null;
		}

		return $scenarios[ $name ];
	}

	public function browser_fresh_has_tracking(): bool {
		return $this->browser_scenario_flag( 'fresh_visitor', 'tracking_detected' );
	}

	public function browser_fresh_has_ga_cookie(): bool {
		return $this->browser_scenario_flag( 'fresh_visitor', 'ga_cookie_detected' );
	}

	public function browser_analytics_has_tracking(): bool {
		return $this->browser_scenario_flag( 'analytics_granted', 'tracking_detected' );
	}

	public function browser_youtube_gated(): bool {
		$scenario = $this->browser_scenario( 'fresh_visitor' );

		if ( null === $scenario ) {
			return false;
		}

		$gates    = (int) ( $scenario['youtube_gates'] ?? 0 );
		$iframes  = (int) ( $scenario['youtube_iframes'] ?? 0 );

		return $gates > 0 && 0 === $iframes;
	}

	/**
	 * @return list<string>
	 */
	public function browser_cookie_names( string $scenario = 'fresh_visitor' ): array {
		$snapshot = $this->browser_scenario( $scenario );

		if ( null === $snapshot ) {
			return array();
		}

		$names = $snapshot['cookie_names'] ?? array();

		if ( ! is_array( $names ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$names,
				static fn ( mixed $name ): bool => is_string( $name ) && '' !== $name
			)
		);
	}

	/**
	 * @return list<string>
	 */
	public function browser_pii_in_urls( string $scenario = 'analytics_granted' ): array {
		$snapshot = $this->browser_scenario( $scenario );

		if ( null === $snapshot ) {
			return array();
		}

		$matches = $snapshot['pii_in_urls'] ?? array();

		if ( ! is_array( $matches ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$matches,
				static fn ( mixed $url ): bool => is_string( $url ) && '' !== $url
			)
		);
	}

	private function browser_scenario_flag( string $scenario, string $flag ): bool {
		$snapshot = $this->browser_scenario( $scenario );

		if ( null === $snapshot ) {
			return false;
		}

		return ! empty( $snapshot[ $flag ] );
	}

	/**
	 * @return array<string, string>
	 */
	public function browser_security_headers(): array {
		$snapshot = $this->browser_scenario( 'fresh_visitor' );

		if ( null === $snapshot ) {
			return array();
		}

		$headers = $snapshot['security_headers'] ?? array();

		if ( ! is_array( $headers ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $headers as $key => $value ) {
			if ( ! is_string( $key ) || ! is_string( $value ) ) {
				continue;
			}

			$normalized[ strtolower( $key ) ] = $value;
		}

		return $normalized;
	}
}
