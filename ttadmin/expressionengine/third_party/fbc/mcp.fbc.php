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
 * FBC Module Class - Control Panel
 *
 * The handler class for all control panel requests
 *
 * @package 	Solspace:FBC
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/fbc/mcp.fbc.php
 */
 
require_once 'mcp.fbc.base.php';

if (APP_VER < 2.0)
{
	eval('class Fbc_CP extends Fbc_cp_base { }');
}
else
{
	eval('class Fbc_mcp extends Fbc_cp_base { }');
}
?>