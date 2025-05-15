<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Hooks;

use MediaWiki\Extension\Apiunto\Repositories\AbstractRepository;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageProps;
use MediaWiki\Page\Hook\ArticlePurgeHook;
use ObjectCache;
use ObjectCacheFactory;

class PurgeHooks implements ArticlePurgeHook {

	public function __construct(
		private readonly ObjectCacheFactory $objectCacheFactory,
		private readonly PageProps $pageProps
	) {}

	/**
	 * @inheritDoc
	 */
	public function onArticlePurge( $wikiPage ): void {
		wfDebugLog( 'Apiunto', 'Running Purge Hook' );

		if ( $wikiPage->getTitle() === null ) {
			return;
		}

		$key = $this->pageProps->getProperties(
			$wikiPage,
			AbstractRepository::PROP_KEY
		);

		if ( empty( $key ) ) {
			wfDebugLog( 'Apiunto', sprintf( 'No "%s" cache key found.', AbstractRepository::PROP_KEY ) );
			return;
		}

		$key = array_shift( $key );

		wfDebugLog( 'Apiunto', sprintf( 'Deleting cache key %s', $key ) );

		$success = $this->objectCacheFactory->getLocalClusterInstance()->delete( $key );

		wfDebugLog( 'Apiunto', sprintf( 'Cache deletion was%s successful.', !$success ?: ' not' ) );
	}
}
