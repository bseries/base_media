ALTER TABLE `media` ADD `source` VARCHAR(250)  NULL  DEFAULT NULL  AFTER `checksum`;
update media set source = 'admin' where owner_id in (select id from users where role = 'admin');

