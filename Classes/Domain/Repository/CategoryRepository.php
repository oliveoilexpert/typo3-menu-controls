<?php

namespace UBOS\MenuControls\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\Repository;
use TYPO3\CMS\Core\Context\Context;

/**
 * Repository for 'sys_category'
 */
class CategoryRepository extends Repository
{
	protected array $defaultOrderings = [
		'sorting' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING
	];

	public function initializeObject(): void
	{
		// todo: check if language aspect is set
		$querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
		$querySettings->setRespectStoragePage(false);
		$this->setDefaultQuerySettings($querySettings);
	}

	public function findByUidList(string $uids, bool $returnRawQueryResult = false): ?QueryResult
	{
		if (!$uids) {
			return null;
		}
		$query = $this->createQuery();
		return $query
			->matching(
				$query->logicalAnd(
					$query->equals('hidden', 0),
					$query->logicalOr(
						$query->in('l10n_parent', explode(',', $uids)),
						$query->in('uid', explode(',', $uids))
					),
				)
			)
			->execute($returnRawQueryResult);
	}

	public function findByParent(int $parentUid, bool $returnRawQueryResult = false): ?QueryResult
	{
		$query = $this->createQuery();
		return $query
			->matching(
				$query->logicalAnd(
					$query->equals('hidden', 0),
					$query->equals('parent', $parentUid)
				)
			)
			->execute($returnRawQueryResult);
	}
}