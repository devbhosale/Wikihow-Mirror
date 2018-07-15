<?php

/******
 * Class RatingsTool
 * Abstract class that manages the information regarding ratings.
 * Class must be extended for each new set of ratings that are created.
 */

abstract class RatingsTool {

	protected $tableName;
	protected $tablePrefix;
	protected $ratingType;
	protected $logType;
	protected $lowTable;
	protected $lowTablePrefix;
	protected $mContext;

	protected function __construct() {
		$this->reasonTable = 'rating_reason';
		$this->reasonPrefix = 'ratr_';
	}

	public function setContext($context){
		$this->mContext = $context;
	}

	public function getContext() {
		return $this->mContext;
	}

	public function restore($itemId, $user, $hi, $low) {
		$dbw = wfGetDB(DB_MASTER);
		$pf = $this->tablePrefix;
		$dbw->update($this->tableName,
			["{$pf}isdeleted" => 0],
			["{$pf}user_deleted" => $user, "{$pf}page" => $itemId, "{$pf}id <= $hi", "{$pf}id" >= $low],
			__METHOD__);

	}

	public function getUnrestoredCount($itemId) {
		$dbr = wfGetDB(DB_SLAVE);
		$pf = $this->tablePrefix;
		$count = $dbr->selectField($this->tableName,
			'count(*)',
			["{$pf}page" => $itemId, "{$pf}isdeleted" => 1],
			__METHOD__);

		return $count;
	}

	public function getAllRatedItems() {
		$dbr = wfGetDB(DB_SLAVE);

		$pf = $this->tablePrefix;
		$res = $dbr->select($this->tableName,
			"{$pf}page",
			[],
			__METHOD__,
			["GROUP BY" => "{$pf}page"]);

		$results = [];
		foreach ($res as $item) {
			$results[] = $item->{$pf.'page'};
		}

		return $results;
	}

	public function clearRatings($itemId, $user, $reason = null) {
		global $wgRequest, $wgLanguageCode, $wgEnotifWatchlist;
		$dbw = wfGetDB( DB_MASTER );

		$pf = $this->tablePrefix;
		$result = $dbw->selectRow( $this->tableName,
			["max({$pf}id) AS max",
			 "min({$pf}id) AS min",
			 "count(*) AS count"],
			["{$pf}page" => $itemId, "{$pf}isdeleted" => 0],
			__METHOD__ );

		$dbw->update($this->tableName,
			["{$pf}isdeleted" => 1, "{$pf}deleted_when = now()", "{$pf}user_deleted" => $user->getID()],
			["{$pf}page" => $itemId, "{$pf}isdeleted" => 0],
			__METHOD__);

		//FIX
		if ($wgLanguageCode == 'en')
			$dbw->delete($this->lowTable, array("{$this->lowTablePrefix}page" => $itemId));
		if ($reason == null)
			$reason = $wgRequest->getVal('reason');

		if ($this->ratingType == 'article') {
			wfRunHooks("RatingsCleared", array($this->ratingType, $itemId));
		}

		$wgEnotifWatchlist = false; // We don't want watchers to receive emails about this
		$this->logClear($itemId, $result->max, $result->min, $result->count, $reason);
	}

	public function listRecentResets($humanTime = '24 hours ago') {
		$newerThan = strtotime($humanTime);
		$dbr = wfGetDB( DB_SLAVE );
		$pf = $this->tablePrefix;
		$res = $dbr->select( $this->tableName,
			["{$pf}page AS pageid", "UNIX_TIMESTAMP(MAX({$pf}deleted_when)) AS reset_time"],
			["{$pf}deleted_when > FROM_UNIXTIME({$newerThan})", "{$pf}isdeleted" => 1],
			__METHOD__,
			["GROUP BY" => "{$pf}page"] );
		$rows = [];
		foreach ($res as $row) {
			// cast array values to int
			$row = array_map(function ($x) { return (int)$x; }, (array)$row);
			$rows[] = $row;
		}
		return $rows;
	}

	public function addRatingDetail($ratingId, $ratingDetail) {
		if ($ratingId <= 0) {
			return;
		}

		$detail = intval(substr($ratingDetail, -1));
		if (!$detail || $detail <= 0) {
			return;
		}

		$dbw = wfGetDB(DB_MASTER);
		$table = $this->tableName;
		$pf = $this->tablePrefix;
		$values = array($pf."detail"=>$detail);
		$conds = array($pf."id"=>$ratingId);
		$fname = __METHOD__;
		$options = array();

		$dbw->update($table, $values, $conds, $fname, $options);
	}

	public function addRatingReason($ratrPageId, $ratrItem, $ratrUser, $ratrUserText, $ratrReason, $ratrType, $ratrRating, $ratrDetail, $ratrName, $ratrEmail, $ratrIsPublic, $ratrFirstname, $ratrLastname) {
		global $wgUser;

		$dbw = wfGetDB(DB_MASTER);

		$pf = $this->reasonPrefix;

		$table = $this->reasonTable;
		$a = array();
		$a[$pf."page_id"] = $ratrPageId ? $ratrPageId : 0;
		$a[$pf."item"] = strip_tags($ratrItem);
		$a[$pf."user"] = intval($ratrUser);
		$a[$pf."user_text"] = strip_tags($ratrUserText);
		$a[$pf."text"] = strip_tags($ratrReason);
		$a[$pf."type"] = strip_tags($ratrType);
		$a[$pf."rating"] = intval($ratrRating);
		if ($ratrDetail) {
			$a[$pf."detail"] = strip_tags($ratrDetail);
		}
		if ($ratrName) {
			$a[$pf."name"] = strip_tags($ratrName);
		}
		if ($ratrEmail) {
			$a[$pf."email"] = strip_tags($ratrEmail);
		}

		$dbw->insert($table, $a, __METHOD__);
		$insertId = $dbw->insertId();
		//if it's public, then also add to UserReview table
		if ($ratrIsPublic) {
			$sur = SubmittedUserReview::newFromFields(
				$ratrPageId,

				$ratrFirstname,
				$ratrLastname,
				$ratrReason,
				"",
				$wgUser->getId(),
				WikihowUser::getVisitorId()
			);
			if( $sur->isQualified()) {
				$sur->correctFields();
				$sur->save();
			}
		}

		wfRunHooks( 'RatingsToolRatingReasonAdded', array( $insertId, $a ) );

		return $this->getRatingReasonResponse($ratrRating);
	}

	public function deleteRatingReason($ratrItem) {
		$dbw = wfGetDB(DB_MASTER);

		$dbw->delete($this->reasonTable, array('ratr_item' => $ratrItem), __METHOD__);
	}

	public function addRating($itemId, $user, $userText, $rating, $source) {
		$dbw = wfGetDB(DB_MASTER);

		$pf = $this->tablePrefix;
		$month = date("Y-m");
		$table = $this->tableName;
		$rows = array();
		$rows[$pf."page"] = $itemId;
		$rows[$pf."user"] = $user;
		$rows[$pf."user_text"] = $userText;
		$rows[$pf."rating"] = $rating;
		$rows[$pf."month"] = $month;
		$rows[$pf."source"] = $source;
		$ratingId = $pf."id";
		$set = array(
			$pf."rating = $rating",
			"$ratingId = LAST_INSERT_ID($ratingId)",
			$pf."isdeleted = 0",
			$pf."user_deleted = NULL",
			$pf."deleted_when = 0"
		);
		$dbw->upsert($table, $rows, array(), $set, __METHOD__);
		$ratingId = $dbw->insertId();

		wfRunHooks("RatingAdded", array($this->ratingType, $itemId));

		return $this->getRatingResponse($itemId, $rating, $source, $ratingId);
	}

	function showClearingInfo($title, $id, $selfUrl, $target) {
		global $wgOut;

		$dbr = wfGetDB( DB_SLAVE );

		$wgOut->addHTML(wfMessage('clearratings_previous_clearings') . "<ul>");

		$loggingResults = $this->getLoggingInfo($title);

		foreach($loggingResults as $logItem) {
			$wgOut->addHTML("<li>" . Linker::link($logItem['userPage'], $logItem['userName']) . " ({$logItem['date']}): ");
			$wgOut->addHTML( $logItem['comment']);
			$wgOut->addHTML("</i>");
			if ($logItem['show']) {
				$linkAttribs = array(
					"page" => $id,
					"type" => $this->ratingType,
					"hi" => $logItem['params'][2],
					"low" => $logItem['params'][1],
					"target" => $target,
					"user" => $logItem['userId'],
					"restore" => 1);
				$wgOut->addHTML("(" . Linker::link($selfUrl, wfMessage('clearratings_previous_clearings_restore'), $linkAttribs) . ")");
			}
			$wgOut->addHTML("</li>");
		}
		$wgOut->addHTML("</ul>");

		if (count($loggingResults) == 0)
			$wgOut->addHTML(wfMessage('clearratings_previous_clearings_none') . "<br/><br/>");

		$pf = $this->tablePrefix;
		$res= $dbr->select($this->tableName,
			array ("COUNT(*) AS C", "AVG({$pf}rating) AS R"),
			array ("{$pf}page" => $id, "{$pf}isdeleted" => "0"),
			__METHOD__);

		if ($row = $dbr->fetchObject($res))  {
			$percent = $row->R * 100;
			$wgOut->addHTML( Linker::link($title, $title->getFullText() ) . "<br/><br/>"  .
				wfMessage('clearratings_number_votes') . " {$row->C}<br/>" .
				wfMessage('clearratings_avg_rating') . " {$percent} %<br/><br/>
						<form  id='clear_ratings' method='POST' action='{$selfUrl->getFullURL()}'>
							<input type=hidden value='$id' name='clearId'>
							<input type=hidden value='{$this->ratingType}' name='type'>
							<input type=hidden value='" . htmlspecialchars($target) ."' name='target'>
							" . wfMessage('clearratings_reason') . " <input type='text' name='reason' size='40'><br/><br/>
							<input type=submit value='" . wfMessage('clearratings_clear_submit') . "'>
						</form><br/><br/>
						");
		}
		$dbr->freeResult($res);
	}

	function showListRatings() {
		global $wgOut;

		// Just change this if you don't want users seeing the ratings
		$wgOut->setHTMLTitle('List Ratings - Accuracy Patrol');
		$wgOut->setPageTitle('List ' . ucfirst($this->ratingType) . ' Ratings');

		list( $limit, $offset ) = wfCheckLimits();
		$lrs = new ListRatingsPage();
		$lrs->setRatingTool($this);
		$lrs->doQuery( $offset, $limit );
	}

	function getRatings() {
		$dbr = wfGetDB(DB_SLAVE);
		$pf = $this->tablePrefix;
		$res = $dbr->select($this->tableName,
			["{$pf}page", "AVG({$pf}rating) as R", 'count(*) as C'],
			[],
			__METHOD__,
			['GROUP BY' => "{$pf}page", 'ORDER BY' => 'R DESC', "LIMIT" => 50]);

		return $res;
	}

	function showAccuracyPatrol() {
		global $wgOut;

		$wgOut->setHTMLTitle(wfMessage('accuracypatrol'));
		$wgOut->setPageTitle(ucfirst($this->ratingType) . " Accuracy Patrol");

		list( $limit, $offset ) = wfCheckLimits();
		$llr = $this->getQueryPage();
		return $llr->doQuery( $offset, $limit );
	}

	function getListRatingsSql() {
		$pf = $this->tablePrefix;
		return "SELECT {$pf}page, AVG({$pf}rating) as R, count(*) as C
			FROM {$this->tableName}
			WHERE {$pf}isDeleted = '0'
			GROUP BY {$pf}page
			ORDER BY R";
	}

	function getTablePrefix() {
		return $this->tablePrefix;
	}

	function getTableName() {
		return $this->tableName;
	}

	function getLowTableName() {
		return $this->lowTable;
	}

	function getLowTablePrefix() {
		return $this->lowTablePrefix;
	}

	protected abstract function logClear($itemId, $max, $min, $count, $reason);
	protected abstract function logRestore($itemId, $low, $hi, $reason, $count);
	protected abstract function getLoggingInfo($title);
	protected abstract function makeTitle($itemId);
	protected abstract function makeTitleFromId($itemId);
	protected abstract function getId($title);
	protected abstract function getRatingResponse($itemId, $rating, $source, $ratingId);
	public abstract function getRatingReasonResponse($rating);
	protected abstract function getRatingForm();
	protected abstract function getMobileRatingForm();
	protected abstract function getQueryPage();

	/*
	 * Send an email to the original article author based on
	 * how many times the "Did this article help you?" Yes/No
	 * has been clicked.
	 *
	 * - 1st
	 * - 10th
	 * - 50th
	 * - 100th
	 */
	function sendHelpfulEmails($type, $pageid) {
		global $wgLanguageCode, $wgIsDevServer;

		$msg = '';
		$basesite = WikihowMobileTools::getNonMobileSite();
		$dbr = wfGetDB(DB_SLAVE);

		// only for English
		if ($wgLanguageCode != 'en') return true;
		// not for samples
		if ($type != 'article') return true;

		$t = Title::newFromID($pageid);
		$r = Revision::newfromTitle($t);
		if (!$r) return true;

		// not for articles with bad templates
		$badTemplates = implode("|", explode("\n", trim(wfMessage('ratings_bad_templates'))));
		$hasBadTemp = preg_match("@{{($badTemplates)[}|]@mi", $r->getText()) == 1 ? 1 : 0;
		if ($hasBadTemp) return true;

		// gotta be promoted
		if (!Newarticleboost::isNABbed($dbr, $pageid)) return true;

		// grab the author's email (if they want emails)
		$to_email = AuthorEmailNotification::getArticleAuthorEmail($pageid);
		if ($to_email == '') return true;

		$helpful = 0;
		$unhelpful = 0;
		$res = $dbr->select('rating',
			array('rat_rating','count(rat_rating) as c'),
			array('rat_page' => $pageid),
			__METHOD__,
			array('GROUP BY' => 'rat_rating'));
		foreach ($res as $row) {
			if ($row->rat_rating == 0)
				$unhelpful = $row->c;
			else
				$helpful = $row->c;
		}

		if ($helpful == 1 && $unhelpful <= 1) {
			$msg = 'helpful-email-1';
		}
		elseif ($helpful == 10 && $unhelpful <= 3) {
			$msg = 'helpful-email-10';
		}
		elseif ($helpful == 50 && $unhelpful <= 10) {
			$msg = 'helpful-email-50';
		}
		elseif ($helpful == 100 && $unhelpful <= 20) {
			$msg = 'helpful-email-100';
		}

		// send email
		if ($msg) {
			// tracking querystring
			$qs = '?utm_source='.str_replace('-','_',$msg).'&utm_medium=email&utm_campaign=helpful_vote_email&utm_term=';

			$unsub_link = UnsubscribeLink::newFromEmail($to_email)->getLink();
			$article_title = $t->getText();
			$article_link = $basesite . '/' . $t->getPartialUrl() . $qs.'article_title';

			$req_link = $basesite. '/Special:ListRequestedTopics'.$qs.'answer_request';
			$topcat = Categoryhelper::getTopCategory($t);
			if ($topcat) {
				$req_link .= '&category='.str_replace('-','+',$topcat);
				$topcat = str_replace('-',' ',$topcat);
			}

			$cleanup_link = $basesite.'/Special:EditFinder/Cleanup'.$qs.'greenhouse';
			$pref_link = $basesite.'/Special:AuthorEmailNotification'.$qs.'change_prefs';

			$subject = wfMessage($msg.'-subject')->text();
			$body = wfMessage($msg.'-body', $article_link, $article_title, $req_link, $topcat, $unsub_link, $cleanup_link, $pref_link)->text();

			$from = new MailAddress('wikiHow Community Team <communityteam@wikihow.com>');
			$to = new MailAddress($to_email);
			$content_type = "text/html; charset=UTF-8"; // HTML email

			if (!$wgIsDevServer) {
				$sent = UserMailer::send($to, $from, $subject, $body, null, $content_type, "aen_helpful");
				$result = $sent->isGood() ? true : false;
			}

		}

		return true;
	}

}

/*
Ratings Reason DB Table

	CREATE TABLE `rating_reason` (
	`ratr_id` int(8) unsigned NOT NULL auto_increment,
	`ratr_item` varchar(255) default NULL,
	`ratr_user` int(5) unsigned NOT NULL default '0',
	`ratr_user_text` varchar(255) NOT NULL default '',
	`ratr_timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
	`ratr_text` varchar(255) NOT NULL,
	`ratr_type` varchar(10) NOT NULL,
	`ratr_type` varchar(10) NOT NULL,
	`ratr_rating` tinyint(1) unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY  (`ratr_id`),
	UNIQUE KEY `ratr_id` (`ratr_id`),
	KEY `ratr_timestamp` (`ratr_timestamp`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;

this is an extra column added to the table to store the rating value:
alter table rating_reason add ratr_rating tinyint(1) unsigned NOT NULL DEFAULT '0';

*/