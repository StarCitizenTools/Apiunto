<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Tests\Unit\Services;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Apiunto\Services\CacheInfoResolver;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @group Apiunto
 * @group Services
 * @covers \MediaWiki\Extension\Apiunto\Services\CacheInfoResolver
 */
class CacheInfoResolverTest extends MediaWikiUnitTestCase {

	private function newWanCache(): WANObjectCache {
		return new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	private function newConfig( bool $enable, array $sources ): HashConfig {
		return new HashConfig( [
			'ApiuntoEnableCache' => $enable,
			'ApiuntoSources' => $sources,
		] );
	}

	public function testResolvesCachedEntry(): void {
		$wan = $this->newWanCache();
		$key = $wan->makeKey( 'ext', 'apiuntocache', sha1( 'https://example.test/v1/golem' ) );
		$wan->set( $key, 'data', 3600 );

		$resolver = new CacheInfoResolver(
			$wan,
			$this->newConfig( true, [ 'src' => [ 'cacheDuration' => 7200 ] ] )
		);

		$rows = $resolver->resolve( [
			[ 'source' => 'src', 'key' => $key, 'url' => 'https://example.test/v1/golem', 'count' => 2 ],
		] );

		$this->assertCount( 1, $rows );
		$row = $rows[0];
		$this->assertSame( CacheInfoResolver::STATUS_CACHED, $row['status'] );
		$this->assertSame( 'src', $row['source'] );
		$this->assertSame( 'https://example.test/v1/golem', $row['url'] );
		$this->assertSame( $key, $row['key'] );
		$this->assertSame( 2, $row['count'] );
		$this->assertSame( 7200, $row['cacheDuration'] );
		$this->assertIsInt( $row['cachedOn'] );
		$this->assertIsInt( $row['expiresOn'] );
		$this->assertGreaterThan( $row['cachedOn'], $row['expiresOn'] );
	}

	public function testResolvesAbsentKeyAsNotCached(): void {
		$wan = $this->newWanCache();
		$key = $wan->makeKey( 'ext', 'apiuntocache', sha1( 'https://example.test/cold' ) );

		$resolver = new CacheInfoResolver( $wan, $this->newConfig( true, [] ) );
		$rows = $resolver->resolve( [
			[ 'source' => 'src', 'key' => $key, 'url' => 'https://example.test/cold', 'count' => 1 ],
		] );

		$this->assertSame( CacheInfoResolver::STATUS_NOT_CACHED, $rows[0]['status'] );
		$this->assertNull( $rows[0]['cachedOn'] );
		$this->assertNull( $rows[0]['expiresOn'] );
		// Default cache duration when the source is not configured.
		$this->assertSame( 86400, $rows[0]['cacheDuration'] );
	}

	public function testCachingDisabled(): void {
		$wan = $this->newWanCache();
		$key = $wan->makeKey( 'ext', 'apiuntocache', sha1( 'x' ) );
		$wan->set( $key, 'data', 3600 );

		$resolver = new CacheInfoResolver( $wan, $this->newConfig( false, [] ) );
		$rows = $resolver->resolve( [
			[ 'source' => 'src', 'key' => $key, 'url' => 'x', 'count' => 1 ],
		] );

		$this->assertSame( CacheInfoResolver::STATUS_DISABLED, $rows[0]['status'] );
		$this->assertNull( $rows[0]['cachedOn'] );
		$this->assertNull( $rows[0]['expiresOn'] );
	}

	public function testResolvesMultipleEntriesInOneBatch(): void {
		$wan = $this->newWanCache();
		$warm = $wan->makeKey( 'ext', 'apiuntocache', sha1( 'a' ) );
		$cold = $wan->makeKey( 'ext', 'apiuntocache', sha1( 'b' ) );
		$wan->set( $warm, 'data', 3600 );

		$resolver = new CacheInfoResolver( $wan, $this->newConfig( true, [] ) );
		$rows = $resolver->resolve( [
			[ 'source' => 's', 'key' => $warm, 'url' => 'a', 'count' => 1 ],
			[ 'source' => 's', 'key' => $cold, 'url' => 'b', 'count' => 1 ],
		] );

		$this->assertSame( CacheInfoResolver::STATUS_CACHED, $rows[0]['status'] );
		$this->assertSame( CacheInfoResolver::STATUS_NOT_CACHED, $rows[1]['status'] );
	}
}
