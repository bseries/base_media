# ************************************************************
# Sequel Pro SQL dump
# Version 4096
#
# http://www.sequelpro.com/
# http://code.google.com/p/sequel-pro/
#
# Host: localhost (MySQL 10.0.10-MariaDB-log)
# Datenbank: rainmap
# Erstellungsdauer: 2014-06-03 15:27:54 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Export von Tabelle media
# ------------------------------------------------------------

DROP TABLE IF EXISTS `media`;

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



# Export von Tabelle media_attachments
# ------------------------------------------------------------

DROP TABLE IF EXISTS `media_attachments`;

CREATE TABLE `media_attachments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `media_id` int(11) unsigned NOT NULL,
  `model` varchar(50) NOT NULL,
  `foreign_key` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `poly` (`model`,`foreign_key`),
  KEY `media_file` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Export von Tabelle media_versions
# ------------------------------------------------------------

DROP TABLE IF EXISTS `media_versions`;

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
  KEY `user` (`media_id`),
  KEY `media_id` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Unique index set on user_id+checksum instead of user_id+dirn';




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
