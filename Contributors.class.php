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
	/**
	 *
	 * @var Title
	 */
	private $target;

	/** @var FormOptions */
	private $formOptions;

	public function getTarget() {
		return $this->target;
	}

	public function getFormOptions() {
		return $this->formOptions;
	}

	public function setTarget( Title $target ) {
		return wfSetVar( $this->target, $target );
	}

	public function setFormOptions( FormOptions $formOptions ) {
		return wfSetVar( $this->formOptions, $formOptions );
	}

	/**
	 * Construct a contributors object based on title and options
	 *
	 * @param Title $target
	 * @param FormOptions $formOptions
	 */
	function __construct( Title $target = null, FormOptions $formOptions ) {
		if ( $target ) {
			$this->setTarget( $target );
		}
		$this->setFormOptions( $formOptions );
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
	public function getMainContributors() {
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
		$opts = $this->getFormOptions();

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
	public function getContributors() {
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
}
