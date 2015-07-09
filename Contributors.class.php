<?php

/**
 * Generate a contributors list for an article
 *
 * @file
 * @ingroup Extensions
 * @author Rob Church <robchur@gmail.com>
 * @author Ike Hecht
 */
class Contributors {
	/** @var Title */
	private $target;

	/** @var array */
	private $options;

	/**
	 * Array of all contributors to this page that should be displayed
	 * The array is of the form: [username] => array ( userid, numberofcontributions )
	 *
	 * @var array
	 */
	private $contributors;

	/**
	 * Should the list be split into main contributors and other contributors?
	 *
	 * @var boolean
	 */
	private $useThreshold = true;

	/**
	 * Number of other contributors. If the list is not supposed to be split, this will be 0.
	 *
	 * @var int
	 */
	private $numOthers;

	public function getTarget() {
		return $this->target;
	}

	public function getOptions() {
		return $this->options;
	}

	public function getContributors() {
		return $this->contributors;
	}

	public function getUseThreshold() {
		return $this->useThreshold;
	}

	public function getNumOthers() {
		return $this->numOthers;
	}

	public function setTarget( Title $target ) {
		return wfSetVar( $this->target, $target );
	}

	public function setOptions( array $options ) {
		return wfSetVar( $this->options, $options );
	}

	public function setContributors( $contributors ) {
		return wfSetVar( $this->contributors, $contributors );
	}

	public function setUseThreshold( $useThreshold ) {
		return wfSetVar( $this->useThreshold, $useThreshold );
	}

	public function setNumOthers( $numOthers ) {
		return wfSetVar( $this->numOthers, $numOthers );
	}

	/**
	 * Construct a contributors object based on title and options
	 * @todo $target should be required
	 *
	 * @param Title|null $target
	 * @param array $options
	 */
	function __construct( Title $target = null, array $options ) {
		$this->setOptions( $options );
		if ( $target ) {
			$this->setTarget( $target );
			$this->setContributors( $this->generateContributors() );
		}
	}

	/**
	 * Get an array of valid options
	 *
	 * @return array Numeric array of strings
	 */
	public static function getValidOptions() {
		return array( 'sortuser', 'asc' );
	}

	/**
	 * Depending on $useThreshold, generate a list of contributors that should be displayed.
	 * Also, set the number of other contributors that are not being listed.
	 *
	 * @return array Contributors sorted and ready
	 */
	private function generateContributors() {
		if ( $this->getUseThreshold() ) {
			$contributors = $this->generateThresholdedContributors();
		} else {
			$contributors = $this->generateUnthresholdedContributors();
			$this->setNumOthers( 0 );
		}
		return $this->sortContributors( $contributors );
	}

	/**
	 * Retrieve the contributors for the target page with their contribution numbers
	 * Generate all contributors, ignoring the threshold value.
	 *
	 * @return array Contributors
	 */
	private function generateUnthresholdedContributors() {
		wfProfileIn( __METHOD__ );
		global $wgMemc;
		$k = wfMemcKey( 'contributors', $this->getTarget()->getArticleID() );
		$contributors = $wgMemc->get( $k );
		if ( !$contributors ) {
			$contributors = array();
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select(
				'revision', array(
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
		wfProfileOut( __METHOD__ );
		return $contributors;
	}

	/**
	 * Retrieve all contributors for the target page worth listing, at least
	 * according to the limit and threshold defined in the configuration
	 *
	 * Also sets the number of contributors who weren't considered
	 * "important enough"
	 *
	 * @return array Contributors
	 */
	private function generateThresholdedContributors() {
		wfProfileIn( __METHOD__ );
		global $wgContributorsLimit, $wgContributorsThreshold;
		$total = 0;
		$ret = array();
		$all = $this->generateUnthresholdedContributors();
		foreach ( $all as $username => $info ) {
			list( $id, $count ) = $info;
			if ( $total >= $wgContributorsLimit && $count < $wgContributorsThreshold ) {
				break;
			}
			$ret[$username] = array( $id, $count );
			$total++;
		}
		$others = count( $all ) - count( $ret );
		$this->setNumOthers( $others );
		wfProfileOut( __METHOD__ );
		return $ret;
	}

	/**
	 * Return an array of contributors, sorted based on options
	 *
	 * @param array $contributors
	 * @return array Contributors
	 */
	private function sortContributors( $contributors ) {
		$opts = $this->getOptions();

		if ( array_key_exists( 'sortuser', $opts ) && $opts['sortuser'] ) {
			krsort( $contributors );
		}
		if ( array_key_exists( 'asc', $opts ) && $opts['asc'] ) {
			$contributors = array_reverse( $contributors );
		}
		return $contributors;
	}

	/**
	 * Get conditions for the main query
	 *
	 * @return array
	 */
	protected function getConditions() {
		$conds['rev_page'] = $this->getTarget()->getArticleID();
		$conds[] = 'rev_deleted & ' . Revision::DELETED_USER . ' = 0';
		return $conds;
	}

	/**
	 * Checks if the target is set
	 *
	 * @return boolean
	 */
	public function hasTarget() {
		return is_object( $this->getTarget() );
	}

	/**
	 * Check if the target exists. Returns false if it wasn't set or does not exist.
	 *
	 * @return boolean
	 */
	public function targetExists() {
		if ( !$this->hasTarget() ) {
			return false;
		}
		return $this->getTarget()->exists();
	}

	/**
	 * Get prefixed text for the target.
	 *
	 * @return string
	 */
	public function getTargetText() {
		if ( !$this->hasTarget() ) {
			return '';
		}
		return $this->getTarget()->getPrefixedText();
	}

	public function getContributorsNames() {
		return array_keys( $this->getContributors() );
	}

	public function getSimpleList( Language $language ) {
		global $wgContributorsLinkUsers;

		if ( $wgContributorsLinkUsers ) {
			$rawNames = $this->getContributorsNames();
			$names = array();
			foreach ( $rawNames as $rawName ) {
				$user = User::newFromName( $rawName );
				if ( $user ) {
					$names[] = Linker::userLink( $user->getId(), $user->getName() );
				} else {
					$names[] = Linker::userLink( 0, $rawName );
				}
			}
		} else {
			$names = $this->getContributorsNames();
		}

		return $language->listToText( $names );
	}

	public function getRawList() {
		$output = '';
		foreach ( $this->getContributors() as $username => $info ) {
			$count = $info[1];
			$output .= ( htmlspecialchars( "{$username} = {$count}\n" ) );
		}
		return $output;
	}

	public function getNormalList( Language $language ) {
		$listHtml = '';
		$items = $this->getNormalListItems( $language );
		foreach ( $items as $item ) {
			$listHtml .= $item . "\n";
		}
		return Html::rawElement( 'ul', array(), $listHtml );
	}

	private function getNormalListItems( Language $language ) {
		$listItems = array();
		foreach ( $this->getContributors() as $username => $info ) {
			list( $id, $count ) = $info;
			$line = Linker::userLink( $id, $username ) . Linker::userToolLinks( $id, $username );
			$line .= ' [' . $language->formatNum( $count ) . ']';
			$listItems[] = Html::rawElement( 'li', array(), $line );
		}
		return $listItems;
	}
}
