DROP TABLE IF EXISTS `sprod_integration_profiles`;
CREATE TABLE `sprod_integration_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sort` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `active` varchar(4) NOT NULL,
  `options` text DEFAULT '' NOT NULL,
  `filter` text DEFAULT '' NOT NULL,
  `statuses` text DEFAULT '' NOT NULL,
  `props` mediumtext DEFAULT '' NOT NULL,
  `contact` mediumtext DEFAULT '' NOT NULL,
  `other` mediumtext DEFAULT '' NOT NULL,
  `neworder` mediumtext DEFAULT '' NOT NULL,
  `date_create` datetime NOT NULL,
  `date_update` datetime NOT NULL,
  PRIMARY KEY (`id`)
);

DROP TABLE IF EXISTS `sprod_integration_locks`;
CREATE TABLE `sprod_integration_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL,
  `entity_id` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `hash` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
);

DROP TABLE IF EXISTS `sprod_integration_field_locks`;
CREATE TABLE `sprod_integration_field_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `entity_id` varchar(50) NOT NULL,
  `field` varchar(100) NOT NULL,
  `time` int(11) NOT NULL,
  `hash` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `entity_field` (`entity_id`, `field`, `type`)
);

DROP TABLE IF EXISTS `sprod_integration_fbasket_profiles`;
CREATE TABLE `sprod_integration_fbasket_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `active` varchar(4) NOT NULL DEFAULT 'Y',
  `site` varchar(50) DEFAULT NULL,
  `options` mediumtext NOT NULL,
  `contacts` mediumtext NOT NULL,
  `statuses` mediumtext NOT NULL,
  `fields` mediumtext NOT NULL,
  PRIMARY KEY (`id`)
);