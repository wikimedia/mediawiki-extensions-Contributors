CREATE TABLE /*_*/contributors(
  cn_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
  cn_page_id int unsigned NOT NULL,
  cn_user_id int unsigned NOT NULL,
  cn_user_text varchar(255) NOT NULL,
  cn_revision_count int unsigned NOT NULL
)/*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/cn_page_user ON /*_*/contributors ( cn_page_id , cn_user_id , cn_user_text );