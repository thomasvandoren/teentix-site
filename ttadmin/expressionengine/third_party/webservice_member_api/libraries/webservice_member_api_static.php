<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Static Auth API
 *
 * @package		webservice
 * @category	Modules
 * @author		Rein de Vries <info@reinos.nl>
 * @link		http://reinos.nl/add-ons/webservice
 * @license  	http://reinos.nl/add-ons/commercial-license
 * @copyright 	Copyright (c) 2014 Reinos.nl Internet Media
 */

class Webservice_member_api_static
{	

	//-------------------------------------------------------------------------

	/**
     * create_member method
    */
	public static function create_member($data = array(), $type = '')
	{
		//load the entry class
		ee()->load->library('webservice_member_api');

		//post the data to the service
		$return_data = ee()->webservice_member_api->create_member($data, $type);

		//format the array, because we cannot do nested arrays
		if($type != 'rest' && isset($return_data['data']))
		{
			$return_data['data'] = webservice_format_data($return_data['data'], $type);
		}

		//Format soap
		if($type == 'soap')
		{
			if(isset($return_data['metadata']))
			{
				$return_data['metadata'] = webservice_format_soap_data($return_data['metadata']);
			}
		}

		//return result
		return $return_data;
	}

	//-------------------------------------------------------------------------

	/**
     * create_member method
    */
	public static function read_member($data = array(), $type = '')
	{
		//load the entry class
		ee()->load->library('webservice_member_api');

		//post the data to the service
		$return_data = ee()->webservice_member_api->read_member($data, $type);

		//Format soap
		if($type == 'soap')
		{
			if(isset($return_data['data']))
			{	
				$return_data['data'] = webservice_format_soap_data($return_data['data'], 'entry_list');
			}
			if(isset($return_data['metadata']))
			{
				$return_data['metadata'] = webservice_format_soap_data($return_data['metadata']);
			}
		}		
		
		//format the array, because we cannot do nested arrays
		if($type != 'rest' && isset($return_data['data']))
		{
			$return_data['data'] = webservice_format_data($return_data['data'], $type);
		}

		//unset the response txt
		unset($return_data['response']);

		//return result
		return $return_data;
	}

	//-------------------------------------------------------------------------

	/**
     * update_member method
    */
	public static function update_member($data = array(), $type = '')
	{
		//load the entry class
		ee()->load->library('webservice_member_api');

		//post the data to the service
		$return_data = ee()->webservice_member_api->update_member($data, $type);

		//format the array, because we cannot do nested arrays
		if($type != 'rest' && isset($return_data['data']))
		{
			$return_data['data'] = webservice_format_data($return_data['data'], $type);
		}

		//Format soap
		if($type == 'soap')
		{
			if(isset($return_data['metadata']))
			{
				$return_data['metadata'] = webservice_format_soap_data($return_data['metadata']);
			}
		}

		//unset the response txt
		unset($return_data['response']);

		//return result
		return $return_data;
	}

	//-------------------------------------------------------------------------

	/**
     * delete_member method
    */
	public static function delete_member($data = array(), $type = '')
	{
		//load the entry class
		ee()->load->library('webservice_member_api');

		//post the data to the service
		$return_data = ee()->webservice_member_api->delete_member($data, $type);

		//format the array, because we cannot do nested arrays
		if($type != 'rest' && isset($return_data['data']))
		{
			$return_data['data'] = webservice_format_data($return_data['data'], $type);
		}

		//Format soap
		if($type == 'soap')
		{
			if(isset($return_data['metadata']))
			{
				$return_data['metadata'] = webservice_format_soap_data($return_data['metadata']);
			}
		}
		
		//unset the response txt
		unset($return_data['response']);

		//return result
		return $return_data;
	}
}