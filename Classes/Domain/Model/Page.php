<?php

namespace Amdeu\MenuControls\Domain\Model;

use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;

/**
 * Extbase model for 'pages' records.
 * Covers the fields commonly needed to render a page in a teaser menu
 */
class Page extends AbstractEntity
{
	public string $title = '';

	public string $subtitle = '';

	public string $navTitle = '';

	public string $slug = '';

	public int $doktype = 1;

	public bool $navHide = false;

	public string $abstract = '';

	public string $description = '';

	public string $keywords = '';

	public string $author = '';

	public string $authorEmail = '';

	/**
	 * Target for doktype "External Link" (link field).
	 */
	public string $link = '';

	/**
	 * Target uid for doktype "Shortcut".
	 */
	public int $shortcut = 0;

	public int $shortcutMode = 0;

	public ?\DateTime $lastUpdated = null;

	/**
	 * @var ObjectStorage<FileReference>|null
	 */
	#[Lazy]
	public ?ObjectStorage $media = null;

	/**
	 * @var ObjectStorage<Category>|null
	 */
	#[Lazy]
	public ?ObjectStorage $categories = null;
}
