<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Auth API
 *
 * @package		webservice
 * @category	Modules
 * @author		Rein de Vries <info@reinos.nl>
 * @link		http://reinos.nl/add-ons/webservice
 * @license  	http://reinos.nl/add-ons/commercial-license
 * @copyright 	Copyright (c) 2014 Reinos.nl Internet Media
 */

class Webservice_member_api
{
	//-------------------------------------------------------------------------

	/**
     * Constructor
    */
	public function __construct()
	{
		// load the stats class because this is not loaded because of the use of the extension
		ee()->load->library('stats'); 
		
		/** ---------------------------------------
		/** load the models
		/** ---------------------------------------*/
		ee()->load->model('member_model');
		
		//set the default data
		$this->_default_data();
	}

	//-------------------------------------------------------------------------

	/**
     * create_member
    */
	public function create_member($post_data = array())
	{		
		/** ---------------------------------------
		/**  can we add a new member, do we have the right for it
		/** ---------------------------------------*/
		if(ee()->session->userdata('can_admin_members') != 'y')
		{
			return array(
				'message' => 'You have no right to administrate members'
			);
		}

		/** ---------------------------------------
		/**  allow member registration
		/** ---------------------------------------*/
		if (ee()->config->item('allow_member_registration') == 'n')
		{
			return array(
				'message' => 'Member Registration has been disabled'
			);
		}

		/** ---------------------------------------
		/**  Restrict access to the Super Admin group
		/** ---------------------------------------*/
		if ($post_data['group_id'] == 1 && ee()->config->item('group_id') != 1)
		{
			return array(
				'message' => 'You dont have access to create a member for group 1'
			);
		}

		/** ---------------------------------------
		/**  Set the defaul globals
		/** ---------------------------------------*/
		$default = array(
			'username', 'password', 'password_confirm', 'email',
			'screen_name', 'url', 'location'
		);

		//assign them to a val
		foreach ($default as $val)
		{
			if ( ! isset($post_data[$val])) $post_data[$val] = '';
		}

		//screen name is the same as username if empty
		if ($post_data['screen_name'] == '')
		{
			$post_data['screen_name'] = $post_data['username'];
		}

		// Instantiate validation class
		if ( ! class_exists('EE_Validate'))
		{
			require APPPATH.'libraries/Validate.php';
		}

		/** ---------------------------------------
		/**  Start the validatiing
		/** ---------------------------------------*/
		$VAL = new EE_Validate(array(
			'member_id'			=> '',
			'val_type'			=> 'new', // new or update
			'fetch_lang' 		=> TRUE,
			'require_cpw' 		=> FALSE,
		 	'enable_log'		=> FALSE,
			'username'			=> trim_nbs($post_data['username']),
			'cur_username'		=> '',
			'screen_name'		=> trim_nbs($post_data['screen_name']),
			'cur_screen_name'	=> '',
			'password'			=> $post_data['password'],
		 	'password_confirm'	=> $post_data['password'],
		 	'cur_password'		=> '',
		 	'email'				=> trim($post_data['email']),
		 	'cur_email'			=> ''
		 ));

		/** ---------------------------------------
		/**  validate the username, screen_name, password and email
		/** ---------------------------------------*/
		$VAL->validate_username();
		$VAL->validate_screen_name();
		$VAL->validate_password();
		$VAL->validate_email();

		/** ---------------------------------------
		/**  Do we have any custom fields?
		/** ---------------------------------------*/
		$query = ee()->db->select('m_field_id, m_field_name, m_field_label, m_field_type, m_field_list_items, m_field_required')
						  ->get('member_fields');

		$cust_errors = array();
		$cust_fields = array();

		if ($query->num_rows() > 0)
		{
			foreach ($query->result_array() as $row)
			{
				$field_name = 'm_field_id_'.$row['m_field_id'];

				// Assume we're going to save this data, unless it's empty to begin with
				$valid = isset($post_data[$row['m_field_name']]) && $post_data[$row['m_field_name']] != '';

				// Basic validations
				if ($row['m_field_required'] == 'y' && ! $valid)
				{
					$cust_errors[] = $row['m_field_label'];
				}
				elseif ($row['m_field_type'] == 'select' && $valid)
				{
					// Ensure their selection is actually a valid choice
					$options = explode("\n", $row['m_field_list_items']);

					if (! in_array(htmlentities($post_data[$row['m_field_name']]), $options))
					{
						$valid = FALSE;
						$cust_errors[] =$row['m_field_label'];
					}
				}

				if ($valid)
				{
					$cust_fields[$field_name] = ee()->security->xss_clean($post_data[$row['m_field_name']]);
				}
			}
		}

		if (isset($post_data['email_confirm']) && $post_data['email'] != $post_data['email_confirm'])
		{
			$cust_errors[] = lang('mbr_emails_not_match');
		}

		//merge error to one array
		$errors = array_merge($VAL->errors, $cust_errors);

		/** ---------------------------------------
		/**  Return error when there are fields who are empty en shoulnd`t
		/** ---------------------------------------*/
		if(!empty($errors) || count($errors) > 0)
		{
			//generate error
			return array(
				'message' => $errors[0]
			);
		}

		ee()->load->helper('security');
		ee()->load->helper('url'); 

		/** ---------------------------------------
		/**  Assign the base query data
		/** ---------------------------------------*/
		$data = array(
			'username'		=> trim_nbs($post_data['username']),
			'password'		=> sha1($post_data['password']),
			'ip_address'	=> ee()->input->ip_address(),
			'unique_id'		=> ee()->functions->random('encrypt'),
			'join_date'		=> ee()->localize->now,
			'email'			=> trim_nbs($post_data['email']),
			'screen_name'	=> trim_nbs($post_data['screen_name']),
			'url'			=> prep_url($post_data['url']),
			'location'		=> isset($post_data['location']) ? $post_data['location'] : '' ,

			// overridden below if used as optional fields
			'language'		=> (ee()->config->item('deft_lang')) ?
									ee()->config->item('deft_lang') : 'english',
            //			'date_format'	=> ee()->config->item('date_format') ?
            //					 				ee()->config->item('date_format') : '%n/%j/%y',
			'time_format'	=> ee()->config->item('time_format') ?
									ee()->config->item('time_format') : '12',
            //			'include_seconds' => ee()->config->item('include_seconds') ?
            //									ee()->config->item('include_seconds') : 'n',
			'timezone'		=> ee()->config->item('default_site_timezone')
		);

		/** ---------------------------------------
		/**  Set member group
		/** ---------------------------------------*/
		if(!isset($post_data['group_id']))
		{
			if (ee()->config->item('default_member_group') == '')
			{
				$data['group_id'] = 4;  // Pending
			}
			else
			{
				$data['group_id'] = ee()->config->item('default_member_group');
			}
		}
		else
		{
			$data['group_id'] = (int)$post_data['group_id'];
		}

		/** ---------------------------------------
		/**  Optional Fields
		/** ---------------------------------------*/
		$optional = array(
			'bio'				=> 'bio',
			'language'			=> 'deft_lang',
			'timezone'			=> 'server_timezone',
            //			'date_format'		=> 'date_format',
			'time_format'		=> 'time_format',
            //			'include_seconds'	=> 'include_seconds',
			'bday_y'			=> 'bday_y',
			'bday_m'   			=> 'bday_m',
			'bday_d'   			=> 'bday_d',
			'occupation'   		=> 'occupation',
			'interests'  	 	=> 'interests',
			'aol_im'   			=> 'aol_im',
			'icq'   			=> 'icq',
			'yahoo_im'   		=> 'yahoo_im',
			'msn_im'   			=> 'msn_im',
		);

		foreach($optional as $key => $value)
		{
			if (isset($post_data[$value]))
			{
				$data[$key] = $post_data[$value];
			}
		}

		// We generate an authorization code if the member needs to self-activate
		if (ee()->config->item('req_mbr_activation') == 'email')
		{
			$data['authcode'] = ee()->functions->random('alnum', 10);
		}

		/** ---------------------------------------
		/**  Insert basic member data
		/** ---------------------------------------*/
		ee()->db->query(ee()->db->insert_string('exp_members', $data));

		$member_id = ee()->db->insert_id();

		/** ---------------------------------------
		/**  Insert custom fields
		/** ---------------------------------------*/
		$cust_fields['member_id'] = $member_id;

		ee()->db->query(ee()->db->insert_string('exp_member_data', $cust_fields));


		// Create a record in the member homepage table
		// This is only necessary if the user gains CP access,
		// but we'll add the record anyway.

		ee()->db->query(ee()->db->insert_string('exp_member_homepage',
			array('member_id' => $member_id))
		);

		// Update
		ee()->stats->update_member_stats();

		// Update
		if (ee()->config->item('req_mbr_activation') == 'none')
		{
			ee()->stats->update_member_stats();
		}

		// Send admin notifications
		if (ee()->config->item('new_member_notification') == 'y' &&
			ee()->config->item('mbr_notification_emails') != '')
		{
			$name = ($data['screen_name'] != '') ? $data['screen_name'] : $data['username'];

			$swap = array(
				'name'					=> $name,
				'site_name'				=> stripslashes(ee()->config->item('site_name')),
				'control_panel_url'		=> ee()->config->item('cp_url'),
				'username'				=> $data['username'],
				'email'					=> $data['email']
			);

			$template = ee()->functions->fetch_email_template('admin_notify_reg');
			$email_tit = $this->_var_swap($template['title'], $swap);
			$email_msg = $this->_var_swap($template['data'], $swap);

			// Remove multiple commas
			$notify_address = reduce_multiples(ee()->config->item('mbr_notification_emails'), ',', TRUE);

			// Send email
			ee()->load->helper('text');

			ee()->load->library('email');
			ee()->email->wordwrap = true;
			ee()->email->from(ee()->config->item('webmaster_email'), ee()->config->item('webmaster_name'));
			ee()->email->to($notify_address);
			ee()->email->subject($email_tit);
			ee()->email->message(entities_to_ascii($email_msg));
			ee()->email->Send();
		}

//		// -------------------------------------------
//		// 'member_member_register' hook.
//		//  - Additional processing when a member is created through the User Side
//		//  - $member_id added in 2.0.1
//		//
//		ee()->extensions->call('member_member_register', $data, $member_id);
//		if (ee()->extensions->end_script === TRUE) return;
//		//
//		// -------------------------------------------

		// Send user notifications
		if (ee()->config->item('req_mbr_activation') == 'email')
		{
			$action_id  = ee()->functions->fetch_action_id('Member', 'activate_member');

			$name = ($data['screen_name'] != '') ? $data['screen_name'] : $data['username'];

			//$board_id = ($post_data['board_id'] !== FALSE && is_numeric($post_data['board_id'])) ? $post_data['board_id'] : 1;

			//$forum_id = ($post_data['FROM'] == 'forum') ? '&r=f&board_id='.$board_id : '';
			$forum_id = '';

			//$add = ($mailinglist_subscribe !== TRUE) ? '' : '&mailinglist='.$post_data['mailinglist_subscribe'];
			$add = '';

			$swap = array(
				'name'				=> $name,
				'activation_url'	=> ee()->functions->fetch_site_index(0, 0).QUERY_MARKER.'ACT='.$action_id.'&id='.$data['authcode'].$forum_id.$add,
				'site_name'			=> stripslashes(ee()->config->item('site_name')),
				'site_url'			=> ee()->config->item('site_url'),
				'username'			=> $data['username'],
				'email'				=> $data['email']
			);

			$template = ee()->functions->fetch_email_template('mbr_activation_instructions');
			$email_tit = $this->_var_swap($template['title'], $swap);
			$email_msg = $this->_var_swap($template['data'], $swap);

			// Send email
			ee()->load->helper('text');

			ee()->load->library('email');
			ee()->email->wordwrap = true;
			ee()->email->from(ee()->config->item('webmaster_email'), ee()->config->item('webmaster_name'));
			ee()->email->to($data['email']);
			ee()->email->subject($email_tit);
			ee()->email->message(entities_to_ascii($email_msg));
			ee()->email->Send();
		}

		/* -------------------------------------------
		/* 'create_member_end' hook.
		/*  - Added: 3.5
		*/
		Webservice_helper::add_hook('create_member_end', $member_id);
		/** ---------------------------------------*/

		/** ---------------------------------------
		/**  Return the result
		/** ---------------------------------------*/
		return array(
			'message' => 'Successfully created',
			'id' => $member_id, //@deprecated
			'metadata' => array(
				'id' => $member_id,
			),
			'success' => true
		);
	}

	//-------------------------------------------------------------------------

	/**
     * read_member
    */
	public function read_member($post_data = array())
	{
		/** ---------------------------------------
		/**  can we update member profiles, do we have the right for it
		/** ---------------------------------------*/
		if(ee()->session->userdata('can_view_profiles') != 'y')
		{
			return array(
				'message' => 'You have no right to administrate members'
			);
		}
		
		/** ---------------------------------------
		/**  Validate data
		/** ---------------------------------------*/
		$data_errors = array();
		
		/** ---------------------------------------
		/**  member_id is for a insert always required
		/** ---------------------------------------*/
		if(!isset($post_data['member_id']) || $post_data['member_id'] == '') {
			$data_errors[] = 'member_id';
		}		

		/** ---------------------------------------
		/**  Return error when there are fields who are empty en shoulnd`t
		/** ---------------------------------------*/
		if(!empty($data_errors) || count($data_errors) > 0)
		{
			//generate error
			return array(
				'message' => 'The following fields are not filled in: '.implode(', ',$data_errors)
			);
		}
				
		/** ---------------------------------------
		/** Get the member
		/** ---------------------------------------*/
		$member_data = ee()->channel_data->get_member($post_data['member_id']);
		
		/** ---------------------------------------
		/** Any result
		/** ---------------------------------------*/
		if($member_data->num_rows == 0)
		{
			return array(
				'message' => 'No member found'
			);
		}
		
		//set the data
		$member_data = $member_data->row_array();
		
		//filter data
		$member_data = $this->filter_memberdata($member_data);

		//also get the entries written by this user
		$member_data['entries'] = $this->_get_entries($member_data['member_id']);

		/* -------------------------------------------
		/* 'read_member_end' hook.
		/*  - Added: 3.5
		*/
		Webservice_helper::add_hook('read_member_end', $member_data);
		/** ---------------------------------------*/

		/** ---------------------------------------
		/**  We got luck, it works
		/** ---------------------------------------*/
		return array(
			'message' => 'Successfully readed',
			'id' => $member_data['member_id'], //@deprecated
			'metadata' => array(
				'id' => $member_data['member_id']
			),
			'data' => array($member_data),
			'success' => true
		);
	}

	//-------------------------------------------------------------------------

	/**
     * update_member
    */
	public function update_member($post_data = array())
	{
		/** ---------------------------------------
		/**  Member_id is for a insert always required
		/** ---------------------------------------*/
		$data_errors = array();
		if(!isset($post_data['member_id']) || $post_data['member_id'] == '') {
			$data_errors[] = 'member_id';
		}	

		/** ---------------------------------------
		/**  Return error when there are fields who are empty en shoulnd`t
		/** ---------------------------------------*/
		if(!empty($data_errors) || count($data_errors) > 0)
		{
			//generate error
			return array(
				'message' => 'The following fields are not filled in: '.implode(', ',$data_errors)
			);
		}

		/** ---------------------------------------
		/**  Check if the member exists
		/** ---------------------------------------*/
		if(!$this->member_exists($post_data['member_id']))
		{
			//generate error
			return array(
				'message' => 'No member found'
			);
		}

		ee()->load->model('member_model');

		// Are any required custom fields empty?
		ee()->db->select('m_field_id, m_field_label');
		ee()->db->where('m_field_required = "y"');
		$query = ee()->db->get('member_fields');

		 $errors = array();

		 if ($query->num_rows() > 0)
		 {
			foreach ($query->result_array() as $row)
			{
				if (isset($post_data['m_field_id_'.$row['m_field_id']]) AND $post_data['m_field_id_'.$row['m_field_id']] == '')
				{
					$errors[] = ee()->lang->line('mbr_custom_field_empty').'&nbsp;'.$row['m_field_label'];
				}
			}
		 }

		/** ---------------------------------------
		/**  Return error when there are fields who are empty en shoulnd`t
		/** ---------------------------------------*/
		if(!empty($errors) || count($errors) > 0)
		{
			//generate error
			return array(
				'message' => $errors[0]
			);
		}

		/** -------------------------------------
		/**  Build query
		/** -------------------------------------*/

		if (isset($post_data['url']) AND $post_data['url'] == 'http://')
		{
			$post_data['url'] = '';
		}

		//set the fields that can be updated
		$fields = array(
			'url',
			'location',
			'occupation',
			'interests',
			'bday_y',
			'bday_m',
			'bday_d',
			'aol_im',
			'yahoo_im',
			'msn_im',
			'icq',
			'bio',
			'signature',
			'avatar_filename',
			'avatar_width',
			'avatar_height',
			'photo_filename',
			'photo_width',
			'photo_height',
			'sig_img_filename',
			'sig_img_width',
			'sig_img_height',
			'language',
			'timezone',
			'cp_theme',
			'profile_theme',
			'forum_theme',
			'notepad'
		);

		$data = array();

		//get the memberdata
		$member_data = $this->get_member($post_data['member_id']);

		foreach ($fields as $val)
		{
			$data[$val] = (isset($post_data[$val])) ? ee()->security->xss_clean($post_data[$val]) : $member_data[$val];
			unset($post_data[$val]);
		}

		ee()->load->helper('url');
		$data['url'] = preg_replace('/[\'"]/is', '', $data['url']);
		$data['url'] = prep_url($data['url']);

		if (is_numeric($data['bday_d']) AND is_numeric($data['bday_m']))
		{
			ee()->load->helper('date');
			$year = ($data['bday_y'] != '') ? $data['bday_y'] : date('Y');
			$mdays = days_in_month($data['bday_m'], $year);

			if ($data['bday_d'] > $mdays)
			{
				$data['bday_d'] = $mdays;
			}
		}


		/** ---------------------------------------
		/**  check if the username is given and validate
		/** ---------------------------------------*/
		if($post_data['username'] != $member_data['username'] && (isset($post_data['username']) || $post_data['username'] != '') )
		{
			$username_check = $this->validate_username($post_data['username']);
			if($username_check['success'])
			{
				$data['username'] = $post_data['username'];
			}
			//return error
			else
			{
				return array(
					'message' => $username_check['message']
				);
			}
		}

		/** ---------------------------------------
		/**  check if the screen_name is given and validate
		/** ---------------------------------------*/
		//if(isset($post_data['screen_name']) || $post_data['screen_name'] != '')
		if($post_data['screen_name'] != $member_data['screen_name'] && (isset($post_data['screen_name']) || $post_data['screen_name'] != '') )
		{
			$screen_name_check = $this->validate_screen_name($post_data['screen_name']);
			if($screen_name_check['success'])
			{
				$data['screen_name'] = $post_data['screen_name'];
			}
			//return error
			else
			{
				return array(
					'message' => $screen_name_check['message']
				);
			}
		}

		/** ---------------------------------------
		/**  check if the email is given and validate
		/** ---------------------------------------*/
		//if(isset($post_data['email']) || $post_data['email'] != '')
		if($post_data['email'] != $member_data['email'] && (isset($post_data['email']) || $post_data['email'] != '') )
		{
			$email_check = $this->validate_email($post_data['email']);
			if($email_check['success'])
			{
				$data['email'] = $post_data['email'];
			}
			//return error
			else
			{
				return array(
					'message' => $email_check['message']
				);
			}
		}

		if (count($data) > 0)
		{
			ee()->member_model->update_member($post_data['member_id'], $data);
		}

		/** -------------------------------------
		/**  Update the custom fields
		/** -------------------------------------*/

		$m_data = array();

		if (count($post_data) > 0)
		{
			foreach ($post_data as $key => $val)
			{
				if (strncmp($key, 'm_field_id_', 11) == 0)
				{
					$m_data[$key] = ee()->security->xss_clean($val);
				}
			}

			if (count($m_data) > 0)
			{
				ee()->member_model->update_member_data($post_data['member_id'], $m_data);
			}
		}

		/** -------------------------------------
		/**  Update comments
		/** -------------------------------------*/

		if ($data['location'] != "" OR $data['url'] != "")
		{
			if (ee()->db->table_exists('comments'))
			{
				$d = array(
					'location'	=> $data['location'],
					'url'		=> $data['url']
				);

				ee()->db->where('author_id', $post_data['member_id']);
				ee()->db->update('comments', $d);
			}
	  	}

	  	/* -------------------------------------------
		/* 'update_member_end' hook.
		/*  - Added: 3.5
		*/
		Webservice_helper::add_hook('update_member_end', $post_data);
		/** ---------------------------------------*/

		/** ---------------------------------------
		/**  We got luck, it works
		/** ---------------------------------------*/
		return array(
			'message' => 'Successfully updated',
			'id' => $post_data['member_id'], //@deprecated
			'metadata' => array(
				'id' => $post_data['member_id']
			),
			'success' => true
		);
	}

	//-------------------------------------------------------------------------

	/**
     * delete_member
    */
	public function delete_member($post_data = array())
	{
		/** ---------------------------------------
		/**  can we add a new channel, do we have the right for it
		/** ---------------------------------------*/
		if(ee()->session->userdata('can_admin_members') != 'y')
		{
			return array(
				'message' => 'You have no right to administrate members'
			);
		}

		/** ---------------------------------------
		/**  can we add a new channel, do we have the right for it
		/** ---------------------------------------*/
		if(ee()->session->userdata('can_delete_members') != 'y')
		{
			return array(
				'message' => 'You have no right to administrate members'
			);
		}

		/** ---------------------------------------
		/**  Title is for a insert always required
		/** ---------------------------------------*/
		$data_errors = array();
		if(!isset($post_data['member_id']) || $post_data['member_id'] == '') {
			$data_errors[] = 'member_id';
		}	

		/** ---------------------------------------
		/**  Return error when there are fields who are empty en shoulnd`t
		/** ---------------------------------------*/
		if(!empty($data_errors) || count($data_errors) > 0)
		{
			//generate error
			return array(
				'message' => 'The following fields are not filled in: '.implode(', ',$data_errors)
			);
		}

		/** ---------------------------------------
		/**  can we add a new channel, do we have the right for it
		/** ---------------------------------------*/
		if(ee()->session->userdata('member_id') == $post_data['member_id'])
		{
			return array(
				'message' => 'Cannot delete yourself'
			);
		}

		/** ---------------------------------------
		/**  check the member
		/** ---------------------------------------*/
		$member_data = $this->get_member($post_data['member_id']);
		if(empty($member_data))
		{
			return array(
				'message' => 'No member found'
			);
		}

		/** ---------------------------------------
		/**  Never delete a super admin
		/** ---------------------------------------*/
		if($member_data['group_id'] == 1)
		{
			return array(
				'message' => 'Cannot delete a superadmin'
			);
		}
		
		/** ---------------------------------------
		/**  Now lets delete the member an get the member_id that can take over the entries
		/** ---------------------------------------*/
		ee()->load->model('member_model');
		$heir = isset($post_data['member_id_takeover']) ? $post_data['member_id_takeover'] : NULL;
		ee()->member_model->delete_member($post_data['member_id'], $heir);

		// Update
		ee()->stats->update_member_stats();

		/* -------------------------------------------
		/* 'delete_member_end' hook.
		/*  - Added: 3.5
		*/
		Webservice_helper::add_hook('delete_member_end', $post_data['member_id']);
		/** ---------------------------------------*/

		/** ---------------------------------------
		/**  We got luck, it works
		/** ---------------------------------------*/
		return array(
			'message' => 'Successfully deleted',
			'id' => $post_data['member_id'], //@deprecated
			'metadata' => array(
				'id' => $post_data['member_id']
			),
			'success' => true
		);
	}

	// --------------------------------------------------------------------

	/**
	 * Check userdata settings        
	 */
	public function member_exists($member_id = 0)
	{
		ee()->db->where('member_id', $member_id);
		$query = ee()->db->get('members');
		
		return $query->num_rows();
	}
	
	// --------------------------------------------------------------------

	/**
	 * Check userdata settings        
	 */
	public function check_member($field, $data, $member_id = '')
	{
		//not looking for this member_id
		if($member_id != '')
		{
			ee()->db->where('member_id !=', $member_id);
		}
		
		ee()->db->where($field, $data);
		$query = ee()->db->get('members');
		
		return $query->num_rows();
	}

	// --------------------------------------------------------------------

	/**
	 * Check userdata settings        
	 */
	public function get_member($member_id = 0)
	{
		ee()->db->where('member_id', $member_id);
		$query = ee()->db->get('members');
		
		return $query->row_array();
	}

	// ----------------------------------------------------------------
	
	/**
	 * Only allow save memberdata          
	 */
	public function filter_memberdata($data, $delete = array())
	{
		$return = array();
		
		foreach($this->default as $val)
		{
			if(isset($data[$val]) && !in_array($val, $delete))
			{
				$return[$val] = $data[$val];
			}
		}
		
		return $return;
	}

	// --------------------------------------------------------------------
	
	/**
	 * default_data function.
	 * 
	 * @access public
	 * @return void
	 */
	private function _default_data()
	{
		$this->default = array(
			'member_id',
			'group_id',
			'username',
			'screen_name',
			'email',
			'url',
			'location',
			'occupation',
			'interests',
			'bday_d',
			'bday_m',
			'bday_y',
			'aol_im',
			'yahoo_im',
			'msn_im',
			'icq',
			'bio',
			'signature',
			'avatar_filename',
			'avatar_width',
			'avatar_height',
			'photo_filename',
			'photo_width',
			'photo_height',
			'sig_img_filename',
			'sig_img_width',
			'sig_img_height',
			'ignore_list',
			'private_messages',
			'accept_messages',
			'last_view_bulletins',
			'last_bulletin_date',
			'ip_address',
			'join_date',
			'last_visit',
			'last_activity',
			'total_entries',
			'total_comments',
			'total_forum_topics',
			'total_forum_posts',
			'last_entry_date',
			'last_comment_date',
			'last_forum_post_date',
			'last_email_date',
			'in_authorlist',
			'accept_admin_email',
			'accept_user_email',
			'notify_by_default',
			'notify_of_pm',
			'display_avatars',
			'display_signatures',
			'parse_smileys',
			'smart_notifications',
			'language',
			'timezone',
			'time_format',
			'cp_theme',
			'profile_theme',
			'forum_theme',
			'tracker',
			'template_size',
			'notepad',
			'notepad_size',
			'quick_links',
			'quick_tabs',
			'show_sidebar',
			'pmember_id',
			'rte_enabled',
			'rte_toolset_id'
		);	
	}

	// --------------------------------------------------------------------

	/**
	 * Replace variables
	 */
	function _var_swap($str, $data)
	{
		if ( ! is_array($data))
		{
			return FALSE;
		}

		foreach ($data as $key => $val)
		{
			$str = str_replace('{'.$key.'}', $val, $str);
		}

		return $str;
	}

	// ----------------------------------------------------------------

	/**
	 * Password safety check
	 *
	 */
	/*function password_safety_check()
	{
		if ($this->cur_password == '')
		{
			return $this->errors[] = ee()->lang->line('missing_current_password');
		}

		ee()->load->library('auth');

		// Get the users current password
		$pq = ee()->db->select('password, salt')
			->get_where('members', array(
				'member_id' => (int) ee()->session->userdata('member_id')
			));

		if ( ! $pq->num_rows())
		{
			$this->errors[] = ee()->lang->line('invalid_password');
		}

		$passwd = ee()->auth->hash_password($this->cur_password, $pq->row('salt'));

		if ( ! isset($passwd['salt']) OR ($passwd['password'] != $pq->row('password')))
		{
			$this->errors[] = ee()->lang->line('invalid_password');
		}
	}*/

	// ----------------------------------------------------------------

	/**
	 * Validate Username
	 */
	function validate_username($username = '', $new = false)
	{
		// Is username formatting correct?
		// Reserved characters:  |  "  '  !
		if (preg_match("/[\|'\"!<>\{\}]/", $username))
		{
			return array(
				'success' => false,
				'message' => ee()->lang->line('invalid_characters_in_username')
			);
		}

		// Is username min length correct?
		$len = ee()->config->item('un_min_len');

		if (strlen($username) < $len)
		{
			return array(
				'success' => false,
				'message' => str_replace('%x', $len, ee()->lang->line('username_too_short'))
			);
		}

		// Is username max length correct?
		if (strlen($username) > 50)
		{
			return array(
				'success' => false,
				'message' => ee()->lang->line('username_password_too_long')
			);
		}

		// Is username banned?
		if (ee()->session->ban_check('username', $username))
		{
			return array(
				'success' => false,
				'message' => ee()->lang->line('username_taken')
			);
		}

		// Is username taken?
		ee()->db->from('members');
		ee()->db->where('username = LOWER('.ee()->db->escape($username).')', NULL, FALSE);
		ee()->db->where('LOWER(username) = '.ee()->db->escape(strtolower($username)), NULL, FALSE);
		$count = ee()->db->count_all_results();

		if ($count  > 0) {
			return array(
				'success' => false,
				'message' => ee()->lang->line('username_taken')
			);
		}

		return array(
			'success' => true
		);
	}

	// ----------------------------------------------------------------

	/**
	 * Validate screen name
	 */
	function validate_screen_name($screen_name = '')
	{
		if (preg_match('/[\{\}<>]/', $screen_name))
		{
			return array(
				'success' => false,
				'message' => ee()->lang->line('disallowed_screen_chars')
			);
		}

		/** -------------------------------------
		/**  Is screen name banned?
		/** -------------------------------------*/
		if (ee()->session->ban_check('screen_name', $screen_name) OR trim(preg_replace("/&nbsp;*/", '', $screen_name)) == '')
		{
			return array(
				'success' => false,
				'message' => ee()->lang->line('screen_name_taken')
			);
		}

		/** -------------------------------------
		/**  Is screen name taken?
		/** -------------------------------------*/
		$query = ee()->db->query("SELECT COUNT(*) AS count FROM exp_members WHERE screen_name = '".ee()->db->escape_str($screen_name)."'");

		if ($query->row('count')  > 0)
		{
			return array(
				'success' => false,
				'message' => ee()->lang->line('screen_name_taken')
			);
		}

		return array(
			'success' => true
		);
	}

	// ----------------------------------------------------------------

	/**
	 * Validate Password
	 *
	 * @return 	mixed 	array on failure, void on success
	 */
//	function validate_password()
//	{
//		/** ----------------------------------
//		/**  Is password missing?
//		/** ----------------------------------*/
//
//		if ($this->password == '' AND $this->password_confirm == '')
//		{
//			return $this->errors[] = ee()->lang->line('missing_password');
//		}
//
//		/** -------------------------------------
//		/**  Is password min length correct?
//		/** -------------------------------------*/
//
//		$len = ee()->config->item('pw_min_len');
//
//		if (strlen($this->password) < $len)
//		{
//			return $this->errors[] = str_replace('%x', $len, ee()->lang->line('password_too_short'));
//		}
//
//		/** -------------------------------------
//		/**  Is password max length correct?
//		/** -------------------------------------*/
//		if (strlen($this->password) > 40)
//		{
//			return $this->errors[] = ee()->lang->line('username_password_too_long');
//		}
//
//		/** -------------------------------------
//		/**  Is password the same as username?
//		/** -------------------------------------*/
//		// We check for a reversed password as well
//
//		//  Make UN/PW lowercase for testing
//
//		$lc_user = strtolower($this->username);
//		$lc_pass = strtolower($this->password);
//		$nm_pass = strtr($lc_pass, 'elos', '3105');
//
//
//		if ($lc_user == $lc_pass OR $lc_user == strrev($lc_pass) OR $lc_user == $nm_pass OR $lc_user == strrev($nm_pass))
//		{
//			return $this->errors[] = ee()->lang->line('password_based_on_username');
//		}
//
//		/** -------------------------------------
//		/**  Do Password and confirm match?
//		/** -------------------------------------*/
//
//		if ($this->password != $this->password_confirm)
//		{
//			return $this->errors[] = ee()->lang->line('missmatched_passwords');
//		}
//
//		/** -------------------------------------
//		/**  Are secure passwords required?
//		/** -------------------------------------*/
//		if (ee()->config->item('require_secure_passwords') == 'y')
//		{
//			$count = array('uc' => 0, 'lc' => 0, 'num' => 0);
//
//			$pass = preg_quote($this->password, "/");
//
//			$len = strlen($pass);
//
//			for ($i = 0; $i < $len; $i++)
//			{
//				$n = substr($pass, $i, 1);
//
//				if (preg_match("/^[[:upper:]]$/", $n))
//				{
//					$count['uc']++;
//				}
//				elseif (preg_match("/^[[:lower:]]$/", $n))
//				{
//					$count['lc']++;
//				}
//				elseif (preg_match("/^[[:digit:]]$/", $n))
//				{
//					$count['num']++;
//				}
//			}
//
//			foreach ($count as $val)
//			{
//				if ($val == 0)
//				{
//					return $this->errors[] = ee()->lang->line('not_secure_password');
//				}
//			}
//		}
//
//
//		/** -------------------------------------
//		/**  Does password exist in dictionary?
//		/** -------------------------------------*/
//		if ($this->lookup_dictionary_word($lc_pass) == TRUE)
//		{
//			$this->errors[] = ee()->lang->line('password_in_dictionary');
//		}
//	}

	// ----------------------------------------------------------------

	/**
	 * Validate Email
	 *
	 *
	 * @return 	mixed 	array on failure, void on success
	 */
	function validate_email($email = '')
	{
		/** -------------------------------------
		/**  Is email missing?
		/** -------------------------------------*/
		if ($email == '')
		{
			return array(
				'success' => false,
				'message' => ee()->lang->line('missing_email')
			);
		}

		/** -------------------------------------
		/**  Is email valid?
		/** -------------------------------------*/

		ee()->load->helper('email');

		if ( ! valid_email($email))
		{
			return array(
				'success' => false,
				'message' => ee()->lang->line('invalid_email_address')
			);
		}

		/** -------------------------------------
		/**  Is email banned?
		/** -------------------------------------*/
		if (ee()->session->ban_check('email', $email))
		{
			return array(
				'success' => false,
				'message' => ee()->lang->line('email_taken')
			);
		}

		/** -------------------------------------
		/**  Duplicate emails?
		/** -------------------------------------*/
		$query = ee()->db->query("SELECT COUNT(*) as count FROM exp_members WHERE email = '".ee()->db->escape_str($email)."'");
		if ($query->row('count')  > 0)
		{
			return array(
				'success' => false,
				'message' => ee()->lang->line('email_taken')
			);
		}

		return array(
			'success' => true
		);
	}

	// ----------------------------------------------------------------

	/**
	 * Lookup word in dictionary file
	 *
	 * @param 	string
	 * @return 	boolean
	 */
//	function lookup_dictionary_word($target)
//	{
//		if (ee()->config->item('allow_dictionary_pw') == 'y' OR ee()->config->item('name_of_dictionary_file') == '')
//		{
//			return FALSE;
//		}
//
//		$path = reduce_double_slashes(PATH_DICT.ee()->config->item('name_of_dictionary_file'));
//
//		if ( ! file_exists($path))
//		{
//			return FALSE;
//		}
//
//		$word_file = file($path);
//
//		foreach ($word_file as $word)
//		{
//			if (trim(strtolower($word)) == $target)
//			{
//				return TRUE;
//			}
//		}
//
//		return FALSE;
//	}

	// ----------------------------------------------------------------

	/**
	 * _update_category
	 * @param int $cat_id
	 * @return array [type]
	 * @internal param array $values
	 */
	private function _get_entries($author_id = 0)
	{
		$ids = array();

		$result = ee()->db->select('entry_id')
			->from('channel_titles')
			->where('author_id', $author_id)
			->get();

		if($result->num_rows() > 0)
		{
			foreach($result->result() as $row)
			{
				$ids[] = $row->entry_id;
			}
		}

		return $ids;
	}

}

