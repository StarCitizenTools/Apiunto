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
use MediaWiki\Hook\InfoActionHook;
use MediaWiki\Page\PageProps;

class ActionHooks implements InfoActionHook  {

	public function __construct(
		private readonly PageProps $pageProps
	) {}

	/**
	 * @inheritDoc
	 */
	public function onInfoAction( $context, &$pageInfo ): void {
		wfDebugLog( 'Apiunto', 'Running Info Action Hook' );

		$properties = $this->pageProps->getProperties(
			$context->getTitle(),
			[
				AbstractRepository::PROP_KEY,
				AbstractRepository::PROP_KEY_CACHE_TIME,
				AbstractRepository::PROP_KEY_CACHE_EXPIRES
			]
		);

		$properties = array_shift( $properties );

		if ( $properties === [] ) {
			return;
		}

		if ( isset( $properties[ AbstractRepository::PROP_KEY ] ) ) {
			$pageInfo['header-apiunto'][] = [
				$context->msg( 'apiunto-cache-key-info-label' ),
				$properties[ AbstractRepository::PROP_KEY ]
			];
		}

		if ( isset( $properties[ AbstractRepository::PROP_KEY_CACHE_TIME ] ) ) {
			$pageInfo['header-apiunto'][] = [
				$context->msg( 'apiunto-cache-time-info-label' ),
				$context->getLanguage()->timeanddate( $properties[ AbstractRepository::PROP_KEY_CACHE_TIME ], true, true, true )
			];
		}

		if ( isset( $properties[ AbstractRepository::PROP_KEY_CACHE_EXPIRES ] ) ) {
			$pageInfo['header-apiunto'][] = [
				$context->msg( 'apiunto-cache-expires-info-label' ),
				$context->getLanguage()->timeanddate( $properties[ AbstractRepository::PROP_KEY_CACHE_EXPIRES ], true, true, true )
			];
		}
	}
}
