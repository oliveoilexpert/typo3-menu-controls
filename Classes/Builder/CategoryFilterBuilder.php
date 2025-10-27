<?php

namespace UBOS\MenuControls\Builder;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderInterface;

use UBOS\MenuControls\Domain\Repository\CategoryRepository;
use UBOS\MenuControls\Domain\Repository\MenuDemandRepositoryInterface;
use UBOS\MenuControls\Dto\MenuDemand;
use UBOS\MenuControls\Dto\CategoryFilter;
use UBOS\MenuControls\Dto\CategoryFilterItem;

/**
 * Builder for category filter objects that can be used in templates.
 * Creates a hierarchical filter based on sys_category records.
 */
class CategoryFilterBuilder
{
	/**
	 * @param Request $request The current request
	 * @param UriBuilder $uriBuilder The controller URI builder
	 * @param string $menuActionName The controller action name for the menu
	 * @param MenuDemandRepositoryInterface|null $menuRepository Repository for checking potential results (optional)
	 * @param MenuDemand|null $menuDemand Demand object for checking potential results (optional)
	 * @param int $pluginContentRecordUid Content element UID for fragment links (default: 0)
	 * @param int $pluginFragmentPageType Page type for fragment requests (default: 0)
	 * @param null|Closure(array):string $fragmentUrlBuilder Custom URL builder for fragment URLs (optional)
	 */
	public function __construct(
		protected Request                        $request,
		protected UriBuilder                     $uriBuilder,
		protected string                         $menuActionName,
		protected ?MenuDemandRepositoryInterface $menuRepository = null,
		protected ?MenuDemand                    $menuDemand = null,
		protected int                            $pluginContentRecordUid = 0,
		protected int                            $pluginFragmentPageType = 0,
		protected ?\Closure						 $fragmentUrlBuilder = null
	)
	{
		$this->categoryRepository = GeneralUtility::makeInstance(CategoryRepository::class);
	}

	protected CategoryRepository $categoryRepository;

	protected string $activeCategories = '';

	/**
	 * Default settings for the category filter.
	 * @see configure() method for detailed explanation of each setting.
	 */
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
		// request argument name for plugin content element UID
		'pluginContentRecordUidArgumentKey' => '',
		'categoryOrder' => ['sorting' => QueryInterface::ORDER_ASCENDING],
		'categoryLabelField' => 'title',
		'categoryLabelFieldFallback' => 'title',
		'resetLabel' => 'Reset',
	];

	/**
	 * Configures the filter builder with custom settings.
	 *
	 * Available settings:
	 * - active: Whether to build the filter at all (default: true)
	 * - categories: Comma-separated UIDs of categories to include as filter items
	 * - treeCategories: Comma-separated UIDs of categories to include as parent nodes with children
	 * - multiSelect: Whether to allow selecting multiple categories at once (default: true)
	 * - buildTree: How many levels deep to build the category tree (default: 1)
	 * - disabledTree: How many levels deep tree items are disabled (default: 1)
	 *   Negative values invert the selection (lower levels disabled, upper levels enabled)
	 * - multiSelectTree: How many levels deep tree items allow multiple selection (default: 1)
	 *   Negative values invert the selection
	 * - buildTreeBelowEnabledInactive: Whether to build tree below inactive, non-disabled categories (default: false)
	 * - checkPotential: Check if selecting a category would yield any results (default: false)
	 * - unsetArguments: Arguments to remove when building filter URIs (default: ['page'])
	 * - demandCategoriesKey: Key of category list in demand->categories (default: '0')
	 * - pluginContentRecordUidArgumentKey: Request argument name for plugin content element UID
	 * - categoryOrder: Ordering of categories (default: sorting ASC)
	 * - categoryLabelField: Field to use for category labels (default: 'title')
	 * - categoryLabelFieldFallback: Fallback field for category labels (default: 'title')
	 * - resetLabel: Label for the reset filter item (default: 'Reset')
	 *
	 * @param array $settings Custom settings to override defaults
	 */
	public function configure(array $settings): self
	{
		$this->settings = array_merge($this->settings, $settings);
		$this->activeCategories =
			$this->request->hasArgument('demand')
				? $this->request->getArgument('demand')['categories'][$this->settings['demandCategoriesKey']]['uids'] ?? ''
				: '';
		return $this;
	}

	/**
	 * Builds a CategoryFilter object based on the current configuration.
	 * Creates a hierarchical structure of filter items based on sys_category records.
	 *
	 * @return CategoryFilter|null Returns null if filter is disabled or no categories configured
	 */
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
			$categories = $this->categoryRepository->findByUidList($this->settings['categories'], true);
			foreach ($categories as $category) {
				$filter->items[] = $this->buildFilterItem($category);
			}
		}

		if ($this->settings['treeCategories']) {
			$treeCategories = $this->categoryRepository->findByUidList($this->settings['treeCategories'], true);
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

	/**
	 * Adds the active category names to the page title.
	 * Useful for SEO to indicate the current filter state in the page title.
	 *
	 * @param PageTitleProviderInterface $titleProvider Title provider to modify
	 * @param string $divider Character(s) to use as divider between title and categories (default: '|')
	 */
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
		$categories = $this->categoryRepository->findByUidList($this->activeCategories, true);
		$pageTitleSuffixCategory =
			$divider .
			implode(', ', array_map(function (array $category) {
				return $this->getCategoryTitle($category);
			}, $categories));
		$titleProvider->setRequest($this->request);
		$titleProvider->setTitle($titleProvider->getTitle() . $pageTitleSuffixCategory);
		return $this;
	}

	/**
	 * Recursively builds a category tree item with its children.
	 * Handles the complex logic of active/disabled states and multi-selection.
	 *
	 * @param array $category The category record to build the tree item for
	 * @param int $buildTree How many levels deep to build the tree
	 * @param int $disabledTree How many levels are disabled (negative values invert selection)
	 * @param int $multiSelectTree How many levels allow multi-selection (negative values invert selection)
	 * @param array $activeSiblings UIDs of active sibling categories
	 * @param string $enabledParent UID of enabled parent category (if any)
	 */
	protected function buildTree(
		array    $category,
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
		$subCategories = $this->categoryRepository->findByParent($category['uid'], true);
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

		$buildTree--;
		foreach ($subCategories as $subCategory) {
			$item->children[] = $this->buildTree(
				$subCategory,
				$buildTree,
				$this->getTreeIteratorAdvancement($disabledTree),
				$this->getTreeIteratorAdvancement($multiSelectTree),
				$activeChildren,
				enabledParent: !$disabled ? (string)$category['uid'] : '',
			);
		}
		return $item;
	}

	/**
	 * Builds a single filter item for a category.
	 * Handles active states, URL generation, and potential result checking.
	 *
	 * @param array $category The category record
	 * @param bool $disabled Whether the item should be disabled (default: false)
	 * @param bool $multiSelect Whether the item allows multi-selection (default: false)
	 * @param array $activeChildren UIDs of active child categories (default: [])
	 * @param array $activeSiblings UIDs of active sibling categories (default: [])
	 * @param string $enabledParent UID of enabled parent category (default: '')
	 */
	protected function buildFilterItem(
		array    $category,
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

		$exclusiveArguments = $arguments;
		$exclusiveArguments['demand']['categories'][$this->settings['demandCategoriesKey']]['uids'] = $uid;
		$exclusiveItem = new CategoryFilterItem(
			label: $this->getCategoryTitle($category),
			url: $this->buildUri($exclusiveArguments),
			fragmentUrl: $this->buildUri($exclusiveArguments, true),
			active: true,
			activeChildren: [],
		);

		if ($newList) {
			$arguments['demand']['categories'][$this->settings['demandCategoriesKey']]['uids'] = $newList;
		} else {
			unset($arguments['demand']['categories'][$this->settings['demandCategoriesKey']]['uids']);
		}

		if ($this->settings['checkPotential'] && $this->menuRepository && $this->menuDemand) {
			$potentialDemand = clone $this->menuDemand;
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
			exclusiveItem: $exclusiveItem,
		);
	}

	/**
	 * Builds a URI for filter links
	 *
	 * @param array $arguments Request arguments for the URI
	 * @param bool $isFragmentUri Whether to build a fragment URI for AJAX requests (default: false)
	 */
	protected function buildUri(array $arguments, bool $isFragmentUri = false): string
	{
		if ($isFragmentUri && $this->fragmentUrlBuilder) {
			return ($this->fragmentUrlBuilder)($arguments);
		}
		if ($isFragmentUri && $this->settings['pluginContentRecordUidArgumentKey'] && $this->pluginContentRecordUid) {
			$arguments[$this->settings['pluginContentRecordUidArgumentKey']] = $this->pluginContentRecordUid;
		}
		return $this->uriBuilder
			->reset()
			->setCreateAbsoluteUri(!$isFragmentUri)
			->setTargetPageType($isFragmentUri ? $this->pluginFragmentPageType : 0)
			->setTargetPageUid($this->request->getAttribute('routing')->getPageId())
			->uriFor($this->menuActionName, $arguments);
	}

	/**
	 * Gets the title of a category using the configured label field
	 *
	 * @param array $category The category record
	 */
	protected function getCategoryTitle(array $category): string
	{
		return $category[$this->settings['categoryLabelField']] ?? $category[$this->settings['categoryLabelFieldFallback']] ?? $category['title'];
	}

	/**
	 * Calculates the next level value for tree iterators.
	 * Handles the complex logic of how disabled/multiselect levels change when going deeper.
	 *
	 * @param int $level The current level value
	 */
	protected function getTreeIteratorAdvancement(int $level): int
	{
		if ($level > 1) {
			return $level - 1;
		}
		if ($level < 0) {
			return $level + 1;
		}
		return 0;
	}
}