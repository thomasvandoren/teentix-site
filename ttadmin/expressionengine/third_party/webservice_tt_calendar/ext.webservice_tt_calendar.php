<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Webservice_tt_calendar_ext
{
    var $name = 'Webservice TT Calendar Extension';
    var $version = '1.0';
    var $description = '';
    var $settings_exist = 'n';
    var $docs_url = '';

    /**
     * Webservice_tt_calendar_ext constructor.
     * @param array $settings
     */
    public function __construct($settings = null)
    {
    }

    public function activate_extension()
    {
        $entry_row_data = array(
            'class' => __CLASS__,
            'method' => 'webservice_entry_row',
            'hook' => 'webservice_entry_row',
            'priority' => 10,
            'version' => $this->version,
            'enabled' => 'y'
        );
        ee()->db->insert('extensions', $entry_row_data);

        $search_entry_end_data = array(
            'class' => __CLASS__,
            'method' => 'webservice_search_entry_end',
            'hook' => 'webservice_search_entry_end',
            'priority' => 10,
            'version' => $this->version,
            'enabled' => 'y'
        );
        ee()->db->insert('extensions', $search_entry_end_data);


        $calendar_range_end_data = array(
            'class' => __CLASS__,
            'method' => 'webservice_calendar_range_end',
            'hook' => 'webservice_calendar_range_end',
            'priority' => 10,
            'version' => $this->version,
            'enabled' => 'y'
        );
        ee()->db->insert('extensions', $calendar_range_end_data);

        return true;
    }

    public function update_extension($current = '')
    {
        if ($current == '' OR $current == $this->version)
        {
            return FALSE;
        }

        if ($current < '1.0')
        {
            // Update to version 1.0
        }

        ee()->db->where('class', __CLASS__);
        ee()->db->update(
            'extensions',
            array('version' => $this->version)
        );

        return true;
    }

    public function disable_extension() {
        ee()->db->where('class', __CLASS__);
        ee()->db->delete('extensions');
    }

    public function webservice_entry_row($data = null, $fields = array())
    {
        $entry_id = $data['entry_id'];

        //loop over the fields to get the relationship or playa field
        if (!empty($fields)) {
            foreach ($fields as $field_name => $field) {
                if ($field['field_type'] == 'calendar') {
                    //is there data or is the field set
                    if (isset($data[$field_name]) && !empty($data[$field_name])) {
                        $calendar_id = $data[$field_name];

                        ee()->db->select('*')->from('exp_calendar_events');
                        ee()->db->where('calendar_id', $calendar_id);
                        ee()->db->where('entry_id', $entry_id);
                        $query = ee()->db->get();

                        if ($query->num_rows() > 0) {
                            foreach($query->result_array() as $key => $row) {
                                $data[$field_name] = $row;
                                break; // in case there somehow is more than one row
                            }
                        }
                    }
                } else if ($field['field_type'] == 'rel') {
                    //is there data or is the field set
                    if (isset($data[$field_name]) && !empty($data[$field_name])) {
                        $rel_id = $data[$field_name];
                        ee()->db->select('rel_child_id')->from('exp_relationships')->where('rel_id', $rel_id);
                        $query = ee()->db->get();

                        if ($query->num_rows() > 0) {
                            foreach($query->result_array() as $key => $row){
                                $rel_entry_id = $row['rel_child_id'];
                                $new_data = ee()->webservice_lib->get_entry($rel_entry_id, array('*'), true);

                                // PHP can deal with an empty string or a list of objects. Other, statically typed languages don't deal
                                // with that.
                                foreach (array('org_email', 'location_email') as $k) {
                                    if (array_key_exists($k, $new_data) && $new_data[$k] == "") {
                                        unset($new_data[$k]);
                                    }
                                }

                                $data[$field_name] = $new_data;
                                break; // in case there somehow is more than one row
                            }
                        }
                    }
                } else if ($field['field_type'] == 'matrix') {
                    //is there data or is the field set
                    if (isset($data[$field_name]) && !empty($data[$field_name])) {
                        $image_array = $data[$field_name];

                        foreach($image_array as $key => $row) {
                            if (array_key_exists('image', $row)) {
                                $data[$field_name][$key]['url'] = ee()->typography->parse_file_paths($row['image']);
                            }
                        }
                    }
                }

                // PHP can deal with a false value or an object. Other, statically typed languages don't deal with
                // that.
                if ($field_name == 'event_featured_image' && array_key_exists($field_name, $data) && $data[$field_name] == false) {
                    $data[$field_name] = null;
                }
            }
        }
        return $data;
    }

    public function webservice_search_entry_end($data = null, $fields = array())
    {
        return $this->sanitize_fields($data);
    }

    public function webservice_calendar_range_end($data = null, $fields = array())
    {
        $events = $data['events'];
        $data['events'] = $this->sanitize_fields($events);
        return $data;
    }

    private function sanitize_fields($data) {
        foreach ($data as $i => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $possible_falsey_keys = array('event_venue', 'event_organization');
            foreach ($possible_falsey_keys as $k) {
                if (array_key_exists($k, $entry) && $entry[$k] == '0') {
                    $data[$i][$k] = null;
                }
            }

            if (array_key_exists('event_venue', $entry)) {
                $event_venue = $entry['event_venue'];
                if (is_array($event_venue) && array_key_exists('location_logo', $event_venue)
                        && $event_venue['location_logo'] == false) {
                    $data[$i]['event_venue']['location_logo'] = null;
                }
            }
        }
        return $data;
    }
}


/* End of file ext.webservice_tt_calendar.php */
/* Location: ./webservice_tt_calendar/libraries/ext.webservice_tt_calendar.php */
