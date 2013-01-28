<?php if ( ! defined('EXT')) exit('No direct script access allowed');
 
 /**
 * Solspace - FBC
 *
 * @package 	Solspace:FBC
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2010-2012, Solspace, Inc.
 * @link		http://www.solspace.com/docs/addon/c/Facebook_Connect/
 * @version		2.0.9
 * @filesource 	./system/expressionengine/third_party/fbc/
 */
 
 /**
 * FBC - Updater
 *
 * In charge of the install, uninstall, and updating of the module
 *
 * @package 	Solspace:FBC
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/fbc/upd.fbc.php
 */
 
if ( ! defined('APP_VER')) define('APP_VER', '2.0'); // EE 2.0's Wizard doesn't like CONSTANTs

require_once 'addon_builder/module_builder.php';

class Fbc_updater_base extends Module_builder_fbc
{
	var $theme_url			= '';

    var $module_actions		= array();
    var $hooks				= array();
    
	// --------------------------------------------------------------------

	/**
	 * Contructor
	 
	 * @access	public
	 * @return	null
	 */
    
	function Fbc_updater_base( )
    {    	
    	parent::Module_builder_fbc('fbc');
    	
		// --------------------------------------------
        //  Module Actions
        // --------------------------------------------
        
        $this->module_actions = explode('|', FBC_ACTIONS);
		
		// --------------------------------------------
        //  Extension Hooks
        // --------------------------------------------
        
        $this->default_settings = array();
        
        $default = array(
        	'class'		=> $this->extension_name,
			'settings'	=> '',
			'priority'	=> 10,
			'version'	=> FBC_VERSION,
			'enabled'	=> 'y'
		);
        
        $this->hooks = array(
			'insert_comment_end'	=> array_merge(
				$default,
				array(
					'method'	=> 'insert_comment_end',
					'hook'		=> 'insert_comment_end'
				)
			),
			'insert_rating_end'	=> array_merge(
				$default,
				array(
					'method'	=> 'insert_rating_end',
					'hook'		=> 'insert_rating_end'
				)
			),
			'status_update'	=> array_merge(
				$default,
				array(
					'method'	=> 'status_update',
					'hook'		=> 'friends_status_update_status'
				)
			)
		);
    	
		// --------------------------------------------
        //  Theme URL
        // --------------------------------------------
        
        $this->theme_url	= rtrim( ee()->config->item('theme_folder_url'), '/' ) . '/' . 'solspace_themes/fbc/default/';
    }
    /* END*/
	
	// --------------------------------------------------------------------

	/**
	 * Add prefs
	 *
	 * @access	public
	 * @return	array
	 */
    
    function _add_prefs( $mode = 'install' )
    {
		ee()->load->helper('string');
			
 		// --------------------------------------------
        //	Grab prefs from DB
        // --------------------------------------------
        
        $sql	= "SELECT site_id, site_system_preferences
					FROM exp_sites";
        	
        $query	= ee()->db->query( $sql );
        
        if ( $query->num_rows() == 0 ) return FALSE;
    	
		// --------------------------------------------
		//	Deinstall mode?
		// --------------------------------------------
		
		if ( $mode == 'deinstall' )
		{
			foreach ( $query->result_array() as $row )
			{
				if ( APP_VER < 2.0 )
				{
					$prefs	= unserialize( $row['site_system_preferences'] );
				}
				else
				{
					$prefs	= unserialize( base64_decode( $row['site_system_preferences'] ) );
				}
				
				foreach ( explode( "|", FBC_PREFERENCES ) as $val )
				{
					unset( $prefs[$val] );
				}
				
				if ( APP_VER < 2.0 )
				{
					$prefs	= serialize( $prefs );
				}
				else
				{
					$prefs	= base64_encode( serialize( $prefs ) );
				}
				
				ee()->db->query(
					ee()->db->update_string(
						'exp_sites',
						array(
							'site_system_preferences' => $prefs
						),
						array(
							'site_id'	=> $row['site_id']
						)
					)
				);
			}
		}
    	
		// --------------------------------------------
		//	Install mode?
		// --------------------------------------------
		
		if ( $mode == 'install' )
		{
			foreach ( $query->result_array() as $row )
			{
				if ( APP_VER < 2.0 )
				{
					$prefs	= unserialize( $row['site_system_preferences'] );
				}
				else
				{				
					$prefs	= unserialize( base64_decode( $row['site_system_preferences'] ) );
				}
				
				foreach ( explode( "|", FBC_PREFERENCES ) as $val )
				{
					$prefs[$val]	= '';
				}
				
				// --------------------------------------------
				//	Set eligible member groups
				// --------------------------------------------
				
				$prefs['fbc_eligible_member_groups']	= implode( "|", array_keys( $this->data->get_safe_member_groups( $row['site_id'] ) ) );
				
				// --------------------------------------------
				//	Set fbc member group
				// --------------------------------------------
				
				$prefs['fbc_member_group']	= 5;
				
				// --------------------------------------------
				//	Set member activation
				// --------------------------------------------
				
				$prefs['fbc_account_activation']	= 'fbc_no_activation';
				
				// --------------------------------------------
				//	Set FB connect url
				// --------------------------------------------
				
				$prefs['fbc_connect_url']	= $this->theme_url;
				
				// --------------------------------------------
				//	Update DB
				// --------------------------------------------
				
				if ( APP_VER < 2.0 )
				{
					$prefs	= serialize( $prefs );
				}
				else
				{
					$prefs	= base64_encode( serialize( $prefs ) );
				}
				
				ee()->db->query(
					ee()->db->update_string(
						'exp_sites',
						array(
							'site_system_preferences' => $prefs
						),
						array(
							'site_id'	=> $row['site_id']
						)
					)
				);
			}
		}
		
		return;
    }
    
    /*	End add prefs */
	
	// --------------------------------------------------------------------

	/**
	 * Module Installer
	 *
	 * @access	public
	 * @return	bool
	 */

    function install()
    {
        // Already installed, let's not install again.
        if ($this->database_version() !== FALSE)
        {
        	return FALSE;
        }
        
        // --------------------------------------------
        //  Our Default Install
        // --------------------------------------------
        
        if ($this->default_module_install() == FALSE)
        {
        	return FALSE;
        }
        
        // --------------------------------------------
        //	Add prefs
        // --------------------------------------------
        
        $this->_add_prefs();
        
        // --------------------------------------------
        //	Additional DB work
        // --------------------------------------------
        
        $sql	= array();
        
        $sql	= array_merge( $sql, $this->_sql_alter_comments( 'install' ), $this->_sql_alter_members( 'install' ) );
		
		// --------------------------------------------
        //  Module Install
        // --------------------------------------------
        
        $sql[] = ee()->db->insert_string(
        	'exp_modules', array(
        		'module_name'	=> $this->class_name,
				'module_version'	=> FBC_VERSION,
				'has_cp_backend'	=> 'y'
			)
		);
		
        foreach ($sql as $query)
        {
            ee()->db->query($query);
        }
        
		// --------------------------------------------
        //	Show silly ass flash message for no earthly reason.
        // --------------------------------------------
        
		if (APP_VER >= 2.0)
		{
			ee()->session->set_flashdata( 'message_success', ee()->lang->line( 'module_has_been_installed' ) . NBS . ee()->lang->line( 'fbc_module_name' ) );
		}
        
        return TRUE;
    }
    
	/* END install() */
	
	// --------------------------------------------------------------------

	/**
	 * SQL for exp_comments alters
	 *
	 * @access	public
	 * @return	array
	 */
    
    function _sql_alter_comments( $mode = 'install' )
    {
    	$sql	= array();
		
		if ( $mode == 'install' )
		{
			// --------------------------------------------
			//	Alter email column to accommodate longer Facebook proxied email addresses
			// --------------------------------------------
			
			$query	= ee()->db->query( "DESCRIBE exp_comments email" );
			
			if ( $query->row('Type') !== 'varchar(100)' )
			{
				$sql[]	= "ALTER TABLE exp_comments CHANGE email email varchar(100) NOT NULL";			
			}
		}
			
		return $sql;
    }
    
    /**	End SQL for exp_comments alters */
	
	// --------------------------------------------------------------------

	/**
	 * SQL for exp_members alters
	 *
	 * @access	public
	 * @return	array
	 */
    
    function _sql_alter_members( $mode = 'install' )
    {
    	$sql	= array();
    	
		// --------------------------------------------
		//	Deinstall mode?
		// --------------------------------------------
		
		if ( $mode == 'deinstall' )
		{		
			$sql[]	= "ALTER TABLE exp_members DROP facebook_connect_user_id";
			
			return $sql;
		}
    	
		// --------------------------------------------
		//	Check for columns in members table
		// --------------------------------------------
			
		if ( $this->column_exists( 'facebook_connect_user_id', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE exp_members ADD facebook_connect_user_id bigint(20) unsigned NOT NULL default '0' AFTER member_id";
		}
    	
		// --------------------------------------------
		//	Alter email column to accommodate longer Facebook proxied email addresses
		// --------------------------------------------
		
		$query	= ee()->db->query( "DESCRIBE exp_members email" );
		
		if ( $query->row('Type') !== 'varchar(100)' )
		{
			$sql[]	= "ALTER TABLE exp_members CHANGE email email varchar(100) NOT NULL";			
		}
			
		return $sql;
    }
    
    /**	End SQL for exp_members alters */
    
	// --------------------------------------------------------------------

	/**
	 * Module Uninstaller
	 
	 * @access	public
	 * @return	bool
	 */

    function uninstall()
    {
        // Cannot uninstall what does not exist, right?
        if ($this->database_version() === FALSE)
        {
        	return FALSE;
        }
        
        // --------------------------------------------
        //	Add prefs
        // --------------------------------------------
        
        $this->_add_prefs( 'deinstall' );
        
		// --------------------------------------------
        //	Additional uninstall routines
        // --------------------------------------------
        
        $sql	= array();
        
        $sql	= array_merge( $sql, $this->_sql_alter_members( 'deinstall' ) );
        
        foreach ($sql as $query)
        {
            ee()->db->query($query);
        }
        
		// --------------------------------------------
        //  Default Module Uninstall
        // --------------------------------------------
        
        if ($this->default_module_uninstall() == FALSE)
        {
        	return FALSE;
        }
        
		// --------------------------------------------
        //	Show silly ass flash message for no earthly reason.
        // --------------------------------------------
        
		if (APP_VER >= 2.0)
		{
			ee()->session->set_flashdata( 'message_success', ee()->lang->line( 'module_has_been_removed' ) . NBS . ee()->lang->line( 'fbc_module_name' ) );
		}
        
        return TRUE;
    }
    /* END */


	// --------------------------------------------------------------------

	/**
	 * Module Updater
	 
	 * @access	public
	 * @return	bool
	 */
    
    function update()
    {
    	// --------------------------------------------
        //  ExpressionEngine 2.x attempts to do automatic updates.  
        //		- Mitchell questioned clients/customers and discovered that the majority preferred to update
        //		themselves, especially on higher traffic sites. So, we forbid EE 2.x from doing updates
        //		unless it comes through our update form.
        // --------------------------------------------
        
    	if ( ! isset($_POST['run_update']) OR $_POST['run_update'] != 'y')
    	{
    		return FALSE;
    	}
    	
    	// --------------------------------------------
        //  Default Module Update
        // --------------------------------------------
    
    	$this->default_module_update();
    	
    	$this->actions();
        
        // --------------------------------------------
        //	Do DB work
        // --------------------------------------------
        
        if ( ! file_exists($this->addon_path.strtolower($this->lower_name).'.sql'))
        {
        	return FALSE;
        }
        
 		$sql = preg_split("/;;\s*(\n+|$)/", file_get_contents($this->addon_path.strtolower($this->lower_name).'.sql'), -1, PREG_SPLIT_NO_EMPTY);
        
		if (sizeof($sql) == 0)
		{
			return FALSE;
		}
		
		foreach($sql as $i => $query)
		{
			$sql[$i] = trim($query);
		}
        
        // --------------------------------------------
        //	Add FB Connect URL
        // --------------------------------------------
        
        if ( $this->version_compare($this->database_version(), '<', '1.0.1') )
        {
        	$sites	= $this->data->get_sites();
        	
        	foreach ( array_keys( $this->data->get_sites() ) as $site_id )
        	{
				$this->data->set_preference( array( 'fbc_connect_url' => $this->theme_url ), $site_id );
        	}
        }
        
        // --------------------------------------------
        //	Update for 1.0.5
        // --------------------------------------------
        
        if ( $this->version_compare($this->database_version(), '<', '1.0.5') )
        {
        	$sql	= 'ALTER TABLE exp_fbc_params CHANGE `hash` `hash` varchar(32) NOT NULL';
        	
        	ee()->db->query( $sql );
        }
        
        // --------------------------------------------
        //	Update for 2.0.0
        // --------------------------------------------
        //	Param handling changed a bit in 2.0.0 so we want to delete old param values and restart
        // --------------------------------------------
        
        if ( $this->version_compare($this->database_version(), '<', '2.0.0') )
        {
        	$sql	= 'TRUNCATE exp_fbc_params';
        	
        	ee()->db->query( $sql );
        }
        
        // --------------------------------------------
        //	Update for 2.0.1
        // --------------------------------------------
        //	Delete all FBC tables. We use no extra tables for FBC
        // --------------------------------------------
        
        if ( $this->version_compare($this->database_version(), '<', '2.0.1') )
        {        	
        	if ( ee()->db->table_exists( 'exp_fbc_member_fields_map' ) === TRUE )
        	{
        		// ee()->db->query( "DROP TABLE exp_fbc_member_fields_map" );
        	}
        }
        
        // --------------------------------------------
        //  Version Number Update - LAST!
        // --------------------------------------------
    	
    	ee()->db->query(
    		ee()->db->update_string(
    			'exp_modules',
				array('module_version'	=> FBC_VERSION), 
				array('module_name'		=> $this->class_name)
			)
		);    									
    									
    	return TRUE;
    }
    
    /* END update() */
}

/* END Class */
?>