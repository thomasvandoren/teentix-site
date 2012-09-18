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
 * Favorites Module Class - Extension Class
 *
 * @package 	Solspace:Favorites
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/favorites/ext.favorites.php
 */
 
require_once 'ext.favorites.base.php';

if (APP_VER < 2.0)
{
	eval('class Favorites_extension extends Favorites_extension_base { }');
}
else
{
	eval('class Favorites_ext extends Favorites_extension_base { }');
}
