<?php

use MediaWiki\Extension\Apiunto\Services\CacheInfoResolver;
use MediaWiki\Extension\Apiunto\Services\CachePurger;
use MediaWiki\MediaWikiServices;

return [
	'Apiunto.CachePurger' => static function ( MediaWikiServices $services ): CachePurger {
		return new CachePurger(
			$services->getConnectionProvider(),
			$services->getMainWANObjectCache()
		);
	},

	'Apiunto.CacheInfoResolver' => static function ( MediaWikiServices $services ): CacheInfoResolver {
		return new CacheInfoResolver(
			$services->getMainWANObjectCache(),
			$services->getMainConfig()
		);
	},
];
