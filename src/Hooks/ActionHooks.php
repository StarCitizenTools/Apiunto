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
