<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 *  Compatibility file
 *
 * @package		Default
 * @category	Modules
 * @author		Rein de Vries <info@reinos.nl>
 * @license  	http://reinos.nl/add-ons/commercial-license
 * @link        http://reinos.nl/add-ons/entry-api
 * @copyright 	Copyright (c) 2014 Reinos.nl Internet Media
 */

//--------------------------------------------
//	Alias to get_instance() < EE 2.6.0 backward compat
//--------------------------------------------
if ( ! function_exists('ee'))
{
	function ee()
	{
		static $EE;
		if ( ! $EE) $EE = get_instance();
		return $EE;
	}
}

// EE 2.8 cp_url function is now used to generate URLs - need to provide it if
// we are on a version prior to EE 2.8
if(!function_exists('cp_url'))
{
	function cp_url($path, $qs = '')
	{
		$path = trim($path, '/');
		$path = preg_replace('#^cp(/|$)#', '', $path);

		$segments = explode('/', $path);
		$result = BASE.AMP.'C='.$segments[0].AMP.'M='.$segments[1];

		if (is_array($qs))
		{
			$qs = AMP.http_build_query($qs, AMP);
		}

		$result .= $qs;

		return $result;
	}
}
