ALTER TABLE `media_versions` CHANGE `mime_type` `mime_type` VARCHAR(100)  CHARACTER SET utf8  COLLATE utf8_general_ci  NULL  DEFAULT '';
ALTER TABLE `media_versions` CHANGE `type` `type` VARCHAR(100)  CHARACTER SET utf8  COLLATE utf8_general_ci  NULL  DEFAULT '';
ALTER TABLE `media_versions` CHANGE `url` `url` CHAR(40)  CHARACTER SET utf8  COLLATE utf8_general_ci  NULL  DEFAULT '';

ALTER TABLE `media_versions` ADD `status` VARCHAR(20)  NOT NULL  DEFAULT 'unknown'  AFTER `checksum`;


