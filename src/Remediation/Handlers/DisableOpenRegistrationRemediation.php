<?php

declare(strict_types=1);

namespace ComplyOps\Remediation\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Remediation\AbstractRemediationHandler;

/**
 * Disables open WordPress user registration.
 */
final class DisableOpenRegistrationRemediation extends AbstractRemediationHandler {

	public function id(): string {
		return 'disable_open_registration';
	}

	public function label(): string {
		return __( 'Disable open user registration', 'complyops' );
	}

	public function description(): string {
		return __( 'Turns off public user registration in WordPress settings.', 'complyops' );
	}

	public function control_ids(): array {
		return array(
			'GDPR-WP-004',
		);
	}

	public function capture_state(): array {
		return array(
			'users_can_register' => (bool) get_option( 'users_can_register', false ),
		);
	}

	public function apply(): array {
		update_option( 'users_can_register', false, false );

		return array(
			'users_can_register' => false,
		);
	}

	public function rollback( array $before_state ): void {
		update_option(
			'users_can_register',
			! empty( $before_state['users_can_register'] ),
			false
		);
	}

	public function preview_changes(): array {
		return array(
			array(
				'key'    => 'users_can_register',
				'label'  => __( 'Anyone can register', 'complyops' ),
				'before' => (bool) get_option( 'users_can_register', false ),
				'after'  => false,
			),
		);
	}
}
