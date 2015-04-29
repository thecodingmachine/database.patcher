CREATE TABLE IF NOT EXISTS `patches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unique_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `status` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `exec_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `error_message` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`unique_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;