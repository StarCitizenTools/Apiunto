<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Tests\Unit\Repositories;

use MediaWiki\Extension\Apiunto\Repositories\AbstractRepository;
use MediaWikiUnitTestCase;

/**
 * @group Apiunto
 * @group Repositories
 * @coversDefaultClass \MediaWiki\Extension\Apiunto\Repositories\AbstractRepository
 */
class AbstractRepositoryTest extends MediaWikiUnitTestCase {

	public function testPropKeyConstant(): void {
		$this->assertSame( 'apiuntocache', AbstractRepository::PROP_KEY );
	}
}
