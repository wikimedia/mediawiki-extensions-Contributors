<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Skin\SkinComponentUtils;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;

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
	 */
	public static function setupParserFunction( Parser $parser ) {
		$parser->setFunctionHook( 'contributors', [ __CLASS__, 'contributorsParserFunction' ],
			Parser::SFH_OBJECT_ARGS );
	}

	/**
	 * Get a simple list of contributors from a given title and (optionally) sort options.
	 *
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args The first element is the title. The remaining arguments are optional and
	 * 	correspond to sort options.
	 * @return string|array
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

		$options = [];
		foreach ( $args as $arg ) {
			$argString = trim( $frame->expand( $arg ) );
			if ( in_array( $argString, Contributors::getValidOptions() ) ) {
				$options[$argString] = true;
			}
		}

		$contributors = new Contributors( $title, $options );
		$list = $contributors->getSimpleList( $parser->getTargetLanguage() );
		return [ $list, 'noparse' => true, 'isHTML' => true ];
	}

	/**
	 * @param Skin $skin
	 * @param array &$sidebar
	 */
	public static function onSidebarBeforeOutput( Skin $skin, array &$sidebar ) {
		if ( !$skin->getTitle()->inNamespace( NS_MAIN ) || !$skin->getOutput()->getRevisionId() ) {
			return;
		}

		$toolbox = &$sidebar['TOOLBOX'];
		$insert = [
			'contributors' => [
				'text' => $skin->msg( 'contributors-toolbox' )->text(),
				'href' => SkinComponentUtils::makeSpecialUrlSubpage( 'Contributors', $skin->thispage ),
			]
		];
		if ( isset( $toolbox['permalink'] ) ) {
			$toolbox = wfArrayInsertAfter( $toolbox, $insert, 'permalink' );
		} else {
			$toolbox += $insert;
		}
	}

	/**
	 * Output the toolbox link
	 *
	 * @param BaseTemplate $monobook
	 */
	public static function onSkinTemplateToolboxEnd( BaseTemplate $monobook ) {
		if ( isset( $monobook->data['nav_urls']['contributors'] ) ) {
			if ( $monobook->data['nav_urls']['contributors']['href'] == '' ) {
				?><li id="t-iscontributors"><?php echo $monobook->msg( 'contributors-toolbox' ); ?></li><?php
			} else {
					?><li id="t-contributors"><a href="<?php echo htmlspecialchars(
					$monobook->data['nav_urls']['contributors']['href'] )
						?>"><?php
				echo $monobook->msg( 'contributors-toolbox' );
						?></a></li><?php
			}
		}
	}

	/**
	 * Add tables to Database
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'contributors', __DIR__ . '/sql/contributors.sql' );
		$updater->addExtensionField( 'contributors', 'cn_first_edit',
			__DIR__ . '/sql/contributors-add-timestamps.sql' );
		$updater->addExtensionField( 'contributors', 'cn_last_edit',
			__DIR__ . '/sql/contributors-add-timestamps.sql' );
	}

	/**
	 * Updates the contributors table with each edit made by a user to a page
	 *
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $user
	 *
	 * @throws Exception
	 */
	public static function onPageSaveComplete( WikiPage $wikiPage, UserIdentity $user ) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnection( DB_PRIMARY );
		$dbr = $lb->getConnection( DB_REPLICA );
		$pageId = $wikiPage->getId();
		$userId = $user->getId();
		$text = $user->getName();
		$timestamp = $wikiPage->getTimestamp();

		$cond = [ 'cn_page_id' => $pageId, 'cn_user_id' => $userId, 'cn_user_text' => $text ];

		$res = $dbr->select(
			'contributors',
			'cn_revision_count',
			$cond,
			__METHOD__
		);
		if ( $res->numRows() == 0 ) {
			$dbw->insert( 'contributors',
				[
					'cn_page_id' => $pageId,
					'cn_user_id' => $userId,
					'cn_user_text' => $text,
					'cn_revision_count' => 1,
					'cn_first_edit' => $timestamp
				],
				__METHOD__
			);
		} else {
			foreach ( $res as $row ) {
				$dbw->upsert( 'contributors',
					[
						'cn_page_id' => $pageId,
						'cn_user_id' => $userId,
						'cn_user_text' => $text,
						'cn_revision_count' => 1,
						'cn_first_edit' => $timestamp
					],
					[
						[ 'cn_page_id', 'cn_user_id', 'cn_user_text' ]
					],
					[
						'cn_revision_count' => $row->cn_revision_count + 1,
						'cn_last_edit' => $timestamp
					],
					__METHOD__
				);
			}
		}
	}

	/**
	 * @param Title $title
	 * @param int[] $ids
	 * @param int[][] $visibilityChangeMap
	 */
	public static function onArticleRevisionVisibilitySet(
		Title $title,
		array $ids,
		array $visibilityChangeMap
	) {
		$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		foreach ( $ids as $id ) {
			// TODO defer updates & transactions
			$revision = $revisionLookup->getRevisionById( $id );
			$user = $revision->getUser( RevisionRecord::RAW );
			$conds = [
				'cn_page_id' => $title->getArticleID(),
				'cn_user_id' => $user->getId(),
				'cn_user_text' => $user->getName()
			];

			if (
				!( $visibilityChangeMap[$id]['oldBits'] & RevisionRecord::DELETED_USER ) &&
				( $visibilityChangeMap[$id]['newBits'] & RevisionRecord::DELETED_USER )
			) {
				$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
				$row = $dbw->selectRow(
					'contributors',
					'cn_revision_count',
					$conds,
					__METHOD__
				);
				if ( $row ) {
					if ( $row->cn_revision_count == 1 ) {
						$dbw->delete(
							'contributors',
							$conds,
							__METHOD__
						);
					} else {
						$dbw->update(
							'contributors',
							[
								'cn_revision_count' => $row->cn_revision_count - 1
							],
							$conds,
							__METHOD__
						);
					}
				}
			} elseif (
				( $visibilityChangeMap[$id]['oldBits'] & RevisionRecord::DELETED_USER ) &&
				!( $visibilityChangeMap[$id]['newBits'] & RevisionRecord::DELETED_USER )
			) {
				$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
				$row = $dbw->selectRow(
					'contributors',
					'cn_revision_count',
					$conds,
					__METHOD__
				);
				if ( !$row ) {
					$dbw->insert(
						'contributors',
						array_merge( $conds, [ 'cn_revision_count' => 1 ] ),
						__METHOD__
					);
				} else {
					$dbw->update(
						'contributors',
						[
							'cn_revision_count' => $row->cn_revision_count + 1
						],
						$conds,
						__METHOD__
					);
				}
			}
		}
	}

}
