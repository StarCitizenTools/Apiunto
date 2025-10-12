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

namespace MediaWiki\Extension\Apiunto;

use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\Apiunto\Repositories\AbstractRepository;
use MediaWiki\Extension\Apiunto\Repositories\RawRepository;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine;
use MediaWiki\MediaWikiServices;

/**
 * Methods callable by LUA
 */
class ApiuntoLuaLibrary extends LibraryBase {

	/**
	 * Page identifier like ship name oder comm-link id
	 * @var string
	 */
	public const IDENTIFIER = 'identifier';

	/** @var string */
	public const QUERY_PARAMS = 'query';

	private static array $requestCache = [];

	/**
	 * Registers the callable lua methods.
	 */
	public function register(): array {
		$lib = [
			'fetch' => [ $this, 'getRaw' ],
		];

		return $this->getEngine()->registerInterface(
			sprintf(
				'%s%s%s',
				__DIR__,
				DIRECTORY_SEPARATOR,
				'mw.ext.Apiunto.lua'
			),
			$lib
		);
	}

	/**
	 * Raw request.
	 * Identifier is the complete uri excluding 'api'.
	 *
	 * @throws LuaError If arguments are invalid.
	 */
	public function getRaw(): array {
		$args = func_get_args();

		if ( !isset( $args[0] ) || !is_string( $args[0] ) || $args[0] === '' ) {
			throw new LuaError( 'Apiunto: Call to getRaw() requires a non-empty string source as the first argument.' );
		}
		$sourceName = $args[0];

		if ( !isset( $args[1] ) || !is_string( $args[1] ) ) {
			throw new LuaError( 'Apiunto: Call to getRaw() requires a string identifier as the second argument.' );
		}
		$identifier = $args[1];

		$inputOptions = [];
		if ( isset( $args[2] ) ) {
			if ( is_array( $args[2] ) ) {
				$inputOptions = $args[2];
			} else {
				// Log a warning but proceed with empty options if the second arg is not an array.
				// Lua will get an empty result if this leads to an invalid API call,
				// or the API might handle default parameters.
				wfLogWarning( sprintf(
					'Apiunto: Call to getRaw() for identifier "%s" expected an array for options (third argument), got %s. Proceeding with empty options.',
					$identifier,
					gettype( $args[2] )
				) );
			}
		}

		$sources = $this->getConfigValue( 'ApiuntoSources' );
		if ( !isset( $sources[$sourceName] ) ) {
			throw new LuaError( "Apiunto: Source '{$sourceName}' not found in configuration." );
		}
		$sourceConfig = $sources[$sourceName];

		$repository = new RawRepository(
			MediaWikiServices::getInstance()->getHttpRequestFactory(),
			$sourceName,
			$sourceConfig,
			[
				self::IDENTIFIER => $identifier,
				self::QUERY_PARAMS => $this->processArgs( $inputOptions ),
			]
		);

		$cacheKey = $repository->makeCacheKey();

		if ( isset( self::$requestCache[$cacheKey] ) ) {
			$response = self::$requestCache[$cacheKey];
		} else {
			$response = $repository->getRaw();
			self::$requestCache[$cacheKey] = $response;
		}

		$this->writeCachePropertyKey( $repository, $sourceName );

		return [ $response ];
	}

	/**
	 * Processes the method arguments from Lua.
	 *
	 * @param array $arguments Method arguments from Lua.
	 * @return array HTTP Query data, filtered for non-empty values.
	 */
	private function processArgs( array $arguments ): array {
		$query = [];
		foreach ( $arguments as $key => $value ) {
			$key = strval( $key );
			if ( is_array( $value ) ) {
				$query[$key] = implode( ',', array_map( 'strval', $value ) );
			} else {
				$query[$key] = strval( $value );
			}
		}

		return array_filter( $query, static function ( $value ) {
			return $value !== '';
		} );
	}

	/**
	 * Loads a config value for a given key from the main config.
	 * Returns null if a ConfigException was thrown and no default is provided.
	 *
	 * @param string $key The config key.
	 * @param mixed|null $default Default value to return if config is not found.
	 * @return mixed The configuration value or the default.
	 */
	private function getConfigValue( string $key, mixed $default = null ): mixed {
		try {
			$value = MediaWikiServices::getInstance()->getMainConfig()->get( $key );
		} catch ( ConfigException $e ) {
			if ( $default === null ) {
				wfLogWarning( sprintf( 'Could not get config for "$wg%s". %s', $key,
					$e->getMessage() ) );
				$value = null;
			} else {
				$value = $default;
			}
		}

		return $value;
	}

	/**
	 * Writes the cache key to the page properties for purging.
	 *
	 * @param AbstractRepository $repository The repository used for the request.
	 * @param string $sourceName The name of the API source.
	 */
	private function writeCachePropertyKey( AbstractRepository $repository, string $sourceName ): void {
		wfDebugLog( 'Apiunto', 'Writing page prop' );

		$parserOutput = $this->getParser()->getOutput();

		$propValue = $parserOutput->getPageProperty( AbstractRepository::PROP_KEY );
		$caches = $propValue ? json_decode( (string)$propValue, true ) : [];
		if ( !is_array( $caches ) ) {
			$caches = [];
		}

		$cacheKey = $repository->makeCacheKey();
		$found = false;
		foreach ( $caches as &$cache ) {
			if ( $cache['key'] === $cacheKey ) {
				$cache['count'] = ( $cache['count'] ?? 1 ) + 1;
				$found = true;
				break;
			}
		}
		unset( $cache );

		if ( !$found ) {
			$caches[] = [
				'source' => $sourceName,
				'key' => $cacheKey,
				'count' => 1,
			];
		}

		$parserOutput->setPageProperty( AbstractRepository::PROP_KEY, json_encode( $caches ) );
	}
}
