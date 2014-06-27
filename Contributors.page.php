<?php
/**
 * Special page class for the Contributors extension
 *
 * @file
 * @ingroup Extensions
 * @author Rob Church <robchur@gmail.com>
 * @author Ike Hecht
 */
class SpecialContributors extends IncludableSpecialPage {
	protected $target;

	/** @var string */
	protected $subpageString;

	/** @var FormOptions */
	protected $formOptions;

	public function __construct() {
		parent::__construct( 'Contributors' );
	}

	public function execute( $subpageString ) {
		wfProfileIn( __METHOD__ );
		$this->subpageString = $subpageString;
		$output = $this->getOutput();
		$this->setHeaders();

		$opts = $this->getOptions();
		$this->target = Title::newFromURL( $opts['target'] );

		# What are we doing? Different execution paths for inclusion,
		# direct access and raw access
		if ( $this->including() ) {
			$this->showInclude();
		} elseif ( $opts['action'] == 'raw' ) {
			$this->showRaw();
		} else {
			$output->addHTML( $this->makeForm() );
			if ( is_object( $this->target ) ) {
				$this->showNormal();
			}
		}

		wfProfileOut( __METHOD__ );
	}

	/**
	 * Get the current FormOptions for this request
	 * Code borrowed from ChangesListSpecialPage
	 *
	 * @return FormOptions
	 */
	protected function getOptions() {
		if ( $this->formOptions === null ) {
			$this->formOptions = $this->setup();
		}

		return $this->formOptions;
	}

	/**
	 * Create a FormOptions object with options as specified by the user
	 *
	 * @return FormOptions
	 */
	protected function setup() {
		$parameters = $this->subpageString;

		$opts = $this->getDefaultOptions();
		$opts->fetchValuesFromRequest( $this->getRequest() );
		// Give precedence to subpage syntax
		if ( $parameters !== null ) {
			$this->parseParameters( $parameters, $opts );
		}

		return $opts;
	}

	/**
	 * Get a FormOptions object containing the default options.
	 *
	 * @return FormOptions
	 */
	protected function getDefaultOptions() {
		$opts = new FormOptions();

		$opts->add( 'target', '', FormOptions::STRING );
		$opts->add( 'sortuser', false );
		$opts->add( 'asc', false );
		$opts->add( 'action', 'view', FormOptions::STRING );

		return $opts;
	}

	private function showInclude() {
		wfProfileIn( __METHOD__ );
		$output = $this->getOutput();
		$language = $this->getLanguage();

		if ( is_object( $this->target ) ) {
			if ( $this->target->exists() ) {
				$names = array();
				list( $contributors, $others ) = $this->getMainContributors();
				foreach ( $contributors as $username => $info ) {
					$names[] = $username;
				}
				$outputHtml = $language->listToText( $names );
				if ( $others > 0 ) {
					$outputHtml .= wgMsgForContent( 'word-separator' ) . $this->msg( 'contributors-others',
							$language->formatNum( $others ) )->inContentLanguage()->text();
				}
				$output->addHTML( htmlspecialchars( $outputHtml ) );
			} else {
				$output->addHTML( '<p>' . $this->msg( 'contributors-nosuchpage',
						$this->target->getPrefixedText() )->inContentLanguage()->escaped() . '</p>' );
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
			list( $contributors, $others ) = $this->getMainContributors( $this->target );
			$output->addHTML( '<ul>' );
			foreach ( $contributors as $username => $info ) {
				list( $id, $count ) = $info;
				$line = Linker::userLink( $id, $username ) . Linker::userToolLinks( $id,
						$username );
				$line .= ' [' . $language->formatNum( $count ) . ']';
				$output->addHTML( '<li>' . $line . '</li>' );
			}
			$output->addHTML( '</ul>' );
			if ( $others > 0 ) {
				$others = $language->formatNum( $others );
				$output->addWikiText( $this->msg( 'contributors-others-long', $others )->plain() );
			}
		} else {
			$output->addHTML( '<p>' . $this->msg( 'contributors-nosuchpage',
					$this->target->getPrefixedText() )->escaped() . '</p>' );
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
	private function getMainContributors() {
		wfProfileIn( __METHOD__ );
		global $wgContributorsLimit, $wgContributorsThreshold;
		$total = 0;
		$ret = array();
		$all = $this->getContributors();
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
	 * Return an array of contributors, sorted based on options
	 * 
	 * @param array $contributors
	 * @return array 
	 */
	private function sortContributors( $contributors ) {
		$opts = $this->getOptions();

		if ( $opts['sortuser'] ) {
			krsort( $contributors );
		}
		if ( $opts['asc'] ) {
			$contributors = array_reverse( $contributors );
		}
		return $contributors;
	}

	/**
	 * Retrieve the contributors for the target page with their contribution numbers
	 *
	 * @return array
	 */
	private function getContributors() {
		wfProfileIn( __METHOD__ );
		global $wgMemc;
		$k = wfMemcKey( 'contributors', $this->target->getArticleID() );
		$contributors = $wgMemc->get( $k );
		if ( !$contributors ) {
			$contributors = array();
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				'revision',
				array(
				'COUNT(*) AS count',
				'rev_user',
				'rev_user_text',
				), $this->getConditions(), __METHOD__,
				array(
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
		$contributors = $this->sortContributors( $contributors );
		wfProfileOut( __METHOD__ );
		return $contributors;
	}

	/**
	 * Get conditions for the main query
	 *
	 * @return array
	 */
	protected function getConditions() {
		$conds['rev_page'] = $this->target->getArticleID();
		$conds[] = 'rev_deleted & ' . Revision::DELETED_USER . ' = 0';
		return $conds;
	}

	/**
	 * Make a nice little form so the user can enter a title and so forth
	 * in normal output mode
	 *
	 * @return string
	 */
	private function makeForm() {
		global $wgScript;
		$opts = $this->getOptions();
		$self = self::getTitleFor( 'Contributors' );
		$target = is_object( $this->target ) ? $this->target->getPrefixedText() : '';
		$form = '<form method="get" action="' . htmlspecialchars( $wgScript ) . '">';
		$form .= Html::Hidden( 'title', $self->getPrefixedText() );
		$form .= '<fieldset><legend>' . $this->msg( 'contributors-legend' ) . '</legend>';
		$form .= '<label for="target">' . $this->msg( 'contributors-target' ) . '</label>';
		$form .= Xml::input( 'target', 40, $target, array( 'id' => 'target' ) );
		$form .= '&#160;';
		$form .= Xml::checkLabel(
				$this->msg( 'contributors-asc' )->text(), 'asc', 'asc', $opts['asc']
		);
		$form .= '&#160;';
		$form .= Xml::checkLabel(
				$this->msg( 'contributors-sortuser' )->text(), 'sortuser', 'sortuser',
				$opts['sortuser']
		);
		$form .= Xml::element( 'br' );
		$form .= Xml::submitButton( $this->msg( 'contributors-submit' )->text() );
		$form .= '</fieldset>';
		$form .= '</form>';
		return $form;
	}

	/**
	 * Process $par and put options found in $opts. Used when including the page.
	 *
	 * @param string $par
	 * @param FormOptions $opts
	 */
	public function parseParameters( $par, FormOptions $opts ) {
		$bits = preg_split( '/\s*,\s*/', trim( $par ) );

		//?target=SomePageName overrides value set with Special:Contributors/OtherPageName
		$target = array_shift( $bits );
		if ( !$opts['target'] ) {
			$opts['target'] = $target;
		}

		foreach ( $bits as $bit ) {
			if ( 'sortuser' === $bit ) {
				$opts['sortuser'] = true;
			}
			if ( 'asc' === $bit ) {
				$opts['asc'] = true;
			}
		}
	}
}
