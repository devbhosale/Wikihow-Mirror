<?php
/**
 * Update for the master expert verified sheet
 *
 */

/*
CREATE TABLE `master_expert_sheet_update` (
	`mesu_running` int(10) unsigned NOT NULL,
	`mesu_stats` blob NOT NULL,
	`mesu_start_time` datetime NOT NULL,
	`mesu_finish_time` datetime NOT NULL
);
 */
// this  will update all indexable recipe pages based on latest good revision
class MasterExpertSheetUpdate implements DeferrableUpdate {

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Run the update
	 */
	public function doUpdate() {
		self::doSheetUpdate();
	}

	public static function getCurrentStatus() {
		$dbw = wfGetDB( DB_MASTER );
		$running = $dbw->selectField( 'master_expert_sheet_update', 'mesu_running', array(), __METHOD__ );
		return $running;
	}

	public static function getStats() {
		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->selectField( 'master_expert_sheet_update', 'mesu_stats', array(), __METHOD__ );
		return $result;
	}

	public static function getLastRunStart() {
		$dbw = wfGetDB( DB_MASTER );
		$time = $dbw->selectField( 'master_expert_sheet_update', 'mesu_start_time', array(), __METHOD__ );
		if ( !$time ) {
			return '';
		}
		$dateTime = new DateTime($time);
		$dateTime->setTimezone(new DateTimeZone('America/Los_Angeles'));
		$result = $dateTime->format("Y-m-d h:i:s A (e)");
		return $result;
	}

	public static function getLastRunFinish() {
		$dbw = wfGetDB( DB_MASTER );
		$time = $dbw->selectField( 'master_expert_sheet_update', 'mesu_finish_time', array(), __METHOD__ );
		if ( !$time ) {
			return '';
		}
		$dateTime = new DateTime($time);
		$dateTime->setTimezone(new DateTimeZone('America/Los_Angeles'));
		$result = $dateTime->format("Y-m-d h:i:s A (e)");
		return $result;
	}

	public static function checkSheetUpdateTimeout() {
		$dbw = wfGetDB( DB_MASTER );
		$time = $dbw->selectField( 'master_expert_sheet_update', 'mesu_start_time', array(), __METHOD__ );
		$difference = time() - strtotime($time);
		if ( $difference > 60 * 5 ) {
			// reset the job since there is an error
			// should prbably log this somehow to the user?
			$updateData = array(
				'mesu_running' => 0,
			);
			$dbw->update( 'master_expert_sheet_update', $updateData, array(), __METHOD__ );
			return true;
		}
		return false;
	}

	public static function doSheetUpdate() {
		ini_set('memory_limit', '1024M');
		//set_time_limit(300);
		$dbw = wfGetDB( DB_MASTER );

		$running = $dbw->selectField( 'master_expert_sheet_update', 'mesu_running', array(), __METHOD__ );
		if ( $running == null ) {
			// edge case if there is no data in this table
			$running = $dbw->insert( 'master_expert_sheet_update', array( 'mesu_running' => 1 ), __METHOD__ );
		} else if ( $running == 1 ) {
			return;
		}

		$old_user_abort = ignore_user_abort( true );
		$startDate = gmdate( "Y-m-d H:i:s" );
		$updateData = array(
			'mesu_running' => 1,
			'mesu_start_time' => $startDate,
			'mesu_finish_time' => ''
		);
		$dbw->update( 'master_expert_sheet_update', $updateData, array(), __METHOD__ );

		$importer = new ExpertVerifyImporter();
		$result = $importer->getSpreadsheet();
		$result['html'] = "<p>Result: ". count( $result['imported'] ) ." lines imported.</p>";
		unset($result['imported']);
		$result['stats'] = self::getVerifierStats();

		$finishDate = gmdate( "Y-m-d H:i:s" );
		$updateData = array(
			'mesu_running' => 0,
			'mesu_stats' => json_encode( $result ),
			'mesu_finish_time' => $finishDate
		);

		$dbw->update( 'master_expert_sheet_update', $updateData, array(), __METHOD__ );
		ignore_user_abort( $old_user_abort );
	}

	private static function getVerifierStats() {
		// get the verify data for all pages that have it
		$pages = VerifyData::getAllVerifiersFromDB();

		// get the total count
		$total = count( $pages );

		// set up result array
		$counts = array_flip( ExpertVerifyImporter::getWorksheetIds() );
		$counts = array_map( function() { return 0; }, $counts );
		$counts['total'] = $total;

		// now count the specific worksheet values
		foreach ( $pages as $page ) {
			// decode the json array of article verify info
			$pageInfo = json_decode( $page );

			// we only will display the last element of this array of page info
			// so therefore we will also only count the last element of this array
			$expert = array_pop( $pageInfo );

			// increment our result array
			$counts[$expert->worksheetName]++;
		}
		$text = "";
		foreach ( $counts as $name => $count ) {
            $nameText = wfMessage( 'asp_' . $name )->text();
			$text .= "<b>$count</b> $nameText<br>";
		}

		$elem = Html::rawElement( 'p', array( 'class'=>'sp_stat' ), $text );

		return $elem;
	}
}
