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
use MediaWiki\Extension\Apiunto\Services\CacheInfoResolver;
use MediaWiki\Hook\InfoActionHook;
use MediaWiki\Html\Html;
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

		$entries = json_decode( (string)$prop, true );
		if ( !is_array( $entries ) || $entries === [] ) {
			return;
		}

		foreach ( $this->cacheInfoResolver->resolve( $entries ) as $row ) {
			$pageInfo['header-apiunto'][] = [
				htmlspecialchars( $row['source'] ),
				$this->buildCacheInfoList( $row, $context ),
			];
		}
	}

	/**
	 * @param array $row A resolved row from CacheInfoResolver::resolve()
	 * @param IContextSource $context
	 * @return string
	 */
	private function buildCacheInfoList( array $row, IContextSource $context ): string {
		$lang = $context->getLanguage();

		$items = [
			$this->buildItem(
				$context,
				'apiunto-cache-status-info-label',
				$this->statusText( $row['status'], $context )
			),
		];

		if ( $row['url'] !== null && $row['url'] !== '' ) {
			$items[] = $this->buildItem( $context, 'apiunto-cache-url-info-label', $row['url'], 'code' );
		}

		if ( $row['key'] !== null && $row['key'] !== '' ) {
			$items[] = $this->buildItem( $context, 'apiunto-cache-key-info-label', $row['key'], 'code' );
		}

		if ( $row['cachedOn'] !== null ) {
			$items[] = $this->buildItem(
				$context,
				'apiunto-cache-time-info-label',
				$lang->userTimeAndDate( (string)$row['cachedOn'], $context->getUser() ),
				'time'
			);
		}

		if ( $row['expiresOn'] !== null ) {
			$items[] = $this->buildItem(
				$context,
				'apiunto-cache-expires-info-label',
				$lang->userTimeAndDate( (string)$row['expiresOn'], $context->getUser() ),
				'time'
			);
		}

		$items[] = $this->buildItem(
			$context,
			'apiunto-cache-duration-info-label',
			$lang->formatDuration( $row['cacheDuration'] )
		);

		if ( $row['count'] > 1 ) {
			$items[] = $this->buildItem( $context, 'apiunto-request-count-info-label', (string)$row['count'] );
		}

		return Html::rawElement( 'ul', [], implode( '', $items ) );
	}

	/**
	 * Builds a single labelled list item.
	 *
	 * @param IContextSource $context
	 * @param string $labelKey Message key for the label
	 * @param string $value Plain-text value
	 * @param string $valueTag HTML tag to wrap the value in
	 * @return string
	 */
	private function buildItem(
		IContextSource $context,
		string $labelKey,
		string $value,
		string $valueTag = 'span'
	): string {
		$label = Html::element( 'strong', [], $context->msg( $labelKey )->text() . ': ' );
		$valueHtml = Html::element( $valueTag, [], $value );

		return Html::rawElement( 'li', [], $label . $valueHtml );
	}

	/**
	 * Maps a resolver status to its localized label.
	 *
	 * @param string $status
	 * @param IContextSource $context
	 * @return string
	 */
	private function statusText( string $status, IContextSource $context ): string {
		$key = match ( $status ) {
			CacheInfoResolver::STATUS_CACHED => 'apiunto-cache-status-cached',
			CacheInfoResolver::STATUS_DISABLED => 'apiunto-cache-status-disabled',
			default => 'apiunto-cache-status-not-cached',
		};

		return $context->msg( $key )->text();
	}
}
