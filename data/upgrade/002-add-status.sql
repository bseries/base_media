ALTER TABLE `media_versions` ADD `status` VARCHAR(20)  NOT NULL  DEFAULT 'unknown'  AFTER `checksum`;
UPDATE media_versions SET `status` = 'processed' WHERE 1=1 ;


