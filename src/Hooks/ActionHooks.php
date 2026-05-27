<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Hooks;

use MediaWiki\Extension\Apiunto\CacheManifest;
use MediaWiki\Extension\Apiunto\Repositories\AbstractRepository;
use MediaWiki\Extension\Apiunto\Services\CacheInfoFormatter;
use MediaWiki\Extension\Apiunto\Services\CacheInfoResolver;
use MediaWiki\Hook\InfoActionHook;
use MediaWiki\Page\PageProps;

class ActionHooks implements InfoActionHook {

	public function __construct(
		private readonly PageProps $pageProps,
		private readonly CacheInfoResolver $cacheInfoResolver
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onInfoAction( $context, &$pageInfo ): void {
		$properties = $this->pageProps->getProperties(
			$context->getTitle(),
			AbstractRepository::PROP_KEY
		);

		$prop = $properties[$context->getTitle()->getArticleID()] ?? null;
		if ( !$prop ) {
			return;
		}

		$entries = CacheManifest::decode( $prop );
		if ( $entries === [] ) {
			return;
		}

		$formatter = new CacheInfoFormatter();
		foreach ( $this->cacheInfoResolver->resolve( $entries ) as $row ) {
			$pageInfo['header-apiunto'][] = [
				htmlspecialchars( $row['source'] ),
				$formatter->format( $row, $context ),
			];
		}
	}
}
