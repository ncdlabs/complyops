<?php

declare(strict_types=1);

namespace ComplyOps\Remediation\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Remediation\AbstractRemediationHandler;

/**
 * Applies recommended WordPress comment privacy settings.
 */
final class HardenCommentPrivacyRemediation extends AbstractRemediationHandler {

	public function id(): string {
		return 'harden_comment_privacy';
	}

	public function label(): string {
		return __( 'Harden comment privacy settings', 'complyops' );
	}

	public function description(): string {
		return __( 'Requires registration for comments, disables gravatars, and closes comments by default on new posts.', 'complyops' );
	}

	public function control_ids(): array {
		return array(
			'GDPR-WP-005',
		);
	}

	public function capture_state(): array {
		return array(
			'comment_registration'   => (bool) get_option( 'comment_registration', false ),
			'show_avatars'           => (bool) get_option( 'show_avatars', true ),
			'default_comment_status' => (string) get_option( 'default_comment_status', 'open' ),
		);
	}

	public function apply(): array {
		update_option( 'comment_registration', true, false );
		update_option( 'show_avatars', false, false );
		update_option( 'default_comment_status', 'closed', false );

		return array(
			'comment_registration'     => true,
			'show_avatars'             => false,
			'default_comment_status'   => 'closed',
		);
	}

	public function rollback( array $before_state ): void {
		if ( array_key_exists( 'comment_registration', $before_state ) ) {
			update_option( 'comment_registration', ! empty( $before_state['comment_registration'] ), false );
		}

		if ( array_key_exists( 'show_avatars', $before_state ) ) {
			update_option( 'show_avatars', ! empty( $before_state['show_avatars'] ), false );
		}

		if ( isset( $before_state['default_comment_status'] ) && is_string( $before_state['default_comment_status'] ) ) {
			update_option( 'default_comment_status', $before_state['default_comment_status'], false );
		}
	}

	public function preview_changes(): array {
		$state = $this->capture_state();

		return array(
			array(
				'key'    => 'comment_registration',
				'label'  => __( 'Comment registration required', 'complyops' ),
				'before' => $state['comment_registration'],
				'after'  => true,
			),
			array(
				'key'    => 'show_avatars',
				'label'  => __( 'Show avatars', 'complyops' ),
				'before' => $state['show_avatars'],
				'after'  => false,
			),
			array(
				'key'    => 'default_comment_status',
				'label'  => __( 'Default comment status', 'complyops' ),
				'before' => $state['default_comment_status'],
				'after'  => 'closed',
			),
		);
	}
}
