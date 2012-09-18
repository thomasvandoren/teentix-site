<?php if ( ! defined('EXT')) exit('No direct script access allowed');

 /**
 * Solspace - Favorites
 *
 * @package		Solspace:Favorites
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2008-2012, Solspace, Inc.
 * @link		http://www.solspace.com/docs/addon/c/Favorites/
 * @version		3.0.5
 * @filesource 	./system/expressionengine/third_party/favorites/
 */

 /**
 * Favorites - Config
 *
 * NSM Addon Updater Config File
 *
 * @package 	Solspace:Favorites
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/favorites/config.php
 */

//since we are 1.x/2.x compatible, we only want this to run in 1.x just in case
if (defined('APP_VER') AND APP_VER >= 2.0)
{
	require_once PATH_THIRD . '/favorites/constants.favorites.php';

	$config['name']    								= 'Favorites';
	$config['version'] 								= FAVORITES_VERSION;
	$config['nsm_addon_updater']['versions_xml'] 	= 'http://www.solspace.com/software/nsm_addon_updater/favorites';
}