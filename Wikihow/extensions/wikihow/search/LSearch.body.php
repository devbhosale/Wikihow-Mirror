<?php

/**
 * Our Search special page. Uses Yahoo Boss and Elasticsearch to retrieve results.
 */
class LSearch extends SpecialPage {

	const RESULTS_PER_PAGE = 30;
	const RESULTS_PER_PAGE_DESKTOP = 10;
	const RESULTS_PER_PAGE_MOBILE = 20;

	const SEARCH_OTHER = 0;
	const SEARCH_LOGGED_IN = 1;
	const SEARCH_MOBILE = 2;
	const SEARCH_APP = 3;
	const SEARCH_RSS = 4;
	const SEARCH_RAW = 5;
	const SEARCH_404 = 6;
	const SEARCH_CATSEARCH = 7;
	const SEARCH_LOGGED_OUT = 8;
	const SEARCH_INTERNAL = 9;

	const SEARCH_WEB = 10 ;

	const NO_IMG_BLUE = '/extensions/wikihow/search/no_img_blue.png';
	const NO_IMG_BLUE_MOBILE = '/extensions/wikihow/search/no_img_blue_mobile.png';
	const NO_IMG_GREEN = '/extensions/wikihow/search/no_img_green.png';
	const NO_IMG_GREEN_MOBILE = '/extensions/wikihow/search/no_img_green_mobile.png';

	const MAXAGE_SECS = 86400; // 24 hours

	var $mResults = array();
	var $mSpelling = array();
	var $mLast = 0;
	var $mQ = '';
	var $mStart = 0;
	var $mLimit = 0;
	var $searchUrl = '/wikiHowTo';

	const ONE_WEEK_IN_SECONDS = 60 * 60 * 24 * 7;
	const FIVE_MINUTES_IN_SECONDS = 300;

	public function __construct() {
		global $wgHooks;
		parent::__construct('LSearch');

		$this->setListed(false);
		$this->mNoImgBlueMobile = wfGetPad(self::NO_IMG_BLUE_MOBILE);
		$this->mNoImgGreenMobile = wfGetPad(self::NO_IMG_GREEN_MOBILE);

		$wgHooks['ShowBreadCrumbs'][] = array($this, 'removeBreadCrumbsCallback');
		$wgHooks['AfterFinalPageOutput'][] = array($this, 'onAfterFinalPageOutput');
	}

	public static function allowMaxageHeadersCallback() {
		return false;
	}

	public function isMobileCapable() {
		return true;
	}

	/**
	 * A Mediawiki callback set in contructor of this class to stop the display
	 * of breadcrumbs at the top of the page.
	 */
	public function removeBreadCrumbsCallback(&$showBreadCrumb) {
		$showBreadCrumb = false;
		return true;
	}
	/**
	 * This function is called by DupTitleChecker for deduping titles.
	 * It returns Bing results for $q
	 * Check with Jordan before using this function for any other purpose.
	 * This is a paid service.
	 */
	public function webSearchResults( $q, $first = 0, $limit = 50, $searchType = self::SEARCH_WEB ) {
		$this->externalSearchResultsBing( $q, $first, $limit, $searchType );
		return $this->mResults['results'] ;
	}

	/*
	* Set cache-control headers right before page diplay
	*/
	public function onAfterFinalPageOutput($out) {
		$user = $out->getUser();
		if ( $user && $user->isAnon() && $out->getTitle() ) {
			$this->setMaxAgeHeaders();
		}
		return true;
	}

	/**
	 * A call used to parse titles from external search results
	 */
	public function externalSearchResultTitles($q, $first = 0, $limit = 30, $minrank = 0, $searchType = self::SEARCH_OTHER) {
		$this->externalSearchResults($q, $first, $limit, $searchType);
		$results = [];
		$searchResults = $this->mResults['results'];
		if (!is_array($searchResults)) return $results;
		foreach ($searchResults as $r) {
			if (!is_array($r)) {
				// This can be a string sometimes, as evidenced by this error in our
				// web logs:
				// NOTICE: PHP message: PHP Warning:  array_change_key_case() expects parameter 1 to be array, string given in /opt/wikihow/prod/extensions/wikihow/search/LSearch.body.php on line 89
				continue;
			}
			$r = array_change_key_case($r);
			$url = $this->localizeUrl($r['url']);
			$t = Title::newFromText(urldecode($url));
			if ($t && $t->exists()) $results[] = $t;
		}
		return $results;
	}

	/**
	 * Query the Bing Search API, which is a (paid-for) API.  Use sparingly and check in with PMs before making
	 * a bunch of calls for any new feature work
	 */
	private function externalSearchResults($q, $start, $limit = 30, $searchType = self::SEARCH_OTHER) {
		// Internal search is used for requests coming from services like FB messenger bot or Alexa.
		// These services often are intermittently blocked by yahoo search (which is free through our DDC contract).
		// Instead we send them to Bing, which we have to pay per query.
		if ($searchType == self::SEARCH_INTERNAL) {
			return $this->externalSearchResultsBing($q, $start, $limit, $searchType);
		} else {
			return $this->externalSearchResultsYahoo($q, $start, $limit, $searchType);
		}
	}

	private function isBadQuery($q): bool {
		global $wgBogusQueries, $wgCensoredWords;

		if (empty($q)) {
			return true;
		}

		if (in_array(strtolower($q), $wgBogusQueries) ) {
			return true;
		}

		foreach ($wgCensoredWords as $censoredWord) {
			if (stripos($q, $censoredWord) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Google WebSearch XML protocol
	 *
	 * Server IPs need to be manually authorized.
	 *
	 * Example request:
	 *  GET http://www.google.com/search
	 *  ?client=wikihow-search
	 *  &output=xml_no_dtd
	 *  &num=10
	 *  &start=0
	 *  &ie=utf8
	 *  &hl=en
	 *  &q=dance site:www.wikihow.com
	 *  &ip=123.45.6.789
	 *  &useragent=Mozilla/5.0 ...
	 *
	 * @link https://developers.google.com/custom-search/docs/xml_results
	 */
	private function externalSearchResultsGoogle($q, $start, $limit = 30, $gm_type = self::SEARCH_OTHER) {
		global $wgMemc;

		$q = trim($q);

		if ($this->isBadQuery($q)) {
			return null;
		}

		$key = wfMemcKey('GoogleXMLAPIResultsV2', str_replace(' ', '-', $q), $start, $limit);
		$data = $wgMemc->get($key);

		if (!is_array($data)) {

			// Query Google

			$params = [
				'client' => 'wikihow-search',
				'output' => 'xml_no_dtd',
				'num' => $limit,
				'start' => $start,
				'ie' => 'utf8',
				'hl' => $this->getLanguage()->getCode(),
				'q' => "$q site:" . Misc::getCanonicalDomain(),
				'ip' => $_SERVER['SERVER_ADDR'],
				'useragent' => $_SERVER['HTTP_USER_AGENT'],
			];
			$url = 'http://www.google.com/search?' . http_build_query($params);

			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

			// Parse response contents or return on failure

			$respBody = curl_exec($ch);
			$respCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			if ($respCode != 200 || curl_errno($ch)) {
				curl_close($ch);
				return null;
			}

			curl_close($ch);

			try {
				$xmlResp = @ new SimpleXMLElement($respBody);
			} catch (Exception $e) {
				return null;
			}

			// Collect data

			$data = [];

			if ($xmlResp->Spelling->CORRECTED_QUERY->Q instanceof SimpleXMLElement) {
				$chunks = explode(' site:', $xmlResp->Spelling->CORRECTED_QUERY->Q);
				$data['spelling'] = [ ['Value' => $chunks[0]] ];
			}

			$data['results'] = [];
			if ($xmlResp->RES->R) foreach ($xmlResp->RES->R as $result) {
				$data['results'][] = [
					'title' => (string) $result->T,
					'description' => (string) $result->S,
					'url' => (string) $result->U,
				];
			}

			$data['totalresults'] = (int) $xmlResp->RES->M;

			// Update cache. If no results, cache only for 5 minutes to handle hiccups in search service
			$count = count($data['results']);
			$expiry = ($count > 0) ? self::ONE_WEEK_IN_SECONDS : self::FIVE_MINUTES_IN_SECONDS;
			$wgMemc->set($key, $data, $expiry);
		}

		// Use data

		if ($gm_type == self::SEARCH_LOGGED_IN || $gm_type == self::SEARCH_LOGGED_OUT) {
			$this->mSpelling = $data['spelling'] ?? [];
		}
		$this->mResults['results'] = $data['results'];
		$this->mLast = $this->mStart + count($data['results']);
		$this->mResults['totalresults'] = $data['totalresults'];

		return $data['results'];

	}

	/**
	 * Query Yahoo proxy search. This is a search proxy to the search service formerly known as Yahoo BOSS provided
	 * by our search ad provider DDC (http://ddc.com). We get this service for free in exchange for hosting ads
	 * on our search results
	 */
	private function externalSearchResultsYahoo($q, $start, $limit = 30, $gm_type = self::SEARCH_OTHER) {
		global $wgMemc, $IP;

		$key = wfMemcKey("YPAResults4", str_replace(" ", "-", $q), $start, $limit);

		wfRunHooks( 'LSearchYahooAfterGetCacheKey', array( &$key ) );

		$q = trim($q);
		if ($this->isBadQuery($q)) {
			return null;
		}

		$set_cache = false;
		$contents = $wgMemc->get($key);
		if (!is_array($contents)) {
			// Reference url for building
			// http://yssads.ddc.com/x1.php?ua=Mozilla/5.0%20(Windows%20NT%206.1;%20WOW64)%20AppleWebKit/537.36%20(KHTML,%20like%20Gecko)%20Chrome/35.0.1916.153%20Safari/537.36&ip=69.231.120.208&surl=http%3A%2F%2Fddctestalgo.com&kw=change%20a%20tire&c=16588&n=5&algo=10&format=json
			$url = "http://yssads.ddc.com/x1.php";
			$siteKeyword = wfCanonicalDomain();
			$surl = $this->getSurl();

			wfRunHooks( 'LSearchBeforeYahooSearch', array( &$siteKeyword, &$surl ) );

			$args = [
				'ua' => $_SERVER['HTTP_USER_AGENT'],
				'ip' => $this->getRequest()->getIP(),
				'surl' => $surl,
				'c' => '22937',
				'kw' => "$q site:$siteKeyword",
				'format' => 'json',
				'sponstart' => $limit,
				'algostart' => $start,
				'algo' => $limit
			];

			// Yahoo boss required OAuth 1.0 authentication
			require_once("$IP/extensions/wikihow/common/oauth/OAuth.php");

			$url = sprintf("%s?%s", $url, OAuthUtil::build_http_query($args));

			//echo($url);exit;
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$rsp = curl_exec($ch);

			$contents = null;
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($http_code != 200 || curl_errno($ch)) {
//				echo $rsp;exit;
				curl_close($ch);
				return null;
			} else {
				//echo $rsp;exit;
				$contents = json_decode($rsp, true);
				curl_close($ch);
			}

			$set_cache = true;
		}

		if ($gm_type == self::SEARCH_LOGGED_IN || $gm_type == self::SEARCH_LOGGED_OUT) {
			$this->mSpelling = $contents['bossresponse']['spelling'];
		}

		$this->mResults['results'] = $contents['web']['web'];
		$num_results = !empty($this->mResults['results']) ?
			count($this->mResults['results']) : 0;

		if ($set_cache) {
			// Set earlier cache expiration for empty results to handle hiccups in search service better
			$expiry = $num_results > 0 ? self::ONE_WEEK_IN_SECONDS : self::FIVE_MINUTES_IN_SECONDS;
			$wgMemc->set($key, $contents, $expiry);
		}

		$this->mLast = $this->mStart + $num_results;
		// The DDC web search proxy doesn't have a 'total results' argument so we simulate it by checking to see
		// if there is a nextargs value. A nextargs values signifies an additional page of results exist.  If nextargs
		// does exist make the total results one more than the last result count to ensure proper pagination
		$this->mResults['totalresults'] = empty($contents['nextargs']) ? $num_results : $this->mLast + 1;

		return $contents;

	}

	/**
	 * Query the Bing Search API, which is a (paid-for) API.  Use sparingly and check in with PMs before making
	 * a bunch of calls for any new feature work
	 */
	private function externalSearchResultsBing($q, $start, $limit = 30, $searchType = self::SEARCH_OTHER) {
		global $wgMemc;

		$key = wfMemcKey("BingSearchAPI-V7", str_replace(" ", "-", $q), $start, $limit);

		$q = trim($q);
		if ($this->isBadQuery($q)) {
			return null;
		}

		$set_cache = false;
		$contents = $wgMemc->get($key);
		if (!is_array($contents)) {
			// Request spelling results for logged in search
			if ($searchType == self::SEARCH_LOGGED_IN || $searchType == self::SEARCH_LOGGED_OUT) {
				$responseFilter = "Webpages,SpellSuggestions";
			} else {
				$responseFilter = "Webpages";
			}

			if ($searchType == self::SEARCH_WEB ) {
				$queryUrl =  "https://api.cognitive.microsoft.com/bing/v7.0/search?responseFilter=$responseFilter"
				. '&count=' . $limit . '&offset=' . $start . '&q=' . urlencode( $q );
			} else {
				$queryUrl =  "https://api.cognitive.microsoft.com/bing/v7.0/search?responseFilter=$responseFilter"
					. '&count=' . $limit . '&offset=' . $start . '&q=' . urlencode( "$q site:wikihow.com" );
			}

			// Enable text decoration if a search originates from a wikihow.com page
			if ($searchType == self::SEARCH_LOGGED_IN || $searchType == self::SEARCH_LOGGED_OUT) {
				$queryUrl .= "&textDecorations=true";
			}

			$ch = curl_init($queryUrl);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ["Ocp-Apim-Subscription-Key: "
				. WH_AZURE_COGNITIVE_SERVICES_BING_API_V7_SUBSCRIPTION_KEY]);

			$rsp = curl_exec($ch);
			//echo $rsp;exit;

			$contents = null;
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($http_code != 200 || curl_errno($ch)) {
				curl_close($ch);
				return null;
			} else {
				$contents = json_decode($rsp, true);
				$contents = empty($contents['webPages']['value']) ? [] : $contents['webPages'];
				curl_close($ch);
			}

			$set_cache = true;
		}

		if ($searchType == self::SEARCH_LOGGED_IN || $searchType == self::SEARCH_LOGGED_OUT) {
			$this->mSpelling = $contents['SpellingSuggestions'];
		}

		$this->mResults['results'] = isset($contents['value']) ?
			$this->normalizeBingResults($contents['value']) : null;

		$this->mResults['totalresults'] = isset($contents['totalEstimatedMatches']) ?
			$contents['totalEstimatedMatches'] : 0;

		$num_results = !empty($contents['value']) ? count($contents['value']) : 0;

		if ($set_cache) {
			// Set earlier cache expiration for empty results to handle hiccups in search service better
			$expiry = $num_results > 0 ? self::ONE_WEEK_IN_SECONDS : self::FIVE_MINUTES_IN_SECONDS;
			$wgMemc->set($key, $contents, $expiry);
		}

		$this->mLast = $this->mStart + $num_results;

		return $contents;
	}

	/**
	 * Add fields expected by LSearch for displaying output
	 *
	 * @param $results
	 * @return mixed
	 */
	private function normalizeBingResults($results) {
		foreach ($results as $i => $r) {
			// Bing puts title text results in the name field.  Add a title key in the results to normalize with
			// DDC/Yahoo.
			$r['title'] = $r['name'];
			$results[$i] = $r;
		}

		return $results;
	}

	private function cleanTitle(&$t) {
		// remove detailed title from search results

		$domain = wfCanonicalDomain();
		$tld = array_pop(explode('.', $domain)); // 'com', 'es', etc

		$t = str_replace("- <b>wikihow</b>.<b>$tld</b>", '', $t);
		$t = str_replace("- <b>$domain</b>", '', $t);

		$t = str_replace('<b>wikiHow</b>', "wikiHow", $t);
		$t = str_replace("–", '-', $t);
		$t = str_replace(" - wikiHow", "", $t);
		$t = preg_replace("@ \(with[^\.]+[\.]*@", "", $t);
		$t = preg_replace("/\:(.*?)steps$/i", "", $t);
		$t = str_replace(' - how to articles from wikiHow', '', $t);
		//$t = str_replace(' - How to do anything', '', $t);

		// If Bing highlighting enabled, switch out highlight characters for html bolding
		// See https://onedrive.live.com/view.aspx?resid=9C9479871FBFA822!112&app=Word&authkey=!ANNnJQREB0kDC04
		// for more info under the EnableHighlighting Option
		$t = preg_replace(["@\x{E000}@u","@\x{E001}@u"], ["<b>","</b>"], $t);
	}

	private function localizeUrl(&$url) {
		$domain = str_replace('.', '\.', wfCanonicalDomain());
		$localizedUrl = preg_replace("@^https?://$domain/@", '', $url);
		if ($localizedUrl == $url) {
			$domain = str_replace('.', '\.', wfCanonicalDomain('', true));
			$localizedUrl = preg_replace("@^https?://$domain/@", '', $url);
		}

		// a chance for a hook (like alternate domains) to localize the url
		// specific to their domain
		wfRunHooks( 'LSearchAfterLocalizeUrl', array( &$localizedUrl, $url ) );

		return $localizedUrl;
	}

	/**
	 * Trim all the "- wikiHow" etc off the back of the titles from external
	 * engine. Make sure the titles can be turned into a MediaWiki Title object.
	 */
	private function makeTitlesUniform($inResults) {
		$results = array();

		// if the $inResults is not an array of results but just one result, wrap it in an array
		if ( array_key_exists( 'title', $inResults ) ) {
			$inResults = array( $inResults );
		}

		foreach ($inResults as $r) {
			$r = array_change_key_case($r);
			$t = htmlspecialchars_decode($r['title']);
			$this->cleanTitle($t);

			$url = $this->localizeUrl($r['url']);
			$tobj = Title::newFromText(urldecode($url), NS_MAIN);
			if (!$tobj || !$tobj->exists()) continue;
			$key = $tobj->getDBkey();

			$results[] = array(
				'title_match' => $t,
				'url' => $url,
				'key' => $key,
				'id' => $tobj->getArticleId(),
				'namespace' => $tobj->getNamespace(),
			);
		}
		return $results;
	}

	/**
	 * Add our own meta data to the search results to make them more
	 * interesting and informative to look at.
	 */
	private function supplementResults($titles) {
		global $wgMemc;

		if (count($titles) == 0) {
			return [];
		}

		$allArticleIds = array_reduce($titles, function($carry, $item) {
			return $carry . $item['id'];
		});

		$enc_q = urlencode($this->mQ);
		$cachekey = wfMemcKey('search_suppl', md5($allArticleIds));
		$rows = $wgMemc->get($cachekey);

		if (!is_array($rows)) {
			$ids = [];
			foreach ($titles as $title) {
				$ids[] = $title['id'];
			}

			$dbr = wfGetDB(DB_SLAVE);
			$res = $dbr->select('search_results', '*', array('sr_id' => $ids), __METHOD__);
			$rows = [];
			foreach ($res as $row) {
				$rows[ $row->sr_title ] = (array)$row;
			}

			$wgMemc->set($cachekey, $rows);
		}

		foreach ($titles as $title) {
			$key = $title['key'];
			$hasSupplement = isset($rows[$key]);
			if ($hasSupplement) {
				foreach ($rows[$key] as $k => &$v) {
					if (preg_match('@^sr_@', $k)) {
						$k = preg_replace('@^sr_@', '', $k);
						if ($v && preg_match('@^img_thumb@', $k)) {
							$v = wfGetPad($v);
						}
						$title[$k] = $v;
					}
				}
			}
			$title['has_supplement'] = intval($hasSupplement);
			$isCategory = $title['namespace'] == NS_CATEGORY;
			$title['is_category'] = intval($isCategory);
			$results[] = $title;
		}

		return $results;
	}

	private function setMaxAgeHeaders($maxAgeSecs = self::MAXAGE_SECS) {
		$out = $this->getOutput();
		$req = $this->getRequest();
		$out->setSquidMaxage( $maxAgeSecs );
		$req->response()->header( 'Cache-Control: s-maxage=' . $maxAgeSecs . ', must-revalidate, max-age=' . $maxAgeSecs );
		$future = time() + $maxAgeSecs;
		$req->response()->header( 'Expires: ' . gmdate('D, d M Y H:i:s T', $future) );
		$out->enableClientCache(true);
		$out->sendCacheControl();
	}

	/**
	 * Use the same search results that would be visible on Special:Search.
	 *
	 * We stopped using this method in June 2017, but now it can still be accessed by adding
	 * `internal=1` to the URL.
	 */
	private function specialSearchFallback() {
		global $IP;
		require_once("$IP/includes/specials/SpecialSearch.php");

		$user = $this->getUser();
		$request = $this->getRequest();

		$this->setNoImgBlueMobile("");
		$this->setNoImgGreenMobile("");

		$ss = new SpecialSearch( $request, $user );

		$term = str_replace( "\n", " ", $request->getText( 'search', '' ) );

		$ss->load();
		$search = $ss->getSearchEngine();
		$search->setLimitOffset( 10, $this->mStart );
		$search->setNamespaces( $ss->getNamespaces() );
		$search->showRedirects = false;
		$search->setFeatureData( 'list-redirects', false );
		$term = $search->transformSearchTerm( $term );

		wfRunHooks( 'SpecialSearchSetupEngine', array( $ss, 'default', $search ) );

		$rewritten = $search->replacePrefixes( $term );
		$titleMatches = $search->searchTitle( $rewritten );
		if ( !( $titleMatches instanceof SearchResultTooMany ) ) {
			$textMatches = $search->searchText( $rewritten );
		}

		$totalMatches = 0;
		if ( $titleMatches ) {
			$totalMatches += $titleMatches->getTotalHits();
		}
		if ( $textMatches ) {
			$totalMatches += $textMatches->getTotalHits();
		}
		$this->mResults = array('totalresults' => $totalMatches);
		$results = array();

		if ( $titleMatches ) {
			$matches = $titleMatches;
			$m = $matches->next();
			while ( $m ) {
				$results[] = $m;
				$m = $matches->next();
			}
		}
		if ( $textMatches ) {
			$matches = $textMatches;
			$m = $matches->next();
			while ( $m ) {
				$results[] = $m;
				$m = $matches->next();
			}
		}

		$formattedResults = array();
		foreach ( $results as $m ) {
			$t = $m->getTitle();
			if ( $t ) {
				$r = array();
				$r['title_match'] = wfMessage('howto',$t->getText())->text();
				$r['url'] = $t->getDBKey();
				$r['id'] = $t->getArticleID();
				$r['key'] = $t->getDBKey();
				$r['namespace'] = $t->getNamespace();
				$formattedResults[] = $r;
			}
		}

		$results = $this->supplementResults($formattedResults);
		$this->mResults['count'] = count( $results );
		$this->mLast = $this->mStart + $this->mResults['count'];

		$this->getOutput()->setHTMLTitle( wfMessage( 'lsearch_title_q', $term )->text() );

		$resultsPerPage = 10;
		$enc_q = htmlspecialchars($this->mQ);
		$suggestionLink = $this->getSpellingSuggestion($this->searchUrl);

		$searchId = $this->sherlockSearch();	// Initialize/check Sherlock cookie
		$this->displaySearchResults( $results, $resultsPerPage, $enc_q, $suggestionLink, $searchId );
	}

	/**
	 * /wikiHowTo?... and /Special:LSearch page entry point
	 */
	public function execute($par) {
		// Added this hack to test whether we can stop some usertype:logged(in|out)
		// queries can be removed from index. Remove this code eventually, say 6 mos.
		// from now. Added by Reuben originally on July 30, 2012.
		$queryString = @$_SERVER['REQUEST_URI'];
		if (strpos($queryString, 'usertype') !== false) {
			header('HTTP/1.0 404 Not Found');
			print "Page not found";
			exit;
		}

		$req = $this->getRequest();

		$this->mStart = $req->getVal('start', 0);
		$this->mQ = $req->getVal('search');
		$this->mLimit = $req->getVal('limit', 20);

		// special case search term filtering
		if (strtolower($this->mQ) == 'sat') { // searching for SAT, not sitting
			$this->mQ = "\"SAT\"";
		}

		$this->getOutput()->setRobotPolicy( 'noindex,nofollow' );

		if ($req->getVal('rss')) {
			$this->rssSearch();
		} elseif ($req->getVal('raw')) {
			$this->rawSearch();
		} elseif ($req->getVal('mobile')) {
			$this->jsonSearch();
		} elseif ($req->getVal('internal')) {
			$this->specialSearchFallback();
		} else {
			$this->regularSearch();
		}
	}

	private function regularSearch() {
		if (class_exists('AndroidHelper') && AndroidHelper::isAndroidRequest()) {
			$resultsPerPage = self::RESULTS_PER_PAGE;
		} elseif (Misc::isMobileMode()) {
			$resultsPerPage = self::RESULTS_PER_PAGE_MOBILE;
		} else {
			$resultsPerPage = self::RESULTS_PER_PAGE_DESKTOP;
		}

		$contents = $this->externalSearchResults($this->mQ, $this->mStart, $resultsPerPage, self::SEARCH_LOGGED_IN);
		if ($contents === null) {
			$reqUrl = $this->getRequest()->getRequestURL();
			$out = $this->getOutput();
			$out->addHTML(wfMessage('lsearch_query_error', $reqUrl)->plain());
			$out->setStatusCode( 404 );
			return;
		}

		$enc_q = htmlspecialchars($this->mQ);
		$this->getOutput()->setHTMLTitle(wfMessage('lsearch_title_q', $enc_q));

		$suggestionLink = $this->getSpellingSuggestion($this->searchUrl);
		$results = $this->mResults['results'] ? $this->mResults['results'] : [];
		$results = $this->makeTitlesUniform($results);
		$results = $this->supplementResults($results);

		wfRunHooks( 'LSearchRegularSearch', array( &$results ) );

		$searchId = $this->sherlockSearch();	// initialize/check Sherlock cookie
		$this->displaySearchResults( $results, $resultsPerPage, $enc_q, $suggestionLink, $searchId );
	}

	// will display the search results that have been formatted by supplementResults
	private function displaySearchResults( $results, $resultsPerPage, $enc_q, $suggestionLink, $searchId ) {
		global $wgServer;

		$out = $this->getOutput();
		$sk = $this->getSkin();

		$mw = Title::makeTitle(NS_SPECIAL, "Search");
		$specialPageURL = $mw->getFullURL();

		$total = $this->mResults['totalresults'];
		$start = $this->mStart;
		$last = $this->mLast;

		$q = $this->mQ;

		$me = Title::makeTitle(NS_MAIN, 'wikiHowTo');

		// Google was complaining about "soft 404s" in GWMT, so I'm making this a hard 404 instead.
		// -Reuben, June 14, 2016
		if (!$results) {
			$out->setStatusCode(404);
		}

		$androidParam = class_exists('AndroidHelper') && AndroidHelper::isAndroidRequest() ?
			"&" . AndroidHelper::QUERY_STRING_PARAM . "=1" : "";
		//buttons
		// - next
		$disabled = !($total > $start + $resultsPerPage && $last == $start + $resultsPerPage);
		// equivalent to: $disabled = $total <= $start + $resultsPerPage || $last != $start + $resultsPerPage;
		$next_url = '/' . $me . '?search=' . urlencode($q) . '&start=' . ($start + $resultsPerPage) . $androidParam;
		$nextButtonAttr = array(
			'href' => $next_url,
			'class' => 'button buttonright primary ' . ($disabled ? 'disabled' : ''),
		);
		if ( $disabled ) {
			$nextButtonAttr['onclick'] = 'return false;';
		}
		$next_button = Html::rawElement( 'a', $nextButtonAttr, wfMessage( "lsearch_next" )->text() );

		// - previous
		$disabled = !($start - $resultsPerPage >= 0);
		// equivalent to: $disabled = $start < $resultsPerPage;

		$prev_url = '/' . $me . '?search=' . urlencode($q) . ($start - $resultsPerPage !== 0 ? '&start=' . ($start - $resultsPerPage) : '') . $androidParam;
		$prevButtonAttr = array(
			'href' => $prev_url,
			'class' => 'button buttonleft primary ' . ($disabled ? 'disabled' : ''),
		);
		if ( $disabled ) {
			$prevButtonAttr['onclick'] = 'return false;';
		}
		$prev_button = Html::rawElement( 'a', $prevButtonAttr, wfMessage( "lsearch_previous" )->text() );
		$page = (int) ($start / $resultsPerPage) + 1;

		$vars = array(
			'q' => $q,
			'enc_q' => $enc_q,
			'ads' => wikihowAds::getSearchAds('yahoo', $q, $page, count($results)),
			'sk' => $sk,
			'me' => $me,
			'max_results' => $resultsPerPage,
			'start' => $start,
			'first' => $start + 1,
			'last' => $last,
			'suggestionLink' => $suggestionLink,
			'results' => $results,
			'specialPageURL' => $specialPageURL,
			'total' => $total,
			'BASE_URL' => $wgServer,
			'next_button' => $next_button,
			'prev_button' => $prev_button,
		);

		if (Misc::isMobileMode()) {
			$tmpl = 'search-results-mobile';
			$out->addModuleStyles('ext.wikihow.lsearch.mobile.styles');
			$vars['no_img_blue'] = $this->getNoImgBlueMobile();
			$vars['no_img_green'] = $this->getNoImgGreenMobile();
		} else {
			$tmpl = 'search-results-desktop';
			$out->addModuleStyles('ext.wikihow.lsearch.desktop.styles');
			$vars['no_img_blue'] = wfGetPad(self::NO_IMG_BLUE);
			$vars['no_img_green'] = wfGetPad(self::NO_IMG_GREEN);
		}

		// Use templates to generate the HTML for the search results & the Sherlock script
		EasyTemplate::set_path(__DIR__ . '/');
		$html = EasyTemplate::html($tmpl, $vars);
		//Check that the Sherlock class is loaded (IE: Not on international)
		if (class_exists("Sherlock")) {
			$html .= EasyTemplate::html("sherlock-script", array("shs_key" => $searchId));
		}

		$out->addHTML($html);
	}

	private function setNoImgBlueMobile( $val ) {
		$this->mNoImgBlueMobile = $val;
	}

	private function setNoImgGreenMobile( $val ) {
		$this->mNoImgGreenMobile = $val;
	}

	private function getNoImgGreenMobile() {
		return $this->mNoImgGreenMobile;
	}

	private function getNoImgBlueMobile() {
		return $this->mNoImgBlueMobile;
	}

	private function getSpellingSuggestion($url) {
		$spellingResults = $this->mSpelling;
		$suggestionLink = null;
		if (sizeof($spellingResults) > 0) {
			$suggestion = $spellingResults[0]['Value'];
			// Lighthouse #1527 - We don't want spelling corrections for wikihow
			if (stripos($suggestion, "wiki how") === false) {
				$suggestionUrl = "$url?search=" . urlencode($suggestion);
				$suggestionLink = "<a href='$suggestionUrl'>$suggestion</a>";
			}

		}
		return $suggestionLink;
	}

	private function rssSearch() {
		$results = $this->externalSearchResultTitles($this->getRequest()->getVal('search'), $this->mStart, self::RESULTS_PER_PAGE, 0, self::SEARCH_RSS);
		$this->getOutput()->setArticleBodyOnly(true);
		$pad = "           ";
		header("Content-type: text/xml;");
		print '<GSP VER="3.2">
<TM>0.083190</TM>
<Q>' . htmlspecialchars($this->mQ) . '</Q>
<PARAM name="filter" value="0" original_value="0"/>
<PARAM name="num" value="16" original_value="30"/>
<PARAM name="access" value="p" original_value="p"/>
<PARAM name="entqr" value="0" original_value="0"/>
<PARAM name="start" value="0" original_value="0"/>
<PARAM name="output" value="xml" original_value="xml"/>
<PARAM name="sort" value="date:D:L:d1" original_value="date%3AD%3AL%3Ad1"/>
<PARAM name="site" value="main_en" original_value="main_en"/>
<PARAM name="ie" value="UTF-8" original_value="UTF-8"/>
<PARAM name="client" value="internal_frontend" original_value="internal_frontend"/>
<PARAM name="q" value="' . htmlspecialchars($this->mQ) . '" original_value="' . htmlspecialchars($this->mQ) . '"/>
<PARAM name="ip" value="192.168.100.100" original_value="192.168.100.100"/>
<RES SN="1" EN="' . sizeof($results) . '">
<M>' . sizeof($results) . '</M>
<XT/>';
		$count = 1;
		foreach ($results as $r) {
			print "<R N=\"{$count}\">
<U>{$r->getFullURL()}</U>
<UE>{$r->getFullURL()}</UE>
<T>How to " . htmlspecialchars($r->getFullText()) . "{$pad}</T>
<RK>10</RK>
<HAS></HAS>
<LANG>en</LANG>
</R>";
			$count++;
		}
		print "</RES>
</GSP>";
	}

	private function rawSearch() {
		$contents = $this->externalSearchResultTitles($this->mQ, $this->mStart, self::RESULTS_PER_PAGE, 0, self::SEARCH_RAW);
		header("Content-type: text/plain");
		$this->getOutput()->setArticleBodyOnly(true);
		foreach ($contents as $t) {
			print "{$t->getCanonicalURL()}\n";
		}
	}

	/*
	 * Return a json array of articles that includes the title, full url and abbreviated intro text.
	 *
	 * NOTE: This method is really slow and shouldn't be used. It creates the 'intro' array element
	 *   by getting the latest revision of the wikitext, pulling out the intro and flattening it.
	 *   We should remove it. - Reuben, 2016/1/8
	 */
	private function jsonSearch() {
		global $wgMemc;

		// Don't return more than 50 search results at a time to prevent abuse
		$limit = min($this->mLimit, 50);

		$key = wfMemcKey("MobileSearch", str_replace(" ", "-", $this->mQ), $this->mStart, $limit);
		if ($val = $wgMemc->get($key)) {
			return $val;
		}

		$contents = $this->externalSearchResultTitles($this->mQ, $this->mStart, $limit, 0, self::SEARCH_MOBILE);
		$results = array();
		foreach ($contents as $t) {
			// Only return articles
			if ($t->getNamespace() != NS_MAIN) {
				continue;
			}

			$result = array();
			$result['title'] = $t->getText();
			$result['url'] = $t->getFullURL();
			$result['imgurl'] = ImageHelper::getGalleryImage($t, 103, 80);
			$result['intro'] = null;
			if ($r = Revision::newFromId($t->getLatestRevID())) {
				$intro = Wikitext::getIntro($r->getText());
				$intro = trim(Wikitext::flatten($intro));
				$result['intro'] = mb_substr($intro, 0, 180);
				// Put an ellipsis on the end
				$len = mb_strlen($result['intro']);
				$result['intro'] .= mb_substr($result['intro'], $len - 1, $len) == '.' ? '..' : '...';
			}
			if (!is_null($result['intro'])) {
				$results[] = array('article' => $result);
			}
		}

		$searchResults['results'] = $results;
		$json = json_encode($searchResults);
		$wgMemc->set($key, $json, 3600); // 1 hour

		header("Content-type: application/json");
		$this->getOutput()->setArticleBodyOnly(true);
		print $json;
	}

	/**
	 * Used to log the search in the site_search_log table, to store this data for
	 * later analysis.
	 */
	/*
		private function logSearch($q, $host_id, $cache, $error, $curl_err, $gm_tm_count, $gm_ts_count, $username, $userid, $rank, $num_results, $gm_type) {
			$dbw = wfGetDB(DB_MASTER);
			$q = $dbw->strencode($q);
			$username = $dbw->strencode($username);
			$vals = array(
					'ssl_query' 		=> strtolower($q),
					'ssl_host_id' 		=> $host_id,
					'ssl_cache' 		=> $cache,
					'ssl_error' 		=> $error,
					'ssl_curl_error'	=> $curl_err,
					'ssl_ts_count' 		=> $gm_ts_count,
					'ssl_tm_count' 		=> $gm_tm_count,
					'ssl_user'			=> $userid,
					'ssl_user_text' 	=> $username,
					'ssl_num_results'	=> $num_results,
					'ssl_rank'			=> $rank,
					'ssl_type'			=> $gm_type
				);
			// FYI: this table has moved to whdata
			$res = $dbw->insert('site_search_log', $vals, __METHOD__);
		}
	*/

	// Executes the logic for managing the Sherlock Cookie & loggin search to DB
	private function sherlockSearch() {
		if (class_exists("Sherlock")) {
			$context = $this->getContext();

			// check if the user is logged in
			$user = $context->getUser();
			if ($user->isAnon()) {
				$logged = false;
			} else {
				$logged = true;
			}

			// Check if their using the mobile site
			if (Misc::isMobileMode()) {
				$platform = "mobile";
			} else {
				$platform = "desktop";
			}

			// Get visitor ID
			$vId = WikihowUser::getVisitorId();

			// Check if there's already a search id cookie
			$request = $context->getRequest();
			$searchId = $request->getCookie("sherlock_id");

			// Determine whether or not this is a new search
			if ($request->getCookie("sherlock_q") != $this->mQ) {
				$searchId = Sherlock::logSherlockSearch($this->mQ, $vId, $this->mResults['totalresults'], $logged, $platform);

				// Then make a new cookie
				$response = $request->response();
				$response->setcookie("sherlock_id", $searchId);
				$response->setcookie("sherlock_q", $this->mQ);
			} else {
				// It's the same query, so we're saying it's not a new search & they must have just gone "back".
				// Don't make a new search entry.
			}

			return $searchId;
		}
	}

	private function getSurl(): string {
		$isM = Misc::isMobileMode();
		$lang = $this->getLanguage()->getCode();

		if ($lang == 'en')     $domain = $isM ? 'mobile.wikihow.com' : 'wikihow.com';
		elseif ($lang == 'ar') $domain = $isM ? 'arm.wikihow.com'    : 'ar.wikihow.com';
		elseif ($lang == 'cs') $domain = $isM ? 'mobile.wikihow.cz'  : 'wikihow.cz';
		elseif ($lang == 'de') $domain = $isM ? 'dem.wikihow.com'    : 'de.wikihow.com';
		elseif ($lang == 'es') $domain = $isM ? 'esm.wikihow.com'    : 'es.wikihow.com';
		elseif ($lang == 'fr') $domain = $isM ? 'frm.wikihow.com'    : 'fr.wikihow.com';
		elseif ($lang == 'hi') $domain = $isM ? 'him.wikihow.com'    : 'hi.wikihow.com';
		elseif ($lang == 'id') $domain = $isM ? 'idm.wikihow.com'    : 'id.wikihow.com';
		elseif ($lang == 'it') $domain = $isM ? 'mobile.wikihow.it'  : 'wikihow.it';
		elseif ($lang == 'ja') $domain = $isM ? 'mobile.wikihow.jp'  : 'wikihow.jp';
		elseif ($lang == 'ko') $domain = $isM ? 'kom.wikihow.com'    : 'ko.wikihow.com';
		elseif ($lang == 'nl') $domain = $isM ? 'nlm.wikihow.com'    : 'nl.wikihow.com';
		elseif ($lang == 'pt') $domain = $isM ? 'ptm.wikihow.com'    : 'pt.wikihow.com';
		elseif ($lang == 'ru') $domain = $isM ? 'rum.wikihow.com'    : 'ru.wikihow.com';
		elseif ($lang == 'th') $domain = $isM ? 'thm.wikihow.com'    : 'th.wikihow.com';
		elseif ($lang == 'vi') $domain = $isM ? 'mobile.wikihow.vn'  : 'wikihow.vn';
		elseif ($lang == 'zh') $domain = $isM ? 'zhm.wikihow.com'    : 'zh.wikihow.com';
		else                   $domain = $isM ? 'mobile.wikihow.com' : 'wikihow.com';

		return "http://$domain";
	}

	/*
	 * This hook removes the canonical url if it's Special:LSearch.  As per SEO discussions
	 * between Jordan and Reuben, a canonical link doesn't make sense for this particular page
	 */
	public static function onOutputPageAfterGetHeadLinksArray( &$headLinks, $out ) {
		$t = SpecialPage::getTitleFor('LSearch');
		$canonicalLink = Html::element( 'link', array(
			'rel' => 'canonical',
			'href' => wfExpandUrl($t->getLocalURL(), PROTO_CANONICAL)
		) );

		foreach($headLinks as $key => $val) {
			if ($val === $canonicalLink) {
				unset($headLinks[$key]);
			}
		}
		return true;
	}
}
