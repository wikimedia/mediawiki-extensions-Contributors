<?php

/**
 * Get the contributors list for a page
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
		}
	}

	/**
	 * Get an array of valid options
	 *
	 * @return array Numeric array of strings
	 */
	public static function getValidOptions() {
		return [ 'filteranon' ];
	}

	/**
	 * Depending on $useThreshold, get a list of contributors that should be displayed.
	 *
	 * @return array Contributors sorted and ready
	 */
	private function getContributorsData() {
		if ( $this->getUseThreshold() ) {
			$contributors = $this->getThresholdedContributors();

			return $contributors;
		}
	}

	/**
	 * Get all contributors for the target page with their contribution numbers.
	 *
	 * @return array Contributors
	 */

	private function getThresholdedContributors() {
		$dbr = wfGetDB( DB_REPLICA );
		$opts = $this->getOptions();
		$pageId = $this->getTarget()->getArticleID();
		$contributors = [];

		if ( array_key_exists( 'filteranon', $opts ) && $opts['filteranon'] ) {
			$cond = [ 'cn_page_id' => $pageId , 'cn_user_id !=0' ];
		} else {
			$cond = [ 'cn_page_id' => $pageId ];
		}
		$res = $dbr->select(
			'contributors',
			[ 'cn_user_text' , 'cn_user_id' , 'cn_revision_count' ],
			$cond,
			__METHOD__,
			[
				'GROUP BY' => 'cn_user_text',
				'ORDER BY' => 'cn_revision_count DESC',
			] );
		if ( $res && $dbr->numRows( $res ) > 0 ) {
			foreach ( $res as $row ) {
				$contributors[ $row->cn_user_text ] = [
					$row->cn_user_text,
					$row->cn_revision_count
				];
			}
		}
		return $contributors;
	}

	/**
	 * Checks if the target is set
	 *
	 * @return bool
	 */
	public function hasTarget() {
		return is_object( $this->getTarget() );
	}

	/**
	 * Check if the target exists. Returns false if it wasn't set or does not exist.
	 *
	 * @return bool
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
			$names = [];
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

}
