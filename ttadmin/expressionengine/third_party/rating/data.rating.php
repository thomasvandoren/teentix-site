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
 * Rating - Data Models
 *
 * @package 	Solspace:Rating
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/rating/data.rating.php
 */

require_once 'addon_builder/data.addon_builder.php';

class Rating_data extends Addon_builder_data_rating 
{
	public $cached = array();

    // --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */
	
	public function __construct()
	{
		parent::Addon_builder_data_rating();
	}
	/* END constructor */
	
	// --------------------------------------------------------------------
	
	/**
	 * List of Rating's Fields
	 *
	 * @access	public
	 * @param	params	MySQL clauses, if necessary
	 * @return	array
	 */
    
	function get_rating_fields_list( $params = array())
    {
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder(func_get_args());
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = array();
 		
 		/** --------------------------------------------
        /**  Perform the Actual Work
        /** --------------------------------------------*/
        
		$query = ee()->db->query( "SELECT field_name, field_label FROM exp_rating_fields" );
        
        foreach($query->result_array() as $row)
        {
        	$this->cached[$cache_name][$cache_hash][$row['field_name']] = $row['field_label'];
        }
        
 		/** --------------------------------------------
        /**  Return Data
        /** --------------------------------------------*/
 		
 		return $this->cached[$cache_name][$cache_hash];	
    }
    /* END get_rating_fields() */
    
    
	// --------------------------------------------------------------------
	
	/**
	 * Full Data for all of Rating's Fields
	 *
	 * @access	public
	 * @param	params	MySQL clauses, if necessary
	 * @return	array
	 */
    
	function get_rating_fields_data( $params = array())
    {
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder(func_get_args());
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = array();
 		
 		/** --------------------------------------------
        /**  Perform the Actual Work
        /** --------------------------------------------*/
        
		$query = ee()->db->query( "SELECT * FROM exp_rating_fields ORDER BY field_order ASC" );
        
        foreach($query->result_array() as $row)
        {
        	$this->cached[$cache_name][$cache_hash][$row['field_name']] = $row;
        }
        
 		/** --------------------------------------------
        /**  Return Data
        /** --------------------------------------------*/
 		
 		return $this->cached[$cache_name][$cache_hash];	
    }
    /* END get_rating_fields_data() */

	// --------------------------------------------------------------------
}
// END CLASS Rating_data