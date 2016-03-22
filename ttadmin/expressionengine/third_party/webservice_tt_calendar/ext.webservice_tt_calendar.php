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
        $data = array(
            'class' => __CLASS__,
            'method' => 'webservice_entry_row',
            'hook' => 'webservice_entry_row',
            'priority' => 10,
            'version' => $this->version,
            'enabled' => 'y'
        );

        ee()->db->insert('extensions', $data);

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
            }
        }
        return $data;
    }
}


/* End of file ext.webservice_tt_calendar.php */
/* Location: ./webservice_tt_calendar/libraries/ext.webservice_tt_calendar.php */
