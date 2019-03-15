<?php

$wgExtensionCredits['api'][] = array(
    'path' => __FILE__,
    'name' => 'Category Listing API',
    'description' => 'An API extension to list available top-level categories'
                   . ' or subcategories and their contents',
    'descriptionmsg' => 'sampleapiextension-desc',
    'version' => 1,
    'author' => 'George Bahij',
    'url' => 'https://www.mediawiki.org/wiki/API:Extensions',
);

$wgAutoloadClasses['ApiCategoryListing'] =
    __DIR__ . '/ApiCategoryListing.body.php';
$wgAutoloadClasses['CategoryLister'] =
    __DIR__ . '/ApiCategoryListing.body.php';
$wgAPIModules['categorylisting'] = 'ApiCategoryListing';
