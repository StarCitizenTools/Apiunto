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

use MediaWiki\Extension\Apiunto\LuaArguments;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;
use MediaWikiIntegrationTestCase;

/**
 * @group Apiunto
 * @covers \MediaWiki\Extension\Apiunto\LuaArguments
 */
class LuaArgumentsTest extends MediaWikiIntegrationTestCase {

	public function testParsesValidArgs(): void {
		$result = LuaArguments::parse( [ 'ships', 'Aurora', [ 'a' => '1', 'b' => '2' ] ] );
		$this->assertSame( 'ships', $result['source'] );
		$this->assertSame( 'Aurora', $result['identifier'] );
		$this->assertSame( [ 'a' => '1', 'b' => '2' ], $result['query'] );
	}

	public function testEmptyOrMissingSourceThrows(): void {
		$this->expectException( LuaError::class );
		LuaArguments::parse( [ '', 'Aurora' ] );
	}

	public function testMissingSourceThrows(): void {
		$this->expectException( LuaError::class );
		LuaArguments::parse( [] );
	}

	public function testNonStringSourceThrows(): void {
		$this->expectException( LuaError::class );
		LuaArguments::parse( [ 123, 'Aurora' ] );
	}

	public function testMissingIdentifierThrows(): void {
		$this->expectException( LuaError::class );
		LuaArguments::parse( [ 'ships' ] );
	}

	public function testNonStringIdentifierThrows(): void {
		$this->expectException( LuaError::class );
		LuaArguments::parse( [ 'ships', [] ] );
	}

	public function testNonArrayOptionsYieldsEmptyQuery(): void {
		$result = null;
		$this->expectPHPError( E_USER_WARNING, static function () use ( &$result ) {
			$result = LuaArguments::parse( [ 'ships', 'Aurora', 'not-an-array' ] );
		} );
		$this->assertSame( [], $result['query'] );
	}

	public function testQueryNormalization(): void {
		$result = LuaArguments::parse( [ 'ships', 'Aurora', [
			'list' => [ 'a', 'b', 3 ],
			'num' => 5,
			'empty' => '',
			7 => 'x',
		] ] );
		$this->assertSame(
			[ 'list' => 'a,b,3', 'num' => '5', '7' => 'x' ],
			$result['query'],
			'arrays comma-joined + stringified, empties dropped, keys stringified'
		);
	}
}
