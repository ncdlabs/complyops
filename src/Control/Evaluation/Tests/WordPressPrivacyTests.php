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
 * WordPress privacy tooling and policy configuration.
 */
final class WordPressPrivacyTests extends AbstractSharedTests {

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'wp.privacy_policy_page'      => array( $this, 'privacy_policy_page' ),
			'wp.export_tools_available'     => array( $this, 'export_tools_available' ),
			'wp.erasure_tools_available'    => array( $this, 'erasure_tools_available' ),
			'wp.export_erasure_available'   => array( $this, 'export_erasure_available' ),
			'wp.comment_privacy'            => array( $this, 'comment_privacy' ),
			'privacy.rights_mechanisms_discoverable' => array( $this, 'rights_mechanisms_discoverable' ),
		);
	}

	public function privacy_policy_page( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$page_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );

		if ( $page_id <= 0 ) {
			return $this->fail(
				$definition,
				__( 'No Privacy Policy page is assigned in WordPress settings.', 'complyops' ),
				__( 'A published Privacy Policy page is assigned under Settings → Privacy.', 'complyops' ),
			);
		}

		$status = get_post_status( $page_id );

		if ( 'publish' !== $status ) {
			return $this->fail(
				$definition,
				sprintf(
					/* translators: %s: post status */
					__( 'Assigned Privacy Policy page exists but is not published (status: %s).', 'complyops' ),
					(string) $status
				),
				__( 'A published Privacy Policy page is assigned under Settings → Privacy.', 'complyops' ),
			);
		}

		return $this->pass(
			$definition,
			__( 'A published Privacy Policy page is assigned in WordPress settings.', 'complyops' ),
			__( 'A published Privacy Policy page is assigned under Settings → Privacy.', 'complyops' ),
		);
	}

	public function export_tools_available( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		return $this->pass(
			$definition,
			__( 'WordPress core personal data export tools are available.', 'complyops' ),
			__( 'Personal data export functionality is available.', 'complyops' ),
		);
	}

	public function erasure_tools_available( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		return $this->pass(
			$definition,
			__( 'WordPress core personal data erasure tools are available.', 'complyops' ),
			__( 'Personal data erasure functionality is available.', 'complyops' ),
		);
	}

	public function export_erasure_available( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );

		$core_only = count( $exporters ) <= 1 && count( $erasers ) <= 1;

		if ( $core_only ) {
			return $this->unknown(
				$definition,
				__( 'Only WordPress core export/erasure hooks are registered; plugin data exporters may be missing.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->pass(
			$definition,
			sprintf(
				/* translators: 1: exporter count, 2: eraser count */
				__( 'Registered %1$d data exporters and %2$d erasers including plugin integrations.', 'complyops' ),
				count( $exporters ),
				count( $erasers )
			),
			$definition->recommended_value,
		);
	}

	public function comment_privacy( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$registration = (bool) get_option( 'comment_registration', false );
		$avatars      = (bool) get_option( 'show_avatars', true );
		$status       = (string) get_option( 'default_comment_status', 'open' );
		$issues       = array();

		if ( ! $registration ) {
			$issues[] = __( 'Guest commenting is allowed without registration.', 'complyops' );
		}

		if ( $avatars ) {
			$issues[] = __( 'Gravatars are enabled for comments.', 'complyops' );
		}

		if ( 'open' === $status ) {
			$issues[] = __( 'New posts allow comments by default.', 'complyops' );
		}

		if ( array() === $issues ) {
			return $this->pass(
				$definition,
				__( 'Comment privacy settings require registration, disable gravatars, and do not open comments by default.', 'complyops' ),
				$definition->recommended_value ?? __( 'Comment privacy behavior reviewed and documented.', 'complyops' ),
			);
		}

		return $this->unknown(
			$definition,
			implode( ' ', $issues ),
			$definition->recommended_value ?? __( 'Comment privacy behavior reviewed and documented.', 'complyops' ),
		);
	}

	public function rights_mechanisms_discoverable( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$page_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );

		if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
			return $this->fail(
				$definition,
				__( 'Privacy policy page is not published; data-subject rights routes may not be discoverable.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'Privacy policy page exists; verify it documents access, correction, erasure, restriction, and objection routes.', 'complyops' ),
			$definition->recommended_value,
		);
	}
}
