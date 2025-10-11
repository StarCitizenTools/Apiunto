<?php

use MediaWiki\Extension\Apiunto\Services\CachePurger;
use MediaWiki\MediaWikiServices;

return [
	'Apiunto.CachePurger' => static function ( MediaWikiServices $services ): CachePurger {
		return new CachePurger(
			$services->getConnectionProvider()
		);
	},
];
