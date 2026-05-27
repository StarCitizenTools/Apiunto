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

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;

/**
 * Validates and normalizes the raw arguments of a mw.ext.Apiunto.fetch() Lua call.
 */
class LuaArguments {

	/**
	 * @param array $args Raw func_get_args() from the Lua call.
	 * @return array{source: string, identifier: string, query: string[]}
	 * @throws LuaError If the source or identifier argument is missing or the wrong type.
	 */
	public static function parse( array $args ): array {
		if ( !isset( $args[0] ) || !is_string( $args[0] ) || $args[0] === '' ) {
			throw new LuaError( 'Apiunto: Call to getRaw() requires a non-empty string source as the first argument.' );
		}
		$source = $args[0];

		if ( !isset( $args[1] ) || !is_string( $args[1] ) ) {
			throw new LuaError( 'Apiunto: Call to getRaw() requires a string identifier as the second argument.' );
		}
		$identifier = $args[1];

		$inputOptions = [];
		if ( isset( $args[2] ) ) {
			if ( is_array( $args[2] ) ) {
				$inputOptions = $args[2];
			} else {
				wfLogWarning( sprintf(
					'Apiunto: Call to getRaw() for identifier "%s" expected an array for options ' .
						'(third argument), got %s. Proceeding with empty options.',
					$identifier,
					gettype( $args[2] )
				) );
			}
		}

		return [
			'source' => $source,
			'identifier' => $identifier,
			'query' => self::normalizeQuery( $inputOptions ),
		];
	}

	/**
	 * @param array $arguments
	 * @return string[] HTTP query data, filtered for non-empty values.
	 */
	private static function normalizeQuery( array $arguments ): array {
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
}
