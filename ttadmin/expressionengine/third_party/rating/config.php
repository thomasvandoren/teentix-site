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
 * Rating - Config
 *
 * NSM Addon Updater Config File
 *
 * @package 	Solspace:Rating
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/rating/config.php
 */

//since we are 1.x/2.x compatible, we only want this to run in 1.x just in case
if (defined('APP_VER') AND APP_VER >= 2.0)
{
	require_once PATH_THIRD . '/rating/constants.rating.php';

	$config['name']    								= 'Rating';
	$config['version'] 								= RATING_VERSION;
	$config['nsm_addon_updater']['versions_xml'] 	= 'http://www.solspace.com/software/nsm_addon_updater/rating';
}