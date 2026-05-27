<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto;

use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\Apiunto\Repositories\AbstractRepository;
use MediaWiki\Extension\Apiunto\Repositories\RepositoryFactory;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;
use MediaWiki\MediaWikiServices;

/**
 * Methods callable by LUA
 */
class ApiuntoLuaLibrary extends LibraryBase {

	/** Page identifier like a ship name or comm-link id. */
	public const IDENTIFIER = 'identifier';

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
		[ 'source' => $sourceName, 'identifier' => $identifier, 'query' => $queryParams ] =
			LuaArguments::parse( func_get_args() );

		$sources = $this->getConfigValue( 'ApiuntoSources' );
		if ( !isset( $sources[$sourceName] ) ) {
			throw new LuaError( "Apiunto: Source '{$sourceName}' not found in configuration." );
		}
		$sourceConfig = $sources[$sourceName];

		/** @var RepositoryFactory $factory */
		$factory = MediaWikiServices::getInstance()->get( 'Apiunto.RepositoryFactory' );
		$repository = $factory->newRawRepository(
			$sourceName,
			$sourceConfig,
			[
				self::IDENTIFIER => $identifier,
				self::QUERY_PARAMS => $queryParams,
			]
		);

		$cacheKey = $repository->makeCacheKey();

		if ( isset( self::$requestCache[$cacheKey] ) ) {
			$response = self::$requestCache[$cacheKey];
			wfDebugLog( 'Apiunto', sprintf( 'Request cache HIT: %s', $cacheKey ) );
		} else {
			$response = $repository->getRaw();
			self::$requestCache[$cacheKey] = $response;
			wfDebugLog( 'Apiunto', sprintf( 'Request cache MISS: %s', $cacheKey ) );
		}

		$this->writeCachePropertyKey( $sourceName, $cacheKey, $repository->getRequestUrl() );

		return [ $response ];
	}

	/**
	 * Loads a config value for a given key from the main config.
	 * Returns the default (or null) if a ConfigException was thrown.
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
	 * Records the cache key in the page properties so the cached response can be purged.
	 * The request URL is stored alongside it for display on action=info.
	 */
	private function writeCachePropertyKey( string $sourceName, string $cacheKey, string $url ): void {
		$parserOutput = $this->getParser()->getOutput();

		$entries = CacheManifest::decode( $parserOutput->getPageProperty( AbstractRepository::PROP_KEY ) );
		$entries = CacheManifest::addEntry( $entries, $sourceName, $cacheKey, $url );

		$parserOutput->setPageProperty( AbstractRepository::PROP_KEY, CacheManifest::encode( $entries ) );
	}
}
