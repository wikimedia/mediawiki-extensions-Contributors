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

	public function setContributorsClass( Contributors $contributorsClass ) {
		return wfSetVar( $this->contributorsClass, $contributorsClass );
	}

	public function __construct() {
		parent::__construct( 'Contributors' );
	}

	public function execute( $subpageString ) {
		wfProfileIn( __METHOD__ );
		$this->subpageString = $subpageString;
		$output = $this->getOutput();
		$this->setHeaders();

		$opts = $this->getOptions();
		$this->setContributorsClass(
			new Contributors( Title::newFromURL( $opts['target'] ), $opts ) );

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

		$outputHtml = $this->contributorsClass->getIncludeList( $language );
		$others = $this->contributorsClass->getNumOthers();
		if ( $others > 0 ) {
			$outputHtml .= $this->msg( 'word-separator' )->plain() . $this->msg( 'contributors-others',
					$language->formatNum( $others ) )->inContentLanguage()->text();
		}
		$output->addHTML( $outputHtml );

		wfProfileOut( __METHOD__ );
	}

	/**
	 * Output a machine-readable form of the raw information
	 */
	private function showRaw() {
		wfProfileIn( __METHOD__ );
		$output = $this->getOutput();
		$output->disable();
		$this->contributorsClass->setUseThreshold( false );
		if ( $this->contributorsClass->targetExists() ) {
			header( 'Content-type: text/plain; charset=utf-8' );
			echo $this->contributorsClass->getRawList();
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
		$output->addHTML( $this->contributorsClass->getNormalList( $language ) );
		$others = $this->contributorsClass->getNumOthers();
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
		$validParams = array( 'sortuser', 'asc' );
		$bits = preg_split( '/\s*,\s*/', trim( $par ) );

		//?target=SomePageName overrides value set with Special:Contributors/OtherPageName
		$target = array_shift( $bits );

		/** @todo this is a bit sloppy */
		/**
		 * @todo if the title has a valid parameter in its name and that is the only text after
		 * a comma, that part of the title will erroneously be assumed to be a parameter. In other
		 * words, where a page title is equal to "b,asc", {{Special:Contributors/b,asc}} will produce
		 * a contributors list for the page whose title is "b", in ascending order.
		 */
		$foundValidParam = false;
		foreach ( $bits as $bit ) {
			if ( in_array( $bit, $validParams ) ) {
				$foundValidParam = true;
				$opts[$bit] = true;
			} elseif ( !$foundValidParam ) {
				//If we haven't reached a valid parameter yet and this is not a valid parameter,
				//it's probably a part of the title - i.e. the title has a comma in it.
				$target .= ',' . $bit;
			}
		}

		if ( !$opts['target'] ) {
			$opts['target'] = $target;
		}
	}

	protected function getGroupName() {
		return 'pages';
	}
}
