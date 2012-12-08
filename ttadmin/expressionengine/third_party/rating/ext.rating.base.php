<?php if ( ! defined('EXT')) exit('No direct script access allowed');
 
 /**
 * Solspace - Rating
 *
 * @package		Solspace:Rating
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2008-2012, Solspace, Inc.
 * @link		http://solspace.com/docs/addon/c/Rating/
 * @version		3.1.1
 * @filesource 	./system/expressionengine/third_party/rating/
 */
 
 /**
 * Rating - Extension
 *
 * @package 	Solspace:Rating
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/rating/ext.rating.php
 */
 
require_once 'addon_builder/extension_builder.php';

class Rating_extension_base extends Extension_builder_rating 
{
	public $settings		= array();
	public $name			= '';
	public $version		= '';
	public $description	= '';
	public $settings_exist	= 'n';
	public $docs_url		= '';
	
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */
    
	public function __construct($settings = array())
    {	
    	parent::Extension_builder_rating('rating');
    	
    	// --------------------------------------------
        //  Settings
        // --------------------------------------------
    	
    	$this->settings = $settings;
	}
	/* END constructor */
	
	
	// --------------------------------------------------------------------
	
	/**
	 * Delete
	 * 
	 * Delete the ratings associated with an entry
	 * 
	 * @param	int	$id
	 * @param	int	$weblog
	 * @return 
	 */
	
	function delete_entries_loop( $id, $weblog )
	{	
		$query	= ee()->db->query("SELECT rating_id, entry_id, rating_author_id, channel_id
								   FROM exp_ratings
								   WHERE entry_id IN (".implode(',', array_map('ceil', preg_split("/,|\|/", $id, -1, PREG_SPLIT_NO_EMPTY))).")");
		
		if ($query->num_rows() == 0)
		{
			return FALSE;
		}
		
		foreach($query->result_array() as $row)
		{
			$channels[] = $row['channel_id'];
			$entries[]	= $row['entry_id'];
			$members[]	= $row['rating_author_id'];
			$ids[]		= $row['rating_id'];
		}
		
		/** --------------------------------------------
        /**  Delete the Ratings..GONE...buh-bye!
        /** --------------------------------------------*/
		
		ee()->db->query("DELETE FROM exp_ratings WHERE entry_id IN (".implode(',', $entries).")");
		ee()->db->query("DELETE FROM exp_rating_stats WHERE entry_id IN (".implode(',', $entries).")");
		ee()->db->query("DELETE FROM exp_rating_reviews WHERE entry_id IN (".implode(',', $entries).")");
		ee()->db->query("DELETE FROM exp_rating_quarantine WHERE entry_id IN (".implode(',', $entries).")");
		
		/** --------------------------------------------
		/**  Update Member's Statistics 
		/** --------------------------------------------*/

		$this->actions()->update_member_stats($members);
		
		/** --------------------------------------------
		/**  Update Channel Statistics 
		/** --------------------------------------------*/
		
		$this->actions()->update_channel_stats($channels);

		// ----------------------------------------
		//	Update rating stats
		// ----------------------------------------

		$this->actions()->update_entry_stats($entries);

		return TRUE;
	}
	/* END delete() */
	
	// --------------------------------------------------------------------
	
	/**
	 * Modify SQL
	 * 
	 * This alters the $end variable for the SQL query that grabs weblog entries.
	 * 
	 * @param	str	$end
	 * @return	str
	 */
	
	function modify_order_by_sql( $end, $sql = '' )
	{
		/** -------------------------------------
		/**  Set return end
		/** -------------------------------------*/
		
		$end = $this->get_last_call($end);
		
		/** -------------------------------------
		/**  Should we even execute?
		/** -------------------------------------*/
		
		if ( ! ee()->TMPL->fetch_param('orderby_ratings') OR ee()->TMPL->fetch_param('orderby_ratings') == '' )
		{
			return $end;
		}
		
		/** -------------------------------------
		/**  Is the ratings module running?
		/** -------------------------------------*/
		
		if ($this->database_version() === FALSE) return $end;
		
		/*
		// Example $sql variable
		
		FROM exp_channel_titles AS t
		LEFT JOIN exp_channels ON t.channel_id = exp_channels.channel_id
		LEFT JOIN exp_members AS m ON m.member_id = t.author_id
		WHERE t.entry_id !=''
		AND t.site_id IN ('1')
		AND t.entry_date < 1302975459 
		AND (t.expiration_date = 0 OR t.expiration_date > 1302975459)
		AND t.channel_id = '1' AND t.status = 'open'
		*/
		
		/** -------------------------------------
		/**  Modify order by
		/** -------------------------------------*/
		
		if ($sql != '')
		{
			$sql = "SELECT exp_rating_stats.entry_id FROM exp_rating_stats, ".substr(trim($sql), 5).
					" AND t.entry_id = exp_rating_stats.entry_id AND exp_rating_stats.entry_id != 0
					ORDER BY exp_rating_stats.avg DESC";
		}
		else
		{
			$sql = "SELECT entry_id FROM exp_rating_stats WHERE entry_id != 0 ORDER BY avg DESC";
		}
		
		$query = ee()->db->query($sql);
		
		if ($query->num_rows() == 0)
		{
			return $end;
		}
		
		foreach($query->result_array() as $row)
		{
			$ids[] = $row['entry_id'];
		}
		
		return str_replace('ORDER BY', 'ORDER BY FIELD(t.entry_id, '.implode(',', $ids).'), ', $end);
	}
	/* END modify_sql() */
	
	// --------------------------------------------------------------------
	
	/**
	 * Parse
	 * 
	 * @param	str		$tagdata
	 * @param	array	$row
	 * @return 
	 */
	
	function weblog_entries_tagdata( $tagdata, $row )
	{
		$return	= ( ! empty(ee()->extensions->last_call)) ? ee()->extensions->last_call : $tagdata;
				
		/** -------------------------------------
		/**  Should we execute?
		/** -------------------------------------*/
		
		if ( ee()->TMPL->fetch_param('parse_rating_stats') === FALSE OR
			 (ee()->TMPL->fetch_param('parse_rating_stats') != 'yes' && ee()->TMPL->fetch_param('parse_rating_stats') != 'y') OR
			 $tagdata == '' OR count( $row ) == 0 )
		{
			return $return;
		}
		
		/** -------------------------------------
		/**  Fire up Rating module
		/** -------------------------------------*/
		
		require_once $this->addon_path.'mod.rating.php';
		
		$Rating = new Rating();
							
		return $Rating->parse_rating_stats( $tagdata, $row );
	}
	/* END weblog_entries_tagdata() */
	
		
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
	    	return ee()->output->show_user_error(
				'general', 
				str_replace('%url%', BASE.AMP.'C=modules', ee()->lang->line('disable_module_to_disable_extension'))
			);
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
}
// END Class Rating_extension