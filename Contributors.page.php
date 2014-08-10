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
	/** @var string */
	protected $subpageString;

	/** @var FormOptions */
	protected $formOptions;

	/** @var Contributors */
	protected $contributorsClass;

	public function __construct() {
		parent::__construct( 'Contributors' );
	}

	public function execute( $subpageString ) {
		wfProfileIn( __METHOD__ );
		$this->subpageString = $subpageString;
		$output = $this->getOutput();
		$this->setHeaders();

		$opts = $this->getOptions();
		$this->contributorsClass = new Contributors( Title::newFromURL( $opts['target'] ), $opts );

		# What are we doing? Different execution paths for inclusion,
		# direct access and raw access
		if ( $this->including() ) {
			$this->showInclude();
		} elseif ( $opts['action'] == 'raw' ) {
			$this->showRaw();
		} else {
			$output->addHTML( $this->makeForm() );
			$this->showNormal();
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

		if ( !$this->contributorsClass->hasTarget() ) {
			$output->addHTML( $this->msg( 'contributors-badtitle' )->inContentLanguage()->parseAsBlock() );
			return;
		}
		if ( !$this->contributorsClass->targetExists() ) {
			$output->addHTML( $this->msg( 'contributors-nosuchpage',
					$this->contributorsClass->getTargetText() )->inContentLanguage()->parseAsBlock() );
			return;
		}

		$names = array();
		list( $contributors, $others ) = $this->contributorsClass->getContributors( false );
		foreach ( $contributors as $username => $info ) {
			$names[] = $username;
		}
		$outputHtml = $language->listToText( $names );
		if ( $others > 0 ) {
			$outputHtml .= $this->msg( 'word-separator' )->plain() . $this->msg( 'contributors-others',
					$language->formatNum( $others ) )->inContentLanguage()->text();
		}
		$output->addHTML( htmlspecialchars( $outputHtml ) );

		wfProfileOut( __METHOD__ );
	}

	/**
	 * Output a machine-readable form of the raw information
	 */
	private function showRaw() {
		wfProfileIn( __METHOD__ );
		$output = $this->getOutput();
		$output->disable();
		if ( $this->contributorsClass->targetExists() ) {
			foreach ( $this->contributorsClass->getContributors() as $username => $info ) {
				list( $userid, $count ) = $info;
				header( 'Content-type: text/plain; charset=utf-8' );
				echo( htmlspecialchars( "{$username} = {$count}\n" ) );
			}
		} else {
			header( 'Status: 404 Not Found', true, 404 );
			echo( $this->msg( 'contributors-nosuchpage', $this->contributorsClass->getTargetText() )->escaped() );
		}
		wfProfileOut( __METHOD__ );
	}

	private function showNormal() {
		wfProfileIn( __METHOD__ );
		$language = $this->getLanguage();
		$output = $this->getOutput();
		if ( !$this->contributorsClass->hasTarget() ) {
			return;
		}
		if ( !$this->contributorsClass->targetExists() ) {
			$output->addHTML( $this->msg( 'contributors-nosuchpage',
					$this->contributorsClass->getTargetText() )->parseAsBlock() );
			return;
		}

		$link = Linker::linkKnown( $this->contributorsClass->getTarget() );
		$this->getOutput()->addHTML( '<h2>' . $this->msg( 'contributors-subtitle' )->rawParams( $link )->escaped() . '</h2>' );
		list( $contributors, $others ) = $this->contributorsClass->getContributors( false );
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

		wfProfileOut( __METHOD__ );
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
		$form = '<form method="get" action="' . htmlspecialchars( $wgScript ) . '">';
		$form .= Html::Hidden( 'title', $this->getPageTitle()->getPrefixedText() );
		$form .= '<fieldset><legend>' . $this->msg( 'contributors-legend' ) . '</legend>';
		$form .= '<label for="target">' . $this->msg( 'contributors-target' ) . '</label>';
		$form .= Xml::input( 'target', 40, $this->contributorsClass->getTargetText(),
				array( 'id' => 'target' ) );
		$form .= '&#160;';
		$form .= Xml::checkLabel(
				$this->msg( 'contributors-asc' )->text(), 'asc', 'asc', $opts['asc']
		);
		$form .= '&#160;';
		$form .= Xml::checkLabel(
				$this->msg( 'contributors-sortuser' )->text(), 'sortuser', 'sortuser', $opts['sortuser']
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

	protected function getGroupName() {
		return 'pages';
	}
}
