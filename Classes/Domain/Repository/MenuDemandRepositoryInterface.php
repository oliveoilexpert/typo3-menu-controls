<?php

namespace UBOS\MenuControls\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use UBOS\MenuControls\Dto\MenuDemand;

interface MenuDemandRepositoryInterface
{
	public function findByMenuDemand(MenuDemand $demand, bool $returnRawQueryResult): QueryResult|array;
}