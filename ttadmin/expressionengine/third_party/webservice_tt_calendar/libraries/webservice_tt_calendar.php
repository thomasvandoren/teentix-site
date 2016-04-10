<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * TeenTix calendar api
 *
 * @package		FIXME
 * @category	Modules
 * @author		FIXME
 * @link		FIXME
 * @license  	FIXME
 * @copyright 	Copyright (c) 2016 FIXME
 */

/**
 * Include the config file
 */
require_once PATH_THIRD.'webservice/config.php';

class Webservice_tt_calendar
{
    public $limit;
    public $offset;
    public $total_results;
    public $absolute_results;

    public function __construct()
    {
        ee()->load->library('api/entry/fieldtypes/webservice_fieldtype');
    }

    public function calendar_range($post_data = array()) {
        $post_data = Webservice_helper::add_hook('calendar_range_start', $post_data);

        /** ---------------------------------------
        /**  Validate data
        /** ---------------------------------------*/
        $data_errors = array();

        /** ---------------------------------------
        /**  Set the site_id is empty
        /** ---------------------------------------*/
        if(!isset($post_data['site_id']) || $post_data['site_id'] == '') {
            $post_data['site_id'] = 1;
        }

        /** ---------------------------------------
        /**  Set the show_fields param
        /** ---------------------------------------*/
        if(!isset($post_data['output_fields']) || $post_data['output_fields'] == '') {
            $post_data['output_fields'] = array();
        }
        else
        {
            $post_data['output_fields'] = explode("|", $post_data['output_fields']);
        }

        //save it to the cache
        ee()->session->set_cache('webservice', 'output_fields', $post_data['output_fields']);

        /** ---------------------------------------
        /**  Get the fields
        /** ---------------------------------------*/
        $this->fields = $this->_get_fieldtypes();

        $start_date = null;
        $days = null;
        foreach ($post_data as $field_name => $field_value) {
            if ($field_name == 'start_date') {
                $start_date = $field_value;
            } else if ($field_name == 'days') {
                $days = $field_value;
            }
        }
        $calendar_events = $this->_get_events_in_range($start_date, $days);


        if(!$calendar_events || !is_array($calendar_events))
        {
            /** ---------------------------------------
            /** return response
            /** ---------------------------------------*/
            if(!$calendar_events)
            {
                return array(
                    'message' => 'No Entry found'
                );
            }
            else
            {
                return array(
                    'message' => 'The following fields are not filled in: '.$calendar_events
                );
            }
        }
        else
        {
            $return_entry_data = array();

            foreach($calendar_events as $data)
            {
                $entry_id = $data['entry_id'];

                /** ---------------------------------------
                /**  get the entry data and check if the entry exists
                /**  Also get the "categories" and preform the ee()->webservice_fieldtype->pre_process() call
                /** ---------------------------------------*/
                $entry_data = ee()->webservice_lib->get_entry($entry_id, array('*'), true);

//				/** ---------------------------------------
//				/** Get the categories
//				/** ---------------------------------------*/
//				$entry_data['categories'] = (ee()->webservice_category_model->get_entry_categories(array($entry_data['entry_id'])));
//
//				/** ---------------------------------------
//				/**  Process the data per field
//				/** ---------------------------------------*/
//				if(!empty($this->fields))
//				{
//					foreach($this->fields as $key=>$val)
//					{
//						if(isset($entry_data[$val['field_name']]))
//						{
//							$entry_data[$val['field_name']] = ee()->webservice_fieldtype->pre_process($entry_data[$val['field_name']], $val['field_type'], $val['field_name'], $val, null, 'search_entry', $entry_id);
//						}
//					}
//				}


                /* -------------------------------------------
                /* 'webservice_search_entry_end' hook.
                /*  - Added: 3.2
                */
                $entry_data = Webservice_helper::add_hook('calendar_range_per_entry', $entry_data, false, $this->fields);
                // -------------------------------------------

                /* -------------------------------------------
                /* 'webservice_entry_row' hook.
                /*  - Added: 3.5
                */
                $entry_data = Webservice_helper::add_hook('entry_row', $entry_data, false, $this->fields);
                // -------------------------------------------

                //assign the data to the array
                $return_entry_data[] = $entry_data;

            };

            /* -------------------------------------------
            /* 'webservice_search_entry_end' hook.
            /*  - Added: 2.2
            */
            $return_entry_data = Webservice_helper::add_hook('calendar_range_end', $return_entry_data, false, $post_data);
            // -------------------------------------------

            /** ---------------------------------------
            /** Lets collect all the entry_ids so we can return
            /** ---------------------------------------*/
            $entry_ids = array();
            foreach($return_entry_data as $row)
            {
                $entry_ids[] = $row['entry_id'];
            }

            /** ---------------------------------------
            /** return response
            /** ---------------------------------------*/
            $this->service_error['success_read']['metadata'] = array(
                'id' => implode('|', $entry_ids),
                'limit' => $this->limit,
                'offset' => $this->offset,
                'total_results' => $this->total_results,
                'absolute_results' => $this->absolute_results
            );
            $this->service_error['success_read']['success'] = true;
            $this->service_error['success_read']['data'] = $return_entry_data;
            return $this->service_error['success_read'];
        }
    }

    // FIXME: implement me! (thomasvandoren, 2016-04-02)
    private function _get_events_in_range($start_date_str, $days_str) {
        if ($start_date_str == null || $days_str == null) {
            return '"start_date" and "days"';
        }
        $start_date = new DateTime($start_date_str, new DateTimeZone('UTC'));
        $days = new DateInterval('P'.$days_str.'D');
        $last_date = new DateTime($start_date_str, new DateTimeZone('UTC'));
        $last_date->add($days);

        $db = ee()->db;

        $db_start_date = $db->escape($start_date->format('Ymd'));
        $db_last_date = $db->escape($last_date->format('Ymd'));

        $db->select('entry_id')->from('exp_calendar_events');
        $where = '(last_date = 0 AND start_date >= '.$db_start_date.' AND start_date <='.$db_last_date.') OR '.
            '(start_date <= '.$db_last_date.' AND last_date >= '.$db_start_date.') OR '.
            '(last_date >= '.$db_start_date.' AND start_date <= '.$db_last_date.')';
        $db->where($where);
        $query = $db->get();

        $row_count = $query->num_rows();
        $results = array();
        if ($row_count > 0) {
            foreach ($query->result_array() as $key => $row) {
                $entry_id = $row['entry_id'];
                $results[] = ee()->webservice_lib->get_entry($entry_id, array('*'), true);
            }
        }

        return $results;
    }

    /**
     * Search an entry based on the given values
     *
     * @access	public
     * @param	parameter list
     * @return	void
     */
    private function _get_fieldtypes()
    {
        $channel_id = isset($this->channel['channel_id']) ? $this->channel['channel_id'] : null ;
        $channel_fields = ee()->channel_data->get_channel_fields($channel_id)->result_array();
        $fields = ee()->channel_data->utility->reindex($channel_fields, 'field_name');
        return $fields;
    }

}