<?php

if (!defined('MEDIAWIKI')) {
    die();
}

require_once("$IP/extensions/wikihow/titus/GoogleSpreadsheet.class.php");

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'SheetInvoicing',
    'author' => 'Alberto Burgos',
    'description' => 'Admin tools used to email invoices to contractors, built on top of Google Sheets',
);

# Shared code

$wgAutoloadClasses['SheetInv\Mailer'] = dirname( __FILE__ ) . '/Mailer.php';
$wgAutoloadClasses['SheetInv\ParsingResult'] = dirname( __FILE__ ) . '/ParsingResult.php';

$wgResourceModules['ext.wikihow.SheetInvoicing'] = [
	'targets' => ['desktop'],
	'position' => 'top',
	'remoteExtPath' => 'wikihow/SheetInvoicing/resources',
	'localBasePath' => dirname(__FILE__) . '/resources',
	'styles' => ['sheet_invoicing.less'],
	'scripts' => ['sheet_invoicing.js'],
];

# Special:ExpertInvoicing

$wgSpecialPages['ExpertInvoicing'] = 'ExpInv\ExpertInvoicing';

$baseDir = dirname(__FILE__) . '/special/ExpertInvoicing';
$wgAutoloadClasses['ExpInv\ExpertInvoicing'] = "$baseDir/ExpertInvoicing.body.php";
$wgAutoloadClasses['ExpInv\Mailer'] = "$baseDir/Mailer.php";
$wgAutoloadClasses['ExpInv\Spreadsheet'] = "$baseDir/Spreadsheet.php";

$wgResourceModules['ext.wikihow.ExpertInvoicing'] = [
	'dependencies' => 'ext.wikihow.SheetInvoicing',
	'targets' => ['desktop'],
	'position' => 'top',
	'remoteExtPath' => 'wikihow/SheetInvoicing/special/ExpertInvoicing/resources',
	'localBasePath' => "$baseDir/resources",
	'scripts' => ['expert_invoicing.js'],
];

# Special:WikiVisualInvoicing

$wgSpecialPages['WikiVisualInvoicing'] = 'WVI\WikiVisualInvoicing';

$baseDir = dirname(__FILE__) . '/special/WikiVisualInvoicing';
$wgAutoloadClasses['WVI\WikiVisualInvoicing'] = "$baseDir/WikiVisualInvoicing.body.php";
$wgAutoloadClasses['WVI\Mailer'] = "$baseDir/Mailer.php";
$wgAutoloadClasses['WVI\Spreadsheet'] = "$baseDir/Spreadsheet.php";

$wgResourceModules['ext.wikihow.WikiVisualInvoicing'] = [
	'dependencies' => 'ext.wikihow.SheetInvoicing',
	'targets' => ['desktop'],
	'position' => 'top',
	'remoteExtPath' => 'wikihow/SheetInvoicing/special/WikiVisualInvoicing/resources',
	'localBasePath' => "$baseDir/resources",
	'scripts' => ['wikivisual_invoicing.js']
];
