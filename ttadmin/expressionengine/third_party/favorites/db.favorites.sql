CREATE TABLE IF NOT EXISTS `exp_favorites` (
	  `favorites_id` 		int(10) unsigned 		NOT NULL AUTO_INCREMENT,
	  `type` 				varchar(16) 			NOT NULL DEFAULT 'entry_id',
	  `author_id` 			int(10) unsigned 		NOT NULL DEFAULT '0',
	  `entry_id` 			int(10) unsigned 		NOT NULL,
	  `member_id` 			int(10) unsigned 		NOT NULL,
	  `site_id` 			smallint(3) unsigned 	NOT NULL DEFAULT '1',
	  `entry_date` 			int(10) unsigned 		NOT NULL,
	  `notes` 				text 					NOT NULL,
	  `public` 				char(1) 				NOT NULL DEFAULT 'y',
	  PRIMARY KEY 			(`favorites_id`),
	  KEY 					`author_id` 			(`author_id`),
	  KEY 					`entry_id` 				(`entry_id`),
	  KEY 					`member_id`				(`member_id`),
	  KEY 					`site_id` 				(`site_id`),
	  KEY 					`public` 				(`public`),
	  KEY 					`type` 					(`type`)
) CHARACTER SET utf8 COLLATE utf8_general_ci;;

CREATE TABLE IF NOT EXISTS `exp_favorites_prefs` (
	`pref_id` 				int(10) unsigned 		NOT NULL AUTO_INCREMENT,
	`language` 				varchar(20) 			NOT NULL,
	`member_id` 			int(10) unsigned 		NOT NULL DEFAULT '0',
	`site_id` 				smallint(3) unsigned 	NOT NULL DEFAULT '1',
	`no_string` 			varchar(100) 			NOT NULL,
	`no_login` 				varchar(100) 			NOT NULL,
	`no_id` 				varchar(100) 			NOT NULL,
	`id_not_found` 			varchar(100) 			NOT NULL,
	`no_duplicates` 		varchar(100) 			NOT NULL,
	`no_favorites` 			varchar(100) 			NOT NULL,
	`no_delete` 			varchar(100) 			NOT NULL,
	`success_add` 			varchar(100) 			NOT NULL,
	`success_delete` 		varchar(100) 			NOT NULL,
	`success_delete_all` 	varchar(100) 			NOT NULL,
	`add_favorite` 			char(1) 				NOT NULL DEFAULT 'n',
	PRIMARY KEY 			(`pref_id`),
	KEY 					`site_id` 				(`site_id`),
	KEY 					`member_id` 			(`member_id`)
) CHARACTER SET utf8 COLLATE utf8_general_ci;;