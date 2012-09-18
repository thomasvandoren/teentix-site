CREATE TABLE IF NOT EXISTS `exp_calendar_calendars` (
	`calendar_id` 		INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`site_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '1',
	`tz_offset` 		CHAR(5) 				NOT NULL DEFAULT '+0000',
	`timezone` 			VARCHAR(100) 			NOT NULL DEFAULT 'Europe/London',
	`time_format` 		VARCHAR(10) 			NOT NULL DEFAULT 'g:i a',
	`ics_url` 			TEXT 					DEFAULT '',
	`ics_updated` 		DATETIME 				DEFAULT '0000-00-00',
	PRIMARY KEY 		(`calendar_id`),
	KEY 				(`site_id`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_calendar_events` (
	`event_id` 			INT(10) UNSIGNED 		NOT NULL AUTO_INCREMENT,
	`site_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '1',
	`calendar_id` 		INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`entry_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`start_date` 		INT(8) 					NOT NULL DEFAULT '0',
	`start_year` 		SMALLINT(4) 			NOT NULL DEFAULT '0',
	`start_month` 		TINYINT(2) 				NOT NULL DEFAULT '0',
	`start_day` 		TINYINT(2) 				NOT NULL DEFAULT '0',
	`all_day` 			CHAR(1) 				NOT NULL DEFAULT 'n',
	`start_time` 		SMALLINT UNSIGNED 		NOT NULL DEFAULT '0',
	`end_date` 			INT(8) 					NOT NULL DEFAULT '0',
	`end_year` 			SMALLINT(4) 			NOT NULL DEFAULT '0',
	`end_month` 		TINYINT(2) 				NOT NULL DEFAULT '0',
	`end_day` 			TINYINT(2) 				NOT NULL DEFAULT '0',
	`end_time` 			SMALLINT UNSIGNED 		NOT NULL DEFAULT '0',
	`recurs` 			CHAR(1) 				NOT NULL DEFAULT 'n',
	`last_date` 		INT(8) 					NOT NULL DEFAULT '0',
	PRIMARY KEY 		(`event_id`),
	KEY 				(`site_id`),
	KEY 				(`calendar_id`),
	KEY 				(`start_date`),
	KEY 				(`end_date`),
	KEY 				(`last_date`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_calendar_events_rules` (
	`rule_id` 			INT(10) UNSIGNED 		NOT NULL AUTO_INCREMENT,
	`event_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`calendar_id` 		INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`entry_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`rule_type` 		CHAR(1) 				DEFAULT '+',
	`start_date` 		INT(8) 					NOT NULL DEFAULT '0',
	`all_day` 			CHAR(1) 				NOT NULL DEFAULT 'n',
	`start_time` 		SMALLINT UNSIGNED 		NOT NULL DEFAULT '0',
	`end_date` 			INT(8) 					NOT NULL DEFAULT '0',
	`end_time` 			SMALLINT UNSIGNED 		NOT NULL DEFAULT '0',
	`repeat_years` 		SMALLINT(5) UNSIGNED 	NOT NULL DEFAULT '0',
	`repeat_months` 	SMALLINT(5) UNSIGNED 	NOT NULL DEFAULT '0',
	`repeat_days` 		SMALLINT(5) UNSIGNED 	NOT NULL DEFAULT '0',
	`repeat_weeks` 		SMALLINT(5) UNSIGNED 	NOT NULL DEFAULT '0',
	`days_of_week` 		VARCHAR(7) 				DEFAULT '',
	`relative_dow` 		VARCHAR(6) 				NOT NULL DEFAULT '',
	`days_of_month` 	VARCHAR(31) 			DEFAULT '',
	`months_of_year` 	VARCHAR(12) 			DEFAULT '',
	`stop_by` 			INT(8) 					NOT NULL DEFAULT '0',
	`stop_after` 		SMALLINT(5) UNSIGNED 	NOT NULL DEFAULT '0',
	`last_date` 		INT(8) 					NOT NULL DEFAULT '0',
	PRIMARY KEY 		(`rule_id`),
	KEY 				(`event_id`),
	KEY 				(`start_date`),
	KEY 				(`end_date`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_calendar_events_occurrences` (
	`occurrence_id` 	INT(10) UNSIGNED 		NOT NULL AUTO_INCREMENT,
	`event_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`calendar_id` 		INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`site_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`entry_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`start_date` 		INT(8) 					NOT NULL DEFAULT '0',
	`start_year` 		SMALLINT(4) 			NOT NULL DEFAULT '0',
	`start_month` 		TINYINT(2) 				NOT NULL DEFAULT '0',
	`start_day` 		TINYINT(2) 				NOT NULL DEFAULT '0',
	`all_day` 			CHAR(1) 				NOT NULL DEFAULT 'n',
	`start_time` 		SMALLINT UNSIGNED 		NOT NULL DEFAULT '0',
	`end_date` 			INT(8) 					NOT NULL DEFAULT '0',
	`end_year` 			SMALLINT(4) 			NOT NULL DEFAULT '0',
	`end_month` 		TINYINT(2) 				NOT NULL DEFAULT '0',
	`end_day` 			TINYINT(2) 				NOT NULL DEFAULT '0',
	`end_time` 			SMALLINT UNSIGNED 		NOT NULL DEFAULT '0',
	PRIMARY KEY 		(`occurrence_id`),
	KEY 				(`event_id`),
	KEY 				(`entry_id`),
	KEY 				(`calendar_id`),
	KEY 				(`site_id`),
	KEY 				(`start_date`),
	KEY 				(`end_date`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_calendar_events_exceptions` (
	`exception_id` 		INT(10) UNSIGNED 		NOT NULL AUTO_INCREMENT,
	`event_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`calendar_id` 		INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`site_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`entry_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`start_date` 		INT(8) 					NOT NULL DEFAULT '0',
	`start_year` 		SMALLINT(4) 			NOT NULL DEFAULT '0',
	`start_month` 		TINYINT(2) 				NOT NULL DEFAULT '0',
	`start_day` 		TINYINT(2) 				NOT NULL DEFAULT '0',
	`start_time` 		SMALLINT UNSIGNED 		NOT NULL DEFAULT '0',
	PRIMARY KEY 		(`exception_id`),
	KEY 				(`event_id`),
	KEY 				(`entry_id`),
	KEY 				(`calendar_id`),
	KEY 				(`site_id`),
	KEY 				(`start_date`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_calendar_reminders` (
	`reminder_id` 		INT(10) UNSIGNED		NOT NULL AUTO_INCREMENT,
	`member_id` 		INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`event_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`occurrence_id` 	INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`template_id` 		INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`time_interval` 	SMALLINT(5) UNSIGNED 	NOT NULL DEFAULT '1',
	`time_unit` 		CHAR(1) 				NOT NULL DEFAULT 'd',
	PRIMARY KEY 		(`reminder_id`),
	KEY 				(`member_id`),
	KEY 				(`event_id`),
	KEY 				(`occurrence_id`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_calendar_reminders_templates` (
	`template_id` 		INT(10) UNSIGNED 		NOT NULL AUTO_INCREMENT,
	`wordwrap` 			CHAR(1) 				NOT NULL DEFAULT 'y',
	`html` 				CHAR(1) 				NOT NULL DEFAULT 'n',
	`template_name` 	VARCHAR(150) 			NOT NULL DEFAULT '',
	`template_label` 	VARCHAR(150) 			NOT NULL DEFAULT '',
	`from_name` 		VARCHAR(150) 			NOT NULL DEFAULT '',
	`from_email` 		VARCHAR(200) 			NOT NULL DEFAULT '',
	`subject` 			VARCHAR(80) 			NOT NULL DEFAULT '',
	`template_data` 	TEXT 					NOT NULL DEFAULT '',
	PRIMARY KEY 		(`template_id`),
	KEY 				(`template_name`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_calendar_permissions_users` (
	`permission_id` 	INT(10) UNSIGNED 		NOT NULL AUTO_INCREMENT,
	`user_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`calendar_id` 		INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`calendar_admin` 	CHAR(1) 				NOT NULL DEFAULT 'n',
	`calendar_edit` 	CHAR(1) 				NOT NULL DEFAULT 'n',
	`calendar_view` 	CHAR(1) 				NOT NULL DEFAULT 'n',
	`events_admin` 		CHAR(1) 				NOT NULL DEFAULT 'n',
	`events_edit` 		CHAR(1) 				NOT NULL DEFAULT 'n',
	`events_view` 		CHAR(1) 				NOT NULL DEFAULT 'n',
	PRIMARY KEY 		(`permission_id`),
	KEY 				(`user_id`),
	KEY 				(`calendar_id`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_calendar_permissions_groups` (
	`permission_id` 	INT(10) UNSIGNED 		NOT NULL AUTO_INCREMENT,
	`group_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`calendar_id` 		INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`calendar_admin` 	CHAR(1) 				NOT NULL DEFAULT 'n',
	`calendar_edit` 	CHAR(1) 				NOT NULL DEFAULT 'n',
	`calendar_view` 	CHAR(1) 				NOT NULL DEFAULT 'n',
	`events_admin` 		CHAR(1) 				NOT NULL DEFAULT 'n',
	`events_edit` 		CHAR(1) 				NOT NULL DEFAULT 'n',
	`events_view` 		CHAR(1) 				NOT NULL DEFAULT 'n',
	PRIMARY KEY 		(`permission_id`),
	KEY					(`group_id`),
	KEY 				(`calendar_id`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_calendar_preferences` (
	`site_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '1',
	`preferences` 		TEXT 					NOT NULL DEFAULT '',
	KEY (`site_id`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_calendar_permissions_preferences` (
	`site_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '1',
	`preferences` 		TEXT 					NOT NULL DEFAULT '',
	KEY (`site_id`)
) ;;

CREATE TABLE IF NOT EXISTS `exp_calendar_events_imports` (
	`import_id` 		INT(10) UNSIGNED 		NOT NULL AUTO_INCREMENT,
	`calendar_id` 		INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`event_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`entry_id` 			INT(10) UNSIGNED 		NOT NULL DEFAULT '0',
	`uid` 				VARCHAR(255) 			NOT NULL DEFAULT '',
	`last_mod` 			CHAR(12) 				NOT NULL DEFAULT '',
	PRIMARY KEY 		(`import_id`),
	KEY 				(`calendar_id`),
	KEY 				(`event_id`),
	KEY 				(`entry_id`),
	KEY 				(`uid`)
) ;;