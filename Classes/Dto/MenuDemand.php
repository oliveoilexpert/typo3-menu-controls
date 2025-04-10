<?php

namespace UBOS\MenuControls\Dto;

class MenuDemand
{
	public function __construct(
		public string $parents = '',
		public string $records = '',
		public ?int   $limit = null,
		public int    $offset = 0,
		/**
		 * @var array<string, array{uids: string, conjunction: string}>
		 */
		public array  $categories = [],
		public string $categoriesConjunction = 'and',
		public string $orderField = 'sorting',
		public string $orderDirection = 'asc',
		public bool   $orderByRecordsProperty = false,
		public array  $additionalSettings = [],
	)
	{
	}

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