<?php

declare(strict_types=1);

namespace ComplyOps\Control\Evaluation\WordPress;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Control\ControlDefinition;
use ComplyOps\Control\ControlResult;
use ComplyOps\Control\Evaluation\AbstractControlEvaluator;
use ComplyOps\Control\Evaluation\EvaluationContext;
use ComplyOps\Database\Schema;

/**
 * Static WordPress configuration checks that do not require the discovery engine.
 */
final class WordPressControlEvaluator extends AbstractControlEvaluator {

	public function supports( ControlDefinition $definition ): bool {
		if ( str_starts_with( $definition->id, 'GDPR-WP-' )
			|| str_starts_with( $definition->id, 'GDPR-SEC-' ) ) {
			return true;
		}

		return in_array( $definition->id, self::WORDPRESS_SUPPORTED_IDS, true );
	}

	public function evaluate( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		return match ( $definition->id ) {
			'GDPR-WP-001' => $this->evaluate_privacy_policy_page( $definition ),
			'GDPR-WP-002' => $this->pass(
				$definition,
				__( 'WordPress core personal data export tools are available.', 'complyops' ),
				__( 'Personal data export functionality is available.', 'complyops' ),
			),
			'GDPR-WP-003' => $this->pass(
				$definition,
				__( 'WordPress core personal data erasure tools are available.', 'complyops' ),
				__( 'Personal data erasure functionality is available.', 'complyops' ),
			),
			'GDPR-WP-004', 'SOC2-CC6-010', 'OWASP-A01-002', 'OWASP-A07-001' => $this->evaluate_registration_setting( $definition ),
			'GDPR-WP-005' => $this->evaluate_comment_privacy( $definition ),
			'GDPR-WP-006', 'SOC2-CC6-009', 'HIPAA-TECH-009', 'OWASP-A01-001' => $this->evaluate_rest_exposure( $definition, $context ),
			'GDPR-SEC-001', 'SOC2-CC6-007', 'HIPAA-ENCRYPT-001', 'OWASP-A02-001' => $this->evaluate_https( $definition ),
			'GDPR-SEC-002', 'SOC2-CC7-002', 'HIPAA-TECH-010', 'OWASP-A06-001' => $this->evaluate_core_version( $definition ),
			'GDPR-SEC-003', 'SOC2-CC7-007', 'SOC2-CC9-004', 'HIPAA-TECH-011', 'OWASP-A06-002' => $this->evaluate_plugin_posture( $definition, $context ),
			'GDPR-SEC-004', 'SOC2-CC6-003', 'OWASP-A01-003' => $this->evaluate_user_privileges( $definition ),
			'SOC2-CC7-006', 'HIPAA-AUDIT-002', 'OWASP-A09-002' => $this->evaluate_activity_log_table( $definition ),
			'OWASP-A05-001' => $this->evaluate_debug_mode( $definition ),
			'OWASP-A05-002' => $this->evaluate_file_editor_disabled( $definition ),
			'OWASP-A05-003' => $this->evaluate_default_admin_username( $definition ),
			default => $this->evaluate_manual_default( $definition ),
		};
	}

	/** @var list<string> */
	private const WORDPRESS_SUPPORTED_IDS = array(
		'SOC2-CC6-003',
		'SOC2-CC6-007',
		'SOC2-CC6-009',
		'SOC2-CC6-010',
		'SOC2-CC7-002',
		'SOC2-CC7-006',
		'SOC2-CC7-007',
		'SOC2-CC9-004',
		'HIPAA-AUDIT-002',
		'HIPAA-ENCRYPT-001',
		'HIPAA-TECH-009',
		'HIPAA-TECH-010',
		'HIPAA-TECH-011',
		'OWASP-A01-001',
		'OWASP-A01-002',
		'OWASP-A01-003',
		'OWASP-A02-001',
		'OWASP-A03-001',
		'OWASP-A03-002',
		'OWASP-A04-002',
		'OWASP-A05-001',
		'OWASP-A05-002',
		'OWASP-A05-003',
		'OWASP-A06-001',
		'OWASP-A06-002',
		'OWASP-A06-003',
		'OWASP-A07-001',
		'OWASP-A07-002',
		'OWASP-A07-003',
		'OWASP-A08-001',
		'OWASP-A08-002',
		'OWASP-A09-001',
		'OWASP-A09-002',
		'OWASP-A10-001',
	);

	private function evaluate_manual_default( ControlDefinition $definition ): ControlResult {
		if ( ! str_starts_with( $definition->id, 'SOC2-' )
			&& ! str_starts_with( $definition->id, 'OWASP-' ) ) {
			return $this->unknown(
				$definition,
				__( 'WordPress control evaluation is not yet implemented.', 'complyops' ),
			);
		}

		if ( null !== $definition->manual_review_instructions ) {
			return $this->unknown(
				$definition,
				$definition->manual_review_instructions,
				$definition->recommended_value,
			);
		}

		if ( str_starts_with( $definition->id, 'OWASP-' ) ) {
			return $this->unknown(
				$definition,
				__( 'OWASP control requires manual or organizational review.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			__( 'SOC 2 control requires manual or organizational review.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	private function evaluate_debug_mode( ControlDefinition $definition ): ControlResult {
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

	private function evaluate_file_editor_disabled( ControlDefinition $definition ): ControlResult {
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

	private function evaluate_default_admin_username( ControlDefinition $definition ): ControlResult {
		$admin = get_user_by( 'login', 'admin' );

		if ( $admin instanceof \WP_User ) {
			return $this->fail(
				$definition,
				__( 'An active account uses the default "admin" username.', 'complyops' ),
				__( 'No active account uses the default "admin" username.', 'complyops' ),
			);
		}

		return $this->pass(
			$definition,
			__( 'No active account uses the default "admin" username.', 'complyops' ),
			__( 'No active account uses the default "admin" username.', 'complyops' ),
		);
	}

	private function evaluate_activity_log_table( ControlDefinition $definition ): ControlResult {
		global $wpdb;

		$table  = Schema::table_name( 'activity_log' );
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		) === $table;

		if ( $exists ) {
			return $this->pass(
				$definition,
				__( 'ComplyOps administrator activity log table is available.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->fail(
			$definition,
			__( 'ComplyOps administrator activity log table is not installed.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	private function evaluate_privacy_policy_page( ControlDefinition $definition ): ControlResult {
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

	private function evaluate_registration_setting( ControlDefinition $definition ): ControlResult {
		$registration_enabled = (bool) get_option( 'users_can_register', false );

		if ( $registration_enabled ) {
			return $this->unknown(
				$definition,
				__( 'Open user registration is enabled; review whether this is required.', 'complyops' ),
				__( 'User registration configuration reviewed and justified.', 'complyops' ),
			);
		}

		return $this->pass(
			$definition,
			__( 'Open user registration is disabled.', 'complyops' ),
			__( 'User registration configuration reviewed and justified.', 'complyops' ),
		);
	}

	private function evaluate_https( ControlDefinition $definition ): ControlResult {
		$home    = (string) get_option( 'home', '' );
		$siteurl = (string) get_option( 'siteurl', '' );

		$home_https    = str_starts_with( $home, 'https://' );
		$siteurl_https = str_starts_with( $siteurl, 'https://' );

		if ( $home_https && $siteurl_https ) {
			return $this->pass(
				$definition,
				__( 'Site URL and Home URL are configured to use HTTPS.', 'complyops' ),
				__( 'Site is served over HTTPS.', 'complyops' ),
			);
		}

		return $this->fail(
			$definition,
			__( 'WordPress Home URL or Site URL is not configured for HTTPS.', 'complyops' ),
			__( 'Site is served over HTTPS.', 'complyops' ),
		);
	}

	private function evaluate_core_version( ControlDefinition $definition ): ControlResult {
		global $wp_version;

		$version = is_string( $wp_version ) ? $wp_version : '';
		$minimum = '6.6';

		if ( '' === $version ) {
			return $this->unknown(
				$definition,
				__( 'WordPress version could not be determined.', 'complyops' ),
				sprintf(
					/* translators: %s: minimum WordPress version */
					__( 'WordPress %s or newer.', 'complyops' ),
					$minimum
				),
			);
		}

		if ( version_compare( $version, $minimum, '>=' ) ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %s: WordPress version */
					__( 'WordPress %s meets the supported minimum.', 'complyops' ),
					$version
				),
				sprintf(
					/* translators: %s: minimum WordPress version */
					__( 'WordPress %s or newer.', 'complyops' ),
					$minimum
				),
			);
		}

		return $this->fail(
			$definition,
			sprintf(
				/* translators: 1: current version, 2: minimum version */
				__( 'WordPress %1$s is below the supported minimum (%2$s).', 'complyops' ),
				$version,
				$minimum
			),
			sprintf(
				/* translators: %s: minimum WordPress version */
				__( 'WordPress %s or newer.', 'complyops' ),
				$minimum
			),
		);
	}

	private function evaluate_rest_exposure( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
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

	private function evaluate_plugin_posture( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		$plugins = $context->discovery['wordpress']['installed_plugins'] ?? array();

		if ( ! is_array( $plugins ) || array() === $plugins ) {
			return $this->unknown(
				$definition,
				__( 'Installed plugin inventory is unavailable.', 'complyops' ),
				__( 'Plugins with data-protection impact are kept reasonably current.', 'complyops' ),
			);
		}

		$active_count = count(
			array_filter(
				$plugins,
				static fn ( array $plugin ): bool => ! empty( $plugin['active'] )
			)
		);

		return $this->info(
			$definition,
			sprintf(
				/* translators: 1: active plugin count, 2: total plugin count */
				__( 'Discovered %1$d active plugins of %2$d installed; version review is informational.', 'complyops' ),
				$active_count,
				count( $plugins )
			),
			__( 'Plugins with data-protection impact are kept reasonably current.', 'complyops' ),
		);
	}

	private function evaluate_comment_privacy( ControlDefinition $definition ): ControlResult {
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

	private function evaluate_user_privileges( ControlDefinition $definition ): ControlResult {
		if ( ! function_exists( 'count_users' ) ) {
			return $this->unknown(
				$definition,
				$definition->manual_review_instructions ?? __( 'User privilege configuration requires manual review.', 'complyops' ),
				$definition->recommended_value ?? __( 'Administrative accounts and privileges follow least-privilege practices.', 'complyops' ),
			);
		}

		$counts = count_users();
		$admins = (int) ( $counts['avail_roles']['administrator'] ?? 0 );

		if ( $admins <= 0 ) {
			return $this->unknown(
				$definition,
				__( 'No administrator accounts were detected.', 'complyops' ),
				$definition->recommended_value ?? __( 'Administrative accounts and privileges follow least-privilege practices.', 'complyops' ),
			);
		}

		if ( $admins > 5 ) {
			return $this->unknown(
				$definition,
				sprintf(
					/* translators: %d: administrator count */
					__( '%d administrator accounts detected; review whether all are required.', 'complyops' ),
					$admins
				),
				$definition->recommended_value ?? __( 'Administrative accounts and privileges follow least-privilege practices.', 'complyops' ),
			);
		}

		return $this->pass(
			$definition,
			sprintf(
				/* translators: %d: administrator count */
				__( 'Detected %d administrator account(s); count is within automated review limits.', 'complyops' ),
				$admins
			),
			$definition->recommended_value ?? __( 'Administrative accounts and privileges follow least-privilege practices.', 'complyops' ),
		);
	}
}
