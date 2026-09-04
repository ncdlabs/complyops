<?php

declare(strict_types=1);

namespace ComplyOps\Activity;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Database\ActivityLogRepository;

/**
 * Records administrator actions for compliance audit trails.
 */
final class ActivityLogService {

	public function __construct(
		private readonly ActivityLogRepository $repository = new ActivityLogRepository(),
	) {
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	public function record(
		string $action,
		string $summary,
		?string $object_type = null,
		?string $object_id = null,
		array $metadata = array(),
	): int {
		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;

		return $this->repository->insert(
			$action,
			$summary,
			$user_id > 0 ? $user_id : null,
			$object_type,
			$object_id,
			$metadata
		);
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return array{total: int, records: list<array<string, mixed>>}
	 */
	public function list( array $filters = array() ): array {
		return array(
			'total'   => $this->repository->count( $filters ),
			'records' => $this->repository->list( $filters ),
		);
	}
}
