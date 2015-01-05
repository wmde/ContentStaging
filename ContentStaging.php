<?php
# Alert the user that this is not a valid access point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
        echo <<<EOT
To install this extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/ContentStaging/SpecialContentStaging.php" );
EOT;
        exit( 1 );
}
 
$wgExtensionCredits[ 'specialpage' ][] = array(
        'path' => __FILE__,
        'name' => 'Content Staging',
        'author' => 'Kai Nissen for Wikimedia Deutschland e. V.',
        'url' => 'https://www.wikimedia.de/',
        'descriptionmsg' => 'contentstaging-desc',
        'version' => '0.1.1',
);
 
$wgAutoloadClasses[ 'SpecialContentStaging' ] = __DIR__ . '/SpecialContentStaging.php';
$wgExtensionMessagesFiles[ 'ContentStaging' ] = __DIR__ . '/ContentStaging.i18n.php';
$wgSpecialPages[ 'ContentStaging' ] = 'SpecialContentStaging';
