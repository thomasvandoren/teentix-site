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
 * Rating - Actions
 *
 * Handles All Form Submissions and Action Requests Used on both User and CP areas of EE
 *
 * @package 	Solspace:Rating
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/rating/act.rating.php
 */


require_once 'addon_builder/module_builder.php';

class Rating_actions extends Module_builder_rating 
{
	public $module_preferences = array();
    
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */
    
	function __construct()
    {	
    	parent::Addon_builder_rating('rating');
    	
    	// --------------------------------------------
        //	Default Preferences
        // --------------------------------------------
    	
    	$this->default_preferences = array(	'can_delete_ratings' 		=> array(),
    										'can_report_ratings'		=> array(),
    										'can_post_ratings'			=> array(),
    										'enabled_channels'			=> array('all'),
    										'quarantine_minimum'		=> 3,
    										'require_email'				=> 'n',
    										'use_captcha'				=> 'n');
    	
    	// -------------------------------------
		//  Module Installed and What Version?
		// -------------------------------------
			
		if ($this->database_version() == FALSE OR 
			$this->version_compare($this->database_version(), '<', RATING_VERSION)
			OR ! $this->extensions_enabled())
		{
			return;
		}
	}
	/* END constructor */
	
	// --------------------------------------------------------------------

	/**
	 *  Get the Preferences for This Module
	 *
	 * @access	public
	 * @return	array
	 */
	
	public function module_preferences($refresh = FALSE)
	{
		if (sizeof($this->module_preferences) > 0 && $refresh === FALSE)
		{
			return $this->module_preferences;
		}
		
		// --------------------------------------------
        //  Default Values Guaranteed - No money back, method is provided as is... :-)
        // --------------------------------------------
        
		$this->module_preferences = $this->default_preferences;
		
		// --------------------------------------------
        //  Values in Database
        // --------------------------------------------
		
		$query = ee()->db->query("SELECT * FROM exp_rating_preferences");
		
		foreach($query->result_array() as $row)
		{
			if ( is_array($this->default_preferences[$row['preference_name']]))
			{
				$this->module_preferences[$row['preference_name']] = explode('|', $row['preference_value']);
			}
			else
			{
				$this->module_preferences[$row['preference_name']] = $row['preference_value'];
			}
		}
		
		// Return!
		return $this->module_preferences;
	}
	// END module_preferences() 
	
	
	// --------------------------------------------------------------------

	/**
	 *	Update Member Stats
	 *
	 *	@access		public
	 *	@param		integer
	 *	@return		boolean
	 */
	
	public function update_member_stats($member_id = '')
	{
		if ( empty($member_id) ) return FALSE;
		
		if ( is_array($member_id))
		{
			foreach(array_unique($member_id) as $id)
			{
				$this->update_member_stats($id);
			}
			
			return;
		}
		
		ee()->db->query("DELETE FROM exp_rating_stats WHERE member_id = '".ee()->db->escape_str($member_id)."'");
		
		$sql = "SELECT COUNT(*) as `count`, MAX(`rating_date`) as rating_date
				FROM exp_ratings
				WHERE quarantine != 'y' AND status != 'closed'
				AND rating_author_id = '".ee()->db->escape_str($member_id)."'";
			
		$query = ee()->db->query($sql);
		
		$arr	= array('entry_id'			=> 0,
						'channel_id'		=> 0,
						'collection'		=> 'all',
						'member_id'			=> $member_id,
						'last_rating_date'	=> ($query->row('rating_date') == NULL) ? 0 : $query->row('rating_date'),
						'count'				=> ($query->num_rows() == 0) ? 0 : $query->row('count'));
							
		$sql	= ee()->db->insert_string( 'exp_rating_stats', $arr );
		
		$sql	.= " ON DUPLICATE KEY UPDATE last_rating_date = VALUES(last_rating_date), `count` = VALUES(`count`)";
			
		ee()->db->query( $sql );
	}
	/* END update_member_stats() */

	// --------------------------------------------------------------------

	/**
	 *	Update Channel Stats
	 *
	 *	@access		public
	 *	@param		integer
	 *	@return		boolean
	 */
	
	public function update_channel_stats($channel_id = '')
	{
		if ( empty($channel_id) ) return FALSE;
		
		if ( is_array($channel_id))
		{
			foreach(array_unique($channel_id) as $id)
			{
				$this->update_channel_stats($id);
			}
			
			return;
		}
		
		$sql = "SELECT COUNT(*) as `count`, MAX(`rating_date`) as rating_date
				FROM exp_ratings
				WHERE quarantine != 'y' AND status != 'closed'
				AND channel_id = '".ee()->db->escape_str($channel_id)."'";
				
		ee()->db->query("DELETE FROM exp_rating_stats WHERE channel_id = '".ee()->db->escape_str($channel_id)."' AND entry_id = 0");
			
		$query = ee()->db->query($sql);
		
		$arr	= array('entry_id'			=> 0,
						'channel_id'		=> $channel_id,
						'collection'		=> 'all',
						'member_id'			=> 0,
						'last_rating_date'	=> ($query->row('rating_date') == NULL) ? 0 : $query->row('rating_date'),
						'count'				=> ($query->num_rows() == 0) ? 0 : $query->row('count'));
	
		$sql	= ee()->db->insert_string( 'exp_rating_stats', $arr );
		
		$sql	.= " ON DUPLICATE KEY UPDATE last_rating_date = VALUES(last_rating_date), `count` = VALUES(`count`)";
			
		ee()->db->query( $sql );
	}
	/* END update_member_stats() */

	// --------------------------------------------------------------------

	/**
	 *	Update Entry Stats
	 *
	 *	---
	 *
	 *	@access		public
	 *	@param		integer
	 *	@return		boolean
	 */
	
	public function update_entry_stats($entry_id = '')
	{	
		// ----------------------------------------
		//  Should we execute?
		// ----------------------------------------

		if ( $entry_id == '' ) return;
		
		if ( is_array($entry_id))
		{
			foreach(array_unique($entry_id) as $id)
			{
				$this->update_entry_stats($id);
			}
			
			return;
		}

		// ----------------------------------------
		//  Does the entry still exist?
		// ----------------------------------------

		$query	= ee()->db->query( "SELECT COUNT(*) AS count 
									FROM {$this->sc->db->channel_titles}
									WHERE entry_id = '".ee()->db->escape_str($entry_id)."'" );

		ee()->db->query("DELETE FROM exp_rating_stats WHERE entry_id = '".ee()->db->escape_str($entry_id)."'");

		if ( $query->row('count') == 0 )
		{
			return;
		}
		
		// --------------------------------------------
        //	Fetch Numeric Fields and Collect Data
        // --------------------------------------------
        
        $stats = array();
		
		foreach ( $this->data->get_rating_fields_data() as $field_row )
		{
			if ($field_row['field_type'] != 'number') continue;
		
			extract($field_row); // $field_id and $field_name now set.			
			
			// --------------------------------------------
			//  Query to Get Stats
			// --------------------------------------------
			
			$sql = "SELECT entry_id, channel_id, collection,
						   COUNT(`{$field_name}`) as `count`, AVG(`{$field_name}`) as `avg`, 
						   SUM(`{$field_name}`) as `sum`, MAX(`rating_date`) as rating_date
					FROM exp_ratings
					WHERE quarantine != 'y' AND status != 'closed'
					AND `{$field_name}` IS NOT NULL AND entry_id = ".ee()->db->escape_str($entry_id)."
					GROUP BY entry_id, collection ";
			
			$result = ee()->db->query($sql);
			
			foreach ( $result->result_array() as $row )
			{
				$data[$row['collection']][$field_id] = array('channel_id'		=> $row['channel_id'],
															'rating_date'		=> $row['rating_date'],
															'count_'.$field_id	=> $row['count'],
															'avg_'.$field_id	=> $row['avg'],
															'sum_'.$field_id	=> $row['sum']);
			}
		}
		
		if (empty($data)) return;
		
		// --------------------------------------------
        //	Tally Data
        // --------------------------------------------
        
        //@todo - Perhaps review this and see if we can make it more efficient.
        //I am sure there is more going on in here than is absolutely necessary.
		

		$all			= array( 'collection' => 'all', 'entry_id' => $entry_id );
		$all_counter	= 0;
		$all_count		= 0;
		$all_avg		= 0;
		$all_sum		= 0;
		$all_nfields	= array();
	
		foreach ( $data as $form => $fields )
		{
			$form_counter	= 0;
			$form_count		= 0;
			$form_avg		= 0;
			$form_sum		= 0;
			$insert			= array();
			
			foreach ( $fields as $field => $stats )
			{
				if ( ! isset ( $all[$field] ) )
				{
					$all['channel_id']			= $stats['channel_id'];
					$all['last_rating_date']	= $stats['rating_date'];
				}
				elseif ( $all['rating_date'] < $stats['rating_date'] )
				{
					$all['last_rating_date'] = $stats['rating_date'];
				}
				
				if ( empty($insert) )
				{
					$insert['channel_id']		= $stats['channel_id'];
					$insert['last_rating_date']	= $stats['rating_date'];
					$insert['collection']		= $form;
					$insert['entry_id']			= $entry_id;
				}
				
				foreach ( $stats as $k => $v )
				{
					if ($k == 'channel_id' OR $k == 'rating_date') continue;
				
					$insert[$k] = $v;
					
					if (substr($k, 0, 3) == 'sum')
					{
						$all_sum += $v;
						$form_sum += $v;
						$all[$k] = ( isset( $all[$k] ) ) ? $all[$k] + $v : $v;
						$all_nfields[$field]['sum'] = (isset($all_nfields[$field]['sum'])) ? $all_nfields[$field]['sum'] + $v : $v;
					}
					elseif (substr($k, 0, 5) == 'count')
					{
						$all_counter += $v;
						$form_counter += $v;
						
						if ($form_count < $v)
						{
							$form_count = $v;
						}
						
						$all[$k] = ( isset( $all[$k] ) ) ? $all[$k] + $v : $v;
						$all_nfields[$field]['count'] = (isset($all_nfields[$field]['count'])) ? $all_nfields[$field]['count'] + $v : $v;
					}
					elseif (substr($k, 0, 3) == 'avg')
					{
						$all_nfields[$field]['avg'] = $k;
					}
				}
			}
			
			$insert['sum'] = $form_sum;
			$insert['avg'] = $form_sum / $form_counter;
			$insert['count'] = $form_count;
			$all_count += $form_count;
			
			if ($form == '') continue; // EMPTY collection="" parameter when submitted
			
			ee()->db->query( ee()->db->insert_string( 'exp_rating_stats', $insert) );
		}

		foreach ($all_nfields as $field => $data)
		{
			$all[$data['avg']] = $data['sum'] / $data['count'];
		}
		
		$all['sum'] = $all_sum;
		$all['avg'] = $all_sum / $all_counter;
		$all['count'] = $all_count;
		ee()->db->query( ee()->db->insert_string( 'exp_rating_stats', $all ) );


		
		return TRUE;
	}
	/* END update_stats() */
	
	
}
// END Rating_actions Class