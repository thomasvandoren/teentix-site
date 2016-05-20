<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Static TeenTix favorites API
 *
 * @package		FIXME
 * @category	FIXME
 * @author		FIXME
 * @link		FIXME
 * @license  	FIXME
 * @copyright 	Copyright (c) 2016 FIXME
 */

/**
 * Include the config file
 */
require_once PATH_THIRD.'webservice/config.php';

class Webservice_tt_favorites_api_static
{
    static function read_favorites($data, $type = '') {
        //load the api class
        ee()->load->library('webservice_tt_favorites');

        $return_data = ee()->webservice_tt_favorites->read_favorites($data);

        return self::serialize_response($return_data, $type);
    }

    private static function serialize_response($return_data, $type) {
        //var_dump($return_data);exit;
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

        //return result
        return $return_data;
    }
}