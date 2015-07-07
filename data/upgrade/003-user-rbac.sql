ALTER TABLE `media` CHANGE `user_id` `owner_id` INT(11)  UNSIGNED  NOT NULL;
UPDATE `media` SET `owner_id` = 1;
