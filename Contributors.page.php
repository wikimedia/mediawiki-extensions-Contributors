<?php

/**
 * Special page class for the Contributors extension
 *
 * @file
 * @ingroup Extensions
 * @author Rob Church <robchur@gmail.com>
 */
class SpecialContributors extends IncludableSpecialPage {

	protected $target;

	public function __construct() {
		parent::__construct( 'Contributors' );
	}

	public function execute( $target ) {
		wfProfileIn( __METHOD__ );
		$output = $this->getOutput();
		$request = $this->getRequest();
		$this->setHeaders();
		$this->determineTarget( $request, $target );

		# What are we doing? Different execution paths for inclusion,
		# direct access and raw access
		if ( $this->including() ) {
			$this->showInclude();
		} elseif ( $request->getText( 'action' ) == 'raw' ) {
			$this->showRaw();
		} else {
			$output->addHTML( $this->makeForm() );
			if ( is_object( $this->target ) ) {
				$this->showNormal();
			}
		}

		wfProfileOut( __METHOD__ );
	}

	private function showInclude() {
		wfProfileIn( __METHOD__ );
		$output = $this->getOutput();
		$language = $this->getLanguage();

		if ( is_object( $this->target ) ) {
			if ( $this->target->exists() ) {
				$names = array();
				list( $contributors, $others ) = self::getMainContributors( $this->target );
				foreach ( $contributors as $username => $info ) {
					$names[] = $username;
				}
				$outputHtml = $language->listToText( $names );
				if ( $others > 0 ) {
					$outputHtml .= wgMsgForContent( 'word-separator' ) . $this->msg( 'contributors-others', $language->formatNum( $others ) )->inContentLanguage()->text();
				}
				$output->addHTML( htmlspecialchars( $outputHtml ) );
			} else {
				$output->addHTML( '<p>' . $this->msg( 'contributors-nosuchpage', $this->target->getPrefixedText() )->inContentLanguage()->escaped() . '</p>' );
			}
		} else {
			$output->addHTML( '<p>' . $this->msg( 'contributors-badtitle' )->inContentLanguage()->escaped() . '</p>' );
		}
		wfProfileOut( __METHOD__ );
	}

	/**
	 * Output a machine-readable form of the raw information
	 */
	private function showRaw() {
		wfProfileIn( __METHOD__ );
		$output = $this->getOutput();
		$output->disable();
		if ( is_object( $this->target ) && $this->target->exists() ) {
			foreach ( $this->getContributors() as $username => $info ) {
				list( $userid, $count ) = $info;
				header( 'Content-type: text/plain; charset=utf-8' );
				echo( htmlspecialchars( "{$username} = {$count}\n" ) );
			}
		} else {
			header( 'Status: 404 Not Found', true, 404 );
			echo( 'The requested target page does not exist.' );
		}
		wfProfileOut( __METHOD__ );
	}

	private function showNormal() {
		wfProfileIn( __METHOD__ );
		$language = $this->getLanguage();
		$output = $this->getOutput();
		if ( $this->target->exists() ) {
			$link = Linker::linkKnown( $this->target );
			$this->getOutput()->addHTML( '<h2>' . $this->msg( 'contributors-subtitle' )->rawParams( $link )->escaped() . '</h2>' );
			list( $contributors, $others ) = self::getMainContributors( $this->target );
			$output->addHTML( '<ul>' );
			foreach ( $contributors as $username => $info ) {
				list( $id, $count ) = $info;
				$line = Linker::userLink( $id, $username ) . Linker::userToolLinks( $id, $username );
				$line .= ' [' . $language->formatNum( $count ) . ']';
				$output->addHTML( '<li>' . $line . '</li>' );
			}
			$output->addHTML( '</ul>' );
			if ( $others > 0 ) {
				$others = $language->formatNum( $others );
				$output->addWikiText( $this->msg( 'contributors-others-long', $others )->plain() );
			}
		} else {
			$output->addHTML( '<p>' . $this->msg( 'contributors-nosuchpage', $this->target->getPrefixedText() )->escaped() . '</p>' );
		}
		wfProfileOut( __METHOD__ );
	}

	/**
	 * Retrieve all contributors for the target page worth listing, at least
	 * according to the limit and threshold defined in the configuration
	 *
	 * Also returns the number of contributors who weren't considered
	 * "important enough"
	 *
	 * @return array
	 */
	public static function getMainContributors( $title ) {
		wfProfileIn( __METHOD__ );
		global $wgContributorsLimit, $wgContributorsThreshold;
		$total = 0;
		$ret = array();
		$all = self::getContributors( $title );
		foreach ( $all as $username => $info ) {
			list( $id, $count ) = $info;
			if ( $total >= $wgContributorsLimit && $count < $wgContributorsThreshold ) {
				break;
			}
			$ret[$username] = array( $id, $count );
			$total++;
		}
		$others = count( $all ) - count( $ret );
		wfProfileOut( __METHOD__ );
		return array( $ret, $others );
	}

	/**
	 * Retrieve the contributors for the target page with their contribution numbers
	 *
	 * @return array
	 */
	public static function getContributors( $title ) {
		wfProfileIn( __METHOD__ );
		global $wgMemc;
		$k = wfMemcKey( 'contributors', $title->getArticleID() );
		$contributors = $wgMemc->get( $k );
		if ( !$contributors ) {
			$contributors = array();
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				'revision', array(
					'COUNT(*) AS count',
					'rev_user',
					'rev_user_text',
				), self::getConditions( $title ), __METHOD__, array(
					'GROUP BY' => 'rev_user_text',
					'ORDER BY' => 'count DESC',
				)
			);
			if ( $res && $dbr->numRows( $res ) > 0 ) {
				while ( $row = $dbr->fetchObject( $res ) ) {
					$contributors[$row->rev_user_text] = array( $row->rev_user, $row->count );
				}
			}
			$wgMemc->set( $k, $contributors, 84600 );
		}
		wfProfileOut( __METHOD__ );
		return $contributors;
	}

	/**
	 * Get conditions for the main query
	 *
	 * @return array
	 */
	protected static function getConditions( $title ) {
		$conds['rev_page'] = $title->getArticleID();
		$conds[] = 'rev_deleted & ' . Revision::DELETED_USER . ' = 0';
		return $conds;
	}

	/**
	 * Given the web request, and a possible override from a subpage, work
	 * out which we want to use
	 *
	 * @param $request WebRequest we're serving
	 * @param $override Possible subpage override
	 * @return string
	 */
	private function determineTarget( &$request, $override ) {
		$target = $request->getText( 'target', $override );
		$this->target = Title::newFromURL( $target );
	}

	/**
	 * Make a nice little form so the user can enter a title and so forth
	 * in normal output mode
	 *
	 * @return string
	 */
	private function makeForm() {
		global $wgScript;
		$self = parent::getTitleFor( 'Contributors' );
		$target = is_object( $this->target ) ? $this->target->getPrefixedText() : '';
		$form = '<form method="get" action="' . htmlspecialchars( $wgScript ) . '">';
		$form .= Html::Hidden( 'title', $self->getPrefixedText() );
		$form .= '<fieldset><legend>' . $this->msg( 'contributors-legend' ) . '</legend>';
		$form .= '<table><tr>';
		$form .= '<td><label for="target">' . $this->msg( 'contributors-target' ) . '</label></td>';
		$form .= '<td>' . Xml::input( 'target', 40, $target, array( 'id' => 'target' ) ) . '</td>';
		$form .= '</tr><tr>';
		$form .= '<td>&#160;</td>';
		$form .= '<td>' . Xml::submitButton( $this->msg( 'contributors-submit' )->text() ) . '</td>';
		$form .= '</tr></table>';
		$form .= '</fieldset>';
		$form .= '</form>';
		return $form;
	}

}
