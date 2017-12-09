<?php

class ApiQueryCnContributors extends ApiQueryBase {

	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'cn' );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$this->buildDbQuery( $params );
		$res = $this->select( __METHOD__ );

		// API result
		$result = $this->getResult();

		foreach ( $res as $row ) {
			$result->addValue(
				'query', $this->getModuleName(), $row->cn_user_text, $row->cn_revision_count );
		}
	}
	private function buildDbQuery( array $params ) {
		$this->addTables( [ 'contributors' , 'page' ] );
		$this->addFields(
			[
				'Username' => 'cn_user_text',
				'Number of revisions' => 'cn_revision_count',
				'Date of first edit' => 'cn_first_edit',
				'Date of last edit' => 'cn_last_edit'
			]
		);
		$this->addJoinConds( [
			'page' => [
				'LEFT JOIN',
				[ 'cn_page_id = page_id' ]
			]
		] );
		$this->addWhereFld( 'page_title', $params['titles'] );
	}
	public function getAllowedParams() {
		return [
			'titles' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			]
		];
	}
	public function getExamplesMessages() {
		return [
			'action=query&prop=contributors&titles=Main+Page'
			=> 'apihelp-query+contributors-example-1',
		];
	}
}
