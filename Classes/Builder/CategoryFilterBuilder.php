<?php

namespace UBOS\MenuControls;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderInterface;

use UBOS\MenuControls\Domain\Repository\CategoryRepository;
use UBOS\MenuControls\Dto\MenuDemand;
use UBOS\MenuControls\Dto\CategoryFilter;
use UBOS\MenuControls\Dto\CategoryFilterItem;

class CategoryFilterBuilder
{
	protected array $settings = [
		// whether to build the filter
		'active' => true,
		// category uids to build items from
		'categories' => '',
		// category uids to build items with children from
		'treeCategories' => '',
		// whether to allow multiselect on the filter
		'multiSelect' => true,
		// levels deep tree is built below a parent category
		'buildTree' => 1,
		// levels deep tree items are disabled, negative values invert selection
		'disabledTree' => 1,
		// levels deep tree is multiselectable, negative values invert selection
		'multiSelectTree' => 1,
		// whether to build tree below inactive, non-disabled category
		'buildTreeBelowEnabledInactive' => false,
		// check if there are any results for a category
		'checkPotential' => false,
		// other arguments to remove when building a filter uri
		'unsetArguments' => ['page', 'recordUid'],
		// key of category list in demand->categories
		'demandCategoriesKey' => '0',
		'categoryOrder' => ['sorting' => QueryInterface::ORDER_ASCENDING],
		'categoryLabelField' => 'title',
		'categoryLabelFieldFallback' => 'title',
		'resetLabel' => 'Reset',
	];

	protected string $activeCategories = '';

	public function __construct(
		protected Request                        $request,
		protected UriBuilder                     $uriBuilder,
		protected CategoryRepository             $categoryRepository,
		protected string                         $menuActionName,
		protected ?MenuDemandRepositoryInterface $menuRepository = null,
		protected ?MenuDemand                    $menuDemand = null,
		protected int                            $pluginContentRecordUid = 0,
		protected int                            $pluginFragmentPageType = 0,
	)
	{
	}

	public function configure(array $settings): self
	{
		$this->settings = array_merge($this->settings, $settings);
		$this->activeCategories =
			$this->request->hasArgument('demand')
				? $this->request->getArgument('demand')['categories'][$this->settings['demandCategoriesKey']]['uids'] ?? ''
				: '';
		return $this;
	}

	public function build(): ?CategoryFilter
	{
		if (!$this->settings['active'] || (!$this->settings['categories'] && !$this->settings['treeCategories'])) {
			return null;
		}
		$filter = new CategoryFilter(
			buildTree: (int)$this->settings['buildTree'],
		);

		$arguments = $this->request->getArguments();

		foreach ($this->settings['unsetArguments'] as $unsetArgument) {
			unset($arguments[$unsetArgument]);
		}

		if ($this->activeCategories) {
			$resetArguments = $arguments;
			unset($resetArguments['demand']['categories'][$this->settings['demandCategoriesKey']]['uids']);
			$filter->resetItem = new CategoryFilterItem(
				label: $this->settings['resetLabel'],
				url: $this->buildUri($resetArguments),
				fragmentUrl: $this->buildUri($resetArguments, true),
			);
		}

		$this->categoryRepository->setDefaultOrderings($this->settings['categoryOrder']);

		if ($this->settings['categories']) {
			$categories = $this->categoryRepository->findByUidList($this->settings['categories'], true)->toArray();
			foreach ($categories as $category) {
				$filter->items[] = $this->buildFilterItem($category);
			}
		}

		if ($this->settings['treeCategories']) {
			$treeCategories = $this->categoryRepository->findByUidList($this->settings['treeCategories'], true)->toArray();
			foreach ($treeCategories as $category) {
				$filter->items[] = $this->buildTree(
					$category,
					(int)$this->settings['buildTree'],
					(int)$this->settings['disabledTree'],
					(int)$this->settings['multiSelectTree']
				);
			}
		}

		return $filter;
	}

	public function addCategorySuffixToPageTitle(
		PageTitleProviderInterface $titleProvider,
		string $divider = '|'
	): self
	{
		if (!$this->activeCategories) {
			return $this;
		}
		if (!method_exists($titleProvider, 'setTitle')) {
			return $this;
		}
		$query = $this->categoryRepository->createQuery();
		$query->getQuerySettings()->setRespectStoragePage(false);
		$categories = $query
			->matching($query->in('uid', explode(',', $this->activeCategories)))
			->execute()->toArray();
		$pageTitleSuffixCategory =
			$divider .
			implode(', ', array_map(function (array $category) {
				return $this->getCategoryTitle($category);
			}, $categories));
		$titleProvider->setRequest($this->request);
		$titleProvider->setTitle($titleProvider->getTitle() . $pageTitleSuffixCategory);
		return $this;
	}

	protected function buildTree(
		array 	 $category,
		int      $buildTree,
		int      $disabledTree,
		int      $multiSelectTree,
		array    $activeSiblings = [],
		string   $enabledParent = '',
	): CategoryFilterItem
	{
		$disabled = $disabledTree > 0;
		$multiSelect = $multiSelectTree > 0;
		$isActive = $this->activeCategories && GeneralUtility::inList($this->activeCategories, (string)$category['uid']);
		if (!$buildTree || (!$this->settings['buildTreeBelowEnabledInactive'] && !$disabled && !$isActive)) {
			return $this->buildFilterItem(
				$category,
				$disabled,
				$multiSelect,
				[],
				$activeSiblings,
				$enabledParent
			);
		}

		$activeChildren = [];
		$subCategories = $this->categoryRepository->findByParent($category['uid'], true)->toArray();
		foreach ($subCategories as $subCategory) {
			if (GeneralUtility::inList($this->activeCategories, (string)$subCategory['uid'])) {
				$activeChildren[] = $subCategory['uid'];
			}
		}

		$item = $this->buildFilterItem(
			$category,
			$disabled,
			$multiSelect,
			$activeChildren,
			$activeSiblings,
			$enabledParent
		);

		foreach ($subCategories as $subCategory) {
			$item->children[] = $this->buildTree(
				$subCategory,
				$buildTree--,
				$this->getTreeIteratorAdvancement($disabledTree),
				$this->getTreeIteratorAdvancement($multiSelectTree),
				$activeChildren,
				enabledParent: !$disabled ? (string)$category['uid'] : '',
			);
		}
		return $item;
	}

	protected function buildFilterItem(
		array 	 $category,
		bool     $disabled = false,
		bool     $multiSelect = false,
		array    $activeChildren = [],
		array    $activeSiblings = [],
		string   $enabledParent = '',
	): CategoryFilterItem
	{
		$arguments = $this->request->getArguments();
		$uid = (string)$category['uid'];
		foreach ($this->settings['unsetArguments'] as $unsetArgument) {
			unset($arguments[$unsetArgument]);
		}

		$isActive = $this->activeCategories && GeneralUtility::inList($this->activeCategories, $uid);

		if ($activeChildren) {
			$closeArguments = $arguments;
			$closeList = implode(',', array_diff(explode(',', $this->activeCategories), $activeChildren));
			if ($closeList) {
				$closeArguments['demand']['categories'][$this->settings['demandCategoriesKey']]['uids'] = $closeList;
			} else {
				unset($closeArguments['demand']['categories'][$this->settings['demandCategoriesKey']]['uids']);
			}
			$closeItem = new CategoryFilterItem(
				label: (string)count($activeChildren),
				url: $this->buildUri($closeArguments),
				fragmentUrl: $this->buildUri($closeArguments, true),
			);
		}

		if ($disabled) {
			return new CategoryFilterItem(
				label: $this->getCategoryTitle($category),
				closeItem: $closeItem ?? null,
				active: $isActive,
				activeChildren: $activeChildren,
			);
		}

		if ($isActive) {
			if ($this->activeCategories == (string)$category['uid'] || !$this->settings['multiSelect']) {
				$newList = $enabledParent;
			} else {
				$newList = implode(',', array_diff(explode(',', $this->activeCategories), [$uid]));
			}
		} else {
			if (!$this->settings['multiSelect']) {
				$newList = $uid;
			} else if ($multiSelect) {
				$newList = $this->activeCategories ? $this->activeCategories . ',' . $uid : $uid;
			} else {
				$newList = implode(',', array_diff(explode(',', $this->activeCategories), $activeSiblings)) . ',' . $uid;
			}
		}

		if ($newList) {
			$arguments['demand']['categories'][$this->settings['demandCategoriesKey']]['uids'] = $newList;
		} else {
			unset($arguments['demand']['categories'][$this->settings['demandCategoriesKey']]['uids']);
		}

		if ($this->settings['checkPotential'] && $this->menuRepository && $this->menuDemand) {
			$potentialDemand = $this->menuDemand;
			$potentialDemand->categories[$this->settings['demandCategoriesKey']]['uids'] = $newList;
			$potentialDemand->limit = 1;
			$hasNoPotential = !$this->menuRepository->findByMenuDemand($potentialDemand, returnRawQueryResult: true);
		}

		return new CategoryFilterItem(
			label: $this->getCategoryTitle($category),
			url: $this->buildUri($arguments),
			fragmentUrl: $this->buildUri($arguments, true),
			closeItem: $closeItem ?? null,
			active: $isActive,
			hasNoPotential: $hasNoPotential ?? false,
			activeChildren: $activeChildren,
		);
	}

	protected function buildUri(array $arguments, bool $isFragmentUri = false): string
	{
		if ($isFragmentUri && $this->pluginContentRecordUid) {
			$arguments['recordUid'] = $this->pluginContentRecordUid;
		}
		return $this->uriBuilder
			->reset()
			->setCreateAbsoluteUri(!$isFragmentUri)
			->setTargetPageType($isFragmentUri ? $this->pluginFragmentPageType : 0)
			->setTargetPageUid($this->request->getAttribute('routing')->getPageId())
			->uriFor($this->menuActionName, $arguments);
	}

	protected function getCategoryTitle(array $category): string
	{
		return $category[$this->settings['categoryLabelField']] ?? $category[$this->settings['categoryLabelFieldFallback']] ?? $category['title'];
	}

	protected function getTreeIteratorAdvancement(int $level): int
	{
		if ($level > 1) {
			$level--;
		} else if ($level == 1) {
			$level -= 2;
		} else if ($level < -1) {
			$level++;
		} else if ($level == -1) {
			$level += 2;
		}
		return $level;
	}
}