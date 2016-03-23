<?php

/**
 * Hooks for Contributors
 *
 * @author Ike Hecht
 */
class ContributorsHooks {

	/**
	 * Set up the #contributors parser function
	 *
	 * @param Parser $parser
	 *
	 * @return bool
	 */
	public static function setupParserFunction( Parser &$parser ) {
		$parser->setFunctionHook( 'contributors', __CLASS__ . '::contributorsParserFunction',
			Parser::SFH_OBJECT_ARGS );

		return true;
	}

	/**
	 * Get a simple list of contributors from a given title and (optionally) sort options.
	 *
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args The first element is the title. The remaining arguments are optional and
	 * 	correspond to sort options.
	 * @return string
	 */
	public static function contributorsParserFunction( Parser $parser, PPFrame $frame, array $args ) {
		$title = Title::newFromText( $frame->expand( array_shift( $args ) ) );
		if ( !$title ) {
			/** @todo This should perhaps return a <strong class="error"> message */
			return wfMessage( 'contributors-badtitle' )->inContentLanguage()->parseAsBlock();
		}
		if ( !$title->exists() ) {
			return wfMessage( 'contributors-nosuchpage', $title->getText() )->
					inContentLanguage()->parseAsBlock();
		}

		$options = array();
		foreach ( $args as $arg ) {
			$argString = trim( $frame->expand( $arg ) );
			if ( in_array( $argString, Contributors::getValidOptions() ) ) {
				$options[$argString] = true;
			}
		}

		$contributors = new Contributors( $title, $options );
		$list = $contributors->getSimpleList( $parser->getFunctionLang() );
		return array( $list, 'noparse' => true, 'isHTML' => true );
	}

	/**
	 * Invalidate the cache we saved for a given title
	 *
	 * @param $article Article object that changed
	 *
	 * @return bool
	 */
	public static function invalidateCache( &$article ) {
		global $wgMemc;
		$wgMemc->delete( wfMemcKey( 'contributors', $article->getId() ) );

		return true;
	}

	/**
	 * Prepare the toolbox link
	 *
	 * @var $skintemplate SkinTemplate
	 *
	 * @return bool
	 */
	public static function navigation( &$skintemplate, &$nav_urls, &$oldid, &$revid ) {
		if ( $skintemplate->getTitle()->getNamespace() === NS_MAIN && $revid !== 0 ) {
			$nav_urls['contributors'] = array(
				'text' => $skintemplate->msg( 'contributors-toolbox' ),
				'href' => $skintemplate->makeSpecialUrl(
					'Contributors/' . wfUrlencode( $skintemplate->thispage )
				),
			);
		}
		return true;
	}

	/**
	 * Output the toolbox link
	 *
	 * @return bool
	 */
	public static function toolbox( &$monobook ) {
		if ( isset( $monobook->data['nav_urls']['contributors'] ) ) {
			if ( $monobook->data['nav_urls']['contributors']['href'] == '' ) {
				?><li id="t-iscontributors"><?php echo $monobook->msg( 'contributors-toolbox' ); ?></li><?php
			} else {
					?><li id="t-contributors"><?php ?><a href="<?php echo htmlspecialchars(
					$monobook->data['nav_urls']['contributors']['href'] )
						?>"><?php
				echo $monobook->msg( 'contributors-toolbox' );
						?></a><?php ?></li><?php
			}
		}
		return true;
	}
}
