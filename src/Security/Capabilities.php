<?php

declare(strict_types=1);

namespace ComplyOps\Security;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress capability definitions for ComplyOps.
 */
final class Capabilities {

	public const MANAGE     = 'manage_complyops';
	public const RUN_AUDITS = 'run_complyops_audits';
	public const REMEDIATE  = 'remediate_complyops';
	public const VIEW       = 'view_complyops';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::MANAGE,
			self::RUN_AUDITS,
			self::REMEDIATE,
			self::VIEW,
		);
	}

	public static function register(): void {
		$roles = array( 'administrator' );

		foreach ( $roles as $role_slug ) {
			$role = get_role( $role_slug );

			if ( null === $role ) {
				continue;
			}

			foreach ( self::all() as $capability ) {
				$role->add_cap( $capability );
			}
		}
	}

	public static function can_view(): bool {
		return current_user_can( self::VIEW )
			|| current_user_can( self::MANAGE )
			|| current_user_can( self::RUN_AUDITS );
	}

	public static function can_run_audits(): bool {
		return current_user_can( self::RUN_AUDITS )
			|| current_user_can( self::MANAGE );
	}

	public static function can_remediate(): bool {
		return current_user_can( self::REMEDIATE )
			|| current_user_can( self::MANAGE );
	}

	public static function can_manage(): bool {
		return current_user_can( self::MANAGE );
	}
}
