DROP TABLE IF EXISTS `OpenM_ID_API_TOKEN`;
CREATE TABLE IF NOT EXISTS `OpenM_ID_API_TOKEN` (
  `token_id` varchar(100) NOT NULL,
  `begin_time` int(20) NOT NULL,
  `service_id` varchar(100) NOT NULL,
  `user_id` varchar(100) NOT NULL,
  PRIMARY KEY (`token_id`),
  KEY `begin_time` (`begin_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_roman_ci;

DROP TABLE IF EXISTS `OpenM_ID_DATA_ACCESS_RIGHTS`;
CREATE TABLE IF NOT EXISTS `OpenM_ID_DATA_ACCESS_RIGHTS` (
  `user_id` varchar(100) CHARACTER SET latin1 NOT NULL,
  `data_id` tinyint(1) NOT NULL,
  `site_id` smallint(6) NOT NULL,
  `date_validation` int(12) NOT NULL,
  PRIMARY KEY (`user_id`,`site_id`,`data_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_roman_ci;

DROP TABLE IF EXISTS `OpenM_ID_SERVICE`;
CREATE TABLE IF NOT EXISTS `OpenM_ID_SERVICE` (
  `service_id` varchar(100) NOT NULL,
  `service_name` varchar(50) NOT NULL,
  `user_id` varchar(200) NOT NULL,
  `is_valid` smallint(1) NOT NULL DEFAULT '0',
  `ip` varchar(100) NOT NULL,
  PRIMARY KEY (`service_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_roman_ci;

DROP TABLE IF EXISTS `OpenM_ID_SERVICE_CLIENT`;
CREATE TABLE IF NOT EXISTS `OpenM_ID_SERVICE_CLIENT` (
  `service_id` varchar(200) NOT NULL,
  `client_ip_hash` varchar(200) NOT NULL,
  `user_id` varchar(200) NOT NULL,
  `time` int(20) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_roman_ci;

DROP TABLE IF EXISTS `OpenM_ID_SITE`;
CREATE TABLE IF NOT EXISTS `OpenM_ID_SITE` (
  `site_id` smallint(6) NOT NULL AUTO_INCREMENT,
  `dns` varchar(100) NOT NULL,
  PRIMARY KEY (`site_id`),
  UNIQUE KEY `dns` (`dns`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_roman_ci AUTO_INCREMENT=3 ;

DROP TABLE IF EXISTS `OpenM_ID_SITE_ALLOWED`;
CREATE TABLE IF NOT EXISTS `OpenM_ID_SITE_ALLOWED` (
  `site_id` smallint(6) NOT NULL,
  `added_by` varchar(100) CHARACTER SET latin1 NOT NULL,
  `date_added` int(12) NOT NULL,
  PRIMARY KEY (`site_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_roman_ci;

DROP TABLE IF EXISTS `OpenM_ID_TOKEN`;
CREATE TABLE IF NOT EXISTS `OpenM_ID_TOKEN` (
  `token_id` varchar(100) NOT NULL,
  `begin_time` int(20) NOT NULL,
  `user_id` varchar(100) NOT NULL,
  PRIMARY KEY (`token_id`),
  KEY `begin_time` (`begin_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_roman_ci;

DROP TABLE IF EXISTS `OpenM_ID_USER`;
CREATE TABLE IF NOT EXISTS `OpenM_ID_USER` (
  `user_id` varchar(100) NOT NULL,
  `mail` varchar(50) NOT NULL,
  `password` varchar(100) NOT NULL,
  `is_valid` smallint(1) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `mail` (`mail`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_roman_ci;

DROP TABLE IF EXISTS `OpenM_ID_USER_SESSION`;
CREATE TABLE IF NOT EXISTS `OpenM_ID_USER_SESSION` (
  `session_id` varchar(100) NOT NULL,
  `user_id` varchar(100) NOT NULL,
  `begin_time` int(20) NOT NULL,
  `ip_hash` varchar(100) NOT NULL,
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `user_id` (`user_id`,`ip_hash`),
  KEY `begin_time` (`begin_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_roman_ci;
