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
 * Favorites - Extension
 *
 * @package 	Solspace:Favorites
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/favorites/ext.favorites.php
 */
 

require_once 'addon_builder/extension_builder.php';

class Favorites_extension_base extends Extension_builder_favorites 
{

	public $settings		= array();
	
	public $name			= '';
	public $version			= '';
	public $description		= '';
	public $settings_exist	= 'n';
	public $docs_url		= '';

	public $required_by		= array('module');
	
	
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */
    
	public function __construct($settings = array())
    {	
    	parent::Extension_builder_favorites('favorites');
    	
    	// --------------------------------------------
        //  Settings
        // --------------------------------------------
    	
    	$this->settings = $settings;
        
        // --------------------------------------------
        //  Set Required Extension Variables
        // --------------------------------------------
        
        if ( is_object(ee()->lang))
        {
        	ee()->lang->loadfile('favorites');
        
        	$this->name			= ee()->lang->line('favorites_module_name');
        	$this->description	= ee()->lang->line('favorites_module_description');
        }
        
        $this->docs_url		= FAVORITES_DOCS_URL;
        $this->version		= FAVORITES_VERSION;
	}
	// END Favorites_extension_base()
	
		
	// --------------------------------------------------------------------

	/**
	 * Activate Extension
	 *
	 * A required method that we actually ignore because this extension is installed by its module
	 * and no other place.  If they want the extension enabled, they have to install the module.
	 *
	 * @access	public
	 * @return	null
	 */
    
	public function activate_extension()
    {
		if (APP_VER < 2.0)
    	{
			return ee()->output->show_user_error(
				'general', 
				str_replace('%url%', BASE.AMP.'C=modules', ee()->lang->line('enable_module_to_enable_extension'))
			);
		}
	}
	// END activate_extension()
	
	
	// --------------------------------------------------------------------

	/**
	 * Disable Extension
	 *
	 * A required method that we actually ignore because this extension is installed by its module
	 * and no other place.  If they want the extension disabled, they have to uninstall the module.
	 *
	 * @access	public
	 * @return	null
	 */
    
	public function disable_extension()
    {
    	if (APP_VER < 2.0)
    	{
    		return ee()->output->show_user_error('general', str_replace('%url%', 
    															BASE.AMP.'C=modules',
    															ee()->lang->line('disable_module_to_disable_extension')));
		}					
	}
	// END disable_extension()
	
	
	// --------------------------------------------------------------------

	/**
	 * Update Extension
	 *
	 * A required method that we actually ignore because this extension is updated by its module
	 * and no other place.  We cannot redirect to the module upgrade script because we require a 
	 * confirmation dialog, whereas extensions were designed to update automatically as they will try
	 * to call the update script on both the User and CP side.
	 *
	 * @access	public
	 * @return	null
	 */
    
	public function update_extension()
    {
    
	}
	// END update_extension()
	
	
	// --------------------------------------------------------------------

	/**
	 * Error Page
	 *
	 * @access	public
	 * @param	string	$error	Error message to display
	 * @return	null
	 */
	
	public function error_page($error = '')
	{	
		$this->cached_vars['error_message'] = $error;
		
		$this->cached_vars['page_title'] = ee()->lang->line('error');
		
		// -------------------------------------
		//  Output
		// -------------------------------------
		
		$this->ee_cp_view('error_page.html');
	}
	// END error_page()
	
	//---------------------------------------------------------------------------------------------------
	

	// --------------------------------------------------------------------

	/**
	 * This alters the $end variable for
	 * the SQL query that grabs weblog
	 * entries.
	 *
	 * @access	public
	 * @param	string	$end sql to modify to re-arrange output
	 * @return	string  modified sql
	 */
	    
	public function modify_sql ( $end )
	{
		//	----------------------------------
		//	Set return end
		//	----------------------------------
		
		if ( isset( ee()->extensions->last_call ) AND 
			 ! in_array(ee()->extensions->last_call, array(FALSE, '' )) )
		{				
			$r_end	= ee()->extensions->last_call;
		}
		else
		{
			$r_end	= $end;
		}
		
		//	----------------------------------
		//	Should we even execute?
		//	----------------------------------
		
		if ( in_array(ee()->TMPL->fetch_param('orderby_favorites'), array(FALSE, '' )) )
		{
			return $r_end;
		}
		
		//	----------------------------------
		//	Is the favorites module running?
		//	----------------------------------
		
		$query	= ee()->db->query( 
			"SELECT 	COUNT(*) AS count 
			 FROM 		exp_modules 
			 WHERE 		module_name = 'Favorites'" 
		);
		
		if ( $query->row('count') == 0 )
		{
			return $r_end;
		}
		
		//	----------------------------------
		//	Modify order by
		//	----------------------------------
		
		if ( preg_match( "/(ORDER BY t.sticky desc,)/s", $r_end, $match ) )
		{			
			$end_a	= "ORDER BY t.sticky desc, t.favorites_count_public desc,";
			
			$r_end	= str_replace( $match['1'], $end_a, $r_end );
			
			return $r_end;
		}
		
		return $r_end;
	}
	// End modify_sql()
	  	

	// --------------------------------------------------------------------

	/**
	 * This records a favorite whenever
     * an entry is submitted.
	 *
	 * @access	public
	 * @param	string	entry_id from extension call	
	 * @param	string	(ee1) data, (ee2) meta info
	 * @param	string  (ee1) null, (ee2) data
	 * @return	bool  	success
	 */
	
	public function add_favorite ( $entry_id, $data, $ee2_data = FALSE )
	{
		//trying a different hook with ee2_data
		if($ee2_data)
		{
			$data = $ee2_data;
		}
        
		// -------------------------------------------
		//  Fail out if not logged in or not enabled
		// -------------------------------------------
		
		if ( ee()->session->userdata['member_id'] == 0 OR 
			! $this->check_yes( $this->data->settings('add_favorite') ) )
		{
			return FALSE;
		}
        
		// -------------------------------------------
		//  Fail out if favorite has already
		//	been recorded for member.
		// -------------------------------------------
			
		$query		= ee()->db->query(
			"SELECT COUNT(*) AS count 
			 FROM 	exp_favorites 
			 WHERE	member_id = '" . ee()->db->escape_str(ee()->session->userdata['member_id']) . "' 
			 AND 	entry_id = '" . ee()->db->escape_str($entry_id) . "'"
		);
			
		if ( $query->row('count') >= 1 ) 
		{
			return FALSE;
		}       
                
		// -------------------------------------------
		//  Insert
		// -------------------------------------------
		
		ee()->db->query( 
			ee()->db->insert_string(
				'exp_favorites', 
				array(
					'author_id'		=> ee()->session->userdata['member_id'],
					'entry_id'      => $entry_id,
					'member_id'     => ee()->session->userdata['member_id'],
					'site_id'		=> ee()->config->item('site_id'),
					'entry_date'	=> ee()->localize->now,
					'notes'         => '',
					'public'        => 'y',
					'type'			=> 'entry_id'
				)
			) 
		);
		
		ee()->db->query( 
			"UPDATE {$this->sc->db->channel_titles} 
			 SET 	`favorites_count` 			= (favorites_count + 1), 
			     	`favorites_count_public` 	= (favorites_count_public + 1) 
			 WHERE 	entry_id 					= '" . ee()->db->escape_str($entry_id) . "'" 
		);
		
		// -------------------------------------------
		//  Return success
		// -------------------------------------------
		
		return TRUE;
	}
	//	End add_favorite()

	
	// --------------------------------------------------------------------

	/**
	 * This prunes the favorites table of 
	 * members that no longer exist
	 *
	 * @access	public
	 * @return	null
	 */
	    
	public function delete_members ()
	{
		$deleted_members	= array();
		$modified_entries	= array();
		
		// --------------------------------------------
        //  Retrieve No Longer Existing Authors and Members
        // --------------------------------------------
		
		$query = ee()->db->query(
			"SELECT		author_id, entry_id 
			 FROM 		exp_favorites 
			 WHERE 		author_id NOT 
			 IN 		( SELECT member_id FROM exp_members )"
		);
		
		foreach($query->result_array() as $row)
		{
			$deleted_members[]	= $row['author_id'];
			$modified_entries[]	= $row['entry_id'];
		}
					
		$query = ee()->db->query(
			"SELECT 	member_id, entry_id 
			 FROM 		exp_favorites 
			 WHERE 		member_id NOT 
			 IN 		( SELECT member_id FROM exp_members )"
		);
		
		foreach($query->result_array() as $row)
		{
			$deleted_members[]	= $row['member_id'];
			$modified_entries[]	= $row['entry_id'];
		}
		
		if (sizeof($deleted_members) == 0) {return;}
		
		$deleted_members	= array_unique($deleted_members);
		$modified_entries	= array_unique($modified_entries);
		
		// --------------------------------------------
        //  Remove Favorites
        // --------------------------------------------
        
        $query	= ee()->db->query( 
			"DELETE 
			 FROM 	exp_favorites
			 WHERE 	author_id 
			 IN 	('" . implode("','", ee()->db->escape_str( $deleted_members )) . "')
			 OR 	member_id 
			 IN 	('" . implode("','", ee()->db->escape_str( $deleted_members )) . "')" 
		);
							   
		// --------------------------------------------
        //  Update Stats
        // --------------------------------------------
        
        foreach($modified_entries as $id)
        {
			$count = ee()->db->query( 
				"SELECT COUNT(*) AS count 
				 FROM 	exp_favorites
				 WHERE 	entry_id = '" . ee()->db->escape_str( $id ) . "'" 
			);
								  
			$public_count = ee()->db->query(	 
				"SELECT COUNT(*) AS count 
				 FROM 	exp_favorites
				 WHERE 	public = 'y'
				 AND 	entry_id = '" . ee()->db->escape_str( $id ) . "'" 
			);
	
			ee()->db->query( 
				ee()->db->update_string( 
					$this->sc->db->channel_titles, 
					array( 'favorites_count' 	=> $count->row('count')), 
					array( 'entry_id' 			=> $id ) 
				) 
			);
											
			ee()->db->query( 
				ee()->db->update_string( 
					$this->sc->db->channel_titles, 
					array( 'favorites_count_public' => $public_count->row('count')), 
					array( 'entry_id' 				=> $id ) 
				) 
			);
		}
	}
	//	End delete_members()
	
	
	// --------------------------------------------------------------------

	/**
	 * This prunes the favorites table of
     * an entry that no longer exists
	 *
	 * @access	public
	 * @param	string 	entry_id
	 * @param	string 	weblog_id
	 * @return	null
	 */
	   
	public function delete_entry ( $entry_id, $weblog_id )
	{
		// --------------------------------------------
        //  Find Affected Members
        // --------------------------------------------
        
        $members = array();
		
		$query = ee()->db->query(
			"SELECT member_id 
			 FROM 	exp_favorites
			 WHERE 	entry_id = '" . ee()->db->escape_str( $entry_id ) . "'" 
		);
							 
		if ($query->num_rows() == 0) {return;}
		
		foreach($query->result_array() as $row)
		{
			$members[] = $row['member_id'];
		}
		
		$members = array_unique($members);
		
		// --------------------------------------------
        //  Now Delete
        // --------------------------------------------
        
        $query = ee()->db->query(
			"DELETE 
			 FROM 	exp_favorites
			 WHERE 	entry_id = '" . ee()->db->escape_str( $entry_id ) . "'" 
		);
							 
		// --------------------------------------------
        //  Recount Stats
        // --------------------------------------------
        
        foreach($members as $id)
        {
        	$count = ee()->db->query( 
				"SELECT COUNT(*) AS count 
				 FROM 	exp_favorites
				 WHERE 	member_id = '" . ee()->db->escape_str( $id ) . "'" 
			);
								  
			$public_count = ee()->db->query(	 
				"SELECT COUNT(*) AS count 
				 FROM 	exp_favorites
				 WHERE 	public 		= 'y'
				 AND 	member_id 	= '" . ee()->db->escape_str( $id ) . "'" 
			);
	
			ee()->db->query( 
				ee()->db->update_string( 
					'exp_members', 
					array( 'favorites_count' 	=> $count->row('count')), 
					array( 'member_id' 			=> $id ) 
				) 
			);
											
			ee()->db->query( 
				ee()->db->update_string( 
					'exp_members', 
					array( 'favorites_count_public' => $public_count->row('count')), 
					array( 'member_id' 				=> $id ) 
				) 
			);
        }
	}
	//	End delete_entry


	// --------------------------------------------------------------------

	/**
	 * Parse Favorites Date in Weblog Entry
	 *
	 * @access	public
	 * @param	string 	tagdata
	 * @param	string 	row to parse
	 * @param	string 	object data
	 * @return	null
	 */
    
	function parse_favorites_date ( $tagdata, $row, $obj )
	{
		if (ee()->extensions->last_call !== FALSE)
		{				
			$tagdata = ee()->extensions->last_call;
		}
		
		//no date? GTFO
		if ( ! isset($obj->favorites_date) OR 
		     $obj->favorites_date !== TRUE)
		{
			return $tagdata;
		}
		
		//code was ugly
		//This makes: 	$cache_fav_date[$marker] 
		//the same as: 	ee()->session->cache['favorites']['favorites_date'][ee()->TMPL->marker]
		$cache_fav_date =& ee()->session->cache['favorites']['favorites_date'];
		$marker			=& ee()->TMPL->marker;
		
		if ( ! isset( $cache_fav_date[$marker] ) )
		{
			//cache all dates matched in the template
			if (preg_match_all("/" . LD . "favorites_date\s+format=[\"'](.*?)[\"']" . RD . "/s", $tagdata, $matches))
			{
				for ($i = 0, $l = count($matches[0]); $i < $l; $i++)
				{
					$matches[0][$i] = str_replace(array(LD,RD), '', $matches['0'][$i]);
					
					$cache_fav_date[$marker][$matches[0][$i]] = ee()->localize->fetch_date_params($matches[1][$i]);
				}
			}
			//no favorites data tag? gtfo
			else
			{
				return $tagdata;
			}
		}
		
		//replace each data var out of the template with the correctly formated date
		foreach($cache_fav_date[$marker] as $key => $format)
		{
			if ( ! isset(ee()->TMPL->var_single[$key])) continue;
			
			$val = ee()->TMPL->var_single[$key];
			
			if ( ! isset($row['favorites_date']))
			{
				$tagdata = ee()->TMPL->swap_var_single($key, '', $tagdata); 
				//skip 
				continue;
			}
			
			foreach ($format as $dvar)
			{
				$val = str_replace(
					$dvar, 
					ee()->localize->convert_timestamp(
						$dvar, 
						$row['favorites_date'], 
						TRUE
					), 
					$val
				);
			}
			
			$tagdata = ee()->TMPL->swap_var_single($key, $val, $tagdata);
		}
		
		return $tagdata;
	}
	//	End favorites_date()	
}
// END Class Favorites_extension