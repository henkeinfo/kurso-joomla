--
-- KURSO for Joomla – database schema (MySQL / MariaDB)
--

CREATE TABLE IF NOT EXISTS `#__kurso_queries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `alias` varchar(191) NOT NULL DEFAULT '',
  `note` varchar(255) NOT NULL DEFAULT '',
  `graphql` mediumtext NOT NULL,
  `variables` mediumtext NOT NULL,
  `template` mediumtext NOT NULL,
  `refresh_interval` int unsigned NOT NULL DEFAULT 60,
  `state` tinyint NOT NULL DEFAULT 1,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `checked_out` int unsigned,
  `checked_out_time` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_alias` (`alias`),
  KEY `idx_state` (`state`),
  KEY `idx_checkout` (`checked_out`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__kurso_cache` (
  `query_id` int unsigned NOT NULL,
  `data` longtext,
  `fetched_at` datetime NULL DEFAULT NULL,
  `attempted_at` datetime NULL DEFAULT NULL,
  `error` text,
  `error_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`query_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__kurso_settings` (
  `name` varchar(100) NOT NULL,
  `value` mediumtext NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
