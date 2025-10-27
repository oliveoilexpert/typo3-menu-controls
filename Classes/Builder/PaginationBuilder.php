<?php

namespace UBOS\MenuControls\Builder;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

use UBOS\MenuControls\Dto\Pagination;
use UBOS\MenuControls\Dto\PaginationItem;

/**
 * Builder for pagination objects that can be used in templates.
 * Supports different pagination variants like standard pagination,
 * load-more buttons and infinite scrolling.
 */
class PaginationBuilder
{
	/**
	 * @param array $records The records to paginate
	 * @param Request $request The current request
	 * @param UriBuilder $uriBuilder The controller URI builder
	 * @param string $menuActionName The controller action name for the menu
	 * @param int $pluginContentRecordUid Content element UID for fragment links (optional)
	 * @param int $pluginFragmentPageType Page type for fragment requests (optional)
	 * @param null|Closure(array):string $fragmentUrlBuilder Custom URL builder for fragment URLs (optional)
	 */
	public function __construct(
		protected array      $records,
		protected Request    $request,
		protected UriBuilder $uriBuilder,
		protected string     $menuActionName,
		protected int        $pluginContentRecordUid = 0,
		protected int        $pluginFragmentPageType = 0,
		protected ?\Closure  $fragmentUrlBuilder = null
	)
	{
	}

	protected ?SlidingWindowPagination $slidingWindowPagination = null;

	/**
	 * Default settings for the pagination builder.
	 * @see configure() method for detailed explanation of each setting.
	 */
	protected array $settings = [
		'pageArgumentKey' => 'page',
		'itemsPerPage' => 12,
		'maximumLinks' => 3,
		'variant' => ''
	];

	/**
	 * Configures the pagination builder with custom settings.
	 * Uses fluent interface pattern to allow method chaining.
	 *
	 * Available settings:
	 * - pageArgumentKey: Request argument name for the page number
	 * - itemsPerPage: Number of items to display per page (default: 12)
	 * - maximumLinks: Maximum number of page links to show in pagination window (default: 3)
	 * - variant: Pagination style ('', 'load-more', or 'infinite-scroll')
	 *   - Empty string: Standard numbered pagination
	 *   - 'load-more': Defines a "load more" pagination item
	 *   - 'infinite-scroll': Defines a "load more" pagination item with trigger "intersect"
	 *
	 * @param array $settings Custom settings to override defaults
	 */
	public function configure(array $settings): self
	{
		$this->settings = array_merge($this->settings, $settings);
		$this->slidingWindowPagination = null;
		return $this;
	}

	/**
	 * Builds a Pagination object based on the current configuration.
	 * Returns different pagination structures based on the selected variant.
	 */
	public function build(): Pagination
	{
		$loadMoreArgs = $this->request->getArguments();
		unset($loadMoreArgs['recordUid']);
		$swp = $this->getSlidingWindowPagination();
		$loadMoreArgs[$this->settings['pageArgumentKey']] = $swp->getNextPageNumber();
		return match ($this->settings['variant']) {
			'load-more', 'infinite-scroll' => new Pagination(
				loadMore: new PaginationItem(
					label: '+',
					url: $this->buildUri($loadMoreArgs),
					fragmentUrl: $this->buildUri($loadMoreArgs, true),
					disabled: !$swp->getNextPageNumber()
				),
				loadMoreTrigger: $this->settings['variant'] === 'infinite-scroll' ? 'intersect' : 'click',
			),
			default => new Pagination(
				currentPage: $swp->getPaginator()->getCurrentPageNumber(),
				prev: $this->buildItem($swp->getPreviousPageNumber(), '<'),
				next: $this->buildItem($swp->getNextPageNumber(), '>'),
				window: array_map(
					function ($page) {
						return $this->buildItem($page);
					},
					$swp->getAllPageNumbers()
				),
				separatorLeft: $swp->getHasLessPages(),
				separatorRight: $swp->getHasMorePages(),
				first: $swp->getFirstPageNumber() < $swp->getDisplayRangeStart() ? $this->buildItem($swp->getFirstPageNumber()) : null,
				last: $swp->getLastPageNumber() > $swp->getDisplayRangeEnd() ? $this->buildItem($swp->getLastPageNumber()) : null,
			)
		};
	}

	/**
	 * Returns the paginated items for the current page
	 */
	public function getPaginatedItems(): array
	{
		return $this->getSlidingWindowPagination()->getPaginator()->getPaginatedItems();
	}

	/**
	 * Adds prev/next links to the HTML head for SEO optimization
	 */
	public function addPaginationLinksToHead(): self
	{
		$arguments = $this->request->getArguments();
		$pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
		if ($this->getSlidingWindowPagination()->getPreviousPageNumber()) {
			$arguments[$this->settings['pageArgumentKey']] = $this->getSlidingWindowPagination()->getPreviousPageNumber();
			$pageRenderer->addHeaderData('<link rel="prev" href="' . $this->buildUri($arguments) . '" />');
		}
		if ($this->getSlidingWindowPagination()->getNextPageNumber()) {
			$arguments[$this->settings['pageArgumentKey']] = $this->getSlidingWindowPagination()->getNextPageNumber();
			$pageRenderer->addHeaderData('<link rel="next" href="' . $this->buildUri($arguments) . '" />');
		}
		return $this;
	}

	/**
	 * Gets or creates the SlidingWindowPagination instance
	 */
	public function getSlidingWindowPagination(): SlidingWindowPagination
	{
		if (!$this->slidingWindowPagination) {
			$paginator = new ArrayPaginator(
				$this->records,
				intval($this->request->getArguments()[$this->settings['pageArgumentKey']] ?? '1'),
				$this->settings['itemsPerPage']
			);
			$this->slidingWindowPagination = new SlidingWindowPagination($paginator, $this->settings['maximumLinks']);
		}
		return $this->slidingWindowPagination;
	}

	/**
	 * Builds a pagination item for a specific page
	 *
	 * @param int|null $page Page number
	 * @param string $label Custom label (uses page number if empty)
	 */
	protected function buildItem(?int $page, string $label = ''): ?PaginationItem
	{
		if (!$page) {
			return null;
		}
		$arguments = $this->request->getArguments();
		$active = $page == intval($arguments[$this->settings['pageArgumentKey']] ?? '1');
		unset($arguments['recordUid']);
		if ($page === 1) {
			unset($arguments[$this->settings['pageArgumentKey']]);
		} else {
			$arguments[$this->settings['pageArgumentKey']] = $page;
		}
		return new PaginationItem(
			label: $label ?: $page,
			url: $this->buildUri($arguments),
			fragmentUrl: $this->buildUri($arguments, true),
			active: $active,
		);
	}

	/**
	 * Builds a URI for pagination links
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
}