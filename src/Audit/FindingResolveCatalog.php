<?php

declare(strict_types=1);

namespace ComplyOps\Audit;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlCapability;
use ComplyOps\Control\ControlDefinition;
use ComplyOps\Remediation\RemediationRegistry;

/**
 * Maps findings to the admin screen where the issue can be addressed.
 */
final class FindingResolveCatalog {

	public function __construct(
		private readonly RemediationRegistry $registry = new RemediationRegistry(),
	) {
	}

	/**
	 * @param array<string, mixed> $finding
	 * @return array{url: string, label: string}|null
	 */
	public function resolve( ?ControlDefinition $definition, array $finding ): ?array {
		if ( null === $definition ) {
			return null;
		}

		$handler = $this->registry->get_handler_for_control( $definition->id );

		if ( null !== $handler ) {
			$url = $this->handler_admin_url( $handler->id(), $definition );

			if ( null !== $url ) {
				return $url;
			}
		}

		$integration_url = $this->integration_admin_url( $definition );

		if ( null !== $integration_url ) {
			return $integration_url;
		}

		return $this->fallback_admin_url( $definition, $finding );
	}

	/**
	 * @return array{url: string, label: string}|null
	 */
	private function handler_admin_url( string $handler_id, ControlDefinition $definition ): ?array {
		$pages = array(
			'enable_native_consent'       => array(
				'page'  => 'complyops-consent',
				'label' => __( 'Open Consent settings', 'complyops' ),
			),
			'enable_ga_enforcement'       => array(
				'page'  => 'complyops-integrations',
				'query' => array( 'pack' => $this->enforcement_pack_for_control( $definition->id ) ),
				'label' => __( 'Open Integrations', 'complyops' ),
			),
			'enable_youtube_gate'         => array(
				'page'  => 'complyops-integrations',
				'query' => array( 'pack' => 'youtube' ),
				'label' => __( 'Open YouTube settings', 'complyops' ),
			),
			'enable_pii_filtering'        => array(
				'page'  => 'complyops-integrations',
				'query' => array( 'pack' => 'google-analytics' ),
				'label' => __( 'Open Integrations', 'complyops' ),
			),
			'enable_evidence_retention'   => array(
				'page'  => 'complyops-evidence',
				'label' => __( 'Open Evidence', 'complyops' ),
			),
			'assign_privacy_policy_page'  => array(
				'page'  => 'options-privacy.php',
				'label' => __( 'Open Privacy settings', 'complyops' ),
			),
			'harden_comment_privacy'      => array(
				'page'  => 'options-discussion.php',
				'label' => __( 'Open Discussion settings', 'complyops' ),
			),
			'disable_open_registration'   => array(
				'page'  => 'options-general.php',
				'label' => __( 'Open General settings', 'complyops' ),
			),
			'restrict_rest_users'         => array(
				'page'  => 'complyops-integrations',
				'label' => __( 'Open Integrations', 'complyops' ),
			),
		);

		if ( ! isset( $pages[ $handler_id ] ) ) {
			return null;
		}

		$config = $pages[ $handler_id ];

		return array(
			'url'   => $this->admin_url( $config['page'], $config['query'] ?? array() ),
			'label' => $config['label'],
		);
	}

	/**
	 * @return array{url: string, label: string}|null
	 */
	private function integration_admin_url( ControlDefinition $definition ): ?array {
		$integrations = array_map( 'strtolower', $definition->integrations );

		if ( in_array( 'consent', $integrations, true ) ) {
			return array(
				'url'   => $this->admin_url( 'complyops-consent' ),
				'label' => __( 'Open Consent settings', 'complyops' ),
			);
		}

		if ( in_array( 'google_tag_manager', $integrations, true ) ) {
			return array(
				'url'   => $this->admin_url( 'complyops-integrations', array( 'pack' => 'google-tag-manager' ) ),
				'label' => __( 'Open GTM settings', 'complyops' ),
			);
		}

		if ( in_array( 'google_analytics', $integrations, true ) || in_array( 'pii', $integrations, true ) ) {
			return array(
				'url'   => $this->admin_url( 'complyops-integrations', array( 'pack' => 'google-analytics' ) ),
				'label' => __( 'Open Google Analytics settings', 'complyops' ),
			);
		}

		if ( in_array( 'youtube', $integrations, true ) || in_array( 'embeds', $integrations, true ) ) {
			return array(
				'url'   => $this->admin_url( 'complyops-integrations', array( 'pack' => 'youtube' ) ),
				'label' => __( 'Open YouTube settings', 'complyops' ),
			);
		}

		if ( in_array( 'forms', $integrations, true ) ) {
			return array(
				'url'   => $this->admin_url( 'complyops-integrations', array( 'pack' => 'forms' ) ),
				'label' => __( 'Open Forms inventory', 'complyops' ),
			);
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $finding
	 * @return array{url: string, label: string}|null
	 */
	private function fallback_admin_url( ControlDefinition $definition, array $finding ): ?array {
		$control_id = strtoupper( $definition->id );
		$framework  = sanitize_key( (string) ( $finding['framework'] ?? $definition->framework ) );

		if ( str_starts_with( $control_id, 'GDPR-WP-001' ) ) {
			return array(
				'url'   => $this->admin_url( 'options-privacy.php' ),
				'label' => __( 'Open Privacy settings', 'complyops' ),
			);
		}

		if ( str_starts_with( $control_id, 'GDPR-WP-004' ) ) {
			return array(
				'url'   => $this->admin_url( 'options-general.php' ),
				'label' => __( 'Open General settings', 'complyops' ),
			);
		}

		if ( str_starts_with( $control_id, 'GDPR-WP-005' ) ) {
			return array(
				'url'   => $this->admin_url( 'options-discussion.php' ),
				'label' => __( 'Open Discussion settings', 'complyops' ),
			);
		}

		if ( str_starts_with( $control_id, 'GDPR-RETENTION-' ) ) {
			return array(
				'url'   => $this->admin_url( 'complyops-evidence' ),
				'label' => __( 'Open Evidence', 'complyops' ),
			);
		}

		if ( in_array( $definition->capability, array( ControlCapability::ManualReview, ControlCapability::LegalReview ), true ) ) {
			return array(
				'url'   => $this->admin_url( 'complyops-controls', array( 'pack' => $framework ) ),
				'label' => __( 'Open control details', 'complyops' ),
			);
		}

		if ( 'wp' === $definition->category ) {
			return array(
				'url'   => $this->admin_url( 'options-general.php' ),
				'label' => __( 'Open WordPress settings', 'complyops' ),
			);
		}

		return array(
			'url'   => $this->admin_url( 'complyops-integrations' ),
			'label' => __( 'Open Integrations', 'complyops' ),
		);
	}

	private function enforcement_pack_for_control( string $control_id ): string {
		if ( str_contains( strtoupper( $control_id ), 'GTM' ) || str_starts_with( strtoupper( $control_id ), 'GDPR-TRACKING-002' ) ) {
			return 'google-tag-manager';
		}

		return 'google-analytics';
	}

	/**
	 * @param array<string, string> $query
	 */
	private function admin_url( string $page, array $query = array() ): string {
		if ( str_starts_with( $page, 'options-' ) || str_starts_with( $page, 'edit.php' ) ) {
			$url = admin_url( $page );
		} else {
			$url = admin_url( 'admin.php' );
			$query = array_merge( array( 'page' => $page ), $query );
		}

		if ( array() === $query ) {
			return $url;
		}

		return add_query_arg( $query, $url );
	}
}
