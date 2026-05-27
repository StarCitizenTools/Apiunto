<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Services;

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

/**
 * Renders a resolved cache-info row (from CacheInfoResolver) to the InfoAction HTML list.
 */
class CacheInfoFormatter {

	/**
	 * @param array $row A resolved row from CacheInfoResolver::resolve()
	 */
	public function format( array $row, IContextSource $context ): string {
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

	private function statusText( string $status, IContextSource $context ): string {
		$key = match ( $status ) {
			CacheInfoResolver::STATUS_CACHED => 'apiunto-cache-status-cached',
			CacheInfoResolver::STATUS_DISABLED => 'apiunto-cache-status-disabled',
			default => 'apiunto-cache-status-not-cached',
		};

		return $context->msg( $key )->text();
	}
}
