<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Tests\Unit\Repositories;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Apiunto\ApiuntoLuaLibrary;
use MediaWiki\Extension\Apiunto\Repositories\AbstractRepository;
use MediaWiki\Extension\Apiunto\Repositories\RawRepository;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @group Apiunto
 * @group Repositories
 * @covers \MediaWiki\Extension\Apiunto\Repositories\AbstractRepository
 */
class AbstractRepositoryTest extends MediaWikiUnitTestCase {

	private function newCache(): WANObjectCache {
		$cache = $this->createMock( WANObjectCache::class );
		$cache->method( 'makeKey' )->willReturnCallback(
			static fn ( ...$args ) => implode( ':', $args )
		);
		return $cache;
	}

	/**
	 * A real WANObjectCache backed by an in-memory store, so the final
	 * getWithSetCallback() runs the production callback for real.
	 */
	private function newRealCache(): WANObjectCache {
		return new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	private function newRequestFactory( bool $ok, string $content ): HttpRequestFactory {
		$status = $this->createMock( \StatusValue::class );
		$status->method( 'isOK' )->willReturn( $ok );

		$req = $this->createMock( \MWHttpRequest::class );
		$req->method( 'execute' )->willReturn( $status );
		$req->method( 'getContent' )->willReturn( $content );

		$factory = $this->createMock( HttpRequestFactory::class );
		$factory->method( 'create' )->willReturn( $req );
		return $factory;
	}

	private function newRepo(
		array $sourceConfig,
		array $options,
		?HttpRequestFactory $factory = null,
		?WANObjectCache $cache = null,
		bool $cacheEnabled = false
	): RawRepository {
		return new RawRepository(
			$factory ?? $this->createMock( HttpRequestFactory::class ),
			new HashConfig( [ 'ApiuntoEnableCache' => $cacheEnabled ] ),
			$cache ?? $this->newCache(),
			'ships',
			$sourceConfig,
			$options
		);
	}

	public function testPropKeyConstant(): void {
		$this->assertSame( 'apiuntocache', AbstractRepository::PROP_KEY );
	}

	public function testRequestUrlTrimsSlashesAndSortsQuery(): void {
		$repo = $this->newRepo(
			[ 'baseUrl' => 'https://api.example/' ],
			[
				ApiuntoLuaLibrary::IDENTIFIER => '/Aurora',
				ApiuntoLuaLibrary::QUERY_PARAMS => [ 'b' => '2', 'a' => '1' ],
			]
		);

		$this->assertSame( 'https://api.example/Aurora?a=1&b=2', $repo->getRequestUrl() );
	}

	public function testMakeCacheKeyIsStableAndHashesUrl(): void {
		$repo = $this->newRepo(
			[ 'baseUrl' => 'https://api.example' ],
			[
				ApiuntoLuaLibrary::IDENTIFIER => 'Aurora',
				ApiuntoLuaLibrary::QUERY_PARAMS => [],
			]
		);

		$expected = 'ext:apiuntocache:' . sha1( 'https://api.example/Aurora' );
		$this->assertSame( $expected, $repo->makeCacheKey() );
		$this->assertSame( $repo->makeCacheKey(), $repo->makeCacheKey(), 'memoized' );
	}

	public function testRequestWithCacheDisabledHitsApiDirectly(): void {
		$repo = $this->newRepo(
			[ 'baseUrl' => 'https://api.example' ],
			[
				ApiuntoLuaLibrary::IDENTIFIER => 'Aurora',
				ApiuntoLuaLibrary::QUERY_PARAMS => [],
			],
			$this->newRequestFactory( true, '{"ok":true}' ),
			null,
			false
		);

		$this->assertSame( '{"ok":true}', $repo->getRaw() );
	}

	public function testRequestUsesCacheCallbackWhenEnabled(): void {
		$repo = $this->newRepo(
			[ 'baseUrl' => 'https://api.example' ],
			[
				ApiuntoLuaLibrary::IDENTIFIER => 'Aurora',
				ApiuntoLuaLibrary::QUERY_PARAMS => [],
			],
			$this->newRequestFactory( true, '{"cached":true}' ),
			$this->newRealCache(),
			true
		);

		$this->assertSame( '{"cached":true}', $repo->getRaw() );
	}

	public function testRequestReturnsErrorStringWhenCacheValueFalse(): void {
		$repo = $this->newRepo(
			[ 'baseUrl' => 'https://api.example' ],
			[
				ApiuntoLuaLibrary::IDENTIFIER => 'Aurora',
				ApiuntoLuaLibrary::QUERY_PARAMS => [],
			],
			$this->newRequestFactory( false, '' ),
			$this->newRealCache(),
			true
		);

		$this->assertSame( 'Could not retrieve API Data', $repo->getRaw() );
	}
}
