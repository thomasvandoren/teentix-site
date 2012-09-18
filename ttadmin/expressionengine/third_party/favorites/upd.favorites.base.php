<?php if ( ! defined('EXT')) exit('No direct script access allowed');
 
 /**
 * Solspace - Favorites
 *
 * @package		Solspace:Favorites
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2008-2012, Solspace, Inc.
 * @link		http://www.solspace.com/docs/addon/c/Favorites/
 * @version		3.0.5
 * @filesource 	./system/expressionengine/third_party/favorites/
 */
 
 /**
 * Favorites - Updater
 *
 * In charge of the install, uninstall, and updating of the module
 *
 * @package 	Solspace:Favorites
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/favorites/upd.favorites.php
 */
 
if ( ! defined('APP_VER')) define('APP_VER', '2.0'); // EE 2.0's Wizard doesn't like CONSTANTs

require_once 'addon_builder/module_builder.php';

class Favorites_updater_base extends Module_builder_favorites
{
    
    public $module_actions		= array();
    public $hooks				= array();
	public $clean_site_id		= 0;
    
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */
    
	public function __construct()
    {    	
    	parent::Module_builder_favorites('favorites');
    	
		// --------------------------------------------
        //  Module Actions
        // --------------------------------------------
        
        $this->module_actions = array();
		
		// --------------------------------------------
        //  Extension Hooks
        // --------------------------------------------
        
        $this->default_settings = array();
        
        $default = array(	
			'class'        => $this->extension_name,
			'settings'     => '', 								// NEVER!				
			'priority'     => 10,                                        				
			'version'      => FAVORITES_VERSION,               				
			'enabled'      => 'y'                                        				
		);
        
		$is2 = ! (APP_VER < 2.0);

        $this->hooks = array(
			array_merge($default, array(	
				'method'		=> 'modify_sql',
				'hook'  		=> $is2 ? 'channel_module_alter_order' : 'weblog_module_alter_order',
				'priority'		=> 7,
			)),
			array_merge($default, array(	
				'method'		=> 'add_favorite',
				'hook'  		=> $is2 ? 'entry_submission_end' : 'submit_new_entry_end',
				'priority'		=> 7,
			)),
			array_merge($default, array(	
				'method'		=> 'delete_members',
				'hook'  		=> 'cp_members_member_delete_end',
				'priority'		=> 7,
			)),
			array_merge($default, array(	
				'method'		=> 'delete_entry',
				'hook'  		=> 'delete_entries_loop',
				'priority'		=> 7,
			)),
			array_merge($default, array(	
				'method'		=> 'parse_favorites_date',
				'hook'  		=> $is2 ? 'channel_entries_tagdata' : 'weblog_entries_tagdata',
			)),
 		);

		//saves a few function calls
		$this->clean_site_id = ee()->db->escape_str(ee()->config->item('site_id'));

    }
    // END Favorites_updater_base()
	
	
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
        
		//clean up any old _ext form hooks left over in exp_extensions
		ee()->db->query("DELETE FROM exp_extensions WHERE class = 'favorites_ext'");

        // --------------------------------------------
        //  Our Default Install
        // --------------------------------------------
        
        if ($this->default_module_install() == FALSE)
        {
        	return FALSE;
        }

		// --------------------------------------------
        //  Module Install
        // --------------------------------------------
        
        $sql[] = ee()->db->insert_string(
        	'exp_modules',
        	array(
        		'module_name'		=> $this->class_name,
        		'module_version'	=> FAVORITES_VERSION,
        		'has_cp_backend'	=> 'y'
			)
		);
		
		//alter native EE tables if the don't have the proper addins
		$this->alter_ee_tables();

		//fill prefs tables with default prefs
		if ( ee()->db->table_exists( 'exp_sites' ) === TRUE )
		{
			$query	= ee()->db->query( "SELECT site_id FROM exp_sites" );

			foreach ( $query->result_array() as $row )
			{
				$this->data->set_default_site_prefs($row['site_id']);
			}
		}
		else
		{
			$this->data->set_default_site_prefs(1);
		}
		
		foreach ($sql as $query)
        {
            ee()->db->query($query);
        }
        
        return TRUE;
    }
	// END install()
    

	// --------------------------------------------------------------------

	/**
	 * Module Uninstaller
	 *
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
        //  Default Module Uninstall
        // --------------------------------------------
        
        if ($this->default_module_uninstall() == FALSE)
        {
        	return FALSE;
        }
        
		// --------------------------------------------
        //  custom uninstalls
        // --------------------------------------------

		$sql	= array();

		$sql[]	= "ALTER TABLE {$this->sc->db->channel_titles} DROP favorites_count";

	    $sql[]	= "ALTER TABLE {$this->sc->db->channel_titles} DROP favorites_count_public";

	    $sql[]	= "ALTER TABLE exp_members DROP favorites_count";

	    $sql[]	= "ALTER TABLE exp_members DROP favorites_count_public";
	
	    foreach ($sql as $query)
	    {
	        ee()->db->query($query);
	    }

        return TRUE;
    }
    // END uninstall()


	// --------------------------------------------------------------------

	/**
	 * Module Updater
	 *
	 * @access	public
	 * @return	bool
	 */
    
    function update()
    {
    	// --------------------------------------------
        //  ExpressionEngine 2.x attempts to do automatic updates.  
        //	- Mitchell questioned clients/customers and discovered 
		//    that the majority preferred to update
        //	  themselves, especially on higher traffic sites. 
		//    So, we forbid EE 2.x from doing updates
        //	  unless it comes through our update form.
        // --------------------------------------------
        
    	if ( ! isset($_POST['run_update']) OR $_POST['run_update'] != 'y')
    	{
    		return FALSE;
    	}
    	
		//get this info BEFORE we run install_module_sql() so we can see where data
		// needs to be inserted
		$prefs_existed 		= ee()->db->table_exists( 'exp_favorites_prefs' );
		$favorites_existed 	= ee()->db->table_exists( 'exp_favorites' );

    	// --------------------------------------------
        //  Default Module Update
        // --------------------------------------------
    
    	//remove any old EXTs before doing update

    	//clean up any old _ext form hooks left over in exp_extensions
		ee()->db->query("DELETE FROM exp_extensions WHERE class = 'favorites_ext'");

    	$this->default_module_update();
    	
    	$this->actions();
    	
		//runs sql file and install tables that are missing
		$this->install_module_sql();

    	// --------------------------------------------
        //  Database Table Adjustments
        // --------------------------------------------

		$sql	= array();

		if ( $this->_column_exists( 'site_id', 'exp_favorites' ) === FALSE )
		{
			ee()->db->query("ALTER TABLE 	exp_favorites 
					   ADD 		   	site_id smallint(3) unsigned NOT NULL default 1 	
					   AFTER 		member_id");
		}

		if ( $this->_column_exists( 'type', 'exp_favorites' ) === FALSE )
		{
			ee()->db->query("ALTER TABLE 	exp_favorites 
					   ADD 			type varchar(16) NOT NULL default 'entry_id' 
					   AFTER 		favorites_id");
		}
			
		if ( $this->version_compare($this->database_version(), '<', '2.5.3') )
		{
			ee()->db->query("ALTER TABLE `exp_favorites` ADD INDEX (`author_id`)");
			ee()->db->query("ALTER TABLE `exp_favorites` ADD INDEX (`entry_id`)");
			ee()->db->query("ALTER TABLE `exp_favorites` ADD INDEX (`member_id`)");
			ee()->db->query("ALTER TABLE `exp_favorites` ADD INDEX (`site_id`)");
			ee()->db->query("ALTER TABLE `exp_favorites` ADD INDEX (`public`)");
			ee()->db->query("ALTER TABLE `exp_favorites` ADD INDEX (`type`)");
		}
	
		//if the prefs were already there, set defaults and check for updates
		if ( $prefs_existed )
		{
			if ( $this->_column_exists( 'site_id', 'exp_favorites_prefs' ) === FALSE )
			{
				ee()->db->query("ALTER TABLE 	exp_favorites_prefs 
						   ADD 			site_id smallint(3) unsigned NOT NULL default 1 
						   AFTER 		member_id");
			}

			if ( $this->_column_exists( 'add_favorite', 'exp_favorites_prefs' ) === FALSE )
			{
				ee()->db->query("ALTER TABLE 	exp_favorites_prefs 
						   ADD 			`add_favorite` char(1) NOT NULL DEFAULT 'n'");
			}

			if ( $this->_column_exists( 'success_delete_all', 'exp_favorites_prefs' ) === FALSE )
			{
				ee()->db->query("ALTER TABLE 	`exp_favorites_prefs` 
						   ADD 			`success_delete_all` VARCHAR(100) NOT NULL 
						   AFTER 		`success_delete`");
						
				ee()->db->query("UPDATE 		`exp_favorites_prefs` 
						   SET 			`success_delete_all` = " . 
										"'All of your Favorites have been successfully deleted.'");
			}

			// -------------------------------------------
			//  Insert prefs for each site
			// -------------------------------------------

			if ( ee()->db->table_exists( 'exp_sites' ) === TRUE )
			{				
				$query	= ee()->db->query( "SELECT site_id FROM exp_sites" );

				foreach ( $query->result_array() as $row )
				{
					$this->data->set_default_site_prefs($row['site_id']);
				}
			}
		}
		else
		{
			if ( ee()->db->table_exists( 'exp_sites' ) === TRUE )
			{
				$query	= ee()->db->query( "SELECT site_id FROM exp_sites" );

				foreach ( $query->result_array() as $row )
				{
					$this->data->set_default_site_prefs($row['site_id']);
				}
			}
			else
			{
				$this->data->set_default_site_prefs(1);
			}
		}		
		
		//run all stored queries
		foreach ($sql as $query)
	    {
	        ee()->db->query($query);
	    }
	    
		//alter native EE tables if the don't have the proper addins
		$this->alter_ee_tables();
	
		// -------------------------------------------
		//	Update exp_weblog_titles/exp_channel_titles 
		//	to insert appropriate counts.
		// -------------------------------------------

		$sql	= array();

		$query	= ee()->db->query( 
			"SELECT 	entry_id, COUNT(*) AS count 
			 FROM 		exp_favorites 
			 WHERE 		site_id = '{$this->clean_site_id}' 
			 GROUP BY 	entry_id" 
		);

		if ( $query->num_rows() > 0 )
		{
			foreach ( $query->result_array() as $row )
			{
				$sql[]	= ee()->db->update_string( 
					$this->sc->db->channel_titles, 
					array( 'favorites_count' => $row['count'] ), 
					array( 'entry_id' 		 => $row['entry_id'] ) 
				);
			}
		}

		$query	= ee()->db->query( 
			"SELECT 	entry_id, COUNT(*) AS count 
			 FROM 		exp_favorites 
			 WHERE 		site_id = '{$this->clean_site_id}' 
			 AND 		public = 'y' 
			 GROUP BY 	entry_id" );

		if ( $query->num_rows() > 0 )
		{
			foreach ( $query->result_array() as $row )
			{
				$sql[]	= ee()->db->update_string( 
					$this->sc->db->channel_titles, 
					array( 'favorites_count_public' => $row['count'] ), 
					array( 'entry_id' 				=> $row['entry_id'] ) 
				);
			}
		}	

        // --------------------------------------------
        //  clean up site prefs
        // --------------------------------------------

		$site_id_query = ee()->db->query(
			"SELECT DISTINCT site_id 
			 FROM 			 exp_favorites_prefs"
		);

		foreach($site_id_query->result_array() as $row)
		{			
			$id_query = ee()->db->query(
				"SELECT * 
				 FROM 	exp_favorites_prefs 
				 WHERE 	site_id = '" . ee()->db->escape_str($row['site_id']) . "'"
			);

			//too many settings?
			if ($id_query->num_rows() > 1)
			{
				$first_count = TRUE;
				
				foreach($id_query->result_array() as $row_2)
				{
					//skip first item, we want to keep it
					if ($first_count)
					{
						$first_count = FALSE;	
						continue;
					}
					
					ee()->db->query(
						"DELETE
						 FROM 	exp_favorites_prefs 
						 WHERE 	pref_id = '" . ee()->db->escape_str($row_2['pref_id']) . "'"
					);
				}
			}
		}

		//remove the rogue column it if exists. Who put that there?
		if ($this->_column_exists( 'auto_add_favorites', 'exp_favorites_prefs' )) 
		{
			ee()->db->query("ALTER TABLE `exp_favorites_prefs` DROP `auto_add_favorites`");
		}

        // --------------------------------------------
        //  Version Number Update - LAST!
        // --------------------------------------------
    	
    	ee()->db->query(
    		ee()->db->update_string(
    			'exp_modules',
    			array(
    				'module_version'	=> FAVORITES_VERSION
				),
				array(
					'module_name'		=> $this->class_name
				)
			)
		);    									
    									
    	return TRUE;
    }
    // END update()


	// --------------------------------------------------------------------

	/**
	 * _column_exists
	 *
	 * @access	private
	 * @return	null
	 */
	private function alter_ee_tables()
	{
		$sql	= array();
		
		if ( $this->_column_exists( 'favorites_count', $this->sc->db->channel_titles ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	{$this->sc->db->channel_titles} 
					   ADD 			`favorites_count` int(10) unsigned NOT NULL default '0' 
					   AFTER 		`dst_enabled`";
		}

		if ( $this->_column_exists( 'favorites_count_public', $this->sc->db->channel_titles ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	{$this->sc->db->channel_titles} 
					   ADD 			`favorites_count_public` int(10) unsigned NOT NULL default '0' 
					   AFTER 		`dst_enabled`";
		}

		if ( $this->_column_exists( 'favorites_count', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	`exp_members` 
					   ADD 			`favorites_count` int(10) unsigned NOT NULL default '0' 
					   AFTER 		`total_forum_posts`";
		}

		if ( $this->_column_exists( 'favorites_count_public', 'exp_members' ) === FALSE )
		{
			$sql[]	= "ALTER TABLE 	`exp_members` 
			    	   ADD 			`favorites_count_public` int(10) unsigned NOT NULL default '0' 
					   AFTER 		`total_forum_posts`";
		}
		
		foreach ($sql as $query)
	    {
	        ee()->db->query($query);
	    }
	}
	// END alter_ee_tables()


	// --------------------------------------------------------------------

	/**
	 * _column_exists
	 *
	 * @access	public
	 * @param	string column name
	 * @param	string table name
	 * @return	null
	 */

	function _column_exists( $column, $table )
	{
		// ----------------------------------------
		// Check for columns in tags table
		// ----------------------------------------

		$query	= ee()->db->query( 
			"DESCRIBE `" . ee()->db->escape_str( $table )  . "` `" . 
						   ee()->db->escape_str( $column ) . "`" 
		);

		if ( $query->num_rows() > 0 )
		{
			return TRUE;
		}

		return FALSE;
	}
	// End _column_exists()
}