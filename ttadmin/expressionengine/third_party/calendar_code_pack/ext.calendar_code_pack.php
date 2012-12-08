<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Calendar Code Pack Extension
 *
 * @package 	Solspace:Calendar Code Pack
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2010, Solspace, Inc.
 * @link		http://www.solspace.com/docs/
 * @version		1.0.0
 * @filesource 	./system/extensions/calendar_code_pack/
 * 
 */
 
 /**
 * Calendar Code Pack - Extension File
 *
 * @package 	Solspace:Calendar Code Pack
 * @author		Solspace Dev Team
 * @filesource 	./system/extensions/ext.calendar_code_pack.php
 */
 
require_once 'ext.calendar_code_pack.base.php';

if (APP_VER < 2.0)
{
	eval('class Calendar_code_pack extends Calendar_code_pack_extension_base { }');
}
else
{
	eval('class Calendar_code_pack_ext extends Calendar_code_pack_extension_base { }');
}
?>