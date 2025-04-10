<?php

namespace UBOS\MenuControls\Dto;

class PaginationItem
{
	public function __construct(
		public string            $label,
		public string            $url = '',
		public string 			 $fragmentUrl = '',
		public bool              $active = false,
		public bool              $disabled = false,
	)
	{
	}

}