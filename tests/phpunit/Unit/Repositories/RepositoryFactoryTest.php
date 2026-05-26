<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Tests\Unit\Repositories;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Apiunto\ApiuntoLuaLibrary;
use MediaWiki\Extension\Apiunto\Repositories\RawRepository;
use MediaWiki\Extension\Apiunto\Repositories\RepositoryFactory;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @group Apiunto
 * @group Repositories
 * @covers \MediaWiki\Extension\Apiunto\Repositories\RepositoryFactory
 */
class RepositoryFactoryTest extends MediaWikiUnitTestCase {

	private function newFactory(): RepositoryFactory {
		$cache = $this->createMock( WANObjectCache::class );
		$cache->method( 'makeKey' )->willReturnCallback(
			static fn ( ...$args ) => implode( ':', $args )
		);
		return new RepositoryFactory(
			$this->createMock( HttpRequestFactory::class ),
			new HashConfig( [ 'ApiuntoEnableCache' => false ] ),
			$cache
		);
	}

	public function testNewRawRepositoryBuildsRawRepository(): void {
		$repo = $this->newFactory()->newRawRepository(
			'ships',
			[ 'baseUrl' => 'https://api.example/' ],
			[
				ApiuntoLuaLibrary::IDENTIFIER => 'Aurora',
				ApiuntoLuaLibrary::QUERY_PARAMS => [],
			]
		);

		$this->assertInstanceOf( RawRepository::class, $repo );
		$this->assertSame( 'https://api.example/Aurora', $repo->getRequestUrl() );
	}
}
