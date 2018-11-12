<?php

/**
 * Summary namespace, magic word, and parser hook reference:
 * https://www.mediawiki.org/wiki/Manual:Parser_functions
 */

class SummarySection {

	const SUMMARY_POSITION_TOP_CLASS = 'sumpos_top';
	const SUMMARY_POSITION_BOTTOM_CLASS = 'sumpos_bottom';
	const QUICKSUMMARY_TEMPLATE_PREFIX = '#quicksummary:';

	/**
	  * renderSummary()
	  * - render the output of {{#quicksummary:}} to wikitext
	  *
	  * @param $parser = the Parser, obvs
	  * @param $position = position of the summary section ('top' or 'bottom') [defaults to 'top']
	  * @param $last_sentence = final sentence of the summary in wikitext (doesn't show for 'bottom' position)
	  * @param $summary_text = the wikitext of the summary
	  *
	  * @return wikitext
	  */
	public static function renderSummary( $parser, $position = '', $last_sentence = '', $summary_text = '' ) {
		$position_class = self::positionClass($position);
		$last_sentence = self::showLastSentence() ? $last_sentence : '';

		$output = wfMessage('summary_section', $summary_text, $last_sentence, $position_class)->text();
		return $output;
	}

	private static function positionClass($position) {
		$class = $position == 'bottom' ? self::SUMMARY_POSITION_BOTTOM_CLASS : self::SUMMARY_POSITION_TOP_CLASS;
		return $class;
	}

	//show the last sentence on desktop always, but never on mobile
	private static function showLastSentence() {
		return !Misc::isMobileMode();
	}

	public static function summaryData($page_title): array {
		$summary_content = '';
		$last_sentence = '';
		$at_top = true;

		$summary = Title::newFromText($page_title, NS_SUMMARY);

		if ($summary && $summary->exists()) {
			$rev = Revision::newFromTitle($summary);
			if ($rev) {
				$wikitext = $rev->getText();

				$param = "((?:.|\n)*)";
				$regex = '{{'.self::QUICKSUMMARY_TEMPLATE_PREFIX.$param.'\|'.$param.'\|'.$param.'}}$';
				preg_match('/'.$regex.'/imU', $wikitext, $m);

				$summary_content = isset($m[3]) ? trim($m[3]) : '';
				$last_sentence = isset($m[2]) ? trim($m[2]) : '';
				$at_top = isset($m[1]) && trim($m[1]) == 'bottom' ? false : true;
			}
		}

		return [
			'content' => $summary_content,
			'last_sentence' => $last_sentence,
			'at_top' => $at_top
		];
	}

	public static function onParserFirstCallInit( &$parser ) {
		if (RequestContext::getMain()->getLanguage()->getCode() == 'en') {
			$parser->setFunctionHook( 'quicksummary', 'SummarySection::renderSummary' );
		}
	}

	//this uses the phpQuery object already started in WikihowArticleHTML::processArticleHTML()
	public static function onProcessArticleHTMLAfter($out) {
		$context = RequestContext::getMain();
		if ($context->getLanguage()->getCode() != 'en') return;

		$context->getOutput()->addModules('ext.wikihow.summary_section_edit_link');

		//Summary placement on the page
		if (pq('#summary_position')->length) {
			if (pq('#summary_position')->hasClass(self::SUMMARY_POSITION_BOTTOM_CLASS)) {

				if (pq("div.video.section")->length)
					pq("div.video.section")->after(pq('#quick_summary_section'));
				elseif (pq("div.qa.section")->length)
					pq("div.qa.section")->after(pq('#quick_summary_section'));
				else
					pq("div.steps:last")->after(pq('#quick_summary_section'));

			}
			else {
				pq("#intro")->after(pq('#quick_summary_section'));
			}
		}

		//edit link override
		if (pq('#quick_summary_section')->length) {
			if ($context->getRequest()->getVal('diff','') != '') {
				pq('#quick_summary_section h2 .editsection')->remove();
			}
			else {
				pq('#quick_summary_section h2 .editsection')->attr('href','#');
			}
		}
	}

	public static function onBeforePageDisplay(OutputPage &$out, Skin &$skin ) {
		$title = $out->getTitle();
		if ($title && $title->inNamespace( NS_SUMMARY )) {
			$page_url = $out->getTitle()->getText();
			$html = '<b>'.wfMessage('summary_namespace_instructions', $page_url)->parse().'</b><br /><br />';
			$out->prependHTML($html);
		}
	}

	/**
	 * onTitleMoveComplete
	 *
	 * if a title moves and it has a quicksummary, we have to:
	 * - move the Summary page
	 * - update the new article wikitext with the new summary template
	 */
	public static function onTitleMoveComplete($oldTitle, $newTitle, $user, $pageid, $redirid, $reason) {
		if (!$oldTitle || !$oldTitle->inNamespace( NS_MAIN )) return true;

		$oldSummary = Title::newFromText($oldTitle->getText(), NS_SUMMARY);

		if ($oldSummary && $oldSummary->exists()) {
			$newSummary = Title::newFromText($newTitle->getText(), NS_SUMMARY);

			if (!$newSummary->exists()) {
				$oldSummary->moveTo($newSummary, $auth = false, $reason);
			}

			$newRev = Revision::newFromTitle($newTitle);
			if ($newRev) {
				$wikitext = $newRev->getText();

				$ns = MWNamespace::getCanonicalName(NS_SUMMARY);
				$title_regex = '('.preg_quote($oldTitle->getText()).'|'.preg_quote($oldTitle->getDBKey()).')';

				$wikitext = preg_replace('/{{'.$ns.':'.$title_regex.'}}/i','{{'.$ns.':'.$newTitle->getDBKey().'}}', $wikitext);

				$content = ContentHandler::makeContent($wikitext, $newTitle);
				$comment = wfMessage('summary_move_log')->text();
				$edit_flags = EDIT_UPDATE | EDIT_MINOR;

				$page = WikiPage::factory($newTitle);
				$page->doEditContent($content, $comment, $edit_flags);
			}
		}
	}

	public static function addDesktopTOCItems() {
		$tocText = wfMessage('summary_toc')->text();
		pq("<a id='summary_toc' href='#'>$tocText</a>")->insertAfter("#method_toc > span");
	}
}