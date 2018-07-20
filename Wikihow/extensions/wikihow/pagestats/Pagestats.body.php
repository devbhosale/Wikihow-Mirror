<?php

class Pagestats extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('Pagestats');
	}

	public static function getTitusData($pageId) {
		global $wgLanguageCode;

		$dbr = wfGetDB(DB_SLAVE);
		$table = Misc::getLangDB('en') . '.titus_copy';
		$where = ['ti_page_id' => $pageId, 'ti_language_code' => $wgLanguageCode];
		$row = $dbr->selectRow($table, '*', $where);
		return $row ?? null;
	}

    public static function getRatingReasonData($pageId, $type, &$dbr) {
    	if (!isset($val)) $val = new stdClass();
        $val->total = $dbr->selectField('rating_reason', "count(*)", array("ratr_item" => $pageId, "ratr_type" => $type), __METHOD__);
        return $val;
    }

	public static function getRatingData($pageId, $tableName, $tablePrefix, &$dbr) {
		global $wgMemc;

		//$key = "ps-rating-" . $pageId;
		//$val = $wgMemc->get($key);

		//if(!$val) {
			$val = new stdClass();
			$val->total = 0;
			$yes = 0;

			$res = $dbr->select($tableName, "{$tablePrefix}_rating as rating", array("{$tablePrefix}_page" => $pageId, "{$tablePrefix}_isdeleted" => 0), __METHOD__);
			while($row = $dbr->fetchObject($res)) {
				$val->total++;
				if($row->rating == 1)
					$yes++;
			}

			if($val->total > 0)
				$val->percentage = round($yes*1000/$val->total)/10;
			else
				$val->percentage = 0;


			//$wgMemc->set($key, $val);

		//}

		return $val;
	}

	function getFellowsTime($fellowEditTimestamp) {
		global $wgLang;
		$d = false;
		if (!$fellowEditTimestamp) {
			return false;
		}

		$ts = wfTimestamp( TS_MW, strtotime($fellowEditTimestamp));
		$hourMinute = $wgLang->sprintfDate("H:i", $ts);
		if ($hourMinute == "00:00") {
			$d = $wgLang->sprintfDate("j F Y", $ts);
		} else {
			$d = $wgLang->timeanddate($ts);
		}
		$result = "<p>" . wfMessage('ps-fellow-time') . " $d&nbsp;&nbsp;</p>";
		return $result;
	}

	public static function getPagestatData($pageId) {
		$context = RequestContext::getMain();
		$t = Title::newFromID($pageId);
		$dbr = wfGetDB(DB_SLAVE);


		$html = "<h3 style='margin-bottom:5px'>Staff-only data</h3>";

		if (class_exists('SummaryEditTool') && SummaryEditTool::authorizedUser($context->getUser())) {
			$html =  SummaryEditTool::editCTAforArticlePage() . $html;
		}

		$error = null;
		$titusData = self::getTitusData($pageId);
		if (!$titusData) {
			$error = "No Titus data was found for article: $pageId";
			$html .= "<p>" . wfMessage('ps-error') . "</p>";
			$html .= "<hr style='margin:5px 0; '/>";
		} else {
			// pageview data
			$views30Day = $titusData->ti_30day_views_unique;
			$views30DayMobile = $titusData->ti_30day_views_unique_mobile;
			$html .= wfMessage( 'ps-pv-30day-unique', $views30Day )->text();
			$mobile30DayPercent = 0;
			if ( $views30Day > 0 ) {
				$mobile30DayPercent = round( 100 * $views30DayMobile / $views30Day );
			}

			$viewsDay = $titusData->ti_daily_views_unique;
			$viewsMobile = $titusData->ti_daily_views_unique_mobile;
			$html .= wfMessage( 'ps-pv-1day-unique', $viewsDay )->text();
			$mobilePercent = 0;
			if ( $viewsDay > 0 ) {
				$mobilePercent = round( 100 * $viewsMobile / $viewsDay );
			}

			$html .= "<p>{$titusData->ti_30day_views} " . wfMessage('ps-pv-30day') . "</p>";
			$html .= "<p>{$titusData->ti_daily_views} " . wfMessage('ps-pv-1day') . "</p>";

			$html .= "<hr style='margin:5px 0; '/>";
			$html .= wfMessage('ps-pv-30day-unique-mobile', $views30DayMobile, $mobile30DayPercent )->text();
			$html .= wfMessage('ps-pv-1day-unique-mobile', $viewsMobile, $mobilePercent )->text();

			// stu data
			$html .= "<hr style='margin:5px 0; '/>";
			$html .= "<p>" . wfMessage('ps-stu') . " {$titusData->ti_stu_10s_percentage_www}%&nbsp;&nbsp;{$titusData->ti_stu_3min_percentage_www}%&nbsp;&nbsp;{$titusData->ti_stu_10s_percentage_mobile}%</p>";
			$html .= "<p>" . wfMessage('ps-stu-views') . "{$titusData->ti_stu_views_www}&nbsp;&nbsp;{$titusData->ti_stu_views_mobile}</p>";
			if ($t) {
				$html .= "<p><a href='#' class='clearstu'>Clear Stu</a></p>";
			}

			// stu2 data
			$nb = '&nbsp;';
			if ($titusData->ti_stu2_search_mobile) {
				$stu2Mb10s = sprintf( '%.1f', 100 * ($titusData->ti_stu2_10s_mobile / $titusData->ti_stu2_search_mobile) ) . "%";
				$stu2Mb3m = sprintf( '%.1f', 100 * ($titusData->ti_stu2_3m_mobile / $titusData->ti_stu2_search_mobile) ) . "%";
				$stu2Mb10sAc = sprintf( '%.1f', 100 * ($titusData->ti_stu2_10s_active_mobile / $titusData->ti_stu2_search_mobile) ) . "%";
				$stu2Mb3mAc = sprintf( '%.1f', 100 * ($titusData->ti_stu2_3m_active_mobile / $titusData->ti_stu2_search_mobile) ) . "%";
				$stu2MbLine = "$stu2Mb10s$nb$stu2Mb3m$nb(active:$nb$stu2Mb10sAc$nb$stu2Mb3mAc)";

				$stu2MbStF = sprintf( '%.1f', 100 * ($titusData->ti_stu2_stepfirst_mobile / $titusData->ti_stu2_search_mobile) ) . "%";
				$stu2MbStL = sprintf( '%.1f', 100 * ($titusData->ti_stu2_steplast_mobile / $titusData->ti_stu2_search_mobile) ) . "%";
				$stu2MbSt = "first:$stu2MbStF$nb{$nb}last:$stu2MbStL";
			} else {
				$stu2MbLine = "<i>(no search views)</i>";
				$stu2MbSt = "";
			}
			if ($titusData->ti_stu2_search_desktop) {
				$stu2Dt10s = sprintf( '%.1f', 100 * ($titusData->ti_stu2_10s_desktop / $titusData->ti_stu2_search_desktop) ) . "%";
				$stu2Dt3m = sprintf( '%.1f', 100 * ($titusData->ti_stu2_3m_desktop / $titusData->ti_stu2_search_desktop) ) . "%";
				$stu2Dt10sAc = sprintf( '%.1f', 100 * ($titusData->ti_stu2_10s_active_desktop / $titusData->ti_stu2_search_desktop) ) . "%";
				$stu2Dt3mAc = sprintf( '%.1f', 100 * ($titusData->ti_stu2_3m_active_desktop / $titusData->ti_stu2_search_desktop) ) . "%";
				$stu2DtLine = "$stu2Dt10s$nb$stu2Dt3m$nb(active:$nb$stu2Dt10sAc$nb$stu2Dt3mAc)";

				$stu2DtStF = sprintf( '%.1f', 100 * ($titusData->ti_stu2_stepfirst_desktop / $titusData->ti_stu2_search_desktop) ) . "%";
				$stu2DtStL = sprintf( '%.1f', 100 * ($titusData->ti_stu2_steplast_desktop / $titusData->ti_stu2_search_desktop) ) . "%";
				$stu2DtSt = "first:$stu2DtStF$nb{$nb}last:$stu2DtStL";
			} else {
				$stu2DtLine = "<i>(no search views)</i>";
				$stu2DtSt = "";
			}

			$r = $titusData->ti_stu2_last_reset;
			if ($r && strlen($r) == 8) {
				$resetLine = "<i>last reset " . substr($r, 0, 4) . '/' . substr($r, 4, 2) . '/' . substr($r, 6, 2) . "</i>";
			} else {
				$resetLine = "";
			}
			$html .= "<hr style='margin:5px 0; '/>";
			$html .= "<p><b>Stu2</b> $nb$nb$nb$resetLine</p>";
			if ($titusData->ti_stu2_search_desktop) {
				$html .= "<p>desktop:$nb$stu2Dt10sAc$nb$stu2Dt3mAc$nb{$nb}views:{$titusData->ti_stu2_search_desktop}</p>";
			} else {
				$html .= "<p>desktop: <i>(no search views)</i></p>";
			}
			$html .= "<p style='font-size:13px; font-style:italic; font-weight:bold; padding-top:3px'>Beta</p>";
			$html .= "<p>mobile:$nb{$stu2MbLine}</p>";
			$html .= "<p>desktop:$nb{$stu2DtLine}</p>";
			if ($stu2MbSt) $html .= "<p>steps mobile$nb{$stu2MbSt}</p>";
			if ($stu2DtSt) $html .= "<p>steps desktop$nb{$stu2DtSt}</p>";
			$html .= "<p>search views mobile:{$titusData->ti_stu2_search_mobile}{$nb}dt:{$titusData->ti_stu2_search_desktop}</p>";
			$html .= "<p>all views mobile:{$titusData->ti_stu2_all_mobile}{$nb}dt:{$titusData->ti_stu2_all_desktop}</p>";
			$html .= "<p>quick{$nb}bounces{$nb}amp:{$titusData->ti_stu2_amp}{$nb}mobile:{$titusData->ti_stu2_quickbounce_mobile}{$nb}dt:{$titusData->ti_stu2_quickbounce_desktop}</p>";

			// summary video data
			$hasSummaryVideo = $titusData->ti_summary_video;
			if ( $hasSummaryVideo ) {
				$html .= "<hr style='margin:5px 0; '/>";
				$html .= "<p>" . 'In a Hurry Views: ' . "{$titusData->ti_summary_video_views}</p>";
				$html .= "<p>" . 'In a Hurry Plays: ' . "{$titusData->ti_summary_video_play}</p>";
				$html .= "<p>" . 'In a Hurry CTR: ' . "{$titusData->ti_summary_video_ctr}%</p>";
				$html .= "<p>" . 'In a Hurry Views Mobile: ' . "{$titusData->ti_summary_video_views_mobile}</p>";
				$html .= "<p>" . 'In a Hurry Plays Mobile: ' . "{$titusData->ti_summary_video_play_mobile}</p>";
				$html .= "<p>" . 'In a Hurry CTR Mobile: ' . "{$titusData->ti_summary_video_ctr_mobile}%</p>";
			}
		}

		$haveBabelfishData = false;
		$languageCode = null;
		if ($titusData) {
			$languageCode = $titusData->ti_language_code;
			// search volume data
			$html .= "<hr style='margin:5px 0; '/>";
			$html .= "<p>Search volume: " . $titusData->ti_search_volume . " - " . $titusData->ti_search_volume_label . "</p>";
			// fellow data
			$html .= "<hr style='margin:5px 0; '/>";
			$html .= "<p>" . wfMessage('ps-fellow') . " ";
			$html .= $titusData->ti_last_fellow_edit ?:"";
			$html .= "&nbsp;&nbsp;</p>";
			$html .= self::getFellowsTime($titusData->ti_last_fellow_edit_timestamp) ?: "";
            $html .= self::getEditingStatus( $titusData->ti_editing_status );

			// babelfish rank
			$haveBabelfishData = true;
			$bfRank = $titusData->ti_babelfish_rank ?: "no data";
			$html .= "<hr style='margin:5px 0; '/>";
			$html .= "<p>" . wfMessage('ps-bfish') . ": {$bfRank}&nbsp;&nbsp;</p>";
		}

		// languages translated
		$lLinks = array();
		if ($languageCode) {
			try {
				$linksTo = TranslationLink::getLinksTo($languageCode, $pageId, true);
				foreach($linksTo as $link) {
					if ($link->fromLang == $languageCode) {
						$href = str_replace("'", "%27", $link->toURL);
						$lLinks[] = "<a href='".htmlspecialchars($href)."'>$link->toLang</a>";
					} else {
						$href = str_replace("'", "%27", $link->fromURL);
						$lLinks[] = "<a href='".htmlspecialchars($href)."'>". $link->fromLang ."</a>";
					}
				}
			} catch (DBQueryError $e) {
				$lLinks[] = "<p>".$e->getText()."</p>";
			}
		}

		// only print the line if we have not printed it above with babelfish data
		if (!$haveBabelfishData) {
			$html .= "<hr style='margin:5px 0;' />";
		}
		$html .= "<p>Translated: " . implode($lLinks, ',') . "</p>";

		// Sensitive Article Tagging

		if ($context->getLanguage()->getCode() == 'en') {
			$html .= "<hr style='margin:5px 0; '/>";
			$saw = new SensitiveArticle\SensitiveArticleWidget($pageId);
			$html .= '<div id="sensitive_article_widget">' . $saw->getHTML() . '</div>';
		}

		// Inbound links
		$target = SpecialPage::getTitleFor('Whatlinkshere', $t->getText());
		$anchor = Articlestats::getInboundLinkCount($t);
		$query = [ 'namespace' => 0, 'hideredirs' => 1 ];
		$link = Linker::link($target, $anchor, [], $query);
		$html .= "<hr style='margin:5px 0;' />";
		$html .= "<p>Inbound links: $link</p>";

		// article id
		$html .= "<hr style='margin:5px 0;' />";
		$html .= "<p>Article Id: $pageId</p>";

		// George 2015-07-08: added hostname for debugging.
		// TODO: remove hostname when titus DNS issue is resolved.
		return array("body"=>$html, "error"=>$error, "hostname"=>gethostname());
	}

    private static function getEditingStatus( $status ) {
        $statusLine = Html::element( 'p', array( 'id' => 'staff-editing-menu-status' ), 'Editing Status: ' . $status );
        $menuTitle = Html::element( 'p', array( 'id' => 'staff-editing-menu-title' ), 'Editing option:' );
        $options = '';
        $options .= Html::rawElement( 'a', array( 'href' => '#', 'role' => 'menuitem', 'data-type' => 'editing' ), 'Request Editing' );
        $options .= Html::rawElement( 'a', array( 'href' => '#', 'role' => 'menuitem', 'data-type' => 'stub' ), 'Send note to future editor' );
        $options .= Html::rawElement( 'a', array( 'href' => '#', 'role' => 'menuitem', 'data-type' => 'removal' ), 'Request removal from Editfish' );
        $options .= Html::rawElement( 'a', array( 'href' => '#', 'role' => 'menuitem', 'data-type' => 'stub' ), 'Request Stub (low quality/low PV/bad title)' );
        $options .= Html::rawElement( 'a', array( 'href' => '#', 'role' => 'menuitem', 'data-type' => 'summaryvideo' ), 'Edit In A Hurry' );
        $menuContent = Html::rawElement( 'div', array( 'id'=> 'staff-editing-menu-content', 'class' => 'menu' ), $options );
        $textArea = Html::rawElement( 'textarea', array( 'id'=> 'sem-textarea', 'class' => 'sem-h', 'placeholder' => 'add any extra comments here' ) );

        $checkBox = Html::rawElement( 'input', array( 'id'=> 'sem-hp-box', 'type' => 'checkbox' ) );
        $checkBoxLabel = Html::rawElement( 'label', array(), "High Priority" );
        $checkBoxWrap = Html::rawElement( 'div', array( 'id' => 'sem-hp', 'class' => 'sem-h' ), $checkBox . $checkBoxLabel );

        $submit .= Html::rawElement( 'a', array( 'id' => 'staff-editing-menu-submit', 'class' => 'sem-h', 'href' => '#' ), 'Submit Editing Request' );
        $menuWrap = Html::rawElement( 'div', array( 'id' => 'staff-editing-menu' ), $menuTitle . $menuContent );
        return $statusLine . $menuWrap . $type . $textArea . $checkBoxWrap . $submit;
    }

    public static function getSampleStatData($sampleTitle) {
		$html = "";

		$dbr = wfGetDB(DB_SLAVE);

		$data = self::getRatingData($sampleTitle, 'ratesample', 'rats', $dbr);
		$html .= "<hr style='margin:5px 0;' />";
		$html .= "<p>Rating Accuracy: {$data->percentage}% of {$data->total} votes</p>";

        $cl = Title::newFromText('ClearRatings', NS_SPECIAL);
        $link = Linker::link($cl, 'Clear ratings', array(), array('type' => 'sample', 'target' => $sampleTitle));
        $html .= "<p>{$link}</p>";

		$data = self::getRatingReasonData($sampleTitle, 'sample', $dbr);
		$html .= "<hr style='margin:5px 0;' />";
		$html .= "<p>Rating Reasons: {$data->total}</p>";

        $cl = SpecialPage::getTitleFor( 'AdminRatingReasons');
        $link = Linker::link($cl, 'View rating reasons', array(), array('item' => $sampleTitle));
        $html .= "<p>{$link}</p>";

        $cl = SpecialPage::getTitleFor( 'AdminRemoveRatingReason', $sampleTitle);
        $link = Linker::link($cl, 'Clear rating reasons');
        $html .= "<p>{$link}</p>";

        return $html;
    }

	private static function addData(&$data) {
		$html = "";
		foreach($data as $key => $value) {
			$html .= "<tr><td style='font-weight:bold; padding-right:5px;'>" . $value . "</td><td>" . wfMessage("ps-" . $key) . "</td></tr>";
		}
		return $html;
	}

	public function execute($par) {
		$out = $this->getContext()->getOutput();
		$request = $this->getRequest();
		$action = $request->getVal('action');
		if ($action == 'ajaxstats') {
            $out->setArticleBodyOnly(true);
            $target = $request->getVal('target');

            $type = $request->getVal('type');
            if ($type == "article") {
                $title = !empty($target) ? Title::newFromURL($target) : null;
                if ($title && $title->exists()) {
                    $result = self::getPagestatData($title->getArticleID());
                    print json_encode($result);
                }
            } elseif ($type == "sample") {
                $title = !empty($target) ? Title::newFromText("sample/$target") : null;
                if ($title) {
                    $result = array(
                        'body' => self::getSampleStatData($target)
                    );
                    print json_encode($result);
                }
            }
        } else if ( $request->wasPosted() && $action == 'editingoptions' ) {
			$out->setArticleBodyOnly(true);
            $out->disable();
            $textBox = $request->getVal( 'textbox' );
            $type = $request->getVal( 'type' );
            $highPriority = $request->getVal( 'highpriority' );
            if ( $highPriority == 'true' ) {
                $highPriority = 1;
            } else {
                $highPriority = 0;
            }
            $pageId = $request->getVal( 'pageid' );
            $title  = Title::newFromID( $pageId );
            if ( $title && $title->exists() ) {
                $title = 'http:' . $title->getFullURL();
            } else {
                $title = "unknown";
            }
			$isSummaryVideoFeedback = false;
			if ( $type == 'Edit In A Hurry' ) {
				$isSummaryVideoFeedback = true;
			}
            $file = $this->getSheetsFile( $isSummaryVideoFeedback );
            $sheet = $file->sheet('default');
            $userName = $this->getUser()->getName();
            $data = array(
                'submitter' => $userName,
                'time' => date('Y-m-d'),
                'option' => $type,
                'comment' => $textBox,
                'url' => $title,
                'pageid' => $pageId,
                'highpriority' => $highPriority,
            );
			if ( $isSummaryVideoFeedback ) {
				$data = array(
					'time' => date('Y-m-d'),
					'submitter' => $userName,
					'pageid' => $pageId,
					'url' => $title,
					'comments' => $textBox,
					'new' => $highPriority,
				);
			}
            $sheet->insert( $data );
            return;
		}
	}

	/**
	 * @return Google_Spreadsheet_File
	 */
	private function getSheetsFile( $isSummaryVideoFeedback = false ): Google_Spreadsheet_File {
		global $wgIsProduction;

		$keys = (Object)[
			'client_email' => WH_GOOGLE_SERVICE_APP_EMAIL,
			'private_key' => file_get_contents(WH_GOOGLE_DOCS_P12_PATH)
		];
		$client = Google_Spreadsheet::getClient($keys);

		// Set the curl timeout within the raw google client.  Had to do it this way because the google client
		// is a private member within the Google_Spreadsheet_Client
		$rawClient = function(Google_Spreadsheet_Client $client) {
			return $client->client;
		};
		$rawClient = Closure::bind($rawClient, null, $client);
        $timeoutLength = 600;
		$configOptions = [
			CURLOPT_CONNECTTIMEOUT => $timeoutLength,
			CURLOPT_TIMEOUT => $timeoutLength
		];
		$rawClient($client)->setClassConfig('Google_IO_Curl', 'options', $configOptions);

		if ($wgIsProduction) {
			if ( $isSummaryVideoFeedback ) {
				$fileId = '1E86B9G_Za-FSicM14vsMTwjCNHIScZgPsAbfq7HYQOU';
			} else {
				$fileId = '11BpgghgRSFuRfylWoViEhQnn8ib-jCXGrNE7qkGchJk';
			}
		} else {
			if ( $isSummaryVideoFeedback ) {
				$fileId = '1xpmYq7euPEEcweyTWkljDmloaT7scM0WDVn4vrIIh3M';
			} else {
				$fileId = '1sMPfAjcG2zCj2c-m3o57QIQpnG19a8Z1SgohR0FP6GA';
			}
		}
		$file = $client->file($fileId);

		return $file;
	}

	public static function getJSsnippet($type) {
		global $wgLanguageCode;
?>
<script>
	function setupEditMenu() {
		$('#staff-editing-menu-title').on('click',function(e) {
            e.preventDefault();
            return;
        });

		$('#staff-editing-menu').hover(function(e) {
            $('#staff-editing-menu-content').show();
            $("#sem-done").remove();
        }, function() {
            $('#staff-editing-menu-content').hide();
        });

		$('#staff-editing-menu a').on('click',function(e) {
			if ($(e.target).data('type') == 'summaryvideo') {
				$('#sem-hp label').text('Request New In a Hurry');
			} else {
				$('#sem-hp label').text('High Priority');
			}
            var text = $(e.target).text();
            $('#semt-type').remove();
            var type = $('<div id="semt-type" class="sem-h"></div>').text(text);
            $('#sem-textarea').data('type', text);
            $('#staff-editing-menu').after(type);
            $('#staff-editing-menu-content').hide();
			// if this is the summary then set the text to something else
            $('.sem-h').show();
            e.preventDefault();
            return;
        });

        var staffEditSubmitted = false;
		$('#staff-editing-menu-submit').on('click',function(e) {
            if (staffEditSubmitted) {
                e.preventDefault();
                return;
            }
			var textBox = $('#sem-textarea').val();
			var type = $('#sem-textarea').data('type');
			var isSummary = type == 'Edit In A Hurry';
			if (textBox == '' && !isSummary) {
                alert("you must enter text to submit");
                e.preventDefault();
                return;
            }

            staffEditSubmitted = true;
			var url = '/Special:Pagestats';
            var action ='editingoptions';
            var textBox = $('#sem-textarea').val();
            var highpriority = $('#sem-hp-box').prop('checked');
            $.post(
                url,
                {action:action,textbox:textBox,type:type,pageid:wgArticleId,highpriority:highpriority},
                function(result) {
                    staffEditSubmitted = false;
                    $('.sem-h').hide();
                    $('#sem-textarea').val('');
                    $('#sem-textarea').data('type', '');
                    $('#staff-editing-menu-submit').after('<p id="sem-done">your submission has been saved</p>');
            });
            e.preventDefault();
            return;
        });
    }
	function setupStaffWidgetClearStuLinks() {
		$('.clearstu').click(function(e) {
			e.preventDefault();
			var answer = confirm("reset all stu data for this page?");
			if (answer == false) {
				return;
			}
			var url = '/Special:Stu';
			var pagesList = window.location.origin + window.location.pathname;

			$.post(url, {
				"discard-threshold" : 0,
				"data-type": "summary",
				"action" : "reset",
				"pages-list": pagesList
				},
				function(result) {
					console.log(result);
				});
		});
	}

	if ($('#staff_stats_box').length) {
		$('#staff_stats_box').html('Loading...');
        var type = "<?php echo $type ?>";
        var target = (type == "sample") ? wgSampleName : wgTitle;

		getData = {'action':'ajaxstats', 'target':target, 'type':type};

		$.get('/Special:Pagestats', getData, function(data) {
				var result = (data && data['body']) ? data['body'] : 'Could not retrieve stats';
				$('#staff_stats_box').html(result);
				if (data && data['error']) {
					console.log(data['error']);
				}

				if ($('.clearstu').length) {
					setupStaffWidgetClearStuLinks();
				}

                if ( $('#staff-editing-menu').length ) {
					setupEditMenu();
                }
			}, 'json');
	}

	<?php
	if ($wgLanguageCode == 'en') {
		echo SensitiveArticle\SensitiveArticleWidget::getJS();
	}
	?>

</script>
<?
	}

}
