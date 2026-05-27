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

namespace MediaWiki\Extension\Apiunto\Tests\Unit;

use MediaWiki\Extension\Apiunto\CacheManifest;
use MediaWikiUnitTestCase;

/**
 * @group Apiunto
 * @covers \MediaWiki\Extension\Apiunto\CacheManifest
 */
class CacheManifestTest extends MediaWikiUnitTestCase {

	public function testDecodeReturnsEmptyForNonStringOrMalformed(): void {
		$this->assertSame( [], CacheManifest::decode( null ) );
		$this->assertSame( [], CacheManifest::decode( '' ) );
		$this->assertSame( [], CacheManifest::decode( 123 ) );
		$this->assertSame( [], CacheManifest::decode( 'not json' ) );
		$this->assertSame( [], CacheManifest::decode( '"a string"' ) );
	}

	public function testEncodeDecodeRoundTrip(): void {
		$entries = [ [ 'source' => 's', 'key' => 'k', 'url' => 'u', 'count' => 2 ] ];
		$this->assertSame( $entries, CacheManifest::decode( CacheManifest::encode( $entries ) ) );
	}

	public function testAddEntryAppendsNewKey(): void {
		$result = CacheManifest::addEntry( [], 'src', 'key1', 'http://u' );
		$this->assertSame(
			[ [ 'source' => 'src', 'key' => 'key1', 'url' => 'http://u', 'count' => 1 ] ],
			$result
		);
	}

	public function testAddEntryIncrementsExistingKeyAndPreservesSiblings(): void {
		$entries = [
			[ 'source' => 'other', 'key' => 'keep', 'url' => 'http://keep', 'count' => 1 ],
			[ 'source' => 'src', 'key' => 'key1', 'url' => 'http://u', 'count' => 1 ],
		];
		$result = CacheManifest::addEntry( $entries, 'ignored', 'key1', 'http://ignored' );
		$this->assertSame( 2, $result[1]['count'], 'count incremented' );
		$this->assertSame( 'src', $result[1]['source'], 'original source preserved' );
		$this->assertSame( 'http://u', $result[1]['url'], 'original url preserved' );
		$this->assertSame( $entries[0], $result[0], 'sibling untouched' );
	}
}
