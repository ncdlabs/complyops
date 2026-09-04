<?php

declare(strict_types=1);

namespace ComplyOps\Remediation;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Remediation\Handlers\AssignPrivacyPolicyPageRemediation;
use ComplyOps\Remediation\Handlers\DisableOpenRegistrationRemediation;
use ComplyOps\Remediation\Handlers\EnableEnforcementRemediation;
use ComplyOps\Remediation\Handlers\EnableEvidenceRetentionRemediation;
use ComplyOps\Remediation\Handlers\EnableNativeConsentRemediation;
use ComplyOps\Remediation\Handlers\EnablePiiFilteringRemediation;
use ComplyOps\Remediation\Handlers\EnableYoutubeGateRemediation;
use ComplyOps\Remediation\Handlers\HardenCommentPrivacyRemediation;
use ComplyOps\Remediation\Handlers\RestrictRestUsersRemediation;

use ComplyOps\Control\ControlTestKeyMap;

/**
 * Maps controls to remediation handlers.
 */
final class RemediationRegistry {

	/** @var array<string, RemediationHandlerInterface> */
	private array $handlers = array();

	/** @var array<string, list<string>> */
	private array $control_map = array();

	/** @var array<string, list<string>> */
	private array $test_key_map = array();

	public function __construct() {
		foreach ( $this->default_handlers() as $handler ) {
			$this->register( $handler );
		}
	}

	public function register( RemediationHandlerInterface $handler ): void {
		$this->handlers[ $handler->id() ] = $handler;

		foreach ( $handler->control_ids() as $control_id ) {
			if ( ! isset( $this->control_map[ $control_id ] ) ) {
				$this->control_map[ $control_id ] = array();
			}

			if ( ! in_array( $handler->id(), $this->control_map[ $control_id ], true ) ) {
				$this->control_map[ $control_id ][] = $handler->id();
			}

			$test_key = ControlTestKeyMap::for_control_id( $control_id );

			if ( null !== $test_key && '' !== $test_key ) {
				if ( ! isset( $this->test_key_map[ $test_key ] ) ) {
					$this->test_key_map[ $test_key ] = array();
				}

				if ( ! in_array( $handler->id(), $this->test_key_map[ $test_key ], true ) ) {
					$this->test_key_map[ $test_key ][] = $handler->id();
				}
			}
		}

		foreach ( $handler->test_keys() as $test_key ) {
			if ( '' === $test_key ) {
				continue;
			}

			if ( ! isset( $this->test_key_map[ $test_key ] ) ) {
				$this->test_key_map[ $test_key ] = array();
			}

			if ( ! in_array( $handler->id(), $this->test_key_map[ $test_key ], true ) ) {
				$this->test_key_map[ $test_key ][] = $handler->id();
			}
		}
	}

	public function get_handler( string $action_id ): ?RemediationHandlerInterface {
		return $this->handlers[ $action_id ] ?? null;
	}

	public function get_handler_for_control( string $control_id ): ?RemediationHandlerInterface {
		$handlers = $this->get_handlers_for_control( $control_id );

		return $handlers[0] ?? null;
	}

	public function get_handler_for_test_key( string $test_key ): ?RemediationHandlerInterface {
		$handlers = $this->get_handlers_for_test_key( $test_key );

		return $handlers[0] ?? null;
	}

	/**
	 * @return list<RemediationHandlerInterface>
	 */
	public function get_handlers_for_test_key( string $test_key ): array {
		$action_ids = $this->test_key_map[ $test_key ] ?? array();
		$handlers   = array();

		foreach ( $action_ids as $action_id ) {
			$handler = $this->get_handler( $action_id );

			if ( null !== $handler ) {
				$handlers[] = $handler;
			}
		}

		return $handlers;
	}

	/**
	 * @return list<RemediationHandlerInterface>
	 */
	public function get_handlers_for_control( string $control_id ): array {
		$action_ids = $this->control_map[ $control_id ] ?? array();
		$handlers   = array();

		foreach ( $action_ids as $action_id ) {
			$handler = $this->get_handler( $action_id );

			if ( null !== $handler ) {
				$handlers[] = $handler;
			}
		}

		return $handlers;
	}

	public function summary_for_control( string $control_id ): ?string {
		$summaries = array();

		foreach ( $this->get_handlers_for_control( $control_id ) as $handler ) {
			$summaries[] = $handler->description();
		}

		if ( array() === $summaries ) {
			return null;
		}

		return implode( ' ', $summaries );
	}

	/**
	 * @return list<RemediationHandlerInterface>
	 */
	public function all_handlers(): array {
		return array_values( $this->handlers );
	}

	/**
	 * @return list<RemediationHandlerInterface>
	 */
	private function default_handlers(): array {
		return array(
			new EnableNativeConsentRemediation(),
			new EnableEnforcementRemediation(),
			new EnableYoutubeGateRemediation(),
			new EnablePiiFilteringRemediation(),
			new EnableEvidenceRetentionRemediation(),
			new AssignPrivacyPolicyPageRemediation(),
			new HardenCommentPrivacyRemediation(),
			new DisableOpenRegistrationRemediation(),
			new RestrictRestUsersRemediation(),
		);
	}
}
