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
 * Exposure and misconfiguration checks.
 */
final class ExposureTests extends AbstractSharedTests {

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'exposure.rest_user_enumeration'     => array( $this, 'rest_user_enumeration' ),
			'exposure.debug_mode_disabled'       => array( $this, 'debug_mode_disabled' ),
			'exposure.file_editor_disabled'      => array( $this, 'file_editor_disabled' ),
			'exposure.xmlrpc_disabled'           => array( $this, 'xmlrpc_disabled' ),
			'exposure.directory_indexing'        => array( $this, 'directory_indexing' ),
			'exposure.outbound_request_controls' => array( $this, 'outbound_request_controls' ),
			'exposure.private_content_leakage'   => array( $this, 'private_content_leakage' ),
		);
	}

	public function rest_user_enumeration( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		$public = $context->rest_users_public();

		if ( null === $public ) {
			return $this->unknown(
				$definition,
				__( 'REST user endpoint exposure could not be determined.', 'complyops' ),
				__( 'REST endpoints do not unnecessarily expose personal information.', 'complyops' ),
			);
		}

		if ( $public ) {
			$count = (int) ( $context->discovery['rest']['exposed_user_count'] ?? 0 );

			return $this->fail(
				$definition,
				sprintf(
					/* translators: %d: number of exposed users */
					__( 'WordPress REST /users endpoint is publicly accessible (%d users exposed).', 'complyops' ),
					$count
				),
				__( 'REST endpoints do not unnecessarily expose personal information.', 'complyops' ),
			);
		}

		return $this->pass(
			$definition,
			__( 'WordPress REST /users endpoint is not publicly accessible.', 'complyops' ),
			__( 'REST endpoints do not unnecessarily expose personal information.', 'complyops' ),
		);
	}

	public function debug_mode_disabled( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return $this->fail(
				$definition,
				__( 'WP_DEBUG is enabled; disable debug mode in production.', 'complyops' ),
				__( 'WP_DEBUG is false or undefined in production.', 'complyops' ),
			);
		}

		return $this->pass(
			$definition,
			__( 'WP_DEBUG is disabled.', 'complyops' ),
			__( 'WP_DEBUG is false or undefined in production.', 'complyops' ),
		);
	}

	public function file_editor_disabled( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
			return $this->pass(
				$definition,
				__( 'Theme and plugin file editing is disabled.', 'complyops' ),
				__( 'DISALLOW_FILE_EDIT is enabled or the file editor is otherwise disabled.', 'complyops' ),
			);
		}

		return $this->fail(
			$definition,
			__( 'Theme and plugin file editing is enabled in wp-admin.', 'complyops' ),
			__( 'DISALLOW_FILE_EDIT is enabled or the file editor is otherwise disabled.', 'complyops' ),
		);
	}

	public function xmlrpc_disabled( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		if ( defined( 'XMLRPC_REQUEST' ) ) {
			return $this->unknown(
				$definition,
				__( 'XML-RPC request context detected during evaluation.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		if ( has_filter( 'xmlrpc_enabled' ) ) {
			return $this->unknown(
				$definition,
				__( 'XML-RPC availability is modified by a filter; verify it is disabled or restricted.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'XML-RPC exposure requires external verification; disable if not required.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function directory_indexing( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		return $this->unknown(
			$definition,
			__( 'Directory indexing must be verified at the web server layer.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function outbound_request_controls( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		if ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL ) {
			return $this->pass(
				$definition,
				__( 'WP_HTTP_BLOCK_EXTERNAL is enabled.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'Outbound HTTP request restrictions were not detected; review SSRF controls for custom integrations.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function private_content_leakage( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		return $this->unknown(
			$definition,
			__( 'Private content exposure requires template and endpoint review.', 'complyops' ),
			$definition->recommended_value,
		);
	}
}
