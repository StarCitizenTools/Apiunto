<?php

declare( strict_types=1 );

use MediaWiki\Extension\Apiunto\Repositories\RepositoryFactory;
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

	'Apiunto.RepositoryFactory' => static function ( MediaWikiServices $services ): RepositoryFactory {
		return new RepositoryFactory(
			$services->getHttpRequestFactory(),
			$services->getMainConfig(),
			$services->getMainWANObjectCache()
		);
	},
];
