<?php

declare(strict_types=1);

namespace ComplyOps\Evidence;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Database\Schema;

/**
 * Applies configured evidence retention policies.
 */
final class EvidenceRetentionService {

	public const OPTION_KEY = 'complyops_evidence_retention_days';

	public function apply(): int {
		$days = get_option( self::OPTION_KEY, null );

		if ( null === $days || ! is_numeric( $days ) || (int) $days <= 0 ) {
			return 0;
		}

		global $wpdb;

		$table = Schema::table_name( 'evidence' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( (int) $days * DAY_IN_SECONDS ) );

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s",
				$cutoff
			)
		);
	}
}
