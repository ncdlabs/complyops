<?php

declare(strict_types=1);

namespace ComplyOps\Enforcement;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for analytics PII query-parameter filtering.
 */
final class PiiSettings {

	public const OPTION_KEY = 'complyops_pii_settings';

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		return array_merge( $this->defaults(), is_array( $stored ) ? $stored : array() );
	}

	public function is_enabled(): bool {
		return ! empty( $this->all()['enabled'] );
	}

	/**
	 * @return list<string>
	 */
	public function blocked_parameters(): array {
		$params = $this->all()['blocked_parameters'] ?? array();

		return is_array( $params ) ? array_values( array_filter( $params, 'is_string' ) ) : array();
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public function save( array $settings ): void {
		update_option( self::OPTION_KEY, $this->sanitize( array_merge( $this->all(), $settings ) ), false );
	}

	public function activate_defaults(): void {
		if ( false !== get_option( self::OPTION_KEY, false ) ) {
			return;
		}

		update_option( self::OPTION_KEY, $this->defaults(), false );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function public_config(): array {
		return array(
			'enabled'            => $this->is_enabled(),
			'blocked_parameters' => $this->blocked_parameters(),
		);
	}

	/**
	 * Recommended profile for automatic remediation.
	 *
	 * @return array<string, mixed>
	 */
	public function recommended_profile(): array {
		return array(
			'enabled'            => true,
			'blocked_parameters' => $this->defaults()['blocked_parameters'],
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return array(
			'enabled'            => false,
			'blocked_parameters' => array(
				'email',
				'email_address',
				'user_email',
				'firstname',
				'first_name',
				'lastname',
				'last_name',
				'fullname',
				'full_name',
				'phone',
				'telephone',
				'mobile',
				'address',
				'password',
				'token',
				'auth',
				'authorization',
				'access_token',
				'session',
				'jwt',
			),
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private function sanitize( array $settings ): array {
		$params = $settings['blocked_parameters'] ?? array();

		if ( is_string( $params ) ) {
			$params = preg_split( '/[\s,]+/', $params ) ?: array();
		}

		$params = is_array( $params ) ? $params : array();
		$params = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn ( $param ): string => sanitize_key( (string) $param ),
						$params
					),
					static fn ( string $param ): bool => '' !== $param
				)
			)
		);

		return array(
			'enabled'            => ! empty( $settings['enabled'] ),
			'blocked_parameters' => $params ?: $this->defaults()['blocked_parameters'],
		);
	}
}
