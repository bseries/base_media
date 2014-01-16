CREATE TABLE `media_attachments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `media_id` int(11) unsigned NOT NULL,
  `model` varchar(50) NOT NULL,
  `foreign_key` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `poly` (`model`,`foreign_key`),
  KEY `media_file` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
