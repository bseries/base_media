-- Create syntax for TABLE 'media'
CREATE TABLE `media` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `title` varchar(250) DEFAULT NULL,
  `url` char(40) NOT NULL DEFAULT '',
  `type` varchar(100) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `checksum` char(32) DEFAULT '',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Unique index set on user_id+checksum instead of user_id+dirn';

-- Create syntax for TABLE 'media_versions'
CREATE TABLE `media_versions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `media_id` int(11) unsigned NOT NULL,
  `url` char(40) NOT NULL DEFAULT '',
  `version` varchar(10) DEFAULT NULL,
  `type` varchar(100) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `checksum` char(32) DEFAULT '',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Unique index set on user_id+checksum instead of user_id+dirn';

CREATE TABLE `media_attachments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `media_id` int(11) unsigned NOT NULL,
  `model` varchar(50) NOT NULL,
  `foreign_key` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `poly` (`model`,`foreign_key`),
  KEY `media_file` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
