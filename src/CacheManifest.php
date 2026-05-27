<?php

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
