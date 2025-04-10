<?php

namespace UBOS\MenuControls\Dto;

/**
 * Data Transfer Object for a single pagination link.
 * Contains all information needed to render a pagination item in templates.
 */
class PaginationItem
{
	/**
	 * @param string $label Display text for the item
	 * @param string $url URL for the item link
	 * @param string $fragmentUrl URL for AJAX fragment loading
	 * @param bool $active Whether this item represents the current page
	 * @param bool $disabled Whether this item is disabled
	 */
	public function __construct(
		public string $label,
		public string $url = '',
		public string $fragmentUrl = '',
		public bool   $active = false,
		public bool   $disabled = false,
	)
	{
	}
}