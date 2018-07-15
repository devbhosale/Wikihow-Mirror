<?php

if (!defined('MEDIAWIKI')) die();

/**
 * ConfigStorage class (and associated Special:AdminConfigEditor page) exist
 * to edit and store large configuration blobs (such as lists of 1000+ URLs)
 * because we've found that Mediawiki messages are not optimal for this task.
 * But it's important that they're non-engineer editable, so we provide an
 * admin interface to edit them.
 */

/*
 *db schema:
 *
CREATE TABLE config_storage (
	cs_key VARCHAR(64) NOT NULL PRIMARY KEY,
	cs_config LONGTEXT NOT NULL,
	cs_article_list TINYINT(3) NOT NULL DEFAULT 0
);
 */

class ConfigStorage {

	const MAX_KEY_LENGTH = 64;

	/**
	 * List all current config keys.
	 */
	public static function dbListConfigKeys() {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select('config_storage', 'cs_key', '', __METHOD__);
		$keys = array();
		foreach ($res as $row) {
			$keys[] = $row->cs_key;
		}
		return $keys;
	}

	/**
	 * get the config value from the database
	 *
	 */
	private static function dbGetConfigFromDatabase($key, $useEnDB = false) {
		$dbr = wfGetDB(DB_SLAVE);
		$table = 'config_storage';
		if ( $useEnDB ) {
			$table = WH_DATABASE_NAME_EN . '.' . $table;
		}
		$res = $dbr->selectField( $table, 'cs_config', array('cs_key' => $key), __METHOD__);

		return $res;
	}

	/**
	 * Pulls the config for a given key from either memcache (if it's there)
	 * or the database.
	 * use $forceEnglish to make sure to get the data from the en database
	 */
	public static function dbGetConfig($key, $forceEnglish = false) {
		global $wgMemc;
		$cachekey = self::getMemcKey($key, $forceEnglish);
		$res = $wgMemc->get($cachekey);
		if (!$res) {
			$res = self::dbGetConfigFromDatabase( $key, $forceEnglish );
			if ($res) {
				$wgMemc->set($cachekey, $res);
			}
		}
		return $res;
	}

	// This should only be used by the Special:AdminTags page. It's not a fast method
	// and should probably be refactored to combine the information fetch if it
	// is going to be called often.
	public static function dbGetIsArticleList($key) {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->selectField('config_storage', 'cs_article_list', array('cs_key' => $key), __METHOD__);
		return $res == "1";
	}

	/**
	 * Update the flag indicating a config storage key is a list of article IDs
	 */
	public static function dbUpdateArticleListFlag(string $key, int $flag) {
		$dbw = wfGetDB(DB_MASTER);
		$dbw->update('config_storage', ['cs_article_list' => $flag], ['cs_key' => $key], __METHOD__);
		return $dbw->affectedRows() > 0;
	}

	/**
	 * Set the new config key in the database (along with the config value).
	 * Clear the memcache key too.
	 */
	public static function dbStoreConfig($key, $config, $isArticleList, &$error, $allowArticleErrors = true, $newTagProb = 0) {
		global $wgMemc, $wgUser;

		if ( !self::checkUserRestrictions($key) ) {
			$error = "Your username '" . $wgUser->getName() . "' cannot modify this key. Ping Elizabeth. :)";
			return false;
		}

		if ( $isArticleList ) {
			$pages = self::convertArticleListToPageIDs($config, $error);
			if (!$allowArticleErrors && $error) {
				return false;
			}
			wfRunHooks( 'ConfigStorageStoreConfig', array( $key, $pages, $newTagProb, &$error) );
		}

		$cachekey = self::getMemcKey($key);
		$wgMemc->delete($cachekey);

		$dbw = wfGetDB(DB_MASTER);
		$dbw->replace('config_storage', 'cs_key',
			[
				['cs_key' => $key, 'cs_config' => $config,
					'cs_article_list' => ($isArticleList ? 1 : 0) ]
			],
			__METHOD__);

        wfRunHooks( 'ConfigStorageAfterStoreConfig', array( $key, $config) );

		return $dbw->affectedRows() > 0;
	}

	public static function dbDeleteConfig($key) {
		global $wgMemc;
		$cachekey = self::getMemcKey($key);
		$wgMemc->delete($cachekey);

		$dbw = wfGetDB(DB_MASTER);
		$dbw->delete('config_storage', ['cs_key' => $key], __METHOD__);
		return $dbw->affectedRows() > 0;
	}

	// consistently generate a memcache key
	// optionally force english key
	private static function getMemcKey($key, $forceEnglishKey = false) {
		global $wgCachePrefix;

		if ( $forceEnglishKey ) {
			$result = wfForeignMemcKey( WH_DATABASE_NAME_EN, $wgCachePrefix, 'cfg', $key);
		} else {
			$result = wfMemcKey('cfg', $key);
		}

		return $result;
	}

	public static function checkUserRestrictions($key) {
		global $wgUser;

		// if they are using a maintenance process, saving is always allowed
		if (!$wgUser) {
			return true;
		}

		$user = $wgUser->getName();

		// Elizabeth can edit any message
		$rules = [
			[ 'keys' => ['editfish-article-exclude-list'],
			  'users' => ['ElizabethD', 'Chris H'] ],
			[ 'keys' => ['picture_patrol_whitelist', 'picture_patrol_blacklist'],
			  'users' => ['ElizabethD', 'AlissaB', 'Argutier', 'Anna'] ],
			[ 'keys' => ['qa_article_ids', 'qa_blacklisted_article_ids', 'qa_box_article_ids', 'qa_category_blacklist'],
			  'users' => ['ElizabethD', 'AlissaB', 'Ethan Teng'] ],
			[ 'keys' => ['wikiphoto-article-exclude-list'],
			  'users' => ['ElizabethD', 'WikiPhoto', 'Wikivisual', 'Wikiphoto'] ],
		];

		foreach ($rules as $rule) {
			if ( in_array($key, $rule['keys']) ) {
				return in_array($user, $rule['users']);
			}
		}

		// any staff or existing special page restrictions are still in effect
		// for less sensitive messages not covered here
		return true;
	}

	/**
	 * Convert a text file (string with line breaks) into a list of articles.
	 * Reports errors in understanding the lines as well.
	 */
	public static function convertArticleListToPageIDs($list, &$err) {
		$err = '';
		$ret = [];
		$lines = explode("\n", $list);
		foreach ($lines as $i => $line) {
			$lineNo = $i + 1;
			$line = trim($line);
			if ($line) {
				$item = [];
				$title = Misc::getTitleFromText($line);
				if (!$title || !$title->exists()) {
					$item['title'] = null;
					$item['err'] = "title not found";
					$item['line'] = $line;
					$item['lineno'] = $lineNo;
					$err .= "$line (line $lineNo): {$item['err']}\n";
				} elseif ($title->isRedirect()) {
					// figure out redirect target, if it has one
					$extraText = '';
					$wikiPage = WikiPage::factory( $title );
					if ($wikiPage) {
						$target = $wikiPage->getRedirectTarget();
						if ($target) {
							$extraText = " to '" . $target->getText() . "'";
						}
					}
					$item['title'] = null;
					$item['err'] = "title is a redirect$extraText";
					$item['line'] = $line;
					$item['lineno'] = $lineNo;
					$err .= "$line (line $lineNo): {$item['err']}\n";
				} else {
					$item['title'] = $title;
					$item['err'] = false;
				}
				$ret[] = $item;
			}
		}
		return $ret;
	}
}
