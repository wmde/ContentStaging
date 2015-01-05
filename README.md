# Content Staging Extension for MediaWiki

An extension to provide staging functionality when using MediaWiki as a
CMS.

## Installation

Store files into /path/to/your/mw/extensions/ContentStaging, then add 
the following line to your LocalSettings.php:

require_once( "$IP/extensions/ContentStaging/ContentStaging.php" );


## Configuration

Define globals according to your desired configuration:

// use a specific namespace for managed content
$wgContentStagingNamespace = NS_MAIN;

// use a specific page prefix
$wgContentStagingPrefix = "Spendenseite-HK2013";

// define stages
$wgContentStagingStages = array(
	"test" => 0,
	"stage" => 0,
	"production" => 0
);


## Usage

