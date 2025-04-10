<?php

namespace UBOS\MenuControls\Dto;

/**
 * Data Transfer Object for menu filtering criteria.
 * Used to filter records by various criteria like parents, specific records,
 * categories, and to configure sorting and pagination.
 */
class MenuDemand
{
	/**
	 * @param string $parents Comma-separated list of parent page UIDs
	 * @param string $records Comma-separated list of record UIDs
	 * @param int|null $limit Maximum number of records to return (default is null for no limit)
	 * @param int $offset Number of records to skip (default: 0)
	 * @param array<string, array{uids: string, conjunction: string}> $categories Category filter configuration
	 * @param string $categoriesConjunction How to combine category groups (and, or, notor, notand) (default: 'and')
	 * @param string $orderField Field to sort by (default: 'sorting')
	 * @param string $orderDirection Sort direction (asc, desc) (default: 'asc')
	 * @param bool $orderByRecordsProperty Whether to maintain the order from the records parameter (default: false)
	 * @param array $additionalSettings Additional custom settings for specialized implementations (optional)
	 */
	public function __construct(
		public string $parents = '',
		public string $records = '',
		public ?int   $limit = null,
		public int    $offset = 0,
		public array  $categories = [],
		public string $categoriesConjunction = 'and',
		public string $orderField = 'sorting',
		public string $orderDirection = 'asc',
		public bool   $orderByRecordsProperty = false,
		public array  $additionalSettings = [],
	)
	{
	}

	/**
	 * Factory method to create a MenuDemand from an array
	 *
	 * @param array $demand Raw demand data
	 * @param array $additionalSettings Additional settings to merge (optional)
	 */
	public static function createFromArray(array $demand, array $additionalSettings = []): MenuDemand
	{
		return new MenuDemand(
			$demand['parents'] ?? '',
			$demand['records'] ?? '',
			(int)($demand['limit'] ?? null),
			(int)($demand['offset'] ?? 0),
			$demand['categories'] ?? [],
			$demand['categoriesConjunction'] ?? 'and',
			$demand['orderField'] ?? 'sorting',
			$demand['orderDirection'] ?? 'asc',
			(bool)($demand['orderByRecordsProperty'] ?? false),
			$additionalSettings
		);
	}
}