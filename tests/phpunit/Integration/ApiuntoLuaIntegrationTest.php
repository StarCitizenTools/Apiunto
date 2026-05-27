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

namespace MediaWiki\Extension\Apiunto\Tests\Integration;

use MediaWiki\Extension\Apiunto\ApiuntoLuaLibrary;
use MediaWiki\Extension\Apiunto\CacheManifest;
use MediaWiki\Extension\Apiunto\Hooks\ScribuntoHooks;
use MediaWiki\Extension\Apiunto\Repositories\AbstractRepository;
use MediaWiki\Parser\ParserOutput;
use MediaWikiIntegrationTestCase;

/**
 * Integration coverage that needs no database: the ScribuntoExternalLibraries hook
 * registers the Apiunto Lua library, and the apiuntocache page property round-trips
 * through a real ParserOutput (the store the production write path uses).
 *
 * @group Apiunto
 * @coversNothing
 */
class ApiuntoLuaIntegrationTest extends MediaWikiIntegrationTestCase {

	public function testHookRegistersApiuntoLuaLibrary(): void {
		$extraLibraries = [];
		( new ScribuntoHooks() )->onScribuntoExternalLibraries( 'lua', $extraLibraries );

		$this->assertArrayHasKey( 'mw.ext.Apiunto', $extraLibraries );
		$this->assertSame( ApiuntoLuaLibrary::class, $extraLibraries['mw.ext.Apiunto'] );
	}

	public function testHookIgnoresNonLuaEngine(): void {
		$extraLibraries = [];
		( new ScribuntoHooks() )->onScribuntoExternalLibraries( 'other', $extraLibraries );

		$this->assertSame( [], $extraLibraries );
	}

	public function testCacheManifestRoundTripsThroughParserOutput(): void {
		$parserOutput = new ParserOutput();
		$entries = CacheManifest::addEntry( [], 'testsource', 'ext:apiuntocache:abc', 'https://api.example/v1/thing' );
		$parserOutput->setPageProperty( AbstractRepository::PROP_KEY, CacheManifest::encode( $entries ) );

		$decoded = CacheManifest::decode( $parserOutput->getPageProperty( AbstractRepository::PROP_KEY ) );

		$this->assertCount( 1, $decoded );
		$this->assertSame( 'testsource', $decoded[0]['source'] );
		$this->assertSame( 'ext:apiuntocache:abc', $decoded[0]['key'] );
	}
}
