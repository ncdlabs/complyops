<?php

declare(strict_types=1);

namespace ComplyOps\Remediation\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exceptions; messages escaped at REST/WP_Error boundaries.

use ComplyOps\Remediation\AbstractRemediationHandler;

/**
 * Creates and assigns a WordPress Privacy Policy page.
 */
final class AssignPrivacyPolicyPageRemediation extends AbstractRemediationHandler {

	public function id(): string {
		return 'assign_privacy_policy_page';
	}

	public function label(): string {
		return __( 'Assign Privacy Policy page', 'complyops' );
	}

	public function description(): string {
		return __( 'Creates a published Privacy Policy page and assigns it under Settings → Privacy.', 'complyops' );
	}

	public function control_ids(): array {
		return array(
			'GDPR-WP-001',
		);
	}

	public function capture_state(): array {
		return array(
			'privacy_policy_page_id' => (int) get_option( 'wp_page_for_privacy_policy', 0 ),
		);
	}

	public function apply(): array {
		$page_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );

		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			return array(
				'privacy_policy_page_id' => $page_id,
			);
		}

		if ( $page_id > 0 && 'publish' !== get_post_status( $page_id ) ) {
			wp_update_post(
				array(
					'ID'          => $page_id,
					'post_status' => 'publish',
				)
			);

			return array(
				'privacy_policy_page_id' => $page_id,
			);
		}

		$new_page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Privacy Policy', 'complyops' ),
				'post_content' => __( 'This page describes how personal data is collected and used on this site. Replace this placeholder with your privacy policy.', 'complyops' ),
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);

		if ( is_wp_error( $new_page_id ) ) {
			throw new \RuntimeException( $new_page_id->get_error_message() );
		}

		update_option( 'wp_page_for_privacy_policy', (int) $new_page_id, false );

		return array(
			'privacy_policy_page_id' => (int) $new_page_id,
		);
	}

	public function rollback( array $before_state ): void {
		$page_id = (int) ( $before_state['privacy_policy_page_id'] ?? 0 );

		if ( $page_id > 0 ) {
			update_option( 'wp_page_for_privacy_policy', $page_id, false );

			return;
		}

		delete_option( 'wp_page_for_privacy_policy' );
	}

	public function preview_changes(): array {
		$page_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );

		return array(
			array(
				'key'    => 'wp_page_for_privacy_policy',
				'label'  => __( 'Privacy Policy page', 'complyops' ),
				'before' => $page_id > 0 ? $page_id : null,
				'after'  => $page_id > 0 ? $page_id : __( 'New published page', 'complyops' ),
			),
		);
	}
}
