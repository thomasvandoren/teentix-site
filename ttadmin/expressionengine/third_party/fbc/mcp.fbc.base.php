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
 * FBC - Control Panel
 *
 * The Control Panel master class that handles all of the CP Requests and Displaying
 *
 * @package 	Solspace:FBC
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/fbc/mcp.fbc.php
 */

require_once 'addon_builder/module_builder.php';

class Fbc_cp_base extends Module_builder_fbc
{
	var $api;

    // -------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	bool		Enable calling of methods based on URI string
	 * @return	string
	 */
    
    function __construct( $switch = TRUE )
    {
        parent::Module_builder_fbc('fbc');
        
		if ((bool) $switch === FALSE) return; // Install or Uninstall Request
        
        // --------------------------------------------
        //  Themes folder
        // --------------------------------------------
        
        $this->theme_url	= rtrim( $this->sc->theme_url, '/' ) . '/' . 'fbc/';
		$this->cached_vars['theme_url']	= $this->theme_url;
        
		// --------------------------------------------
        //  Module Menu Items
        // --------------------------------------------
        
        $menu	= array(
        	'module_preferences'	=> array(
        		'name'	=> 'preferences',
        		'link'  => $this->base . AMP . 'method=preferences',
        		'title' => ee()->lang->line('preferences')
			),
        	'module_diagnostics'	=> array(
        		'name'	=> 'diagnostics',
        		'link'  => $this->base . AMP . 'method=diagnostics',
        		'title' => ee()->lang->line('diagnostics')
			),
			'module_documentation'	=> array(
        		'name'	=> 'documentation',
				'link'  => FBC_DOCS_URL,
				'title' => ee()->lang->line('online_documentation') . ((APP_VER < 2.0) ? ' (' . FBC_VERSION . ')' : ''),
				'new_window' => TRUE
			)
		);
        
		$this->cached_vars['lang_module_version'] 	= ee()->lang->line('fbc_module_version');        
		$this->cached_vars['module_version'] 		= FBC_VERSION;
        $this->cached_vars['module_menu_highlight'] = 'module_preferences';
        $this->cached_vars['module_menu'] 			= $menu;
                
        // --------------------------------------------
        //  Sites
        // --------------------------------------------
        
        $this->cached_vars['sites']	= array();
        
        foreach( $this->data->get_sites() as $site_id => $site_label )
        {
        	$this->cached_vars['sites'][$site_id] = $site_label;
        }
			
		// --------------------------------------------
		//  Module Installed and What Version?
		// --------------------------------------------
			
		if ($this->database_version() == FALSE)
		{
			return;
		}
		elseif($this->version_compare($this->database_version(), '<', FBC_VERSION))
		{
			if (APP_VER < 2.0)
			{
				if ($this->fbc_module_update() === FALSE)
				{
					return;
				}
			}
			else
			{
				// For EE 2.x, we need to redirect the request to Update Routine
				$_GET['method'] = 'fbc_module_update';
			}
		}
        
        // --------------------------------------------
		//  Request and View Builder
		// --------------------------------------------
        
        if (APP_VER < 2.0 AND $switch !== FALSE)
        {
        	if (ee()->input->get('method') === FALSE)
        	{
        		$this->index();
        	}
        	elseif( ! method_exists($this, ee()->input->get('method')))
        	{
        		$this->add_crumb(ee()->lang->line('invalid_request'));
        		$this->cached_vars['error_message'] = ee()->lang->line('invalid_request');
        		
        		return $this->ee_cp_view('error_page.html');
        	}
        	else
        	{
        		$this->{ee()->input->get('method')}();
        	}
        }
    }
    /* END */

    // -------------------------------------------------------------

	/**
	 * Api
	 *
	 * Invoke the api object
	 *
	 * @access	public
	 * @return	boolean
	 */
	 
	function api()
	{
		if ( isset( $this->api->cached ) === TRUE ) return TRUE;
	
        // --------------------------------------------
        //  API Object
        // --------------------------------------------
        
		require_once $this->addon_path . 'api.fbc.php';
		
		$this->api = new Fbc_api();		
	}
	
	/*	End api */
	
	// -------------------------------------------------------------

	/**
	 * Index
	 
	 * @access	public
	 * @param	string
	 * @return	null
	 */
    
	function index( $message='' )
    {
    	return $this->preferences( $message );
	}
	
	/* End index */
	
	// -------------------------------------------------------------

	/**
	 * Preferences
	 
	 * @access	public
	 * @param	string
	 * @return	null
	 */
    
	function preferences( $message='' )
    {
		// --------------------------------------------
		//	Prep vars
		// --------------------------------------------
		
		$this->cached_vars['member_groups']			= $this->data->get_member_groups();
		$this->cached_vars['account_activation']	= array( 'fbc_no_activation', 'fbc_email_activation', 'fbc_admin_activation' );
		$this->cached_vars['prefs']['fbc_app_id']	= '';
		$this->cached_vars['prefs']['fbc_secret']	= '';
		$this->cached_vars['prefs']['fbc_eligible_member_groups']	= '';
		$this->cached_vars['prefs']['fbc_member_group']	= ( isset( $this->cached_vars['safe_member_groups'][5] ) === TRUE ) ? 5: 3;
		$this->cached_vars['prefs']['fbc_account_activation']	= 'fbc_no_activation';
		$this->cached_vars['prefs']['fbc_confirm_account_sync']	= 'n';
		$this->cached_vars['prefs']['fbc_passive_registration']	= 'y';
        
		// --------------------------------------------
		//	Set vars
		// --------------------------------------------
		
		foreach ( $this->cached_vars['prefs'] as $key => $val )
		{		
			if ( ee()->config->item( $key ) !== FALSE )
			{
				$this->cached_vars['prefs'][$key]	= ee()->config->item( $key );
			}
		}
        
		// --------------------------------------------
		//	Are we updating / inserting?
		// --------------------------------------------
		
		if ( ee()->input->post('fbc_app_id') !== FALSE )
		{
			// --------------------------------------------
			//	Prep vars
			// --------------------------------------------
			
			foreach ( $this->cached_vars['prefs'] as $key => $val )
			{
				if ( ee()->input->post($key) !== FALSE )
				{
					$this->cached_vars['prefs'][$key]	= ee()->input->post($key);
				}
			}
			
			// --------------------------------------------
			//	Special handling for eligible member groups
			// --------------------------------------------
			
			$this->cached_vars['prefs']['fbc_eligible_member_groups']	= '';
			
			if ( ! empty( $_POST['fbc_eligible_member_groups'] ) AND is_array( $_POST['fbc_eligible_member_groups'] ) === TRUE )
			{
				$temp	= array();
			
				foreach ( $_POST['fbc_eligible_member_groups'] as $val )
				{
					if ( is_numeric( $val ) === FALSE ) continue;
					
					$temp[]	= $val;
				}
				
				$this->cached_vars['prefs']['fbc_eligible_member_groups']	= implode( "|", $temp );
			}
			
			// --------------------------------------------
			//	Check DB for insert / update
			// --------------------------------------------
			
			$message	= '';
			
			if ( $this->data->set_preference( $this->cached_vars['prefs'], ee()->config->item('site_id') ) !== FALSE )
			{
				$message	= ee()->lang->line( 'preferences_updated' );
			}			
		}
        
		// --------------------------------------------
		//	Prepare eligible member groups
		// --------------------------------------------
		
		$this->cached_vars['prefs']['fbc_eligible_member_groups']	= explode( "|", $this->cached_vars['prefs']['fbc_eligible_member_groups'] );
        
		// --------------------------------------------
		//	Prep message
		// --------------------------------------------
		
		$this->_prep_message( $message );
        
		// --------------------------------------------
		//  Title and Crumbs
		// --------------------------------------------
		
		$this->add_crumb(ee()->lang->line('preferences'));
		$this->build_crumbs();
		
		// --------------------------------------------
        //  Load Homepage
        // --------------------------------------------
        
		return $this->ee_cp_view('preferences.html');
	}
	
	/* End preferences */
	
	// -------------------------------------------------------------

	/**
	 * Prep message
	 
	 * @access	private
	 * @param	message
	 * @return	boolean
	 */
	
	function _prep_message( $message = '' )
	{
        if ( $message == '' AND isset( $_GET['msg'] ) )
        {
        	$message = ee()->lang->line( $_GET['msg'] );
        }
		
		$this->cached_vars['message']	= $message;
		
		return TRUE;
	}
	
	/*	End prep message */
	
	// -------------------------------------------------------------

	/**
	 * Diagnostics
	 
	 * @access	public
	 * @param	string
	 * @return	null
	 */
    
	function diagnostics( $message='' )
    {
		// --------------------------------------------
		//	API Credentials present
		// --------------------------------------------
		
		$this->cached_vars['api_credentials_present']	= ee()->lang->line('api_credentials_are_present');
		
		if ( ee()->config->item('fbc_app_id') === FALSE OR ee()->config->item('fbc_app_id') == '' OR ee()->config->item('fbc_secret') === FALSE OR ee()->config->item('fbc_secret') == '' )
		{
			$this->cached_vars['api_credentials_present']	= ee()->lang->line('api_credentials_are_not_present');
		}
        
		// --------------------------------------------
		//	API successful connect
		// --------------------------------------------
		
		$this->api();
		
		$this->api->connect_to_api();
		
		$this->cached_vars['api_successful_connect']	= ee()->lang->line('api_connect_was_not_successful');
		
		if ( $this->api->user )
		{
			$this->cached_vars['api_successful_connect']	= ee()->lang->line('api_connect_was_successful');
		}
        
		// --------------------------------------------
		//	API login button
		// --------------------------------------------
		
		$this->cached_vars['facebook_loader_js']	= $this->data->get_facebook_loader_js();
		
		$this->cached_vars['api_successful_login']	= ee()->lang->line('api_login_was_successful');
		
		$this->cached_vars['fbc_app_id']	= ee()->config->item('fbc_app_id');
        
		// --------------------------------------------
		//	Try login
		// --------------------------------------------
		
		try
		{
			$appobj = $this->api->FB->api( ee()->config->item('fbc_app_id') );
			
			$app	= array();
			
			if ( is_object( $appobj ) === TRUE )
			{
				$app['connect_url']	= $appobj->connect_url;
				$app['app_id']		= $appobj->app_id;
			}
			elseif ( is_array( $appobj ) === TRUE )
			{
				$app	= $appobj;
			}
			
			if ( empty( $app['connect_url'] ) )
			{
				$this->cached_vars['api_connect_url_test']		= ee()->lang->line('api_connect_url_is_empty');
			}
			elseif ( $app['connect_url'] != $this->cached_vars['api_connect_url'] )
			{
				$this->cached_vars['api_connect_url_test']		= str_replace( array( '%incorrect_connect_url%', '%correct_connect_url%' ), array( $app['connect_url'], $this->cached_vars['api_connect_url'] ), ee()->lang->line('api_connect_url_incorrect') );
			}
			else
			{
				$this->cached_vars['api_connect_url_test']	= '';
			}
			
			if ( ! empty( $app['app_id'] ) )
			{
				$this->cached_vars['api_connect_url_facebook']	= str_replace( '%fbc_url%', 'http://www.facebook.com/developers/editapp.php?app_id=' . $app['app_id'], ee()->lang->line('api_connect_url_facebook') );
			}
		}
		catch (Exception $e)
		{
		}
        
		// --------------------------------------------
		//	Prep message
		// --------------------------------------------
		
		$this->_prep_message( $message );
        
		// --------------------------------------------
		//  Title and Crumbs
		// --------------------------------------------
		
		$this->add_crumb(ee()->lang->line('diagnostics'));
		$this->build_crumbs();
		
		// --------------------------------------------
        //  Load Homepage
        // --------------------------------------------
        
		$this->cached_vars['module_menu_highlight'] = 'module_diagnostics';
		return $this->ee_cp_view('diagnostics.html');
    }
    
    /* End diagnostics *

	// -------------------------------------------------------------

	/**
	 * Module Installation
	 *
	 * Due to the nature of the 1.x branch of ExpressionEngine, this function is always required.
	 * However, because of the large size of the module the actual code for installing, uninstalling,
	 * and upgrading is located in a separate file to make coding easier
	 *
	 * @access	public
	 * @return	bool
	 */

    function fbc_module_install()
    {
       require_once $this->addon_path.'upd.fbc.php';
    	
    	$U = new Fbc_updater();
    	return $U->install();
    }
	/* END fbc_module_install() */    
    
	// -------------------------------------------------------------

	/**
	 * Module Uninstallation
	 *
	 * Due to the nature of the 1.x branch of ExpressionEngine, this function is always required.
	 * However, because of the large size of the module the actual code for installing, uninstalling,
	 * and upgrading is located in a separate file to make coding easier
	 *
	 * @access	public
	 * @return	bool
	 */

    function fbc_module_deinstall()
    {
       require_once $this->addon_path.'upd.fbc.php';
    	
    	$U = new Fbc_updater();
    	return $U->uninstall();
    }
    /* END fbc_module_deinstall() */


	// -------------------------------------------------------------

	/**
	 * Module Upgrading
	 *
	 * This function is not required by the 1.x branch of ExpressionEngine by default.  However,
	 * as the install and deinstall ones are, we are just going to keep the habit and include it
	 * anyhow.
	 *		- Originally, the $current variable was going to be passed via parameter, but as there might
	 *		  be a further use for such a variable throughout the module at a later date we made it
	 *		  a class variable.
	 *		
	 *
	 * @access	public
	 * @return	bool
	 */
    
    function fbc_module_update()
    {
    	if ( ! isset($_POST['run_update']) OR $_POST['run_update'] != 'y')
    	{
    		$this->add_crumb(ee()->lang->line('update_fbc_module'));
			$this->cached_vars['form_url'] = $this->cached_vars['base_uri'] . '&method=fbc_module_update';
			return $this->ee_cp_view('update_module.html');
		}
    
    	require_once $this->addon_path.'upd.fbc.base.php';
    	
    	$U = new Fbc_updater_base();
    	
    	if ($U->update() !== TRUE)
    	{
    		return $this->index(ee()->lang->line('update_failure'));
    	}
    	else
    	{
    		return $this->index(ee()->lang->line('update_successful'));
    	}
    }
    /* END fbc_module_update() */

	// -------------------------------------------------------------
	
}
// END CLASS Fbc
