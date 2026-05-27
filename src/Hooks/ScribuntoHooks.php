<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Apiunto\Hooks;

use MediaWiki\Extension\Apiunto\ApiuntoLuaLibrary;
use MediaWiki\Extension\Scribunto\Hooks\ScribuntoExternalLibrariesHook;

/**
 * Hooks for Apiunto extension
 */
class ScribuntoHooks implements ScribuntoExternalLibrariesHook {

	/**
	 * @inheritDoc
	 */
	public function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.Apiunto'] = ApiuntoLuaLibrary::class;
		}
	}
}
