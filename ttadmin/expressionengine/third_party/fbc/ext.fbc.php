<?php if ( ! defined('EXT')) exit('No direct script access allowed');
 
 /**
 * Solspace - FBC
 *
 * @package 	Solspace:FBC
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2010-2012, Solspace, Inc.
 * @link		http://www.solspace.com/docs/addon/c/Facebook_Connect/
 * @version		2.0.9
 * @filesource 	./system/expressionengine/third_party/fbc/
 */
 
 /**
 * FBC Module Class - Extension Class
 *
 * @package 	Solspace:FBC
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/fbc/ext.fbc.php
 */
 
require_once 'ext.fbc.base.php';

if (APP_VER < 2.0)
{
	eval('class Fbc_extension extends Fbc_extension_base { }');
}
else
{
	eval('class Fbc_ext extends Fbc_extension_base { }');
}
?>