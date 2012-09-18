<?php if ( ! defined('EXT')) exit('No direct script access allowed');
 
 /**
 * Solspace - Calendar
 *
 * @package		Solspace:Calendar
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2010-2012, Solspace, Inc.
 * @link		http://www.solspace.com/docs/addon/c/Calendar/
 * @version		1.7.0
 * @filesource 	./system/expressionengine/third_party/calendar/
 */
 
 /**
 * Calendar - Extension
 *
 * @package 	Solspace:Calendar
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/calendar/ext.calendar.php
 */
 
if ( ! class_exists('Calendar_extension_base'))
{
	require_once 'ext.calendar.base.php';	
}

if (APP_VER < 2.0)
{
	eval('class Calendar_extension extends Calendar_extension_base { }');
}
else
{
	eval('class Calendar_ext extends Calendar_extension_base { }');
}