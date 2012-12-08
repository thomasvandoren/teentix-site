<?php if ( ! defined('EXT')) exit('No direct script access allowed');
 
 /**
 * Solspace - Rating
 *
 * @package		Solspace:Rating
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2008-2012, Solspace, Inc.
 * @link		http://solspace.com/docs/addon/c/Rating/
 * @version		3.1.1
 * @filesource 	./system/expressionengine/third_party/rating/
 */
 
 /**
 * Rating - Constants
 *
 * Central location for various values we need throughout the module
 *
 * @package 	Solspace:Rating
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/rating/constants.rating.php
 */
 
if ( ! defined('RATING_VERSION'))
{
	define('RATING_VERSION',	'3.1.1');
	define('RATING_DOCS_URL',	'http://solspace.com/docs/addon/c/Rating/');
	define('RATING_PREFS',		'can_delete_ratings|can_report_ratings|can_post_ratings|enabled_channels|quarantine_minimum|require_email|use_captcha');
}
