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
 * Account, registration, and privilege checks.
 */
final class AccessAccountTests extends AbstractSharedTests {

	/**
	 * @return array<string, callable(ControlDefinition, EvaluationContext): ControlResult>
	 */
	public function handlers(): array {
		return array(
			'access.open_registration_review'  => array( $this, 'open_registration_review' ),
			'access.administrator_inventory'   => array( $this, 'administrator_inventory' ),
			'access.default_admin_username'    => array( $this, 'default_admin_username' ),
			'access.stale_admin_accounts'      => array( $this, 'stale_admin_accounts' ),
			'access.mfa_integration'           => array( $this, 'mfa_integration' ),
			'access.session_timeout'           => array( $this, 'session_timeout' ),
			'access.rate_limiting_present'     => array( $this, 'rate_limiting_present' ),
		);
	}

	public function open_registration_review( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
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

	public function administrator_inventory( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
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

	public function default_admin_username( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
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

	public function stale_admin_accounts( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'orderby' => 'user_registered',
				'order'   => 'ASC',
			)
		);

		$stale_threshold = time() - ( 90 * DAY_IN_SECONDS );
		$stale           = array();

		foreach ( $admins as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$last_login = get_user_meta( $user->ID, 'complyops_last_login', true );
			$reference  = is_numeric( $last_login ) ? (int) $last_login : strtotime( (string) $user->user_registered );

			if ( $reference < $stale_threshold ) {
				$stale[] = $user->user_login;
			}
		}

		if ( array() === $stale ) {
			return $this->pass(
				$definition,
				__( 'No administrator accounts appear stale based on available registration metadata.', 'complyops' ),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: %s: comma-separated usernames */
				__( 'Administrator accounts may be stale: %s. Review and disable unused accounts.', 'complyops' ),
				implode( ', ', array_slice( $stale, 0, 5 ) )
			),
			$definition->recommended_value,
		);
	}

	public function mfa_integration( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$mfa_plugins = array(
			'wordfence/wordfence.php',
			'wp-2fa/wp-2fa.php',
			'two-factor/two-factor.php',
			'miniorange-2-factor-authentication/miniorange-2-factor-authentication.php',
		);

		foreach ( $mfa_plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				return $this->pass(
					$definition,
					sprintf(
						/* translators: %s: plugin basename */
						__( 'Multi-factor authentication plugin detected: %s.', 'complyops' ),
						$plugin
					),
					$definition->recommended_value,
				);
			}
		}

		return $this->unknown(
			$definition,
			__( 'No supported MFA plugin was detected; verify administrative MFA through your identity provider.', 'complyops' ),
			$definition->recommended_value,
		);
	}

	public function session_timeout( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$lifetime = (int) apply_filters( 'auth_cookie_expiration', 2 * DAY_IN_SECONDS, 0, false );

		if ( $lifetime <= DAY_IN_SECONDS ) {
			return $this->pass(
				$definition,
				sprintf(
					/* translators: %d: session lifetime in seconds */
					__( 'WordPress session cookie lifetime is %d seconds.', 'complyops' ),
					$lifetime
				),
				$definition->recommended_value,
			);
		}

		return $this->unknown(
			$definition,
			sprintf(
				/* translators: %d: session lifetime in seconds */
				__( 'WordPress session cookie lifetime is %d seconds; review whether shorter sessions are required.', 'complyops' ),
				$lifetime
			),
			$definition->recommended_value,
		);
	}

	public function rate_limiting_present( ControlDefinition $definition, EvaluationContext $context ): ControlResult {
		unset( $context );
		$plugins = array(
			'wordfence/wordfence.php',
			'sucuri-scanner/sucuri.php',
			'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php',
		);

		foreach ( $plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				return $this->pass(
					$definition,
					sprintf(
						/* translators: %s: plugin basename */
						__( 'Rate limiting or login protection plugin detected: %s.', 'complyops' ),
						$plugin
					),
					$definition->recommended_value,
				);
			}
		}

		return $this->unknown(
			$definition,
			__( 'No automated rate-limiting plugin detected; review brute-force protections at the web server or WAF layer.', 'complyops' ),
			$definition->recommended_value,
		);
	}
}
