<?php
class SpecialContentStaging extends SpecialPage {
	
	private $mwNamespaceIndex;
	private $mwNamespace;
	private $pagePrefix;
	private $stages;

	function __construct() {
		global $wgContentStagingPrefix, $wgContentStagingNamespace, $wgContentStagingStages;

		$this->mwNamespaceIndex = isset( $wgContentStagingNamespace ) ? ( $wgContentStagingNamespace ) : 0;
		$this->mwNamespace = MWNamespace::getCanonicalName( $this->mwNamespaceIndex ) . ":";
		$this->pagePrefix = isset( $wgContentStagingPrefix ) ? $wgContentStagingPrefix : "CMS";
		# TODO: make the special page respect user defined names and number of stages
		$this->stages = isset( $wgContentStagingStages ) ? $wgContentStagingStages : array( "test" => 0, "stage" => 0, "production" => 0 );

		parent::__construct( 'ContentStaging', 'edit', true, false, 'default', false );
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

		$action = $request->getText( 'action' );
		$page = $request->getText( 'page' );
		$source = $request->getText( 'source' );
		$target = $request->getText( 'target' );

		if ( !empty( $this->pagePrefix ) ) {
			if ( $action === "copy" ) {
				$this->copyPage( $this->pagePrefix, $page, $source, $target );
			}

			$pages = array();
			$allPages = $this->getPagesByPrefix( $this->pagePrefix );
			foreach( $allPages as $page ) {
				$stage = $this->getStage( $page->page_title );
				$title = $this->getTitleWithoutPrefixes( $page->page_title );
				if( !array_key_exists( $title, $pages ) ) {
					$pages[$title] = $this->stages;
				}
				$pages[$title][$stage] = WikiPage::newFromID( $page->page_id );
				if ( $action === "stageall" && $source === $stage ) {
					$pages[$title][$target] = $this->copyPage( $this->pagePrefix, $page->page_id, $source, $target );
				}
			}

			$resultTable = "{| class=\"wikitable sortable\" border=\"1\"\n";
			$resultTable .= "|-\n";
			$resultTable .= "! Titel\n";
			$resultTable .= "! Test <html><br /><a href='?title=Special:ContentStaging&prefix=" . $this->pagePrefix . "&action=stageall&source=test&target=stage'>Stage all</a></html>\n";
			$resultTable .= "! Stage <html><br /><a href='?title=Special:ContentStaging&prefix=" . $this->pagePrefix . "&action=stageall&source=stage&target=production'>Stage all</a></html>\n";
			$resultTable .= "! Produktion\n";
			
			foreach( $pages as $title => $stages ) {
				$resultTable .= "|-\n";
				$resultTable .= "| [[" . $this->mwNamespace . $this->pagePrefix . "/test/" . $title . " | " . $title . "]]\n";

				foreach( array_keys( $this->stages ) as $stage ) {
					if( $stages[$stage] !== 0 ) {
						$target = "";
						$source = $stage;
						$keys = array_keys( $stages );
						$element = array_search( $source, $keys );
						if ( $stage !== "production" ) $target = $keys[$element + 1];

						$currPage = $stages[$stage];
						$pageNextStage = null;
						if ( !empty( $target ) ) {
							$pageNextStage = $stages[$target];
						}
						$stagingStatus = "<span style=\"color: green\">&#10003;</span>";

						if ( $stage !== "production" && ( !$pageNextStage || $this->replaceStageInternalRefs( $this->pagePrefix, $currPage->getText(), $source, $target ) !== $pageNextStage->getText() ) ) {
							$stagingStatus = "<html><a href=\"?title=Special:ContentStaging&prefix=" . $this->pagePrefix .
									"&action=copy&page=" . $currPage->getId() .
									"&source=" . $source . "&target=" . $target .
									"\" style=\"color: red;\">&#10007;</a></html>";
						}
						$resultTable .= "| style='text-align: center;' | " . $stagingStatus . "\n";
					} else {
						$resultTable .= "| \n";
					}
				}
				$resultTable .= "\n";
			}
			$resultTable .= "|}\n";
			$output->addWikiText( $resultTable );
		}
	}
	
	function getPagesByStage( $prefix, $stage = "" ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
				array( "page" ),
				array( "page_id", "page_title", "page_namespace" ),
				array( "page_namespace = " . $this->mwNamespaceIndex, "page_title LIKE '" . $prefix . "/" . $stage . "%'" )
		);

		return $res;
	}
	
	function getPagesByPrefix( $prefix ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
				array( "page" ),
				array( "page_id", "page_title", "page_namespace" ),
				array( "page_namespace = " . $this->mwNamespaceIndex, "page_title LIKE '" . $prefix . "%'" )
		);

		return $res;
	}
	
	function replaceGeneralPrefix( $title, $prefix ) {
		return str_replace( $prefix . "/", "", $title );
	}
	
	function getTitleWithoutPrefixes( $fullTitle ) {
		$arrTitle = explode( "/", $fullTitle, 3 );
		return $arrTitle[sizeof( $arrTitle ) - 1];
	}

	function getStage( $fullTitle ) {
		$arrTitle = explode( "/", $fullTitle, 3 );
		return sizeof( $arrTitle ) > 2 ? $arrTitle[sizeof( $arrTitle ) - 2] : "unstaged";
	}
	
	function replaceStageInternalRefs( $prefix, $page, $source, $target ) {
		return str_replace( $prefix . "/" . $source, $prefix . "/" . $target, $page );
	}
	
	function copyPage( $prefix, $page, $source, $target ) {
		if ( is_string( $page ) ) {
			$objSrc = WikiPage::newFromID( intval( $page ) );
		} else {
			$objSrc = $page;
		}
		$titleSrc = $objSrc->getTitle()->mTextform;
		if( $source === "" ) {
			$titleTarget = $this->mwNamespace . str_replace( $prefix . "/", $prefix . "/" . $target . "/", $titleSrc );
		} else {
			$titleTarget = $this->mwNamespace . str_replace( $prefix . "/" . $source, $prefix . "/" . $target, $titleSrc );
		}
		
		$pageContent = $objSrc->getText();
		$pageContent = $this->replaceStageInternalRefs( $prefix, $pageContent, $source, $target );

		$objTarget = WikiPage::factory ( Title::newFromText( $titleTarget ) );
		$objTarget->doEdit( $pageContent, "Staging content from " . $source . " to " . $target );
		
		return $objTarget;
	}
}
