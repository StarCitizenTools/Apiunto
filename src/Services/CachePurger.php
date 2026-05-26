<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Services;

use BatchRowIterator;
use MediaWiki\Extension\Apiunto\Repositories\AbstractRepository;
use MediaWiki\Title\Title;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;

class CachePurger {

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly WANObjectCache $cache
	) {
	}

	public function purgeAll( callable $logger, bool $dryRun = false, int $batchSize = 150 ): void {
		$iterator = new BatchRowIterator(
			$this->dbProvider->getReplicaDatabase(),
			'page_props',
			[ 'pp_page', 'pp_value' ],
			$batchSize
		);
		$iterator->addConditions( [ 'pp_propname' => AbstractRepository::PROP_KEY ] );
		$iterator->setCaller( __METHOD__ );

		foreach ( $iterator as $batch ) {
			foreach ( $batch as $row ) {
				$title = Title::newFromID( $row->pp_page );
				if ( !$title ) {
					continue;
				}

				$logMessage = sprintf( 'Purging Apiunto cache for title %s', $title->getText() );
				if ( $dryRun ) {
					$logger( '(Not) ' . $logMessage );
				} else {
					$logger( $logMessage );
				}

				if ( !$dryRun ) {
					$this->purgeByPageId( (int)$row->pp_page, $row->pp_value );
				}
			}
		}
	}

	public function purgeByPageId( int $pageId, ?string $propertyValue = null ): void {
		if ( $propertyValue === null ) {
			$dbr = $this->dbProvider->getReplicaDatabase();
			$propertyValue = $dbr->selectField(
				'page_props',
				'pp_value',
				[ 'pp_page' => $pageId, 'pp_propname' => AbstractRepository::PROP_KEY ],
				__METHOD__
			);
		}

		if ( !$propertyValue ) {
			return;
		}

		$caches = json_decode( (string)$propertyValue, true );
		if ( !is_array( $caches ) ) {
			return;
		}

		foreach ( $caches as $cacheInfo ) {
			if ( isset( $cacheInfo['key'] ) ) {
				// HOLDOFF_TTL_NONE: cached responses come from an external API, not a
				// DB replica, so there is no replica-lag window to guard against. Use a
				// non-volatile purge so the next fetch can repopulate the cache immediately.
				$this->cache->delete( $cacheInfo['key'], WANObjectCache::HOLDOFF_TTL_NONE );
			}
		}

		// The apiuntocache page property is parser-derived metadata owned by the
		// parse/LinksUpdate lifecycle. It is deliberately left untouched here: it records
		// which cache keys the page populates (used for purging and shown on action=info),
		// is rewritten on every reparse, and a plain purge does not trigger a LinksUpdate to
		// restore it. Deleting it would make the keys un-purgeable and hide the info section
		// until the next edit.
	}
}
