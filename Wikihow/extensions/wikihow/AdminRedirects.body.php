<?php

class AdminRedirects extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct('AdminRedirects');
	}

	function getIntlRedirect($lang, $pageid) {
		static $dbr = null;
		if (!$dbr) $dbr = wfGetDB(DB_SLAVE);

		$tables = Misc::getLangDB($lang) . '.redirect';
		$fields = 'rd_title';
		$where = [ 'rd_from' => intval($pageid), 'rd_namespace' => NS_MAIN ];

		$res = $dbr->select($tables, $fields, $where);
		$row = $res ? $res->fetchObject() : null;
		return $row ? Misc::getLangBaseURL($lang) . '/' . $row->rd_title : '';
	}

    private static function httpDownloadHeaders() {
		$date = date('Y-m-d');
		header('Content-type: application/force-download');
		header('Content-disposition: attachment; filename="redirects_' . $date . '.xls"');
	}

	/**
	 * Execute special page.  Only available to wikihow staff.
	 */
	function execute($par) {
		global $wgRequest, $wgOut, $wgUser, $wgLang;

		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || !in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		if ($wgRequest->wasPosted()) {
			set_time_limit(0);
			$pageList = $wgRequest->getVal('pages-list', '');
			$wgOut->setArticleBodyOnly(true);
			if ($pageList) $pageList = urldecode($pageList);
			$pageList = preg_split('@[\r\n]+@', $pageList);
			$urls = array();
			$partials = array();
			foreach ($pageList as $url) {
				$url = trim($url);
				if (!empty($url)) {
					$urlDecoded = urldecode($url);
					$urls[] = $url;
					$urls[] = $urlDecoded;
					$partials[] = array();
					$pIndex = count($partials) - 1;
					if (preg_match('@^http://[^/]+/([^?]+)@', $url, $m)) {
						$partials[$pIndex]['plain']['match'] = $m[1];
						$partials[$pIndex]['plain']['url'] = $url;
					}
					if (preg_match('@^http://[^/]+/([^?]+)@', $urlDecoded, $m)) {
						$partials[$pIndex]['decoded']['match'] = $m[1];
						$partials[$pIndex]['decoded']['url'] = $urlDecoded;
					}
				}
			}

			$results = Misc::getPagesFromURLs($urls, array('page_id', 'page_is_redirect'), true);

			$lines = array();
			foreach ($results as $url => $result) {
				$storedUrl = '';

				foreach ($partials as $pIndex => $partialInfo) {
					if (!$partialInfo) {
						continue;
					}

					if (($partialInfo['plain'] && $partialInfo['plain']['match'] == $result['page_title'])
						|| ($partialInfo['decoded'] && $partialInfo['decoded']['match'] == $result['page_title']))
					{
						$type = $partialInfo['plain'] ? 'plain' : 'decoded';
						$storedUrl = $partialInfo[$type]['url'];
						unset($partials[$pIndex]);
					}
				}

				$details = 'Not a redirect';
				if ($result['page_is_redirect']) {
					$redir = self::getIntlRedirect($result['lang'], $result['page_id']);
					if ($redir) $details = $redir;
				}

				$displayUrl = $storedUrl ?: $url;

				$lines[] = array($displayUrl, $result['page_id'], $result['page_is_redirect'], $details);
			}

			foreach ($partials as $partialInfo) {
				if ($partialInfo) {
					$type = $partialInfo['plain'] ? 'plain' : 'decoded';
					$lines[] = array($partialInfo[$type]['url'], '', 0, 'Not found');
				}
			}

			self::httpDownloadHeaders();
			foreach ($lines as $line) {
				print join("\t", $line) . "\n";
			}

			return;
		}

		$wgOut->setHTMLTitle('Admin - Lookup Redirects - wikiHow');

		$tmpl = self::getGuts('AdminRedirects');

		$wgOut->addHTML($tmpl);
	}

	function getGuts($action) {
		return "
		<script src='/extensions/wikihow/common/download.jQuery.js'></script>
		<form method='post' action='/Special:$action'>
		<h4>Enter a list of URLs / redirects such as <code>http://www.wikihow.com/Lose-Weight-Quickly</code> to look up.  One per line.</h4>
		<br/>
		<textarea id='pages-list' type='text' rows='10' cols='70'></textarea>
		<button id='pages-go' disabled='disabled'>process</button><br/>
		<br/>
		<div id='pages-result'>
		</div>
		</form>

		<script>
		(function($) {
			$(document).ready(function() {
				$('#pages-go')
					.prop('disabled', false)
					.click(function () {
						$('#pages-result').html('sending list ...');
						var url = '/Special:$action';
						var form = 'pages-list=' + encodeURIComponent($('#pages-list').val());
						$.download(url, form);
						return false;
					});
				$('#pages-list')
					.focus();
			});
		})(jQuery);
		</script>";
	}
}
