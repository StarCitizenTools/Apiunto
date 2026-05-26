<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Tests\Unit\Services;

use MediaWiki\Extension\Apiunto\Services\CachePurger;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @group Apiunto
 * @group Services
 * @covers \MediaWiki\Extension\Apiunto\Services\CachePurger
 */
class CachePurgerTest extends MediaWikiUnitTestCase {

	/**
	 * The fetch path stores responses through WANObjectCache, which keeps the value under a
	 * sister key rather than the bare key. Purging must therefore go through the same
	 * WANObjectCache instance; deleting the bare key from a raw BagOStuff is a silent no-op
	 * and leaves the response cached.
	 */
	public function testPurgeByPageIdEvictsWanCachedValue(): void {
		$wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$key = $wanCache->makeKey( 'ext', 'apiuntocache', 'https://example.test/v1/golem' );
		$wanCache->set( $key, 'cached-response', 3600 );
		$this->assertSame( 'cached-response', $wanCache->get( $key ), 'precondition: value is cached' );

		$primary = $this->createMock( IDatabase::class );
		$primary->expects( $this->once() )->method( 'delete' );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getPrimaryDatabase' )->willReturn( $primary );

		$propertyValue = json_encode( [
			[ 'source' => 'test', 'key' => $key, 'count' => 1 ],
		] );

		$purger = new CachePurger( $dbProvider, $wanCache );
		$purger->purgeByPageId( 123, $propertyValue );

		$this->assertFalse( $wanCache->get( $key ), 'value must be gone after purge' );
	}

	/**
	 * The ArticlePurge hook calls purgeByPageId() without a property value, so the keys must be
	 * read from page_props on the replica before purging.
	 */
	public function testPurgeByPageIdReadsPagePropsWhenPropertyValueOmitted(): void {
		$wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$key = $wanCache->makeKey( 'ext', 'apiuntocache', 'https://example.test/v1/golem' );
		$wanCache->set( $key, 'cached-response', 3600 );
		$this->assertSame( 'cached-response', $wanCache->get( $key ), 'precondition: value is cached' );

		$replica = $this->createMock( IReadableDatabase::class );
		$replica->expects( $this->once() )
			->method( 'selectField' )
			->willReturn( json_encode( [
				[ 'source' => 'test', 'key' => $key, 'count' => 1 ],
			] ) );

		$primary = $this->createMock( IDatabase::class );
		$primary->expects( $this->once() )->method( 'delete' );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getReplicaDatabase' )->willReturn( $replica );
		$dbProvider->method( 'getPrimaryDatabase' )->willReturn( $primary );

		$purger = new CachePurger( $dbProvider, $wanCache );
		$purger->purgeByPageId( 123 );

		$this->assertFalse( $wanCache->get( $key ), 'value must be gone after purge via page_props lookup' );
	}
}
