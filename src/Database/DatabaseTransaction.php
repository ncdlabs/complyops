<?php

declare(strict_types=1);

namespace ComplyOps\Database;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RuntimeException;

/**
 * Small transaction boundary for multi-table compliance operations.
 */
final class DatabaseTransaction {

	private bool $active = false;

	public function begin(): void {
		global $wpdb;

		if ( $this->active ) {
			throw new RuntimeException( 'A database transaction is already active.' );
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			throw new RuntimeException( 'Could not start the database transaction.' );
		}

		$this->active = true;
	}

	public function commit(): void {
		global $wpdb;

		if ( ! $this->active ) {
			return;
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			throw new RuntimeException( 'Could not commit the database transaction.' );
		}

		$this->active = false;
	}

	public function rollback(): void {
		global $wpdb;

		if ( ! $this->active ) {
			return;
		}

		$rolled_back = $wpdb->query( 'ROLLBACK' );
		$this->active = false;

		if ( false === $rolled_back ) {
			throw new RuntimeException( 'Could not roll back the database transaction.' );
		}
	}

	public function is_active(): bool {
		return $this->active;
	}
}
