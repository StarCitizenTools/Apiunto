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

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\Apiunto\Repositories\AbstractRepository;
use MediaWiki\Hook\InfoActionHook;
use MediaWiki\Html\Html;
use MediaWiki\Page\PageProps;

class ActionHooks implements InfoActionHook {

	public function __construct(
		private readonly PageProps $pageProps
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onInfoAction( $context, &$pageInfo ): void {
		wfDebugLog( 'Apiunto', 'Running Info Action Hook' );

		$properties = $this->pageProps->getProperties(
			$context->getTitle(),
			AbstractRepository::PROP_KEY
		);

		$prop = $properties[$context->getTitle()->getArticleID()] ?? null;

		if ( !$prop ) {
			return;
		}

		$caches = json_decode( (string)$prop, true );
		if ( !is_array( $caches ) || $caches === [] ) {
			return;
		}

		foreach ( $caches as $cache ) {
			$pageInfo['header-apiunto'][] = [
				htmlspecialchars( $cache['source'] ),
				$this->buildCacheInfoList( $cache, $context ),
			];
		}
	}

	private function buildCacheInfoList( array $cache, IContextSource $context ): string {
		$items = [
			$this->buildCacheKeyItem( $cache, $context ),
			// $this->buildCacheTimeItem( $cache, $context ),
			// $this->buildCacheExpiresItem( $cache, $context ),
		];

		if ( isset( $cache['count'] ) && $cache['count'] > 1 ) {
			$items[] = $this->buildRequestCountItem( $cache, $context );
		}

		return Html::rawElement( 'ul', [], implode( '', $items ) );
	}

	private function buildCacheKeyItem( array $cache, IContextSource $context ): string {
		$label = Html::element( 'strong', [], $context->msg( 'apiunto-cache-key-info-label' )->text() . ': ' );
		$value = Html::element( 'code', [], $cache['key'] );

		return Html::rawElement( 'li', [], $label . $value );
	}

	private function buildCacheTimeItem( array $cache, IContextSource $context ): string {
		$lang = $context->getLanguage();
		$label = Html::element( 'strong', [], $context->msg( 'apiunto-cache-time-info-label' )->text() . ': ' );
		$value = Html::element( 'time', [], $lang->timeanddate( $cache['time'], true ) );

		return Html::rawElement( 'li', [], $label . $value );
	}

	private function buildCacheExpiresItem( array $cache, IContextSource $context ): string {
		$lang = $context->getLanguage();
		$label = Html::element( 'strong', [], $context->msg( 'apiunto-cache-expires-info-label' )->text() . ': ' );
		$value = Html::element( 'time', [], $lang->timeanddate( $cache['expires'], true ) );

		return Html::rawElement( 'li', [], $label . $value );
	}

	private function buildRequestCountItem( array $cache, IContextSource $context ): string {
		$label = Html::element( 'strong', [], $context->msg( 'apiunto-request-count-info-label' )->text() . ': ' );
		$value = htmlspecialchars( (string)$cache['count'] );

		return Html::rawElement( 'li', [], $label . $value );
	}
}
