<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Facebook Connect - Actions
 *
 * Handles all form submissions and action requests used on both user and CP areas of EE.
 *
 * @package		Solspace:Facebook Connect
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2013, Solspace, Inc.
 * @link		http://solspace.com/docs/facebook_connect
 * @license		http://www.solspace.com/license_agreement
 * @version		2.1.1
 * @filesource	fbc/act.fbc.php
 */

require_once 'addon_builder/module_builder.php';

class Fbc_actions extends Addon_builder_fbc
{
	public $api;

	// -------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */

	public function __construct()
	{
		parent::__construct();

		// --------------------------------------------
		//  Module Installed and What Version?
		// --------------------------------------------

		if ($this->database_version() == FALSE OR
			$this->version_compare($this->database_version(), '<', FBC_VERSION))
		{
			return;
		}
	}
	/* END */

	// -------------------------------------------------------

	/**
	 * Api
	 *
	 * Invoke the api object
	 *
	 * @access	public
	 * @return	boolean
	 */

	public function api()
	{
		if ( isset( $this->api->cached ) === TRUE ) return TRUE;

		// --------------------------------------------
		//  API Object
		// --------------------------------------------

		require_once $this->addon_path . 'api.fbc.php';

		$this->api = new Fbc_api();
	}

	/*	End api */

	// -------------------------------------------------------

	/**
	 * Check form hash
	 *
	 * Makes sure that a valid XID is present in the form POST
	 *
	 * @access		private
	 * @return		boolean
	 */

	public function _check_form_hash()
	{
		if ( ! $this->check_secure_forms())
		{
			return $this->error[]	= lang('not_authorized');
		}

		return TRUE;
	}

	/*	End check form hash */

	// -------------------------------------------------------

	/**
	 * Create member account
	 *
	 * This method accepts a facebook user id and creates a new EE member account with it.
	 *
	 * @access	public
	 * @return	boolean / numeric
	 */

	public function create_member_account( $uid = '', $member_data = array() )
	{
		// --------------------------------------------
		//	Validate
		// --------------------------------------------

		if ( $uid == '' OR empty( $member_data ) OR empty( $member_data['email'] ) OR empty( $member_data['username'] ) ) return FALSE;

		// --------------------------------------------
		//	'fbc_create_member_account_start' hook.
		// --------------------------------------------

		if ( $this->EE->extensions->active_hook('fbc_create_member_account_start') === TRUE )
		{
			$edata = $this->EE->extensions->universal_call( 'fbc_create_member_account_start', $uid,  $member_data );
			if ($this->EE->extensions->end_script === TRUE) return FALSE;
		}

		// --------------------------------------------
		//	Prepare screen name
		// --------------------------------------------

		if ( empty( $member_data['screen_name'] ) )
		{
			$member_data['screen_name']	= $member_data['username'];
		}

		// --------------------------------------------
		//	Start data
		// --------------------------------------------

		$data	= array(
			'email'						=> $member_data['email'],
			'screen_name'				=> $member_data['screen_name'],
			'username'					=> $member_data['username'],
			'facebook_connect_user_id'	=> $uid,
			'password'					=> $this->EE->functions->random('encrypt'),
			'unique_id'					=> $this->EE->functions->random('encrypt'),
			'ip_address'				=> $this->EE->input->ip_address(),
			'join_date'					=> $this->EE->localize->now,
			'last_visit'				=> $this->EE->localize->now
		);

		if ( ! empty( $member_data['password'] ) )
		{
			//$data['password']	= $this->EE->functions->hash( stripslashes( $member_data['password'] ) );

			$pass_data = $this->EE->auth->hash_password(stripslashes( $member_data['password']));

			$data['password']    	= $pass_data['password'];
			$data['salt']    		= $pass_data['salt'];
		}

		// --------------------------------------------
		//	Set member group
		// --------------------------------------------

		$data['group_id'] = ( ! empty( $member_data['group_id'] ) ) ? $member_data['group_id']: $this->EE->config->item('fbc_member_group');

		// --------------------------------------------
		//	We generate an authorization code if the member needs to self-activate
		// --------------------------------------------

		if ( $this->EE->config->item('fbc_account_activation') == 'fbc_email_activation' )
		{
			$data['authcode'] = $this->EE->functions->random('alpha', 10);
		}

		// --------------------------------------------
		//	Default timezone
		// --------------------------------------------

		if ( empty( $member_data['timezone'] ) )
		{
			$data['timezone'] = 'UTC';
		}

		// --------------------------------------------
		//	Insert basic member data
		// --------------------------------------------

		$this->EE->db->query( $this->EE->db->insert_string('exp_members', $data) );

		$data['member_id'] = $this->EE->db->insert_id();

		// --------------------------------------------
		//	Prepare custom fields
		// --------------------------------------------

		$cust_fields['member_id'] = $data['member_id'];

		$custom_member_fields	= $this->data->get_member_fields();

		foreach ( $custom_member_fields as $name => $field_data )
		{
			if ( ! empty( $member_data[ 'm_field_id_' . $field_data['id'] ] ) )
			{
				$cust_fields[ 'm_field_id_' . $field_data['id'] ]	= $member_data[ 'm_field_id_' . $field_data['id'] ];
			}
			elseif ( ! empty( $member_data[ $name ] ) )
			{
				$cust_fields[ 'm_field_id_' . $field_data['id'] ]	= $member_data[ $name ];
			}
		}

		// --------------------------------------------
		//	Insert custom fields
		// --------------------------------------------

		$this->EE->db->query( $this->EE->db->insert_string('exp_member_data', $cust_fields) );

		// --------------------------------------------
		//	Create a record in the member
		//	homepage table
		// --------------------------------------------

		$this->EE->db->query( $this->EE->db->insert_string('exp_member_homepage', array( 'member_id' => $data['member_id'] ) ) );

		// --------------------------------------------
		//	Update global member stats
		// --------------------------------------------

		if ($this->EE->config->item('req_mbr_activation') == 'none')
		{
			$this->EE->stats->update_member_stats();
		}

		// --------------------------------------------
		//	'fbc_member_member_register' hook.
		// --------------------------------------------

		if ( $this->EE->extensions->active_hook('fbc_member_member_register') === TRUE )
		{
			$edata = $this->EE->extensions->universal_call('fbc_member_member_register', $data);
			if ($this->EE->extensions->end_script === TRUE) return FALSE;
		}

		// --------------------------------------------
		//	Return data
		// --------------------------------------------

		return $data;
	}

	/*	End create member account */

	// -------------------------------------------------------

	/**
	 * EE login
	 *
	 * This method takes an EE member id and logs that person in.
	 *
	 * @access	public
	 * @return	boolean
	 */

	public function ee_login( $member_id = '' )
	{
		// --------------------------------------------
		//	Run security tests
		// --------------------------------------------

		if ( $this->_security() === FALSE )
		{
			return FALSE;
		}

		//--------------------------------------------
		//	2.2.0 Auth lib
		//--------------------------------------------

		$this->EE->load->library('auth');

		// This should go in the auth lib.
		if ( ! $this->EE->auth->check_require_ip())
		{
			$this->error[]	= lang('not_authorized');
			return FALSE;
		}


		// --------------------------------------------
		//	'fbc_member_login_start' hook.
		// --------------------------------------------

		if ( $this->EE->extensions->active_hook('fbc_member_login_start') === TRUE )
		{
			$edata = $this->EE->extensions->universal_call('fbc_member_login_start');
			if ($this->EE->extensions->end_script === TRUE) return FALSE;
		}

		// --------------------------------------------
		//	Kill old sessions first
		// --------------------------------------------

		$this->EE->session->gc_probability = 100;

		$this->EE->session->delete_old_sessions();

		// --------------------------------------------
		//	Use Facebook's session expiration as our own, or set to one day if there's any trouble.
		// --------------------------------------------

		$this->api();

		$this->api->connect_to_api();

		$expire = ( isset( $this->api->user['expires'] ) === TRUE AND is_numeric( $this->api->user['expires'] ) === TRUE ) ? 86400: $this->api->user['expires'] - time();

		$expire	= 86400;	// Let's do this for a while. Facebook can continually refresh the session it keeps for a user, but we are not going to try to continually update ours. Let's just give the user some breathing room.

		// --------------------------------------------
		//	Get member data
		// --------------------------------------------

		if ( ( $member_data = $this->data->get_member_data_from_member_id( $member_id ) ) === FALSE )
		{
			return FALSE;
		}

		// --------------------------------------------
		//  Is the member account pending?
		// --------------------------------------------

		if ( $member_data['group_id'] == 4 )
		{
			$this->EE->output->show_user_error('general', array(lang('mbr_account_not_active')));
		}

		// --------------------------------------------
		//  Do we allow multiple logins on the same account?
		// --------------------------------------------

		if ($this->EE->config->item('allow_multi_logins') == 'n')
		{
			$expire = time() - $this->EE->session->session_length;

			// See if there is a current session

			$result = $this->EE->db->query("SELECT ip_address, user_agent
								  FROM   exp_sessions
								  WHERE  member_id  = '".$member_data['member_id']."'
								  AND    last_activity > " . $this->EE->db->escape_str( $expire ) . "
								  AND	 site_id = '" . $this->EE->db->escape_str( $this->EE->config->item('site_id') ) . "'");

			// If a session exists, trigger the error message

			if ($result->num_rows() == 1)
			{
				$row	= $result->row_array();

				if ( $this->EE->session->userdata('ip_address') != $row['ip_address'] OR $this->EE->session->userdata('user_agent') != $row['user_agent'] )
				{
					$errors[] = lang('multi_login_warning');
				}
			}
		}

		// --------------------------------------------
		//  New auth method in EE 2.2.0
		// --------------------------------------------


		$member	= $this->EE->db->get_where(
			'members',
			array('member_id' => $member_data['member_id'])
		);

		$session 	= new Auth_result($member->row());

		if (is_callable(array($session, 'remember_me')))
		{
			$session->remember_me(60*60*24*182);
		}

		$session->start_session();

		// Update system stats
		$this->EE->load->library('stats');

		if ( ! $this->check_no($this->EE->config->item('enable_online_user_tracking')))
		{
			$this->EE->stats->update_stats();
		}


		// --------------------------------------------
		//	Log this
		// --------------------------------------------

		$this->log_to_cp( 'Logged in', $member_data );

		// --------------------------------------------
		//	'fbc_member_login_single' hook.
		// --------------------------------------------

		if ( $this->EE->extensions->active_hook('fbc_member_login_single') === TRUE )
		{
			$edata = $this->EE->extensions->universal_call('fbc_member_login_single', $member_data);
			if ($this->EE->extensions->end_script === TRUE) return FALSE;
		}

		// --------------------------------------------
		//	Return success
		// --------------------------------------------

		return TRUE;
	}

	/*	End EE login */

	// -------------------------------------------------------

	/**
	 * Log to CP
	 *
	 * @access	public
	 * @return	string
	 */

	public function log_to_cp( $msg = '', $member_data = array() )
	{
		return FALSE;

		if ( $msg == '' )
		{
			return FALSE;
		}

		$data = array(
			'id'         => '',
			'member_id'  => ( empty( $member_data['member_id'] ) ) ? '1': $member_data['member_id'],
			'username'   => ( empty( $member_data['username'] ) ) ? 'Solspace Facebook Connect Module': $member_data['username'],
			'ip_address' => $this->EE->input->ip_address(),
			'act_date'   => $this->EE->localize->now,
			'action'     => 'Facebook: ' . $msg
		 );

		$this->EE->db->insert('exp_cp_log', $data);
	}

	/*	End log to CP */

	// -------------------------------------------------------

	/**
	 * Passive registration
	 *
	 * @access	private
	 * @return	boolean
	 */

	public function passive_registration( $uid = '' )
	{
		// --------------------------------------------
		//	Do we allow new member registrations?
		// --------------------------------------------

		if ( $this->EE->config->item('allow_member_registration') == 'n' )
		{
			$this->error[]	= lang('registration_not_enabled');
			return FALSE;
		}

		//--------------------------------------------
		//	2.2.0 Auth lib
		//--------------------------------------------

		$this->EE->load->library('auth');

		// This should go in the auth lib.
		if ( ! $this->EE->auth->check_require_ip())
		{
			$this->error[]	= lang('not_authorized');
			return FALSE;
		}


		// --------------------------------------------
		//	Do we already have a record for this facebook user id?
		// --------------------------------------------

		if ( $this->data->get_member_id_from_facebook_user_id( $uid ) !== FALSE )
		{
			$this->error[]	= lang( 'fb_user_already_exists' );
			return FALSE;
		}

		// --------------------------------------------
		//	Create fake member data
		// --------------------------------------------

		$this->api();
		$default_meber_data	= array();
		$member_data		= array();

		$default_member_data['email']		= $member_data['email'] = md5( time() . $uid ) . '@facebook.com';
		$default_member_data['screen_name']	= $member_data['screen_name']	= 'facebook' . $uid;
		$default_member_data['username']	= $member_data['username']	= 'facebook' . $uid;

		if ( ( $data = $this->api->get_user_info() ) !== FALSE )
		{
			$member_data['email']	= ( empty( $data['email'] ) ) ? $member_data['email']: $data['email'];

			if ( ! empty( $data['name'] ) )
			{
				$member_data['username']	= strtolower( str_replace( ' ', '_', $data['name'] ) );
				$member_data['screen_name']	= $data['name'];
			}
			elseif ( ! empty( $data['first_name'] ) AND ! empty( $data['last_name'] ) )
			{
				$member_data['username']	= strtolower( str_replace( ' ', '_', $data['first_name'] . ' ' . $data['last_name'] ) );
				$member_data['screen_name']	= $data['first_name'] . ' ' . $data['last_name'];
			}
		}

		// --------------------------------------------
		//	Validate
		// --------------------------------------------

		$random_number	= rand(1,999);

		$validate = array(
			'val_type'		=> 'new', // new or update
			'fetch_lang'	=> TRUE,
			'require_cpw'	=> FALSE,
			'enable_log'	=> FALSE,
			'username'		=> $member_data['username'],
			'screen_name'	=> stripslashes( $member_data['screen_name'] ),
			'email'			=> $member_data['email']
		 );

		$this->EE->load->library('validate', $validate, 'validate');

		// --------------------------------------------
		//	Compensate for email
		// --------------------------------------------

		$this->EE->validate->validate_email();

		if ( count( $this->EE->validate->errors ) > 0 )
		{
			$member_data['email']	= $default_member_data['email'];
			$this->EE->validate->errors	= array();
		}

		// --------------------------------------------
		//	Compensate for screen name
		// --------------------------------------------

		$this->EE->validate->validate_screen_name();

		if ( count( $this->EE->validate->errors ) > 0 )
		{
			// --------------------------------------------
			//	Try once more
			// --------------------------------------------

			$member_data['screen_name']	= $member_data['screen_name'] . ' ' . $random_number;
			$this->EE->validate->screen_name	= $member_data['screen_name'];
			$this->EE->validate->errors	= array();

			$this->EE->validate->validate_screen_name();

			if ( count( $this->EE->validate->errors ) > 0 )
			{
				$member_data['screen_name']	= $default_member_data['screen_name'];
				$this->EE->validate->errors	= array();
			}
		}

		// --------------------------------------------
		//	Compensate for username
		// --------------------------------------------

		$this->EE->validate->validate_username();

		if ( count( $this->EE->validate->errors ) > 0 )
		{
			// --------------------------------------------
			//	Try once more
			// --------------------------------------------

			$member_data['username']	= $member_data['username'] . '_' . $random_number;
			$this->EE->validate->username	= $member_data['username'];
			$this->EE->validate->errors	= array();

			$this->EE->validate->validate_username();

			if ( count( $this->EE->validate->errors ) > 0 )
			{
				$member_data['username']	= $default_member_data['username'];
				$this->EE->validate->errors	= array();
			}
		}

		// --------------------------------------------
		//	Attempt to create account
		// --------------------------------------------

		if ( ( $member_id_data = $this->create_member_account( $uid, $member_data ) ) === FALSE )
		{
			return FALSE;
		}

		// --------------------------------------------
		//	Send admin notification
		// --------------------------------------------

		$this->send_admin_notification_of_registration( $member_data );

		// --------------------------------------------
		//	'fbc_passive_register_end' hook.
		// --------------------------------------------
		//	Additional processing when a member is created through the User Side
		// --------------------------------------------

		if ( $this->EE->extensions->active_hook('fbc_passive_register_end') === TRUE )
		{
			$edata = $this->EE->extensions->universal_call('fbc_passive_register_end', $this, $member_id);
			if ($this->EE->extensions->end_script === TRUE) return;
		}

		// --------------------------------------------
		//	Just log them in
		// --------------------------------------------

		if ( $this->ee_login( $member_id_data['member_id'] ) === FALSE )
		{
			return FALSE;
		}

		// --------------------------------------------
		//	Return
		// --------------------------------------------

		return TRUE;
	}

	/*	End passive registration */

	// -------------------------------------------------------

	/**
	 * Security
	 *
	 * @access	private
	 * @return	boolean
	 */

	public function _security()
	{
		// --------------------------------------------
		//	Is the user banned?
		// --------------------------------------------

		if ( $this->EE->session->userdata['is_banned'] === TRUE )
		{
			return $this->EE->output->show_user_error('general', lang('not_authorized'));
		}

		// --------------------------------------------
		//	Is the IP address and User Agent required?
		// --------------------------------------------

		if ( $this->EE->config->item('require_ip_for_posting') == 'y' )
		{
			if ( ( $this->EE->input->ip_address() == '0.0.0.0' OR $this->EE->session->userdata['user_agent'] == '' ) AND $this->EE->session->userdata['group_id'] != 1 )
			{
				return $this->EE->output->show_user_error('general', lang('not_authorized'));
			}
		}

		// --------------------------------------------
		//	Is the nation of the user banned?
		// --------------------------------------------

		$this->EE->session->nation_ban_check();

		// --------------------------------------------
		//	Blacklist / Whitelist Check
		// --------------------------------------------

		if ( $this->EE->blacklist->blacklisted == 'y' && $this->EE->blacklist->whitelisted == 'n' )
		{
			return $this->EE->output->show_user_error('general', lang('not_authorized'));
		}

		// --------------------------------------------
		//	Return
		// --------------------------------------------

		return TRUE;
	}

	/*	End security */

	// -------------------------------------------------------

	/**
	 * Send admin notification of registration
	 *
	 * Sends an email to the designated admins that a new member has registered.
	 *
	 * @access	public
	 * @return	boolean
	 */

	public function send_admin_notification_of_registration( $member_data = array() )
	{
		if ( $this->EE->config->item('new_member_notification') == 'y' AND $this->EE->config->item('mbr_notification_emails') != '' )
		{
			$name = ( $member_data['screen_name'] != '' ) ? $member_data['screen_name'] : $member_data['username'];

			$swap = array(
				'name'					=> $name,
				'site_name'				=> stripslashes( $this->EE->config->item('site_name') ),
				'control_panel_url'		=> $this->EE->config->item('cp_url'),
				'username'				=> $member_data['username'],
				'email'					=> $member_data['email']
			 );

			$template	= $this->EE->functions->fetch_email_template('admin_notify_reg');
			$email_tit	= $this->_var_swap($template['title'], $swap);
			$email_msg	= $this->_var_swap($template['data'], $swap);

			$notify_address = $this->EE->config->item('mbr_notification_emails');

			$this->EE->load->helper('string');
			$this->EE->load->helper('text');

			$notify_address	= reduce_multiples( $notify_address );

			// --------------------------------------------
			//	Send email
			// --------------------------------------------

			$this->EE->load->library('email');

			$this->EE->email->initialize();
			$this->EE->email->wordwrap = true;
			$this->EE->email->mailtype = 'plain';
			$this->EE->email->priority = '3';
			$this->EE->email->from( $this->EE->config->item('webmaster_email'), $this->EE->config->item('webmaster_name') );
			$this->EE->email->to( $notify_address );
			$this->EE->email->subject( $email_tit );
			$this->EE->email->message( entities_to_ascii($email_msg) );
			$this->EE->email->Send();
		}
	}

	/* End send admin notification of registration */

	// -------------------------------------------------------

	/**
	 * Send user activation email
	 *
	 * Sends an email to the registrant to allow them to activate their account.
	 *
	 * @access	public
	 * @return	boolean
	 */

	public function send_user_activation_email( $member_data = array() )
	{
		$qs = ( $this->EE->config->item('force_query_string') == 'y' ) ? '' : '?';

		$action_id  = $this->EE->functions->fetch_action_id('Fbc', 'activate_member');

		$name = ( ! empty( $member_data['screen_name'] ) ) ? $member_data['screen_name']: $member_data['username'];

		$swap = array(
						'name'				=> $name,
						'activation_url'	=> $this->EE->functions->fetch_site_index( 0, 0 ) . $qs . 'ACT=' . $action_id . '&id=' . $member_data['authcode'],
						'site_name'			=> stripslashes($this->EE->config->item('site_name')),
						'site_url'			=> $this->EE->config->item('site_url'),
						'username'			=> $member_data['username'],
						'email'				=> $member_data['email']
					 );

		$template = $this->EE->functions->fetch_email_template('mbr_activation_instructions');
		$email_tit = $this->_var_swap($template['title'], $swap);
		$email_msg = $this->_var_swap($template['data'], $swap);

		// --------------------------------------------
		//	Send email
		// --------------------------------------------

		$this->EE->load->library('email');
		$this->EE->load->helper('text');

		$this->EE->email->initialize();
		$this->EE->email->wordwrap = true;
		$this->EE->email->from( $this->EE->config->item('webmaster_email'), $this->EE->config->item('webmaster_name') );
		$this->EE->email->to( $member_data['email'] );
		$this->EE->email->subject( $email_tit );
		$this->EE->email->message( entities_to_ascii( $email_msg ) );
		$this->EE->email->Send();
	}

	/* End send user activation email */

	// -------------------------------------------------------

	/**
	 *	Variable Swapping
	 *
	 *	Available even when $TMPL is not
	 *
	 *	@access		public
	 *	@param		string
	 *	@param		array
	 *	@return		string
	 */

	public function _var_swap($str, $data)
	{
		if ( ! is_array($data))
		{
			return false;
		}

		foreach ($data as $key => $val)
		{
			$str = str_replace('{'.$key.'}', $val, $str);
		}

		return $str;
	}

	/* End _var_swap() */
}