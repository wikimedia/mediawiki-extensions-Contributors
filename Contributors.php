<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Contributors' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Contributors'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['ContributorsAlias'] = __DIR__ . '/Contributors.alias.php';
	$wgExtensionMessagesFiles['ContributorsMagic'] = __DIR__ . '/Contributors.magic.php';
	/* wfWarn(
		'Deprecated PHP entry point used for Contributors extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the Contributors extension requires MediaWiki 1.25+' );
}
