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
 * Calendar - Config
 *
 * NSM Addon Updater Config File
 *
 * @package 	Solspace:Calendar
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/calendar/config.php
 */

//since we are 1.x/2.x compatible, we only want this to run in 1.x just in case
if (defined('APP_VER') AND APP_VER >= 2.0)
{
	if ( ! defined('CALENDAR_VERSION'))
	{
		require_once 'constants.calendar.php';		
	}

	$config['name']    								= 'Calendar';
	$config['version'] 								= CALENDAR_VERSION;
	$config['nsm_addon_updater']['versions_xml'] 	= 'http://www.solspace.com/software/nsm_addon_updater/calendar';
}