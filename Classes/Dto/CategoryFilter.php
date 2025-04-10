<?php

namespace UBOS\MenuControls\Dto;

class CategoryFilter
{
	public function __construct(
		/**
		 * @var CategoryFilterItem[]
		 */
		public array               $items = [],
		public ?CategoryFilterItem $resetItem = null,
		public int                 $buildTree = 1
	)
	{
	}
}