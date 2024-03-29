<?php

use MediaWiki\Revision\RevisionRecord;

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	require_once getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php';
} else {
	require_once __DIR__ . '/../../../maintenance/Maintenance.php';
}

/**
 * Maintenance script that populates the contributors table with contributors' data
 *
 * @ingroup Maintenance
 */
class PopulateContributorsTable extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Populates the contributors table with contributors' data" );
		$this->requireExtension( 'Contributors' );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$this->output( "Started processing..\n" );
		$dbw = $this->getDB( DB_PRIMARY );
		$dbr = $this->getDB( DB_REPLICA );

		$start = $dbr->selectField( 'revision', 'MIN(rev_page)', false, __METHOD__ );
		if ( !$start ) {
			$this->output( "Nothing to do.\n" );
			return true;
		}
		$end = $dbr->selectField( 'revision', 'MAX(rev_page)', false, __METHOD__ );

		$end += $this->mBatchSize - 1;
		$blockStart = $start;
		$blockEnd = $start + $this->mBatchSize - 1;
		while ( $blockEnd <= $end ) {
			$this->output( "Getting Contributor's data..\n" );
			$cond = [
				"rev_page BETWEEN $blockStart AND $blockEnd",
				$dbr->bitAnd( 'rev_deleted', RevisionRecord::DELETED_USER ) . ' = 0'
			];
			$res = $dbr->select(
				[ 'revision', 'actor' ],
				[
					'COUNT(*) AS cn_revision_count',
					'rev_page',
					'MIN(rev_timestamp) AS cn_first_edit',
					'MAX(rev_timestamp) AS cn_last_edit',
					'actor_user',
					'actor_name'
				],
				$cond,
				__METHOD__,
				[ 'GROUP BY' => [ 'rev_page', 'rev_actor', 'actor_name' ] ],
				[ 'actor' => [ 'JOIN', 'rev_actor = actor_id' ] ]
			);

			$this->output( "Writing data into Contributors Table.. \n" );

			foreach ( $res as $row ) {
				if ( !isset( $row->actor_user ) ) {
					$row->actor_user = 0;
				}
				$dbw->upsert(
					'contributors',
					[
						'cn_page_id' => $row->rev_page,
						'cn_user_id' => $row->actor_user,
						'cn_user_text' => $row->actor_name,
						'cn_revision_count' => $row->cn_revision_count,
						'cn_first_edit' => $row->cn_first_edit,
						'cn_last_edit' => $row->cn_last_edit
					],
					[
						[ 'cn_page_id', 'cn_user_id', 'cn_user_text' ]
					],
					[
						'cn_page_id' => $row->rev_page,
						'cn_user_id' => $row->actor_user,
						'cn_user_text' => $row->actor_name,
						'cn_revision_count' => $row->cn_revision_count,
						'cn_first_edit' => $row->cn_first_edit,
						'cn_last_edit' => $row->cn_last_edit
					],
					__METHOD__
				);
			}
			$blockStart += $this->mBatchSize;
			$blockEnd += $this->mBatchSize;
			$this->output( "Process finished.\n" );
		}
	}
}

$maintClass = PopulateContributorsTable::class;
require_once RUN_MAINTENANCE_IF_MAIN;
