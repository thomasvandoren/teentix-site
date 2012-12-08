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
 * Rating Module Class - Extension Class
 *
 * @package 	Solspace:Rating module
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/rating/ext.rating.php
 */
 
require_once 'ext.rating.base.php';

if (APP_VER < 2.0)
{
	eval('class Rating_extension extends Rating_extension_base { }');
}
else
{
	eval('class Rating_ext extends Rating_extension_base { }');
}
