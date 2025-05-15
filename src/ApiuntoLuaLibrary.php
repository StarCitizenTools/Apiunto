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

    /** @var string */
    public const PARAM_LIMIT = 'limit';

    /** @var string */
    public const PARAM_PAGE = 'page';

    /** @var string */
    public const PARAM_LOCALE = 'locale';
    
    /** @var string */
    public const PARAM_INCLUDE = 'include';

    private static ?Client $client = null;

    public function __construct( LuaEngine $engine ) {
        parent::__construct( $engine );

        if ( static::$client === null ) {
            $this->initGuzzleClient();
        }
    }

    /**
     * Initializes the guzzle client.
     * Adds the bearer token if set in the config.
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
     * Registers the callable lua methods.
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
     * Raw request.
     * Identifier is the complete uri excluding 'api'.
     *
     * @throws LuaError If arguments are invalid.
     */
    public function getRaw(): array {
        $args = func_get_args();

        if ( !isset( $args[0] ) || !is_string( $args[0] ) || $args[0] === '' ) {
            throw new LuaError( 'Apiunto: Call to getRaw() requires a non-empty string identifier as the first argument.' );
        }
        $identifier = $args[0];

        $inputOptions = [];
        if ( isset( $args[1] ) ) {
            if ( is_array( $args[1] ) ) {
                $inputOptions = $args[1];
            } else {
                // Log a warning but proceed with empty options if the second arg is not an array.
                // Lua will get an empty result if this leads to an invalid API call,
                // or the API might handle default parameters.
                wfLogWarning( sprintf(
                    'Apiunto: Call to getRaw() for identifier "%s" expected an array for options (second argument), got %s. Proceeding with empty options.',
                    $identifier,
                    gettype( $args[1] )
                ) );
            }
        }

        $repository = new RawRepository( static::$client, [
            self::IDENTIFIER => $identifier,
            self::QUERY_PARAMS => $this->processArgs( $inputOptions ),
        ] );

        $response = $repository->getRaw();
        $this->writeCachePropertyKey( $repository );

        return [ $response ];
    }

    /**
     * Processes the method arguments from Lua.
     *
     * @param array $arguments Method arguments from Lua.
     * @return array HTTP Query data, filtered for non-empty values.
     */
    private function processArgs( array $arguments ): array {
        $data = [
            self::PARAM_LIMIT => $arguments[self::PARAM_LIMIT] ?? '',
            self::PARAM_PAGE => $arguments[self::PARAM_PAGE] ?? '',
            self::PARAM_LOCALE => $this->processLocale( $arguments ),
            self::PARAM_INCLUDE => $this->processIncludes( $arguments ),
        ];

        return array_filter( $data, static function ( $value ) {
            return !empty( $value );
        } );
    }

    /**
     * @param array $arguments Method arguments from Lua.
     * @return string Comma-separated string of locales, or default/empty string.
     */
    private function processLocale( array $arguments ): string {
        if ( !isset( $arguments[self::PARAM_LOCALE] ) ) {
            return $this->getConfigValue( 'ApiuntoDefaultLocale' ) ?? '';
        }

        $localeValue = $arguments[self::PARAM_LOCALE];

        $localesArray = is_array( $localeValue ) ? $localeValue : [ $localeValue ];
        $localesArray = array_map( 'strval', $localesArray );

        return implode( ',', $localesArray );
    }

    /**
     * @param array $arguments Method arguments from Lua.
     * @return string Comma-separated string of includes, or empty string.
     */
    private function processIncludes( array $arguments ): string {
        if ( !isset( $arguments[self::PARAM_INCLUDE] ) ) {
            return '';
        }

        $includesInput = $arguments[self::PARAM_INCLUDE];

        if ( is_array( $includesInput ) ) {
            $includesArray = array_map( 'strval', $includesInput );
        } elseif ( is_string( $includesInput ) ) {
            $includesArray = [ $includesInput ];
        } else {
            return '';
        }

        return implode( ',', $includesArray );
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
     */
    private function writeCachePropertyKey( AbstractRepository $repository ): void {
        wfDebugLog( 'Apiunto', 'Writing page prop' );

        $parserOutput = $this->getParser()->getOutput();

        $parserOutput->setPageProperty( AbstractRepository::PROP_KEY, $repository->makeCacheKey() );
        $parserOutput->setNumericPageProperty( AbstractRepository::PROP_KEY_CACHE_TIME, time() );
    }
}
