<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * TeenTix favorites api
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

if (! class_exists('Module_build_favorites')) {
    require_once PATH_THIRD.'favorites/addon_builder/module_builder.php';
}

if (! class_exists('Favorites')) {
    require_once PATH_THIRD.'favorites/mod.favorites.php';
}


class Webservice_tt_favorites extends Module_builder_favorites
{
    public $limit;
    public $offset;
    public $total_results;
    public $absolute_results;

    public function __construct()
    {
        parent::__construct('favorites');
        ee()->load->library('api/entry/fieldtypes/webservice_fieldtype');
    }

    public function read_favorites($post_data = array()) {
        $post_data = Webservice_helper::add_hook('read_favorites_start', $post_data);

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
        $site_id = $post_data['site_id'];

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

        $member_id = ee()->session->userdata['member_id'];

        $favorite_events = $this->_get_favorites($member_id, $site_id);

        if(!$favorite_events || !is_array($favorite_events))
        {
            /** ---------------------------------------
            /** return response
            /** ---------------------------------------*/
            if(!$favorite_events)
            {
                return array(
                    'message' => 'No Entry found'
                );
            }
            else
            {
                return array(
                    'message' => 'The following fields are not filled in: '.$favorite_events
                );
            }
        }
        else
        {
            $return_entry_data = array();

            foreach($favorite_events as $entry_id)
            {
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
                $entry_data = Webservice_helper::add_hook('read_favorites_per_entry', $entry_data, false, $this->fields);
                // -------------------------------------------

                /* -------------------------------------------
                /* 'webservice_entry_row' hook.
                /*  - Added: 3.5
                */
                $entry_data = Webservice_helper::add_hook('entry_row', $entry_data, false, $this->fields);
                // -------------------------------------------

                //assign the data to the array
                $return_entry_data[$entry_id] = $entry_data;

            };

            /* -------------------------------------------
            /* 'webservice_search_entry_end' hook.
            /*  - Added: 2.2
            */
            $return_entry_data = Webservice_helper::add_hook('read_favorites_end', $return_entry_data, false, $post_data);
            // -------------------------------------------

            /** ---------------------------------------
            /** Lets collect all the entry_ids so we can return
            /** ---------------------------------------*/
            $entry_ids = array_keys($return_entry_data);

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

    public function create_favorite($post_data = array()) {
        $post_data = Webservice_helper::add_hook('create_favorite_start', $post_data);

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
        $site_id = $post_data['site_id'];

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

        $member_id = ee()->session->userdata['member_id'];

        $entry_id_to_favorite = null;
        if (array_key_exists('entry_id', $post_data)) {
            $entry_id_to_favorite = $post_data['entry_id'];
        }
        $saved_event = $this->_create_favorite($member_id, $entry_id_to_favorite, $site_id);

        if(is_string($saved_event) || !$saved_event) {
            /** ---------------------------------------
            /** return response
            /** ---------------------------------------*/
            if(!$saved_event)
            {
                return array(
                    'message' => 'No Entry found'
                );
            }
            else
            {
                return array(
                    'message' => 'The following fields are not filled in: '.$saved_event
                );
            }
        }
        else
        {
            $return_entry_data = $saved_event;

            /* -------------------------------------------
            /* 'webservice_search_entry_end' hook.
            /*  - Added: 2.2
            */
            $return_entry_data = Webservice_helper::add_hook('create_favorite_end', $return_entry_data, false, $post_data);
            // -------------------------------------------

            /** ---------------------------------------
            /** return response
            /** ---------------------------------------*/
            $this->service_error['success_read']['metadata'] = array(
                'id' => $entry_id_to_favorite,
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

    public function delete_favorite($post_data = array()) {
        $post_data = Webservice_helper::add_hook('delete_favorite_start', $post_data);

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
        $site_id = $post_data['site_id'];

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

        $member_id = ee()->session->userdata['member_id'];

        $entry_id_to_delete = null;
        if (array_key_exists('entry_id', $post_data)) {
            $entry_id_to_delete = $post_data['entry_id'];
        }
        $deleted_event = $this->_delete_favorite($member_id, $entry_id_to_delete, $site_id);

        if(is_string($deleted_event) || !$deleted_event) {
            /** ---------------------------------------
            /** return response
            /** ---------------------------------------*/
            if(!$deleted_event)
            {
                return array(
                    'message' => 'No Entry found'
                );
            }
            else
            {
                return array(
                    'message' => 'The following fields are not filled in: '.$deleted_event
                );
            }
        }
        else
        {
            $return_entry_data = $deleted_event;

            /* -------------------------------------------
            /* 'webservice_search_entry_end' hook.
            /*  - Added: 2.2
            */
            $return_entry_data = Webservice_helper::add_hook('delete_favorite_end', $return_entry_data, false, $post_data);
            // -------------------------------------------

            /** ---------------------------------------
            /** return response
            /** ---------------------------------------*/
            $this->service_error['success_read']['metadata'] = array(
                'id' => $entry_id_to_delete,
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

    private function _get_favorites($member_id, $site_id) {
        $db = ee()->db;
        $db->select('entry_id')->from('exp_favorites');
        $db->where('member_id', $member_id);
        $db->where('site_id', $site_id);
        $query = $db->get();

        $row_count = $query->num_rows();
        $event_ids = array();
        if ($row_count > 0) {
            foreach ($query->result_array() as $key => $row) {
                $event_ids[] = $row['entry_id'];
            }
        }
        return $event_ids;
    }

    private function _create_favorite($member_id, $entry_id, $site_id) {
        if (!$entry_id) {
            return '"entry_id"';
        }
        $db = ee()->db;

        $db->select('entry_id')->from('exp_channel_data');
        $db->where('entry_id', $entry_id);
        $db->where('site_id', $site_id);
        $entry_count = $db->count_all_results();

        // Will return not found error.
        if ($entry_count <= 0) {
            return false;
        }

        $db->select('favorites_id')->from('exp_favorites');
        $db->where('member_id', $member_id);
        $db->where('entry_id', $entry_id);
        $db->where('site_id', $site_id);
        $existing_favorite_count = $db->count_all_results();

        // Will return success result.
        if ($existing_favorite_count > 0) {
            return true;
        }

        $row_data = array(
            'entry_id' => $entry_id,
            'member_id' => $member_id,
            'site_id' => $site_id,
        );
        $db->insert('exp_favorites', $row_data);
        return true;
    }

    private function _delete_favorite($member_id, $entry_id, $site_id) {
        if (!$entry_id) {
            return '"entry_id"';
        }
        $db = ee()->db;
        $db->select('entry_id')->from('exp_channel_data');
        $db->where('entry_id', $entry_id);
        $db->where('site_id', $site_id);
        $entry_count = $db->count_all_results();

        // Will return not found error.
        if ($entry_count <= 0) {
            return false;
        }

        $db->select('favorites_id')->from('exp_favorites');
        $db->where('member_id', $member_id);
        $db->where('entry_id', $entry_id);
        $db->where('site_id', $site_id);
        $existing_favorite_count = $db->count_all_results();

        // Will return success result.
        if ($existing_favorite_count <= 0) {
            return true;
        }

        $db->delete('exp_favorites', array(
            'entry_id' => $entry_id,
            'member_id' => $member_id,
            'site_id' => $site_id,
        ));
        return true;
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