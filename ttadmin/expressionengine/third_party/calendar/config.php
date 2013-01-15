<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Calendar - Config
 *
 * NSM Addon Updater config file.
 *
 * @package		Solspace:Calendar
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2013, Solspace, Inc.
 * @link		http://solspace.com/docs/calendar
 * @license		http://www.solspace.com/license_agreement
 * @version		1.8.1
 * @filesource	calendar/config.php
 */

//since we are 1.x/2.x compatible, we only want this to run in 1.x just in case
if (defined('APP_VER') AND APP_VER >= 2.0)
{
	if ( ! defined('CALENDAR_VERSION'))
	{
		require_once 'constants.calendar.php';
	}

	$config['name']									= 'Calendar';
	$config['version']								= CALENDAR_VERSION;
	$config['nsm_addon_updater']['versions_xml']	= 'http://www.solspace.com/software/nsm_addon_updater/calendar';
}