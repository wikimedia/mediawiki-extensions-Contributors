<?php
/**
 * Special page that lists the ten most prominent contributors to an article
 *
 * @file
 * @ingroup Extensions
 * @author Rob Church <robchur@gmail.com>
 * @author Ike Hecht
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	echo ( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	exit( 1 );
}

$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'Contributors',
	'version' => '2.0',
	'author' => array( 'Rob Church', 'Ike Hecht' ),
	'descriptionmsg' => 'contributors-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Contributors',
);

$wgMessagesDirs['Contributors'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['Contributors'] = __DIR__ . '/Contributors.i18n.php';
$wgExtensionMessagesFiles['ContributorsMagic'] = __DIR__ . '/Contributors.magic.php';
$wgExtensionMessagesFiles['ContributorsAlias'] = __DIR__ . '/Contributors.alias.php';

$wgAutoloadClasses['Contributors'] = __DIR__ . '/Contributors.class.php';
$wgAutoloadClasses['SpecialContributors'] = __DIR__ . '/Contributors.page.php';
$wgAutoloadClasses['ContributorsHooks'] = __DIR__ . '/Contributors.hooks.php';
$wgSpecialPages['Contributors'] = 'SpecialContributors';

$wgHooks['ArticleDeleteComplete'][] = 'ContributorsHooks::invalidateCache';
$wgHooks['ArticleSaveComplete'][] = 'ContributorsHooks::invalidateCache';
# Good god, this is ludicrous!
$wgHooks['SkinTemplateBuildNavUrlsNav_urlsAfterPermalink'][] = 'ContributorsHooks::navigation';
$wgHooks['SkinTemplateToolboxEnd'][] = 'ContributorsHooks::toolbox';
$wgHooks['ParserFirstCallInit'][] = 'ContributorsHooks::setupParserFunction';

/**
 * Intelligent cut-off limit; see below
 */
$wgContributorsLimit = 10;

/**
 * After $wgContributorsLimit is reached, contributors with less than this
 * number of edits to a page won't be listed in normal or inclusion lists
 */
$wgContributorsThreshold = 2;

/**
 * Link the user names to their User page. Doesn't affect the Special page, which is always linked.
 */
$wgContributorsLinkUsers = true;
