<?php

declare(strict_types=1);

namespace ComplyOps\Control;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exceptions; messages escaped at REST/WP_Error boundaries.

use InvalidArgumentException;

/**
 * Persists per-control enable/disable overrides within a framework.
 */
final class ControlOverrideService {

	public const OPTION = 'complyops_control_overrides';

	/**
	 * @return array{enabled: bool, disabled_comment: string|null, disabled_at: string|null, disabled_by: int|null}
	 */
	public function state_for_control( string $framework_id, string $control_id ): array {
		$override = $this->override( $framework_id, $control_id );

		if ( null === $override ) {
			return array(
				'enabled'           => true,
				'disabled_comment'  => null,
				'disabled_at'       => null,
				'disabled_by'       => null,
			);
		}

		return array(
			'enabled'           => ! empty( $override['enabled'] ),
			'disabled_comment'  => isset( $override['comment'] ) && is_string( $override['comment'] )
				? $override['comment']
				: null,
			'disabled_at'       => isset( $override['disabled_at'] ) && is_string( $override['disabled_at'] )
				? $override['disabled_at']
				: null,
			'disabled_by'       => isset( $override['disabled_by'] ) && is_numeric( $override['disabled_by'] )
				? (int) $override['disabled_by']
				: null,
		);
	}

	public function is_enabled( string $framework_id, string $control_id ): bool {
		return $this->state_for_control( $framework_id, $control_id )['enabled'];
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function overrides_for_framework( string $framework_id ): array {
		$stored = $this->all_overrides();

		return isset( $stored[ $framework_id ] ) && is_array( $stored[ $framework_id ] )
			? $stored[ $framework_id ]
			: array();
	}

	/**
	 * @return array{enabled: bool, disabled_comment: string|null, disabled_at: string|null, disabled_by: int|null}
	 */
	public function disable( string $framework_id, string $control_id, string $comment ): array {
		$comment = trim( $comment );

		if ( '' === $comment ) {
			throw new InvalidArgumentException(
				__( 'A comment is required when disabling a control.', 'complyops' )
			);
		}

		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$stored  = $this->all_overrides();

		if ( ! isset( $stored[ $framework_id ] ) || ! is_array( $stored[ $framework_id ] ) ) {
			$stored[ $framework_id ] = array();
		}

		$stored[ $framework_id ][ $control_id ] = array(
			'enabled'     => false,
			'comment'     => $comment,
			'disabled_at' => gmdate( 'c' ),
			'disabled_by' => $user_id > 0 ? $user_id : null,
		);

		update_option( self::OPTION, $stored, false );

		return $this->state_for_control( $framework_id, $control_id );
	}

	/**
	 * @return array{enabled: bool, disabled_comment: string|null, disabled_at: string|null, disabled_by: int|null}
	 */
	public function enable( string $framework_id, string $control_id ): array {
		$stored = $this->all_overrides();

		if ( isset( $stored[ $framework_id ][ $control_id ] ) ) {
			unset( $stored[ $framework_id ][ $control_id ] );

			if ( array() === $stored[ $framework_id ] ) {
				unset( $stored[ $framework_id ] );
			}
		}

		update_option( self::OPTION, $stored, false );

		return $this->state_for_control( $framework_id, $control_id );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function all_overrides(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function override( string $framework_id, string $control_id ): ?array {
		$framework = $this->overrides_for_framework( $framework_id );
		$override  = $framework[ $control_id ] ?? null;

		return is_array( $override ) ? $override : null;
	}
}
