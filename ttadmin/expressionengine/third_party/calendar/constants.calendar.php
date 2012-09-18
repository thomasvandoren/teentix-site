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
 * Calendar - Constants
 *
 * Central location for various values we need throughout the module
 *
 * @package 	Solspace:Calendar
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/calendar/constants.calendar.php
 */

if ( ! defined('CALENDAR_VERSION'))
{
	$path = (APP_VER < 2.0) ? PATH.'modules/' : PATH_THIRD;

	define('CALENDAR_VERSION',	'1.7.0');
	define('CALENDAR_DOCS_URL',	'http://www.solspace.com/docs/addon/c/Calendar');

	// -------------------------------------
	// Paths to enlightenment
	// -------------------------------------

	define('CALENDAR_PATH', $path.'calendar/');
	define('CALENDAR_PATH_ASSETS', $path.'calendar/assets/');
	define('CALENDAR_PATH_THEMES', PATH_THEMES . ((APP_VER < 2.0) ? '' : 'third_party/') . 'calendar/');
	
	//this stupid thing errors out during upgrade
	define(
		'CALENDAR_URL_THEMES', 
		((APP_VER < 2.0) ? 
			$GLOBALS['PREFS']->ini('theme_folder_url', 1) : 
			get_instance()->config->item('theme_folder_url') . 'third_party/'
		) . 'calendar/'
	);

	// -------------------------------------
	// Default weblogs and fields
	// -------------------------------------

	define('CALENDAR_CALENDARS_CHANNEL_NAME_DEFAULT', 'calendar_calendars');
	define('CALENDAR_CALENDARS_CHANNEL_TITLE', 'Calendar: Calendars');
	define('CALENDAR_CALENDARS_FIELD_GROUP', 'Calendar: Calendars');
	define('CALENDAR_CALENDARS_FIELD_PREFIX', 'calendar_');
	define('CALENDAR_EVENTS_CHANNEL_NAME_DEFAULT', 'calendar_events');
	define('CALENDAR_EVENTS_CHANNEL_TITLE', 'Calendar: Events');
	define('CALENDAR_EVENTS_FIELD_GROUP', 'Calendar: Events');
	define('CALENDAR_EVENTS_FIELD_PREFIX', 'event_');
}