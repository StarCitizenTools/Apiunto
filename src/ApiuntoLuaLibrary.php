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

use GuzzleHttp\Client;
use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\Apiunto\Repositories\AbstractRepository;
use MediaWiki\Extension\Apiunto\Repositories\RawRepository;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine;
use MediaWiki\MediaWikiServices;

/**
 * Methods callable by LUA
 */
class ApiuntoLuaLibrary extends LibraryBase {

    /**
     * Page identifier like ship name oder comm-link id
     */
    public const IDENTIFIER = 'identifier';

    /**
     * Query parameters
     */
    public const QUERY_PARAMS = 'query';

    private static ?Client $client = null;

    private array $availableIncludes = [];

    public function __construct( LuaEngine $engine ) {
        parent::__construct( $engine );

        if ( static::$client === null ) {
            $this->initGuzzleClient();
        }
    }

    /**
     * Initializes the guzzle client
     * Adds the bearer token if set in the config
     *
     * @return void
     */
    private function initGuzzleClient(): void {
        $apiKey = $this->getConfigValue( 'ApiuntoKey' );

        $headers = [
            'User-Agent' => 'MediaWiki/ext-apiunto-' . MW_VERSION,
        ];

        if ( null !== $apiKey ) {
            $headers['Authorization'] = "Bearer {$apiKey}";
        }

        static::$client = new Client( [
            'base_uri' => $this->getConfigValue( 'ApiuntoUrl' ),
            'timeout' => $this->getConfigValue( 'ApiuntoTimeout', 5 ),
            'headers' => $headers,
        ] );
    }

    /**
     * Registers the callable lua methods
     *
     * @return array
     */
    public function register(): array {
        $lib = [
            'get_raw' => [ $this, 'getRaw' ],
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
     * Raw request
     *
     * Identifier is the complete uri excluding 'api'
     *
     * @return array
     */
    public function getRaw(): array {
        $params = func_get_args();

        $this->availableIncludes = $params[1]['include'] ?? [];

        $repository = new RawRepository( static::$client, [
            self::IDENTIFIER => $params[0],
            self::QUERY_PARAMS => $this->processArgs( $params[1] ),
        ] );

        $response = $repository->getRaw();
        $this->writeCachePropertyKey( $repository );

        return [ $response ];
    }

    /**
     * Processes the method arguments
     * Returns an array used by http_build_query
     *
     * @param array $arguments Method arguments
     * @return array HTTP Query data
     */
    private function processArgs( array $arguments ): array {
        $data = [
            'limit' => $arguments['limit'] ?? '',
            'page' => $arguments['page'] ?? '',
            'locale' => $this->processLocale( $arguments ),
            'include' => $this->processIncludes( $arguments ),
        ];

        return array_filter( $data, static function ( $value ) {
            return !empty( $value );
        } );
    }

    /**
     * @param array $arguments Method arguments
     * @return string Locale string
     */
    private function processLocale( array $arguments ): string {
        if ( !isset( $arguments['locale'] ) ) {
            return $this->getConfigValue( 'ApiuntoDefaultLocale' ) ?? '';
        }

        $arguments = $arguments['locale'];

        if ( !is_array( $arguments ) ) {
            $arguments = [ $arguments ];
        }

        return implode( ',', $arguments );
    }

    /**
     * Returns a string with all invalid includes removed
     *
     * @param array $arguments Method arguments
     * @return string
     */
    private function processIncludes( array $arguments ): string {
        if ( !isset( $arguments['include'] ) ) {
            return '';
        }

        $arguments = $arguments['include'];

        if ( !is_array( $arguments ) ) {
            $arguments = [ $arguments ];
        }

        $validIncludes = [];

        foreach ( $arguments as $include ) {
            foreach ( $this->availableIncludes as $availableInclude ) {
                if ( strpos( $include, $availableInclude ) !== false ) {
                    $validIncludes[] = $include;
                }
            }
        }

        return implode( ',', $validIncludes );
    }

    /**
     * Loads a config value for a given key from the main config
     * Returns null on if an ConfigException was thrown
     *
     * @param string $key The config key
     * @param null $default Default value to return
     * @return mixed|null
     */
    private function getConfigValue( string $key, $default = null ) {
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
     * Write the cache key to the page props for purging
     *
     * @param AbstractRepository $repository
     */
    private function writeCachePropertyKey( AbstractRepository $repository ): void {
        wfDebugLog( 'Apiunto', 'Writing page prop' );

        $this->getParser()->getOutput()->setPageProperty( 'apiuntocache', $repository->makeCacheKey() );
    }
}
