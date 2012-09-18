<?php if ( ! defined('EXT') ) exit('No direct script access allowed');
 
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
 * Favorites Module Class - Install/Uninstall/Update class
 *
 * @package 	Solspace:Favorites
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/favorites/upd.favorites.php
 */

require_once 'upd.favorites.base.php';

if (APP_VER < 2.0)
{
	eval('class Favorites_updater extends Favorites_updater_base { }');
}
else
{
	eval('class Favorites_upd extends Favorites_updater_base { }');
}