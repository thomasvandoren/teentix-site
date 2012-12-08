
CREATE TABLE IF NOT EXISTS `exp_ratings` (
  `rating_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rated_rating_id` int(10) unsigned NOT NULL DEFAULT 0,
  `entry_id` int(10) unsigned NOT NULL DEFAULT 0,
  `channel_id` int(4) unsigned NOT NULL DEFAULT 0,
  `rating_author_id` int(10) unsigned NOT NULL DEFAULT 0,
  `quarantine` char(1) NOT NULL DEFAULT 'n',
  `collection` varchar(50) NOT NULL DEFAULT '',
  `status` char(10) NOT NULL DEFAULT 'open',
  `name` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(50) NOT NULL DEFAULT '',
  `url` varchar(75) NOT NULL DEFAULT '',
  `location` varchar(50) NOT NULL DEFAULT '',
  `ip_address` varchar(16) NOT NULL DEFAULT '',
  `rating_date` int(10) NOT NULL DEFAULT 0,
  `edit_date` int(10) NOT NULL DEFAULT 0,
  `rating_review` TEXT NULL DEFAULT NULL,
  `rating_helpful_y` int(10) NOT NULL DEFAULT 0,
  `rating_helpful_n` int(10) NOT NULL DEFAULT 0,
  `rating` INT UNSIGNED NULL DEFAULT NULL,
  `review` TEXT NULL DEFAULT NULL,
  `notify` char(1) NOT NULL DEFAULT 'n',
  `duplicate` CHAR(1) NOT NULL DEFAULT 'n',
  PRIMARY KEY (`rating_id`),
  KEY `rated_rating_id` (`rated_rating_id`),
  KEY `entry_id` (`entry_id`),
  KEY `channel_id` (`channel_id`),
  KEY `rating_author_id` (`rating_author_id`),
  KEY `collection` (`collection`),
  KEY `status` (`status`)
) ;;


CREATE TABLE IF NOT EXISTS `exp_rating_cache` (
  `cache_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `date` int(10) NOT NULL DEFAULT 0,
  `array` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`cache_id`),
  KEY `name` (`name`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_rating_fields` (
  `field_id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `field_name` varchar(32) NOT NULL,
  `field_label` varchar(50) NOT NULL,
  `field_type` varchar(12) NOT NULL DEFAULT 'text',
  `field_list_items` TEXT NULL DEFAULT NULL,
  `field_maxl` smallint(3) NOT NULL DEFAULT '150',
  `field_search` char(1) NOT NULL DEFAULT 'n',
  `field_fmt` varchar(40) NOT NULL DEFAULT 'none',
  `field_order` int(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`field_id`),
  KEY `field_name` (`field_name`)
) ;;


INSERT INTO exp_rating_fields (field_id, field_name, field_label, field_order, field_type, field_maxl) VALUES (1, 'review', 'Review', '1', 'textarea', '1') ;;
INSERT INTO exp_rating_fields (field_id, field_name, field_label, field_order, field_type, field_maxl) VALUES (2, 'rating','Rating','2','number','10') ;;


CREATE TABLE IF NOT EXISTS `exp_rating_notification_log` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entry_date` int(10) unsigned NOT NULL DEFAULT 0,
  `total_sent` int(6) unsigned NOT NULL,
  `from_name` varchar(70) NOT NULL,
  `from_email` varchar(70) NOT NULL,
  `recipient` TEXT NULL DEFAULT NULL,
  `cc` TEXT NULL DEFAULT NULL,
  `bcc` TEXT NULL DEFAULT NULL,
  `recipient_array` MEDIUMTEXT NULL DEFAULT NULL,
  `subject` varchar(120) NOT NULL,
  `message` MEDIUMTEXT NULL DEFAULT NULL,
  `plaintext_alt` MEDIUMTEXT NULL DEFAULT NULL,
  `mailtype` varchar(6) NOT NULL DEFAULT '',
  `text_fmt` varchar(40) NOT NULL DEFAULT '',
  `wordwrap` char(1) NOT NULL DEFAULT 'y',
  `priority` char(1) NOT NULL DEFAULT '3',
  PRIMARY KEY (`log_id`)
) ;;


CREATE TABLE IF NOT EXISTS `exp_rating_params` (
  `params_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date` int(10) NOT NULL DEFAULT 0,
  `hash` VARCHAR( 25 ) NOT NULL DEFAULT '',
  `data` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`params_id`),
  KEY `date` (`date`),
  KEY `hash` (`hash`)
) ;;


CREATE TABLE IF NOT EXISTS `exp_rating_preferences` (
  `preference_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `preference_name` varchar(100) NOT NULL,
  `preference_value` varchar(100) NOT NULL,
  PRIMARY KEY (`preference_id`)
) ;;


CREATE TABLE IF NOT EXISTS `exp_rating_quarantine` (
  `quarantine_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rating_id` int(10) unsigned NOT NULL DEFAULT 0,
  `entry_id` int(10) unsigned NOT NULL DEFAULT 0,
  `channel_id` int(4) unsigned NOT NULL DEFAULT 0,
  `member_id` int(10) unsigned NOT NULL DEFAULT 0,
  `rating_author_id` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(10) NOT NULL DEFAULT 'open',
  `entry_date` int(10) NOT NULL DEFAULT 0,
  `edit_date` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`quarantine_id`),
  KEY `rating_id` (`rating_id`),
  KEY `entry_id` (`entry_id`),
  KEY `channel_id` (`channel_id`),
  KEY `member_id` (`member_id`),
  KEY `status` (`status`)
) ;;


CREATE TABLE IF NOT EXISTS `exp_rating_reviews` (
  `review_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rating_id` int(10) unsigned NOT NULL DEFAULT 0,
  `entry_id` int(10) unsigned NOT NULL DEFAULT 0,
  `channel_id` int(4) unsigned NOT NULL DEFAULT 0,
  `author_id` int(10) unsigned NOT NULL DEFAULT 0,
  `ip_address` varchar(16) NOT NULL DEFAULT '',
  `status` varchar(10) NOT NULL DEFAULT 'open',
  `rating_helpful` char(1) NOT NULL DEFAULT '',
  `rating_review` TEXT NULL DEFAULT NULL,
  `review_date` int(20) DEFAULT NULL,
  PRIMARY KEY (`review_id`),
  KEY `rating_id` (`rating_id`),
  KEY `entry_id` (`entry_id`),
  KEY `channel_id` (`channel_id`),
  KEY `author_id` (`author_id`)
) ;;


CREATE TABLE IF NOT EXISTS `exp_rating_stats` (
  `stat_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` int(10) unsigned NOT NULL DEFAULT 0,
  `channel_id` int(4) unsigned NOT NULL DEFAULT 0,
  `member_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `collection` varchar(50) NOT NULL DEFAULT '',
  `last_rating_date` int(10) unsigned NOT NULL DEFAULT 0,
  `count` INT UNSIGNED NULL DEFAULT NULL,
  `sum` INT UNSIGNED NULL DEFAULT NULL,
  `avg` FLOAT UNSIGNED NULL DEFAULT NULL,
  `count_1` INT UNSIGNED NULL DEFAULT NULL,
  `sum_1` INT UNSIGNED NULL DEFAULT NULL,
  `avg_1` FLOAT UNSIGNED NULL DEFAULT NULL,
  `count_2` INT UNSIGNED NULL DEFAULT NULL,
  `sum_2` INT UNSIGNED NULL DEFAULT NULL,
  `avg_2` FLOAT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`stat_id`),
  KEY `entry_id` (`entry_id`),
  KEY `channel_id` (`channel_id`),
  KEY `member_id` (`member_id`),
  KEY `count` (`count`),
  KEY `sum` (`sum`),
  KEY `avg` (`avg`)
) ;;


CREATE TABLE IF NOT EXISTS `exp_rating_templates` (
  `template_id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `enable_template` char(1) NOT NULL DEFAULT 'y',
  `wordwrap` char(1) NOT NULL DEFAULT 'y',
  `template_name` varchar(150) NOT NULL,
  `template_label` varchar(150) NOT NULL,
  `subject` varchar(80) NOT NULL DEFAULT '',
  `message` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`template_id`),
  KEY `template_name` (`template_name`)
) ;;

INSERT INTO exp_rating_templates (template_id, template_name, template_label, subject, message)
VALUES (NULL, 'default_template', 'Default Template', 'Someone has posted a rating', 'Someone has posted a rating.\n\nHere are the details:\nEntry Date: {entry_date} {all_custom_fields}') ;;

