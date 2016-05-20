<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Webservice Extension helper
 *
 * @package		webservice
 * @category	Modules
 * @author		Rein de Vries <info@reinos.nl>
 * @link		http://reinos.nl/add-ons/entry-api
 * @license  	http://reinos.nl/add-ons/commercial-license
 * @copyright 	Copyright (c) 2014 Reinos.nl Internet Media
 */

/**
 * Include the config file
 */
require_once(PATH_THIRD.'webservice/config.php');

/**
 * Include helper
 */
require_once(PATH_THIRD.'webservice/libraries/webservice_helper.php');

class Webservice_lib
{
	private $default_settings;
	private $EE;

	public function __construct()
	{					
		//load model
		ee()->load->model(WEBSERVICE_MAP.'_model');

		//load the channel data
		ee()->load->driver('channel_data');

		//load the settings
		ee()->load->library(WEBSERVICE_MAP.'_settings');

		//load logger
		ee()->load->library('logger');

        //load helper
        ee()->load->helper('webservice_helper');
		
		//require the default settings
		require PATH_THIRD.WEBSERVICE_MAP.'/settings.php';
		
		// no time limit
		//set_time_limit(0);
			
		//check the tmp path
		ee()->load->helper('file');
		
		//create dir if not exists
		if(!is_dir(ee()->webservice_settings->item('tmp_dir')) && ee()->webservice_settings->item('tmp_dir') != '')
		{
			@mkdir(ee()->webservice_settings->item('tmp_dir'), 0777, true);
		}
		//chmod to write mode
		@chmod(ee()->webservice_settings->item('tmp_dir'), 0777);
		
		//set urls
		ee()->webservice_settings->set_setting('xmlrpc_url', reduce_double_slashes(ee()->config->item('site_url').ee()->config->item('site_index').'/webservice/xmlrpc'));
		ee()->webservice_settings->set_setting('soap_url', reduce_double_slashes(ee()->config->item('site_url').ee()->config->item('site_index').'/webservice/soap'));
		ee()->webservice_settings->set_setting('rest_url', reduce_double_slashes(ee()->config->item('site_url').ee()->config->item('site_index').'/webservice/rest'));

	}

	// --------------------------------------------------------------------
        
    /**
     * Has the user free access
     * User who exists has never free access
     * 0 = not free
     * 1 = no username, free access
     * 2 = inlog require, free access
     */
    public function has_free_access($method = '', $username = '')
    {
    	//user not exists, take the global settings
    	$user_exists = ee()->webservice_model->user_exists($username);

    	if($username == '' || $user_exists == false)
		{
			if(in_array($method, (array) ee()->webservice_settings->item('free_apis')))
			{	
				return 1;
			}
			return 0;
		}
		else if($user_exists)
		{
			$member = ee()->webservice_model->get_member_based_on_username($username);
			if(in_array($method, explode('|', $member->free_apis)))
			{	
				return 2;
			}
			return 0;
		}

		return 0;
    }

	public function tt_favorites_api_and_is_member($method = '', $member_id = '') {
		return strlen($member_id) > 0 && ($this->tt_ends_with($method, 'favorites') || $this->tt_ends_with($method, 'favorite'));
	}

	private function tt_ends_with($haystack, $needle) {
		// search forward starting from end minus needle length characters
		return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
	}

    // --------------------------------------------------------------------
        
    /**
     * Load the apis based on their dir name
     */
    public function load_apis()
    {
		//get from cache
		if ( isset(ee()->session->cache[WEBSERVICE_MAP]['apis']))
		{
			return ee()->session->cache[WEBSERVICE_MAP]['apis'];
		}

    	$apis = array();

    	ee()->load->helper('file');
    	ee()->load->helper('directory');

    	$path = PATH_THIRD.'webservice/libraries/api';
		$dirs = directory_map($path);

		foreach ($dirs as $key=>$dir)
		{
			if(is_array($dir))
			{
				foreach($dir as $file)
				{
					 if($file == 'settings.json')
					 {
					 	$json = file_get_contents($path.'/'.$key.'/settings.json');
					 	$json = json_decode($json);
					 	$json->path = $path.'/'.$key;

                        //is enabled?
                        if(isset($json->enabled) && $json->enabled)
                        {
    					 	//set a quick array for the methods
    					 	$json->_methods = array();
    					 	foreach($json->methods as $method)
    					 	{
    					 		$json->_methods[$json->name] = $method->method;
    					 		$apis['_methods_class'][$method->method] = $json->name;
    					 	}

    					 	$apis['apis'][$json->name] = $json;
                        }
					 }
				}
			}		    
		}

		//also look in the other maps for webservice stuff
		$path = PATH_THIRD;
		$dirs = directory_map($path);

		foreach ($dirs as $key=>$dir)
		{
			if(is_array($dir))
			{
				foreach($dir as $file)
				{
					if($file == 'webservice_settings.json')
					{
						$json = file_get_contents($path.$key.'/webservice_settings.json');
						$json = json_decode($json);
						$json->path = $path.$key;

						//is enabled?
						if(isset($json->enabled) && $json->enabled && !isset($apis['apis'][$json->name]))
						{
							//set a quick array for the methods
							$json->_methods = array();
							foreach($json->methods as $method)
							{
								$json->_methods[$json->name] = $method->method;
								$apis['_methods_class'][$method->method] = $json->name;
							}

							$apis['apis'][$json->name] = $json;
						}
					}
				}
			}
		}

		//save as session
		ee()->session->cache[WEBSERVICE_MAP]['apis'] = $apis;

        return $apis;
    }

    // --------------------------------------------------------------------
        
    /**
     * Search for the api method
     */
    public function search_api_method_class($method = '')
    {
    	$apis = $this->load_apis();
    	if(isset($apis['_methods_class'][$method]))
    	{
    		return $apis['_methods_class'][$method];
    	}
    }

    // --------------------------------------------------------------------
        
    /**
     * Load the apis based on their dir name
     */
    public function get_api_names()
    {
    	$apis = $this->load_apis();

    	$return = array();
    	foreach($apis['apis'] as $val)
    	{
            if($val->public == false)
            {
                $return[$val->name] = $val->label.(isset($val->version) ? ' <small>(v'.$val->version.')</small>' : '');
            }
    	}

    	return $return;
    }

    // --------------------------------------------------------------------
        
    /**
     * Load the apis based on their dir name
     */
    public function get_api_free_names()
    {
    	$apis = $this->load_apis();

    	$return = array();
    	foreach($apis['apis'] as $val)
    	{
    		foreach($val->methods as $method)
    		{
    			if(isset($method->free_api) && $method->free_api)
    			{
    				$return[$method->method] = $val->name.'/'.$method->method;
    			}
    		}
    	}

    	return $return;
    }

	// --------------------------------------------------------------------

	/**
	 * Load api data
	 */
	public function get_api_data($api_name = '', $method_name = '')
	{
		$apis = $this->load_apis();

		if($api_name != '' && isset($apis['apis'][$api_name]))
		{
			foreach($apis['apis'][$api_name]->methods as $method)
			{
				if($method->method == $method_name)
				{
					return $method;
				}
			}
		}

		return false;
	}

	// --------------------------------------------------------------------

	/**
	 * check if cachable
	 */
	public function method_is_cachable($api_name = '', $method_name = '')
	{
		$data = $this->get_api_data($api_name, $method_name);

		return isset($data->cachable) ? $data->cachable : false;
	}

	// --------------------------------------------------------------------

	/**
	 * check if the cache need to be flusche
	 */
	public function method_is_clear_cache($api_name = '', $method_name = '')
	{
		$data = $this->get_api_data($api_name, $method_name);

		return isset($data->clear_cache) ? $data->clear_cache : false;
	}

    // --------------------------------------------------------------------
        
    /**
     * Load the apis based on their dir name
     */
    public function get_api($name = '')
    {
    	$apis = $this->load_apis();

    	if($name != '' && isset($apis['apis'][$name]))
    	{
    		return $apis['apis'][$name];
    	}

		return false;
    }

	// --------------------------------------------------------------------

	/**
	 * Get entyr
	 */
	// ----------------------------------------------------------------

	/**
	 * Get entry based on entry_id
	 * It also has the pre_proces call to attach the data
	 *
	 * @access    public
	 * @param int $entry_id
	 * @param array $select
	 * @param bool $more_data
	 * @internal param list $parameter
	 * @return array
	 */
	function get_entry($entry_id = 0, $select = array('channel_data.entry_id', 'channel_data.channel_id', 'channel_titles.author_id', 'channel_titles.title', 'channel_titles.url_title', 'channel_titles.entry_date', 'channel_titles.expiration_date', 'status'), $more_data = false)
	{
		//get the entry
		$entry_data_query = ee()->channel_data->get_entry($entry_id, array('select' => $select));

		if(!$entry_data_query || $entry_data_query->num_rows() == 0)
		{
			return array();
		}

		//get the entry
		$entry = $entry_data_query->row_array();

		if($more_data)
		{
			//also get the channel data
			$entry_data = array_merge($entry, $this->_get_channel_data($entry['channel_id']));

			/** ---------------------------------------
			/** Get the categories
			/** ---------------------------------------*/
			$entry_data['categories'] = (ee()->webservice_category_model->get_entry_categories(array($entry_data['entry_id'])));

			/** ---------------------------------------
			/**  Process the data per field
			/** ---------------------------------------*/
			$fields = $this->get_fieldtypes($entry['channel_id']);
			if(!empty($fields))
			{
				foreach($fields as $key=>$val)
				{
					if(isset($entry_data[$val['field_name']]))
					{
						$entry_data[$val['field_name']] = ee()->webservice_fieldtype->pre_process($entry_data[$val['field_name']], $val['field_type'], $val['field_name'], $val, null, 'search_entry', $entry_id);
					}
				}
			}

			$entry = $entry_data;
		}

		/** ---------------------------------------
		 *  Hacky....
		 *   Output the fields
		 *   Yeah, we do this in PHP, but we should do did in MySQL via the channel_data class
		/** ---------------------------------------*/
		$output_fields = ee()->session->cache('webservice', 'output_fields');
		if(!empty($output_fields))
		{
			$def_fields = array('entry_id', 'entry_title', 'entry_date');
			$fields = array_merge($output_fields, $def_fields);

			//reset
			$old_entry = $entry;
			$entry = array();

			//format
			foreach($fields as $field)
			{
				if(isset($old_entry[$field]))
				{
					$entry[$field] = $old_entry[$field];
				}
			}
		}

		/** ---------------------------------------
		/** set the data correct
		/** ---------------------------------------*/
		$entry = $this->_format_read_result($entry);


		return $entry;
	}

	// ----------------------------------------------------------------

	/**
	 * Get the channel data
	 *
	 * @access    public
	 * @param int $channel_id
	 * @internal param int $entry_id
	 * @internal param \list $parameter
	 * @return    void
	 */
	private function _get_channel_data($channel_id = 0)
	{
		ee()->db->select('channel_name, channel_title');
		ee()->db->where('channel_id', $channel_id);
		$q = ee()->db->get('channels');

		if($q->num_rows() > 0)
		{
			return $q->row_array();
		}

		return array();
	}

	// ----------------------------------------------------------------

	/**
	 * Search an entry based on the given values
	 *
	 * @access	public
	 * @param	parameter list
	 * @return	void
	 */
	private function get_fieldtypes($channel_id = 0)
	{
		$channel_fields = ee()->channel_data->get_channel_fields($channel_id)->result_array();
		$fields = ee()->channel_data->utility->reindex($channel_fields, 'field_name');
		return $fields;
	}

	// ----------------------------------------------------------------

	//format an result for a get
	private function _format_read_result($result)
	{
		if(!empty($result))
		{
			foreach($result as $key=>$val)
			{
				if(substr($key, 0, 9) == 'field_ft_' || substr($key, 0, 9) == 'field_id_')
				{
					unset($result[$key]);
				}
			}
		}
		return $result;
	}

//	public function get_entry($entry_id, $select = array('channel_data.entry_id', 'channel_data.channel_id', 'channel_titles.author_id', 'channel_titles.title', 'channel_titles.url_title', 'channel_titles.entry_date', 'channel_titles.expiration_date', 'status'))
//	{
//		$entry = ee()->channel_data->get_entry($entry_id, $select);
//
//		if($entry->num_rows() > 0)
//		{
//
//		}
//
//
//		/** ---------------------------------------
//		/** Get the categories
//		/** ---------------------------------------*/
//		$entry_data['categories'] = (ee()->webservice_category_model->get_entry_categories(array($entry_data['entry_id'])));
//
//		/** ---------------------------------------
//		/**  Process the data per field
//		/** ---------------------------------------*/
//		if(!empty($this->fields))
//		{
//			foreach($this->fields as $key=>$val)
//			{
//				if(isset($entry_data[$val['field_name']]))
//				{
//					$entry_data[$val['field_name']] = ee()->webservice_fieldtype->pre_process($entry_data[$val['field_name']], $val['field_type'], $val['field_name'], $val, null, 'search_entry', $entry_id);
//				}
//			}
//		}
//
//		return false;
//
//		//get_channel_entries
//	}

	// --------------------------------------------------------------------
        
    /**
     * Hook - allows each method to check for relevant hooks
     */
    public function activate_hook($hook='', $data=array())
    {
        if ($hook AND ee()->extensions->active_hook(DEFAULT_MAP.'_'.$hook) === TRUE)
        {
                $data = ee()->extensions->call(DEFAULT_MAP.'_'.$hook, $data);
                if (ee()->extensions->end_script === TRUE) return;
        }
        
        return $data;
    }
	
		
	// ----------------------------------------------------------------------
	
} // END CLASS

/* End of file webservice_lib.php  */
/* Location: ./system/expressionengine/third_party/webservice/libraries/webservice_lib.php */