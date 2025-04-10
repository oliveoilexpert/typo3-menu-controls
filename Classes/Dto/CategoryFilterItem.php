<?php

namespace UBOS\MenuControls\Dto;

class CategoryFilterItem
{
	public function __construct(
		public string              $label,
		public string              $url = '',
		public string              $fragmentUrl = '',
		/**
		 * @var CategoryFilterItem[]
		 */
		public array               $children = [],
		public ?CategoryFilterItem $closeItem = null,
		public bool                $active = false,
		public bool                $disabled = false,
		public bool                $hasNoPotential = false,
		public array               $activeChildren = [],
	)
	{
	}

}