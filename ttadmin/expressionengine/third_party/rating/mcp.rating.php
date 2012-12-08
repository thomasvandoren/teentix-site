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
 * Rating Module Class - Control Panel
 *
 * The handler class for all control panel requests
 *
 * @package 	Solspace:Rating module
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/rating/mcp.rating.php
 */
 
require_once 'mcp.rating.base.php';

if (APP_VER < 2.0)
{
	eval('class Rating_CP extends Rating_cp_base { }');
}
else
{
	eval('class Rating_mcp extends Rating_cp_base { }');
}
