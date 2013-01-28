CREATE TABLE IF NOT EXISTS `exp_fbc_params` (
`params_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
`hash` varchar(32) NOT NULL,
`entry_date` int(10) NOT NULL,
`data` text NOT NULL,
PRIMARY KEY (`params_id`),
KEY `hash` (`hash`)
) ;;