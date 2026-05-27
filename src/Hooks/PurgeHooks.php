<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Hooks;

use MediaWiki\Extension\Apiunto\Services\CachePurger;
use MediaWiki\Page\Hook\ArticlePurgeHook;

class PurgeHooks implements ArticlePurgeHook {

	public function __construct(
		private readonly CachePurger $cachePurger
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onArticlePurge( $wikiPage ): void {
		wfDebugLog( 'Apiunto', 'Running Purge Hook' );

		$this->cachePurger->purgeByPageId( $wikiPage->getId() );
	}
}
