<?php

namespace UBOS\MenuControls;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

use UBOS\MenuControls\Dto\Pagination;
use UBOS\MenuControls\Dto\PaginationItem;

class PaginationBuilder
{
	protected array $settings = [
		'pageArgumentKey' => 'page',
		'pluginContentRecordUidArgumentKey' => '',
		'itemsPerPage' => 12,
		'maximumLinks' => 3,
		'variant' => ''
	];
	protected ?SlidingWindowPagination $slidingWindowPagination = null;

	public function __construct(
		protected array      $records,
		protected Request    $request,
		protected UriBuilder $uriBuilder,
		protected string     $menuActionName,
		protected int        $pluginContentRecordUid = 0,
		protected int        $pluginFragmentPageType = 0,
	)
	{
	}

	public function configure(array $settings): self
	{
		$this->settings = array_merge($this->settings, $settings);
		$this->slidingWindowPagination = null;
		return $this;
	}

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

	public function getPaginatedItems(): array
	{
		return $this->getSlidingWindowPagination()->getPaginator()->getPaginatedItems();
	}

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

	protected function buildUri(array $arguments, bool $isFragmentUri = false): string
	{
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