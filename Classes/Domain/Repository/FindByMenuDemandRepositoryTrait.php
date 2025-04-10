<?php

namespace UBOS\MenuControls\Domain\Repository;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use UBOS\MenuControls\Dto\MenuDemand;

trait FindByMenuDemandRepositoryTrait
{
	abstract public function createQuery();

	public function map(array $rows): array
	{
		return GeneralUtility::makeInstance(DataMapper::class)->map($this->objectType, $rows);
	}

	/**
	 * @param QueryInterface $query
	 * @param MenuDemand $demand
	 * @return ConstraintInterface[]
	 */
	protected function getAdditionalMenuDemandConstraints(QueryInterface $query, MenuDemand $demand): array
	{
		return [];
	}

	public function findByMenuDemand(MenuDemand $demand, bool $returnRawQueryResult = false): array
	{
		$query = $this->createQuery();
		$constraints = $this->getAdditionalMenuDemandConstraints($query, $demand);
		$pidUidConstraints = [];
		if ($demand->records) {
			foreach (explode(',', $demand->records) as $key => $value) {
				$pidUidConstraints[] = $query->equals('uid', $value);
				$pidUidConstraints[] = $query->equals('l10n_parent', $value);
			}
		}
		if ($demand->parents) {
			foreach (explode(',', $demand->parents) as $key => $value) {
				$pidUidConstraints[] = $query->equals('pid', $value);
			}
		}
		if ($pidUidConstraints) {
			$constraints[] = $query->logicalOr(...$pidUidConstraints);
		}

		$categoriesConstraints = [];
		foreach ($demand->categories as $key => $group) {
			if ($group['uids'] ?? '') {
				$categoriesConstraints[] = $this->createCategoryConstraint($query, $group['uids'] ?? '', $group['conjunction'] ?? 'or');
			}
		}
		if ($categoriesConstraints) {
			$constraints[] = match (strtolower($demand->categoriesConjunction)) {
				'or' => $query->logicalOr(...$categoriesConstraints),
				'and' => $query->logicalAnd(...$categoriesConstraints),
				'notor' => $query->logicalNot($query->logicalOr(...$categoriesConstraints)),
				'notand' => $query->logicalNot($query->logicalAnd(...$categoriesConstraints))
			};
		}

		if ($demand->limit) {
			$query->setLimit($demand->limit);
		}
		if ($demand->offset) {
			$query->setOffset($demand->offset);
		}
		$orderDirection = $demand->orderDirection === 'desc' ? QueryInterface::ORDER_DESCENDING : QueryInterface::ORDER_ASCENDING;
		$query->setOrderings([$demand->orderField => $orderDirection, 'sorting' => $orderDirection]);
		if (!$constraints) {
			$constraints[] = $query->greaterThan('uid', 0);
		}

		$records = $query->matching($query->logicalAnd(...$constraints))->execute($returnRawQueryResult);
		if (!$returnRawQueryResult) {
			$records = $records->toArray();
		}

		if ($demand->orderByRecordsProperty) {
			$recordUids = explode(',', $demand->records);
			$notInUidsIterator = 0;
			$newArray = [];
			foreach ($records as $record) {
				$uid = is_array($record) ? $record['uid'] : $record->getUid();
				$selectionPosition = array_search($uid, $recordUids);
				if ($selectionPosition !== false) {
					$newArray[$selectionPosition] = $record;
				} else {
					$newArray[count($recordUids) + $notInUidsIterator] = $record;
					$notInUidsIterator++;
				}
			}
			ksort($newArray);
			return $newArray;
		}
		return $records;
	}

	/**
	 * Returns a category constraint created by
	 * a given list of categories and a junction string
	 *
	 * @param QueryInterface $query
	 * @param string|array $categories
	 * @param string $conjunction
	 * @return ConstraintInterface|null
	 */
	protected function createCategoryConstraint(
		QueryInterface $query,
		string|array   $categories,
		string         $conjunction,
	): ?ConstraintInterface
	{
		$constraint = null;
		$categoryConstraints = [];

		if (empty($conjunction)) {
			return null;
		}
		if (!is_array($categories)) {
			$categories = GeneralUtility::intExplode(',', $categories, true);
		}
		foreach ($categories as $category) {
			$categoryConstraints[] = $query->contains('categories', $category);
		}
		if ($categoryConstraints) {
			$constraint = match (strtolower($conjunction)) {
				'or' => $query->logicalOr(...$categoryConstraints),
				'and' => $query->logicalAnd(...$categoryConstraints),
				'notor' => $query->logicalNot($query->logicalOr(...$categoryConstraints)),
				'notand' => $query->logicalNot($query->logicalAnd(...$categoryConstraints))
			};
		}
		return $constraint;
	}
}