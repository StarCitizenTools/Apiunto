<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Services;

use BatchRowIterator;
use MediaWiki\Extension\Apiunto\Repositories\AbstractRepository;
use MediaWiki\Title\Title;
use ObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;

class CachePurger {

	public function __construct(
		private readonly IConnectionProvider $dbProvider
	) {}

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

	public function purgeByPageId( int $pageId, ?string $cacheKey = null ): void {
		$dbw = $this->dbProvider->getPrimaryDatabase();
		$dbw->delete(
			'page_props',
			[
				'pp_page' => $pageId,
				'pp_propname' => [
					AbstractRepository::PROP_KEY,
					AbstractRepository::PROP_KEY_CACHE_TIME,
					AbstractRepository::PROP_KEY_CACHE_EXPIRES,
				],
			]
		);

		if ( $cacheKey ) {
			ObjectCache::getLocalClusterInstance()->delete( $cacheKey );
		}
	}
}
