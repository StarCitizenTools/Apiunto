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
