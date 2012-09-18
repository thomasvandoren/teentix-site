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
 * Calendar - Control Panel
 *
 * The Control Panel master class that handles all of the CP Requests and Displaying
 *
 * @package 	Solspace:Calendar
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/calendar/mcp.calendar.php
 */
 
if ( ! class_exists('Calendar_cp_base'))
{
	require_once 'mcp.calendar.base.php';	
}

if (APP_VER < 2.0)
{
	eval('class Calendar_CP extends Calendar_cp_base { }');
}
else
{
	eval('class Calendar_mcp extends Calendar_cp_base { }');
}