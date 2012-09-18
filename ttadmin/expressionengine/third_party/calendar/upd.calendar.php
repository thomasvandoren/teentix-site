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
 * Calendar - Updater
 *
 * In charge of the install, uninstall, and updating of the module
 *
 * @package 	Solspace:Calendar
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/calendar/upd.calendar.php
 */

if ( ! class_exists('Calendar_updater_base'))
{
	require_once 'upd.calendar.base.php';	
}

if (APP_VER < 2.0)
{
	eval('class Calendar_updater extends Calendar_updater_base { }');
}
else
{
	eval('class Calendar_upd extends Calendar_updater_base { }');
}