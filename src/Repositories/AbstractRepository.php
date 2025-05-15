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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use MediaWiki\Config\Config;
use MediaWiki\Extension\Apiunto\ApiuntoLuaLibrary;
use MediaWiki\MediaWikiServices;
use Wikimedia\ObjectCache\BagOStuff;

abstract class AbstractRepository {
	public const API_ENDPOINT = '';

	public const PROP_KEY = 'apiuntocache';

	/**
	 * @var Client
	 */
	protected Client $client;

	/**
	 * @var array|null
	 */
	protected $options;

	/**
	 * @var Config
	 */
	private Config $config;

	/**
	 * @var BagOStuff
	 */
	private BagOStuff $bagOStuff;

	/**
	 * AbstractRepository constructor.
	 *
	 * @param Client $client Request Client
	 * @param null|array $options Request options gets appended to the request url
	 */
	public function __construct( Client $client, $options = null ) {
		$this->client = $client;

		if ( is_array( $options ) ) {
			$this->options = $options;
		}

		$services = MediaWikiServices::getInstance();
		$this->config = $services->getMainConfig();
		$this->bagOStuff = $services->getObjectCacheFactory()->getLocalClusterInstance();
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
	 * @throws JsonException|GuzzleException
	 */
	protected function request(): string {
		$callback = function () {
			wfDebugLog( 'Apiunto', 'Retrieving Data from API' );

			try {
				$url = sprintf(
					'%s/%s',
					static::API_ENDPOINT,
					$this->options[ ApiuntoLuaLibrary::IDENTIFIER ]
				);

				$response = $this->client->get(
					$url,
					[
						'query' => $this->options[ ApiuntoLuaLibrary::QUERY_PARAMS ]
					]
				);
			} catch ( GuzzleException | Exception $e ) {
				wfLogWarning( sprintf( '[Apiunto] Error retrieving API data: %s', $e->getMessage() ) );
				wfDebugLog( 'Apiunto', sprintf( 'Error retrieving API data: %s', $e->getMessage() ) );

				$key = $this->makeCacheKey();
				$stale = $this->bagOStuff->get( $key );

				if ( $stale !== false ) {
					wfLogWarning( sprintf( '[Apiunto] Returning stale content for key %s', $key ) );
					wfDebugLog( 'Apiunto', sprintf( 'Returning stale content for key %s', $key ) );
					return $stale;
				}

				return false;
			}

			return (string)$response->getBody();
		};

		if ( $this->config->get( 'ApiuntoEnableCache' ) !== true ) {
			wfDebugLog( 'Apiunto', 'Cache is disabled' );
			return $callback();
		}

		$expires = $this->config->get( 'ApiuntoCacheTimes' );

		$value = $this->bagOStuff->getWithSetCallback(
			$this->makeCacheKey(),
			$expires[ str_replace( 'api/', '', self::API_ENDPOINT ) ] ?? $expires[ 'Default' ],
			$callback
		);

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
		$key = $this->bagOStuff->makeKey(
			'ext',
			self::PROP_KEY,
			explode( '/', static::API_ENDPOINT )[1] ?? static::API_ENDPOINT,
			...(array)( $this->options[ ApiuntoLuaLibrary::IDENTIFIER ] ),
			...array_values( $this->options[ ApiuntoLuaLibrary::QUERY_PARAMS ] ),
		);

		wfDebugLog( 'Apiunto', sprintf( 'Key is %s', $key ) );

		return $key;
	}
}
