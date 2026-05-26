<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Services;

use MediaWiki\Config\Config;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Resolves a page's apiuntocache page-property entries into structured rows describing the
 * live cache state, for display on action=info.
 *
 * The page property is the parser-owned index of which keys/URLs a page populates; the live
 * cache state (whether a key is currently cached, when it was cached, when it expires) is read
 * from WANObjectCache at render time so it is never stale.
 */
class CacheInfoResolver {

	public const STATUS_CACHED = 'cached';
	public const STATUS_NOT_CACHED = 'not-cached';
	public const STATUS_DISABLED = 'caching-disabled';

	private const DEFAULT_CACHE_DURATION = 86400;

	public function __construct(
		private readonly WANObjectCache $cache,
		private readonly Config $config
	) {
	}

	/**
	 * @param array $entries Decoded apiuntocache page-property entries
	 * @return array[] One row per entry, each with keys: source, url, key, count, cacheDuration,
	 *   status, cachedOn, expiresOn
	 */
	public function resolve( array $entries ): array {
		$enabled = $this->config->get( 'ApiuntoEnableCache' ) === true;
		$sources = $this->config->get( 'ApiuntoSources' );
		if ( !is_array( $sources ) ) {
			$sources = [];
		}

		$liveByKey = $enabled ? $this->fetchLiveState( $entries ) : [];

		$rows = [];
		foreach ( $entries as $entry ) {
			$source = (string)( $entry['source'] ?? '' );
			$key = isset( $entry['key'] ) ? (string)$entry['key'] : null;

			$row = [
				'source' => $source,
				'url' => isset( $entry['url'] ) ? (string)$entry['url'] : null,
				'key' => $key,
				'count' => (int)( $entry['count'] ?? 1 ),
				'cacheDuration' => (int)( $sources[$source]['cacheDuration'] ?? self::DEFAULT_CACHE_DURATION ),
				'status' => self::STATUS_NOT_CACHED,
				'cachedOn' => null,
				'expiresOn' => null,
			];

			if ( !$enabled ) {
				$row['status'] = self::STATUS_DISABLED;
			} elseif ( $key !== null && isset( $liveByKey[$key] ) ) {
				$row['status'] = self::STATUS_CACHED;
				$row['cachedOn'] = $liveByKey[$key]['cachedOn'];
				$row['expiresOn'] = $liveByKey[$key]['expiresOn'];
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Batch-read the live cache state for the entries' keys in a single round trip.
	 *
	 * @param array $entries
	 * @return array[] Keyed by cache key; each value has cachedOn and expiresOn. Only includes
	 *   keys that are currently cached (present and not expired).
	 */
	private function fetchLiveState( array $entries ): array {
		$keys = [];
		foreach ( $entries as $entry ) {
			if ( isset( $entry['key'] ) ) {
				$keys[] = (string)$entry['key'];
			}
		}
		if ( !$keys ) {
			return [];
		}

		$curTTLs = [];
		// Seed $info with the PASS_BY_REF sentinel to opt into the rich per-key metadata format.
		$info = WANObjectCache::PASS_BY_REF;
		$this->cache->getMulti( $keys, $curTTLs, [], $info );

		$live = [];
		foreach ( $keys as $key ) {
			$asOf = $info[$key][WANObjectCache::KEY_AS_OF] ?? null;
			$curTTL = $curTTLs[$key] ?? null;
			$ttl = $info[$key][WANObjectCache::KEY_TTL] ?? null;
			if ( $asOf !== null && $curTTL !== null && $curTTL > 0 ) {
				$live[$key] = [
					'cachedOn' => (int)$asOf,
					'expiresOn' => $ttl !== null ? (int)( $asOf + $ttl ) : (int)( $asOf + $curTTL ),
				];
			}
		}

		return $live;
	}
}
