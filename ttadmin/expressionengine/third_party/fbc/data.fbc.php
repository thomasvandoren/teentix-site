<?php if ( ! defined('EXT')) exit('No direct script access allowed');
 
 /**
 * Solspace - FBC
 *
 * @package 	Solspace:FBC
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2010-2011, Solspace, Inc.
 * @link		http://www.solspace.com/docs/addon/c/Facebook_Connect/
 * @version		2.0.6
 * @filesource 	./system/expressionengine/third_party/fbc/
 */
 
 /**
 * FBC - Data Models
 *
 * @package 	Solspace:FBC
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/fbc/data.fbc.php
 */

require_once 'addon_builder/data.addon_builder.php';

class Fbc_data extends Addon_builder_data_fbc
{
	var $cached		= array();
	var $params_tbl	= 'exp_fbc_params';
	
	private $prohibited_member_groups	= array();
	
	// --------------------------------------------------------------------
	
	/**
	 * Delete cookie
	 *
	 * @access	public
	 * @return	array
	 */
    
	function _delete_cookie( $hash = '' )
    {
    	if ( $hash == '' ) return FALSE;
    	
    	if ( ee()->input->cookie( 'fbc2_params_' . $hash ) !== FALSE )
    	{
			ee()->functions->set_cookie( 'fbc2_params_' . $hash, '', ( time() - 86400 ) );
    	}
    	
    	return TRUE;
    }
    
    /*	End delete cookie */
	
	// --------------------------------------------------------------------
	
	/**
	 * Get facebook loader js
	 *
	 * @access	public
	 * @return	string
	 */
    
	function get_facebook_loader_js( $language = 'en_US' )
    {
		// --------------------------------------------
		//  Assemble JS
		// --------------------------------------------
		
		$js	= '
<div id="fb-root"></div>' . NL .
			'<script>' . NL .
			'window.fbAsyncInit = function() {
	FB._https = true; // Required because FB Javascript SDK tries to submit https to http
	FB.init({' . 
			'appId:"' . ee()->config->item('fbc_app_id') . '", cookie:true, status:true, xfbml:true, oauth:true' . 
			'});' . NL .
		'};' . NL .
			'(function() {
	var e = document.createElement("script");
	e.src = document.location.protocol + "' . str_replace( 'en_US', $language, FBC_LOADER_JS ) . '";
	e.async = true;
	document.getElementById("fb-root").appendChild(e);
}());' . NL .
		'</script>' . NL;
			
		// --------------------------------------------
		//  Return
		// --------------------------------------------
		
		return $js;
	}
	
	/*	End get facebook loader js*/
	
	// --------------------------------------------------------------------
	
	/**
	 * Get facebook registration fields
	 *
	 * @access	public
	 * @return	array
	 */
    
	function get_facebook_registration_fields( $prefs = array() )
    {
 		// --------------------------------------------
        //  Prep Cache, Return if Set
        // --------------------------------------------
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( func_get_args() );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = array();
 		
 		// --------------------------------------------
        //  Baseline
        // --------------------------------------------
        
        $fields	= array(
        	'name'	=> array(
        		'name'			=> 'name'
        	),
        	'email'	=> array(
        		'name'			=> 'email'
        	)
		);
 		
 		// --------------------------------------------
        //  Custom fields
        // --------------------------------------------
        
        foreach ( $this->get_member_fields() as $name => $data )
        {
			// --------------------------------------------
			// Temporary fix for required fields
			// --------------------------------------------
			// Currently Facebook makes all custom fields provided in the registration form required.
			//	There is a very long and complicated way to make some of the fields optionaal. 
			//  Until this changes, we're going to only show custom member fields in reg forms when 
			//  they are both indicated as showing in the registration and required. 
			//	- mitchell@solspace.com 2011 06 27.
			// --------------------------------------------
			
			if ( empty( $data['required'] ) OR $data['required'] == 'n' ) continue;
        
        	$data['description']	= $data['label'];
        	unset( $data['label'] );
        	
        	// Textarea fields are not supported
        	$data['type']	= ( $data['type'] == 'textarea' ) ? 'text': $data['type'];
        
        	$fields[$name]	= $data;
        }
 		
 		// --------------------------------------------
        //  Funkify FB native fields
 		// --------------------------------------------
 		//	Facebook support first_name and last_name fields. If these same fields are also called for in the EE site, we force the registration form to use FB's version of the fields so that they can be prefilled.
        // --------------------------------------------
        
        foreach ( array( 'first_name', 'last_name' ) as $val )
        {
        	if ( isset( $fields[$val] ) === TRUE )
        	{
        		$fields[$val]	= array( 'name' => $val );
        	}
        }
 		
 		// --------------------------------------------
        //  Add captcha if needed
        // --------------------------------------------
        
		$fields['captcha']	= array(
			'name'	=> 'captcha'
		);
        
        if ( ! empty( $prefs['captcha'] ) AND $prefs['captcha'] == 'n' )
        {
        	unset( $fields['captcha'] );
        }
        elseif ( ! empty( $prefs['captcha'] ) AND $prefs['captcha'] == 'y' )
        {
        	//	This page intentionally left blank.
        }
		elseif ( ee()->config->item('use_membership_captcha') == 'n' )
		{
        	unset( $fields['captcha'] );
		}
 		
 		// --------------------------------------------
        //  Add accept terms if needed
        // --------------------------------------------
        
        if ( ee()->config->item('require_terms_of_service') == 'y' )
        {
			$fields['accept_terms']	= array(
				'name'			=> 'accept_terms',
				'description'	=> 'Accept Terms of Service',
				'type'			=> 'checkbox',
				'required'		=> 'y'
			);
        }
 		
 		// --------------------------------------------
        //  Return
        // --------------------------------------------
        
        return $this->cached[$cache_name][$cache_hash]	= $fields;
 	}
 	
 	/*	End get facebook registration fields */
       
	
	// --------------------------------------------------------------------
	
	/**
	 * Get facebook user id from member id
	 *
	 * @access	public
	 * @return	mixed
	 */
    
	function get_facebook_user_id_from_member_id( $member_id = '' )
    {
 		// --------------------------------------------
        //  Prep Cache, Return if Set
        // --------------------------------------------
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( func_get_args() );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
    
 		// --------------------------------------------
        //  Validate
        // --------------------------------------------
        
        if ( ( $uid = $this->get_member_data_from_member_id( $member_id ) ) === FALSE )
        {
        	return FALSE;
        }
        
        if ( isset( $uid['facebook_connect_user_id'] ) === FALSE )
        {
        	return FALSE;
        }
    
 		// --------------------------------------------
        //  Return
        // --------------------------------------------
        
        return $this->cached[$cache_name][$cache_hash] = $uid['facebook_connect_user_id'];
    }
    
    /*	End get facebook user id from member id */
	
	// --------------------------------------------------------------------
	
	/**
	 * Get member data from member id
	 *
	 * @access	public
	 * @return	mixed
	 */
    
	function get_member_data_from_member_id( $member_id = '', $all_groups = '' )
    {
 		// --------------------------------------------
        //	Prep Cache, Return if Set
        // --------------------------------------------
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( func_get_args() );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
    
 		// --------------------------------------------
        //  Validate
        // --------------------------------------------
        
        if ( is_numeric( $member_id ) === FALSE ) return FALSE;
    
 		// --------------------------------------------
        //	Get eligible member groups for current site
        // --------------------------------------------
        
        $groups	= explode( "|", ee()->config->item('fbc_eligible_member_groups') );
        
        if ( empty( $groups ) AND $all_groups != 'all_groups' )
        {
        	return FALSE;
        }
    
 		// --------------------------------------------
        //  Hit the DB
        // --------------------------------------------
        
		$sql	= "/* data.fbc.php " . __FUNCTION__ . " */ 
			SELECT member_id, group_id, username, screen_name, email, password, unique_id, facebook_connect_user_id
			FROM exp_members
			WHERE 0 = 0";
        			
        if ( $all_groups != 'all_groups' )
        {
			$sql	.= " AND group_id IN (" . implode( ',', $groups ) . ")";
        }        			
        			
        $sql	.= " AND member_id = " . ee()->db->escape_str( $member_id );
        			
        $query	= ee()->db->query( $sql );
    
 		// --------------------------------------------
        //	Did we fail to find a member?
        // --------------------------------------------
        
        if ( $query->num_rows() == 0 )
        {
        	$this->error[]	= ee()->lang->line('member_group_not_eligible');
        	return FALSE;
        }
    
 		// --------------------------------------------
        //	Let's cache for other methods that need this data
        // --------------------------------------------
 		
 		if ( isset( $this->cached[ 'get_member_id_from_facebook_user_id' ][ $this->_imploder( array( $query->row('facebook_connect_user_id') ) ) ] ) === FALSE )
 		{
			$this->cached[ 'get_member_id_from_facebook_user_id' ][ $this->_imploder( array( $query->row('facebook_connect_user_id') ) ) ]	= $query->row('member_id');
 		}
 		
 		if ( isset( $this->cached[ 'get_facebook_user_id_from_member_id' ][ $this->_imploder( array( $query->row('member_id') ) ) ] ) === FALSE )
 		{
			$this->cached[ 'get_facebook_user_id_from_member_id' ][ $this->_imploder( array( $query->row('member_id') ) ) ]	= $query->row('facebook_connect_user_id');
 		}
    
 		// --------------------------------------------
        //  Return
        // --------------------------------------------
        
        return $this->cached[$cache_name][$cache_hash] = $query->row_array();
    }
    
    /*	End get member id from facebook user id */
	
	// --------------------------------------------------------------------
	
	/**
	 * Get member fields
	 *
	 * @access	public
	 * @return	array
	 */
    
	function get_member_fields()
    {    
 		// --------------------------------------------
        //  Prep Cache, Return if Set
        // --------------------------------------------
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( func_get_args() );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = array();
    
 		// --------------------------------------------
        //	Grab member fields from DB
        // --------------------------------------------
        
		$sql	= "/* data.fbc.php " . __FUNCTION__ . " */ 
			SELECT
				m_field_id AS id,
				m_field_name AS name,
				m_field_label AS label,
				m_field_description AS description,
				m_field_type AS type,
				m_field_fmt AS format,
				m_field_list_items AS options,
				m_field_required AS required
			FROM exp_member_fields
			WHERE m_field_reg = 'y'
			ORDER BY m_field_order, m_field_name";
        	
        $query	= ee()->db->query( $sql );
        
        if ( $query->num_rows() == 0 ) return array();
        
        foreach ( $query->result_array() as $row )
        {
        	if ( $row['type'] == 'select' )
        	{
        		$options	= explode( "\n", $row['options'] );
        		
        		unset( $row['options'] );
        		
        		foreach ( $options as $option )
        		{
        			$row['options'][$option]	= $option;
        		}
        	}
        	
			$this->cached[$cache_name][$cache_hash][$row['name']] =	$row;     	
        }
    
 		// --------------------------------------------
        //	Return
        // --------------------------------------------
        
        return $this->cached[$cache_name][$cache_hash];
 	}
 	
 	/*	End get member fields */
	
	// --------------------------------------------------------------------
	
	/**
	 * Get member groups
	 *
	 * @access	public
	 * @return	array
	 */
    
	function get_member_groups( $site_id = '' )
    {
 		// --------------------------------------------
        //  Override Super Admin security feature?
 		// --------------------------------------------
 		//	Super Admins are not allowed to login to EE using Facebook. It's a security risk. You can override this behavior at your own risk by commenting out this line.
        // --------------------------------------------
    	
		$this->prohibited_member_groups	= array(1,2,3,4);
 		
 		// --------------------------------------------
        //  Set site id
        // --------------------------------------------
        
        $site_id	= ( $site_id == '' ) ? ee()->config->item('site_id'): $site_id;
    
 		// --------------------------------------------
        //  Prep Cache, Return if Set
        // --------------------------------------------
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( array( $site_id ) );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = array();
    
 		// --------------------------------------------
        //	Grab member groups from DB
        // --------------------------------------------
        
		$sql	= "/* data.fbc.php " . __FUNCTION__ . " */ 
			SELECT group_id, group_title
			FROM exp_member_groups
			WHERE site_id = " . ee()->db->escape_str( $site_id );
					
		if ( count( $this->prohibited_member_groups ) > 0 )
		{
			$sql	.= " AND group_id NOT IN (" . implode( ",", $this->prohibited_member_groups ) . ")";
		}
		
		$sql	.= " ORDER BY group_title ASC";
        	
        $query	= ee()->db->query( $sql );
        
        if ( $query->num_rows() == 0 ) return FALSE;
        
        foreach ( $query->result_array() as $row )
        {
			$this->cached[$cache_name][$cache_hash][$row['group_id']] =	$row['group_title'];      	
        }
        
        return $this->cached[$cache_name][$cache_hash];
 	}
 	
 	/*	End get member groups */
	
	// --------------------------------------------------------------------
	
	/**
	 * Get member id from facebook user id
	 *
	 * @access	public
	 * @return	mixed
	 */
    
	function get_member_id_from_facebook_user_id( $uid	= '' )
    {
 		// --------------------------------------------
        //  Prep Cache, Return if Set
        // --------------------------------------------
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( func_get_args() );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
    
 		// --------------------------------------------
        //  Validate
        // --------------------------------------------
        
        if ( is_numeric( $uid ) === FALSE ) return FALSE;
    
 		// --------------------------------------------
        //	Get eligible member groups for current site
        // --------------------------------------------
        
        $groups	= explode( "|", ee()->config->item('fbc_eligible_member_groups') );
        
        if ( empty( $groups ) )
        {
        	return FALSE;
        }
    
 		// --------------------------------------------
        //  Hit the DB
        // --------------------------------------------
        
		$sql	= "/* data.fbc.php " . __FUNCTION__ . " */ 
			SELECT member_id, group_id, username, screen_name, email, password, unique_id, facebook_connect_user_id
			FROM exp_members
			WHERE group_id IN (" . implode( ',', $groups ) . ")
			AND facebook_connect_user_id = " . ee()->db->escape_str( $uid );
        			
        $query	= ee()->db->query( $sql );
    
 		// --------------------------------------------
        //	Did we fail to find a member?
        // --------------------------------------------
        
        if ( $query->num_rows() == 0 )
        {
        	$this->error[]	= ee()->lang->line('member_group_not_eligible');
        	return FALSE;
        }
    
 		// --------------------------------------------
        //	Let's cache for another method that needs this data
        // --------------------------------------------
        
        $this->cached[ 'get_member_data_from_member_id' ][ $this->_imploder( array( $query->row('member_id') ) ) ]	= $query->row_array();
    
 		// --------------------------------------------
        //  Return
        // --------------------------------------------
        
        return $this->cached[$cache_name][$cache_hash] = $query->row('member_id');
    }
    
    /*	End get member id from facebook user id */
	
	// --------------------------------------------------------------------
	
	/**
	 * Get param
	 *
	 * @access	public
	 * @return	string
	 */
    
    function get_param( $tag_hash = '', $param = '', $delete = 'nodelete' )
    {
		// --------------------------------------------
		//	Empty?
		// --------------------------------------------
    	
    	if ( empty( $tag_hash ) OR empty( $param ) ) return FALSE;
    
 		// --------------------------------------------
        //  Prep Cache, Return if Set
        // --------------------------------------------
 		
 		$cache_name = __FUNCTION__;
 		
 		if (isset($this->cached[$cache_name][$param]))
 		{
 			return $this->cached[$cache_name][$param];
 		}
 		
 		$this->cached[$cache_name][$param] = FALSE;
    	
		// --------------------------------------------
		//	Are we using db or cookie based param location?
		// --------------------------------------------
		
		if ( FBC_PARAMS_LOCATION == 'cookie' )
		{
			// --------------------------------------------
			//	Has the hash already been set? If so, is it valid?
			// --------------------------------------------
			
			if ( ee()->input->cookie('fbc2_params_' . $tag_hash ) !== FALSE AND ee()->input->cookie('fbc2_params_' . $tag_hash ) != '' )
			{
				if ( ( $decoded = base64_decode( urldecode( ee()->input->cookie('fbc2_params_' . $tag_hash ) ) ) ) !== FALSE )
				{
					if ( ( $decoded_arr = @unserialize( $decoded ) ) !== FALSE )
					{
						// --------------------------------------------
						//	We're trying to prevent people from tinkering with their cookies and further tinkering with the website that FBC is running on. So we run through a little hash validation trick that is by no means fool proof.
						// --------------------------------------------
					
						if ( ! empty( $decoded_arr['param_hash'] ) )
						{
							$test_hash	= $decoded_arr['param_hash'];
							unset( $decoded_arr['param_hash'] );
							
							if ( md5( serialize( $decoded_arr ) ) == $test_hash )
							{
								foreach ( $decoded_arr as $key => $val )
								{
									$this->cached[$cache_name][$key] = $val;
								}
							}
						}
					}
				}
			}
		}
		else
		{				
			// --------------------------------------------
			//	Select from DB
			// --------------------------------------------
			
			$sql	= "/* data.fbc.php " . __FUNCTION__ . " */ 
				SELECT data
				FROM $this->params_tbl 
				WHERE hash = '" . ee()->db->escape_str( $tag_hash ) . "'";
			
			$query	= ee()->db->query( $sql );
			
			// --------------------------------------------
			//	Empty?
			// --------------------------------------------
			
			if ( $query->num_rows() == 0 ) return FALSE;
			
			// --------------------------------------------
			//	Unpack
			// --------------------------------------------
			
			$params	= unserialize( base64_decode( urldecode( $query->row('data') ) ) );
			
			if ( empty( $params ) ) return FALSE;
			
			// --------------------------------------------
			//	Load to cache
			// --------------------------------------------
			
			foreach ( $params as $key => $val )
			{
				$this->cached[$cache_name][$key] = $val;
			}
		}
			
		// --------------------------------------------
		//	Did we find our value after all of that?
		// --------------------------------------------
		
		if ( $this->cached[$cache_name][$param] !== FALSE )
		{
			// --------------------------------------------
			//	Delete this hash record form the DB
			// --------------------------------------------
		
			if ( $delete != 'nodelete' )
			{
				// --------------------------------------------
				//	Delete our hash cookie
				// --------------------------------------------
				//	We use cookies to record the fact that we have saved params to the DB. We do this to cut down on unecessary writes to the DB. But when we find and use a hash, we need to delete the cookie to reflect that.
				// --------------------------------------------
				
				$this->_delete_cookie( $tag_hash );
				
				if ( FBC_PARAMS_LOCATION != 'cookie' AND empty( $this->cached['param_deleted'][$tag_hash] ) )
				{
					ee()->db->query( "DELETE FROM $this->params_tbl WHERE hash = '" . ee()->db->escape_str( $tag_hash ) . "'" );
					
					$this->cached['param_deleted'][$tag_hash]	= TRUE;
				}				
			}
				
			return $this->cached[$cache_name][$param];
		}
		
		return FALSE;
    }
    
    /*	End get param */
	
	// --------------------------------------------------------------------
	
	/**
	 * Get possible permissions
	 *
	 * @access	public
	 * @return	array
	 */
    
    function get_possible_permissions()
    {
		// --------------------------------------------
		//	Just returning an array here folks
		// --------------------------------------------
		
		return $possible_permissions	= array(
			'user_about_me',
			'user_activities',
			'user_birthday',
			'user_checkins',
			'user_education_history',
			'user_events',
			'user_groups',
			'user_hometown',
			'user_interests',
			'user_likes',
			'user_location',
			'user_notes',
			'user_online_presence',
			'user_photo_video_tags',
			'user_photos',
			'user_relationships',
			'user_relationship_details',
			'user_religion_politics',
			'user_status',
			'user_videos',
			'user_website',
			'user_work_history',
			'email',
			'read_friendlists',
			'read_insights',
			'read_mailbox',
			'read_requests',
			'read_stream',
			'xmpp_login',
			'ads_management',
			
			'publish_stream',
			'create_event',
			'rsvp_event',
			'sms',
			'offline_access',
			'publish_checkins',
			'manage_friendlists',
			
			'manage_pages',
		);
	}
	
	/*	End get possible permissions */
	
	// --------------------------------------------------------------------
	
	/**
	 * Get safe member groups
	 *
	 * @access	public
	 * @return	array
	 */
    
	function get_safe_member_groups( $site_id = '' )
    {
 		// --------------------------------------------
        //  Set site id
        // --------------------------------------------
        
        $site_id	= ( $site_id == '' ) ? ee()->config->item('site_id'): $site_id;
    
 		// --------------------------------------------
        //  Prep Cache, Return if Set
        // --------------------------------------------
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( array( $site_id ) );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
    
 		// --------------------------------------------
        //	Grab member groups from DB
        // --------------------------------------------
        
		$sql	= "/* data.fbc.php " . __FUNCTION__ . " */ 
			SELECT group_id, group_title
			FROM exp_member_groups
			WHERE site_id = " . ee()->db->escape_str( $site_id ) . "
			AND can_view_offline_system = 'n'
			AND can_access_cp = 'n'
			ORDER BY group_title ASC";
        	
        $query	= ee()->db->query( $sql );
        
        if ( $query->num_rows() == 0 ) return FALSE;
        
        foreach ( $query->result_array() as $row )
        {
			$this->cached[$cache_name][$cache_hash][$row['group_id']] =	$row['group_title'];      	
        }
        
        return $this->cached[$cache_name][$cache_hash];
 	}
 	
 	/*	End get safe member groups */
	
	// --------------------------------------------------------------------
	
	/**
	 * Set facebook user id for member id
	 *
	 * @access	public
	 * @return	boolean
	 */
    
	function set_facebook_user_id_for_member_id( $uid = '', $member_id = '', $unsync = '' )
    {
 		// --------------------------------------------
        //  Prep Cache, Return if Set
        // --------------------------------------------
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( func_get_args() );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = FALSE;
    
 		// --------------------------------------------
        //  Validate
        // --------------------------------------------
        
        if ( is_numeric( $uid ) === FALSE OR is_numeric( $member_id ) === FALSE ) return FALSE;
        
 		// --------------------------------------------
        //	Does this member already have an FB UID recorded? If so fail out.
        // --------------------------------------------
        
        if ( $unsync != 'unsync' )
        {
			$sql	= "/* data.fbc.php " . __FUNCTION__ . " */ 
				SELECT COUNT(*) AS count
				FROM exp_members
				WHERE member_id = " . ee()->db->escape_str( $member_id ) . "
				AND facebook_connect_user_id != ''";
			
			$query	= ee()->db->query( $sql );
			
			if ( $query->row('count') > 0 ) return FALSE;
        }        

 		// --------------------------------------------
        //	Update the DB
        // --------------------------------------------
        
        $sql	= ee()->db->update_string(
        	'exp_members',
        	array( 'facebook_connect_user_id' => $uid ),
        	array( 'member_id' => $member_id )
        );
        			
        $query	= ee()->db->query( $sql );
    
 		// --------------------------------------------
        //  Return
        // --------------------------------------------
        
        return $this->cached[$cache_name][$cache_hash] = TRUE;
    }
    
    /*	End set facebook user id for member id */
	
	// --------------------------------------------------------------------
	
	/**
	 * Set params
	 *
	 * @access	public
	 * @return	boolean
	 */
    
    function set_params( $tag_hash = '', $params = array() )
    {
		// --------------------------------------------
		//	Empty?
		// --------------------------------------------
    	
    	if ( empty( $tag_hash ) OR empty( $params ) )
    	{
    		return FALSE;
    	}
    	
		// --------------------------------------------
		//	Are we using db or cookie based param location?
		// --------------------------------------------
		
		if ( FBC_PARAMS_LOCATION == 'cookie' )
		{
			$test_hash	= md5( serialize( $params ) );
			
			// --------------------------------------------
			//	Has the hash already been set? If so, is it valid?
			// --------------------------------------------
			
			if ( ee()->input->cookie('fbc2_params_' . $tag_hash ) !== FALSE AND ee()->input->cookie('fbc2_params_' . $tag_hash ) != '' )
			{
				if ( ( $decoded = base64_decode( urldecode( ee()->input->cookie('fbc2_params_' . $tag_hash ) ) ) ) !== FALSE )
				{
					if ( ( $decoded_arr = @unserialize( $decoded ) ) !== FALSE )
					{
						// --------------------------------------------
						//	We're trying to prevent people from tinkering with their cookies and further tinkering with the website that FBC is running on. So we run through a little hash validation trick that is by no means fool proof.
						// --------------------------------------------
					
						if ( ! empty( $decoded_arr['param_hash'] ) AND $decoded_arr['param_hash'] == $test_hash )
						{
							return $tag_hash;
						}
					}
				}
			}
			
			// --------------------------------------------
			//	Prepare the array to be saved
			// --------------------------------------------
			
			$params['param_hash']	= $test_hash;
			
			$hash	= urlencode( base64_encode( serialize( $params ) ) );
			
			// --------------------------------------------
			//	Save this to a cookie
			// --------------------------------------------
			
			ee()->functions->set_cookie( 'fbc2_params_' . $tag_hash, $hash );
		}
		else
		{
			// --------------------------------------------
			//	Have we done this already?
			// --------------------------------------------
			//	Update: Though this is a good idea for avoiding performance problems, something about the way I am implementing this cookie thing is breaking the workflow. If you have settings from a previous version of a login / logout button and then change those settings in the template, the old version is used. Very confusing.
			// --------------------------------------------
			
			if ( ee()->input->cookie('fbc2_params_' . $tag_hash ) !== FALSE AND ee()->input->cookie('fbc2_params_' . $tag_hash ) != '' )
			{
				// return ee()->input->cookie('fbc2_params_' . $tag_hash );
				return $tag_hash;
			}
			
			// --------------------------------------------
			//	Delete excess when older than 2 hours
			// --------------------------------------------
        
			srand( time() );
			if ( ( rand() % 100 ) < 5 ) 
			{
				$sql	= "/* data.fbc.php " . __FUNCTION__ . " */ 
					DELETE FROM $this->params_tbl
					WHERE entry_date < UNIX_TIMESTAMP()-7200";
					
				ee()->db->query( $sql );
			}
			
			// --------------------------------------------
			//	Insert
			// --------------------------------------------
			
			$hash	= urlencode( base64_encode( serialize( $params ) ) );
			
			ee()->db->query(
				"/* data.fbc.php " . __FUNCTION__ . " */
				INSERT INTO `" . $this->params_tbl . "`
				(
					`hash`,
					`entry_date`,
					`data`
				)
				VALUES
				(
					'" . $tag_hash . "',
					UNIX_TIMESTAMP(),
					'" . $hash . "'
				)"
			);
		}
			
		// --------------------------------------------
		//	Save this to a cookie
		// --------------------------------------------
		
		ee()->functions->set_cookie( 'fbc2_params_' . $tag_hash, $tag_hash, 7200 );
    		
		// --------------------------------------------
		//	Return
		// --------------------------------------------
		
		return $tag_hash;
    }
    
    /*	End set params */
	
	// --------------------------------------------------------------------
	
	/**
	 * Set preference
	 *
	 * @access	public
	 * @return	array
	 */
    
	function set_preference( $preferences = array(), $site_id = '' )
    {
 		// --------------------------------------------
        //  Site id
        // --------------------------------------------
        
        $site_id	= ( $site_id == '' ) ? ee()->config->item('site_id'): $site_id;
        
 		// --------------------------------------------
        //  Prep Cache, Return if Set
        // --------------------------------------------
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder( $preferences, $site_id );
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = array();
    
 		// --------------------------------------------
        //	Grab prefs from DB
        // --------------------------------------------
        
        ee()->load->helper('string');
        
        $sql	= "/* data.fbc.php " . __FUNCTION__ . " */
        	SELECT site_system_preferences
			FROM exp_sites
			WHERE site_id = " . ee()->db->escape_str( $site_id );
        	
        $query	= ee()->db->query( $sql );
        
        if ( $query->num_rows() == 0 ) return FALSE;
        
		if ( APP_VER < 2.0 )
		{
			$this->cached[$cache_name][$cache_hash]	= unserialize( $query->row('site_system_preferences') );
		}
		else
		{
			$this->cached[$cache_name][$cache_hash]	= unserialize( base64_decode( $query->row('site_system_preferences') ) );
		}
    
 		// --------------------------------------------
        //	Add our prefs
        // --------------------------------------------
        
        $prefs	= array();
        
        foreach ( explode( "|", FBC_PREFERENCES ) as $val )
        {
        	if ( isset( $preferences[$val] ) === TRUE )
        	{
        		$this->cached[$cache_name][$cache_hash][$val]	= $preferences[$val];
        	}
        }
				
		if ( APP_VER < 2.0 )
		{
			$this->cached[$cache_name][$cache_hash]	= serialize( $this->cached[$cache_name][$cache_hash] );
		}
		else
		{
			$this->cached[$cache_name][$cache_hash]	= base64_encode( serialize( $this->cached[$cache_name][$cache_hash] ) );
		}
			
		ee()->db->query(
			ee()->db->update_string(
				'exp_sites',
				array(
					'site_system_preferences' => $this->cached[$cache_name][$cache_hash]
				),
				array(
					'site_id'	=> $site_id
				)
			)
		);
		
		return TRUE;
	}
	
	/* End set preference */

	// --------------------------------------------------------------------	
}
// END CLASS Fbc_data
