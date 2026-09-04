<?php

declare(strict_types=1);

namespace ComplyOps\Notification;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deduplicated administrator alerts surfaced in the admin notification bell.
 */
final class NotificationService {

	public const OPTION_KEY = 'complyops_notifications';

	/**
	 * @param array<string, mixed> $context
	 */
	public function queue(
		string $type,
		string $severity,
		string $message,
		string $dedupe_key,
		array $context = array(),
	): void {
		if ( '' === $dedupe_key || '' === $message ) {
			return;
		}

		$notifications = $this->all();

		foreach ( $notifications as $notification ) {
			if ( ! is_array( $notification ) ) {
				continue;
			}

			if ( (string) ( $notification['dedupe_key'] ?? '' ) === $dedupe_key
				&& empty( $notification['dismissed_at'] ) ) {
				return;
			}
		}

		$notifications[] = array(
			'id'           => wp_generate_uuid4(),
			'type'         => sanitize_key( $type ),
			'severity'     => sanitize_key( $severity ),
			'message'      => $message,
			'dedupe_key'   => sanitize_key( $dedupe_key ),
			'context'      => $context,
			'created_at'   => gmdate( 'c' ),
			'dismissed_at' => null,
		);

		$this->save( $notifications );
	}

	public function dismiss( string $dedupe_key ): void {
		$dedupe_key = sanitize_key( $dedupe_key );
		if ( '' === $dedupe_key ) {
			return;
		}

		$this->dismiss_matching(
			static fn ( array $notification ): bool => (string) ( $notification['dedupe_key'] ?? '' ) === $dedupe_key
		);
	}

	public function dismiss_by_id( string $id ): bool {
		$id = sanitize_text_field( $id );
		if ( '' === $id ) {
			return false;
		}

		return $this->dismiss_matching(
			static fn ( array $notification ): bool => (string) ( $notification['id'] ?? '' ) === $id
		) > 0;
	}

	public function dismiss_all(): int {
		return $this->dismiss_matching( static fn ( array $notification ): bool => true );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function active(): array {
		return array_values(
			array_filter(
				$this->all(),
				static fn ( mixed $notification ): bool => is_array( $notification ) && empty( $notification['dismissed_at'] )
			)
		);
	}

	/**
	 * Active alerts, newest first, for the admin bell and REST.
	 *
	 * @return list<array{id: string, type: string, severity: string, message: string, created_at: string}>
	 */
	public function active_payload(): array {
		$items = array();

		foreach ( $this->active() as $notification ) {
			$id      = (string) ( $notification['id'] ?? '' );
			$message = (string) ( $notification['message'] ?? '' );
			if ( '' === $id || '' === $message ) {
				continue;
			}

			$items[] = array(
				'id'         => $id,
				'type'       => (string) ( $notification['type'] ?? '' ),
				'severity'   => (string) ( $notification['severity'] ?? 'warning' ),
				'message'    => $message,
				'created_at' => (string) ( $notification['created_at'] ?? '' ),
			);
		}

		return array_reverse( $items );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @param callable(array<string, mixed>): bool $matcher
	 */
	private function dismiss_matching( callable $matcher ): int {
		$notifications = $this->all();
		$count         = 0;

		foreach ( $notifications as &$notification ) {
			if ( ! is_array( $notification ) ) {
				continue;
			}

			if ( ! empty( $notification['dismissed_at'] ) ) {
				continue;
			}

			if ( ! $matcher( $notification ) ) {
				continue;
			}

			$notification['dismissed_at'] = gmdate( 'c' );
			++$count;
		}
		unset( $notification );

		if ( $count > 0 ) {
			$this->save( $notifications );
		}

		return $count;
	}

	/**
	 * @param list<array<string, mixed>> $notifications
	 */
	private function save( array $notifications ): void {
		$notifications = array_slice( $notifications, -50 );
		update_option( self::OPTION_KEY, $notifications, false );
	}
}
