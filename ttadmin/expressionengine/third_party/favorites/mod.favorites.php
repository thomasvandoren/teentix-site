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
 * Favorites - User Side
 *
 * @package 	Solspace:Favorites
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/favorites/mod.favorites.php
 */
 
if ( ! defined('APP_VER')) define('APP_VER', '2.0'); // EE 2.0's Wizard doesn't like CONSTANTs

require_once 'addon_builder/module_builder.php';

class Favorites extends Module_builder_favorites 
{

	public $return_data				= '';
	
	public $disabled				= FALSE;

	public $TYPE;

	public $entry_id				= '';
	public $member_id				= '';
	public $reserved_cat_segment	= '';
	public $cat_request				= '';

	// Pagination variables
    public $paginate				= FALSE;
    public $pagination_links		= '';
    public $page_next				= '';
    public $page_previous			= '';
	public $current_page			= 1;
	public $total_pages				= 1;
	public $total_rows				= 0;
	public $p_limit					= 20;
	public $p_page					= 0;
	public $basepath				= '';
	public $uristr					= '';
    
	public $messages				= array();
	public $mfields					= array();
	
	public $clean_site_id			= 0;

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
        
		ee()->load->helper(array('text', 'form', 'url', 'security', 'string'));

        // -------------------------------------
		//  Module Installed and Up to Date?
		// -------------------------------------
		
		if ($this->database_version() == FALSE OR 
			$this->version_compare($this->database_version(), '<', FAVORITES_VERSION))
		{
			$this->disabled = TRUE;
			
			trigger_error(ee()->lang->line('favorites_module_disabled'), E_USER_NOTICE);
		}
		
		//saves a few function calls
		$this->clean_site_id = ee()->db->escape_str(ee()->config->item('site_id'));
	}
	// END Favorites()


    // --------------------------------------------------------------------

	/**
	 * set messages to local var and set db for prefs if empty
	 *
	 * @access	public
	 * @return	null
	 */

	public function set_messages()
	{
		//return of no messages to set
		if (count( $this->messages ) > 0)
		{
			return;
		}

		$query = ee()->db->query(
			"SELECT * 
			 FROM 	exp_favorites_prefs 
			 WHERE 	site_id = '{$this->clean_site_id}'"
		);

		// If they do not exist, let us simply put the defaults in there
		// instead of throwing an error.

		if ($query->num_rows() == 0)
		{
			//set defaults
			$this->data->set_default_site_prefs(ee()->config->item('site_id'));

			$query = ee()->db->query(
				"SELECT * 
				 FROM 	exp_favorites_prefs 
				 WHERE 	site_id = '{$this->clean_site_id}'"
			);
		}

		foreach ($query->row() as $key => $value)
		{
			if (isset($value))
			{
				$this->messages[$key] = $value;
			}
		}
	}
	//	End get_messages()


    // --------------------------------------------------------------------

	/**
	 * Entry count
	 * This fetches a count of favorites for an entry.
	 *
	 * @access	public
	 * @return	null
	 */
	
	public function entry_count( $type = 'entry_id' )
	{
		$tagdata	= ee()->TMPL->tagdata;

		if ( $this->_entry_id( $type ) === FALSE )
		{
			return $this->no_results();
		}

		if ( $type == 'member_id' )
		{
			$sql	= "SELECT 	favorites_count, favorites_count_public
					   FROM 	exp_members
					   WHERE 	site_id 
					   IN 		( '" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
					   AND 		member_id = '" . ee()->db->escape_str( $this->member_id ) . "'";
		}
		else
		{
			$sql	= "SELECT 	favorites_count, favorites_count_public
					   FROM 	{$this->sc->db->channel_titles}
					   WHERE 	site_id 
					   IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
					   AND 		entry_id = '" . ee()->db->escape_str( $this->entry_id ) . "'";
		}

		$query		= ee()->db->query( $sql );

		$result		= $query->result_array();

		//need an array result for looping key/value
		$result_row = $result[0];
		$cond		= $result_row;

		$tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );

		foreach ( $result_row as $key => $value )
		{
			$tagdata	= str_replace( LD . $key . RD, $value, $tagdata );
		}

		return $tagdata;
	}
	// END entry_count()	


    // --------------------------------------------------------------------

	/**
	 * Member count
	 * This fetches the number of times that a
	 * member has been favorited.
 	 *
	 * @access	public
	 * @return	string tagdata
	 */
	
	public function member_count()
	{
		return $this->entry_count( 'member_id' );
	}
	// END member_count()


    // --------------------------------------------------------------------

	/**
	 * Favorites count (deprecated)
	 * This is an alias of entry count
 	 *
	 * @access	public
	 * @return	string tagdata
	 */
	
	public function favorites_count()
	{
		return $this->entry_count();
	}
	// END favorites_count()
	
	
    // --------------------------------------------------------------------

	/**
	 * Author popularity
	 * This fetches a ranked count of authors by
	 * the number of favorites attributed to
	 * articles.
 	 *
	 * @access	public
	 * @return	string tagdata
	 */
	
	public function author_popularity()
	{
		$r			= '';

		// -------------------------------------------
		//  Prep SQL
		// -------------------------------------------

		$sql		= "SELECT 		m.*, md.*, COUNT(f.favorites_id) AS count, 
									f.entry_date AS favorites_date
					   FROM 		exp_favorites 	AS f
					   LEFT JOIN 	exp_members 	AS m 
					   ON 			m.member_id 	= f.author_id
					   LEFT JOIN 	exp_member_data AS md 
					   ON 			f.author_id 	= md.member_id
					   WHERE 		f.site_id IN ('" . implode("','", ee()->TMPL->site_ids) . "')
					   AND 			m.member_id != 0 ";

		// ----------------------------------------------------
        //  Limit query by date range given in tag parameters
        // ----------------------------------------------------

        if (ee()->TMPL->fetch_param('favorites_start_on'))
        {
            $sql .= "AND f.entry_date >= '" . 
					ee()->localize->convert_human_date_to_gmt(
						ee()->TMPL->fetch_param('favorites_start_on')
					) . 
					"' ";
		}

        if (ee()->TMPL->fetch_param('favorites_stop_before'))
        {
        	$sql .= "AND f.entry_date < '" . 
					ee()->localize->convert_human_date_to_gmt(
						ee()->TMPL->fetch_param('favorites_stop_before')
					) .
					"' ";
        }

		// -------------------------------------------
		//  Members
		// -------------------------------------------

		if ( $this->_numeric( ee()->TMPL->fetch_param('member_id') ) === TRUE )
		{
			$sql	.= ee()->functions->sql_andor_string( 
							ee()->TMPL->fetch_param('member_id'), 
							'm.member_id' 
					   );
		}
		elseif ( ee()->TMPL->fetch_param('username') )
		{
			$sql	.= ee()->functions->sql_andor_string( 
							ee()->TMPL->fetch_param('username'), 
							'm.username' 
					   );
		}

		// -------------------------------------------
		//  Order By, My Little Monkeys!  FLY!!! FLY!!!
		// -------------------------------------------

		$sql	.= " GROUP BY f.author_id";

		switch (ee()->TMPL->fetch_param('orderby'))
		{
			case 'favorites_date'		: $sql .= " ORDER BY favorites_date";
				break;
			default						: $sql .= " ORDER BY count";
				break;
		}

		// -------------------------------------------
		//  Sort...you know, if you wanna...
		// -------------------------------------------

		switch (ee()->TMPL->fetch_param('sort'))
		{
			case 'asc'	: $sql .= " ASC";
				break;
			case 'desc'	: $sql .= " DESC";
				break;
			default		: $sql .= " DESC";
				break;
		}

		// -------------------------------------------
		//  Limit
		// -------------------------------------------

		if ( $this->_numeric( ee()->TMPL->fetch_param('limit') ) === TRUE )
		{
			$sql	.= " LIMIT " . ee()->TMPL->fetch_param('limit');
		}
		else
		{
			$sql	.= " LIMIT 10";
		}

		// -------------------------------------------
		//  Execute
		// -------------------------------------------

		$query		= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 ) 
		{
			return $this->no_results();
		}

		// -------------------------------------------
		//  Custom member fields
		// -------------------------------------------

		$this->_fetch_custom_member_fields();

		// -------------------------------------------
		//  Instantiate typography
		// -------------------------------------------
		
        if (APP_VER < 2.0)
        {
	        if ( ! class_exists('Typography'))
	        {
	            require PATH_CORE.'core.typography'.EXT;
	        }

	        $this->TYPE	= new Typography;

	        if ( isset( $this->TYPE->convert_curly ) )
	        {
	        	$this->TYPE->convert_curly	= FALSE;
	        }
        }
        else
        {
			ee()->load->library('typography');
			ee()->typography->initialize();
			ee()->typography->convert_curly = FALSE;
			
			$this->TYPE =& ee()->typography;
		}

        // --------------------------------------
		//  Indy!  Bad Dates! - Stolen from Query module.  
		//  I feel terrible...so bad...so bad...
		// --------------------------------------

		$dates = array();

		if (preg_match_all(
				"/" . LD . "([a-z\_]*?)\s+format=[\"'](.*?)[\"']" . RD . "/is", 
				ee()->TMPL->tagdata, 
				$matches
			))
		{
			for ($j = 0, $l = count($matches['0']); $j < $l; $j++)
			{
				$matches['0'][$j] = str_replace(array(LD, RD), '', $matches['0'][$j]);

				if ( $query->row($matches['1'][$j]) AND 
					 is_numeric( $query->row($matches['1'][$j])) ) 
				{
					$dates[$matches['0'][$j]] = array(
						$matches['1'][$j], 
						ee()->localize->fetch_date_params( $matches['2'][$j] )
					);
				}
			}
		}

		// -------------------------------------------
		//  Parse
		// -------------------------------------------

		$i	= 0;

		foreach ( $query->result_array() as $row )
		{
			$i++;

			$row['rank']	= $i;

			$tagdata		= ee()->TMPL->tagdata;

			$cond			= $row;

			foreach( $this->mfields as $key => $value )
			{
				if ( isset($row['m_field_id_'.$value['0']]) )
				{
					$cond[$key] = $this->TYPE->parse_type(
						$row['m_field_id_' . $value['0']],
						array(
							'text_format'   => $value['1'],
							'html_format'   => 'safe',
							'auto_links'    => 'y',
							'allow_img_url' => 'n'
						)
					);
				}
			}

			$tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );

			foreach ( ee()->TMPL->var_single as $key => $value )
			{
				// -------------------------------------------
				//  Custom member fields
				// -------------------------------------------

				if ( isset( $fields[$key] ) )
				{
					$tagdata = ee()->TMPL->swap_var_single( 
						$key, 
						$row[$fields[$key]], 
						$tagdata 
					);
				}

				// -------------------------------------------
                //  Parse custom member fields
				// -------------------------------------------

                if ( isset( $this->mfields[$value]) AND 
					 isset($row['m_field_id_' . $this->mfields[$value]['0']]))
                {
                    $tagdata = ee()->TMPL->swap_var_single(
						$value,
						$this->TYPE->parse_type(
							$row['m_field_id_' . $this->mfields[$value]['0']],
							array(
								'text_format'   => $this->mfields[$value]['1'],
								'html_format'   => 'safe',
								'auto_links'    => 'y',
								'allow_img_url' => 'n'
							)
						),
						$tagdata
					);
                }

				// -------------------------------------------
				//  Favorites Date (i.e. Date Added as Favorite)
				// -------------------------------------------

				if (isset($dates[$key]))
            	{
            		foreach ($dates[$key]['1'] as $dvar)
					{
						$value = str_replace(
							$dvar, 
							ee()->localize->convert_timestamp(
								$dvar, 
								$row[$dates[$key]['0']], 
								TRUE
							), 
							$value
						);
					}

					$tagdata = ee()->TMPL->swap_var_single($key, $value, $tagdata);
            	}

				// -------------------------------------------
				//  Remaining fields
				// -------------------------------------------

				if ( isset( $row[$key] ) )
				{
					$tagdata = ee()->TMPL->swap_var_single( $key, $row[$key], $tagdata );
				}
			}

			$r	.= $tagdata;
		}

		return $r;
	}
	// END author_popularity()	
	
		
    // --------------------------------------------------------------------

	/**
	 * Author rank (deprecated)
 	 *
	 * @access	public
	 * @return	string tagdata
	 */

	public function author_rank() 
	{ 
		return $this->author_popularity(); 
	}
	//	END author_rank()


    // --------------------------------------------------------------------

	/**
	 * Author count (deprecated)
 	 *
	 * @access	public
	 * @return	string tagdata
	 */
	
	public function author_count() 
	{ 
		return $this->author_popularity(); 
	}
	// END author_count()


    // --------------------------------------------------------------------

	/**
	 * Save a favorite member
 	 *
	 * @access	public
	 * @return	string tagdata
	 */
	
	public function save_member() 
	{ 
		return $this->save( 'member_id' ); 
	}
	// END save_member()	

	
    // --------------------------------------------------------------------

	/**
	 * Save a Favorite
 	 *
	 * @access	public
	 * @param	string type to save
	 * @param	string delete 
	 * @return	string message
	 */

	public function save( $type = 'entry_id', $delete = '' )
	{
		$return		= '';
        $uri		= ee()->uri->uri_string;
		$user_id	= ee()->session->userdata['member_id'];

		// -------------------------------------------
		//  Set messages
		// -------------------------------------------

        $this->set_messages();

		// -------------------------------------------
		//  URI?  Fail out gracefully.
		// -------------------------------------------

        if ( $uri == '' )
        {
			return $this->messages['no_string'];
        }

		// -------------------------------------------
		//  Not logged in?  Fail out gracefully.
		// -------------------------------------------

        if ( $user_id == '0' )
        {
            return $this->messages['no_login'];
        }

        // -------------------------------------------
        //	Are we deleting all Favorites for Member?
		// -------------------------------------------

        if ( stristr( $uri, "/delete_all" ))
        {
        	return $this->delete_all();
        }

		// -------------------------------------------
		//  Update last activity
		// -------------------------------------------

		$this->_update_last_activity();

		// -------------------------------------------
        //	Do we have a valid ID number?
		// -------------------------------------------

		if ( ! $this->_entry_id( $type ) )
		{
            return $this->messages['no_id'];
		}
		else
		{
			if ( $type == 'member_id' )
			{
				$sql	= "SELECT 	member_id AS author_id 
						   FROM 	exp_members
						   WHERE 	site_id 
						   IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
						   AND 		member_id = '" . ee()->db->escape_str( $this->member_id ) . "' 
						   LIMIT 	1";
			}
			else
			{
				$sql	= "SELECT 	author_id, entry_id, site_id AS entry_site_id 
						   FROM 	{$this->sc->db->channel_titles}
						   WHERE 	site_id 
						   IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
						   AND 		entry_id = '" . ee()->db->escape_str( $this->entry_id ) . "' 
						   LIMIT 	1";
			}

			$query		= ee()->db->query( $sql );

			if ( $query->num_rows() == 0 ) 
			{
				return $this->messages['id_not_found'];
			}
		}

		$result = $query->result_array();

		// make every item a local var
		extract($result[0]);

		// -------------------------------------------
        //	Are we deleting?
		// -------------------------------------------

        if ( stristr( $uri, "/delete" ) OR $delete == 'delete' )
        {
			// ----------------------------------------
			// 'delete_favorite_start' hook.
			//  - Change or add additional processing before saving an favorite
			//	- Added Favorites 3.0.5
			// ----------------------------------------

			if (ee()->extensions->active_hook('delete_favorite_start') === TRUE)
			{
				ee()->extensions->universal_call(
					'delete_favorite_start',
					$type, 
					$author_id, 
					$user_id,
					$entry_id, 
					$entry_site_id 
				);
				
				if (ee()->extensions->end_script === TRUE) return;
			}

        	if ( $type == 'member_id' )
        	{
				// -------------------------------------------
				//  First, fail out if favorite does not exist
				//	or does not belong to member.
				// -------------------------------------------

				$query		= $this->cacheless_query( 
					"SELECT favorites_id, public 
					 FROM 	exp_favorites
					 WHERE 	site_id 
					 IN 	('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
					 AND 	type = 'member_id'
					 AND 	author_id = '" . ee()->db->escape_str( $author_id ) . "'
					 AND 	member_id = '" . ee()->db->escape_str( $user_id ) . "'" 
				);

				if ( $query->num_rows() == 0 ) 
				{
					return $this->messages['no_delete'];
				}

				ee()->db->query(
					"DELETE FROM 	exp_favorites
					 WHERE 			site_id 
					 IN 			('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
					 AND 			favorites_id = '" . ee()->db->escape_str( $query->row('favorites_id') ) . "'
					 LIMIT 			1"
				);

				// -------------------------------------------
				//  Update The Counts in exp_members
				// -------------------------------------------
				
				$this->update_stats($author_id, 'member_id');
			}
        	else
        	{
				// -------------------------------------------
				//  Does Our Favorite Exist for the Member?
				// -------------------------------------------

				$query		= $this->cacheless_query( 
					"SELECT favorites_id, public
					 FROM 	exp_favorites
					 WHERE 	type = 'entry_id'
				     AND 	entry_id = '" . ee()->db->escape_str( $entry_id ) . "'
					 AND 	member_id = '" . ee()->db->escape_str( $user_id ) . "'" 
				);

				if ( $query->num_rows() == 0 ) 
				{
					return $this->messages['no_delete'];
				}

				ee()->db->query( 
					"DELETE FROM 	exp_favorites 
					 WHERE 			favorites_id = '" . ee()->db->escape_str( $query->row('favorites_id') ) . "' 
					 LIMIT 			1" 
				);

				// --------------------------------------------
				//  Update Our Counts in exp_weblog_titles
				// --------------------------------------------
				
				$this->update_stats($entry_id, 'entry_id');
			}

			// ----------------------------------------
			// 'delete_favorite_end' hook.
			//  - Change or add additional processing before saving an favorite
			//	- Added Favorites 3.0.5
			// ----------------------------------------

			if (ee()->extensions->active_hook('delete_favorite_end') === TRUE)
			{
				ee()->extensions->universal_call(
					'delete_favorite_end',
					$type, 
					$author_id, 
					$user_id,
					$entry_id, 
					$entry_site_id 
				);
				
				if (ee()->extensions->end_script === TRUE) return;
			}

			return $this->messages['success_delete'];
		}

		// -------------------------------------------
		//  Favorite Exists?  Bail out.
		// -------------------------------------------

		if ( $type == 'member_id' )
		{
			$sql	= "SELECT 	COUNT(*) AS count
					   FROM 	exp_favorites
					   WHERE 	site_id 
					   IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
					   AND 		type = 'member_id'
					   AND 		member_id = '" . ee()->db->escape_str( $user_id ) . "'
					   AND 		author_id = '" . ee()->db->escape_str( $author_id ) . "'";
		}
		else
		{
			$sql	= "SELECT 	COUNT(*) AS count
					   FROM 	exp_favorites
					   WHERE 	site_id 
					   IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
					   AND 		type = 'entry_id'
					   AND 		member_id = '" . ee()->db->escape_str( $user_id ) . "'
					   AND 		entry_id = '" . ee()->db->escape_str( $entry_id ) . "'";
		}

		//silly DB caches >:(
		$query		= $this->cacheless_query( $sql );

		if ( $query->row('count') >= 1 ) 
		{
			return $this->messages['no_duplicates'];
		}

		// -------------------------------------------
		//  Prepare insert
		// -------------------------------------------

		$public	= (stristr($uri, "/private")) ? 'n' : 'y';

		$data	= array(
			'type'			=> $type,
			'author_id'		=> ( isset( $author_id ) === TRUE )  ? $author_id : '',
			'entry_id'		=> ( isset( $entry_id ) === TRUE )   ? $entry_id  : '',
			'member_id'     => $user_id,
			'entry_date'	=> ee()->localize->now,
			'site_id'		=> isset($entry_site_id) ? $entry_site_id : ee()->config->item('site_id'),
			'notes'         => '',
			'public'        => $public
		);

		// ----------------------------------------
		// 'insert_favorite_start' hook.
		//  - Change or add additional processing before saving an favorite
		//	- Added Favorites 3.0.5
		// ----------------------------------------
		
		if (ee()->extensions->active_hook('insert_favorite_start') === TRUE)
		{
			$data = ee()->extensions->universal_call('insert_favorite_start', $data);
			if (ee()->extensions->end_script === TRUE) { return $data; }
		}


		// -------------------------------------------
		//  Insert
		// -------------------------------------------

		ee()->db->query( ee()->db->insert_string( 'exp_favorites', $data ) );
		$favorite_id = ee()->db->insert_id();

		// --------------------------------------------
        //  Update Stats
        // --------------------------------------------

		if ( $type == 'member_id' )
		{
			$this->update_stats($author_id, 'member_id');
		}
		else
		{
			$this->update_stats($entry_id, 'entry_id');
		}

		// ----------------------------------------
		// 'insert_favorite_end' hook.
		//  - Change or add additional processing after saving an favorite
		//	- Added Favorites 3.0.5
		// ----------------------------------------

		if (ee()->extensions->active_hook('insert_favorite_end') === TRUE)
		{
			$edata = ee()->extensions->universal_call('insert_favorite_end', $data, $favorite_id);
			if (ee()->extensions->end_script === TRUE) { return $edata; }
		}


		// -------------------------------------------
		//  Return success
		// -------------------------------------------

		return $this->messages['success_add'];
	}
	// END save()


	// --------------------------------------------------------------------

	/**
	 *	Update Favorite Stats for Member or Entry
	 *
	 *	@access		public
	 *	@param		integer		$id for either entry or member
	 *	@param		string		$type, either member_id or entry_id.
	 *	@return		bool
	 */
	 
	public function update_stats($id, $type)
	{		
		if ( ! in_array($type, array('member_id', 'entry_id')))
		{
			return FALSE;
		}
		
		$site_ids = (is_object(ee()->TMPL)) ? 
						ee()->TMPL->site_ids : array(ee()->config->item('site_id'));
		
		if ($type == 'member_id')
		{
			// -------------------------------------------
			//  Update The Counts in exp_members
			// -------------------------------------------
			
			$count = $this->cacheless_query( 
				"SELECT COUNT(*) AS count 
				 FROM 	exp_favorites
				 WHERE 	site_id 
				 IN 	('" . implode("','", ee()->db->escape_str($site_ids)) . "')
				 AND 	member_id = '" . ee()->db->escape_str( $id ) . "'" );
								  
			$public_count = $this->cacheless_query(	 
				"SELECT COUNT(*) AS count 
				 FROM 	exp_favorites
				 WHERE 	site_id 
				 IN 	('" . implode("','", ee()->db->escape_str($site_ids)) . "')
				 AND 	public = 'y'
				 AND 	member_id = '" . ee()->db->escape_str( $id ) . "'" 
			);
	
			ee()->db->query( 
				ee()->db->update_string( 
					'exp_members', 
					array( 'favorites_count' => $count->row('count')), 
					array( 'member_id' 		 => $id ) 
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
		else
		{	
			// --------------------------------------------
			//  Update Our Counts in exp_weblog_titles
			// --------------------------------------------
	
			$count = $this->cacheless_query( 
				"SELECT COUNT(*) AS count 
				 FROM 	exp_favorites
				 WHERE 	site_id 
				 IN 	('" . implode("','", ee()->db->escape_str($site_ids)) . "')
				 AND 	entry_id = '" . ee()->db->escape_str( $id ) . "'" 
			);
								  
			$public_count = $this->cacheless_query(	 
				"SELECT COUNT(*) AS count 
				 FROM 	exp_favorites
				 WHERE 	site_id 
				 IN 	('" . implode("','", ee()->db->escape_str($site_ids)) . "')
				 AND 	public = 'y'
				 AND 	entry_id = '" . ee()->db->escape_str( $id ) . "'" 
			);
	
			ee()->db->query( 
				ee()->db->update_string( 
					$this->sc->db->channel_titles, 
					array( 'favorites_count' => $count->row('count')), 
					array( 'entry_id' 		 => $id ) 
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
	// END update_stats()
	
	
	// --------------------------------------------------------------------

	/**
	 *	Add a Favorite
	 *  This is a deprecated function
	 * 
	 *	@access		public
	 *	@return		bool
	 */

	public function add_favorite_entry()
	{
		return $this->save();
	}
	// END add_favorite_entry()		
	
	
	// --------------------------------------------------------------------

	/**
	 *	Delete a Favorite for an entry
	 * 
	 *	@access		public
	 *	@return		bool
	 */
	
	public function delete()
	{
		return $this->save( 'entry_id', 'delete' );
	}
	//	End delete()	

	
	// --------------------------------------------------------------------

	/**
	 *	Delete all Favorites for a member
	 * 
	 *	@access		public
	 *	@return		string
	 */
	
	public function delete_all()
	{
		// -------------------------------------------
		//  Set messages
		// -------------------------------------------

        $this->set_messages();

		// -------------------------------------------
		//  Not logged in?  Fail out gracefully.
		// -------------------------------------------

        if ( ee()->session->userdata['member_id'] == '0' )
        {
            return $this->messages['no_login'];
        }

		// -------------------------------------------
		//  Update last activity
		// -------------------------------------------

		$this->_update_last_activity();

		// -------------------------------------------
		//  Our Query!
		// -------------------------------------------

		$query = ee()->db->query(
			"SELECT type, entry_id, author_id, public 
			 FROM 	exp_favorites
			 WHERE 	member_id = '" . ee()->db->escape_str(ee()->session->userdata['member_id']) . "'
			 AND 	site_id 
			 IN 	('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')");

		if ($query->num_rows() == 0)
		{
			return $this->messages['success_delete'];
		}

		// -------------------------------------------
		//  Default Variables Required for Delete All
		// -------------------------------------------

		$sql			= array();
		$authors		= array();
		$authors_public	= array();
		$entries		= array();
		$entries_public = array();

		// -------------------------------------------
		//  Tabulate, Add Up, and Organize Counts and Info
		// -------------------------------------------

		foreach ($query->result_array() as $row)
		{
			if ($row['type'] == 'member_id')
			{
				if (isset($authors[$row['author_id']]))
				{
					$authors[$row['author_id']]++;
				}
				else
				{
					$authors[$row['author_id']] = 1;
				}

				if ($row['public'] == 'y')
				{
					if (isset($authors[$row['author_id']]))
					{
						$authors_public[$row['author_id']]++;
					}
					else
					{
						$authors_public[$row['author_id']] = 1;
					}

				}
			}
			else
			{
				$entries[] = $row['entry_id'];

				if ($row['public'] == 'y')
				{
					$entries_public[] = $row['entry_id'];
				}
			}
		}

		// -------------------------------------------
		//  Clever Coding for Author Stat Updates
		// -------------------------------------------

		// Authors can have their favorite counts upped multiple times by a member.
		// By adding those favorite counts up and THEN grouping members with the same
		// amount to be removed today, we can reduce the number of queries required.

		$author_counts			= array();
		$author_counts_public	= array();

		foreach ($authors as $author_id => $count)
		{
			$author_counts[$count][] = $author_id;
		}

		foreach ($authors_public as $author_id => $count)
		{
			$author_counts_public[$count][] = $author_id;
		}

		// ----------------------------------------
		// 'delete_all_favorites_start' hook.
		//  - Change or add additional processing before saving an favorite
		//	- Added Favorites 3.0.5
		// ----------------------------------------

		if (ee()->extensions->active_hook('delete_all_favorites_start') === TRUE)
		{
			ee()->extensions->universal_call('delete_all_favorites_start');
			
			if (ee()->extensions->end_script === TRUE) return;
		}

		// -------------------------------------------
		//  Delete All Favorites for this User for Site
		// -------------------------------------------

		$sql[] = "DELETE FROM 	exp_favorites
				  WHERE 	  	member_id = '" . ee()->db->escape_str(ee()->session->userdata['member_id']) . "'
				  AND 			site_id 
				  IN 			('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')";

		// -------------------------------------------
		//  Update Weblog Entries Stats
		// -------------------------------------------

		if (count($entries) > 0)
		{
			$sql[] = "UPDATE 	{$this->sc->db->channel_titles}
					  SET 	 	favorites_count = (favorites_count - 1)
					  WHERE 	entry_id 
					  IN 		('" . implode("','", ee()->db->escape_str($entries)) . "')";
		}

		if (count($entries_public) > 0)
		{
			$sql[] = "UPDATE 	{$this->sc->db->channel_titles}
					  SET 		favorites_count_public = (favorites_count_public - 1)
					  WHERE 	entry_id 
					  IN 		('" . implode("','", ee()->db->escape_str($entries)) . "')";
		}

		// -------------------------------------------
		//  Update Authors' Stats
		// -------------------------------------------

		foreach ($author_counts as $count => $authors)
		{
			$sql[] = "UPDATE 	exp_members
					  SET 		favorites_count = (favorites_count - ".ceil($count).")
					  WHERE 	member_id IN ('".implode("','", ee()->db->escape_str($authors))."')";
		}

		foreach ($author_counts_public as $count => $authors)
		{
			$sql[] = "UPDATE 	exp_members
					  SET 		favorites_count_public = (favorites_count_public - ".ceil($count).")
					  WHERE 	member_id IN ('".implode("','", ee()->db->escape_str($authors))."')";
		}

		// -------------------------------------------
		//  Run Queries
		// -------------------------------------------

		//print_r($sql); exit;

		foreach ($sql as $sql_query)
		{
			ee()->db->query($sql_query);
		}

		// ----------------------------------------
		// 'delete_all_favorites_end' hook.
		//  - Change or add additional processing before saving an favorite
		//	- Added Favorites 3.0.5
		// ----------------------------------------

		if (ee()->extensions->active_hook('delete_all_favorites_end') === TRUE)
		{
			ee()->extensions->universal_call('delete_all_favorites_end');
			
			if (ee()->extensions->end_script === TRUE) return;
		}

		// -------------------------------------------
		//  Return success
		// -------------------------------------------

		return $this->messages['success_delete_all'];
	}
	// End delete_all()
	
	
	// --------------------------------------------------------------------

	/**
	 *	Delete a Favorite for a member
	 * 
	 *	@access		public
	 *	@return		string
	 */
	
	public function delete_member()
	{
		return $this->save( 'member_id', 'delete' );
	}
	//	End delete_member()		


	// --------------------------------------------------------------------

	/**
	 *  has this been saved already?
	 * 
	 *	@access		public
	 *	@return		string
	 */
	
	public function member_saved() 
	{
		return $this->saved( 'member_id' ); 
	}
	//END member_saved()	


	// --------------------------------------------------------------------

	/**
	 *  Saved() helps you test whether a member
	 *	has saved an entry already
	 * 
	 *	@access		public
	 *  @param		string type to check
	 *	@return		string tagdata with conditions for saved parsed
	 */
	
	public function saved( $type = 'entry_id' )
	{
		$saved		= FALSE;

		$user_id	= ee()->session->userdata['member_id'];

		if ( $this->_entry_id( $type ) === TRUE AND $user_id != '0' )
		{
			if ( $type == 'member_id' )
			{
				$sql	= "SELECT 	COUNT(*) AS count
						   FROM 	exp_favorites
						   WHERE 	site_id 
						   IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
						   AND 		type 		= 'member_id'
						   AND 		author_id 	= '" . ee()->db->escape_str( $this->member_id ) . "'
						   AND 		member_id 	= '" . ee()->db->escape_str( $user_id ) . "'";
			}
			else
			{
				$sql	= "SELECT 	COUNT(*) AS count
						   FROM 	exp_favorites
						   WHERE 	site_id 
						   IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
						   AND 		type 		= 'entry_id'
						   AND 		entry_id 	= '" . ee()->db->escape_str( $this->entry_id ) . "'
						   AND 		member_id 	= '" . ee()->db->escape_str( $user_id ) . "'";
			}

			$query = $this->cacheless_query( $sql );

			if ($query->row('count') > 0)
			{
				$saved	= TRUE;
			}
		}

		$tagdata			= ee()->TMPL->tagdata;

		$cond['saved']		= ( $saved ) 	? TRUE: FALSE;
		$cond['not_saved']	= ( ! $saved ) 	? TRUE: FALSE;

		$tagdata			= ee()->functions->prep_conditionals($tagdata, $cond);

		return $tagdata;
	}
	// End saved()


	// --------------------------------------------------------------------

	/**
	 *  entries
	 * 
	 *	@access		public
	 *	@return		string result of $this->_entries();
	 */
	
	public function entries()
	{
		$entry_id	= '';

		// -------------------------------------------
		//  Validate member
		// -------------------------------------------
		
		//has favorites_member_id=?
		if ( ! in_array( ee()->TMPL->fetch_param('favorites_member_id'), array(FALSE, '') ) )
		{
			$member_id = ee()->TMPL->fetch_param('favorites_member_id');

			if ($member_id == 'CURRENT_USER' && ee()->session->userdata['member_id'] != '0')
			{
				$member_id = ee()->session->userdata['member_id'];
			}
		}
		//has favorites_username=?
		elseif ( ! in_array( ee()->TMPL->fetch_param('favorites_username') , array(FALSE, '') ) )
		{
			$username = ee()->TMPL->fetch_param('favorites_username');

			if ($username == 'CURRENT_USER' && ee()->session->userdata['member_id'] != '0')
			{
				$username = ee()->session->userdata['username'];
			}
		}
		//uri indicator?
		elseif ( preg_match( "/\/(\d+)\/?/s", ee()->uri->uri_string, $match ) && 
				 ! $this->check_no(ee()->TMPL->fetch_param('dynamic')) )
		{
			$member_id	= $match['1'];
		}
		//logged in?
		elseif ( ee()->session->userdata['member_id'] != '0' )
		{
			$member_id	= ee()->session->userdata['member_id'];
		}
		else
		{
			return $this->no_results( 'favorites' );
		}

		// -------------------------------------------
		//  Grab entries
		// -------------------------------------------

		$sql	= "SELECT 	exp_favorites.entry_id, exp_favorites.member_id 
				   FROM 	exp_favorites";

		if (isset($username))
		{
			$sql 	.= " LEFT JOIN 	exp_members 
						 ON 		exp_favorites.member_id = exp_members.member_id
						 WHERE 		exp_members.username = '" . ee()->db->escape_str($username) . "'";
		}
		else
		{
			$sql	.= " WHERE exp_favorites.member_id ='" . ee()->db->escape_str( $member_id ) . "'";
		}

		$sql .= " AND 	exp_favorites.site_id 
				  IN 	('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
				  AND 	exp_favorites.type = 'entry_id'";

		if (ee()->TMPL->fetch_param('favorites_start_on') !== FALSE)
        {
            $sql .= "AND exp_favorites.entry_date >= '" . 	
					ee()->localize->convert_human_date_to_gmt(
						ee()->TMPL->fetch_param('favorites_start_on')
					) . "' ";
		}

        if (ee()->TMPL->fetch_param('favorites_stop_before') !== FALSE)
        {
        	$sql .= "AND exp_favorites.entry_date < '" . 
						ee()->localize->convert_human_date_to_gmt(
							ee()->TMPL->fetch_param('favorites_stop_before')
						) . "' ";
        }

		//public favorite?
        if (ee()->TMPL->fetch_param('favorites_type') == 'private')
        {
        	$sql .= " AND public = 'n'";
        }
        elseif (ee()->TMPL->fetch_param('favorites_type') == 'both')
        {
        	// Nothing! (nothing? -greg)
        }
        else
        {
			$sql .= " AND public = 'y'";
		}

		if( ee()->TMPL->fetch_param('orderby') == 'favorites_date' AND ee()->TMPL->fetch_param('limit') != ''  )
		{
			/*
			$limit = ( ! ee()->TMPL->fetch_param('limit') OR 
							! is_numeric(ee()->TMPL->fetch_param('limit'))) ? 
								'9999' : ee()->TMPL->fetch_param('limit');

			$offset = ( ! ee()->TMPL->fetch_param('offset') OR 
						! is_numeric(ee()->TMPL->fetch_param('offset'))) ? 
							'0' : ee()->TMPL->fetch_param('offset');

			$sort = (in_array(strtoupper(ee()->TMPL->fetch_param('sort')), array('DESC', 'ASC'))) ?
 						strtoupper(ee()->TMPL->fetch_param('sort')) : 'DESC';

			$sql .= " ORDER BY entry_date " . $sort . " LIMIT " . $offset . ',' . $limit;
			*/
		}

		$query	= ee()->db->query( $sql );

		//no results?
		if ( $query->num_rows() == 0 )
		{
			if (ee()->TMPL->fetch_param('favorites_count') == 'yes')
			{
				return $this->return_data = str_replace('{favorites_count}', '0', ee()->TMPL->tagdata);
			}
			else
			{
				return $this->no_results( 'favorites' );
			}
		}
		
		$this->total_rows	= $query->num_rows();
		
		foreach ( $query->result_array() as $row )
		{
			$this->entry_id	.= $row['entry_id'] . '|';
		}

		$this->member_id = $query->row('member_id');

		//unset($query->result);

		ee()->TMPL->tagparams['dynamic'] = 'off';

		//	----------------------------------------
		//	Parse and return
		//	----------------------------------------

        return $this->_entries();
	}
	//	End entries()
	
		
	// --------------------------------------------------------------------

	/**
	 *  _entries
	 * 
	 *	@access		public
	 *  @param		array 	params for weblog options
	 *	@return		string 	result of $this->_entries();
	 */
	
	public function _entries ( $params = array() )
	{
		//	----------------------------------------
		//	Execute?
		//	----------------------------------------

		if ( $this->entry_id == '' ) 
		{
			return FALSE;
		}

		//	----------------------------------------
		//	Invoke Channel/Weblog class
		//	----------------------------------------

		if (APP_VER < 2.0)
		{
			if ( ! class_exists('Weblog') )
			{
				require PATH_MOD.'/weblog/mod.weblog'.EXT;
			}
		
			$channel = new Weblog;
		}
		else
		{
			if ( ! class_exists('Channel') )
			{
				require PATH_MOD.'/channel/mod.channel'.EXT;
			}
	
			$channel = new Channel;
		}
		
		// --------------------------------------------
        //  Invoke Pagination for EE 2.4 and Above
        // --------------------------------------------

		if (APP_VER >= '2.4.0')
		{
			ee()->load->library('pagination');
			$channel->pagination = new Pagination_object('Channel');
			
			// Used by pagination to determine whether we're coming from the cache
			$channel->pagination->dynamic_sql = FALSE;
		}

		//	----------------------------------------
		//	Pass params
		//	----------------------------------------

		ee()->TMPL->tagparams['entry_id']	= $this->entry_id;

		// Clear the url_title value, we are using it in a different context 
		ee()->TMPL->tagparams['url_title']	= NULL;

        ee()->TMPL->tagparams['inclusive']	= '';

		ee()->TMPL->tagparams['show_pages']	= 'all';

        if ( isset( $params['dynamic'] ) AND $this->check_no($params['dynamic'])  )
        {
			ee()->TMPL->tagparams['dynamic']	= (APP_VER < 2.0) ? 'off' : 'no';
        }

		//	----------------------------------------
		//	Pre-process related data
		//	----------------------------------------
		//	Look. This sucks. Those knuckleheads
		//	have the TMPL class coded so that only
		//	one method in the weblog class and one
		//	method in the search class are allowed
		//	to parse related entries tags. This is
		//	no doubt for performance reasons. I
		//	can dig it. But it makes 3rd party
		//	developers' jobs hard. Well, write your
		//	own damned platform then Mitchell. See
		//	how you like it. Fine, I think I'll
		//	write the software platform that all
		//	inter-stellar space craft will rely on
		//	for life support. Then we'll see about
		//	classes and methods Rick and Paul. Then
		//	we'll see indeed.
		//	----------------------------------------


		if (ee()->TMPL->fetch_param('favorites_count') != 'yes')
		{
			ee()->TMPL->tagdata		= ee()->TMPL->assign_relationship_data( ee()->TMPL->tagdata );

			ee()->TMPL->var_single	= array_merge( ee()->TMPL->var_single, ee()->TMPL->related_markers );
		}

		//	----------------------------------------
		//	Execute needed methods
		//	----------------------------------------

		if (APP_VER < 2.0)
		{
        	$channel->fetch_custom_weblog_fields();
		}
		else
		{
			$channel->fetch_custom_channel_fields();
		}

        $channel->fetch_custom_member_fields();

        if (ee()->TMPL->fetch_param('favorites_count') != 'yes')
        {
			// --------------------------------------------
			//  Pagination Tags Parsed Out
			// --------------------------------------------
			
			if (APP_VER >= '2.4.0')
			{
				$channel->pagination->get_template();
				$channel->pagination->cfields = $channel->cfields;
			}
			else
			{
				$channel->fetch_pagination_data();
			}

			//	----------------------------------------
			//	Override segment 3 momentarily
			//	----------------------------------------
			//	We need to force some functionality on EE 2. The CI Pagination class looks at the 
			//	3rd URI segment. If it sees an integer there, it assumes the number is a page 
			//	number and builds pagination based on that. We want that to be ignored.
			//	----------------------------------------
			
			if ( APP_VER >= 2.0 )
			{
				$segs	= ee()->uri->segments;
				ee()->uri->segments[3]	= '';
			}
			
			// I think this can actually be removed, as the pagination calls will be done
			// in build_sql_query() below.  At the same time, they are not doing any harm, just
			// a bit of extra work.  Let's remove them when we no longer support versions
			// prior to EE 2.4.  -PB
			
			if (APP_VER >= '2.4.0')
			{
				//$channel->pagination->build();
			}
			else
			{
				//$channel->create_pagination();
			}		
		}

		//	----------------------------------------
		//	 Build Weblog Data Query
		//	----------------------------------------
		
		// Since they no longer give us $this->pager_sql in EE 2.4, I will just
		// insure it is stored  and pull it right back out to use again.
		if (APP_VER >= '2.4.0')
		{
			ee()->db->save_queries = TRUE;
		}

        $channel->build_sql_query();
        
        // --------------------------------------------
        //  Transfer Pagination Variables Over to Channel object
        //	- Has to go after the building of the query as EE 2.4 does its Pagination work in there
        // --------------------------------------------
        
        if (APP_VER >= '2.4.0')
		{
			$transfer = array(	'paginate'		=> 'paginate',
								'total_pages' 	=> 'total_pages',
								'current_page'	=> 'current_page',
								'offset'		=> 'offset',
								'page_next'		=> 'page_next',
								'page_previous'	=> 'page_previous',
								'page_links'	=> 'pagination_links', // different!
								'total_rows'	=> 'total_rows',
								'per_page'		=> 'per_page',
								'per_page'		=> 'p_limit',
								'offset'		=> 'p_page');
								
			foreach($transfer as $from => $to)
			{
				$channel->$to = $channel->pagination->$from;
			}
		}

		//	----------------------------------------
		//	Return segment 3 now
		//	----------------------------------------
		//	We need to force some functionality on EE 2. The CI Pagination class looks at the 3rd URI segment. If it sees an integer there, it assumes the number is a page number and builds pagination based on that. We want that to be ignored.
		//	----------------------------------------
		
		if ( isset( $segs ) === TRUE )
		{
			ee()->uri->segments	= $segs;
		}

        if ($channel->sql == '')
        {
        	return $this->no_results();
        }

        //	----------------------------------------
		//	 Favorites Specific Rewrites!
		//	----------------------------------------

        if (ee()->TMPL->fetch_param('favorites_count') == 'yes')
        {
        	$query = ee()->db->query(
				preg_replace(
					"/SELECT(.*?)\s+FROM\s+/is", 
					'SELECT COUNT(*) AS count FROM ', 
					$channel->sql
				)
			);

        	return $this->return_data = str_replace(
				LD . 'favorites_count' . RD, 
				$query->row('count'), 
				ee()->TMPL->tagdata
			);
        }

        if ( stristr(ee()->TMPL->tagdata, LD.'favorites_date ') OR  
        	ee()->TMPL->fetch_param('orderby') == 'favorites_date')
        {
        	$sort = (in_array(strtoupper(ee()->TMPL->fetch_param('sort')), array('DESC', 'ASC'))) ?
 						strtoupper(ee()->TMPL->fetch_param('sort')) : 'DESC';

        	// --------------------------------------------
			//  EE 2.4 removed $this->pager from the Channel class.
			//	To find it, we do some clever searching.
			// --------------------------------------------
			
			if (APP_VER >= '2.4.0')
			{
				$num = sizeof(ee()->db->queries) - 1;
					
				while($num > 0)
				{
					$test_sql = ee()->db->queries[$num];
					
					if ( substr(trim($test_sql), 0, strlen('SELECT t.entry_id FROM')) == 'SELECT t.entry_id FROM')
					{
						$channel->pager_sql = $test_sql;
						break;
					}
					
					$num--;
				}
				
				if (ee()->config->item('show_profiler') != 'y' && DEBUG != 1)
				{
					ee()->db->save_queries	= FALSE;
					ee()->db->queries 		= array();
				}
			}
			
			// --------------------------------------------
			//  Fun Times with Pagination Manipulation
			// --------------------------------------------

        	if ( ! empty($channel->pager_sql) && $channel->paginate == TRUE )
        	{
        		$channel->pager_sql = preg_replace(
					"/\s+FROM\s+/s",
        			", f.entry_date AS favorites_date FROM ",
        			ltrim($channel->pager_sql)
				);

				$channel->pager_sql = preg_replace(
					"/LEFT JOIN\s+{$this->sc->db->channels}/is",
					"LEFT JOIN 	exp_favorites AS f 
					 ON 		t.entry_id = f.entry_id
					 LEFT JOIN 	{$this->sc->db->channels}",
					$channel->pager_sql
				);

				if ($this->member_id != '' && is_numeric($this->member_id))
				{
					$channel->pager_sql = preg_replace(
						"/WHERE\st.entry_id\s+/is",
						"WHERE 	f.member_id = '" . ee()->db->escape_str($this->member_id) . "' 
						 AND 	t.entry_id ",
						ltrim($channel->pager_sql)
					);
				}
				
				// In EE 2.4.0 we find the pager_sql in the query log.
				// Previous to that we actually got it from $channel
				// However, it was missing the ORDER clause, so we add it back in
				if (APP_VER < '2.4.0')
				{
					if (preg_match("/ORDER BY(.*?)(LIMIT|$)/s", $channel->sql, $matches))
					{
						$channel->pager_sql .= 'ORDER BY'.$matches[1];
					}
				}

				if (ee()->TMPL->fetch_param('orderby') == 'favorites_date')
        		{
        			if (stristr($channel->pager_sql, 'ORDER BY'))
        			{
        				$channel->pager_sql = preg_replace("/ORDER BY(.*?)(,|LIMIT|$)/s", 
														   'ORDER BY favorites_date '.$sort.',\1\2',
														   $channel->pager_sql);
					}
					else
					{
						$channel->pager_sql .= ' ORDER BY favorites_date '.$sort.' ';
					}
				}
				
				// In EE 2.4.0 we find the pager_sql in the query log.
				// Previous to that we actually got it from $channel
				// However, it was missing the LIMIT clause, so we add it back in
				if (APP_VER < '2.4.0')
				{
					$offset = ( ! ee()->TMPL->fetch_param('offset') OR 
								! is_numeric(ee()->TMPL->fetch_param('offset'))) ? 
									'0' : ee()->TMPL->fetch_param('offset');
	 
					$channel->pager_sql .= ($channel->p_page == '') ? 
						" LIMIT " . $offset . ', ' . $channel->p_limit : 
						" LIMIT " . $channel->p_page . ', ' . $channel->p_limit;
				
				}
				
				$pquery = ee()->db->query($channel->pager_sql);

				$entries = array();

				// Build ID numbers (checking for duplicates)

				foreach ($pquery->result_array() as $row)
				{
					$entries[] = $row['entry_id'];
				}

				$channel->sql = preg_replace(
					"/t\.entry_id\s+IN\s+\([^\)]+\)/is",
        			"t.entry_id IN (".implode(',', $entries).")",
        			$channel->sql
				);

				//?
				unset($pquery);
				unset($entries);
        	}

        	// --------------------------------------------
			//  Rewrite the Weblog Data Query
			// --------------------------------------------

        	$channel->favorites_date = TRUE;

        	$channel->sql = preg_replace(
				"/\s+FROM\s+/s",
        		", f.entry_date AS favorites_date FROM ",
        		ltrim($channel->sql)
			);

        	$channel->sql = preg_replace(
				"/LEFT JOIN\s+{$this->sc->db->channels}/is",
        		"LEFT JOIN 	exp_favorites AS f 
  				 ON 		t.entry_id = f.entry_id
  				 LEFT JOIN 	{$this->sc->db->channels}",
        		$channel->sql
			);

        	if ($this->member_id != '' && is_numeric($this->member_id))
        	{
        		$channel->sql = preg_replace(
					"/WHERE\st.entry_id\s+/is",
        			"WHERE 	f.member_id = '" . ee()->db->escape_str($this->member_id) . "' 
					 AND 	t.entry_id ",
        			 ltrim($channel->sql)
				);
        	}

        	if (ee()->TMPL->fetch_param('orderby') == 'favorites_date')
        	{
        		$channel->sql = preg_replace(
					"/ORDER BY.+?(LIMIT|$)/is",
        			"ORDER BY favorites_date " . $sort . ' \1',
        			$channel->sql
				);
        	}
        }

        $channel->query = ee()->db->query($channel->sql);

		if (APP_VER < 2.0)
		{
			$channel->query->result	= $channel->query->result_array();
		}

		//	----------------------------------------
		//	Are we forcing the order?
		//	----------------------------------------

		if ( ee()->TMPL->fetch_param( 'tag_rank' ) !== FALSE )
		{
			//	----------------------------------------
			//	Reorder
			//	----------------------------------------
			//	The weblog class fetches entries and
			//	sorts them for us, but not according to
			//	our ranking order. So we need to
			//	reorder them.
			//	----------------------------------------

			$new	= array_flip(explode( "|", $this->entry_id ));

			foreach ( $channel->query->result_array() as $key => $row )
			{
				$new[$row['entry_id']] = $row;
			}

			//	----------------------------------------
			//	Redeclare
			//	----------------------------------------
			//	We will reassign the
			//	$channel->query->result with our
			//	reordered array of values. Thank you
			//	PHP for being so fast with array loops.
			//	----------------------------------------

			if (APP_VER < 2.0)
			{
				$channel->query->result	= array_values($new);
			}
			else
			{
				$channel->query->result_array = array_values($new);
			}

			//	Clear some memory
			unset( $new );
			unset( $entries );
		}

        if ( isset( $channel->query ) === FALSE OR $channel->query->num_rows() == 0)
        {
            return FALSE;
        }

		//	----------------------------------------
		//	typography
		//	----------------------------------------

        if (APP_VER < 2.0)
        {
        	if ( ! class_exists('Typography'))
			{
				require PATH_CORE.'core.typography'.EXT;
			}
					
			$channel->TYPE = new Typography;
			$channel->TYPE->convert_curly = FALSE;
        }
        else
        {
			ee()->load->library('typography');
			ee()->typography->initialize();
			ee()->typography->convert_curly = FALSE;
		}
		
        $channel->fetch_categories();

		//	----------------------------------------
		//	Parse and return entry data
		//	----------------------------------------

		if (APP_VER < 2.0)
		{
        	$channel->parse_weblog_entries();
		}
		else
		{
			//	----------------------------------------
			//	Here's another pagination hack to make sure that total pages parses correctly in the template.
			//	----------------------------------------
			
			// echo (floor($channel->total_rows / $channel->p_limit));
		
			$channel->total_pages	= ceil($channel->total_rows / $channel->p_limit);
			
			$channel->parse_channel_entries();
		}
			
		// $channel->total_pages	= ( $channel->total_pages == 0 ) ? 1: $channel->total_pages;

		if (APP_VER >= '2.4.0')
		{
			$channel->return_data = $channel->pagination->render($channel->return_data);
		}
		else
		{
			$channel->add_pagination_data();
		}

		//	----------------------------------------
		//	Count tag
		//	----------------------------------------

		if (count(ee()->TMPL->related_data) > 0 AND count($channel->related_entries) > 0)
		{
			$channel->parse_related_entries();
		}

		if (count(ee()->TMPL->reverse_related_data) > 0 AND count($channel->reverse_related_entries) > 0)
		{
			$channel->parse_reverse_related_entries();
		}

		//	----------------------------------------
		//	Handle problem with pagination segments
		//	in the url
		//	----------------------------------------

		if ( preg_match("#(/?P\d+)#", ee()->uri->uri_string, $match) )
		{
			$channel->return_data	= str_replace( $match['1'], "", $channel->return_data );
		}

        $tagdata = $channel->return_data;

        return $tagdata;
	}
	// End _entries()	
	
	
	// --------------------------------------------------------------------

	/**
	 *  This fetches a list of members who have
	 *	favorited a given member.
	 * 
	 *	@access		public
	 *	@return		string 	result of $this->members(), tagdata output
	 */
	
	public function fans () 
	{ 
		return $this->members( 'member_id' ); 
	}
	//	End fans()


	// --------------------------------------------------------------------

	/**
	 * This fetches a list of favorited members
	 * belonging to the logged in member
	 * 
	 *	@access		public
	 *	@return		string 	tagdata for ouput
	 */
	
	public function my_members()
	{
		$groups	= ( is_numeric( ee()->TMPL->fetch_param('groups') ) ) ? 
						ee()->TMPL->fetch_param('groups'): '1';

		// -------------------------------------------
		//  Get member id
		// -------------------------------------------

		if ( ee()->session->userdata('member_id') == 0 )
		{
			return $this->no_results();
		}
		else
		{
			$this->member_id = ee()->session->userdata('member_id');
		}

		// -------------------------------------------
		//  Begin SQL
		// -------------------------------------------

		$sql	= "SELECT DISTINCT m.*";

		// -------------------------------------------
		//  Add custom member fields
		// -------------------------------------------

		$this->_fetch_custom_member_fields();

		foreach ( $this->mfields as $key => $value )
		{
			$sql	.= ", md.m_field_id_".$value['0']." AS ".$key;
		}

		$sql	.= " FROM 		exp_members 	AS m 
					 LEFT JOIN 	exp_member_data AS md 
					 ON 		md.member_id 	= m.member_id 
					 LEFT JOIN 	exp_favorites 	AS f 
					 ON 		m.member_id 	= f.author_id 
					 WHERE 		f.public 		= 'y' 
					 AND 		f.type 			= 'member_id' 
					 AND  		f.member_id 	= '" . ee()->db->escape_str( $this->member_id ) . "'";

		// -------------------------------------------
		//  Allow narcissism?
		// -------------------------------------------

		if ( 
			( ee()->TMPL->fetch_param( 'allow_narcissism' ) !== FALSE AND 
			   ! $this->check_yes( ee()->TMPL->fetch_param( 'allow_narcissism' ) ) ) OR 
			 ( ee()->TMPL->fetch_param( 'allow_narcisism' ) !== FALSE AND 
			   ! $this->check_yes( ee()->TMPL->fetch_param( 'allow_narcisism' ) ) ) 
		   )
		{
			$sql	.= " AND f.author_id != f.member_id";
		}

		// -------------------------------------------
		//  Limit by member group
		// -------------------------------------------

		if ( ee()->TMPL->fetch_param('group_id') )
		{
			$sql	.= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('group_id'), 'm.group_id' );
		}

		// -------------------------------------------
		//  Order by
		// -------------------------------------------

		if ( ee()->TMPL->fetch_param('orderby') !== FALSE AND ee()->TMPL->fetch_param('orderby') != '' )
		{
			if ( isset( $this->mfields[ee()->TMPL->fetch_param('orderby')] ) )
			{
				$sql	.= " ORDER BY md.m_field_id_" . $this->mfields[ee()->TMPL->fetch_param('orderby')]['0'];
			}
			else
			{
				$sql	.= " ORDER BY m." . ee()->TMPL->fetch_param('orderby');
			}
		}
		else
		{
			$sql	.= " ORDER BY m.screen_name";
		}

		// -------------------------------------------
		//  Sort
		// -------------------------------------------

		if ( ee()->TMPL->fetch_param('sort') == 'asc' )
		{
			$sql	.= " ASC";
		}
		else
		{
			$sql	.= " DESC";
		}

		// -------------------------------------------
		//  Limit
		// -------------------------------------------

		if ( is_numeric( ee()->TMPL->fetch_param('limit') ) )
		{
			$sql	.= " LIMIT " . ee()->TMPL->fetch_param('limit');
		}

		// -------------------------------------------
		//  Run query
		// -------------------------------------------

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			return $this->no_results();
		}

		// ----------------------------------------
		//  Parse count
		// ----------------------------------------

		ee()->TMPL->tagdata	= ee()->TMPL->swap_var_single( 'favorites_count', $query->num_rows(), ee()->TMPL->tagdata );

		// ----------------------------------------
		//  Parse list
		// ----------------------------------------

		return $this->_members( $query, $groups );
	}
	//	End my_members()


	// --------------------------------------------------------------------

	/**
	 * This fetches a list of members who have
	 * favorited a given entry.
	 * 
	 *	@access		public
	 *	@return		string 	result of $this->members, tagdata
	 */
	
	public function subscribers() 
	{ 
		return $this->members( 'entry_id' ); 
	}
	//	End subscribers()


	// --------------------------------------------------------------------

	/**
	 * This fetches a list of members who have
	 * favorited a given entry or member.
	 * 
	 *	@access		public
	 *  @param 		string 	type to look up
	 * 	@param		string  ranking type
	 *	@return		string 	result of $this->members, tagdata
	 */

	public function members( $type = 'entry_id', $rank = '' )
	{
		$groups	= ( is_numeric( ee()->TMPL->fetch_param('groups') ) ) ? 
			ee()->TMPL->fetch_param('groups'): '1';

		// -------------------------------------------
		//  Get entry id
		// -------------------------------------------

		if ( $this->_entry_id( $type ) === FALSE AND $rank == '' )
		{
			return $this->no_results();
		}

		// -------------------------------------------
		//  Begin SQL
		// -------------------------------------------

		$sql	= "SELECT DISTINCT m.*";

		// -------------------------------------------
		//  Add custom member fields
		// -------------------------------------------

		$this->_fetch_custom_member_fields();

		foreach ( $this->mfields as $key => $value )
		{
			$sql	.= ", md.m_field_id_" . $value['0'] . " AS " . $key;
		}

		$sql	.= " FROM 		exp_members 	AS m 
					 LEFT JOIN 	exp_member_data AS md 
					 ON 		md.member_id 	= m.member_id 
					 LEFT JOIN 	exp_favorites 	AS f 
					 ON 		m.member_id 	= f.member_id 
					 WHERE 		f.site_id 
					 IN 		('" . implode("','", ee()->TMPL->site_ids) . "') 
					 AND 		f.public 		= 'y'";

		// -------------------------------------------
		//  Switch on type
		// -------------------------------------------

		if ( $type == 'member_id' )
		{
			if ( $rank == '' )
			{
				$sql	.= " AND f.type 		= 'member_id' 
							 AND f.author_id 	= '" . ee()->db->escape_str( $this->member_id ) . "'";
			}
		}
		else
		{
			$sql	.= " AND f.type 	= 'entry_id' 
			  			 AND f.entry_id = '" . ee()->db->escape_str( $this->entry_id ) . "'";
		}

		// -------------------------------------------
		//  Allow narcissism?
		// -------------------------------------------

		if ( 
			 ( ee()->TMPL->fetch_param( 'allow_narcissism' ) !== FALSE AND 
			   ! $this->check_yes( ee()->TMPL->fetch_param( 'allow_narcissism' ) ) ) OR 
			 ( ee()->TMPL->fetch_param( 'allow_narcisism' ) !== FALSE AND 
			   ! $this->check_yes( ee()->TMPL->fetch_param( 'allow_narcisism' ) ) ) 
		   )
		{
			$sql	.= " AND f.author_id != f.member_id";
		}

		// -------------------------------------------
		//  Limit by member group
		// -------------------------------------------

		if ( ee()->TMPL->fetch_param('group_id') )
		{
			$sql	.= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param('group_id'), 'm.group_id' );
		}

		// -------------------------------------------
		//  Order by
		// -------------------------------------------

		if ( $type == 'member_id' AND $rank != '' )
		{
			$sql	.= " ORDER BY m.favorites_count_public";
		}
		elseif ( ee()->TMPL->fetch_param('orderby') !== FALSE AND 
				 ee()->TMPL->fetch_param('orderby') != '' )
		{
			if ( isset( $this->mfields[ee()->TMPL->fetch_param('orderby')] ) )
			{
				$sql	.= " ORDER BY md.m_field_id_" . $this->mfields[ee()->TMPL->fetch_param('orderby')]['0'];
			}
			else
			{
				$sql	.= " ORDER BY m." . ee()->TMPL->fetch_param('orderby');
			}
		}
		else
		{
			$sql	.= " ORDER BY m.screen_name";
		}

		// -------------------------------------------
		//  Sort
		// -------------------------------------------

		if ( ee()->TMPL->fetch_param('sort') == 'asc' )
		{
			$sql	.= " ASC";
		}
		else
		{
			$sql	.= " DESC";
		}

		// -------------------------------------------
		//  Limit
		// -------------------------------------------

		if ( is_numeric( ee()->TMPL->fetch_param('limit') ) )
		{
			$sql	.= " LIMIT ".ee()->TMPL->fetch_param('limit');
		}

		// -------------------------------------------
		//  Run query
		// -------------------------------------------

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			return $this->no_results();
		}

		// ----------------------------------------
		//  Parse count
		// ----------------------------------------

		ee()->TMPL->tagdata	= ee()->TMPL->swap_var_single( 'favorites_count', $query->num_rows(), ee()->TMPL->tagdata );

		// ----------------------------------------
		//  Parse list
		// ----------------------------------------

		return $this->_members( $query, $groups );
	}
	//	End members()


	// --------------------------------------------------------------------

	/**
	 * This parses a member list.
	 * 
	 *	@access		public
	 *  @param 		string 	query to check
	 * 	@param		string  groups
	 *	@return		string 	tagdata
	 */
	
	public function _members( $query, $groups = '1' )
	{
		// ----------------------------------------
		//  Set dates
		// ----------------------------------------

		$dates	= array( 
			'join_date', 
			'last_bulletin_date', 
			'last_visit', 
			'last_activity', 
			'last_entry_date', 
			'last_rating_date', 
			'last_comment_date', 
			'last_forum_post_date', 
			'last_email_date' 
		);

		// -------------------------------------------
		//  Parse when we have groups
		// -------------------------------------------

		if ( preg_match( "/" . LD . 'group' . RD . "(.+?)" . LD . preg_quote(T_SLASH, '/') . 
						 "group" . RD . "/s", ee()->TMPL->tagdata, $match ) )
		{
			$chunk	= $match['1'];

			// -------------------------------------------
			//  Convert to array and chunk
			// -------------------------------------------

			$members	= $query->result;

			$members	= array_chunk( $members, ceil( count( $members ) / $groups ) );

			// -------------------------------------------
			//  Parse
			// -------------------------------------------

			$return	= '';

			foreach ( $members as $group )
			{
				$tagdata	= ee()->TMPL->tagdata;
				$r			= '';

				foreach ( $group as $row )
				{
					$c			= $chunk;

					// -------------------------------------------
					//  Conditionals
					// -------------------------------------------

					$cond	= $row;
					$c		= ee()->functions->prep_conditionals( $c, $cond );

					// ----------------------------------------
					//  Parse dates
					// ----------------------------------------

					foreach ($dates as $value)
					{
						if (preg_match("/" . LD . $value . "\s+format=[\"'](.*?)[\"']" . RD . "/s", $c, $m))
						{
							$str	= $m['1'];

							$codes	= ee()->localize->fetch_date_params( $m['1'] );

							foreach ( $codes as $code )
							{
								$str	= str_replace( 
									$code, 
									ee()->localize->convert_timestamp( $code, $row[$value], TRUE ), 
									$str 
								);
							}

							$c	= str_replace( $m['0'], $str, $c );
						}
					}

					// -------------------------------------------
					//  Single vars
					// -------------------------------------------

					foreach ( ee()->TMPL->var_single as $key => $value )
					{
						if ( isset( $row[$key] ) )
						{
							$c	= ee()->TMPL->swap_var_single( $key, $row[$key], $c );
						}
					}

					$r	.= $c;
				}

				$tagdata	= str_replace( $match['0'], $r, $tagdata );

				$return		.= $tagdata;
			}
		}
		else
		{
			$return	= '';

			foreach ( $query->result_array() as $row )
			{
				$tagdata	= ee()->TMPL->tagdata;

				// ----------------------------------------
				//  Parse dates
				// ----------------------------------------

				foreach ($dates as $value)
				{
					if (preg_match("/" . LD . $value . "\s+format=[\"'](.*?)[\"']" . RD . "/s", $tagdata, $match))
					{
						$str	= $match['1'];

						$codes	= ee()->localize->fetch_date_params( $match['1'] );

						foreach ( $codes as $code )
						{
							$str	= str_replace( 
								$code, 
								ee()->localize->convert_timestamp( $code, $row[$value], TRUE ), 
								$str 
							);
						}

						$tagdata	= str_replace( $match['0'], $str, $tagdata );
					}
				}

				// ----------------------------------------
				//  Parse conditionals
				// ----------------------------------------

				$cond		= $row;
				$tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );

				// ----------------------------------------
				//  Parse singles
				// ----------------------------------------

				foreach ( ee()->TMPL->var_single as $key => $value )
				{
					if ( isset( $row[$key] ) )
					{
						$tagdata	= ee()->TMPL->swap_var_single( $key, $row[$key], $tagdata );
					}
					
				}

				$return	.= $tagdata;
			}
		}

		return $return;
	}
	//	End _members()	


	// --------------------------------------------------------------------

	/**
	 * Member rank
	 * 
	 *	@access		public
	 *	@return		string 	tagdata. result of $this->members
	 */
	
	public function member_rank() 
	{ 
		return $this->members( 'member_id', 'yes' ); 
	}
	//	End member_rank()


	// --------------------------------------------------------------------

	/**
	 * rank
	 * 
	 *	@access		public
	 *	@return		string 	tagdata
	 */

	public function rank()
	{
		$entry_id	= '';
		$cat_id		= '';

		$dynamic = ! $this->check_no(ee()->TMPL->fetch_param('dynamic'));

		// -------------------------------------------
		//  Grab entries
		// -------------------------------------------

		$sql	= "FROM  		{$this->sc->db->channel_titles} AS t
				   LEFT JOIN 	{$this->sc->db->channels} as c 
				   ON 			c.{$this->sc->db->channel_id} = t.{$this->sc->db->channel_id}";

        if ( ee()->TMPL->fetch_param('category') OR 
			 ($cat_id != '' AND $dynamic) )
        {
			$sql	.= " LEFT JOIN 	exp_category_posts 
			 			 ON 		t.entry_id = exp_category_posts.entry_id
						 LEFT JOIN 	exp_categories 
						 ON 		exp_category_posts.cat_id = exp_categories.cat_id";
        }

		$sql	.= " WHERE 	t.site_id 
					 IN 	('".implode("','", ee()->TMPL->site_ids)."') ";

		// -------------------------------------------
        //	Yes, One Could Potentially Show Nothing...
		// -------------------------------------------

		if ( ! $this->check_yes( ee()->TMPL->fetch_param('show_unfavorited') ) )
		{
			$sql	.= " AND (t.favorites_count_public != 0 
						 AND t.favorites_count_public != '') ";
		}

		if ( $this->check_no( ee()->TMPL->fetch_param('show_favorites') ) )
		{
			$sql	.= " AND (t.favorites_count_public = 0 OR t.favorites_count_public = '') ";
		}

		if (ee()->TMPL->fetch_param('favorites_start_on') !== FALSE OR 
		    ee()->TMPL->fetch_param('favorites_stop_before') !== FALSE)
		{
			$asql	= "SELECT DISTINCT 	entry_id
					   FROM 			exp_favorites
					   WHERE 			site_id 
					   IN 				('".implode("','", ee()->TMPL->site_ids)."')";

			if (ee()->TMPL->fetch_param('favorites_start_on'))
			{
				$asql .= " AND exp_favorites.entry_date >= '" . 
							ee()->localize->convert_human_date_to_gmt(
								ee()->TMPL->fetch_param('favorites_start_on')
							) . "' ";
			}

			if (ee()->TMPL->fetch_param('favorites_stop_before'))
			{
				$asql .= " AND exp_favorites.entry_date < '" . 
							ee()->localize->convert_human_date_to_gmt(
								ee()->TMPL->fetch_param('favorites_stop_before')
							) . "' ";
			}

			$aquery = ee()->db->query($asql);

			if ($aquery->num_rows() == 0)
			{
				return $this->no_results();
			}

			$entries = array();

			foreach($aquery->result_array() as $row)
			{
				$entries[] = $row['entry_id'];
			}

			$sql .= " AND t.entry_id IN ('" . implode("','", $entries) . "')";

			unset($aquery);
			unset($entries);
		}

		// -------------------------------------------
        //	We only select un-expired entries
		// -------------------------------------------

		$timestamp = (ee()->TMPL->cache_timestamp != '') ? 
					 	ee()->TMPL->cache_timestamp : ee()->localize->now;

        if ( ! $this->check_yes( ee()->TMPL->fetch_param('show_future_entries') ) )
        {
			$sql .= " AND t.entry_date < ".$timestamp." ";
        }

        if ( ! $this->check_yes( ee()->TMPL->fetch_param('show_expired') ) )
        {
			$sql .= " AND (t.expiration_date = 0 || t.expiration_date > " . $timestamp . ") ";
        }

		// -------------------------------------------
        // Limit to/exclude specific weblogs
		// -------------------------------------------

		if ($channel = ee()->TMPL->fetch_param($this->sc->channel))
		{
			$xql = "SELECT 	{$this->sc->db->channel_id} 
				    FROM 	{$this->sc->db->channels} 
					WHERE 	site_id 
					IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "') ";

			$xql .= ee()->functions->sql_andor_string($channel, $this->sc->db->channel_name);

			$query = ee()->db->query($xql);
			
			if ($query->num_rows() == 0)
			{
				return $this->no_results();
			}
			else
			{
				if ($query->num_rows() == 1)
				{
					$sql .= "AND t.{$this->sc->db->channel_id} = '" . $query->row($this->sc->db->channel_id) . "' ";
				}
				else
				{
					$sql .= "AND (";

					foreach ($query->result_array() as $row)
					{
						$sql .= "t.{$this->sc->db->channel_id} = '" . $row[$this->sc->db->channel_id] . "' OR ";
					}

					$sql = substr($sql, 0, - 3);

					$sql .= ") ";
				}
			}
		}

		// -------------------------------------------
        //  Limit query by category
		// -------------------------------------------

        if (ee()->TMPL->fetch_param('category'))
        {
            $sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('category'), 'exp_categories.cat_id')." ";
        }
        else
        {
            if ($cat_id != '' AND $dynamic)
            {
                $sql .= " AND exp_categories.cat_id = '".ee()->db->escape_str($cat_id)."' ";
            }
        }

		// -------------------------------------------
        //	Add status declaration
		// -------------------------------------------

        if ($status = ee()->TMPL->fetch_param('status'))
        {
			$status = str_replace('Open',   'open',   $status);
			$status = str_replace('Closed', 'closed', $status);

			$sstr = ee()->functions->sql_andor_string($status, 't.status');

			if ( ! stristr($sstr, "'closed'") )
			{
				$sstr .= " AND t.status != 'closed' ";
			}

			$sql .= $sstr;
        }
        else
        {
            $sql .= "AND t.status = 'open' ";
        }

		// -------------------------------------------
        //	Limit by number of hours
		// -------------------------------------------

		if ( $days = ee()->TMPL->fetch_param('hours') )
		{
			$time	= ee()->localize->now - ( $days * 60 * 60 );
			$sql	.= " AND t.entry_date > $time";
		}

		// -------------------------------------------
        //	Order by
		// -------------------------------------------

		if (ee()->TMPL->fetch_param('orderby') == 'random')
		{
			$sql	.= " ORDER BY rand()";
		}
		else
		{
			$sql	.= " ORDER BY count DESC";
		}

		// ----------------------------------------
        //  Pagination!
        // ----------------------------------------

        if ( is_numeric( ee()->TMPL->fetch_param('limit') ) )
		{
			$this->p_limit = ee()->TMPL->fetch_param('limit');
		}


		// ----------------------------------------
        //  Favorites Date Required for Ordering?
        // ----------------------------------------

        $sort = (in_array(strtoupper(ee()->TMPL->fetch_param('sort')), array('DESC', 'ASC'))) ? 
						strtoupper(ee()->TMPL->fetch_param('sort')) : 'DESC';

		if (ee()->TMPL->fetch_param('orderby') == 'favorites_date')
        {
        	$sql = preg_replace(
				"/ORDER BY.+?(LIMIT|$)/is",
        		"ORDER BY favorites_date " . $sort . ' \1',
        		$sql
			);

        	$ugh = ( ! $this->check_yes( ee()->TMPL->fetch_param('show_unfavorited') ) ) ? 'INNER' : 'LEFT';

        	$sql = preg_replace(
				"/LEFT JOIN\s+{$this->sc->db->channels}/is",
        		"{$ugh} JOIN 	exp_favorites AS f 
				 ON 			(t.entry_id = f.entry_id
        		 AND 			f.favorites_id 
				 IN				(SELECT MAX(favorites_id) FROM exp_favorites GROUP BY entry_id))
        		 LEFT JOIN 		{$this->sc->db->channels}",
        		$sql
			);
        }

		// -------------------------------------------
        //	Run query
		// -------------------------------------------

		$orderby = ( ee()->TMPL->fetch_param('orderby') != '' ) ? 
						ee()->TMPL->fetch_param('orderby') : 'count';

		if ( $orderby == 'favorites_date')
		{
			$query	= ee()->db->query( 
				'SELECT t.entry_id, 
						f.entry_date AS favorites_date, 
						t.favorites_count_public AS count ' . $sql 
			);
		}
		else
		{
			$query	= ee()->db->query( 
				'SELECT t.entry_id, 
						t.favorites_count_public AS count ' . $sql 
			);
		}

		// -------------------------------------------
		//	Create entries array
		// -------------------------------------------

		$entries = array();

		if ( $query->num_rows() == 0 )
		{
			return $this->no_results();
		}
		else
		{
			foreach ( $query->result_array() as $row )
			{
				$entries[]	= $row['entry_id'];
			}

			// -------------------------------------------
			//  Pass params
			// -------------------------------------------

			ee()->TMPL->tagparams['entry_id']		= implode( "|", $entries );

			if ( $orderby == 'favorites_date' OR $orderby == 'count' )
			{

				if ( $sort == 'ASC' )
				{
					$entries = array_reverse( $entries );	
				} 
	
				ee()->TMPL->tagparams['fixed_order']	= implode( "|", $entries );
			}

			
        	ee()->TMPL->tagparams['sort'] = $sort;
		}

		// -------------------------------------------
		//  Invoke weblog class
		// -------------------------------------------

		if (APP_VER < 2.0)
		{
			if ( ! class_exists('Weblog') )
			{
				require PATH_MOD.'/weblog/mod.weblog'.EXT;
			}
		
			$channel = new Weblog;
		}
		else
		{
			if ( ! class_exists('Channel') )
			{
				require PATH_MOD.'/channel/mod.channel'.EXT;
			}
	
			$channel = new Channel;
		}
		
		// --------------------------------------------
        //  Invoke Pagination for EE 2.4 and Above
        // --------------------------------------------

		if (APP_VER >= '2.4.0')
		{
			ee()->load->library('pagination');
			$channel->pagination = new Pagination_object('Channel');
			
			// Used by pagination to determine whether we're coming from the cache
			$channel->pagination->dynamic_sql = FALSE;
		}

		// ----------------------------------------
		//  Pre-process related data
		// ----------------------------------------
		//	TMPL class is coded so that only
		//	one method in the weblog class and one
		//	method in the search class are allowed
		//	to parse related entries tags. This is
		//	no doubt for performance reasons.
		// ----------------------------------------

		ee()->TMPL->tagdata		= ee()->TMPL->assign_relationship_data( ee()->TMPL->tagdata );

		ee()->TMPL->var_single	= array_merge( ee()->TMPL->var_single, ee()->TMPL->related_markers );

		// ----------------------------------------
		//  Execute needed methods
		// ----------------------------------------

		if (APP_VER < 2.0)
		{
        	$channel->fetch_custom_weblog_fields();
		}
		else
		{
			$channel->fetch_custom_channel_fields();
		}

        $channel->fetch_custom_member_fields();

		// --------------------------------------------
        //  Pagination Tags Parsed Out
        // --------------------------------------------
		
		if (APP_VER >= '2.4.0')
		{
			$channel->pagination->get_template();
		}
		else
		{
			$channel->fetch_pagination_data();
		}

        //	----------------------------------------
		//	 Build Weblog Data Query
		//	----------------------------------------

        $channel->build_sql_query();
        
        // --------------------------------------------
        //  Transfer Pagination Variables Over to Channel object
        //	- Has to go after the building of the query as EE 2.4 does its Pagination work in there
        // --------------------------------------------
        
        if (APP_VER >= '2.4.0')
		{
			$transfer = array(	'paginate'		=> 'paginate',
								'total_pages' 	=> 'total_pages',
								'current_page'	=> 'current_page',
								'offset'		=> 'offset',
								'page_next'		=> 'page_next',
								'page_previous'	=> 'page_previous',
								'page_links'	=> 'pagination_links', // different!
								'total_rows'	=> 'total_rows',
								'per_page'		=> 'per_page',
								'per_page'		=> 'p_limit',
								'offset'		=> 'p_page');
								
			foreach($transfer as $from => $to)
			{
				$channel->$to = $channel->pagination->$from;
			}
		}

		//	----------------------------------------
		//	Empty?
		//	----------------------------------------

        if( trim($channel->sql) == '' )
        {	
        	if ($this->check_yes(ee()->TMPL->fetch_param('favorites_count')))
	        {

	        	return $this->return_data = str_replace( LD . 'favorites_count' . RD, '0', ee()->TMPL->tagdata);
	        }
	        else
	        {
	            return $this->no_results();
	        }
        }

        // ----------------------------------------
        //  Pagination
        // ----------------------------------------

		$query = ee()->db->query(
			preg_replace(
				"/SELECT(.*?)\s+FROM\s+/is", 
				'SELECT COUNT(*) AS count FROM ', 
				$channel->sql
			)
		);

		$this->total_rows = $query->row('count');

		//pagination request but no entries?
		if ( $query->row('count') == 0 AND 
			 strpos( ee()->TMPL->tagdata, 'paginate' ) !== FALSE )
		{
			return $this->no_results();
		}

		//$sql_remove = 'SELECT t.entry_id ';

		//get pagination info
		$pagination_data = $this->universal_pagination(array(
			'sql'					=> $channel->sql, 
			'total_results'			=> $this->total_rows, 
			'tagdata'				=> ee()->TMPL->tagdata,
			'limit'					=> $this->p_limit,
			'uri_string'			=> ee()->uri->uri_string,
			'current_page'			=> $this->current_page,
		));

		//if we paginated, sort the data
		if ($pagination_data['paginate'] === TRUE)
		{
			$this->paginate			= $pagination_data['paginate'];
			$this->page_next		= $pagination_data['page_next']; 
			$this->page_previous	= $pagination_data['page_previous'];
			$this->p_page			= $pagination_data['pagination_page'];
			$this->current_page		= $pagination_data['current_page'];
			$channel->sql			= str_replace($sql_remove, '', $pagination_data['sql']);
			$this->pagination_links = $pagination_data['pagination_links'];
			$this->basepath			= $pagination_data['base_url'];
			$this->total_pages		= $pagination_data['total_pages'];
			$this->paginate_data	= $pagination_data['paginate_tagpair_data'];
			ee()->TMPL->tagdata		= $pagination_data['tagdata'];
		}
		//else we limit the data still? Need to check on this...
		else
		{
			$channel->sql .= " LIMIT " . $this->p_limit;
		}

        //	----------------------------------------
		//	 Favorites Specific Rewrites!
		//	----------------------------------------

        if ($this->check_yes(ee()->TMPL->fetch_param('favorites_count')))
        {
        	/*$query = ee()->db->query(
				preg_replace(
					"/SELECT(.*?)\s+FROM\s+/is", 
					'SELECT COUNT(*) AS count FROM ', 
					$channel->sql
				)
			);*/

        	return $this->return_data = str_replace( LD . 'favorites_count' . RD, $this->total_rows, ee()->TMPL->tagdata);
        }

        //	----------------------------------------
		//	 Favorites date
		//	----------------------------------------

        if (stristr(ee()->TMPL->tagdata, LD.'favorites_date ') OR ee()->TMPL->fetch_param('orderby') == 'favorites_date')
        {
        	$channel->favorites_date = TRUE;

        	$channel->sql = preg_replace(
				"/\s+FROM\s+/s",
        		", f.entry_date AS favorites_date FROM ",
        		ltrim($channel->sql)
			);

        	$channel->sql = preg_replace(
				"/LEFT JOIN\s+{$this->sc->db->channels}/is",
				"LEFT JOIN 	exp_favorites AS f 
				 ON 		(t.entry_id = f.entry_id
				 AND 		f.favorites_id 
				 IN 		(SELECT MAX(favorites_id) FROM exp_favorites GROUP BY entry_id))
        		 LEFT JOIN 	{$this->sc->db->channels}",
        		$channel->sql
			);
        }

        $channel->query = ee()->db->query($channel->sql);

		if (APP_VER < 2.0)
		{
			$channel->query->result	= $channel->query->result_array();
		}

		//	----------------------------------------
		//	Empty?
		//	----------------------------------------
		
        if ( ! isset( $channel->query ) OR 
			 $channel->query->num_rows() == 0 )
        {
            return $this->no_results();
        }

		//	----------------------------------------
		//	typography
		//	----------------------------------------

        if (APP_VER < 2.0)
        {
        	if ( ! class_exists('Typography'))
			{
				require PATH_CORE.'core.typography'.EXT;
			}
					
			$channel->TYPE = new Typography;
			$channel->TYPE->convert_curly = FALSE;
        }
        else
        {
			ee()->load->library('typography');
			ee()->typography->initialize();
			ee()->typography->convert_curly = FALSE;
		}
		
        $channel->fetch_categories();

		// ----------------------------------------
		//  Parse and return entry data
		// ----------------------------------------

		if (APP_VER < 2.0)
		{
        	$channel->parse_weblog_entries();
		}
		else
		{
			$channel->parse_channel_entries();
		}
		
		// --------------------------------------------
        //  Render the Pagination Data
        // --------------------------------------------
		
		if (APP_VER >= '2.4.0')
		{
			$channel->return_data = $channel->pagination->render($channel->return_data);
		}
		else
		{
			$channel->add_pagination_data();
		}
		
		// --------------------------------------------
        //  Reverse and Related Entries
        // --------------------------------------------

		if (count(ee()->TMPL->related_data) > 0 AND count($channel->related_entries) > 0)
		{
			$channel->parse_related_entries();
		}

		if (count(ee()->TMPL->reverse_related_data) > 0 AND count($channel->reverse_related_entries) > 0)
		{
			$channel->parse_reverse_related_entries();
		}

		// ----------------------------------------
		//  Handle problem with pagination segments
		//	in the url
		// ----------------------------------------

		if ( preg_match("#(/P\d+)#", ee()->uri->uri_string, $match) )
		{
			$channel->return_data	= str_replace( $match['1'], "", $channel->return_data );
		}
		elseif ( preg_match("#(P\d+)#", ee()->uri->uri_string, $match) )
		{
			$channel->return_data	= str_replace( $match['1'], "", $channel->return_data );
		}
		
		// ----------------------------------------
		//  Pagination Replace
		// ----------------------------------------

		if ($this->paginate == TRUE)
        {
			$this->paginate_data = str_replace(LD . 'current_page'		. RD, $this->current_page, 		$this->paginate_data);
			$this->paginate_data = str_replace(LD . 'total_pages' 	   	. RD, $this->total_pages, 		$this->paginate_data);
			$this->paginate_data = str_replace(LD . 'pagination_links' 	. RD, $this->pagination_links, 	$this->paginate_data);

        	if (preg_match("/" . LD . "if previous_page" . RD . "(.+?)" . LD . 
						   preg_quote(T_SLASH, '/') . "if" . RD . "/s", $this->paginate_data, $match))
        	{
        		if ($this->page_previous == '')
        		{
        			$this->paginate_data = preg_replace(
						"/" . LD . "if previous_page" . RD . ".+?" . LD . preg_quote(T_SLASH, '/') . "if" . RD . "/s", 
						'', 
						$this->paginate_data
					);
        		}
        		else
        		{
					$match['1'] = preg_replace("/" . LD . 'path.*?' . RD . "/", 	$this->page_previous, $match['1']);
					$match['1'] = preg_replace("/" . LD . 'auto_path' . RD . "/",	$this->page_previous, $match['1']);

					$this->paginate_data = str_replace($match['0'],	$match['1'], $this->paginate_data);
				}
       	 	}


        	if (preg_match(
					"/" . LD . "if next_page" . RD . "(.+?)" . LD . preg_quote(T_SLASH, '/') . "if" . RD . "/s", 
					$this->paginate_data, 
					$match
				))
        	{
        		if ($this->page_next == '')
        		{
        			$this->paginate_data = preg_replace(
						"/" . LD . "if next_page" . RD . ".+?" . LD . preg_quote(T_SLASH, '/') . "if" . RD . "/s", 
						'', 
						$this->paginate_data
					);
        		}
        		else
        		{
					$match['1'] = preg_replace("/" . LD . 'path.*?' . RD . "/", 	$this->page_next, $match['1']);
					$match['1'] = preg_replace("/" . LD . 'auto_path' . RD . "/",	$this->page_next, $match['1']);

					$this->paginate_data = str_replace($match['0'],	$match['1'], $this->paginate_data);
				}
        	}

			$position = ( ! ee()->TMPL->fetch_param('paginate')) ? '' : ee()->TMPL->fetch_param('paginate');

			switch ($position)
			{
				case "top"	: $channel->return_data  = $this->paginate_data . $channel->return_data;
					break;
				case "both"	: $channel->return_data  = $this->paginate_data . $channel->return_data . $this->paginate_data;
					break;
				default		: $channel->return_data .= $this->paginate_data;
					break;
			}
        }

        $tagdata = $channel->return_data;

        return $tagdata;
	}
	//	End rank()				


	// --------------------------------------------------------------------

	/**
	 * shared
	 * 
	 *	@access		public
	 *	@return		string 	tagdata
	 */

	public function shared()
	{
		$member_id	= array();
		$qstring	= '';
		$cat_id		= '';
		$year		= '';

		// -------------------------------------------
		//  Entry Id
		// -------------------------------------------

		if ( $this->_entry_id() === FALSE )
		{
			return $this->no_results();
		}

		// -------------------------------------------
		//  Grab members
		// -------------------------------------------

		$sql	= "SELECT 		f.member_id
				   FROM 		exp_favorites AS f
				   LEFT JOIN 	{$this->sc->db->channel_titles} AS t 
				   ON 			t.entry_id = f.entry_id
				   WHERE 		f.site_id 
				   IN 			('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
				   AND 			t.entry_id = '" . ee()->db->escape_str($this->entry_id) . "'
				   AND 			t.title IS NOT NULL";

		$query	= ee()->db->query( $sql );

		if ( $query->num_rows() == 0 )
		{
			return $this->no_results();
		}

		foreach ( $query->result_array() as $row )
		{
			$member_id[] = $row['member_id'];
		}

		// -------------------------------------------
		//  Grab member entries
		// -------------------------------------------

		$sql		= "SELECT 		f.entry_id, COUNT(f.entry_id) AS count 
					   FROM 		exp_favorites AS f
					   LEFT JOIN 	{$this->sc->db->channel_titles} AS t 
					   ON 			t.entry_id = f.entry_id
					   WHERE 		f.site_id 
					   IN 			('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "')
					   AND 			f.public = 'y'
					   AND 			t.title IS NOT NULL ";

		if ( ee()->session->userdata['member_id'] != '0' )
		{
			$sql	.= " AND f.member_id != '" . ee()->db->escape_str(ee()->session->userdata['member_id']) . "'";
		}

		if (count($member_id) > 0)
		{
			$sql		.= " AND f.member_id IN ('" . implode("','", ee()->db->escape_str($member_id)) . "')";
		}

		$sql		.= " AND t.entry_id != '" . ee()->db->escape_str($this->entry_id) . "'";

		$sql		.= " GROUP BY entry_id ORDER BY count DESC";

		$query		= ee()->db->query( $sql );

		$this->entry_id	= '';

		if ( $query->num_rows() == 0 )
		{
			return $this->no_results();
		}

		foreach ( $query->result_array() as $row )
		{
			$this->entry_id	.= $row['entry_id'].'|';
		}

		ee()->TMPL->tagparams['dynamic'] = 'off';

		//	----------------------------------------
		//	Parse and return
		//	----------------------------------------

        return $this->_entries();
	}
	//	End shared()


	// --------------------------------------------------------------------

	/**
	 *  Show favorite entries (deprecated)
	 * 
	 *	@access		public
	 *	@return		string 	tagdata, result of $this->entries();
	 */

	public function show_favorite_entries()
	{
		return $this->entries();
	}
	// End show_favorite_entries()


	// --------------------------------------------------------------------

	/**
	 *  Show Other People's Favorites (Deprecated)
	 * 
	 *	@access		public
	 *	@return		string 	tagdata, result of $this->entries();
	 */

	public function show_others_favorites()
	{
		return $this->entries();
	}
	// End show_others_favorites()


	// --------------------------------------------------------------------

	/**
	 *  _entry_id
	 * 
	 *	@access		public
	 *  @param		string	type
	 *	@return		bool 	id type found and set to $this->$type
	 */

    public function _entry_id( $type = 'entry_id' )
    {
    	if ( $this->$type != '' ) 
		{
			return TRUE;
		}

		$cat_segment	= ee()->config->item("reserved_category_word");

		// --------------------------------------
		//  Set Via Parameter
		// --------------------------------------

		if ( $this->_numeric( trim( ee()->TMPL->fetch_param( $type ) ) ) === TRUE )
		{
			$this->$type	= trim( ee()->TMPL->fetch_param( $type ) );

			return TRUE;
		}


		// --------------------------------------
		//  Set Via the url_title parameter
		// --------------------------------------

		if( ee()->TMPL->fetch_param( 'url_title' ) != '' )
		{
			$sql	= "SELECT 	{$this->sc->db->channel_titles}.entry_id
					   FROM   	{$this->sc->db->channel_titles}, {$this->sc->db->channels}
					   WHERE  	{$this->sc->db->channel_titles}.{$this->sc->db->channel_id} = " .		
									"{$this->sc->db->channels}.{$this->sc->db->channel_id}
					   AND    	{$this->sc->db->channel_titles}.url_title = '" . ee()->db->escape_str(ee()->TMPL->fetch_param('url_title') ) . "'
					   AND	  	{$this->sc->db->channels}.site_id 
					   IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "') ";

			if (ee()->TMPL->fetch_param($this->sc->channel) !== FALSE)
			{
				$sql .= ee()->functions->sql_andor_string(
					ee()->TMPL->fetch_param($this->sc->channel), 
					$this->sc->db->channel_name, 
					$this->sc->db->channels
				);
			}

			$query	= ee()->db->query($sql);

			if ( $query->num_rows() > 0 )
			{
				$this->entry_id = $query->row('entry_id');
				
				return TRUE;
			}
		}

		// --------------------------------------
		//  Found in the URI
		// --------------------------------------

		$qstring	= ( ee()->uri->page_query_string != '' ) ? 
						ee()->uri->page_query_string : ee()->uri->query_string;
		$dynamic	= ! $this->check_no( ee()->TMPL->fetch_param('dynamic') );

		// -------------------------------------------
		//  Do we have a pure ID number?
		// -------------------------------------------

		if ( $this->_numeric( $qstring ) === TRUE )
		{
			$this->$type	= $qstring;

			return TRUE;
		}
		elseif ($dynamic === TRUE)
		{
			// --------------------------------------
			//  Remove day
			// --------------------------------------

			if (preg_match("#\d{4}/\d{2}/(\d{2})#", $qstring, $match))
			{
				$partial = substr($match['0'], 0, -3);

				$qstring = trim_slashes(str_replace($match['0'], $partial, $qstring));
			}

			// --------------------------------------
			//  Remove /year/month/
			// --------------------------------------

			// added (^|\/) to make sure this doesn't trigger with url titles like big_party_2006
			if (preg_match("#(^|\/)(\d{4}/\d{2})#", $qstring, $match))
			{
				$qstring = trim_slashes(str_replace($match['2'], '', $qstring));
			}

			// --------------------------------------
			//  Remove ID indicator
			// --------------------------------------

			if (preg_match("#^(\d+)(.*)#", $qstring, $match))
			{
				$seg = ( ! isset($match['2'])) ? '' : $match['2'];

				if (substr($seg, 0, 1) == "/" OR $seg == '')
				{
					$this->entry_id = $match['1'];

					return TRUE;
				}
			}

			// --------------------------------------
			//  Remove page number
			// --------------------------------------

			if (preg_match("#^P(\d+)|/P(\d+)#", $qstring, $match) AND $dynamic)
			{
				$qstring = trim_slashes(str_replace($match['0'], '', $qstring));
			}

			// --------------------------------------
			//  Parse category indicator
			// --------------------------------------

			// Text version of the category

			if ( $qstring != '' AND 
				 $this->reserved_cat_segment != '' AND 
				 in_array($this->reserved_cat_segment, explode("/", $qstring)) AND 
				 ee()->TMPL->fetch_param($this->sc->channel))
			{
				$qstring = preg_replace("/(.*?)" . preg_quote($this->reserved_cat_segment) . "\//i", '', $qstring);
			}

			// Numeric version of the category

			if (preg_match("#(^|\/)C(\d+)#", $qstring, $match))
			{
				$qstring = trim_slashes(str_replace($match['0'], '', $qstring));
			}

			// --------------------------------------
			//  Remove "N"
			// --------------------------------------

			// The recent comments feature uses "N" as the URL indicator
			// It needs to be removed if present

			if (preg_match("#^N(\d+)|/N(\d+)#", $qstring, $match))
			{
				$qstring = trim_slashes(str_replace($match['0'], '', $qstring));
			}

			// ----------------------------------------
			//  Remove 'delete' and 'private'
			// ----------------------------------------

			$qstring	= trim_slashes( str_replace( array('delete', 'private'), array( '','' ), $qstring) );

			// ----------------------------------------
			//  Try numeric id again
			// ----------------------------------------

			if ( preg_match( "/^(\d+)$/", $qstring, $match ) )
			{
				$this->$type = $match['1'];

				return TRUE;
			}

			// ----------------------------------------
			//  Parse URL title or username
			// ----------------------------------------

			if ( $type == 'member_id' )
			{
				// ----------------------------------------
				//  Parse username
				// ----------------------------------------

				if (strstr($qstring, '/'))
				{
					$xe			= explode('/', $qstring);
					$qstring	= current($xe);
				}

				$sql	= "SELECT 	member_id 
						   FROM 	exp_members 
						   WHERE 	username = '" . ee()->db->escape_str($qstring) . "'";

				$query	= ee()->db->query($sql);

				if ( $query->num_rows() > 0 )
				{
					$this->member_id = $query->row('member_id');

					return TRUE;
				}
			}
			else
			{
				// ----------------------------------------
				//  Parse URL title
				// ----------------------------------------

				if (strstr($qstring, '/'))
				{
					$xe			= explode('/', $qstring);
					$qstring	= current($xe);
				}

				$sql	= "SELECT 	{$this->sc->db->channel_titles}.entry_id
						   FROM   	{$this->sc->db->channel_titles}, {$this->sc->db->channels}
						   WHERE  	{$this->sc->db->channel_titles}.{$this->sc->db->channel_id} = " .		
										"{$this->sc->db->channels}.{$this->sc->db->channel_id}
						   AND    	{$this->sc->db->channel_titles}.url_title = '" . ee()->db->escape_str($qstring) . "'
						   AND	  	{$this->sc->db->channels}.site_id 
						   IN 		('" . implode("','", ee()->db->escape_str(ee()->TMPL->site_ids)) . "') ";

				if (ee()->TMPL->fetch_param($this->sc->channel) !== FALSE)
				{
					$sql .= ee()->functions->sql_andor_string(
						ee()->TMPL->fetch_param($this->sc->channel), 
						$this->sc->db->channel_name, 
						$this->sc->db->channels
					);
				}

				$query	= ee()->db->query($sql);

				if ( $query->num_rows() > 0 )
				{
					$this->entry_id = $query->row('entry_id');

					return TRUE;
				}
			}
		}

		return FALSE;
	}
	//	End _entry_id()

			
	//-----------------------------------------------------------------------------------------------------------

	// --------------------------------------------------------------------

	/**
	 *  _numeric
	 * 
	 *	@access		public
	 *  @param		string	string to check for number
	 *	@return		bool 	is numeric	
	 */

    public function _numeric ( $str = '' )
    {
    	if ( $str == '' OR preg_match( '/[^0-9]/', $str ) != 0 )
    	{
    		return FALSE;
    	}

    	return TRUE;
    }
    // END _numeric() 


	// --------------------------------------------------------------------

	/**
	 *  Fetch custom member field IDs
	 * 
	 *	@access		public
	 *	@return		null	
	 */

    public function _fetch_custom_member_fields()
    {
        $query = ee()->db->query(
			"SELECT m_field_id, m_field_name, m_field_fmt 
			 FROM exp_member_fields"
		);

        if ( $query->num_rows() > 0 )
        {
			foreach ($query->result_array() as $row)
			{
				$this->mfields[$row['m_field_name']] = array($row['m_field_id'], $row['m_field_fmt']);
			}
        }
    }

    //	End  _fetch_custom_member_fields()


	// --------------------------------------------------------------------

	/**
	 *  Update last activity
	 * 
	 *	@access		public
	 *	@return		bool	has been updated	
	 */

    public function _update_last_activity()
    {
		if ( ee()->session->userdata('member_id') == 0 )
		{
			return FALSE;
		}

    	return ee()->db->query( 
			ee()->db->update_string( 
				'exp_members', 
				array( 'last_activity' 	=> ee()->localize->now ), 
				array( 'member_id' 		=> ee()->session->userdata('member_id') ) 
			) 
		);
    }
    //	End _update_last_activity()
    	

	// --------------------------------------------------------------------
	
}
// END CLASS Favorites