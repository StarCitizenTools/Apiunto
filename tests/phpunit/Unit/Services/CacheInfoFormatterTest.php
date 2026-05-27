<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Tests\Unit\Services;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\Apiunto\Services\CacheInfoFormatter;
use MediaWiki\Extension\Apiunto\Services\CacheInfoResolver;
use MediaWiki\Language\Language;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

/**
 * @group Apiunto
 * @group Services
 * @covers \MediaWiki\Extension\Apiunto\Services\CacheInfoFormatter
 */
class CacheInfoFormatterTest extends MediaWikiUnitTestCase {

	private function newContext(): IContextSource {
		$lang = $this->createMock( Language::class );
		$lang->method( 'userTimeAndDate' )->willReturn( '2026-01-01 00:00' );
		$lang->method( 'formatDuration' )->willReturn( '1 day' );

		$context = $this->createMock( IContextSource::class );
		$context->method( 'getLanguage' )->willReturn( $lang );
		$context->method( 'getUser' )->willReturn( $this->createMock( User::class ) );
		$context->method( 'msg' )->willReturnCallback( function ( $key ) {
			$msg = $this->createMock( \MediaWiki\Message\Message::class );
			$msg->method( 'text' )->willReturn( $key );
			return $msg;
		} );
		return $context;
	}

	private function baseRow(): array {
		return [
			'source' => 'ships',
			'url' => 'https://api.example/Aurora',
			'key' => 'ext:apiuntocache:abc',
			'count' => 1,
			'cacheDuration' => 86400,
			'status' => CacheInfoResolver::STATUS_CACHED,
			'cachedOn' => 1000,
			'expiresOn' => 2000,
		];
	}

	public function testFullRowRendersAllItems(): void {
		$html = ( new CacheInfoFormatter() )->format( $this->baseRow(), $this->newContext() );
		$this->assertStringContainsString( 'apiunto-cache-status-info-label', $html );
		$this->assertStringContainsString( 'apiunto-cache-url-info-label', $html );
		$this->assertStringContainsString( 'apiunto-cache-key-info-label', $html );
		$this->assertStringContainsString( 'apiunto-cache-time-info-label', $html );
		$this->assertStringContainsString( 'apiunto-cache-expires-info-label', $html );
		$this->assertStringContainsString( 'apiunto-cache-duration-info-label', $html );
		$this->assertStringContainsString( 'apiunto-cache-status-cached', $html );
		$this->assertStringContainsString( '<ul>', $html );
	}

	public function testOmitsOptionalItemsWhenAbsent(): void {
		$row = $this->baseRow();
		$row['url'] = null;
		$row['key'] = null;
		$row['cachedOn'] = null;
		$row['expiresOn'] = null;
		$html = ( new CacheInfoFormatter() )->format( $row, $this->newContext() );
		$this->assertStringNotContainsString( 'apiunto-cache-url-info-label', $html );
		$this->assertStringNotContainsString( 'apiunto-cache-key-info-label', $html );
		$this->assertStringNotContainsString( 'apiunto-cache-time-info-label', $html );
		$this->assertStringNotContainsString( 'apiunto-cache-expires-info-label', $html );
		$this->assertStringContainsString( 'apiunto-cache-status-info-label', $html );
	}

	public function testCountShownOnlyWhenGreaterThanOne(): void {
		$one = ( new CacheInfoFormatter() )->format( $this->baseRow(), $this->newContext() );
		$this->assertStringNotContainsString( 'apiunto-request-count-info-label', $one );

		$row = $this->baseRow();
		$row['count'] = 3;
		$many = ( new CacheInfoFormatter() )->format( $row, $this->newContext() );
		$this->assertStringContainsString( 'apiunto-request-count-info-label', $many );
	}

	public function testStatusMapping(): void {
		$ctx = $this->newContext();
		$disabled = $this->baseRow();
		$disabled['status'] = CacheInfoResolver::STATUS_DISABLED;
		$this->assertStringContainsString(
			'apiunto-cache-status-disabled',
			( new CacheInfoFormatter() )->format( $disabled, $ctx )
		);

		$notCached = $this->baseRow();
		$notCached['status'] = CacheInfoResolver::STATUS_NOT_CACHED;
		$notCached['cachedOn'] = null;
		$notCached['expiresOn'] = null;
		$this->assertStringContainsString(
			'apiunto-cache-status-not-cached',
			( new CacheInfoFormatter() )->format( $notCached, $ctx )
		);
	}
}
