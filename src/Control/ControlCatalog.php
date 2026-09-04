<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exceptions; messages escaped at REST/WP_Error boundaries.

use ComplyOps\Control\Evaluation\ApplicabilityGatedEvaluator;
use ComplyOps\Control\Evaluation\EvaluatorRegistry;
use ComplyOps\Evidence\EvidenceTestMethod;
use InvalidArgumentException;
use RuntimeException;

/**
 * Loads control definitions from JSON catalog files.
 */
final class ControlCatalog {

	/** @var list<CatalogControl> */
	private array $controls = array();

	private function __construct(
		public readonly string $framework,
		public readonly string $version,
	) {
	}

	public static function from_file( string $path, ?EvaluatorRegistry $registry = null ): self {
		if ( ! is_readable( $path ) ) {
			throw new RuntimeException(
				sprintf( 'Control catalog not readable: %s', $path )
			);
		}

		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			throw new RuntimeException(
				sprintf( 'Control catalog could not be read: %s', $path )
			);
		}

		/** @var array<string, mixed>|null $data */
		$data = json_decode( $contents, true );

		if ( ! is_array( $data ) ) {
			throw new RuntimeException(
				sprintf( 'Control catalog contains invalid JSON: %s', $path )
			);
		}

		return self::from_array( $data, $registry );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array( array $data, ?EvaluatorRegistry $registry = null ): self {
		$framework = isset( $data['framework'] ) && is_string( $data['framework'] )
			? $data['framework']
			: '';
		$version   = isset( $data['version'] ) && is_string( $data['version'] )
			? $data['version']
			: '0.0.0';

		if ( '' === $framework ) {
			throw new InvalidArgumentException( 'Control catalog requires a framework identifier.' );
		}

		if ( ! isset( $data['controls'] ) || ! is_array( $data['controls'] ) ) {
			throw new InvalidArgumentException( 'Control catalog requires a controls array.' );
		}

		$catalog  = new self( $framework, $version );
		$registry = $registry ?? new EvaluatorRegistry();

		foreach ( $data['controls'] as $index => $row ) {
			if ( ! is_array( $row ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Control catalog entry %d is not an object.', (int) $index )
				);
			}

			$definition = self::definition_from_array( $row, $framework );
			$evaluator  = new ApplicabilityGatedEvaluator(
				$registry->for_control( $definition )
			);

			$catalog->controls[] = new CatalogControl( $definition, $evaluator );
		}

		return $catalog;
	}

	public function register( ControlRegistry $registry ): void {
		foreach ( $this->controls as $control ) {
			$registry->register( $control );
		}
	}

	/**
	 * @return list<CatalogControl>
	 */
	public function controls(): array {
		return $this->controls;
	}

	public function count(): int {
		return count( $this->controls );
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private static function definition_from_array( array $row, string $framework ): ControlDefinition {
		$required = array( 'id', 'title', 'description', 'rationale', 'category', 'severity', 'capability' );

		foreach ( $required as $field ) {
			if ( ! isset( $row[ $field ] ) || ! is_string( $row[ $field ] ) || '' === $row[ $field ] ) {
				throw new InvalidArgumentException(
					sprintf( 'Control catalog entry is missing required field: %s', $field )
				);
			}
		}

		/** @var list<string> $integrations */
		$integrations = array();

		if ( isset( $row['integrations'] ) ) {
			if ( ! is_array( $row['integrations'] ) ) {
				throw new InvalidArgumentException( 'Control integrations must be an array of strings.' );
			}

			foreach ( $row['integrations'] as $integration ) {
				if ( ! is_string( $integration ) || '' === $integration ) {
					throw new InvalidArgumentException( 'Control integrations must be an array of strings.' );
				}

				$integrations[] = $integration;
			}
		}

		$recommended = isset( $row['recommended_value'] ) && is_string( $row['recommended_value'] )
			? $row['recommended_value']
			: null;
		$manual      = isset( $row['manual_review_instructions'] ) && is_string( $row['manual_review_instructions'] )
			? $row['manual_review_instructions']
			: null;
		$test_method = EvidenceTestMethod::Static;
		$references  = self::references_from_array( $row );

		if ( isset( $row['test_method'] ) && is_string( $row['test_method'] ) && '' !== $row['test_method'] ) {
			$test_method = EvidenceTestMethod::from( strtolower( $row['test_method'] ) );
		}

		$control_id = (string) $row['id'];
		$capability   = ControlCapability::from( strtoupper( (string) $row['capability'] ) );
		$test_key     = isset( $row['test_key'] ) && is_string( $row['test_key'] ) && '' !== $row['test_key']
			? $row['test_key']
			: ControlTestKeyMap::for_control_id( $control_id );

		$verification_type = ControlVerificationType::Automatic;

		if ( isset( $row['verification_type'] ) && is_string( $row['verification_type'] ) && '' !== $row['verification_type'] ) {
			$verification_type = ControlVerificationType::from( strtoupper( $row['verification_type'] ) );
		} else {
			$verification_type = ControlVerificationType::from_capability( $capability );
		}

		$source = ControlSource::from_array(
			isset( $row['source'] ) && is_array( $row['source'] ) ? $row['source'] : null
		);

		$applicability = ControlApplicabilityConfig::from_array(
			isset( $row['applicability'] ) && is_array( $row['applicability'] ) ? $row['applicability'] : null
		);

		$tsc = isset( $row['tsc'] ) && is_string( $row['tsc'] ) && '' !== trim( $row['tsc'] )
			? sanitize_key( $row['tsc'] )
			: null;

		return new ControlDefinition(
			id: $control_id,
			framework: $framework,
			title: (string) $row['title'],
			description: (string) $row['description'],
			rationale: (string) $row['rationale'],
			category: (string) $row['category'],
			severity: ControlSeverity::from( strtoupper( (string) $row['severity'] ) ),
			capability: $capability,
			integrations: $integrations,
			recommended_value: $recommended,
			manual_review_instructions: $manual,
			test_method: $test_method,
			references: $references,
			test_key: $test_key,
			verification_type: $verification_type,
			source: $source,
			applicability: $applicability,
			tsc: $tsc,
		);
	}

	/**
	 * Normalizes current references plus legacy pack reference fields.
	 *
	 * @param array<string, mixed> $row
	 * @return list<string>
	 */
	private static function references_from_array( array $row ): array {
		$references = array();

		if ( isset( $row['references'] ) ) {
			if ( ! is_array( $row['references'] ) ) {
				throw new InvalidArgumentException( 'Control references must be an array of strings.' );
			}

			foreach ( $row['references'] as $reference ) {
				if ( ! is_string( $reference ) || '' === trim( $reference ) ) {
					throw new InvalidArgumentException( 'Control references must be an array of strings.' );
				}

				$references[] = trim( $reference );
			}
		}

		foreach ( array( 'cc_reference', 'gdpr_reference', 'hipaa_reference', 'owasp_reference' ) as $legacy_field ) {
			if ( isset( $row[ $legacy_field ] ) && is_string( $row[ $legacy_field ] ) && '' !== trim( $row[ $legacy_field ] ) ) {
				$references[] = trim( $row[ $legacy_field ] );
			}
		}

		if ( array() === $references && isset( $row['id'] ) && is_string( $row['id'] ) ) {
			$references = self::default_references_for_control( $row['id'] );
		}

		return array_values( array_unique( $references ) );
	}

	/**
	 * Supplies traceability for the original GDPR technical catalog, whose
	 * schema predates per-control references.
	 *
	 * @return list<string>
	 */
	private static function default_references_for_control( string $control_id ): array {
		return match ( true ) {
			str_starts_with( $control_id, 'GDPR-CONSENT-' ) => array( 'GDPR Articles 4(11), 6, 7', 'ePrivacy Directive Article 5(3)' ),
			str_starts_with( $control_id, 'GDPR-TRACKING-' ),
			str_starts_with( $control_id, 'GDPR-GA-' ),
			str_starts_with( $control_id, 'GDPR-EMBED-' ) => array( 'GDPR Articles 5, 6, 7, 25', 'ePrivacy Directive Article 5(3)' ),
			'GDPR-WP-001' === $control_id => array( 'GDPR Articles 12-14' ),
			'GDPR-WP-002' === $control_id => array( 'GDPR Article 15' ),
			'GDPR-WP-003' === $control_id => array( 'GDPR Article 17' ),
			str_starts_with( $control_id, 'GDPR-FORM-' ) => array( 'GDPR Articles 5, 6, 9, 13, 25' ),
			str_starts_with( $control_id, 'GDPR-WP-' ),
			str_starts_with( $control_id, 'GDPR-PII-' ) => array( 'GDPR Articles 5, 25, 32' ),
			str_starts_with( $control_id, 'GDPR-RETENTION-' ) => array( 'GDPR Article 5(1)(e)' ),
			str_starts_with( $control_id, 'GDPR-SEC-' ) => array( 'GDPR Article 32' ),
			str_starts_with( $control_id, 'OWASP-' ) => array( 'OWASP Top 10 (2025)' ),
			default => array(),
		};
	}
}
