<?php

namespace UBOS\MenuControls\Dto;

/**
 * Data Transfer Object for category filter information.
 * Contains all necessary data for rendering category filter controls in templates.
 */
class CategoryFilter
{
	/**
	 * @param CategoryFilterItem[] $items The filter items
	 * @param CategoryFilterItem|null $resetItem Item to reset the filter (default: null)
	 * @param int $buildTree How many levels deep to build the tree (default: 1)
	 */
	public function __construct(
		public array               $items = [],
		public ?CategoryFilterItem $resetItem = null,
		public int                 $buildTree = 1
	)
	{
	}
}