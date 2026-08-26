<?php

use Amdeu\MenuControls\Domain\Model;

return [
	Model\Category::class => [
		'tableName' => 'sys_category',
	],
	Model\Page::class => [
		'tableName' => 'pages',
		'properties' => [
			'lastUpdated' => [
				'fieldName' => 'lastUpdated',
			],
		],
	],
];