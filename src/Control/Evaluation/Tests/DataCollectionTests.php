<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation\Tests;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlCapability;
use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\Evaluation\AbstractControlEvaluator;
use ComplyOps\Control\Evaluation\EvaluationContext;

/**
 * Form and data collection inventory checks.
 */
final class DataCollectionTests extends AbstractSharedTests {

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'data.form_inventory'              => array( $this, 'form_inventory' ),
			'forms.marketing_consent_review'   => array( $this, 'marketing_consent_review' ),
			'forms.privacy_notice_at_collection' => array( $this, 'privacy_notice_at_collection' ),
			'forms.retention_review'           => array( $this, 'retention_review' ),
			'data.sensitive_fields_identified' => array( $this, 'sensitive_fields_identified' ),
			'data.ephi_inventory'              => array( $this, 'ephi_inventory' ),
		);
	}

	public function form_inventory( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_forms() ) {
			$observed = __( 'No supported form plugins were detected.', 'complyops' );

			if ( ControlCapability::Informational === $definition->capability ) {
				return $this->info(
					$definition,
					$observed,
					$definition->recommended_value,
				);
			}

			return $this->unknown(
				$definition,
				$observed,
				$definition->recommended_value,
			);
		}

		$count = $context->form_inventory_count();
		$ids   = $context->discovery['forms']['detected_ids'] ?? array();
		$ids   = is_array( $ids ) ? implode( ', ', $ids ) : '';

		if ( $count > 0 ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: 1: number of forms, 2: plugin ids */
					__( 'Inventoried %1$d forms from plugins: %2$s.', 'complyops' ),
					$count,
					$ids
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: %s: form plugin ids */
				__( 'Form plugins detected (%s); field-level inventory is pending.', 'complyops' ),
				$ids
			),
			$definition->recommended_value,
		);
	}

	public function marketing_consent_review( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_forms() ) {
			return $this->not_applicable(
				$definition,
				__( 'No supported form plugins were detected.', 'complyops' )
			);
		}

		$summary = $context->form_inventory_summary();
		$with    = (int) ( $summary['forms_with_marketing_consent'] ?? 0 );
		$total   = max( 1, $context->form_inventory_count() );

		if ( 0 === $with ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %d: total forms inventoried */
					__( 'None of the %d inventoried forms include marketing consent fields.', 'complyops' ),
					$total
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: 1: forms with marketing fields, 2: total forms inventoried */
				__( '%1$d of %2$d inventoried forms include marketing consent fields; manual review is required to confirm they are not bundled with necessary processing.', 'complyops' ),
				$with,
				$total
			),
			$definition->manual_review_instructions ?? $definition->recommended_value,
		);
	}

	public function privacy_notice_at_collection( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_forms() ) {
			return $this->not_applicable(
				$definition,
				__( 'No supported form plugins were detected.', 'complyops' )
			);
		}

		$summary = $context->form_inventory_summary();
		$with    = (int) ( $summary['forms_with_privacy_acknowledgement'] ?? 0 );
		$total   = $context->form_inventory_count();

		if ( $total > 0 && $with === $total ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %d: number of forms */
					__( 'All %d inventoried forms include privacy or consent acknowledgement fields.', 'complyops' ),
					$total
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: 1: forms with privacy fields, 2: total forms */
				__( '%1$d of %2$d inventoried forms include privacy or consent acknowledgement fields; manual review is required.', 'complyops' ),
				$with,
				$total
			),
			$definition->manual_review_instructions ?? $definition->recommended_value,
		);
	}

	public function retention_review( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_forms() ) {
			return $this->not_applicable(
				$definition,
				__( 'No supported form plugins were detected.', 'complyops' )
			);
		}

		return $this->unknown(
			$definition,
			$definition->manual_review_instructions ?? __( 'Review retention settings in supported form plugins and document periods.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function sensitive_fields_identified( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_forms() ) {
			return $this->not_applicable(
				$definition,
				__( 'No supported form plugins were detected.', 'complyops' )
			);
		}

		if ( ! $context->forms_have_sensitive_fields() ) {
			return $this->pass(
				$definition,
				__( 'No sensitive form fields were flagged in the automated inventory.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		$sensitive_count = (int) ( $context->form_inventory_summary()['sensitive_field_count'] ?? 0 );

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: %d: number of sensitive fields */
				__( '%d sensitive form fields were flagged for review.', 'complyops' ),
				$sensitive_count
			),
			$definition->recommended_value,
		);
	}

	public function ephi_inventory( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		if ( ! $context->has_forms() ) {
			return $this->unknown(
				$definition,
				__( 'No form collection points inventoried; review uploads and custom fields for ePHI.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( $context->forms_have_sensitive_fields() ) {
			return $this->unknown(
				$definition,
				__( 'Sensitive or health-related form fields were detected; confirm whether ePHI is collected.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->pass(
			$definition,
			__( 'Automated form inventory did not flag obvious health or PHI field patterns.', 'complyops' ),
			$definition->recommended_value,
		);
	}
}
