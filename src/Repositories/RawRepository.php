<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Repositories;

/**
 * Repository that returns the raw API response body.
 */
class RawRepository extends AbstractRepository {

	public function getRaw(): string {
		return $this->request();
	}
}
