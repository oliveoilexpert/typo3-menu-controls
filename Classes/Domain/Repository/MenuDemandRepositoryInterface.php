<?php

namespace UBOS\MenuControls\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use UBOS\MenuControls\Dto\MenuDemand;

/**
 * Interface for repositories that support filtering by MenuDemand objects.
 * Repositories implementing this interface can be used with the menu control system.
 */
interface MenuDemandRepositoryInterface
{
	/**
	 * Finds records based on a MenuDemand configuration.
	 *
	 * @param MenuDemand $demand The demand object containing search criteria
	 * @param bool $returnRawQueryResult Whether to return raw query results instead of domain objects
	 * @return QueryResult|array The matched records
	 */
	public function findByMenuDemand(MenuDemand $demand, bool $returnRawQueryResult): QueryResult|array;
}