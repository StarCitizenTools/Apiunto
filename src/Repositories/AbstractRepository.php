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

use Exception;
use JsonException;
use MediaWiki\Config\Config;
use MediaWiki\Extension\Apiunto\ApiuntoLuaLibrary;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MediaWikiServices;
use Wikimedia\ObjectCache\WANObjectCache;

abstract class AbstractRepository {
	public const PROP_KEY = 'apiuntocache';
	private const DEFAULT_CACHE_DURATION = 86400;

	private ?string $cacheKey = null;
	private Config $config;

	private WANObjectCache $cache;

	/**
	 * AbstractRepository constructor.
	 *
	 * @param HttpRequestFactory $requestFactory
	 * @param string $sourceName
	 * @param array $sourceConfig
	 * @param array $options Request options gets appended to the request url
	 */
	public function __construct(
		protected readonly HttpRequestFactory $requestFactory,
		protected readonly string $sourceName,
		protected readonly array $sourceConfig,
		protected array $options = []
	) {
		$services = MediaWikiServices::getInstance();
		$this->config = $services->getMainConfig();
		$this->cache = $services->getMainWANObjectCache();
	}

	/**
	 * @param array $options
	 */
	public function setOptions( array $options ): void {
		$this->options = $options;
	}

	/**
	 * Perform the request
	 *
	 * @return string
	 * @throws JsonException
	 */
	protected function request(): string {
		$cacheMiss = false;
		$caller = __METHOD__;
		$callback = function () use ( &$cacheMiss, $caller ) {
			$cacheMiss = true;
			wfDebugLog( 'Apiunto', 'Retrieving Data from API' );

			try {
				$req = $this->requestFactory->create( $this->getFullUrl(), [
					'timeout' => $this->sourceConfig['timeout'] ?? 5
				], $caller );
				$req->setHeader( 'User-Agent', 'MediaWiki/ext-apiunto-' . MW_VERSION );
				if ( !empty( $this->sourceConfig['token'] ) ) {
					$req->setHeader( 'Authorization', 'Bearer ' . $this->sourceConfig['token'] );
				}
				$status = $req->execute();

				if ( !$status->isOK() ) {
					return false;
				}

				return $req->getContent();
			} catch ( Exception $e ) {
				wfLogWarning( sprintf( '[Apiunto] Error retrieving API data: %s', $e->getMessage() ) );
				wfDebugLog( 'Apiunto', sprintf( 'Error retrieving API data: %s', $e->getMessage() ) );

				$key = $this->makeCacheKey();
				$stale = $this->cache->get( $key );

				if ( $stale !== false ) {
					wfLogWarning( sprintf( '[Apiunto] Returning stale content for key %s', $key ) );
					wfDebugLog( 'Apiunto', sprintf( 'Returning stale content for key %s', $key ) );
					return $stale;
				}

				return false;
			}
		};

		if ( $this->config->get( 'ApiuntoEnableCache' ) !== true ) {
			wfDebugLog( 'Apiunto', 'Object cache is disabled' );
			return (string)$callback();
		}

		$key = $this->makeCacheKey();
		$value = $this->cache->getWithSetCallback(
			$key,
			$this->sourceConfig['cacheDuration'] ?? self::DEFAULT_CACHE_DURATION,
			$callback
		);

		wfDebugLog( 'Apiunto', sprintf(
			$cacheMiss ? 'Object cache MISS: %s' : 'Object cache HIT: %s',
			$key
		) );

		if ( $value === false ) {
			return 'Could not retrieve API Data';
		}

		return (string)$value;
	}

	/**
	 * Creates a key for caching
	 *
	 * @return string
	 */
	public function makeCacheKey(): string {
		if ( $this->cacheKey !== null ) {
			return $this->cacheKey;
		}
		$fullUrl = $this->getFullUrl();

		$this->cacheKey = $this->cache->makeKey(
			'ext',
			self::PROP_KEY,
			$fullUrl
		);

		return $this->cacheKey;
	}

	private function getFullUrl(): string {
		$queryString = $this->buildQueryString();

		$baseUrl = rtrim( $this->sourceConfig['baseUrl'], '/' );
		$identifier = ltrim( $this->options[ApiuntoLuaLibrary::IDENTIFIER], '/' );
		$fullUrl = $baseUrl . '/' . $identifier;
		if ( $queryString ) {
			$fullUrl .= '?' . $queryString;
		}

		return $fullUrl;
	}

	private function buildQueryString(): string {
		$queryParams = $this->options[ ApiuntoLuaLibrary::QUERY_PARAMS ];
		ksort( $queryParams );
		return http_build_query( $queryParams );
	}

	/**
	 * @return int
	 */
	public function getCacheDuration(): int {
		return (int)( $this->sourceConfig['cacheDuration'] ?? self::DEFAULT_CACHE_DURATION );
	}
}
