<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(PATH_THIRD . 'freeform_tt_address_validation/vendor/guzzle.phar');

class Freeform_tt_address_validation_ext
{
    var $name = 'Freeform TT Address Validation Extension';
    var $version = '1.0';
    var $description = '';
    var $settings_exist = 'n';
    var $docs_url = '';

    var $lob_client;
    /**
     * Freeform_tt_address_validation_ext constructor
     * @param array $settings
     */
    public function __construct($settings = null)
    {
        $this->lob_client = new GuzzleHttp\Client(array('base_uri' => 'https://api.lob.com'));
    }

    public function activate_extension()
    {
        $address_validation_data = array(
            'class' => __CLASS__,
            'method' => 'validate_address',
            'hook' => 'freeform_module_validate_begin',
            'priority' => 10,
            'version' => $this->version,
            'enabled' => 'y'
        );
        ee()->db->insert('extensions', $address_validation_data);

        $save_mailing_address_data = array(
            'class' => __CLASS__,
            'method' => 'save_mailing_address',
            'hook' => 'freeform_module_insert_begin',
            'priority' => 10,
            'version' => $this->version,
            'enabled' => 'y'
        );
        ee()->db->insert('extensions', $save_mailing_address_data);
    }

    public function update_extension($current = '')
    {
        if ($current = '' OR $current == $this->version)
        {
            return FALSE;
        }

        if ($current < '1.0') {
            // update to version 1.0
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

    public function validate_address($errors, $obj) {
        //have other extensions already manipulated?
        if (ee()->extensions->last_call !== FALSE)
        {
            $errors = ee()->extensions->last_call;
        }

        //detect and add custom errors
        $addr_info = $this->get_address($_POST);
        if (count(array_keys($addr_info)) > 0) {
            $verified_address = $this->verify_address($addr_info);

            if (array_key_exists('message', $verified_address)) {
                $errors['address'] = $verified_address['message'] . ' ' . 'Try re-entering the address and double check it is correct. If you experience any issues, please report them to <a href="mailto:info@teentix.org">info@teentix.org</a>.';
            }
        }

        //must return error array
        return $errors;
    }

    public function save_mailing_address($inputs, $entry_id, $form_id, $obj) {
        //have other extensions already manipulated?
        if (ee()->extensions->last_call !== FALSE)
        {
            $inputs = ee()->extensions->last_call;
        }

        //custom input data
        $addr_info = $this->get_address($inputs);
        if (count(array_keys($addr_info)) > 0) {
            $verified_address = $this->verify_address($addr_info);

            // Store the mailing address values next to the originals.
            $inputs['mailing_street1'] = trim($verified_address['address']['address_line1'] . ' ' . $verified_address['address']['address_line2']);
            $inputs['mailing_city'] = $verified_address['address']['address_city'];
            $inputs['mailing_state'] = $verified_address['address']['address_state'];
            $inputs['mailing_zip_code'] = $verified_address['address']['address_zip'];
            $inputs['mailing_country'] = $verified_address['address']['address_country'];
        }

        //must return input array
        return $inputs;
    }

    private function verify_address($addr_info) {
        // Do not try to verify international addresses.
        if ($addr_info['address_country'] != 'US') {
            return array(
                'address' => array_merge(
                    array(
                        'address_line1' => '',
                        'address_line2' => '',
                        'address_city' => '',
                        'address_state' => '',
                        'address_zip' => '',
                        'address_country' => ''
                    ),
                    $addr_info
                )
            );
        }

        $default_err = 'Server failure';
        $resp_body = array('message' => $default_err);
        try {
            $response = $this->lob_client->request('POST', '/v1/verify', array(
                'auth' => array(ee()->config->item('lob_api_key'), ''),
                'json' => $addr_info
            ));
            $resp_body = json_decode($response->getBody()->getContents(), $assoc=TRUE);
        } catch (GuzzleHttp\Exception\RequestException $e) {
            if ($e->hasResponse()) {
                $err_body = json_decode($e->getResponse()->getBody()->getContents(), $assoc=TRUE);
                if (array_key_exists('error', $err_body) && array_key_exists('message', $err_body['error'])) {
                    $resp_body = array('message' => $err_body['error']['message'] . '.');
                } else {
                    $resp_body = array('message' => $default_err . ' (request error ' . $e->getCode() . ').');
                }
            } else {
                $resp_body = array('message' => $default_err . ' (no response).');
            }
        }
        return $resp_body;
    }

    private function get_address($post_data) {
        $addr_fields = array(
            'street1' => 'address_line1',
            'city' => 'address_city',
            'state' => 'address_state',
            'zip_code' => 'address_zip',
            'country' => 'address_country'
        );
        $result = array();
        foreach ($addr_fields as $post_field => $lob_field) {
            if (array_key_exists($post_field, $post_data)) {
                $result[$lob_field] = $post_data[$post_field];
            }
        }
        return $result;
    }
}
