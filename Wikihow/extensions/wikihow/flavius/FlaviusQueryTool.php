<?php

if ( ! defined( 'MEDIAWIKI' ) ) die();

$wgExtensionCredits['specialpage'][] = array(
  'name' => 'Flavius Query Tool',
  'author' => 'Gershon Bialer',
  'description' => 'A tool to query the Users',
);

$wgSpecialPages['FlaviusQueryTool'] = 'FlaviusQueryTool';
$wgAutoloadClasses['FlaviusQueryTool'] = dirname(__FILE__) . '/FlaviusQueryTool.body.php';
$wgResourceModules['ext.wikihow.flaviusquerytool'] = array(
	'localBasePath' => dirname(__FILE__) . '/',
	'remoteExtPath' => 'wikihow/flavius',
	'targets' => ['desktop'],
	'styles' => [
		'resources/styles/flaviusquerytool.css',
	],
	'scripts' => [
		'../common/download.jQuery.js',
		'resources/scripts/flaviusquerytool.js',
	],
	'dependencies' => [
		'ext.wikihow.common_bottom',
		'wikihow.common.ace',
		'wikihow.common.querybuilder',
	],
);