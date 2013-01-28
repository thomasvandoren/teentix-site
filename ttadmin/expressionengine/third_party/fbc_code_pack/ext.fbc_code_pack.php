<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * FBC Code Pack Extension
 *
 * @package 	Solspace:FBC Code Pack
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2011, Solspace, Inc.
 * @link		http://www.solspace.com/docs/
 * @version		1.0.0
 * @filesource 	./system/extensions/fbc_code_pack/
 */
 
 /**
 * FBC Code Pack - Constants
 *
 * @package 	Solspace:FBC Code Pack
 * @author		Solspace Dev Team
 * @filesource 	./system/extensions/fbc_code_pack/ext.fbc_code_pack.php
 */
 
require_once 'ext.fbc_code_pack.base.php';

if (APP_VER < 2.0)
{
	eval('class Fbc_code_pack extends Fbc_code_pack_extension_base { }');
}
else
{
	eval('class Fbc_code_pack_ext extends Fbc_code_pack_extension_base { }');
}
?>