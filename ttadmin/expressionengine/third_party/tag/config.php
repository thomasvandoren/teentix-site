<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Tag - Config
 *
 * NSM Addon Updater config file.
 *
 * @package		Solspace:Tag
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2008-2013, Solspace, Inc.
 * @link		http://solspace.com/docs/tag
 * @license		http://www.solspace.com/license_agreement
 * @version		4.2.1
 * @filesource	tag/config.php
 */

//since we are 1.x/2.x compatible, we only want this to run in 1.x just in case
if (APP_VER >= 2.0)
{
	require_once PATH_THIRD . '/tag/constants.tag.php';

	$config['name']    								= 'Tag';
	$config['version'] 								= TAG_VERSION;
	$config['nsm_addon_updater']['versions_xml'] 	= 'http://www.solspace.com/software/nsm_addon_updater/tag';
}