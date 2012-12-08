<?php if ( ! defined('EXT') ) exit('No direct script access allowed');
 
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
 * Rating Module Class - Install/Uninstall/Update class
 *
 * @package 	Solspace:Rating
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/rating/upd.rating.php
 */

require_once 'upd.rating.base.php';

if (APP_VER < 2.0)
{
	eval('class Rating_updater extends Rating_updater_base { }');
}
else
{
	eval('class Rating_upd extends Rating_updater_base { }');
}