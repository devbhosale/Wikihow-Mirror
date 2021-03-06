<?php

class EmailLink extends SpecialPage {

	function __construct() {
		parent::__construct( 'EmailLink' );
	}

	function reject() {
		global $wgOut, $wgUser;
		$dbw = wfGetDB(DB_MASTER);
		$dbw->selectDB('whdata');
		$dbw->insert('rejected_email_links',
			array(
				'rel_text' => "REJECTED\nuserid: " . $wgUser->getID() . "\n"
				. wfReportTime() . "\nReferer:" . $_SERVER["HTTP_REFERER"] . "\n"
				. wfGetIP() . "\n" . print_r($_POST, true)
			),
			__METHOD__);
		$dbw->selectDB(WH_DATABASE_NAME);
		//be coy
		$this->thanks();
	}

	function thanks() {
		global $wgOut, $wgRequest;
		$wgOut->addHTML("<br/><br/>".wfMessage('thank-you-sending-article')."<br/><br/>");
		if (!$wgRequest->getVal('fromajax')) {
			$wgOut->returnToMain( false );
		}
		return;
	}

	function getToken1() {
		global $wgRequest, $wgUser;
		$target  = urldecode($wgRequest->getVal('target'));
		//$s = $wgUser->getID() . $_SERVER['HTTP_X_FORWARDED_FOR'] . $_SERVER['REMOTE_ADDR'] . $target  . date ("YmdH");
		$s = $wgUser->getID() .  wfGetIP() . $target  . date ("YmdH");
		return md5($s);
	}

	function getToken2() {
		global $wgRequest, $wgUser;
		$target  = urldecode($wgRequest->getVal('target'));
		//$s = $wgUser->getID() . $_SERVER['HTTP_X_FORWARDED_FOR'] . $_SERVER['REMOTE_ADDR'] . $target . date ("YmdH", time() - 40 * 40);
		$s = $wgUser->getID() . wfGetIP() . $target . date ("YmdH", time() - 40 * 40);
		return md5($s);
	}

	function execute($par) {
		// NOTE from Reuben 3/6/2018: don't turn on this feature again without refactoring
		// this code. There are multiple problems reported with parameters not being sanitized.
		// The html generated by this class should refactored to be generated by Mustache
		// templates. This will protect us against these reported XSS attacks:
		// https://www.openbugbounty.org/reports/116472/
		// https://www.openbugbounty.org/reports/116471/
		// https://www.openbugbounty.org/reports/116470/
		// https://www.openbugbounty.org/reports/116469/
		// https://www.openbugbounty.org/reports/116468/

		return; // Jordan turning off at Eliz's request due to spam emails 2018/01/05

		global $wgUser, $wgOut, $wgLang, $wgTitle, $wgMemc, $wgDBname, $wgScriptPath;
		global $wgRequest, $wgSitename, $wgLanguageCode;
		global $wgScript;

		if ($wgRequest->getVal('fromajax')) {
			$wgOut->setArticleBodyOnly(true);
		}

		$this->setHeaders();

		$fc = new FancyCaptcha();
		$pass_captcha = true;

		$name = $from = $r1 = $r2 = $r3 = $m = "";
		if ($wgRequest->wasPosted())  {
			$pass_captcha 	= $fc->passCaptcha();
			$email 			= trim( $wgRequest->getVal("email") );
			$name 			= $wgRequest->getVal("name");
			$recipient1 	= trim( $wgRequest->getVal('recipient1') );
			$recipient2 	= trim( $wgRequest->getVal('recipient2') );
			$recipient3 	= trim( $wgRequest->getVal('recipient3') );
			$message 		= $wgRequest->getVal('message');
			$invalidEmail = '';
			if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$invalidEmail = $email;
			} elseif ($recipient1 && !filter_var($recipient1, FILTER_VALIDATE_EMAIL)) {
				$invalidEmail = $recipient1;
			} elseif ($recipient2 && !filter_var($recipient2, FILTER_VALIDATE_EMAIL)) {
				$invalidEmail = $recipient2;
			} elseif ($recipient3 && !filter_var($recipient3, FILTER_VALIDATE_EMAIL)) {
				$invalidEmail = $recipient3;
			}
		}

		if (!$wgRequest->wasPosted() || !$pass_captcha) {
			if ( $wgUser->getID() > 0 && !$wgUser->canSendEmail() ) {
				$userEmail = $wgUser->getEmail();
				// If there is no verification time stamp and no email on record, show initial message to have a user input a valid email address
				if ( empty($userEmail) ) {
					wfDebug( "User can't send.\n" );
					$wgOut->showErrorPage( "mailnologin", "mailnologintext" );
				} else {	// When user does have an email on record, but has not verified it yet
					wfDebug( "User can't send without verification.\n" );
					$wgOut->showErrorPage( "mailnologin", "mailnotverified" );
				}
				return;
			}

			$titleKey = isset( $par ) ? $par : $wgRequest->getVal( 'target' );

			if ($titleKey == "") {
				$wgOut->addHTML ("<br/></br><font color=red>".wfMessage('error-no-title')."</font>");
				return;
			}

			if ($invalidEmail) {
				$wgOut->addHTML ("<br/></br><font color=red>".wfMessage('error-invalid-email-address')."</font>");
				return;
			}

			$titleObj = Title::newFromURL($titleKey);
			if (!$titleObj) $titleObj = Title::newFromURL(urldecode($titleKey));
			if (!$titleObj || $titleObj->getArticleID() < 0) {
				$wgOut->addHTML ("<br/></br><font color=red>".wfMessage('error-article-not-found')."</font>");
				return;
			} else {
				$titleKey = $titleObj->getDBKey();
			}

			$subject = $titleObj->getText();
			$titleText = $titleObj->getText();
			if (WikihowArticleEditor::articleIsWikiHow($wikiPage)) {
				$subject = wfMessage('howto', $subject);
				$titleText = wfMessage('howto',$titleText);
			}
			$subject = wfMessage('wikihow-article-subject',$subject);
			if ($titleObj->getText() == wfMessage('mainpage'))
				$subject = wfMessage('wikihow-article-subject-main-page');

			// add the form HTML
			$article_title = wfMessage('article').":";
			if ($titleObj->inNamespace(NS_ARTICLE_REQUEST)) {
				$wgOut->addHTML ( "<br/><br/>".wfMessage('know-someone-answer-topic-request') );
				$article_title = wfMessage('topic-requested').":";
			}

			if ( !$titleObj->inNamespaces(NS_MAIN, NS_ARTICLE_REQUEST) ) {
				$wgOut->showErrorPage('emaillink', 'emaillink_invalidpage');
				return;
			}

			if ($titleObj->getText() == "Books For Africa") {
				$message = wfMessage('friend-sends-article-email-africa-body');
			}

			$titleKey = urlencode($titleKey);
			$token = $this->getToken1();
			$wgOut->addHTML ( "
<form id=\"emaillink\" method=\"post\">
<input type=\"hidden\" name=\"target\" value=\"$titleKey\">
<input type=\"hidden\" name=\"token\" value=\"$token\">
<table border=\"0\">
<tr>
<td valign=\"top\" colspan=\"1\" class='mw-label'>$article_title</td>
<td valign=\"top\" colspan=\"2\">$titleText</td>
</tr>
");
			if ($wgUser->getID() <= 0) {
				$wgOut->addHTML("
<tr>
<td valign=\"top\" colspan=\"1\" class='mw-label'>".wfMessage('your-name').":</td>
<td valign=\"top\" colspan=\"2\"><input type=text size=\"40\" name=\"name\" value=\"{$name}\" class='input_med'></td>
</tr>
<tr>
<td valign=\"top\" colspan=\"1\" class='mw-label'>".wfMessage('your-email').":</td>
<td valign=\"top\" colspan=\"2\"><input type=text size=\"40\" name=\"email\" value=\"{$email}\" class='input_med'></td>
</tr>");

			}
			$wgOut->addHTML("
<tr>
<td valign=\"top\" width=\"300px\" colspan=\"1\" rowspan='3' class='mw-label'>".wfMessage('recipient-emails').":</td>
<td valign=\"top\" colspan=\"2\"><input type=text size=\"40\" name=\"recipient1\" value=\"{$recipient1}\" class='input_med'></td>
</tr>
<tr>
<td valign=\"top\" colspan=\"2\"><input type=text size=\"40\" name=\"recipient2\" value=\"{$recipient2}\" class='input_med'></td>
</tr>
<tr>
<td valign=\"top\" colspan=\"2\"><input type=text size=\"40\" name=\"recipient3\" value=\"{$recipient3}\" class='input_med'></td>
</tr>
<!--<tr>
<td valign=\"top\" colspan=\"1\">".wfMessage('emailsubject').":</td>
<td valign=\"top\" colspan=\"2\"><input type=text size=\"40\" name=\"subject\" value=\"$subject\" class='input_med'></td>
</tr>-->
<tr>
<td colspan=\"1\" valign=\"top\" class='mw-label'>".wfMessage('emailmessage').":</td>
<td colspan=\"2\"><TEXTAREA rows=\"5\" cols=\"55\" name=\"message\">{$message}</TEXTAREA></td>
</tr>
<tr>
<TD>&nbsp;</TD>
<TD colspan=\"2\"><br/>
"  . wfMessage('emaillink_captcha')->parseAsBlock() . "
"  . ($pass_captcha ? "" : "<br><br/><font color='red'>Sorry, that phrase was incorrect, try again.</font><br/><br/>") . "
" . $fc->getForm('') . "
</TD>
</tr>
<tr>
<TD>&nbsp;</TD>
<TD colspan=\"2\"><br/>
<input type='submit' name=\"wpEmaiLinkSubmit\" value=\"".wfMessage('submit')."\" class=\"button primary\" />
</td>
</tr>
<tr>
<TD colspan=\"3\">
<br/><br/>
".wfMessage('share-message-three-friends')."
</TD>
</TR>

");

			// do this if the user isn't logged in
			$wgOut->addHTML("</table> </form>");
		} else {

			if ( $wgUser->pingLimiter('emailfriend') ) {
				throw new ThrottledError;
			}

			$usertoken = $wgRequest->getVal('token');
			$token1 = $this->getToken1();
			$token2 = $this->getToken2();
			if ($usertoken != $token1 && $usertoken != $token2) {
				$this->reject();
				echo "token $usertoken $token1 $token2\n";
				exit;
			}

			// check referrer
			$good_referer = Title::makeTitle(NS_SPECIAL, "EmailLink")->getFullURL();
			$referer = $_SERVER["HTTP_REFERER"] ;
			if (strpos($refer, $good_referer) != 0) {
				$this->reject();
				echo "referrer bad\n";
				exit;
			}

			// this is a post, accept the POST data and create the Request article
			$recipient1 = $_POST['recipient1'];
			$recipient2 = $_POST['recipient2'];
			$recipient3 = $_POST['recipient3'];
			$titleKey = $_POST['target'];
			$message = $_POST['message'];

			if ($titleKey == "Books-For-Africa") {
				$titleKey = "wikiHow:" . $titleKey;
			}

			$titleKey = urldecode($titleKey);
			$titleObj = Title::newFromDBKey($titleKey);

			if ($titleObj->getArticleID() <= 0) {
				$this->reject();
				echo "no article id\n";
				exit;
			}
			$dbkey = $titleObj->getDBKey();

			$wikiPage = WikiPage::factory($titleObj);
			$subject = $titleObj->getText();
			$how_to = $subject;
			if (WikihowArticleEditor::articleIsWikiHow($wikiPage)) {
				$subject = wfMessage("howto", $subject);
			}
			$how_to = $subject;
			if ($titleObj->inNamespace(NS_ARTICLE_REQUEST)) {
				$subject = wfMessage('subject-requested-howto').": ".wfMessage("howto", $subject);
			} else {
				$subject = wfMessage('wikihow-article-subject',$subject);
			}
			if ( !$titleObj->inNamespaces(NS_MAIN, NS_ARTICLE_REQUEST) ) {
				$wgOut->showErrorPage('emaillink', 'emaillink_invalidpage');
				return;
			}

			// for the body of the email
			$titleText = $titleObj->getText();
			if ($titleText != wfMessage('mainpage')) {
				$summary = Article::getSection($wikiPage->getText(), 0);
				$summary = Wikitext::flatten($summary);
			}
			$url = $titleObj->getCanonicalURL();

			$from_name = "";
			$validEmail = "";
			if ($wgUser->getID() > 0) {
				$from_name = $wgUser->getName();
				$real_name = $wgUser->getRealName();
				if ($real_name != "") {
					$from_name = $real_name;
				}
				$email = $wgUser->getEmail();
				if ($email != "") {
					$validEmail = $email;
					$from_name .= "<$email>";
				} else {
					$from_name .= "<do_not_reply@wikihow.com>";
				}
			} else {
				$email = $wgRequest->getVal("email");
				$name = $wgRequest->getVal("name");
				if ($email == "") {
					$email = "do_not_reply@wikihow.com";
				} else {
					$validEmail = $email;
				}

				$from_name = "$name <$email>";
			}

			if (strpos($email, "\n") !== false
				|| strpos($recipient1, "\n") !== false
				|| strpos($recipient2, "\n") !== false
				|| strpos($recipient3, "\n") !== false
				|| strpos($title, "\n") !== false) {
				echo "reciep\n";
				exit;
				$this->reject();
				return;
			}
			$r_array = array();
			$num_recipients = 0;
			if ($recipient1 != "") {
				$num_recipients++;
				$x = explode(";", $recipient1);
				$r_array[] = $x[0];
			}
			if ($recipient2 != "") {
				$num_recipients++;
				$x = explode(";", $recipient2);
				$r_array[] = $x[0];
			}
			if ($recipient3 != "") {
				$num_recipients++;
				$x = explode(";", $recipient3);
				$r_array[] = $x[0];
			}

			if ($validEmail != "" && !in_array($validEmail, $r_array)) {
				$num_recipients++;
				$r_array[] = $validEmail;
			}

			if ($titleObj->inNamespace(NS_ARTICLE_REQUEST)) {
				$body = "$message

----------------

	".wfMessage('article-request-email',
			$how_to,
			"http://www.wikihow.com/index.php?title2=$dbkey&action=easy&requested=$dbkey",
			"http://www.wikihow.com/Request:$dbkey",
			"http://www.wikihow.com/".wfMessage('writers-guide-url'),
			"http://www.wikihow.com/".wfMessage('about-wikihow-url')."") ;
			} elseif ($titleObj->getText() == wfMessage('mainpage')) {
				$body = "$message

----------------

	".wfMessage('friend-sends-article-email-main-page')."

	";
			} elseif ($titleObj->inNamespace(NS_PROJECT)) {
				$body = "$message";
			} else {
				$body = "$message

----------------

" . wfMessage('friend-sends-article-email', $how_to, $summary, $url) . "

	";
			}

			$from = new MailAddress($email);
			foreach ($r_array as $address) {
				$address = preg_replace("@,.*@", "", $address);
				$to = new MailAddress($address);
				$sbody = $body;
				if ($address == $validEmail) {
					$sbody = wfMessage('copy-email-from-yourself') . "\n\n" . $sbody;
				}

				$link = UnsubscribeLink::newFromEmail( $address );
				$sbody .= "\n\n" . wfMessage( 'unsubscribe-anon', $link->getLink() )->inContentLanguage()->plain();

				if (!UserMailer::send($to, $from, $subject, $sbody, null, null, "share_friend")) {
						//echo "got an en error\n";
				}
			}
			EmailLink::addLinksEmailed($num_recipients);
			$this->thanks();
		}
	}


	//used to be in SiteStats.php but pulled out here
	//because it's the only place we use it and fewer tweaks
	//to core code is better
	function addLinksEmailed($num) {
		$dbw = wfGetDB( DB_MASTER );
		$sql = "UPDATE site_stats SET ss_links_emailed = ss_links_emailed + " + (int)$num;
		$dbw->query( $sql, __METHOD__ );
	}
}

