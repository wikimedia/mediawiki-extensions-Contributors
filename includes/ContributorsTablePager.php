<?php


class ContributorsTablePager extends TablePager {

	private $fieldNames;
	protected $articleId;
	protected $opts;

	public function __construct( $articleId , $opts , IContextSource $context = null, IDatabase $readDb = null ) {
		if ( $readDb !== null ) {
			$this->mDb = $readDb;
		}
		$this->articleId = $articleId;
		$this->opts = $opts;
		$this->mDefaultDirection = true;
		parent::__construct( $context );

	}

	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$this->fieldNames = array(
				'cn_user_text' => $this->msg( 'contributors-name' )->escaped(),
				'cn_revision_count' => $this->msg( 'contributors-revisions' )->escaped(),
			);

		}
		return $this->fieldNames;
	}

	public function formatValue( $field, $value ) {
		$lang = $this->getLanguage();

		$row = $this->mCurrentRow;

		switch ( $field ) {

			case 'cn_user_text':
				$formatted =
					Linker::userLink( $row->cn_user_text , $row->cn_user_text ) . ' ' .
					Linker::userToolLinks( $row->cn_user_text ,$row->cn_user_text );
				return $formatted;
				break;
			case 'cn_revision_count':
				$formatted = $lang->formatNum( $row->cn_revision_count );

				return $formatted;
				break;
		}

	}

	public function getQueryInfo() {
		if ( array_key_exists( 'filteranon', $this->opts ) && $this->opts['filteranon'] ) {
			$conds = array( 'cn_page_id' => (int)$this->articleId , 'cn_user_id !=0' );
		} else {
			$conds = array( 'cn_page_id' => (int)$this->articleId );
		}
		$info = array(
			'tables' => array( 'contributors' ),
			'fields' => array(
				'cn_user_id',
				'cn_user_text',
				'cn_revision_count'
			),
			'conds' => $conds
		);

		return $info;
	}

	public function getDefaultSort() {
		return 'cn_revision_count';
	}

	public function isFieldSortable( $name ) {
		$sortable_fields = array( 'cn_user_text', 'cn_revision_count' );
		return in_array( $name, $sortable_fields );
	}

}
