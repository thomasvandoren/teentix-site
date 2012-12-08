<?php
if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * Google Map 
 *
 * @package		Mx_google_map
 * @subpackage	ThirdParty
 * @category	Modules
 * @author		Max Lazar
 * @link		http://eec.ms
 */
class Mx_google_map
{
    var $return_data;
    var $default_long = "-74.002962";
    var $default_lat = "40.715192";
    var $default_address = "New York, NY, USA";
    var $default_radius = 500;
    var $geo_cache_dir			= 'mx_google_map';

	public $cache_lifetime = 1440;

    function Mx_google_map()
    {
        $this->EE =& get_instance(); // Make a local reference to the ExpressionEngine super object
    }
    
    
    /**
     * Helper function for getting a parameter
     */
    function _get_param($key, $default_value = '')
    {
        $val = $this->EE->TMPL->fetch_param($key);
        
        if ($val == '') {
            return $default_value;
        }
        return $val;
    }
    
    function search()
    {
		$address = '';
        $tagdata = $this->EE->TMPL->tagdata;
        
        $prec   = (!$this->EE->TMPL->fetch_param('prec')) ? '' : ',' . $this->EE->TMPL->fetch_param('prec');
        $prefix = (!$this->EE->TMPL->fetch_param('prefix')) ? '' : ',' . $this->EE->TMPL->fetch_param('prefix');
        
        $orderby = (!$this->EE->TMPL->fetch_param('orderby')) ? false : (($this->EE->TMPL->fetch_param('orderby') == 'distance') ? false : $this->EE->TMPL->fetch_param('orderby'));
        $sort    = (!$this->EE->TMPL->fetch_param('sort')) ? 'asc' : $this->EE->TMPL->fetch_param('sort');
        
        $this->EE->TMPL->tagparams['limit'] = (!$this->EE->TMPL->fetch_param('limit')) ? 99999 : $this->EE->TMPL->fetch_param('limit');


        $address_fields  = (!$this->EE->TMPL->fetch_param('address_fields')) ? false : $this->EE->TMPL->fetch_param('address_fields');

        $debug = (!$this->EE->TMPL->fetch_param('debug')) ? false : true; //@delete
        
        $reverse_geocoding = (!$this->EE->TMPL->fetch_param('reverse_geocoding')) ? '' : ',' . $this->EE->TMPL->fetch_param('reverse_geocoding');
        
        if ((isset($_POST) AND count($_POST) > 0) OR (isset($_GET) AND count($_GET) > 0)) {
            $zipLongitude = $this->EE->security->xss_clean($this->EE->input->get_post('long'));
            $zipLatitude  = $this->EE->security->xss_clean($this->EE->input->get_post('lat'));
            $unit         = $this->EE->security->xss_clean($this->EE->input->get_post('unit'));
            
			//@add to site description
			if ($address_fields) {
				foreach (explode('|',$address_fields) as $field_name) {
				  $address = ($this->EE->input->get_post($field_name)) ? $address.$this->EE->security->xss_clean($this->EE->input->get_post($field_name)).', ' : '';
				}
				
			} else {
				$address      = $this->EE->security->xss_clean($this->EE->input->get_post('address'));
				
				if  (is_array($address)) {
					$address = implode(",", $address);
				}
			}

			$address      = $prefix . $address;
			
			
            $radius       = $this->EE->security->xss_clean($this->EE->input->get_post('radius'));
        } else {
            $zipLongitude = ($this->EE->TMPL->fetch_param('long') != '') ? $this->EE->TMPL->fetch_param('long') : "";
            $zipLatitude  = ($this->EE->TMPL->fetch_param('lat') != '') ? $this->EE->TMPL->fetch_param('lat') : '';
            $unit         = ($this->EE->TMPL->fetch_param('unit') != '') ? $this->EE->TMPL->fetch_param('unit') : 'ml';
            $address      = ($this->EE->TMPL->fetch_param('address') != '') ? $this->EE->TMPL->fetch_param('address') : '';
            $radius       = ($this->EE->TMPL->fetch_param('radius') != '') ? $this->EE->TMPL->fetch_param('radius') : $this->default_radius;
        }

        $radius       = ($radius == '') ? $this->default_radius : $radius;
        $earth_radius = ($unit == 'km') ? 6371 : 3959; //earth_radius		
        
        if (($zipLongitude == "" OR $zipLatitude == "") AND $address == "") {
            $zipLongitude = $this->default_long;
            $zipLatitude  = $this->default_lat;
            $address      = $this->default_address;
        }
        
        $entry_id = '';
        $points   = array();
		

        $entry_id = rtrim($entry_id, '|');
        
        $channel = new Channel;
        
        $LD          = '\{';
        $RD          = '\}';
        $SLASH       = '\/';
        $variable    = "entries";
        $return_data = "";
        
        if (isset($_POST['categories'])) {
            $this->EE->TMPL->tagparams['category'] = ((isset($this->EE->TMPL->tagparams['category'])) ? $this->EE->TMPL->tagparams['category'] : '') . '|' . implode("|", $this->EE->security->xss_clean($_POST['categories']));
        }
        

         
        if (preg_match("/" . LD . $variable . ".*?" . RD . "(.*?)" . LD . '\/' . $variable . RD . "/s", $tagdata, $entries)) {
            $channel->EE->TMPL->tagdata = $entries[1];
            
            if ($channel->EE->TMPL->fetch_param('related_categories_mode') == 'yes') {
                return $channel->related_entries();
            }
            
            $channel->initialize();
            
            $channel->uri = ($channel->query_string != '') ? $channel->query_string : 'index.php';
            
            if ($channel->enable['custom_fields'] == TRUE) {
                $channel->fetch_custom_channel_fields();
            }
            
            if ($channel->enable['member_data'] == TRUE) {
                $channel->fetch_custom_member_fields();
            }
            
            if ($channel->enable['pagination'] == TRUE) {
            	if (version_compare(APP_VER, '2.4', '>='))
				{
					$channel->add_pagination_data();
				}else {
					$channel->fetch_pagination_data();
				}                
            }
            
            $save_cache = FALSE;
	
            $channel->EE->TMPL->tagparams['dynamic'] = 'no';
			//$zipLongitude.$zipLatitude.$address
			 
            if ($channel->EE->config->item('enable_sql_caching') == 'y') {
                if (FALSE == ($channel->sql = $channel->fetch_cache())) {
					$save_cache = TRUE;
                } else {
                    if ($channel->EE->TMPL->fetch_param('dynamic') != 'no') {
                        if (preg_match("#(^|\/)C(\d+)#", $channel->query_string, $match) OR in_array($channel->reserved_cat_segment, explode("/", $channel->query_string))) {
                            $channel->cat_request = TRUE;
                        }
                    }
                }
                
                if (FALSE !== ($cache = $channel->fetch_cache('pagination_count'))) {
                    if (FALSE !== ($channel->fetch_cache('field_pagination'))) {
                        if (FALSE !== ($pg_query = $channel->fetch_cache('pagination_query'))) {
                            $channel->paginate         = TRUE;
                            $channel->field_pagination = TRUE;
                            $channel->create_pagination(trim($cache), $channel->EE->db->query(trim($pg_query)));
                        }
                    } else {
                        $channel->create_pagination(trim($cache));
                    }
                }
            }
            
            if ($channel->sql == '') {
                $channel->build_sql_query();
            }
            
            if ($channel->sql == '') {
                return $channel->EE->TMPL->no_results();
            }
            $sql = "";
            
            //@start geocoding
            //if don't have the Latitude and Longitude, do query to google.map; ($zipLongitude == "" OR $zipLatitude == "") AND 

            if ($address != "" AND ($zipLongitude == "" OR $zipLatitude == "")) {
                
                $GetLatLong_result = $this->GetLatLong($address, 2);

                if ($GetLatLong_result != false) {
                    list($zipLongitude, $zipLatitude) = $GetLatLong_result;
                } else {
                    return $this->EE->TMPL->no_results();
                }
            }
        
            if ($reverse_geocoding and $address == "") {
                $GetLatLong_result = $this->GetLatLong($zipLatitude . ',' . $zipLongitude, $api_key, 1);
                if ($GetLatLong_result != false) {
                    $address = $GetLatLong_result;
                } else {
                    return $this->EE->TMPL->no_results();
                }
            }
                
            //@END geocoding
			$conds['radius'] = $radius;
			$tagdata = $this->EE->functions->prep_conditionals($tagdata, $conds);
			
			$tagdata  = str_replace(array(
				'{center:long}',
				'{center:lat}',
				'{radius}'
			), array(
				$zipLongitude,
				$zipLatitude,
				$radius
			), $tagdata);
		
            $where_strpos = strpos($channel->sql, "WHERE");
			
			if ($where_strpos > 0 ) 
			{

				
				$sql_entry_id = (substr($channel->sql, $where_strpos + 5, strpos($channel->sql, "ORDER BY") - $where_strpos - 5));
				$limit_strpos = strpos($channel->sql, "LIMIT");
				$order_by = substr($channel->sql, strpos($channel->sql, "ORDER BY"), $limit_strpos);
				$limit = substr($channel->sql, strpos($channel->sql, "LIMIT"));
				
				$sql = str_replace('FROM', ", gm.*, ROUND( $earth_radius * acos( cos( radians($zipLatitude) ) * cos( radians( gm.latitude ) ) * cos( radians( gm.longitude ) - radians($zipLongitude) ) + sin( radians($zipLatitude) ) *  sin( radians( gm.latitude ) ) ) $prec ) AS distance   FROM ", $channel->sql);
				

				$sql = substr($sql, 0, strpos($sql, "WHERE")) . "RIGHT JOIN exp_mx_google_map AS gm ON t.entry_id = gm.entry_id HAVING distance < $radius AND " . $sql_entry_id;
				

				if ($orderby) {
					$sql = $sql . $order_by;
				} else {
					$sql = $sql . " ORDER BY distance " . $sort;
				}

				$channel->sql = $sql;
            }
            
            
            if ($save_cache == TRUE) {
                $channel->save_cache($channel->sql);
            }
            
            $channel->query = $channel->EE->db->query($channel->sql);
            
            if ($channel->query->num_rows() == 0) {
                return $channel->EE->TMPL->no_results();
            }
            
            foreach ($channel->query->result_array() as $row) {
                $points[$row['point_id']] = $row['distance'];
            }
            
            $channel->EE->TMPL->tagparams['points'] = $points;
            
            if ($channel->EE->config->item('relaxed_track_views') === 'y' && $channel->query->num_rows() == 1) {
                $channel->hit_tracking_id = $channel->query->row('entry_id');
            }
            
            $channel->track_views();
            
            $channel->EE->load->library('typography');
            $channel->EE->typography->initialize();
            $channel->EE->typography->convert_curly = FALSE;
            
            if ($channel->enable['categories'] == TRUE) {
                $channel->fetch_categories();
                
            }
            
            $channel->parse_channel_entries();
            
            if ($channel->enable['pagination'] == TRUE) {
                if (version_compare(APP_VER, '2.4', '>='))
				{
					$channel->add_pagination_data();
				}else {
					$channel->fetch_pagination_data();
				}  
            }
            
            
            if (count($channel->EE->TMPL->related_data) > 0 && count($channel->related_entries) > 0) {
                $channel->parse_related_entries();
            }
            
            if (count($channel->EE->TMPL->reverse_related_data) > 0 && count($channel->reverse_related_entries) > 0) {
                $channel->parse_reverse_related_entries();
            }
            $return_data = str_replace($entries[0], $channel->return_data, $tagdata);
        }
        
        
        
        return $return_data;
        
        
    }
	function fetch_cache($identifier = '')
	{
		$tag = ($identifier == '') ? $this->EE->TMPL->tagproper : $this->EE->TMPL->tagproper.$identifier;

		if ($this->EE->TMPL->fetch_param('dynamic_parameters') !== FALSE && isset($_POST) && count($_POST) > 0)
		{

		
		}

		$cache_file = APPPATH.'cache/'.$this->geo_cache_dir.md5($tag.$this->uri);

		if ( ! $fp = @fopen($cache_file, FOPEN_READ))
		{
			return FALSE;
		}

		flock($fp, LOCK_SH);
		$sql = @fread($fp, filesize($cache_file));
		flock($fp, LOCK_UN);
		fclose($fp);

		return $sql;
	} 	/**
	  *  Save Cache
	  */
	function save_cache($sql, $identifier = '')
	{
		$tag = ($identifier == '') ? $this->EE->TMPL->tagproper : $this->EE->TMPL->tagproper.$identifier;

		$cache_dir  = APPPATH.'cache/'.$this->geo_cache_dir;
		$cache_file = $cache_dir.md5($tag.$this->uri);

		if ( ! @is_dir($cache_dir))
		{
			if ( ! @mkdir($cache_dir, DIR_WRITE_MODE))
			{
				return FALSE;
			}

			if ($fp = @fopen($cache_dir.'/index.html', FOPEN_WRITE_CREATE_DESTRUCTIVE))
			{
				fclose($fp);				
			}

			@chmod($cache_dir, DIR_WRITE_MODE);
		}

		if ( ! $fp = @fopen($cache_file, FOPEN_WRITE_CREATE_DESTRUCTIVE))
		{
			return FALSE;
		}

		flock($fp, LOCK_EX);
		fwrite($fp, $sql);
		flock($fp, LOCK_UN);
		fclose($fp);
		@chmod($cache_file, FILE_WRITE_MODE);

		return TRUE;
	}
	
    function form()
    {
        $tagdata = $this->EE->TMPL->tagdata;
        
        $result_page = (!$this->EE->TMPL->fetch_param('result_page')) ? $this->EE->functions->fetch_current_uri() : $this->EE->TMPL->fetch_param('result_page');
        $long        = (!$this->EE->TMPL->fetch_param('long')) ? '' : $this->EE->TMPL->fetch_param('long');
        $lat         = (!$this->EE->TMPL->fetch_param('lat')) ? '' : $this->EE->TMPL->fetch_param('lat');
        $unit        = (!$this->EE->TMPL->fetch_param('unit')) ? 'miles' : $this->EE->TMPL->fetch_param('unit');
        
        $form_details = array(
            'action' => $result_page,
            'name' => 'mx_locator',
            'secure' => FALSE,
            'id' => 'mx_locator',
            'hidden_fields' => array(
                'unit' => $unit,
                'result_page' => $result_page,
                'long' => $long,
                'lat' => $lat,
                ''
            )
        );
        
        $r = $this->EE->functions->form_declaration($form_details);
        $r .= $this->EE->TMPL->tagdata;
        
        $r .= "</form>";
        return $this->return_data = $r;
    }

    function GetLatLong($query, $mode)
    {

		$query = str_replace(" ", "+", trim($query));
        $xml_url = "http://maps.googleapis.com/maps/api/geocode/xml?address=".$query."&ie=utf-8&oe=utf-8&sensor=false";

	   if (!$out = $this->_readCache(md5($query)))
       {


        if (ini_get('allow_url_fopen')) {
           $xml = @simplexml_load_file($xml_url);
        } else {
            $ch = curl_init($xml_url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $xml_raw = curl_exec($ch);
            $xml     = simplexml_load_string($xml_raw);
        }
	  
        if (is_object($xml) AND ($xml instanceof SimpleXMLElement) AND $xml->Response->Status == "OVER_QUERY_LIMIT") {
            $out = array($this->default_long, $this->default_lat);
            $this->_createCacheFile(json_encode($out), md5($query));
            $this->EE->TMPL->log_item("mx_google_map: OVER_QUERY_LIMIT");
        } else {
            if (is_object($xml) AND ($xml instanceof SimpleXMLElement) AND (int) $xml->Response->Status->code === 200) {
                $out = ($mode == 1) ? $xml->Response->Placemark->address : explode(',', $xml->Response->Placemark->Point->coordinates);

    			$this->_createCacheFile(json_encode($out), md5($query));
                return $out;
            } else {
                return false;
            }
        }   
        }else {
            $out = json_decode($out, true);
        }

        return $out;
        
    }


    private function _createCacheFile($data, $key)
    {
        $cache_path = APPPATH.'cache/' . $this->geo_cache_dir;
        $filepath = $cache_path ."/". $key;

        if (! is_dir($cache_path))
        {
            mkdir($cache_path . "", 0777, TRUE);
        }
        if (! is_really_writable($cache_path))
        {
            return;
        }
        if ( ! $fp = fopen($filepath, FOPEN_WRITE_CREATE_DESTRUCTIVE))
        {
            log_message('error', "Unable to write cache file: ".$filepath);
            return;
        }

        flock($fp, LOCK_EX);
        fwrite($fp, $data);
        flock($fp, LOCK_UN);
        fclose($fp);
        chmod($filepath, DIR_WRITE_MODE);

        log_message('debug', "Cache file written: " . $filepath);
    }

    /**
     * _readCache function.
     *
     * @access private
     * @param mixed $key
     * @return void
     */
    private function _readCache($key)
    {
        $cache = FALSE;
        $cache_path = APPPATH.'cache/' . $this->geo_cache_dir ;
        $filepath = $cache_path ."/". $key;

        if ( ! file_exists($filepath))
        {
            return FALSE;
        }
        if ( ! $fp = fopen($filepath, FOPEN_READ))
        {
            @unlink($filepath);
            log_message('debug', "Error reading cache file. File deleted");
            return FALSE;
        }
        if ( ! filesize($filepath))
        {
            @unlink($filepath);
            log_message('debug', "Error getting cache file size. File deleted");
            return FALSE;
        }

        $cache_timeout = $this->cache_lifetime + (rand(0, 10) * 3600);

        if ( (filemtime($filepath) + $cache_timeout) < time() )
        {
            @unlink($filepath);
            log_message('debug', "Cache file has expired. File deleted");
            return FALSE;
        }

        flock($fp, LOCK_SH);
        $cache = fread($fp, filesize($filepath));
        flock($fp, LOCK_UN);
        fclose($fp);

        return $cache;
    }    
    /**
     * Helper funciton for template logging
     */
    function _error_log($msg)
    {
        $this->EE->TMPL->log_item("mx_google_map ERROR: " . $msg);
    }
}

if (!class_exists('Channel')) {
    require PATH_MOD . 'channel/mod.channel.php';
}
/* End of file mod.mx_google_map.php */
/* Location: ./system/expressionengine/third_party/mx_google_map/mod.mx_google_map.php */