<?php

declare(strict_types=1);

namespace ComplyOps\Detection;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classifies form field names, labels, and types for privacy inventory.
 */
final class FormFieldAnalyzer {

	/** @var array<string, list<string>> */
	private const CATEGORY_PATTERNS = array(
		'email'   => array( 'email', 'e-mail', 'mail' ),
		'phone'   => array( 'phone', 'tel', 'mobile', 'telephone', 'cell' ),
		'address' => array( 'address', 'street', 'city', 'zip', 'postal', 'state', 'country' ),
		'name'    => array( 'name', 'firstname', 'first_name', 'lastname', 'last_name', 'fullname', 'full_name', 'surname' ),
	);

	/** @var list<string> */
	private const MARKETING_PATTERNS = array(
		'marketing',
		'newsletter',
		'opt-in',
		'optin',
		'subscribe',
		'promotional',
		'promo',
		'email updates',
	);

	/** @var list<string> */
	private const PRIVACY_PATTERNS = array(
		'privacy',
		'gdpr',
		'data protection',
		'consent',
		'terms',
		'policy',
	);

	/** @var list<string> */
	private const SENSITIVE_PATTERNS = array(
		'password',
		'passwd',
		'ssn',
		'social security',
		'health',
		'medical',
		'credit card',
		'creditcard',
		'card number',
		'cvv',
		'iban',
		'passport',
	);

	/**
	 * @return array<string, mixed>
	 */
	public function analyze_field( string $name, string $label = '', string $type = '' ): array {
		$haystack = strtolower( trim( $name . ' ' . $label . ' ' . $type ) );
		$categories = $this->match_categories( $haystack );

		$flags = array(
			'personal_data'           => array() !== $categories,
			'marketing_consent'       => $this->matches_any( $haystack, self::MARKETING_PATTERNS ),
			'privacy_acknowledgement' => $this->matches_any( $haystack, self::PRIVACY_PATTERNS ),
			'sensitive'               => $this->matches_any( $haystack, self::SENSITIVE_PATTERNS ),
		);

		return array(
			'name'       => $name,
			'label'      => $label,
			'type'       => $type,
			'categories' => $categories,
			'flags'      => $flags,
		);
	}

	/**
	 * @param list<array<string, mixed>> $fields
	 * @return array<string, mixed>
	 */
	public function summarize_form( array $fields ): array {
		$summary = array(
			'field_count'              => count( $fields ),
			'has_email'                => false,
			'has_phone'                => false,
			'has_address'              => false,
			'has_name'                 => false,
			'has_marketing_consent'    => false,
			'has_privacy_acknowledgement'=> false,
			'has_sensitive_fields'     => false,
			'sensitive_field_count'    => 0,
		);

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$categories = $field['categories'] ?? array();
			$flags      = $field['flags'] ?? array();

			if ( ! is_array( $categories ) || ! is_array( $flags ) ) {
				continue;
			}

			$summary['has_email']     = $summary['has_email'] || in_array( 'email', $categories, true );
			$summary['has_phone']     = $summary['has_phone'] || in_array( 'phone', $categories, true );
			$summary['has_address']   = $summary['has_address'] || in_array( 'address', $categories, true );
			$summary['has_name']      = $summary['has_name'] || in_array( 'name', $categories, true );
			$summary['has_marketing_consent'] = $summary['has_marketing_consent'] || ! empty( $flags['marketing_consent'] );
			$summary['has_privacy_acknowledgement'] = $summary['has_privacy_acknowledgement'] || ! empty( $flags['privacy_acknowledgement'] );

			if ( ! empty( $flags['sensitive'] ) ) {
				$summary['has_sensitive_fields']  = true;
				++$summary['sensitive_field_count'];
			}
		}

		return $summary;
	}

	/**
	 * @param list<array<string, mixed>> $forms
	 * @return array<string, mixed>
	 */
	public function summarize_inventory( array $forms ): array {
		$summary = array(
			'total_forms'                   => count( $forms ),
			'forms_with_email'              => 0,
			'forms_with_marketing_consent'  => 0,
			'forms_with_privacy_acknowledgement' => 0,
			'forms_with_sensitive_fields'   => 0,
			'sensitive_field_count'         => 0,
		);

		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) ) {
				continue;
			}

			$form_summary = $form['summary'] ?? array();

			if ( ! is_array( $form_summary ) ) {
				continue;
			}

			if ( ! empty( $form_summary['has_email'] ) ) {
				++$summary['forms_with_email'];
			}

			if ( ! empty( $form_summary['has_marketing_consent'] ) ) {
				++$summary['forms_with_marketing_consent'];
			}

			if ( ! empty( $form_summary['has_privacy_acknowledgement'] ) ) {
				++$summary['forms_with_privacy_acknowledgement'];
			}

			if ( ! empty( $form_summary['has_sensitive_fields'] ) ) {
				++$summary['forms_with_sensitive_fields'];
			}

			$summary['sensitive_field_count'] += (int) ( $form_summary['sensitive_field_count'] ?? 0 );
		}

		return $summary;
	}

	/**
	 * @return list<string>
	 */
	private function match_categories( string $haystack ): array {
		$matched = array();

		foreach ( self::CATEGORY_PATTERNS as $category => $patterns ) {
			if ( $this->matches_any( $haystack, $patterns ) ) {
				$matched[] = $category;
			}
		}

		return $matched;
	}

	/**
	 * @param list<string> $patterns
	 */
	private function matches_any( string $haystack, array $patterns ): bool {
		foreach ( $patterns as $pattern ) {
			if ( str_contains( $haystack, strtolower( $pattern ) ) ) {
				return true;
			}
		}

		return false;
	}
}
