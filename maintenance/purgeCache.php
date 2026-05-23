<?php

namespace MediaWiki\Extension\Apiunto\Maintenance;

use MediaWiki\Extension\Apiunto\Services\CachePurger;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class PurgeCache extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription( "Purge the Apiunto cache" );
		$this->setBatchSize( 150 );
		$this->requireExtension( 'Apiunto' );
		$this->addOption( 'dry-run', "Don't actually delete the cache." );
	}

	public function execute() {
		/** @var CachePurger $purger */
		$purger = MediaWikiServices::getInstance()->get( 'Apiunto.CachePurger' );

		$dryRun = $this->getOption( 'dry-run' ) !== null;

		$purger->purgeAll(
			function ( string $message ) {
				$this->output( "$message\n" );
			},
			$dryRun,
			$this->getBatchSize()
		);
	}
}

$maintClass = PurgeCache::class;
require_once RUN_MAINTENANCE_IF_MAIN;
