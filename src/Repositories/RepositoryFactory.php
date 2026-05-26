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

namespace MediaWiki\Extension\Apiunto\Repositories;

use MediaWiki\Config\Config;
use MediaWiki\Http\HttpRequestFactory;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Builds repository instances with their service dependencies injected.
 */
class RepositoryFactory {

	public function __construct(
		private readonly HttpRequestFactory $requestFactory,
		private readonly Config $config,
		private readonly WANObjectCache $cache
	) {
	}

	/**
	 * Build a RawRepository for one API source.
	 *
	 * @param string $sourceName Configured source name (a key of $wgApiuntoSources).
	 * @param array $sourceConfig Source config (e.g. baseUrl, token, timeout, cacheDuration).
	 * @param array $options Request options, keyed by ApiuntoLuaLibrary::IDENTIFIER and
	 *   ApiuntoLuaLibrary::QUERY_PARAMS, appended to the request URL.
	 */
	public function newRawRepository(
		string $sourceName,
		array $sourceConfig,
		array $options = []
	): RawRepository {
		return new RawRepository(
			$this->requestFactory,
			$this->config,
			$this->cache,
			$sourceName,
			$sourceConfig,
			$options
		);
	}
}
