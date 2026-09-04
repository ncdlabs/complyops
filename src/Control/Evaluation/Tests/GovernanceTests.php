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
 * Manual, legal, and governance attestation checks.
 */
final class GovernanceTests extends AbstractSharedTests {

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'governance.manual_evidence'      => array( $this, 'manual_evidence' ),
			'governance.policy_evidence'      => array( $this, 'manual_evidence' ),
			'governance.threat_model_review'  => array( $this, 'manual_evidence' ),
			'encryption.at_rest_evidence'     => array( $this, 'manual_evidence' ),
			'integrity.unauthorized_alteration' => array( $this, 'manual_evidence' ),
			'transmission.email_ephi_flag'    => array( $this, 'email_ephi_flag' ),
		);
	}

	public function manual_evidence( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );

		if ( null !== $definition->manual_review_instructions ) {
			return $this->unknown(
				$definition,
				$definition->manual_review_instructions,
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'Human evidence or policy review is required before this control can pass.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function email_ephi_flag( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$smtp_plugins = array(
			'wp-mail-smtp/wp_mail_smtp.php',
			'easy-wp-smtp/easy-wp-smtp.php',
			'post-smtp/postman-smtp.php',
		);

		foreach ( $smtp_plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				return $this->unknown(
					$definition,
					__( 'SMTP plugin detected; confirm whether ePHI is transmitted via email and whether encryption is enforced.', 'complyops' ),
					$definition->recommended_value,
				);
			}
		}

		return $this->unknown(
			$definition,
			__( 'Review whether WordPress sends ePHI via unencrypted email notifications.', 'complyops' ),
			$definition->recommended_value,
		);
	}
}
