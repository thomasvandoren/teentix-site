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
 * Favorites - Data Models
 *
 * @package 	Solspace:Favorites
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/favorites/data.favorites.php
 */
 
require_once 'addon_builder/data.addon_builder.php';

class Favorites_data extends Addon_builder_data_favorites 
{
	public $cached = array();

	private $preference_defaults 	= array(
		'pref'	=> 'value'		
	);

    // --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */
	
	public function __construct()
	{
		parent::Addon_builder_data_favorites();
	}


    // --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */
		
	public function settings($setting = FALSE)
	{	
		//no cache?
		if ( ! isset($this->cached['settings']))
		{
			$query = ee()->db->query(
				"SELECT * 
				 FROM 	exp_favorites_prefs 
				 WHERE 	site_id = '" . ee()->db->escape_str(ee()->config->item('site_id')) . "'"
			);
			
			//no settings for this site yet?
			if ($query->num_rows() == 0)
			{
				$this->set_default_site_prefs(ee()->config->item('site_id'));
			}
			
			//once more
			$query = ee()->db->query(
				"SELECT * 
				 FROM 	exp_favorites_prefs 
				 WHERE 	site_id = '" . ee()->db->escape_str(ee()->config->item('site_id')) . "'"
			);
			
			$this->cached['settings'] = $query->row_array();
		}
		
		if ($setting !== FALSE)
		{
			return isset($this->cached['settings'][$setting]) ? $this->cached['settings'][$setting] : FALSE;
		}
		
		return $this->cached['settings'];
	}
	
    // --------------------------------------------------------------------

	/**
	 * set_default_site_prefs
	 *
	 * @access	public
     * @param	int		site id number to add defaults to
	 * @return	null
	 */

	public function set_default_site_prefs($site_id = 1)
	{
		$query = ee()->db->query(
			"SELECT * 
			 FROM 	exp_favorites_prefs 
			 WHERE 	site_id = '" . ee()->db->escape_str(ceil($site_id)) . "'"
		);
		
		//no settings for this site yet?
		if ($query->num_rows() == 0)
		{
			ee()->db->query(
				ee()->db->insert_string(
					'exp_favorites_prefs',
					array(
						'pref_id' 				=> '', 
						'site_id'               => ceil($site_id), 
						'language'              => 'english', 
						'member_id'             => '', 
						'no_string'             => 'We do not have a proper string.', 
						'no_login'              => 'You must be logged in before you can add or view favorites.', 
						'no_id'                 => 'An entry id must be provided.', 
						'id_not_found'          => 'No entry was found for that entry id.', 
						'no_duplicates'         => 'This favorite has already been recorded.', 
						'no_favorites'          => 'No favorites have been recorded.', 
						'no_delete'             => 'That favorite does not exist.', 
						'success_add'           => 'Your favorite has been successfully added.', 
						'success_delete'        => 'Your Favorite has been successfully deleted.', 
						'success_delete_all'	=> 'All of your Favorites have been successfully deleted',
						'add_favorite'			=> 'n'
					)
				)
			);
		}
	}
	
	// --------------------------------------------------------------------
}
// END CLASS Favorites_data