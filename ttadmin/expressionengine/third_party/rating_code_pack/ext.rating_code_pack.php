<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Rating Code Pack Extension
 *
 * @package 	Solspace:Rating Code Pack
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2011, Solspace, Inc.
 * @link		http://solspace.com/docs/
 * @version		1.0.0
 * @filesource 	./system/extensions/rating_code_pack/
 * 
 */
 
 /**
 * Rating Code Pack - Extension File
 *
 * @package 	Solspace:Rating Code Pack
 * @author		Solspace DevTeam
 * @filesource 	./system/extensions/ext.rating_code_pack.php
 */
 
require_once 'ext.rating_code_pack.base.php';

if (APP_VER < 2.0)
{
	eval('class Rating_code_pack extends Rating_code_pack_extension_base { }');
}
else
{
	eval('class Rating_code_pack_ext extends Rating_code_pack_extension_base { }');
}
?>