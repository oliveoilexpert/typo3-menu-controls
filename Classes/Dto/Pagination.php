<?php

namespace UBOS\MenuControls\Dto;

/**
 * Data Transfer Object for pagination information.
 * Contains all necessary data for rendering pagination controls in templates.
 * Supports both standard pagination and load-more/infinite scroll variants.
 */
class Pagination
{
	/**
	 * @param int $currentPage Current page number
	 * @param PaginationItem|null $prev Previous page link
	 * @param PaginationItem|null $next Next page link
	 * @param PaginationItem[] $window Links for pages in the current window
	 * @param bool $separatorLeft Whether to show a separator before the window
	 * @param bool $separatorRight Whether to show a separator after the window
	 * @param string $separatorString Text to use for separators
	 * @param PaginationItem|null $first Link to the first page
	 * @param PaginationItem|null $last Link to the last page
	 * @param PaginationItem|null $loadMore Load more button for alternative pagination
	 * @param string $loadMoreTrigger Trigger type for load more (click or intersect)
	 */
	public function __construct(
		public int             $currentPage = 1,
		public ?PaginationItem $prev = null,
		public ?PaginationItem $next = null,
		public array           $window = [],
		public bool            $separatorLeft = false,
		public bool            $separatorRight = false,
		public string          $separatorString = '...',
		public ?PaginationItem $first = null,
		public ?PaginationItem $last = null,
		public ?PaginationItem $loadMore = null,
		public string          $loadMoreTrigger = 'click'
	)
	{
	}
}