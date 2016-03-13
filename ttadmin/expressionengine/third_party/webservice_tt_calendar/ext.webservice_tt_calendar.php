<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Webservice_tt_calendar_ext
{
    var $name = 'Webservice TT Calendar Extension';
    var $version = '1.0';
    var $description = '';
    var $settings_exist = 'y';
    var $docs_url = '';

    var $settings = array();

    /**
     * Webservice_tt_calendar_ext constructor.
     * @param array $settings
     */
    public function __construct(array $settings = null)
    {
        $this->settings = $settings != null ? $settings : array();
    }

    public function activate_extension()
    {
        $data = array(
            'class' => __CLASS__,
            'method' => 'webservice_entry_row',
            'hook' => 'webservice_entry_row',
            'settings' => serialize($this->settings),
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

    public function webservice_entry_row($data = null, $fields = array())
    {
        //loop over the fields to get the relationship or playa field
        if (!empty($fields)) {
            foreach ($fields as $field_name => $field) {
                if ($field['field_type'] == 'calendar') {
                    //is there data or is the field set
                    if (isset($data[$field_name]) && !empty($data[$field_name])) {
                        // FIXME: figure out where to get data for this... (thomasvandoren, 2016-03-12)
                        $new_data = array("some_calendar" => $data[$field_name]);
                        $data[$field_name] = $new_data;
                    }
                } else if ($field['field_type'] == 'rel') {
                    //is there data or is the field set
                    if (isset($data[$field_name]) && !empty($data[$field_name])) {
                        // FIXME: figure out where to get data for this... (thomasvandoren, 2016-03-12)
                        $data[$field_name] = array("some_rel" => $data[$field_name]);
                    }
                } else if ($field['field_type'] == 'relationship' || $field['field_type'] == 'playa') {
                    //is there data or is the field set
                    if (isset($data[$field_name]) && !empty($data[$field_name])) {
                        $new_data = array();

                        //get for each item the data
                        foreach ($data[$field_name] as $entry_data) {
                            $new_data[] = ee()->webservice_lib->get_entry($entry_data['entry_id'], array('*'), true);
                        }

                        //assign the data back
                        $data[$field_name] = $new_data;
                    }
                }
            }
        }
        return $data;
    }
}


/* End of file ext.webservice_tt_calendar.php */
/* Location: ./webservice_tt_calendar/libraries/ext.webservice_tt_calendar.php */
