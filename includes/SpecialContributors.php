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
		$this->subpageString = $subpageString;
		$output = $this->getOutput();
		$this->setHeaders();

		$opts = $this->getOptions();
		$target = $opts['target'];
		unset( $opts['target'] );
		$this->setContributorsClass(
			new Contributors( Title::newFromText( $target ), $opts->getAllValues() ) );

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
		$opts = $this->getDefaultOptions();
		$opts->fetchValuesFromRequest( $this->getRequest() );
		// Give precedence to target parameter over subpage string
		if ( !$opts['target'] && $this->subpageString !== null ) {
			$opts['target'] = $this->subpageString;
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

		$outputHtml = $this->contributorsClass->getSimpleList( $language );
		$others = $this->contributorsClass->getNumOthers();
		if ( $others > 0 ) {
			$outputHtml .= $this->msg( 'word-separator' )->plain() . $this->msg( 'contributors-others',
					$language->formatNum( $others ) )->inContentLanguage()->text();
		}
		$output->addHTML( $outputHtml );
	}

	/**
	 * Output a machine-readable form of the raw information
	 */
	private function showRaw() {
		$output = $this->getOutput();
		$output->disable();
		$this->contributorsClass->setUseThreshold( false );
		if ( $this->contributorsClass->targetExists() ) {
			header( 'Content-type: text/plain; charset=utf-8' );
			echo $this->contributorsClass->getRawList();
		} else {
			header( 'Status: 404 Not Found', true, 404 );
			echo ( $this->msg(
				'contributors-nosuchpage', $this->contributorsClass->getTargetText() )->escaped() );
		}
	}

	private function showNormal() {
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
		$this->getOutput()->addHTML( '<h2>' . $this->msg( 'contributors-subtitle' )
				->rawParams( $link )->escaped() . '</h2>' );
		$output->addHTML( $this->contributorsClass->getNormalList( $language ) );
		$others = $this->contributorsClass->getNumOthers();
		if ( $others > 0 ) {
			$others = $language->formatNum( $others );
			$output->addWikiText( $this->msg( 'contributors-others-long', $others )->plain() );
		}
	}

	/**
	 * Make a nice little form so the user can enter a title and so forth
	 * in normal output mode
	 *
	 * @return string
	 */
	private function makeForm() {
		$opts = $this->getOptions();

		$formDescriptor = array(
			'target' => array(
				'name' => 'target',
				'label-message' => 'contributors-target',
				'type' => 'title',
				'size' => 40,
				'id'=> 'target',
				'default' => $this->contributorsClass->getTargetText()
			),
			'sortuser' => array(
				'name' => 'sortuser',
				'label-message' => 'contributors-sortuser',
				'type' => 'check',
				'checked' => $opts['sortuser']
			),
			'asc' => array(
				'name'=>'asc',
				'label-message' => 'contributors-asc',
				'type' => 'check',
				'checked' => $opts['asc']
			),

		);
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setWrapperLegendMsg( 'contributors-legend' )
			->setSubmitTextMsg( 'contributors-submit' )
			->setMethod( 'get' )
			->prepareForm()
			->displayForm( false );
	}

	protected function getGroupName() {
		return 'pages';
	}
}
