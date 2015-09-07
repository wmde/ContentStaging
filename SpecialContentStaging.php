<?php

class SpecialContentStaging extends SpecialPage {

	private $mwNamespaceIndex;
	private $mwNamespace;
	private $pagePrefix;
	private $stages;
	private $archiveMarker = "\n<noinclude>[[Category:ContentStagingArchive]]</noinclude>";

	public function __construct() {
		global $wgContentStagingPrefix, $wgContentStagingNamespace, $wgContentStagingStages;

		$this->mwNamespaceIndex = isset( $wgContentStagingNamespace ) ? ( $wgContentStagingNamespace ) : 0;
		$this->mwNamespace = MWNamespace::getCanonicalName( $this->mwNamespaceIndex ) . ":";
		$this->pagePrefix = isset( $wgContentStagingPrefix ) ? $wgContentStagingPrefix : "CMS";
		# TODO: make the special page respect user defined names and number of stages
		$this->stages = isset( $wgContentStagingStages ) ? $wgContentStagingStages : array( "test" => 0, "stage" => 0, "production" => 0 );

		parent::__construct( 'ContentStaging', 'edit', true, false, 'default', false );
	}

	public function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

		$action = $request->getText( 'action' );
		$page = $request->getText( 'page' );
		$currStage = $request->getText( 'source' );
		$targetStage = $request->getText( 'target' );
		$showArchived = $request->getBool( 'showArchived' );

		$baseUrl = '?title=Special:ContentStaging';
		$baseUrl .= $showArchived ? '&showArchived=1' : '';

		if ( !empty( $this->pagePrefix ) ) {
			if ( $action === 'copy' ) {
				$this->copyPage(
					$this->pagePrefix,
					WikiPage::newFromID( $page ),
					$currStage,
					$targetStage
				);
			}
			if ( $action === 'archive' ) {
				$this->archivePage( $page );
			}
			if ( $action === 'restore' ) {
				$this->restorePage( $page );
			}

			$pages = array();
			$allPages = $this->getPagesByPrefix( $this->pagePrefix );
			foreach ( $allPages as $page ) {
				$stage = $this->getStage( $page->page_title );
				$title = $this->getTitleWithoutPrefixes( $page->page_title );

				$wikiPage = WikiPage::newFromID( $page->page_id );

				if ( $this->shouldPageShow( $wikiPage, $showArchived ) ) {
					if ( !array_key_exists( $title, $pages ) ) {
						$pages[$title] = $this->stages;
					}
					$pages[$title][$stage] = $wikiPage;
				}

				if ( $action === "stageall" && $currStage === $stage ) {
					$pages[$title][$targetStage] = $this->copyPage( $this->pagePrefix, $wikiPage, $currStage, $targetStage );
				}
			}

			$resultTable = "{| class=\"wikitable sortable\" border=\"1\"\n";
			$resultTable .= "|-\n";
			$resultTable .= "! Title\n";
			$resultTable .= "! Test <html><br /><a href='" . $baseUrl . "&action=stageall&source=test&target=stage'>Stage all</a></html>\n";
			$resultTable .= "! Stage <html><br /><a href='" . $baseUrl . "&action=stageall&source=stage&target=production'>Stage all</a></html>\n";
			$resultTable .= "! Production\n";
			$resultTable .= "! Options\n";

			foreach ( $pages as $title => $stages ) {
				$resultTable .= "|-\n";
				$resultTable .= "| [[" . $this->mwNamespace . $this->pagePrefix . "/test/" . $title . " | " . $title . "]]\n";

				foreach ( array_keys( $this->stages ) as $stage ) {
					if ( $this->wikiPageExists( $stages[$stage] ) ) {
						$currStage = $stage;
						$targetStage = '';

						$keys = array_keys( $stages );
						$element = array_search( $currStage, $keys );
						if ( $stage !== "production" ) {
							$targetStage = $keys[$element + 1];
							$targetPage = $stages[$targetStage];
						}

						$currPage = $stages[$stage];

						$stagingStatus = '<span style="color: green">&#10003;</span>';
						if ( $stage !== "production" && ( !$this->wikiPageExists( $targetPage ) || $this->stageContentDiffers( $currPage, $targetPage, $currStage, $targetStage ) ) ) {
							$stagingStatus = '<html><a href="' . $baseUrl .
								'&action=copy&page=' . $currPage->getId() .
								'&source=' . $currStage .
								'&target=' . $targetStage . '" style="color: red;">&#10007;</a></html>';
						}

						$resultTable .= "| style='text-align: center;' | " . $stagingStatus . "\n";
					} else {
						$resultTable .= "| \n";
					}
				}

				if ( !$showArchived ) {
					$archiveOption = '<html><a href="' . $baseUrl .
						'&action=archive&page=' . $title .
						'" style="font-weight:bold">&#128448;</a></html>';
				} else {
					$archiveOption = '<html><a href="' . $baseUrl .
						'&action=restore&page=' . $title .
						'" style="font-weight:bold">&#128449;</a></html>';
				}

				$resultTable .= "| style='text-align: center;' | " . $archiveOption . "\n";
			}
			$resultTable .= "|}\n";

			if ( !$showArchived ) {
				$archiveLink = '<html><a href="?title=Special:ContentStaging&showArchived=1">&#128448; View Archive</a></html>';
			} else {
				$archiveLink = '<html><a href="?title=Special:ContentStaging">&#128449; View List</a></html>';
			}

			$output->addWikiText( $archiveLink . "\n" . $resultTable );
		}
	}

	/**
	 * @param string $prefix
	 * @param string $stage
	 * @return bool|ResultWrapper
	 */
	private function getPagesByStage( $prefix, $stage = "" ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array( 'page' ),
			array( 'page_id', 'page_title', 'page_namespace' ),
			array( 'page_namespace = ' . $this->mwNamespaceIndex, 'page_title LIKE "' . $prefix . '/' . $stage . '%"' )
		);

		return $res;
	}

	/**
	 * @param string $prefix
	 * @return bool|ResultWrapper
	 */
	private function getPagesByPrefix( $prefix ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			array( 'page' ),
			array( 'page_id', 'page_title', 'page_namespace' ),
			array( 'page_namespace = ' . $this->mwNamespaceIndex, 'page_title LIKE "' . $prefix . '%"' )
		);

		return $res;
	}

	/**
	 * @param string $fullTitle
	 * @return string
	 */
	private function getTitleWithoutPrefixes( $fullTitle ) {
		$arrTitle = explode( '/', $fullTitle, 3 );
		return $arrTitle[count( $arrTitle ) - 1];
	}

	/**
	 * @param string $fullTitle
	 * @return string
	 */
	private function getStage( $fullTitle ) {
		$arrTitle = explode( '/', $fullTitle, 3 );
		return count( $arrTitle ) > 2 ? $arrTitle[count( $arrTitle ) - 2] : 'unstaged';
	}

	/**
	 * @param string $prefix
	 * @param string $page
	 * @param string $source
	 * @param string $target
	 * @return string
	 */
	private function replaceStageInternalRefs( $prefix, $page, $source, $target ) {
		return str_replace( $prefix . '/' . $source, $prefix . '/' . $target, $page );
	}

	/**
	 * @param string $prefix
	 * @param WikiPage $sourcePage
	 * @param string $source
	 * @param string $target
	 * @return WikiPage
	 * @throws MWException
	 */
	private function copyPage( $prefix, WikiPage $sourcePage, $source, $target ) {
		$titleSrc = $sourcePage->getTitle()->mTextform;
		if ( $source === '' ) {
			$titleTarget = $this->mwNamespace . str_replace( $prefix . '/', $prefix . '/' . $target . '/', $titleSrc );
		} else {
			$titleTarget = $this->mwNamespace . str_replace( $prefix . '/' . $source, $prefix . '/' . $target, $titleSrc );
		}

		$pageContent = $sourcePage->getContent()->getNativeData();
		$pageContent = $this->replaceStageInternalRefs( $prefix, $pageContent, $source, $target );

		$targetPage = WikiPage::factory( Title::newFromText( $titleTarget ) );
		$targetPage->doEditContent( new WikitextContent( $pageContent ), 'Staging content from ' . $source . ' to ' . $target );

		return $targetPage;
	}

	/**
	 * @param WikiPage $sourceWikiPage
	 * @param WikiPage $targetWikiPage
	 * @param string $sourceStage
	 * @param string $targetStage
	 * @return bool
	 */
	private function stageContentDiffers( WikiPage $sourceWikiPage, WikiPage $targetWikiPage, $sourceStage, $targetStage ) {
		$sourceText = $sourceWikiPage->getContent()->getNativeData();
		$targetText = $targetWikiPage->getContent()->getNativeData();

		return $this->replaceStageInternalRefs( $this->pagePrefix, $sourceText, $sourceStage, $targetStage ) !== $targetText;
	}

	/**
	 * @param $wikiPage
	 * @return bool
	 */
	private function wikiPageExists( $wikiPage ) {
		return get_class( $wikiPage ) === 'WikiPage';
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param bool $showArchived
	 * @return bool
	 */
	private function shouldPageShow( WikiPage $wikiPage, $showArchived ) {
		return $this->isArchivedPage( $wikiPage ) xor !$showArchived;
	}

	/**
	 * @param string $title
	 * @throws MWException
	 */
	private function archivePage( $title ) {
		foreach ( $this->stages as $stage => $number ) {
			$archiveTitle = $this->mwNamespace . $this->pagePrefix . '/' . $stage . '/' . $title;
			$archivePage = WikiPage::factory( Title::newFromText( $archiveTitle ) );
			$this->doArchivePage( $archivePage );
		}
	}

	/**
	 * @param WikiPage $page
	 * @return bool
	 * @throws MWException
	 */
	private function doArchivePage( WikiPage $page ) {
		$oldContent = $page->getContent();

		if ( $oldContent === null || $this->isArchivedPage( $page ) ) {
			return false;
		}

		$text = $oldContent->getNativeData();

		$text .= $this->archiveMarker;
		$page->doEditContent( new WikitextContent( $text ), 'archived by ContentStaging' );

		return true;
	}

	/**
	 * @param string $title
	 * @throws MWException
	 */
	private function restorePage( $title ) {
		foreach ( $this->stages as $stage => $number ) {
			$archiveTitle = $this->mwNamespace . $this->pagePrefix . '/' . $stage . '/' . $title;
			$archivePage = WikiPage::factory( Title::newFromText( $archiveTitle ) );
			$this->doRestorePage( $archivePage );
		}
	}

	/**
	 * @param WikiPage $page
	 * @return bool
	 * @throws MWException
	 */
	private function doRestorePage( WikiPage $page ) {
		$oldContent = $page->getContent();
		if ( $oldContent === null || !$this->isArchivedPage( $page ) ) {
			return false;
		}

		$text = $oldContent->getNativeData();

		$text = str_replace( $this->archiveMarker, '', $text );
		$page->doEditContent( new WikitextContent( $text ), 'restored by ContentStaging' );

		return true;
	}

	/**
	 * @param WikiPage $page
	 * @return bool
	 */
	private function isArchivedPage( WikiPage $page ) {
		$currCategories = $page->getCategories();
		foreach ( $currCategories as $category ) {
			if ( $category->getText() === 'ContentStagingArchive' ) {
				return true;
			}
		}
		return false;
	}
}
