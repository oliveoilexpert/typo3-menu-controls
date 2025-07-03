<?php

namespace UBOS\MenuControls\Dto;

/**
 * Data Transfer Object for a single category filter item.
 * Contains all information needed to render a filter item in templates.
 */
class CategoryFilterItem
{
	/**
	 * @param string $label Display text for the item
	 * @param string $url URL for the item link (default: '')
	 * @param string $fragmentUrl URL for AJAX fragment loading (default: '')
	 * @param CategoryFilterItem[] $children Child items for hierarchical filters (default: [])
	 * @param CategoryFilterItem|null $closeItem Item to close/deselect children (default: null)
	 * @param bool $active Whether this item is currently selected (default: false)
	 * @param bool $disabled Whether this item is disabled (default: false)
	 * @param bool $hasNoPotential Whether selecting this item would yield no results (default: false)
	 * @param array $activeChildren UIDs of active child categories (default: [])
	 * @param string $header Optional header text for the item (default: '')
	 */
	public function __construct(
		public string              $label,
		public string              $url = '',
		public string              $fragmentUrl = '',
		public array               $children = [],
		public ?CategoryFilterItem $closeItem = null,
		public bool                $active = false,
		public bool                $disabled = false,
		public bool                $hasNoPotential = false,
		public array               $activeChildren = [],
		public string              $header = '',
	)
	{
	}
}