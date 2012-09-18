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
 * Favorites Module Class - Control Panel
 *
 * The handler class for all control panel requests
 *
 * @package 	Solspace:Favorites module
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/favorites/mcp.favorites.php
 */
 
require_once 'mcp.favorites.base.php';

if (APP_VER < 2.0)
{
	eval('class Favorites_CP extends Favorites_cp_base { }');
}
else
{
	eval('class Favorites_mcp extends Favorites_cp_base { }');
}
