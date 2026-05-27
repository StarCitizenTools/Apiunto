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

/**
 * Stateless codec for the `apiuntocache` page property: the per-page index of which
 * cache keys (and their request URLs) a page populates, used for purging and action=info.
 */
class CacheManifest {

	/**
	 * Decode a stored page-property value into the entries array.
	 *
	 * @param mixed $raw The raw page-property value (string, or anything for robustness).
	 * @return array[] Decoded entries, or [] for empty/non-string/malformed/non-array input.
	 */
	public static function decode( mixed $raw ): array {
		if ( !is_string( $raw ) || $raw === '' ) {
			return [];
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * @param array[] $entries
	 */
	public static function encode( array $entries ): string {
		return (string)json_encode( $entries );
	}

	/**
	 * Record one cache key in the entries array. If an entry with $key already exists its
	 * count is incremented (source/url preserved); otherwise a new entry is appended.
	 *
	 * @param array[] $entries
	 * @return array[]
	 */
	public static function addEntry( array $entries, string $source, string $key, string $url ): array {
		foreach ( $entries as &$entry ) {
			if ( ( $entry['key'] ?? null ) === $key ) {
				$entry['count'] = ( $entry['count'] ?? 1 ) + 1;
				return $entries;
			}
		}
		unset( $entry );

		$entries[] = [
			'source' => $source,
			'key' => $key,
			'url' => $url,
			'count' => 1,
		];

		return $entries;
	}
}
