<?php

if (!defined('MEDIAWIKI')) exit;

class UserPagePolicy {

	// The minimum number of contributions a user can have done before
	// their user page is viewable to anons such as Googlebot
	const ANON_VIEWABLE_MIN_EDIT_COUNT = 20;

	/**
	 * Cache outcome of good user page to allow for multiple calls
	 */
	static $goodUserCache;

	/**
	 * Determine if we want to display this user page or 404
	 * - $name: user name of the user page
	 * - $checkLoggedIn: true = check the logged in status of the current user / false = skip check
	 *   (this is for calls where we have to cache and need to determine logged in status later)
	 * @return True to display, or false to 404
	 */
	public static function isGoodUserPage($name, $checkLoggedIn = true) {
		global $wgUser, $wgOut;

		if (isset(self::$goodUserCache[$name])) {
			return self::$goodUserCache[$name];
		}

		$user = User::newFromName($name);
		if (!$user || $user->getID() == 0) {
			self::$goodUserCache[$name] = false;
			return false;
		}

		// Hide user pages belonging to users who've been inactive for 1+ years
		$lastYear = wfTimestamp( TS_MW, strtotime( '-1 year' ) );
		if ( $wgUser->isAnon() && $user->getTouched() < $lastYear ) {
			self::$goodUserCache[$name] = false;
			return false;
		}

		// All user pages are viewable for logged in users that view
		if ($checkLoggedIn && $wgUser && $wgUser->getID() > 0) {
			self::$goodUserCache[$name] = true;
			return true;
		}

		// User has at least N main namespace edits?
		if ($user->getEditCount() >= self::ANON_VIEWABLE_MIN_EDIT_COUNT) {
			self::$goodUserCache[$name] = true;
			return true;
		}

		$dbr = wfGetDB(DB_SLAVE);

		// Has the user started an indexable article?
		$tables = ['firstedit', 'index_info'];
		$fields = 'count(*) as ct';
		$where = [
			'fe_user' => $user->getID(),
			'fe_page = ii_page',
			'ii_policy' => [1, 4],
		];
		$res = $dbr->selectRow($tables, $fields, $where);
		if ($res->ct > 0) {
			self::$goodUserCache[$name] = true;
			return true;
		}

		self::$goodUserCache[$name] = false;
		return false;
	}

}

