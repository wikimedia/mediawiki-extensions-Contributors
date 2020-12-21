<?php

use Wikimedia\Rdbms\IDatabase;

class ContributorsTablePager extends TablePager {

	/** @var string[]|null */
	private $fieldNames;

	/** @var int */
	private $articleId;

	/** @var array */
	private $opts;

	/** @var Title */
	private $target;

	/**
	 * @param int $articleId
	 * @param array $opts
	 * @param Title $target
	 * @param IContextSource|null $context
	 * @param IDatabase|null $readDb
	 */
	public function __construct(
		$articleId,
		array $opts,
		Title $target,
		IContextSource $context = null,
		IDatabase $readDb = null
	) {
		if ( $readDb !== null ) {
			$this->mDb = $readDb;
		}
		$this->articleId = $articleId;
		$this->opts = $opts;
		$this->target = $target;
		$this->mDefaultDirection = true;
		parent::__construct( $context );
	}

	/**
	 * @inheritDoc
	 */
	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$this->fieldNames = [
				'cn_user_text' => $this->msg( 'contributors-name' )->escaped(),
				'cn_revision_count' => $this->msg( 'contributors-revisions' )->escaped(),
				'cn_first_edit' => $this->msg( 'contributors-first-edit' )->escaped(),
				'cn_last_edit' => $this->msg( 'contributors-last-edit' )->escaped(),
			];

		}
		return $this->fieldNames;
	}

	/**
	 * @inheritDoc
	 */
	public function formatValue( $field, $value ) {
		$lang = $this->getLanguage();

		$row = $this->mCurrentRow;

		switch ( $field ) {
			case 'cn_user_text':
				$formatted =
					Linker::userLink( $row->cn_user_text, $row->cn_user_text ) . ' ' .
					Linker::userToolLinks( $row->cn_user_text, $row->cn_user_text );
				return $formatted;
			case 'cn_revision_count':
				$formatted = $lang->formatNum( $row->cn_revision_count );
				return $formatted;
			case 'cn_first_edit':
				$formatted = $lang->timeanddate( $row->cn_first_edit, true );
				return $formatted;
			case 'cn_last_edit':
				$formatted = $lang->timeanddate( $row->cn_last_edit, true );
				return $formatted;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		$dbr = wfGetDB( DB_REPLICA );
		$prefixKey = $this->target->getPrefixedDBkey();

		if ( $this->opts['filteranon'] == true ) {
			$conds = [ 'cn_page_id' => (int)$this->articleId , 'cn_user_id !=0' ];
		} elseif ( $this->opts['pagePrefix'] == true ) {
			$conds = [ 'page_title' . $dbr->buildLike( $prefixKey, $dbr->anyString() ) ];
		} else {
			$conds = [ 'cn_page_id' => (int)$this->articleId ];
		}

		$info = [
			'tables' => [ 'contributors' , 'page' ],
			'fields' => [
				'cn_user_id',
				'cn_user_text',
				'cn_first_edit',
				'cn_last_edit',
				'cn_revision_count',
				'page_title'
			],
			'conds' => $conds,
			'join_conds' =>
				[ 'page' =>
					[
						'LEFT JOIN' ,
						'page_id = cn_page_id'
					]
				]
		];
		return $info;
	}

	/**
	 * @return string
	 */
	public function getDefaultSort() {
		return 'cn_revision_count';
	}

	/**
	 * @inheritDoc
	 */
	public function isFieldSortable( $name ) {
		$sortable_fields = [ 'cn_user_text', 'cn_revision_count' ];
		return in_array( $name, $sortable_fields );
	}

}
