-- Add two columns for the first edit and last edit of a contributor
ALTER TABLE /*_*/contributors
  ADD COLUMN cn_first_edit varbinary(14) ;
ALTER TABLE /*_*/contributors
  ADD COLUMN cn_last_edit varbinary(14) ;