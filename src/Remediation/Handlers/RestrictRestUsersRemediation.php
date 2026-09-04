<?php

declare(strict_types=1);

namespace ComplyOps\Remediation\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Remediation\AbstractRemediationHandler;
use ComplyOps\WordPress\WordPressPrivacySettings;

/**
 * Restricts public access to the WordPress users REST endpoint.
 */
final class RestrictRestUsersRemediation extends AbstractRemediationHandler {

	public function __construct(
		private readonly WordPressPrivacySettings $settings = new WordPressPrivacySettings(),
	) {
	}

	public function id(): string {
		return 'restrict_rest_users';
	}

	public function label(): string {
		return __( 'Restrict REST user endpoint', 'complyops' );
	}

	public function description(): string {
		return __( 'Removes public access to the /wp/v2/users REST endpoint.', 'complyops' );
	}

	public function control_ids(): array {
		return array(
			'GDPR-WP-006',
		);
	}

	public function capture_state(): array {
		return array(
			'wordpress_privacy' => $this->settings->all(),
		);
	}

	public function apply(): array {
		$this->settings->save(
			array(
				'restrict_rest_users' => true,
			)
		);

		return array(
			'wordpress_privacy' => $this->settings->all(),
		);
	}

	public function rollback( array $before_state ): void {
		$privacy = $before_state['wordpress_privacy'] ?? null;

		if ( is_array( $privacy ) ) {
			$this->settings->save( $privacy );
		}
	}

	public function preview_changes(): array {
		return array(
			array(
				'key'    => 'wordpress_privacy.restrict_rest_users',
				'label'  => __( 'Restrict REST /users endpoint', 'complyops' ),
				'before' => $this->settings->restrict_rest_users(),
				'after'  => true,
			),
		);
	}
}
