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
 * Favorites - Actions
 *
 * Handles All Form Submissions and Action Requests Used on both User and CP areas of EE
 *
 * @package 	Solspace:Favorites
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/favorites/act.favorites.php
 */


require_once 'addon_builder/extension_builder.php';

class Favorites_actions extends Addon_builder_favorites 
{
    
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */
    
	public function __construct()
    {	
    	parent::Addon_builder_favorites('favorites');
    	
    	// -------------------------------------
		//  Module Installed and What Version?
		// -------------------------------------
			
		if ($this->database_version() == FALSE OR 
			$this->version_compare($this->database_version(), '<', FAVORITES_VERSION))
		{
			return;
		}
	}
	// END
}
// END Favorites_actions Class