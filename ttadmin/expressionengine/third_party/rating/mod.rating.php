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
 * Rating - User Side
 *
 * @package 	Solspace:Rating
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/rating/mod.rating.php
 */
 
if ( ! defined('APP_VER')) define('APP_VER', '2.0'); // EE 2.0's Wizard doesn't like CONSTANTs

require_once 'addon_builder/module_builder.php';

class Rating extends Module_builder_rating 
{

	public $return_data		= '';
	public $disabled		= FALSE;
	public $fields_numeric	= FALSE;
	
	protected $params		= array();
	
	protected $entry_id		= '';
	protected $channel_id	= '';
	protected $rating_id	= '';
	
	public $basepath				= '';
	public $current_page			= 0;
	public $limit					= 100;
	public $total_pages				= 0;
	public $total_results			= 0;
	public $page_count				= '';
	public $page_next				= '';
	public $page_previous			= '';
	public $pager					= '';
	public $paginate				= FALSE;
	public $paginate_match			= array();
	public $paginate_data			= '';
	public $res_page				= '';
	
	public $mfields					= array();

    // --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */
	 
	public function __construct()
	{	
		parent::Module_builder_rating('rating');
		
		//load helpers for everything
		ee()->load->helper(array('text', 'form', 'url', 'security', 'string'));
        
        // -------------------------------------
		//  Module Installed and Up to Date?
		// -------------------------------------
		
		if ($this->database_version() == FALSE OR 
			$this->version_compare($this->database_version(), '<', RATING_VERSION) 
			OR ! $this->extensions_enabled())
		{
			$this->disabled = TRUE;
			
			trigger_error(ee()->lang->line('rating_module_disabled'), E_USER_NOTICE);
		}
		
		// --------------------------------------------
        //	Legacy form_name="" parameter maps to collection=""
        // --------------------------------------------
        
        if (isset(ee()->TMPL) && is_object(ee()->TMPL) && isset(ee()->TMPL->tagparams['form_name']))
        {
        	ee()->TMPL->tagparams['collection'] = ee()->TMPL->tagparams['form_name'];
        }
		
	}
	// END Rating()
	
	
	// --------------------------------------------------------------------

	/**
	 *	Rating Stats tag
	 *
	 *	http://www.solspace.com/docs/detail/rating_stats/
	 *
	 *	@access		public
	 *	@return		string
	 */

	public function stats()
	{
		// ----------------------------------------
		//	Grab entry id
		// ----------------------------------------
		
		if ( ! $this->entry_id() )
		{
			return $this->no_results();
		}

		// ----------------------------------------
		// Make sure the entry has been rated
		// ----------------------------------------
		
		/*
		$query	= ee()->db->query("SELECT COUNT(*) AS count FROM exp_rating_stats
								   WHERE entry_id = '".ee()->db->escape_str($this->entry_id)."'");

		if ($query->row('count') == 0 )
		{
			return $this->no_results();
		}
		*/
		
		// ----------------------------------------
		//	Parse and return
		// ----------------------------------------
		
		return $this->parse_rating_stats();
	}
	//	End stats


	// --------------------------------------------------------------------

	/**
	 *	Rating:Count tag
	 *
	 *	http://www.solspace.com/docs/detail/rating_count/
	 *
	 *	@access		public
	 *	@return		string
	 */

	public function count()
	{
		// ----------------------------------------
		//	Grab entry id
		// ----------------------------------------

		if ( ! $this->entry_id() )
		{
			return $this->no_results();
		}

		// ----------------------------------------
		//	Get count
		// ----------------------------------------

		$query	= ee()->db->query( "SELECT count FROM exp_rating_stats
									WHERE entry_id = '".ee()->db->escape_str($this->entry_id)."' LIMIT 1");

		// ----------------------------------------
		//	Conditionals
		// ----------------------------------------

		$cond['count'] = ($query->num_rows() == 0) ? 0 : $query->row('count');

		$tagdata = ee()->functions->prep_conditionals( ee()->TMPL->tagdata, $cond );

		// ----------------------------------------
		//	Parse
		// ----------------------------------------

		return str_replace( LD."rating_count".RD, $cond['count'], $tagdata );
	}
	// END count()


	// --------------------------------------------------------------------

	/**
	 *	Myentries for Ratings - LEGACY
	 *
	 *	http://www.solspace.com/docs/detail/rating_entries/
	 *
	 *	@access		public
	 *	@return		string
	 */

	public function myentries()
	{
		return $this->entries();
	}
	// End

	// --------------------------------------------------------------------

	/**
	 *	Rating Entries tag
	 *
	 *	http://www.solspace.com/docs/detail/rating_entries/
	 *
	 *	@access		public
	 *	@return		string
	 */

	public function entries()
	{
		$qstring	= ee()->uri->query_string;

		$total		= array();
		$return		= '';

		// ----------------------------------------
		// Fetch the rating language pack
		// ----------------------------------------

		ee()->lang->loadfile('rating');

		// ----------------------------------------
		//	Set the decimal precision and format, as well as get the offset and limit
		// ----------------------------------------

		$precision		= ( ee()->TMPL->fetch_param('precision') ) ? ee()->TMPL->fetch_param('precision'): '0';
		$g_precision	= ($precision > 0 ) ? 2: 0;
		$thousands		= ( ee()->TMPL->fetch_param('thousands') ) ? ee()->TMPL->fetch_param('thousands'): ',';
		$fractions		= ( ee()->TMPL->fetch_param('fractions') ) ? ee()->TMPL->fetch_param('fractions'): '.';
		$offset 		= ( ee()->TMPL->fetch_param('offset') OR ctype_digit(ee()->TMPL->fetch_param('offset'))) ? ee()->TMPL->fetch_param('offset'): 0;
		$this->limit 	= ( ee()->TMPL->fetch_param('limit') && ctype_digit(ee()->TMPL->fetch_param('limit'))) ? ee()->TMPL->fetch_param('limit'): $this->limit;

		// ----------------------------------------
		//	Form name
		// ----------------------------------------

		$collection = ( ee()->TMPL->fetch_param('collection') ) ? ee()->TMPL->fetch_param('collection') : 'all';

		// ----------------------------------------
		//	Rating author id
		// ----------------------------------------

		$rating_author_id		= ( ee()->TMPL->fetch_param('rating_author_id') !== FALSE )		  ? ee()->TMPL->fetch_param('rating_author_id') : '';
		$rating_author_username	= ( ee()->TMPL->fetch_param('rating_author_username') !== FALSE ) ? ee()->TMPL->fetch_param('rating_author_username') : '';
		
		// ----------------------------------------
		//	Get average rating parameter - Not found in docs, so removed.
		// ----------------------------------------
/*
		$avg_rating = FALSE;

		if (ee()->TMPL->fetch_param('average_rating') !== FALSE)
		{
			preg_match('#([\D^ ]{0,2} ?)?(\d{1,}(?:\.\d{1,})?)(?: ([\D^ ]{1,2}) ?(\d{1,}(?:\.\d{1,})?))?#', ee()->TMPL->fetch_param('average_rating'), $matches);
			
			if (isset($matches[1], $matches[2]))
			{
				$avg_rating = $matches[1] . '"' . $matches[2] . '"';
				
				if (isset($matches[3], $matches[4]))
				{
					$avg_rating .= ' AND s.avg ' . $matches[3] . ' "' . $matches[4] . '"';
				}
			}
			elseif (isset($matches[2]))
			{
				$avg_rating = '= "' . $matches[2] . '"';
			}
		}
*/
		// ----------------------------------------
		//	Get timeframe parameters
		// ----------------------------------------

		$timeframe_start	= ( ee()->TMPL->fetch_param('timeframe_start') ) ? strtotime( ee()->TMPL->fetch_param('timeframe_start') ) : FALSE;
		$timeframe_end		= ( ee()->TMPL->fetch_param('timeframe_end') ) ? strtotime( ee()->TMPL->fetch_param('timeframe_end') ) : FALSE;

		// ----------------------------------------
		//	Get entries
		// ----------------------------------------
		
		if ( ee()->TMPL->fetch_param('distinct_entries') !== FALSE AND ee()->TMPL->fetch_param('distinct_entries') == 'yes' )
		{
			$sql = "SELECT DISTINCT r.rating_id AS id, r.entry_id FROM exp_ratings AS r";
		}
		else
		{
			$sql = "SELECT r.rating_id AS id, r.entry_id FROM exp_ratings AS r";
		}
		
		if ( ee()->TMPL->fetch_param('category') )
		{
			$sql	.= " LEFT JOIN exp_category_posts AS cp ON cp.entry_id = r.entry_id";
		}
		
		if ($rating_author_username != '')
		{
			$sql	.= " LEFT JOIN exp_members m ON (m.member_id = r.rating_author_id) ";
		}

		$sql	.= " WHERE r.quarantine != 'y'";

		if ($this->rating_id() === TRUE )
		{
			$sql	.= " AND r.rating_id = '".ee()->db->escape_str($this->rating_id)."'";
		}
		elseif ($this->entry_id() )
		{
			$sql	.= " AND r.entry_id = '".ee()->db->escape_str($this->entry_id)."'";
		}
		
		// --------------------------------------------
        //	Timeframe for Ratings
        // --------------------------------------------

		$t_start_sql = '';
		$t_end_sql = '';
		
		if (ctype_digit($timeframe_start) && $timeframe_start > 0 )
		{
			$sql .= $t_start_sql = " AND r.rating_date >= '$timeframe_start' ";
			
			if ($timeframe_end > $timeframe_start )
			{
				$sql .= $t_end_sql = " AND r.rating_date <= '$timeframe_end' ";
			}
		}
		elseif (ctype_digit($timeframe_end) && $timeframe_end > 0 )
		{
			$sql .= $t_end_sql = " AND r.rating_date <= '$timeframe_end' ";
		}
		
		// --------------------------------------------
        //	Restrict by Author, Form Name, Channel ID, Category
        // --------------------------------------------

		if ($rating_author_id != '' )
		{
			if ( ctype_digit($rating_author_id))
			{
				$sql .= " AND r.rating_author_id = '".ee()->db->escape_str($rating_author_id)."'";
			}
			else
			{
				// ----------------------------------------
				//	This is a little hacky, but {member_id} hasn't been parsed at this point.
				// ----------------------------------------
	
				switch($rating_author_id)
				{
					case LD.'member_id'.RD :
					case LD.'logged_in_member_id'.RD :
					case 'CURRENT_USER' :
						$sql .= " AND r.rating_author_id = '".ee()->db->escape_str(ee()->session->userdata['member_id'])."'";
					break;
					
					case 'NOT_CURRENT_USER' :
						$sql .= " AND r.rating_author_id != '".ee()->db->escape_str(ee()->session->userdata['member_id'])."'";
					break;
				}
			}
		}
		
		if ($rating_author_username != '' )
		{
			$sql .= " AND m.username = '".ee()->db->escape_str($rating_author_username)."'";
		}

		if ( ee()->TMPL->fetch_param('collection') )
		{
			$sql	.= " AND r.collection = '".ee()->db->escape_str(ee()->TMPL->fetch_param('collection'))."'";
		}

		if ( ee()->TMPL->fetch_param($this->sc->db->channel_id) !== FALSE )
		{
			$sql	.= ee()->functions->sql_andor_string( ee()->TMPL->fetch_param($this->sc->db->channel_id), 'r.{$this->sc->db->channel_id} ' );
		}

		if ( ee()->TMPL->fetch_param('category') !== FALSE )
		{
			$category	= str_replace( "C", "", ee()->TMPL->fetch_param('category') );
			$sql	.= ee()->functions->sql_andor_string($category, 'cp.cat_id' );
		}
		
		// --------------------------------------------
        //	Status of Rating
        // --------------------------------------------

		//  Add status declaration
		if ($status = ee()->TMPL->fetch_param('status'))
		{
			$status = str_replace('Open',   'open', $status);
			$status = str_replace('Closed', 'closed', $status);

			$sstr = ee()->functions->sql_andor_string($status, 'r.status');

			$sql	.= $sstr;
		}
		else
		{
			$sql	.= " AND r.status = 'open' ";
		}
		
		// --------------------------------------------
        //	ORDER BY clause
        // --------------------------------------------

		if ( ee()->TMPL->fetch_param('orderby') )
		{
			if ( ee()->TMPL->fetch_param('orderby') == 'random')
			{
				$sql	.= " ORDER BY RAND() ";
			}
			else
			{
				$sql	.= " ORDER BY r.".ee()->TMPL->fetch_param('orderby');
			}
		}
		else
		{
			$sql	.= " ORDER BY r.rating_date";
		}
		
		// --------------------------------------------
        //	Sort
        // --------------------------------------------

		if ( ee()->TMPL->fetch_param('sort') )
		{
			$sort = ( ee()->TMPL->fetch_param('sort') != 'asc' ) ? 'DESC': 'ASC';
		}
		else
		{
			$sort = "DESC";
		}
		
		// Unknown exactly why Mitchell does this, but it seems to put the most recent ratings on top when
		// no orderby="" is specified.
		
		if ( ee()->TMPL->fetch_param('distinct_entries') !== FALSE AND ee()->TMPL->fetch_param('distinct_entries') == 'yes' )
		{
			$sort = ($sort == 'ASC' ) ? 'DESC': 'ASC';
		}
		
		if ( ee()->TMPL->fetch_param('orderby') == 'random')
		{
			$sort = '';
		}
		
		$sql .= " ".$sort;
		
		/** --------------------------------------------
        /**  Limit
        /**	  - It seems like the main data query retrieves information based on r.rating_id, which
        /**		is in turn based off the rating_ids returned by THIS query.  So! Doesn't it sort of
        /**		make sense that we do the pagination and limit on this query instead of on that massive
        /**		data query below?  That way we are not ORDERing possibly 10's of 1000's of rows of data?
        /** --------------------------------------------*/
        		
		if ( ee()->TMPL->fetch_param('paginate') !== FALSE AND ee()->TMPL->fetch_param('paginate') != '' )
		{
			$cquery = ee()->db->query( preg_replace("/SELECT(.*?)\s+FROM\s+/is", 'SELECT COUNT(*) AS count FROM ', $sql));

			if ($cquery->row('count') == 0 )
			{
				return $this->no_results();
			}
			
			$absolute_results = $cquery->row('count');
			
			// ----------------------------------------
			//	Get page number, if any
			// ----------------------------------------
	
			if (preg_match("#/P(\d+)#", $qstring, $match))
			{
				$this->current_page	= $match[1];
			}

			// ----------------------------------------
			//	Prep pagination
			// ----------------------------------------

			$pagination_data = $this->universal_pagination(array(
				'sql'					=> $sql, 
				'total_results'			=> $cquery->row('count'), 
				'tagdata'				=> ee()->TMPL->tagdata,
				'limit'					=> $this->limit,
				'uri_string'			=> ee()->uri->uri_string,
				'current_page'			=> $this->current_page,
				'offset'				=> $offset,
				'paginate_prefix'		=> 'rating_'
			));

			if ($pagination_data['paginate'] === TRUE)
			{
				$this->paginate			= $pagination_data['paginate'];
				$this->page_next		= $pagination_data['page_next']; 
				$this->page_previous	= $pagination_data['page_previous'];
				$this->p_page			= $pagination_data['pagination_page'];
				$this->current_page  	= $pagination_data['current_page'];
				$this->pager 			= $pagination_data['pagination_links'];
				$this->basepath			= $pagination_data['base_url'];
				$this->total_pages		= $pagination_data['total_pages'];
				$this->paginate_data	= $pagination_data['paginate_tagpair_data'];
				$this->page_count		= $pagination_data['page_count'];
				ee()->TMPL->tagdata		= $pagination_data['tagdata'];
				$sql					= $pagination_data['sql'];
			}
			
			unset($cquery);
		}
		else
		{
			$sql .= " LIMIT ".ceil($offset).", ".ceil($this->limit);
		}

		// ----------------------------------------
		//	Retrieve IDs
		// ----------------------------------------
		
		$query	= ee()->db->query($sql );

		// ----------------------------------------
		//	No Results from Query
		// ----------------------------------------

		if ($query->num_rows() == 0 )
		{
			return $this->no_results();
		}
		
		if ( ! isset($absolute_results))
		{
			$absolute_results = $query->num_rows();
		}

		// ----------------------------------------
		//	Run again for full data
		// ----------------------------------------

		$ids	= array();

		foreach ($query->result_array() as $row )
		{
			$ids[]	= $row['id'];
		}

		$sql	= "
			SELECT
				r.*, r.url AS rating_url, r.edit_date as rating_edit_date, r.email AS rating_email, r.location AS rating_location, r.name AS rating_name, m.screen_name AS rating_author,
				t.*,
				m.*, m.email AS m_email, m.url AS m_url, m.location AS m_location,
				md.*,
				s.count AS rating_count
				FROM exp_ratings AS r 
				LEFT JOIN {$this->sc->db->channel_titles} AS t ON t.entry_id = r.entry_id
				LEFT JOIN exp_members AS m ON m.member_id = r.rating_author_id
				LEFT JOIN exp_member_data AS md ON md.member_id = r.rating_author_id 
				LEFT JOIN exp_rating_stats AS s ON s.entry_id = r.entry_id
				WHERE r.rating_id IN (".implode( ",", $ids ).")
				AND (s.collection IS NULL OR s.collection = '".ee()->db->escape_str($collection)."') ";

		if ( ee()->TMPL->fetch_param('orderby') )
		{
			if (ee()->TMPL->fetch_param('orderby') == 'random')
			{
				$sql .= "ORDER BY RAND()";
			}
			else
			{
				$sql	.= " ORDER BY r.".ee()->TMPL->fetch_param('orderby');
			}
		}
		else
		{
			$sql	.= " ORDER BY r.rating_date";
		}
		
		if (ee()->TMPL->fetch_param('orderby') != 'random')
		{
			if ( ee()->TMPL->fetch_param('sort') )
			{
				$sql	.= ( ee()->TMPL->fetch_param('sort') != 'asc' ) ? ' DESC': ' ASC';
			}
			else
			{
				$sql	.= " DESC";
			}
		}
		
		// ----------------------------------------
		//	Full data query
		// ----------------------------------------

		$query	= ee()->db->query($sql );
		
		if ($query->num_rows() == 0 )
		{
			return $this->no_results();
		}
		
		// --------------------------------------------
        //	Typography Class!
        // --------------------------------------------
        
		ee()->load->library('typography');
		
		if (APP_VER >= 2.0)
		{
			ee()->typography->initialize();
		}
		
		ee()->typography->convert_curly = FALSE;
 		ee()->typography->smileys		= FALSE;
		
		// ----------------------------------------
		// Prepare custom fields arrays
		// ----------------------------------------

		$this->fields = $this->data->get_rating_fields_data();
		
		// ----------------------------------------
		//	Check for rating scale
		// ----------------------------------------

		if ($scale = ee()->TMPL->fetch_param('scale') )
		{
			if ($scale != '5' AND $scale != '10' )
			{
				return $this->fetch_error( ee()->lang->line('incorrect_scale') );
			}
		}
		else
		{
			$scale	= 5;
		}

		// ----------------------------------------
		//  Load Theme
		// ----------------------------------------

		$theme		= ( ee()->TMPL->fetch_param('theme') ) ? ee()->TMPL->fetch_param('theme'): 'default';

		$image_url	= ee()->config->slash_item('theme_folder_url') . 
						(APP_VER >= 2.0 ? 'third_party/' : '') . 'rating/'.$theme.'/images/';

		// ----------------------------------------
		//  Add additional values to $query->row
		// ----------------------------------------

		$photo_url		= ee()->config->slash_item('photo_url');
		$avatar_url		= ee()->config->slash_item('avatar_url');
		$sig_img_url	= ee()->config->slash_item('sig_img_url');

		// ----------------------------------------
		//  Fetch all the date-related variables at once to save processing time/effort
		// ----------------------------------------

		$rating_date 			= array();
		$gmt_rating_date		= array();
		$rating_edit_date		= array();
		$gmt_rating_edit_date	= array();
		$join_date				= array();

		$date_vars	= array('rating_date', 'gmt_rating_date', 'rating_edit_date', 'gmt_rating_edit_date', 'join_date');

		foreach ($date_vars as $val)
		{
			if (preg_match_all("/".LD.$val."\s+format=[\"'](.*?)[\"']".RD."/s", ee()->TMPL->tagdata, $matches))
			{
				for ($j = 0; $j < count($matches[0]); $j++)
				{
					$matches[0][$j] = str_replace(array(LD,RD), '', $matches[0][$j]);

					switch ($val)
					{
						case 'rating_date' 			: $rating_date[$matches[0][$j]] = ee()->localize->fetch_date_params($matches[1][$j]);
							break;
						case 'gmt_rating_date'		: $gmt_rating_date[$matches[0][$j]] = ee()->localize->fetch_date_params($matches[1][$j]);
							break;
						case 'rating_edit_date'		: $rating_edit_date[$matches[0][$j]] = ee()->localize->fetch_date_params($matches[1][$j]);
							break;
						case 'gmt_rating_edit_date'	: $gmt_rating_edit_date[$matches[0][$j]] = ee()->localize->fetch_date_params($matches[1][$j]);
							break;
						case 'join_date'			: $join_date[$matches[0][$j]] = ee()->localize->fetch_date_params($matches[1][$j]);
							break;
					}
				}
			}
		}

		// --------------------------------------------
        //	First Rating Information
        // --------------------------------------------
        
		$first_rating = array();
		
		if ( strstr( ee()->TMPL->tagdata, 'first_rating'))
		{
			$fr_query = ee()->db->query(" SELECT r.rating, r.rating_author_id, r.ip_address
										FROM exp_ratings AS r
										WHERE
											r.entry_id = '" . ee()->db->escape_str($query->row('entry_id')) . "'
											AND r.quarantine != 'y'
											AND r.status != 'closed'
										ORDER BY r.rating_date ASC");
			
			foreach ($fr_query->result_array() as $row )
			{
				if ($row['rating_author_id'] != 0 )
				{
					if ( ! isset ($first_rating[$row['rating_author_id']]))
					{
						$first_rating[$row['rating_author_id']] = $row['rating'];
					}
				}
				else
				{
					if ( ! isset ($first_rating[$row['rating_author_id']][$row['ip_address']]))
					{
						$first_rating[$row['rating_author_id']][$row['ip_address']] = $row['rating'];
					}
				}
			}
		}
		
		// ----------------------------------------
		//	Already Reviewed
		// ----------------------------------------
		
		if (strstr(ee()->TMPL->tagdata, 'already_reviewed') OR strstr(ee()->TMPL->tagdata, 'prior_review_count') OR
			strstr(ee()->TMPL->tagdata, 'already_voted') OR strstr(ee()->TMPL->tagdata, 'prior_vote_count'))
		{
			$priors = $this->already_reviewed($query->row('entry_id'));
		}

		// ----------------------------------------
		//	Parse rating rows
		// ----------------------------------------

		$output	= '';

		$count	= 0;
		$page_results = $query->num_rows();

		foreach ($query->result_array() as $row )
		{
			// --------------------------------------------
			//	First Things First
			// --------------------------------------------
		
			ee()->TMPL->log_item('Rating Module Top of Loop: Rating ID = "'.$row['rating_id'].'"');

			$tagdata	= ee()->TMPL->tagdata;
			
			$count++;
		
			// Fix URL bug
			$row['url'] = $row['rating_url'];

			// --------------------------------------------
			//	Clean up mismatched Member data
			// --------------------------------------------
			
			if ($row['rating_author_id'] != 0)
			{
				if ($row['name'] != $row['screen_name'])
				{
					$row['name'] = $row['screen_name'];
				}
				
				if ($row['email'] != $row['m_email'] && $row['m_email'] != '')
				{
					$row['email'] = $row['m_email'];
				}
				
				if ($row['location'] != $row['m_location'] && $row['m_location'] !='')
				{
					$row['location'] = $row['m_location'];
				}
			}
			
			// --------------------------------------------
			//	First Rating
			// --------------------------------------------
			
			$row['first_rating'] = '';

			if ( is_array($first_rating ) && ! empty($first_rating) )
			{
				if ($row['rating_author_id'] > 0 && isset($first_rating[$row['rating_author_id']]))
				{
					$row['first_rating'] = $first_rating[$row['rating_author_id']];
				}
				elseif (isset($first_rating[$row['rating_author_id']][$row['ip_address']]))
				{
					$row['first_rating'] = $first_rating[$row['rating_author_id']][$row['ip_address']];
				}
			}

			// ----------------------------------------
			//  Add additional values to $row
			// ----------------------------------------

			$row['photo_url']	= $photo_url.$row['photo_filename'];
			$row['avatar_url']	= $avatar_url.$row['avatar_filename'];
			$row['sig_img_url']	= $sig_img_url.$row['sig_img_filename'];
			
			// ----------------------------------------
			//	Prep overall avg
			// ----------------------------------------

			$row['overall_avg']		= (isset($row['overall_avg'])) ? $row['overall_avg']: 0;

			$row['overall_avg']		= number_format($row['overall_avg'], $precision, $fractions, $thousands );

			// ----------------------------------------
			//	Prep counts
			// ----------------------------------------

			$row['count']		= $count;
			
			if (empty($row['rating_count']))
			{
				$row['rating_count'] = '0';
			}
			
			$row['total_rating_count']	= $row['rating_count'];
			
			// The Madness of King Kelsey.
			// https://solspace.basecamphq.com/projects/365931-rating/todo_items/88057360/
			$row['page_results']		= $page_results;
			$row['total_results']		= $page_results;
			$row['absolute_results']	= $absolute_results;

			// ----------------------------------------
			//	Start Conditionals
			// ----------------------------------------
			
			$cond						= $row;
			
			// --------------------------------------------
			//	Prep Conditionals
			// --------------------------------------------
			
			$cond['can_edit'] 			= (($row['rating_author_id'] != 0 && ee()->session->userdata['member_id'] == $row['rating_author_id']) OR ee()->session->userdata['group_id'] == 1) ? 'TRUE' : 'FALSE';
			$cond['can_delete'] 		= (in_array(ee()->session->userdata['group_id'], $this->preference('can_delete_ratings'))) ? 'TRUE' : 'FALSE';
			
			$cond['logged_in']			= (ee()->session->userdata['member_id'] == 0) ? 'FALSE' : 'TRUE';
			$cond['logged_out']			= (ee()->session->userdata['member_id'] != 0) ? 'FALSE' : 'TRUE';
			$cond['signature_image']	= ( ! isset($row['sig_img_filename']) OR $row['sig_img_filename'] == '' OR ee()->config->item('enable_signatures') == 'n' OR ee()->session->userdata['display_signatures'] == 'n') ? 'FALSE' : 'TRUE';
			$cond['avatar']				= ( ! isset($row['avatar_filename']) OR $row['avatar_filename'] == '' OR ee()->config->item('enable_avatars') == 'n' OR ee()->session->userdata['display_avatars'] == 'n') ? 'FALSE' : 'TRUE';
			$cond['photo']				= ( ! isset($row['photo_filename']) OR $row['photo_filename'] == '' OR ee()->config->item('enable_photos') == 'n' OR ee()->session->userdata['display_photos'] == 'n') ? 'FALSE' : 'TRUE';
			$cond['already_reviewed']	= (isset($priors[$row['rating_id']])) ? $priors[$row['rating_id']]['already_reviewed'] : FALSE;
			$cond['not_already_reviewed']	= (isset($priors[$row['rating_id']])) ? $priors[$row['rating_id']]['not_already_reviewed'] : TRUE;
			$cond['prior_review_count']	= (isset($priors[$row['rating_id']])) ? $priors[$row['rating_id']]['prior_review_count'] : 0;
			$row['prior_review_count'] = $cond['prior_review_count'];
			
			$tagdata	= ee()->functions->prep_conditionals($tagdata, $cond );

			// ----------------------------------------
			//	Prepare vars for overall avg
			// ----------------------------------------

			$overall	= array( 'stars' => array(), 'bar' => array(), 'circles' => array() );

			// ----------------------------------------
			//	Parse {stars}{/stars} Variable Pair
			// ----------------------------------------

			if ( preg_match_all( "/".LD."stars".RD."(.+?)".LD.preg_quote(T_SLASH, '/')."stars".RD."/s", $tagdata, $matches))
			{
				foreach ($matches[0] as $k => $match)
				{
					$tdata	= $matches[1][$k];
	
					foreach ($row as $key => $val )
					{
						if ( stristr($tdata, LD.$key.RD))
						{
							$data = array(
											'type'		=> 'stars',
											'row'		=> $row,
											'field'		=> $key,
											'scale'		=> $scale,
											'image_url'	=> $image_url
											);
	
							//	Prep stars overall
							if (isset($data['row'][$key]) && ctype_digit($data['row'][$key]) && $data['row'][$key] > 0 AND in_array($key, $this->fields_numeric()))
							{
								$overall['stars'][]	= $data['row'][$key];
							}

							$tdata	= str_replace( LD.$key.RD, $this->parse_stars($data ), $tdata );
						}
					}

					// ----------------------------------------
					//	Parse overall
					// ----------------------------------------
	
					$row['overall_avg']		= ( count($overall['stars']) > 0 ) ? ( array_sum($overall['stars']) / count($overall['stars'])) : 0;
	
					// ----------------------------------------
					//	Parse images
					// ----------------------------------------
	
					$tdata		= str_replace( LD.'overall_avg'.RD, $this->parse_overall_avg($row['overall_avg'], $g_precision, $scale, $image_url, 'stars' ), $tdata );
	
					$tagdata	= str_replace($match, $tdata, $tagdata );
				}
			}
			
			// ----------------------------------------
			//	Parse {bar}{/bar} Variable Pair
			// ----------------------------------------

			if ( preg_match_all( "/".LD."bar".RD."(.+?)".LD.preg_quote(T_SLASH, '/')."bar".RD."/s", $tagdata, $matches))
			{
				foreach ($matches[0] as $k => $match)
				{
					$tdata	= $matches[1][$k];

					foreach ($row as $key => $val )
					{
						if ( stristr($tdata, LD.$key.RD))
						{
							$data	= array(
												'type'		=> 'bar',
												'row'		=> $row,
												'field'		=> $key,
												'scale'		=> $scale,
												'image_url'	=> $image_url
												);
	
							//	Prep bar overall
							if ( isset($data['row'][$key]) && ctype_digit($data['row'][$key]) && $data['row'][$key] > 0 && in_array($key, $this->fields_numeric()))
							{
								$overall['bar'][]	= $data['row'][$key];
							}
	
							$tdata	= str_replace( LD.$key.RD, $this->parse_stars($data ), $tdata );
						}
					}
	
					// ----------------------------------------
					//	Parse overall
					// ----------------------------------------
	
					$row['overall_avg']	= ( count($overall['bar']) > 0 ) ? ( array_sum($overall['bar']) / count($overall['bar'])) : 0;
	
					// ----------------------------------------
					//	Parse images
					// ----------------------------------------
	
					$tdata		= str_replace( LD.'overall_avg'.RD, $this->parse_overall_avg($row['overall_avg'], $g_precision, $scale, $image_url, 'bar' ), $tdata );
	
					$tagdata	= str_replace($match, $tdata, $tagdata );
				}
			}
			
			// ----------------------------------------
			//	Parse {circles}{/circles} Variable Pair
			// ----------------------------------------

			if ( preg_match_all( "/".LD."circles".RD."(.+?)".LD.preg_quote(T_SLASH, '/')."circles".RD."/s", $tagdata, $matches))
			{
				foreach ($matches[0] as $k => $match)
				{
					$tdata	= $matches[1][$k];

					foreach ($row as $key => $val )
					{
						if ( stristr($tdata, LD.$key.RD))
						{
							$data	= array(
												'type'		=> 'circles',
												'row'		=> $row,
												'field'		=> $key,
												'scale'		=> $scale,
												'image_url'	=> $image_url
												);
	
							//	Prep stars overall
							if ( isset($data['row'][$key]) && ctype_digit($data['row'][$key]) && $data['row'][$key] > 0 && in_array($key, $this->fields_numeric()))
							{
								$overall['circles'][]	= $data['row'][$key];
							}
	
							$tdata	= str_replace( LD.$key.RD, $this->parse_stars($data ), $tdata );
						}
					}
	
					// ----------------------------------------
					//	Parse overall
					// ----------------------------------------
	
					$row['overall_avg']		= ( count($overall['circles']) > 0 ) ? ( array_sum($overall['circles']) / count($overall['circles'])) : 0;
	
					// ----------------------------------------
					//	Parse images
					// ----------------------------------------
	
					$tdata		= str_replace( LD.'overall_avg'.RD, $this->parse_overall_avg($row['overall_avg'], $g_precision, $scale, $image_url, 'circles' ), $tdata );
	
					$tagdata	= str_replace($match, $tdata, $tagdata );
				}
			}

			// ----------------------------------------
			//	Parse single variables
			// ----------------------------------------
			
			foreach ( ee()->TMPL->var_single as $key => $val )
			{
				// ----------------------------------------
				//  parse {switch} variable
				// ----------------------------------------

				if (preg_match("/^switch\s*=.+/i", $key))
				{
					$sparam = ee()->functions->assign_parameters($key);

					$sw = '';

					if (isset($sparam['switch']))
					{
						$sopt = explode("|", $sparam['switch']);
						$sw = $sopt[($count - 1) % count($sopt)];
					}

					$tagdata = ee()->TMPL->swap_var_single($key, $sw, $tagdata);
				}

				// ----------------------------------------
				//  parse rating date
				// ----------------------------------------

				if (isset($rating_date[$key]))
				{
					foreach ($rating_date[$key] as $dvar)
					{
						$val	= str_replace($dvar, ee()->localize->convert_timestamp($dvar, $row['rating_date'], TRUE), $val);
					}
					
					$tagdata	= ee()->TMPL->swap_var_single($key, $val, $tagdata);
				}

				// ----------------------------------------
				//  parse gmt rating date
				// ----------------------------------------

				if (isset($gmt_rating_date[$key]))
				{
					foreach ($gmt_rating_date[$key] as $dvar)
					{
						$val	= str_replace($dvar, ee()->localize->convert_timestamp($dvar, $row['rating_date'], FALSE), $val);
					}
					
					$tagdata	= ee()->TMPL->swap_var_single($key, $val, $tagdata);
				}

				// ----------------------------------------
				//  parse rating edit date
				// ----------------------------------------

				if (isset($rating_edit_date[$key]))
				{
					foreach ($rating_edit_date[$key] as $dvar)
					{
						$val	= str_replace($dvar, ee()->localize->convert_timestamp($dvar, $row['rating_edit_date'], TRUE), $val);
					}
					
					$tagdata	= ee()->TMPL->swap_var_single($key, $val, $tagdata);
				}

				// ----------------------------------------
				//  parse gmt rating edit date
				// ----------------------------------------

				if (isset($gmt_rating_edit_date[$key]))
				{
					foreach ($gmt_rating_edit_date[$key] as $dvar)
					{
						$val	= str_replace($dvar, ee()->localize->convert_timestamp($dvar, $row['rating_edit_date'], FALSE), $val);
					}
					
					$tagdata	= ee()->TMPL->swap_var_single($key, $val, $tagdata);
				}

				// ----------------------------------------
				//  parse join date date
				// ----------------------------------------

				if (isset($join_date[$key]))
				{
					foreach ($join_date[$key] as $dvar)
					{
						$val	= str_replace($dvar, ee()->localize->convert_timestamp($dvar, $row['join_date'], FALSE), $val);
					}
					
					$tagdata	= ee()->TMPL->swap_var_single($key, $val, $tagdata);
				}

				// ----------------------------------------
				//  parse member fields
				// ----------------------------------------

				if ( count($this->mfields() ) != 0 )
				{
					if ( isset($this->mfields[$key]))
					{
						$tagdata	= ee()->TMPL->swap_var_single($key, $row[ 'm_field_id_'.$this->mfields[$key]['id'] ], $tagdata );
					}
				}

				// ----------------------------------------
				//  Parse Typography
				// ----------------------------------------

				if ( isset($this->fields[$key]) AND $this->fields[$key]['field_type'] == 'textarea' )
				{
					$data	= ee()->typography->parse_type(
													   $row[$key],
													   array(
															'text_format'   => $this->fields[$key]['field_fmt'],
															'html_format'   => 'all',
															'auto_links'	=> 'n',
															'allow_img_url' => 'y'
															)
													 );

					$tagdata = ee()->TMPL->swap_var_single($key, $data, $tagdata);
				}
				
				// ----------------------------------------
				//  Parse Remaining Variables
				// ----------------------------------------
				
				if ( isset($row[$key]))
				{
					// respect precision / format parameters for _avg variables
					if (substr($key, -4) == '_avg')
					{
						$row[$key] = number_format($row[$key], $precision, $fractions, $thousands );
					}
					
					$tagdata	= ee()->TMPL->swap_var_single($key, $row[$key], $tagdata );
				}
				elseif (substr($key, -4) == '_avg')
				{
					$tagdata = ee()->TMPL->swap_var_single($key, number_format( 0, $precision, $fractions, $thousands ), $tagdata );
				}
			}

			ee()->TMPL->log_item('Rating Module Bottom of Loop: Rating ID = "'.$row['rating_id'].'"');

			$output	.= $tagdata;
		}
		
		// ----------------------------------------
		//	Add Pagination
		// ----------------------------------------

		if (stristr($output, 'rating_pagination'))
		{
			$output = $this->parse_pagination($output);
		}

		$output = $this->parse_pagination($output);

		// ----------------------------------------
		//	Return
		// ----------------------------------------

		return $output;
	}
	/* END entries() */
	
	
	// --------------------------------------------------------------------

	/**
	 * Parse pagination
	 *
	 * @access	private
	 * @param	string
	 * @return	string
	 */

	public function parse_pagination( $return = '', $prefix = '' )
	{
		if ($prefix != '')
		{
			$prefix = rtrim($prefix, '_').'_';
		}
	
		// ----------------------------------------
		//	Capture pagination format
		// ----------------------------------------

		if ( $this->paginate === FALSE )
		{
			ee()->TMPL->tagdata = preg_replace(
				"/" . LD . "if ".preg_quote($prefix,'/')."paginate" . RD . ".*?" . LD . "&#47;if" . RD . "/s", 
				'', 
				ee()->TMPL->tagdata
			);
		}
		else
		{
			ee()->TMPL->tagdata = preg_replace(
				"/" . LD . "if ".preg_quote($prefix,'/')."paginate" . RD . "(.*?)" . LD . "&#47;if" . RD . "/s", 
				"\\1", 
				ee()->TMPL->tagdata
			);

			$pagination_array	= array(
				$prefix.'pagination_links'	=> $this->pager,
				$prefix.'current_page'		=> $this->current_page,
				$prefix.'total_pages'		=> $this->total_pages,
				$prefix.'page_count'		=> $this->page_count
			);

			$this->paginate_data	= ee()->functions->prep_conditionals( $this->paginate_data, $pagination_array );

			foreach ( $pagination_array as $key => $val )
			{
				$this->paginate_data	= str_replace( LD.$key.RD, $val, $this->paginate_data);
			}

			// ----------------------------------------
			//	Previous link
			// ----------------------------------------

        	if (preg_match("/".LD."if ".preg_quote($prefix,'/')."previous_page".RD."(.+?)".LD.preg_quote(T_SLASH, '/')."if".RD."/s", $this->paginate_data, $match))
        	{
        		if ($this->page_previous == '')
        		{
        			 $this->paginate_data = preg_replace(
						"/" . LD . "if ".preg_quote($prefix,'/')."previous_page" . RD . ".+?" . LD . preg_quote(T_SLASH, '/') . "if" . RD . "/s", 
						'', 
						$this->paginate_data
					);
        		}
        		else
        		{
					$match['1'] = preg_replace("/".LD.preg_quote($prefix,'/').'path.*?'.RD."/", 	$this->page_previous, $match['1']);
					$match['1'] = preg_replace("/".LD.preg_quote($prefix,'/').'auto_path'.RD."/",	$this->page_previous, $match['1']);

					$this->paginate_data = str_replace($match[0], $match[1], $this->paginate_data);
				}
        	}

			// ----------------------------------------
			//	Next link
			// ----------------------------------------

        	if (preg_match("/".LD."if ".preg_quote($prefix,'/')."next_page".RD."(.+?)".LD.preg_quote(T_SLASH, '/')."if".RD."/s", $this->paginate_data, $match))
        	{
        		if ($this->page_next == '')
        		{
        			 $this->paginate_data = preg_replace("/".LD."if ".preg_quote($prefix,'/')."next_page".RD.".+?".LD.preg_quote(T_SLASH, '/')."if".RD."/s", '', $this->paginate_data);
        		}
        		else
        		{
					$match['1'] = preg_replace("/".LD.preg_quote($prefix,'/').'path.*?'.RD."/", 	$this->page_next, $match['1']);
					$match['1'] = preg_replace("/".LD.preg_quote($prefix,'/').'auto_path'.RD."/",	$this->page_next, $match['1']);

					$this->paginate_data = str_replace( $match['0'],	$match['1'], $this->paginate_data );
				}
        	}
		}

		// ----------------------------------------
		//	Add pagination
		// ----------------------------------------

		if ( ee()->TMPL->fetch_param('paginate') == 'both' )
		{
			$return	= $this->paginate_data.$return.$this->paginate_data;
		}
		elseif ( ee()->TMPL->fetch_param('paginate') == 'top' )
		{
			$return	= $this->paginate_data.$return;
		}
		else
		{
			$return	= $return.$this->paginate_data;
		}

		// ----------------------------------------
		//	Return
		// ----------------------------------------

		return $return;
	}
	// End parse pagination

	
	// --------------------------------------------------------------------

	/**
	 *	Rankings for Ratings - LEGACY
	 *
	 *	http://www.solspace.com/docs/detail/rating_rankings/
	 *
	 *	@access		public
	 *	@return		string
	 */
	
	public function rankings()
	{
		return $this->rank();
	}
	/* END rankings() */


	// --------------------------------------------------------------------

	/**
	 *	Rank for Ratings
	 *
	 *	http://www.solspace.com/docs/detail/rating_rankings/
	 *
	 *	@access		public
	 *	@return		string
	 */

	public function rank()
	{
		// --------------------------------------------
        //	Rating Pagination - We use Weblog/Channel Entries' parsing so we do a quick find/replace
        // --------------------------------------------
        
        ee()->TMPL->tagdata = preg_replace("/\{(".preg_quote(T_SLASH, '/')."){0,1}rating_paginate\}/", '{\1paginate}', ee()->TMPL->tagdata);
        
		// ----------------------------------------
		//	Invoke Channel class
		// ----------------------------------------
		
		if (APP_VER < 2.0)
		{
			if ( ! class_exists('Weblog') )
			{
				require PATH_MOD.'/weblog/mod.weblog'.EXT;
			}
	
			$this->channel = new Weblog;
		}
		else
		{
			if ( ! class_exists('Channel') )
			{
				require PATH_MOD.'/channel/mod.channel'.EXT;
			}
	
			$this->channel = new Channel;
		}

		ee()->TMPL->tagdata		= ee()->TMPL->assign_relationship_data( ee()->TMPL->tagdata );

		ee()->TMPL->var_single	= array_merge( ee()->TMPL->var_single, ee()->TMPL->related_markers );
		
		// --------------------------------------------
        //  Invoke Pagination for EE 2.4 and Above
        // --------------------------------------------

		if (APP_VER >= '2.4.0')
		{
			ee()->load->library('pagination');
			$this->channel->pagination = new Pagination_object('Channel');
			
			// Used by pagination to determine whether we're coming from the cache
			$this->channel->pagination->dynamic_sql = FALSE;
		}

		// ----------------------------------------
		//  Execute needed methods
		// ----------------------------------------

		if ($this->channel->enable['custom_fields'] == TRUE)
		{
			if (APP_VER < 2.0)
			{
				$this->channel->fetch_custom_weblog_fields();
			}
			else
			{
				$this->channel->fetch_custom_channel_fields();
			}
		}
		
		if ($this->channel->enable['member_data'] == TRUE)
		{
			$this->channel->fetch_custom_member_fields();
		}
		
		// --------------------------------------------
        //  Pagination Tags Parsed Out
        // --------------------------------------------
        
        if ($this->channel->enable['pagination'] == TRUE)
        {
			if (APP_VER >= '2.4.0')
			{
				$this->channel->pagination->get_template();
			}
			else
			{
				$this->channel->fetch_pagination_data();
			}
		}
		
		// --------------------------------------------
        //	Choose a Start/Stop Time for Ratings
        // --------------------------------------------
        
        $sql = $this->build_sql_for_rank();
        
        //echo $sql;
        
        if ($sql == '')
        {
        	return $this->no_results();
        }
        
        $query = ee()->db->query($sql);
        
        if ($query->num_rows() == 0)
        {
        	return $this->no_results();
        }
		
		$ids = array();
		
		foreach($query->result_array() as $row)
		{
			$ids[] = $row['entry_id'];
		}
		
		$params_backup = array();
		
		foreach(ee()->TMPL->tagparams as $key => $value)
		{
			$params_backup[$key] = $value;
			unset(ee()->TMPL->tagparams[$key]);
		}
		
		ee()->TMPL->tagparams['fixed_order']	= implode('|', $ids);
		ee()->TMPL->tagparams['entry_id']		= implode('|', $ids);

		ee()->TMPL->tagparams['inclusive']	= '';
		ee()->TMPL->tagparams['paginate']	= (isset($params_backup['paginate'])) ? $params_backup['paginate'] : '';
		ee()->TMPL->tagparams['limit']		= (isset($params_backup['limit'])) ? $params_backup['limit'] : '';
		ee()->TMPL->tagparams['status']		= (isset($params_backup['status'])) ? $params_backup['status'] : '';
		ee()->TMPL->tagparams['show_expired'] = (isset($params_backup['show_expired'])) ? $params_backup['show_expired'] : '';

		ee()->TMPL->tagparams['show_pages']	= 'all';

		if ( isset( $params_backup['dynamic'] ) AND $this->check_no($params_backup['dynamic']))
		{
			ee()->TMPL->tagparams['dynamic'] = (APP_VER < 2.0) ? 'off' : 'no';
		}
        
		// ----------------------------------------
		//  Grab entry data
		// ----------------------------------------
		
		$csql = preg_replace("/LIMIT(.*?)$/s", '', $sql);
		$cquery = $this->EE->db->query("SELECT COUNT(*) AS count FROM ({$csql}) AS query");

		if ($cquery->row('count') == 0 )
		{
			return $this->no_results();
		}
		
		if (APP_VER >= '2.4.0')
		{
			$this->channel->pagination->cfields = $this->channel->cfields;
			$this->channel->pagination->build($cquery->row('count'));
		}
		else
		{
			$this->channel->create_pagination($cquery->row('count'));
		}

		$this->channel->build_sql_query();
		
		if ($this->channel->sql == '')
        {
        	return $this->no_results();
        }
		
		foreach($params_backup as $key => $value)
		{
			ee()->TMPL->tagparams[$key] = $value;
		}
        
        // --------------------------------------------
        //	Add in Stats
        // --------------------------------------------
        
        $extra = " rs.`count` AS timeframe_count, rs.`count` AS rating_timeframe_count, 
        		   rs.`avg` AS rating_timeframe_avg, rs.`avg` AS timeframe_avg, 
        		   rs.`sum` AS rating_timeframe_sum, rs.`sum` AS timeframe_sum, ";
        
        $this->channel->sql = str_replace("md.*,", "md.*, ".$extra, $this->channel->sql);
        
        if ( ee()->TMPL->fetch_param('collection') !== FALSE && ee()->TMPL->fetch_param('collection') != 'all')
        {
        	 $extra = " LEFT JOIN exp_rating_stats AS rs ON (rs.entry_id = t.entry_id AND rs.collection = '".ee()->db->escape_str(ee()->TMPL->fetch_param('collection'))."')";
        }
        else
        {
        	 $extra = "LEFT JOIN exp_rating_stats AS rs ON (rs.entry_id = t.entry_id AND rs.collection = 'all')";
        }
        
		$this->channel->sql = str_replace("LEFT JOIN exp_members", $extra." LEFT JOIN exp_members", $this->channel->sql);
        
        // --------------------------------------------
        //	Perform Query
        // --------------------------------------------
        
		$this->channel->query = ee()->db->query($this->channel->sql);

		if ( ! isset($this->channel->query ) OR $this->channel->query->num_rows() == 0)
		{
			return $this->no_results();
		}
		
		if (APP_VER < 2.0)
		{			
			$this->channel->query->result = $this->channel->query->result_array();
		}
		
		// --------------------------------------------
        //	Give them Typography or Give Them CAKE!
        // --------------------------------------------

		if (APP_VER < 2.0)
        {
        	if ( ! class_exists('Typography'))
			{
				require PATH_CORE.'core.typography'.EXT;
			}
					
			$this->channel->TYPE = new Typography;
			$this->channel->TYPE->convert_curly = FALSE;
        }
        else
        {
			ee()->load->library('typography');
			ee()->typography->initialize();
			ee()->typography->convert_curly = FALSE;
		}
		
		if ($this->channel->enable['categories'] == TRUE)
		{
			$this->channel->fetch_categories();
		}
		
		// Prep _avg variables
		$precision	= ( ee()->TMPL->fetch_param('precision') ) ? ee()->TMPL->fetch_param('precision'): '0';
		$thousands	= ( ee()->TMPL->fetch_param('thousands') ) ? ee()->TMPL->fetch_param('thousands'): ',';
		$fractions	= ( ee()->TMPL->fetch_param('fractions') ) ? ee()->TMPL->fetch_param('fractions'): '.';
		
		foreach ($this->channel->query->result_array() as $k => $data)
		{
			foreach ($data as $key => $val)
			{
				// respect precision / format parameters for _avg variables
				if (substr($key, -4) == '_avg')
				{
					if (APP_VER < 2.0)
					{
						$this->channel->query->result[$k][$key] = number_format($val, $precision, $fractions, $thousands );
					}
					else
					{
						$this->channel->query->result_array[$k][$key] = number_format($val, $precision, $fractions, $thousands );
					}
				}
			}
		}
		
		// ----------------------------------------
		//  Parse and return entry data
		// ----------------------------------------

		if (APP_VER < 2.0)
		{
			$this->channel->parse_weblog_entries();
		}
		else
		{
			$this->channel->parse_channel_entries();
		}
		
		// --------------------------------------------
        //  Parse Pagination
        // --------------------------------------------
		
		if ($this->channel->enable['pagination'] == TRUE)
		{
			if (APP_VER >= '2.4.0')
			{
				$this->channel->return_data = $this->channel->pagination->render($this->channel->return_data);
			}
			else
			{
				$this->channel->add_pagination_data();
			}
		}
		
		// ----------------------------------------
		//  Handle related entries
		// ----------------------------------------

		if (count(ee()->TMPL->related_data) > 0 AND count($this->channel->related_entries) > 0)
		{
			$this->channel->parse_related_entries();
		}

		if (count(ee()->TMPL->reverse_related_data) > 0 AND count($this->channel->reverse_related_entries) > 0)
		{
			$this->channel->parse_reverse_related_entries();
		}

		// ----------------------------------------
		//  Handle problem with pagination segments in the url
		// ----------------------------------------

		if ( preg_match("#(/?P\d+)#", ee()->uri->uri_string, $match) )
		{
			$this->channel->return_data	= str_replace($match[1], '', $this->channel->return_data );
		}

		return $tagdata = $this->channel->return_data;
	}
	//	End rank
	
	// --------------------------------------------------------------------

	/**
	 *	Build SQL for Rank Entry ID Restrictions
	 *
	 *	@access		public
	 *	@return		string
	 */
	 
	function build_sql_for_rank()
	{	
		$sql = '';
	
		// --------------------------------------------
        //	Restrict by Entries who have been rated within this time frame
        // --------------------------------------------
	
		if ( ee()->TMPL->fetch_param('timeframe_start') !== FALSE OR ee()->TMPL->fetch_param('timeframe_end') !== FALSE)
		{
			$start	= ( ee()->TMPL->fetch_param('timeframe_start') ) ? strtotime( ee()->TMPL->fetch_param('timeframe_start') ) : 0;
			$end	= ( ee()->TMPL->fetch_param('timeframe_end') ) ? strtotime( ee()->TMPL->fetch_param('timeframe_end') ) : 0;
			
			if (ctype_digit($start) && $start > 0 )
			{
				$sql .= " AND r.rating_date >= '$start' ";
				
				if ($end > $start )
				{
					$sql .= " AND r.rating_date <= '$end' ";
				}
			}
			elseif (ctype_digit($end) && $end > 0 )
			{
				$sql .= " AND r.rating_date <= '$end' ";
			}
		}
		
		// --------------------------------------------
        //	Restrict by collection=""
        // --------------------------------------------
                
        if ( ee()->TMPL->fetch_param('collection') !== FALSE && ee()->TMPL->fetch_param('collection') != 'all')
        {
        	$sql .= " AND r.collection = '".ee()->db->escape_str(ee()->TMPL->fetch_param('collection'))."'";
        }
        
        // ----------------------------------------------
		//	ORDER BY and SORT
		// ----------------------------------------------

		$order  = ee()->TMPL->fetch_param('orderby');
		$sort   = ee()->TMPL->fetch_param('sort');
		$sticky = ee()->TMPL->fetch_param('sticky');

		// -------------------------------------
		//	Multiple Orders and Sorts...
		// -------------------------------------

		if ($order !== FALSE && stristr($order, '|'))
		{
			$order_array = explode('|', $order);

			if ($order_array[0] == 'random')
			{
				$order_array = array('random');
			}
		}
		else
		{
			$order_array = array($order);
		}

		if ($sort !== FALSE && stristr($sort, '|'))
		{
			$sort_array = explode('|', $sort);
		}
		else
		{
			$sort_array = array($sort);
		}
		
		// -------------------------------------
		//	Begin ORDER BY processing
		// -------------------------------------

		$base_orders = array('random', 'date', 'title', 'url_title', 'edit_date', 'entry_date', 'comment_total',
							 'username', 'screen_name', 'most_recent_comment', 'expiration_date',
							 'view_count_one', 'view_count_two', 'view_count_three', 'view_count_four',
							 'timeframe_count');

		if ( $order !== FALSE)
		{
			$rating_orders		= array('overall' => 'avg');
			$bayesian_orders	= array('bayesian:overall' => 'bayesian:overall'); 
	
			foreach ($this->data->get_rating_fields_data() as $data)
			{
				$rating_orders['count_'.$data['field_name']]	= 'count_'.$data['field_id'];
				$rating_orders['sum_'.$data['field_name']]	= 'sum_'.$data['field_id'];
				$rating_orders['avg_'.$data['field_name']]	= 'avg_'.$data['field_id'];
				
				$rating_orders[$data['field_name']]			= 'avg_'.$data['field_id'];
				
				if ($data['field_type'] == 'number')
				{
					$bayesian_orders['bayesian:'.$data['field_name']] = $data['field_name'];
				}
			}
		}

		foreach($order_array as $key => $order)
		{
			// We only modify non-base orders
			if ($order !== FALSE && ! in_array($order, $base_orders))
			{
				if (array_key_exists($order, $bayesian_orders))
				{
					$corder[$key] = $bayesian_orders[$order];
					$order_array[$key] = 'bayesian_rating_field';
				}
				elseif (array_key_exists($order, $rating_orders))
				{
					$corder[$key] = $rating_orders[$order];
					$order_array[$key] = 'rating_field';
				}
				else
				{
					$set = 'n';

					foreach($this->channel->cfields as $site_id => $cfields)
					{
						if (isset($cfields[$order]))
						{
							$corder[$key] = $cfields[$order];
							$order_array[$key] = 'custom_field';
							$set = 'y';
							break;
						}
					}

					if ($set == 'n')
					{
						$order_array[$key] = FALSE;
					}
				}
			}

			if ( ! isset($sort_array[$key]))
			{
				$sort_array[$key] = 'desc';
			}
		}

		foreach($sort_array as $key => $sort)
		{
			if ($sort == FALSE || ($sort != 'asc' AND $sort != 'desc'))
			{
				$sort_array[$key] = "desc";
			}
		}
		
		// --------------------------------------------------
		//	Build sorting clause
		// --------------------------------------------------

		$end = ' ';

		if (FALSE === $order_array[0])
		{
			$end .= "ORDER BY ";
			
			if ($this->check_no($sticky))
			{
				if (ee()->TMPL->fetch_param('display_unrated') !== FALSE && ee()->TMPL->fetch_param('display_unrated') == 'yes')
				{
					$end .= "r.entry_id IS NOT NULL DESC,";
				}
			
				$end .= "rs.avg";
			}
			else
			{
				$end .= "t.sticky desc,";
				
				if (ee()->TMPL->fetch_param('display_unrated') !== FALSE && ee()->TMPL->fetch_param('display_unrated') == 'yes')
				{
					$end .= "r.entry_id IS NOT NULL DESC,";
				}
				
				$end .= "rs.avg";
			}

			if ($sort_array[0] == 'asc' || $sort_array[0] == 'desc')
			{
				$end .= " ".$sort_array[0];
			}
			
			if (ee()->TMPL->fetch_param('display_unrated') == 'yes')
			{
				$end .= ", t.entry_date DESC";
			}
		}
		else
		{
			if ($this->check_no($sticky))
			{
				$end .= "ORDER BY ";
			}
			else
			{
				$end .= "ORDER BY t.sticky DESC, ";
			}

			// Force the ranking order here as well
			// I have no idea today why this array would be empty, but I am sure this code is here for a reason
			if ( ! $order_array)
			{
				$end	.= "r.entry_id IS NOT NULL DESC, rs.avg DESC, t.entry_date DESC, ";
			}

			foreach($order_array as $key => $order)
			{
				if (in_array($order, array('view_count_one', 'view_count_two', 'view_count_three', 'view_count_four')))
				{
					$view_ct = substr($order, 10);
					$order	 = "view_count";
				}

				if ($key > 0) $end .= ", ";
				
				// This forces the NULL to the bottom
				if (ee()->TMPL->fetch_param('display_unrated') !== FALSE && ee()->TMPL->fetch_param('display_unrated') == 'yes')
				{
					if (in_array($order, 
								 array(	'rating', 'rating_avg', 'overall', 
								 		'rating_field', 'bayesian_rating_field', 
								 		'timeframe_rating', 'timeframe_avg', 
								 		'timeframe_count', 'timeframe_sum'
								 		)
								)
					)
					{
						$end .= "r.entry_id IS NOT NULL DESC, ";
					}
				}
				
				switch ($order)
				{
					case 'date' :
						$end .= "t.entry_date";
					break;

					case 'edit_date' :
						$end .= "t.edit_date";
					break;

					case 'expiration_date' :
						$end .= "t.expiration_date";
					break;

					case 'rating' :
					case 'rating_avg' :
					case 'overall' :
						$end .= "rs.avg";
					break;

					case 'title' :
						$end .= "t.title";
					break;

					case 'url_title' :
						$end .= "t.url_title";
					break;

					case 'view_count' :
						$vc = $order.$view_ct;

						$end .= " t.{$vc} ".$sort_array[$key].", t.entry_date ".$sort_array[$key];

						$sort_array[$key] = FALSE;
					break;

					case 'comment_total' :
						$end .= "t.comment_total ".$sort_array[$key].", t.entry_date ".$sort_array[$key];
						$sort_array[$key] = FALSE;
					break;

					case 'most_recent_comment' :
						$end .= "t.recent_comment_date ".$sort_array[$key].", t.entry_date ".$sort_array[$key];
						$sort_array[$key] = FALSE;
					break;

					case 'username' :
						$end .= "m.username";
					break;

					case 'screen_name' :
						$end .= "m.screen_name";
					break;

					case 'custom_field' :
						$end .= "wd.field_id_".$corder[$key];
					break;

					case 'random' :
							$end = "ORDER BY rand()";
							$sort_array[$key] = FALSE;
					break;

					case 'rating_field' :
						$end .= 'rs.'.$corder[$key];
					break;
					
					case 'bayesian_rating_field' :
						$sort_array[$key] = '';
						$end .= $this->bayesian_rating_field($corder[$key], $sql);
					break;

					case 'total_ratings' :
						$end .= "t.rating_total";
						//@todo - Change this as this field is buh-bye
					break;

					case 'timeframe_rating' :
					case 'timeframe_avg':
						$end .= "rating_timeframe_avg";
					break;

					case 'timeframe_count' :
						$end .= "rating_timeframe_count";
					break;

					case 'timeframe_sum' :
						$end .= "rating_timeframe_sum";
					break;

					default	   :
						$end .= "t.entry_date";
					break;
				}

				if ($sort_array[$key] == 'asc' || $sort_array[$key] == 'desc')
				{
					$end .= " ".$sort_array[$key];
				}
			}
		}

		$end = rtrim($end, ', ');
		
		// --------------------------------------------
        //	Build Channel SQL Query with Our Extras
        // --------------------------------------------
        
        $rating_sql['join'] = " LEFT JOIN exp_ratings AS r ON (t.entry_id = r.entry_id) ";
        
        if ( ee()->TMPL->fetch_param('collection') !== FALSE && ee()->TMPL->fetch_param('collection') != 'all')
        {
			$rating_sql['join'] .= " LEFT JOIN exp_rating_stats AS rs ON (rs.entry_id = r.entry_id AND rs.collection = r.collection)";
        }
        else
        {
        	$rating_sql['join'] .= " LEFT JOIN exp_rating_stats AS rs ON (rs.entry_id = r.entry_id AND rs.collection = 'all')";
        }
        
        if (ee()->TMPL->fetch_param('display_unrated') !== FALSE && ee()->TMPL->fetch_param('display_unrated') == 'yes')
        {
        	$rating_sql['where'] = " AND (r.entry_id IS NULL OR (r.quarantine != 'y' AND r.status = 'open' ".$sql.'))';
        }
        else
        {
        	$rating_sql['where'] = " AND r.quarantine != 'y' AND r.status = 'open' AND r.rating IS NOT NULL ".$sql;
        }
        
        $rating_sql['order_by'] = $end;
        
        $rating_channel = new Rating_channel();
        $rating_channel->sc = $this->sc;
        $rating_channel->cfields	= $this->channel->cfields;
        $rating_channel->mfields	= $this->channel->mfields;
		return $rating_channel->build_sql_query('', $rating_sql);
		
		exit($x);
	}
	// END build_sql_for_rank()
	
	
	// --------------------------------------------------------------------

	/**
	 *	Bayesian Rating Field ORDERY BY
	 *
	 *	@access		public
	 *	@param		string
	 *	@param		string
	 *	@return		string
	 */
	
	function bayesian_rating_field($field, $sql)
	{	
		// --------------------------------------------
        //	Main Query
        // --------------------------------------------
        
        if ($field == 'bayesian:overall')
        {
        	// Overall, for now, is based off the data in exp_rating_stats
        	// The reason for this is that if we start SUM'ing fields in exp_ratings, we have to take
        	// into account number fields with no value, except the default of 0.  Requires checking
        	// number field values and dividing the SUM by the number of non-0 fields.
        	
        	$mquery = ee()->db->query(
				"SELECT DISTINCT rs.avg AS bayesian_field, rs.count AS overall_count, 
						r.entry_id
				 FROM 	exp_ratings AS r, exp_rating_stats AS rs
				 WHERE 	rs.entry_id = r.entry_id AND IF(r.collection='', rs.collection = 'all', rs.collection = r.collection)
				 ".$sql
			);
        }
        else
        {
        	if (ctype_alnum(str_replace('_', '', $field)) === FALSE) return '';
        	
        	$mquery = ee()->db->query(
				"SELECT r.`".$field."` AS bayesian_field, 
						r.entry_id, 
						r.rating_helpful_y, 
						r.rating_helpful_n, 
						r.rating_author_id, 
						r.ip_address 
				 FROM 	exp_ratings AS r
				 WHERE 	r.status = 'open' 
				 AND 	r.quarantine != 'y' 
				 AND	r.`".$field."` IS NOT NULL ".$sql
			);
        }
		
		if ($mquery->num_rows() == 0) return '';
	
		// --------------------------------------------
		//	Total Votes Submitted
		// --------------------------------------------
		
		$query = ee()->db->query(
			"SELECT COUNT(*) AS count 
			 FROM 	exp_ratings AS r 
			 WHERE 	r.status = 'open' 
			 AND r.rating IS NOT NULL " . $sql );
		
		$total_num_votes = ($query->row('count') == 0) ? 1 : $query->row('count');
		
		/** --------------------------------------------
		/**  Average Votes Per Voting Users
		/**		 - Might restrict to entries voted on in the last X days
		/** --------------------------------------------*/
		/*
		$query = ee()->db->query(
			"SELECT (COUNT(*) / COUNT(DISTINCT IF(rating_author_id = 0, ip_address, rating_author_id))) AS average_count
			 FROM 	`exp_ratings` 
			 WHERE 	quarantine != 'y'"
		);
		
		// This only works for member votes, not non-members.
		// $query = ee()->db->query("SELECT AVG(`count`) AS average_count FROM exp_rating_stats WHERE member_id != 0");
		
		$avg_num_votes = $query->row('average_count');
		
		*/
		
		/** --------------------------------------------
		/**  Vote Ratio for Voting Members
		/**		- Might do something more complicated later with how well reviewed their Ratings are.
		/**		- Might restrict by votes in the last X days
		/** --------------------------------------------*/
		/*
		$vote_ratio = array();
		
		$query = ee()->db->query("SELECT `count`, member_id FROM exp_rating_stats WHERE member_id != 0");
		
		foreach($query->result_array() as $row)
		{
			$vote_ratio[$row['member_id']] = $row['count'] / $avg_num_votes;
		}
		*/
		
		// --------------------------------------------
		//	Vote Ratio for Voting IP Addresses
		// --------------------------------------------
		/*
		$query = ee()->db->query("SELECT COUNT(rating_id) AS count, ip_address
								  FROM exp_ratings
								  WHERE rating_author_id = 0
								  GROUP BY ip_address");
		
		foreach($query->result_array() as $row)
		{
			$vote_ratio[$row['ip_address']] = $row['count'] / $avg_num_votes;
		}
		*/
		
		// --------------------------------------------
		//	Calculate a Raw Score for Entry Based on Weighted Votes (Basically recalculating every voters vote based on vote ratio)
		// --------------------------------------------
		
		$all_votes = 0;
		$all_count = 0;
		
		$raw_scores		= array();
		$votes			= array();
		$score_totals	= array();
		$score_ratios	= array();
				
		foreach($mquery->result_array() as $row)
		{
			$all_count += ( ! empty($row['overall_count'])) ? $row['overall_count'] : 1;
			$all_votes += ( ! empty($row['overall_count'])) ? ($row['overall_count'] * $row['bayesian_field']) : $row['bayesian_field'];
			
			if ( ! isset($votes[$row['entry_id']]))
			{
				$score_totals[$row['entry_id']] = 0;
				$score_ratios[$row['entry_id']]	= 0.0;
				$votes[$row['entry_id']]		= 0;
			}
			
			$votes[$row['entry_id']]		+= ( ! empty($row['overall_count'])) ? $row['overall_count'] : 1;
			
			$score_totals[$row['entry_id']] += ( ! empty($row['overall_count'])) ? ($row['overall_count'] * $row['bayesian_field']) : $row['bayesian_field'];
			$score_ratios[$row['entry_id']] += ( ! empty($row['overall_count'])) ? $row['overall_count'] : 1;
		}
		
		foreach($score_totals as $entry_id => $score_total)
		{
			if ($score_total == 0)
			{
				$raw_scores[$entry_id] = 0;
				continue;
			}
				
			$raw_scores[$entry_id] = ($score_total/$score_ratios[$entry_id]);  // R
		}
		
		// --------------------------------------------
		//	Few More Variables
		// --------------------------------------------
		
		$minimum_votes = 1; // m  (Settable at a future date, perhaps)
		$average_vote  = ($all_votes / $all_count);  // C
		$average_num_votes = (array_sum($votes) / sizeof($votes));  // For all entries
		$average_score = (array_sum($raw_scores) / sizeof($raw_scores)); // For all entries
		
		if (isset(ee()->TMPL) && is_object(ee()->TMPL) && ee()->TMPL->fetch_param('bayesian_average_score') != FALSE)
		{
			$average_score = ee()->TMPL->fetch_param('bayesian_average_score');
		}
		
		/** --------------------------------------------
		/**  Weighted Score
				W = (v / (v + m) ) * R + (m / (v + m)) * C
				W = Weighted Rating
				v = Number of votes (for that entry)
				m = Minimum number of votes
				R = The average score (for that entry, and weighted based on voter's prestige)
				C = The average vote across the entire dataset.
		/** --------------------------------------------*/
		
		$weighted_scores = array();
		$bayesian_rating = array();
		
		foreach($raw_scores as $entry_id => $raw_score)
		{
			if ($raw_score == 0)
			{
				$bayesian_rating[$entry_id] = 0;
				continue;
			}
		
			//$weighted_scores[$entry_id] =	(($votes[$entry_id] / ($votes[$entry_id] + $minimum_votes)) * $raw_score) +
			//								(($minimum_votes / ($votes[$entry_id] + $minimum_votes)) * $average_vote);
											
			$bayesian_rating[$entry_id] =	(($average_num_votes * $average_score) + ($votes[$entry_id] * $raw_score)) /
											($average_num_votes + $votes[$entry_id]);
			
			/*
			echo "{$entry_id} : (($average_num_votes * $average_score) + ($votes[$entry_id] * $raw_score)) /
											($average_num_votes + $votes[$entry_id])\n\n";
			*/
			
		}
		
		//arsort($weighted_scores);
		arsort($bayesian_rating);
		
		//print_r($bayesian_rating);
		
		return 'FIELD(r.entry_id, '.implode(',', array_keys($bayesian_rating)).', NULL) ';
	}
	// END bayesian_rating_field()
		
	// --------------------------------------------------------------------

	/**
	 *	Parse Rating Stats
	 *
	 *	@access		public
	 *	@param		string
	 *	@param		array
	 *	@return		string
	 */

	function parse_rating_stats( $tagdata = '', $row = array() )
	{
		// ----------------------------------------
		//	Fetch language file.
		// ----------------------------------------

		ee()->lang->loadfile('rating');

		// ----------------------------------------
		//	Let's set the decimal precision and
		//	format.
		// ----------------------------------------

		$precision		= ( ee()->TMPL->fetch_param('precision') ) ? ee()->TMPL->fetch_param('precision'): '0';
		$g_precision	= ( $precision > 0 ) ? 2: 0;
		$thousands		= ( ee()->TMPL->fetch_param('thousands') ) ? ee()->TMPL->fetch_param('thousands'): ',';
		$fractions		= ( ee()->TMPL->fetch_param('fractions') ) ? ee()->TMPL->fetch_param('fractions'): '.';

		// ----------------------------------------
		//	Form name
		// ----------------------------------------

		$collection	= ( ee()->TMPL->fetch_param('collection') ) ? ee()->TMPL->fetch_param('collection'): 'all';

		// ----------------------------------------
		//	Set entry id
		// ----------------------------------------

		$this->entry_id	= ( count( $row ) != 0 ) ? $row['entry_id'] : $this->entry_id();

		// ----------------------------------------
		//	Refresh fields_stats and fail if empty
		// ----------------------------------------

		if ( $this->fields_stats( $this->entry_id, $collection) === FALSE)
		{
			return $this->no_results();
		}

		// ----------------------------------------
		//	Tagdata
		// ----------------------------------------

		$tagdata	= ( $tagdata == '' ) ? ee()->TMPL->tagdata: $tagdata;

		// ----------------------------------------
		//	Parse conditionals
		// ----------------------------------------

		$cond		= $this->fields_stats[$this->entry_id];

		$tagdata	= ee()->functions->prep_conditionals( $tagdata, $cond );

		// ----------------------------------------
		//  Fetch all the date-related variables
		// ----------------------------------------

		$dates		= array('entry_date', 'recent_rating_date');

		// ----------------------------------------
		//  Parse dates
		// ----------------------------------------

		foreach ($dates as $val)
		{
			if (preg_match("/".LD.$val."\s+format=[\"'](.*?)[\"']".RD."/s", $tagdata, $match))
			{
				$str	= $match['1'];

				$codes	= ee()->localize->fetch_date_params( $match['1'] );

				foreach ( $codes as $code )
				{
					$str	= str_replace( $code, ee()->localize->convert_timestamp( $code, $cond[$val], TRUE ), $str );
				}

				$tagdata	= str_replace( $match['0'], $str, $tagdata );
			}
		}

		// ----------------------------------------
		//	Check for rating scale
		// ----------------------------------------
		
		$scale	= 5;

		if ( ee()->TMPL->fetch_param('scale') !== FALSE)
		{
			$scale = ee()->TMPL->fetch_param('scale');
			
			if ( $scale != '5' AND $scale != '10' )
			{
				$scale	= 5;
			}
		}

		// ----------------------------------------
		//  Load Theme
		// ----------------------------------------

		$theme		= ( ee()->TMPL->fetch_param('theme') ) ? ee()->TMPL->fetch_param('theme'): 'default';

		$image_url	= ee()->config->slash_item('theme_folder_url') . 
						(APP_VER >= 2.0 ? 'third_party/' : '') . 
						'rating/'.trim($theme, '/').'/images/';

		// ----------------------------------------
		//	Parse variables
		// ----------------------------------------

		foreach ( ee()->TMPL->var_single as $key => $var )
		{
			// ----------------------------------------
			//	Parse other variables
			// ----------------------------------------

			if ( isset($this->fields_stats[$this->entry_id][$key]))
			{
				// We DON'T want to use number_format() for any "_count"  or "_sum" variables,
				// e.g. overall_count. That would be silly!
				
				if ( substr( $key, -6 )   == '_count' OR
					 substr( $key, 0, 6 ) == 'count_' OR
					 substr( $key, -4 )   == '_sum' OR
					 substr( $key, 0, 4 ) == 'sum_' OR
					 $this->fields_stats[$this->entry_id][$key] == '')
				{
					$tagdata	= ee()->TMPL->swap_var_single( $key, $this->fields_stats[$this->entry_id][$key], $tagdata );
				}
				else
				{
					$tagdata	= ee()->TMPL->swap_var_single( $key, number_format( $this->fields_stats[$this->entry_id][$key], $precision, $fractions, $thousands ), $tagdata );
				}
			}

			// ----------------------------------------
			//	Parse graphics - stars_avg_rating
			// ----------------------------------------

			if ( $scale AND stristr( $key, '_avg' ) )
			{
				$standard	= preg_replace( "/^stars_|^circles_|^bar_/", "", $key );

				if ( isset($this->fields_stats[$this->entry_id][$standard]))
				{
					// ----------------------------------------
					//	Determine type
					// ----------------------------------------
					
					$type = 'stars';

					if (strncmp('circles_', $key, strlen('circles_')) == 0)
					{
						$type = 'circles';
					}
					elseif (strncmp('bar_', $key, strlen('bar_')) == 0)
					{
						$type = 'circles';
					}

					// ----------------------------------------
					//	Parse images
					// ----------------------------------------
					
					if ($this->fields_stats[$this->entry_id][$standard] == '')
					{
						$tagdata	= ee()->TMPL->swap_var_single( $key, '', $tagdata );
					}
					else
					{
						$tagdata	= ee()->TMPL->swap_var_single( $key, $this->parse_overall_avg( $this->fields_stats[$this->entry_id][$standard], $g_precision, $scale, $image_url, $type ), $tagdata );
					}
				}
			}
		}

		// ----------------------------------------
		//	Return
		// ----------------------------------------

		return $tagdata;
	}
	//	End parse rating stats


	// --------------------------------------------------------------------

	/**
	 *	Rating Submission Form
	 *
	 *	@access		public
	 *	@param		string|bool
	 *	@param		string
	 *	@return		string
	 */

	public function form()
	{	
		// --------------------------------------------
        //	Entry ID or Rating ID?
        // --------------------------------------------

		$edit = ( ee()->TMPL->fetch_param('edit') == 'yes' ) ? TRUE: FALSE;

		if ($edit === FALSE )
		{
			if ( ! $this->entry_id() )
			{
				return;
			}
			
			ee()->TMPL->log_item('Rating Module: Entry ID = "'.$this->entry_id.'"');
		}
		else
		{
			if ($this->rating_id() === FALSE)
			{
				return;
			}
			else
			{
				ee()->TMPL->log_item('Rating Module: Rating ID = "'.$this->rating_id.'"');
			}
		}

		// --------------------------------------
		//  Form name
		// --------------------------------------

		$collection	= ( ee()->TMPL->fetch_param('collection') ) ? ee()->TMPL->fetch_param('collection'): '';
		
        // --------------------------------------------
        //	Retrieve Entry ID and Channel ID
        // --------------------------------------------
        
        if ($edit === TRUE)
        {
        	$sql = "SELECT ct.entry_id, ct.{$this->sc->db->channel_id}, exp_ratings.*
        			FROM exp_ratings, {$this->sc->db->channel_titles} AS ct
        			WHERE exp_ratings.rating_id = '".ee()->db->escape_str($this->rating_id)."'
        			AND exp_ratings.entry_id = ct.entry_id ";
        }
        else
        {
        	$sql = "SELECT ct.entry_id, ct.{$this->sc->db->channel_id}
        			FROM {$this->sc->db->channel_titles} AS ct
        			WHERE ct.entry_id = '".ee()->db->escape_str($this->entry_id)."' ";
        }
		
		if ($channel = ee()->TMPL->fetch_param('channel') OR $channel = ee()->TMPL->fetch_param($this->sc->channel))
		{
			$sql	.= ee()->functions->sql_andor_string($channel, 'ct.'.$this->sc->db->channel_name);
		}

		$query = ee()->db->query($sql);

		ee()->TMPL->log_item('Rating Module: SQL = "'.$sql.'"');

		ee()->TMPL->log_item('Rating Module: Number of Rows = "'.$query->num_rows().'"');

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}
		
		$this->entry_id = $query->row('entry_id');
		
		// --------------------------------------------
        //	Enabled Channel?
        // --------------------------------------------
		
		if ( ! in_array('all', $this->preference('enabled_channels')) && ! in_array($query->row($this->sc->db->channel_id), $this->preference('enabled_channels')))
		{
			return FALSE;
		}
		
		/** --------------------------------------------
        /**  Edit Mode - SuperAdmin or Owner of Rating
        /**	 New Rating - SuperAdmin or Member Group Can Post Ratings
        /** --------------------------------------------*/
        
        if (ee()->session->userdata['group_id'] != 1)
        {
			if ($edit === TRUE )
			{
				if ($query->row('rating_author_id') == 0 OR $query->row('rating_author_id') != ee()->session->userdata['member_id'])
				{
					return FALSE;
				}
	
				$this->entry_id	= $query->row('entry_id');
			}
			elseif( ! in_array(ee()->session->userdata['group_id'], $this->preference('can_post_ratings')))
			{
				return FALSE;
			}
		}
		
		$tagdata = ee()->TMPL->tagdata;
		
		// ----------------------------------------
		// 'rating_form_tagdata' hook.
		//  - Modify, add, etc. something to the rating form
		//
			if (isset(ee()->extensions->extensions['rating_form_tagdata']))
			{
				$tagdata = ee()->extensions->call('rating_form_tagdata', $tagdata);
				if (ee()->extensions->end_script === TRUE) return;
			}
		//
		// ----------------------------------------


		// --------------------------------------------
        //	Entry Already Rated by User? - Check via Cookie and DB
        // --------------------------------------------
		
		$duplicate = $this->already_rated($this->entry_id );

		// ----------------------------------------
		//   Conditionals
		// ----------------------------------------

		$cond					= $query->row_array();
		$cond['duplicate']		= $duplicate['duplicate'];
		$cond['not_duplicate']	= !$duplicate['duplicate'];
		$cond['first_rating']	= $duplicate['first_rating'];
		$cond['logged_in']		= (ee()->session->userdata['member_id'] == 0) ? 'FALSE' : 'TRUE';
		$cond['logged_out']		= (ee()->session->userdata['member_id'] != 0) ? 'FALSE' : 'TRUE';
		
		if ($this->preference('use_captcha') == 'n')
		{
			$cond['captcha']	= 'FALSE';
		}
		else
		{
			$cond['captcha'] =  (ee()->config->item('captcha_require_members') == 'y'  ||
								(ee()->config->item('captcha_require_members') == 'n' AND ee()->session->userdata['member_id'] == 0)) ? 'TRUE' : 'FALSE';
		}

		$tagdata = ee()->functions->prep_conditionals($tagdata, $cond );

	   	// ----------------------------------------
		//  Single Variables
		// ----------------------------------------
		
		ee()->load->helper('form');

		foreach($duplicate['user_ratings'] as $k => $v )
		{
			$tagdata = ee()->TMPL->swap_var_single( "user_rating_" . $k, $v, $tagdata );
		}
		
		$tagdata = ee()->TMPL->swap_var_single( 'first_rating', $duplicate['first_rating'], $tagdata );

		foreach (ee()->TMPL->var_single as $key => $val)
		{
			// ----------------------------------------
			//  parse {name}
			// ----------------------------------------

			if ($key == 'name')
			{
				if ( isset($cond['name']))
				{
					$name = $cond['name'];
				}
				else
				{
					$name	= (ee()->session->userdata['screen_name'] != '') ? ee()->session->userdata['screen_name'] : ee()->session->userdata['username'];
				}
				
				$name		= ( ! isset($_POST['name'])) ? $name : $_POST['name'];

				$tagdata	= ee()->TMPL->swap_var_single($key, form_prep($name), $tagdata);
			}

			// ----------------------------------------
			//  parse {email}
			// ----------------------------------------

			if ($key == 'email')
			{
				if ( isset($cond['email']))
				{
					$email = $cond['email'];
				}
				else
				{
					$email = ee()->session->userdata['email'];
				}
			
				$email		= ( ! isset($_POST['email'])) ? $email : $_POST['email'];

				$tagdata	= ee()->TMPL->swap_var_single($key, form_prep($email), $tagdata);
			}

			// ----------------------------------------
			//  parse {url}
			// ----------------------------------------

			if ($key == 'url')
			{
				if ( isset($cond['url']))
				{
					$url = $cond['url'];
				}
				else
				{
					$url = ee()->session->userdata['url'];
				}
			
				$url		= ( ! isset($_POST['url'])) ? $url : $_POST['url'];

				if ($url == '')
					$url = 'http://';

				$tagdata	= ee()->TMPL->swap_var_single($key, form_prep($url), $tagdata);
			}

			// ----------------------------------------
			//  parse {location}
			// ----------------------------------------

			if ($key == 'location')
			{
				if ( isset($cond['location']))
				{
					$location = $cond['location'];
				}
				else
				{
					$location = ee()->session->userdata['location'];
				}
			
				$location	= ( ! isset($_POST['location'])) ? $location : $_POST['location'];

				$tagdata	= ee()->TMPL->swap_var_single($key, form_prep($location), $tagdata);
			}

			// ----------------------------------------
			//  parse {rating}
			// ----------------------------------------

			if ($key == 'rating')
			{

				if( isset( $cond['rating'] ) )
				{
					$rating = $cond['rating'];
				}
				else
				{
					$rating = '';
				}

				$comment	= ( ! isset($_POST['rating'])) ? $rating : $_POST['rating'];

				$tagdata	= ee()->TMPL->swap_var_single($key, $rating, $tagdata);
			}

			// ----------------------------------------
			//  parse {captcha_word}
			// ----------------------------------------

			if ($key == 'captcha_word')
			{
				$tagdata	= ee()->TMPL->swap_var_single($key, '', $tagdata);
			}

			// ----------------------------------------
			//  parse {save_info}
			// ----------------------------------------

			if ($key == 'save_info')
			{
				$save_info	= ( ! isset($_POST['save_info'])) ? '' : $_POST['save_info'];

				$notify		= ( ! isset(ee()->session->userdata['notify_by_default'])) ? ee()->input->cookie('save_info') : ee()->session->userdata['notify_by_default'];

				$checked	= ( ! isset($_POST['PRV'])) ? $notify : $save_info;

				$tagdata	= ee()->TMPL->swap_var_single($key, ($checked == 'yes') ? 'checked="checked"' : '', $tagdata);
			}

			// ----------------------------------------
			//  parse {notify_me}
			// ----------------------------------------

			if ($key == 'notify_me')
			{
				$checked	= '';

				if ( ! isset($_POST['PRV']))
				{
					if (ee()->input->cookie('notify_me'))
					{
						$checked	= ee()->input->cookie('notify_me');
					}

					if (isset(ee()->session->userdata['notify_by_default']))
					{
						$checked	= (ee()->session->userdata['notify_by_default'] == 'y') ? 'yes' : '';
					}
				}

				if (isset($_POST['notify_me']))
				{
					$checked	= $_POST['notify_me'];
				}

				$tagdata = ee()->TMPL->swap_var_single($key, ($checked == 'yes') ? "checked=\"checked\"" : '', $tagdata);
			}

			// ----------------------------------------
			//  parse rating vars if present
			// ----------------------------------------
			
			foreach($query->row_array() as $dvar => $dval)
			{
				$tagdata = ee()->TMPL->swap_var_single($dvar, $dval, $tagdata );
			}
		}

		/** --------------------------------------------
        /**  Parse Variables for {logged_in_x} variables.
        /** 	- The original note says these were not being parsed properly.  Will leave in here for now. -Paul
        /** --------------------------------------------*/

		$logged_in_vars = array(
								'member_id',
								'group_id',
								'group_description',
								'username',
								'screen_name',
								'email',
								'ip_address',
								'location',
								'total_entries',
								'total_comments',
								'private_messages',
								'total_forum_topics',
								'total_forum_replies',
								'total_forum_posts'
								);
			
		foreach ($logged_in_vars as $var)
		{
			$tagdata = ee()->TMPL->swap_var_single( 'logged_in_'.$var, ee()->session->userdata[$var], $tagdata );
		}
		
		// --------------------------------------------
        //	CAPTCHA
        // --------------------------------------------
		
		if ($this->preference('use_captcha') != 'n')
		{
			if (strpos($tagdata, "{captcha}") !== FALSE)
			{
				$tagdata = preg_replace("/{captcha}/", ee()->functions->create_captcha(), $tagdata);
			}
		}
		
		// ----------------------------------------
		//  Create form
		// ----------------------------------------

		$hidden_fields	= array(
								'ACT'	  			=> ee()->functions->fetch_action_id('Rating', 'insert_new_rating'),
								'RET'	  			=> ee()->functions->fetch_current_uri(),
								'URI'	  			=> (ee()->uri->uri_string == '') ? 'index' : ee()->uri->uri_string,
								'PRV'	  			=> (isset($_POST['PRV'])) ? $_POST['PRV'] : ee()->TMPL->fetch_param('preview'),
								'XID'	  			=> (isset($_POST['XID'])) ? $_POST['XID'] : '',
								'entry_id' 			=> $this->entry_id);
								
		$parameters		= array(
								'collection'		=> $collection,
								'status'			=> ( ee()->TMPL->fetch_param('status') == 'closed' ) ? 'closed': 'open',
								'anonymous'			=> ( ee()->TMPL->fetch_param('anonymous') AND ee()->TMPL->fetch_param('anonymous') == 'yes' ) ? 'y': 'n',
								'require_membership'=> ( ee()->TMPL->fetch_param('require_membership') == 'yes' ) ? 'y': 'n',
								'allow_duplicates'	=> ( ee()->TMPL->fetch_param('allow_duplicates') != 'no' ) ? 'yes': 'no',
								'user_template'		=> ( ee()->TMPL->fetch_param('user_template') ) ?  ee()->TMPL->fetch_param('user_template'): '',
								'admin_template'	=> ( ee()->TMPL->fetch_param('admin_template') ) ?  ee()->TMPL->fetch_param('admin_template'): '',
								'owner_template'	=> ( ee()->TMPL->fetch_param('owner_template') ) ?  ee()->TMPL->fetch_param('owner_template'): '',
								'required'			=> ( ee()->TMPL->fetch_param('required') ) ?  ee()->TMPL->fetch_param('required'): '',
								'notify'			=> ee()->TMPL->fetch_param('notify')
							  );
		
		$parameters['output_json'] 					= ($this->check_yes(ee()->TMPL->fetch_param('output_json'))) ? 
													'y' : 'n';  // Default is 'n';
		
		$parameters['ajax_request'] 				= ($this->check_yes(ee()->TMPL->fetch_param('ajax_request'))) ? 
													'y' : 'n';  // Default is 'n'

		if ($edit == TRUE )
		{
			$parameters['rating_id']	= $this->rating_id;
			$parameters['edit_mode']	= 'y';
		}

		if ( ee()->TMPL->fetch_param('return') != '' )
		{
			$hidden_fields['return'] = str_replace( "%%", "", ee()->TMPL->fetch_param('return') );
		}

		if ( ee()->TMPL->fetch_param('template') != '' )
		{
			$parameters['template']	= ee()->TMPL->fetch_param('template');
		}

		if ( ee()->TMPL->fetch_param('first_rating_field') !== FALSE AND ee()->TMPL->fetch_param('first_rating_field') != '' )
		{
			$parameters['first_rating_field'] = ee()->TMPL->fetch_param('first_rating_field');
		}
		
		// --------------------------------------------
        //	Fields - Allowed Values, Minimum/Maximum
        // --------------------------------------------
        
        foreach ($this->data->get_rating_fields_list() as $field_name => $field_label )
		{
			foreach(array('values', 'min', 'max') as $type)
			{
				if ( ee()->TMPL->fetch_param('field:'.$field_name.':'.$type) !== FALSE )
				{
					$parameters['fields'][$field_name][$type] = ee()->TMPL->fetch_param('field:'.$field_name.':'.$type);
				}
			}
		}

		// -------------------------------------------
		// 'rating_form_hidden_fields' hook.
		//  - Add/Remove Hidden Fields for Rating Form
		//
			if (isset(ee()->extensions->extensions['rating_form_hidden_fields']))
			{
				$hidden_fields = ee()->extensions->call('rating_form_hidden_fields', $hidden_fields);
				if (ee()->extensions->end_script === TRUE) return;
			}
		//
		// -------------------------------------------
		
		// -------------------------------------------
		// 'rating_form_parameter_fields' hook.
		//  - Add/Remove Parameters for Rating Form
		//	- Added Rating 3.0.0
		//
			if (isset(ee()->extensions->extensions['rating_form_parameter_fields']))
			{
				$parameters = ee()->extensions->call('rating_form_parameter_fields', $parameters);
				if (ee()->extensions->end_script === TRUE) return;
			}
		//
		// -------------------------------------------
		
		// ----------------------------------------
		//  Save the parameters
		// ----------------------------------------
		
		$hidden_fields['params_id']		= $this->insert_params($parameters);
		$hidden_fields['ajax_request']	= $parameters['ajax_request'];

		// -------------------------------------------
		// 'rating_form_action' hook.
		//  - Modify action="" attribute for rating form
		//  - Added 1.4.2
		//
			if (isset(ee()->extensions->extensions['rating_form_action']))
			{
				$RET = ee()->extensions->call('rating_form_action', $RET);
				if (ee()->extensions->end_script === TRUE) return;
			}
		//
		// -------------------------------------------

		$array = array( 'hidden_fields'	=> $hidden_fields,
						'action'		=> $hidden_fields['RET'],
						'id'			=> ( ee()->TMPL->fetch_param('form_id') ) ? ee()->TMPL->fetch_param('form_id'): 'rating_form'.$this->entry_id,
						'onsubmit'		=> ( ee()->TMPL->fetch_param('onsubmit') ) ? ee()->TMPL->fetch_param('onsubmit'): '');
						
		if ($name = ee()->TMPL->fetch_param('name') OR $name = ee()->TMPL->fetch_param('collection'))
		{
			$array['name'] = $name;
		}

		// --------------------------------------------
        //	Override Form Attributes with form:xxx="" parameters
        // --------------------------------------------
        
        $extra_attributes = array();
        
        if (is_object(ee()->TMPL) AND ! empty(ee()->TMPL->tagparams))
		{
			foreach(ee()->TMPL->tagparams as $key => $value)
			{
				if (strncmp($key, 'form:', 5) == 0)
				{
					if (isset($array[substr($key, 5)]))
					{
						$array[substr($key, 5)] = $value;
					}
					else
					{
						$extra_attributes[substr($key, 5)] = $value;
					}
				}
			}
		}
		
		$res = ee()->functions->form_declaration($array);

		// ----------------------------------------
		//  Parse rating fields var pair
		// ----------------------------------------

		if ( preg_match( "/".LD."rating_fields.*?(backspace=[\"|'](\d+?)[\"|'])?".RD."(.*?)".LD.preg_quote(T_SLASH, '/')."rating_fields".RD."/s", $tagdata, $match))
		{
			$cond['rating_fields']	= FALSE;
			$subs	= array();
			$output	= '';

			if ( count($this->data->get_rating_fields_list()) > 0 )
			{
				$cond['rating_fields']	= TRUE;

				foreach($this->data->get_rating_fields_list() as $key => $val )
				{
					$block	= $match[3];

					foreach ($val as $k => $v )
					{
						if ( stristr($block, LD.$k.RD))
						{
							$block	= str_replace( LD.$k.RD, $v, $block );
						}
					}

					$output	.= trim($block )."\n";
				}

				//	Backspace?
				if ( isset($match[2]) AND $match[2] != '' )
				{
					$output	= substr($output, 0, -$match[2]);
				}
			}

			$tagdata	= str_replace($match[0], '', $tagdata );

			$tagdata	= ee()->functions->prep_conditionals($tagdata, $cond );
		}

		// ----------------------------------------
		//  Add class
		// ----------------------------------------

		if ($class = ee()->TMPL->fetch_param('class') OR $class = ee()->TMPL->fetch_param('form_class') )
		{
			$res	= str_replace( "<form", "<form class=\"".form_prep($class)."\"", $res );
		}

		// ----------------------------------------
		//  Add title
		// ----------------------------------------

		if ($form_title = ee()->TMPL->fetch_param('form_title') )
		{
			$res	= str_replace( "<form", "<form title=\"".form_prep($form_title)."\"", $res );
		}

		// ----------------------------------------
		//  Return
		// ----------------------------------------

		$res	.= stripslashes($tagdata);
		$res	.= "</form>";
		
		// ----------------------------------------
		//	Add <form> attributes from 
		// ----------------------------------------
		
		$allowed = array(
			'accept', 'accept-charset', 'enctype', 'method', 'action', 'name', 'target', 'class', 'dir', 'id', 'lang', 'style', 'title', 'onclick', 'ondblclick', 'onmousedown', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onkeydown', 'onkeyup', 'onkeypress', 'onreset', 'onsubmit'
		);
		
		foreach($extra_attributes as $key => $value)
		{
			if ( in_array($key, $allowed) == FALSE AND strncmp($key, 'data-', 5) != 0) continue;
			
			$res = str_replace( "<form", '<form '.$key.'="'.htmlspecialchars($value).'"', $res );
		}

		return str_replace('&#47;', '/', $res);
	}
	/* END form() */


	// ----------------------------------------
	//  Insert new rating
	// ----------------------------------------

	public function insert_new_rating()
	{
		// --------------------------------------------
        //	Allowed to Submit Ratings?
        // --------------------------------------------
		
		if ( ! in_array(ee()->session->userdata['group_id'], $this->preference('can_post_ratings')))
		{
			return $this->fetch_error( ee()->lang->line('not_authorized') );
		}
		
		// ----------------------------------------
		//	Is the user banned?
		// ----------------------------------------

		if (ee()->session->userdata['is_banned'] == TRUE)
		{
			return $this->fetch_error( ee()->lang->line('not_authorized') );
		}
		
		// ----------------------------------------
		//	Are the IP address and User Agent required?
		// ----------------------------------------

		if (ee()->config->item('require_ip_for_posting') == 'y')
		{
			if (ee()->input->ip_address() == '0.0.0.0' || ee()->session->userdata['user_agent'] == "")
			{
				return $this->fetch_error( ee()->lang->line('not_authorized') );
			}
		}

		// ----------------------------------------
		// Is the nation of the user banned?
		// ----------------------------------------

		ee()->session->nation_ban_check();

		// ----------------------------------------
		//  Blacklist/Whitelist Check
		// ----------------------------------------

		if (ee()->blacklist->blacklisted == 'y' && ee()->blacklist->whitelisted == 'n')
		{
			return $this->fetch_error( ee()->lang->line('not_authorized') );
		}
		
		// --------------------------------------------
        //	Some Manner of Default Encoding Work for POST vars
        // --------------------------------------------
		
		$fields = array('name', 'email', 'url', 'location', 'rating');

		foreach ($fields as $val)
		{
			if (isset($_POST[$val]))
			{
				$_POST[$val] = ee()->functions->encode_ee_tags($_POST[$val]);
				$_POST[$val] = str_replace("{", "&#123;", $_POST[$val]);
				$_POST[$val] = str_replace("}", "&#125;", $_POST[$val]);
			}
		}
		
		// --------------------------------------------
        //	Basic POST security checks and defaults
        // --------------------------------------------
	
		$_POST		= ee()->security->xss_clean($_POST );

		$edit		= ( $this->param('edit_mode') == 'y' ) ? TRUE: FALSE;

		$default	= array('name', 'email', 'url', 'location', 'entry_id');

		foreach ($default as $val)
		{
			if ( ! isset($_POST[$val]))
			{
				$_POST[$val] = '';
			}
		}

		// ----------------------------------------
		// Fetch the rating language pack
		// ----------------------------------------

		ee()->lang->loadfile('rating');

		// ----------------------------------------
		//	Are we editing?
		// ----------------------------------------

		if ($edit == TRUE )
		{
			if ( ! ctype_digit($this->param('rating_id')))
			{
				return $this->fetch_error( ee()->lang->line('no_rating_id') );
			}
			else
			{
				$this->rating_id = $this->param('rating_id');
			}
		}
		elseif ( ! ctype_digit($_POST['entry_id']))
		{
			return $this->fetch_error( ee()->lang->line('no_entry_id') );
		}
		else
		{
			$this->entry_id	= $_POST['entry_id'];
		}

		// ----------------------------------------
		//  Prohibited form name?
		// ----------------------------------------

		if ($this->param('collection') !== FALSE AND in_array($this->param('collection'), array('all')))
		{
			return $this->fetch_error( str_replace( "%n", $this->param('collection'), ee()->lang->line('bad_collection')));
		}

		// ----------------------------------------
		//	Has this user submitted already?
		// ----------------------------------------
		
		$duplicate = FALSE;

		if ($edit == FALSE)
		{
			$previous_data = $this->already_rated($this->entry_id);
										
			if ($previous_data['duplicate'] == TRUE)
			{
				$duplicate = TRUE;

				if ($this->param('allow_duplicates') != 'yes')
				{
					return $this->fetch_error( ee()->lang->line('no_duplicates') );
				}
			}
		}
		
		// ----------------------------------------
		//	If editing, is this not the owner or super admin?
		// ----------------------------------------

		if ($edit == TRUE )
		{
			$query	= ee()->db->query( "SELECT entry_id, rating_author_id
										FROM exp_ratings
										WHERE rating_id = '".ee()->db->escape_str($this->rating_id)."'" );

			if ($query->num_rows() == 0 )
			{
				return $this->fetch_error( ee()->lang->line('no_rating_id') );
			}

			if ( ($query->row('rating_author_id') != ee()->session->userdata['member_id'] OR $query->row('rating_author_id') == '0' ) AND ee()->session->userdata['group_id'] != '1' )
			{
				return $this->fetch_error( ee()->lang->line('not_owner') );
			}

			$this->entry_id	= $query->row('entry_id');
		}

		// -------------------------------------------
		// 'insert_rating_start' hook.
		//  - Allows complete rewrite of rating submission routine.
		//  - Or could be used to modify the POST data before processing
		//
			$edata = ee()->extensions->call('insert_rating_start');
			if (ee()->extensions->end_script === TRUE) return;
		//
		// -------------------------------------------

		// ----------------------------------------
		// Fetch Channel/Weblog preferences
		// ----------------------------------------

		$sql = "SELECT	{$this->sc->db->channel_titles}.title,
						{$this->sc->db->channel_titles}.url_title,
						{$this->sc->db->channel_titles}.{$this->sc->db->channel_id},
						{$this->sc->db->channel_titles}.entry_date,
						{$this->sc->db->channels}.{$this->sc->db->channel_title}
				FROM	{$this->sc->db->channel_titles}, {$this->sc->db->channels}
				WHERE	{$this->sc->db->channel_titles}.{$this->sc->db->channel_id} = {$this->sc->db->channels}.{$this->sc->db->channel_id}
				AND		{$this->sc->db->channel_titles}.entry_id = '".ee()->db->escape_str($this->entry_id)."'";

		// -------------------------------------------
		// 'insert_rating_preferences_sql' hook.
		//  - Rewrite or add to the rating preference sql query
		//  - Could be handy for rating/weblog/channel restrictions
		//
			if (isset(ee()->extensions->extensions['insert_rating_preferences_sql']))
			{
				$sql = ee()->extensions->call('insert_rating_preferences_sql', $sql);
				if (ee()->extensions->end_script === TRUE) return $edata;
			}
		//
		// -------------------------------------------

		$query	= ee()->db->query($sql);
		
		// --------------------------------------------
        //	Ratings Allowed for Channel/Weblog?
        // --------------------------------------------

		if ($query->num_rows() == 0)
		{
			return $this->fetch_error( ee()->lang->line('ratings_not_allowed') );
		}
		
		if ( ! in_array('all', $this->preference('enabled_channels')) && ! in_array($query->row($this->sc->db->channel_id), $this->preference('enabled_channels')))
		{
			return $this->fetch_error( ee()->lang->line('ratings_not_allowed') );
		}

		// ----------------------------------------
		// Assign data
		// ----------------------------------------

		$entry_title			= $query->row('title');
		$url_title				= $query->row('url_title');
		$channel_title		 	= $query->row($this->sc->db->channel_title);
		$channel_id		  		= $query->row($this->sc->db->channel_id);
		$rating_moderate		= (ee()->session->userdata['group_id'] == 1 OR ee()->session->userdata['exclude_from_moderation'] == 'y') ? 'n' : $query->row('rating_moderate');
		$author_id				= $query->row('author_id');
		
		//@todo - Based off parameters now.
		$author_notify			= $this->param('notify_authors');
		
		// ----------------------------------------
		//  Apply logged-in values if needed
		// ----------------------------------------
		
		if ($_POST['name'] == '')
		{
			$_POST['name'] = ee()->session->userdata['screen_name'];
		}
		if ($_POST['email'] == '')
		{
			$_POST['email'] = ee()->session->userdata['email'];
		}
		if ($_POST['url'] == '')
		{
			$_POST['url'] = ee()->session->userdata['url'];
		}
		if ($_POST['location'] == '')
		{
			$_POST['location'] = ee()->session->userdata['location'];
		}

		// ----------------------------------------
		//  Start error trapping
		// ----------------------------------------

		$error = array();

		// ----------------------------------------
		//  Is membership required to post?
		// ----------------------------------------

		if ($this->param('require_membership') == 'y' )
		{
			// Not logged in

			if ( ee()->session->userdata['member_id'] == 0 )
			{
				return $this->fetch_error( ee()->lang->line('must_be_member') );
			}

			// Membership is pending

			if ( ee()->session->userdata['group_id'] == 4 )
			{
				return $this->fetch_error( ee()->lang->line('account_not_active') );
			}
		}
		else
		{
			// ----------------------------------------
			//  Missing name?
			// ----------------------------------------

			if ($_POST['name'] == '' && $this->param('anonymous') != 'y')
			{
				$error[] = ee()->lang->line('missing_name');
			}

			// -------------------------------------
			//  Is name banned?
			// -------------------------------------

			if ( ee()->session->ban_check( 'screen_name', $_POST['name']))
			{
				$error[] = ee()->lang->line('name_not_allowed');
			}

			// ----------------------------------------
			//  Missing or invalid email address
			// ----------------------------------------

			if ($this->preference('require_email') != 'n' && $this->param('anonymous') != 'y' )
			{
				ee()->load->helper('email');
			
				if ($_POST['email'] == '')
				{
					$error[] = ee()->lang->line('missing_email');
				}
				elseif ( ! valid_email($_POST['email']))
				{
					$error[] = ee()->lang->line('invalid_email');
				}
			}
		}

		// ----------------------------------------
		//  Required custom fields?
		// ----------------------------------------
		
		if ($this->param('required') != '' )
		{
			$required_fields	= preg_split( "/,|\|/", $this->param('required') );

			$labels = $this->data->get_rating_fields_list();

			$labels['name']		= ee()->lang->line('name');
			$labels['email']	= ee()->lang->line('email');
			
			// --------------------------------------------
			//	Check that Required Fields have Data
			// --------------------------------------------

			foreach ($required_fields as $val )
			{
				ee()->load->helper('email');
				
				if ($val == 'email' && ! valid_email($_POST[$val]) )
				{
					$error[] = ee()->lang->line('invalid_email');
				}
				elseif( empty($_POST[$val]))
				{
					$error[] = ee()->lang->line('required_field_was_empty');
				}
			}
			// End empty check
		}
		// Even if no fields are required, they have to submit SOMEthing
		else
		{
			$all_empty = TRUE;

			foreach ($this->data->get_rating_fields_list() as $field_name => $field_label )
			{
				if (isset($_POST[$field_name]))
				{
					$all_empty = FALSE;
					break;
				}
			}

			if ($all_empty === TRUE )
			{
				$error[] = ee()->lang->line('required_field_was_empty');
			}
		}

		// -------------------------------------
		//  Is email banned?
		// -------------------------------------

		if ($_POST['email'] != '' )
		{
			if ( ee()->session->ban_check( 'email', $_POST['email']))
			{
				$error[] = ee()->lang->line('banned_email');
			}
		}
		
		// ----------------------------------------
		//  Validate Rating Fields
		// ----------------------------------------

		foreach($this->data->get_rating_fields_data() as $name => $val )
		{
			if ( ! empty($_POST[$val['field_name']]))
			{
				// ----------------------------------------
				//  Numeric?
				// ----------------------------------------

				if ( $val['field_type'] == 'number' && ! ctype_digit($_POST[ $val['field_name'] ]))
				{
					$error[] = str_replace( "%label%", $val['field_label'] ,ee()->lang->line('field_not_numeric') );
				}

				// ----------------------------------------
				//  Length
				// ----------------------------------------

				if ( strlen($_POST[ $val['field_name'] ]) > $val['field_maxl'] AND $val['field_type'] != 'textarea' )
				{
					$error[]	= str_replace(array('%label%','%x%'), array($val['field_label'],$val['field_maxl']), ee()->lang->line('field_too_long') );
				}
				
				// --------------------------------------------
				//	Parameter Restrictions - Allowed Values, Minimum/Maximum
				// --------------------------------------------
				
				if (($allowed = $this->param('fields', $val['field_name'])) !== FALSE)
				{
					if ( isset($allowed['values']))
					{	
						if ( ! in_array($_POST[$val['field_name']], explode('|', $allowed['values'])))
						{
							$error[] = str_replace( "%label%", $val['field_label'] ,ee()->lang->line('field_invalid_value') );
						}
					}
					
					if ($val['field_type'] == 'number')
					{					
						if ( isset($allowed['min']))
						{
							if ($_POST[$val['field_name']] < floor($allowed['min']))
							{
								$error[] = str_replace( "%label%", $val['field_label'] ,ee()->lang->line('field_invalid_value') );
							}
						}
						
						if ( isset($allowed['max']))
						{
							if ($_POST[$val['field_name']] > ceil($allowed['max']))
							{
								$error[] = str_replace( "%label%", $val['field_label'] ,ee()->lang->line('field_invalid_value') );
							}
						}
					}
				}
				
			}
		}

		// ----------------------------------------
		//  Do we have errors to display?
		// ----------------------------------------

		if ( count($error) > 0 )
		{
			return $this->fetch_error($error );
		}

		// ----------------------------------------
		// Do we require captcha?
		// ----------------------------------------

		if ($this->preference('use_captcha') != 'n')
		{
			if ( ee()->config->item('captcha_require_members') == 'y'  || 
				(ee()->config->item('captcha_require_members') == 'n' AND ee()->session->userdata['member_id'] == 0))
			{
				if ($this->check_captcha() == FALSE)
				{
					return $this->fetch_error( ee()->lang->line('captcha_incorrect') );
				}
			}
		}
		
		// --------------------------------------------
        //	Check Secure Forms
        // --------------------------------------------
		
		if ($this->check_secure_forms() === FALSE)
		{
			return $this->fetch_error( ee()->lang->line('form_already_submitted') );
		}

		// ----------------------------------------
		//  Build the data array
		// ----------------------------------------

		$data	= array(
						'channel_id'		=> $channel_id,
						'entry_id'			=> $this->entry_id,
						'collection'		=> ($this->param('collection') !== FALSE && $this->param('collection') != '' ) ? $this->param('collection') : '',
						'notify'			=> ( ee()->input->get_post('notify_me') ) ? 'y': 'n'
					 );

		if ($edit == FALSE )
		{
			$data['rating_author_id']	= ee()->session->userdata['member_id'];
			$data['rating_date']		= ee()->localize->now;
			$data['ip_address']			= ee()->input->ip_address();
			$data['status']				= ($this->param('status') == 'closed' ) ? 'closed' : 'open';
			$data['duplicate']			= ($duplicate == TRUE) ? 'y' : 'n';
		}
		else
		{
			$data['edit_date']			= ee()->localize->now;
			$data['status']				= ($this->param('status') == 'closed' ) ? 'closed' : 'open';
		}

		foreach ($default as $val )
		{
			if ( isset($_POST[$val]))
			{
				$data[$val]	= $_POST[$val];
			}
		}
		
		// --------------------------------------------
        //	Add on Rating Fields - Validated Earlier
        // --------------------------------------------

		foreach($this->data->get_rating_fields_list() as $name => $label )
		{
			if (isset($_POST[$name]))
			{
				$data[$name] = (is_array($_POST[$name])) ? 
							   implode('|', ee()->security->xss_clean($_POST[$name])) : 
							   ee()->security->xss_clean($_POST[$name]);
			}
		}
		
		// ----------------------------------------
		// 'insert_rating_insert_array' hook.
		//  - Modify any of the soon to be inserted
		//	values
		//
			if (isset(ee()->extensions->extensions['insert_rating_insert_array']))
			{
				$data = ee()->extensions->call('insert_rating_insert_array', $data);
				if (ee()->extensions->end_script === TRUE) return $edata;
			}
		//
		// ----------------------------------------

		// ----------------------------------------
		//	Insert or update
		// ----------------------------------------
		
		if ($edit == TRUE )
		{
			ee()->db->query(ee()->db->update_string('exp_ratings', $data, array('rating_id' => $this->rating_id)));
		}
		else
		{
			ee()->db->query(ee()->db->insert_string('exp_ratings', $data));
			$this->rating_id = ee()->db->insert_id();
		}
		
		/** --------------------------------------------
        /**  A Cookie Tracking per Member ID
        /**		- Tracking Entry ID and "rating" field value (or alternative field with first_rating_field="" parameter set)
        /** --------------------------------------------*/
		
		$first_rating_field = ( ee()->input->get_post('first_rating_field') !== FALSE AND ee()->input->get_post('first_rating_field') != '' ) 
								? ee()->input->get_post('first_rating_field') 
								: 'rating';
		
		if (isset($data[$first_rating_field]))
		{
			$id_string = (ee()->input->cookie( 'rating_rated_0') === FALSE) ? '' : ee()->input->cookie( 'rating_rated_0');
			
			// --------------------------------------------
			//	Check to see if entry was previously rated.  If not, add and reset cookie.
			// --------------------------------------------
		
			if ( empty($id_string) OR ! preg_match("/,{$this->entry_id}\|([0-9]+),/", ','.$id_string.',', $match))
			{
				$id_string = trim($id_string, ',|').','.$this->entry_id . '|' . $data[$first_rating_field];
				ee()->functions->set_cookie( 'rating_rated_0', $id_string, 60*60*24*365 );
			}
		}
		
		// --------------------------------------------
        //	Status Open?  Update Stats!
        // --------------------------------------------
	
		if ($data['status'] == 'open')
		{
			// --------------------------------------------
			//	Update Member's Statistics 
			// --------------------------------------------

			if ( ee()->session->userdata['member_id'] != 0 AND $edit == FALSE )
			{
				$this->actions()->update_member_stats(ee()->session->userdata['member_id']);
			}
			
			// --------------------------------------------
			//	Update Channel Statistics 
			// --------------------------------------------
			
			$this->actions()->update_channel_stats($data['channel_id']);

			// ----------------------------------------
			//	Update rating stats
			// ----------------------------------------

			$this->actions()->update_entry_stats($data['entry_id']);
		}
		
		// --------------------------------------------
        //	Typography Class!
        // --------------------------------------------
        
		ee()->load->library('typography');
		
		if (APP_VER >= 2.0)
		{
			ee()->typography->initialize();
		}
		
		ee()->typography->convert_curly = FALSE;
 		ee()->typography->smileys		= FALSE;
 		
 		$recipients = ( ! empty($_POST['email'])) ? array($_POST['email']) : array();

		// --------------------------------------------
        //	Admin Notifications - Set entirely via parameter starting in Rating 3.0
        // --------------------------------------------
        
        $params = array();

		$notify_recipients = ($this->param('notify') === FALSE) ? '' : preg_split("/,|\|/", $this->param('notify'), -1, PREG_SPLIT_NO_EMPTY);
		
		if ( ! empty($notify_recipients))
		{
			$template = ( $this->param('admin_template') !== FALSE && $this->param('admin_template') != '') ? $this->param('admin_template') : 'default_template';

			// ----------------------------------------
			//  Generate message
			// ----------------------------------------

			$msg		= array();
			
			if ( ($msg = $this->fetch_email_template($template)) === FALSE )
			{
				return $this->fetch_error(ee()->lang->line('no_notification_template_found'));
			}
			
			$msg['msg']  	= $msg['message'];

			$msg['name']	= ( ee()->input->get_post('name') AND ee()->input->get_post('name') != '' ) ? ee()->input->get_post('name'): ee()->config->item('webmaster_name');
			$msg['email']	= ( ee()->input->get_post('email') AND ee()->input->get_post('email') != '' ) ? ee()->input->get_post('email'): ee()->config->item('webmaster_email');
			$wordwrap		= ($msg['wordwrap'] == 'y') ? TRUE: FALSE;
			$msg['subject']	= str_replace( LD.'entry_date'.RD, ee()->localize->set_human_time(ee()->localize->now), $msg['subject']);
			$msg['msg']		= str_replace( LD.'entry_date'.RD, ee()->localize->set_human_time(ee()->localize->now), $msg['msg']);

			// ----------------------------------------
			//  Parse conditionals
			// ----------------------------------------

			$data['title']		= $entry_title;	
			$data['url_title']	= $url_title;
			$data['rating_id']	= $this->rating_id;
			
			// -------------------------------------
			//	Invoke parsing capability
			// -------------------------------------
			
			require_once 'addon_builder/parser.addon_builder.php';
			
			// -------------------------------------
			//	Parse Conditionals
			// -------------------------------------
			
			$this->TMPL = new Addon_builder_parser_rating();
			
			foreach($msg as $key => $val )
			{
				$msg[$key]	= $this->TMPL->advanced_conditionals( ee()->functions->prep_conditionals($msg[$key], $data));
			}

			// ----------------------------------------
			//  Parse individual fields
			// ----------------------------------------

			$all_fields	= '';

			$fields		= $this->data->get_rating_fields_data();

			$fields[]	= array( 'field_name' => 'name', 'field_label' => 'Name' );
			$fields[]	= array( 'field_name' => 'email', 'field_label' => 'Email' );
			$fields[]	= array( 'field_name' => 'subject', 'field_label' => 'Subject' );
			$fields[]	= array( 'field_name' => 'entry_id', 'field_label' => 'Entry ID' );
			$fields[]	= array( 'field_name' => 'rating_id', 'field_label' => 'Rating ID' );
			$fields[]	= array( 'field_name' => 'title', 'field_label' => 'Entry Title' );
			$fields[]	= array( 'field_name' => 'url_title', 'field_label' => 'URL Title' );
			$fields[]	= array( 'field_name' => 'rating_author_id', 'field_label' => 'Rating Author ID' );
			
			foreach ($msg as $key => $val )
			{
				foreach ($fields as $name => $arr )
				{
					if ( isset($data[$arr['field_name']]) AND $arr['field_name'] != 'submit')
					{
						$msg[$key]	= str_replace( LD.$arr['field_name'].RD, $data[$arr['field_name']], $msg[$key]);

						// ----------------------------------------
						//	We don't want to concatenate for every time through the main loop.
						// ----------------------------------------

						if ($key == 'msg' )
						{
							$all_fields	.= $arr['field_label'].": ".$data[$arr['field_name']]."\n";
						}
					}
					else
					{
						$msg[$key]	= str_replace( LD.$arr['field_name'].RD, '', $msg[$key]);
					}
				}
			}

			// ----------------------------------------
			//  Parse all fields variable
			// ----------------------------------------

			$msg['msg']	= str_replace( LD.'all_custom_fields'.RD, $all_fields, $msg['msg']);
			
			// --------------------------------------------
			//	Send Email
			// --------------------------------------------
			
			ee()->load->library('email');
			ee()->load->helper('text');
			
			ee()->email->wordwrap = $wordwrap;
			
			foreach ($notify_recipients as $val)
			{
				if ( in_array($val, $recipients) )
				{
					continue;  // No duplicates!
				}
			
				$recipients[] = $val;
			
				ee()->email->initialize();
				ee()->email->from($msg['email'], $msg['name']);	
				ee()->email->to($val); 
				ee()->email->subject($msg['subject']);	
				ee()->email->message(entities_to_ascii($msg['msg']));		
				ee()->email->Send();
			}
			
			// Clear everything
			ee()->email->clear(TRUE);

			unset($msg);
		}
		//	End send admin notifications


		// --------------------------------------------
        //	Entry Author Notification...of Despair...
        // --------------------------------------------

		if ($author_notify == 'y' AND $this->param('owner_template') !== FALSE AND $this->param('owner_template') != '' )
		{
			$query	= ee()->db->query( "SELECT m.email, m.screen_name
										FROM exp_members m
										LEFT JOIN {$this->sc->db->channel_titles} AS t ON t.author_id = m.member_id
										LEFT JOIN exp_ratings AS r ON r.entry_id = t.entry_id
										WHERE r.rating_id = '".ee()->db->escape_str($this->rating_id )."'
										LIMIT 1" );

			if ($query->row('email') != '' )
			{	
				if ( in_array($query->row('email'), $recipients))
				{
					continue;  // No duplicates!
				}
			
				$recipients[] = $query->row('email');
				
				$info	= array(
								'email'					=> $query->row('email'),
								'notification_template'	=> $this->param('owner_template'),
								'extra'					=> $query->row,
								'review'				=> $data['review'],
								'rating'				=> $data['rating'],
								'rating_id'				=> $this->rating_id,
								'entry_date'			=> $data['rating_date'],
								'entry_id'				=> $data['entry_id'],
								'name'					=> $data['name'],
								'rating_author_id'		=> $data['rating_author_id'],
								'url_title'				=> $url_title,
								'title'					=> $entry_title
								);
								
				$this->send_notification($info);
			}
		}

		// --------------------------------------------
        //	Sending a notification email to the users who submitted a rating for entry
        // --------------------------------------------

		if ($this->param('user_template') !== FALSE AND $this->param('user_template') != '' )
		{
			$query = ee()->db->query("	SELECT DISTINCT(email), name as screen_name, rating_id
										FROM exp_ratings AS r
										WHERE r.status = 'open' 
										AND r.notify = 'y'
										AND r.entry_id = '".ee()->db->escape_str($this->entry_id)."' ");
										
			foreach ($query->result_array() as $row)
			{
				if ( in_array($row['email'], $recipients) )
				{
					continue;  // No duplicates!
				}
				
				$recipients[] = $row['email'];

				 $info = array(
					'email'						=> $row['email'],
					'notification_template'		=> $this->param('user_template'),
					'extra'						=> $row,
					'review'					=> $data['review'],
					'rating'					=> $data['rating'],
					'rating_id'					=> $this->rating_id,
					'entry_date'				=> $data['rating_date'],
					'url_title'					=> $url_title,
					'title'						=> $entry_title,
					'entry_id'					=> $data['entry_id'],
					'name'						=> $data['name'],
					'rating_author_id'			=> $data['rating_author_id'],
					'unsubscribe'				=> 'yes',
					'usubscribe_rating_id'		=> $row['rating_id']
					);
			
				$this->send_notification($info);
			}
		}

		// ----------------------------------------
		//  Clear cache files
		// ----------------------------------------

		ee()->functions->clear_caching('all', ee()->functions->fetch_site_index().$_POST['URI']);

		// ----------------------------------------
		//  Set cookies
		// ----------------------------------------

		if ( ee()->input->post('notify_me') == 'y')
		{
			ee()->functions->set_cookie('notify_me', 'yes', 60*60*24*365);
		}
		else
		{
			ee()->functions->set_cookie('notify_me', 'no',  60*60*24*365);
		}

		if (ee()->input->post('save_info'))
		{
			ee()->functions->set_cookie('save_info',	'yes',				60*60*24*365);
			ee()->functions->set_cookie('my_name',		$_POST['name'],	 	60*60*24*365);
			ee()->functions->set_cookie('my_email',		$_POST['email'],	60*60*24*365);
			ee()->functions->set_cookie('my_url',		$_POST['url'],		60*60*24*365);
			ee()->functions->set_cookie('my_location',	$_POST['location'],	60*60*24*365);
		}
		else
		{
			ee()->functions->set_cookie('save_info',	'no', 60*60*24*365);
			ee()->functions->set_cookie('my_name',		'');
			ee()->functions->set_cookie('my_email',		'');
			ee()->functions->set_cookie('my_url',		'');
			ee()->functions->set_cookie('my_location',	'');
		}

		// -------------------------------------------
		// 'insert_rating_end' hook.
		//  - More emails, more processing, different redirect
		//
			$edata = ee()->extensions->call('insert_rating_end', $data, $rating_moderate);
			if (ee()->extensions->end_script === TRUE) return;
		//
		// -------------------------------------------
		
		// ----------------------------------------
		//	Set return
		// ----------------------------------------
		
		if ( ! $return = ee()->input->get_post('return') )
        {
        	$return	= ee()->input->get_post('RET');
        }
		
		if ( preg_match( "/".LD."\s*path=(.*?)".RD."/", $return, $match ) > 0 )
		{
			$return	= ee()->functions->create_url($match['1']);
		}
		elseif ( stristr($return, "http://" ) === FALSE && stristr($return, "https://" ) === FALSE )
		{
			$return	= ee()->functions->create_url($return );
		}
		
		$return	= str_replace("rating_id", $this->rating_id, $return );
		
		$return	= $this->parameter_chars_decode($return);
		
		// -------------------------------------------
		//  Prep cond
		// -------------------------------------------

		$data['failure']	= FALSE;
		$data['success']	= TRUE;
		
		// -------------------------------------------
		//  Prep message
		// -------------------------------------------

		$this->message[0]	= ee()->lang->line('rating_success');
		
		if ( count($this->message ) == 1 )
		{
			$data['message'] = $this->message[0];
		}
		else
		{
			$content  = '<ul>';

			foreach ($this->message as $m )
			{
				$content	.= "<li>".$m."</li>\n";
			}

			$content .= "</ul>";
			
			$data['message'] = $content;
		}
		
		// --------------------------------------------
		//  AJAX Response
		// --------------------------------------------
		
		if ($this->check_yes($this->param('output_json')) && $this->is_ajax_request())
		{
			$this->send_ajax_response(array(
				'success' 	=> TRUE,
				'rating_id'	=> $this->rating_id,
				'heading'	=> ee()->lang->line('rating_success'),
				'message'	=> implode(', ', $this->message),
			));
		}
		
		// -------------------------------------------
		//  Are we using a template?
		// -------------------------------------------

		if ( ! $body = $this->fetch_template( '', $data))
		{
			if ( ee()->input->post('return') != '' )
			{
				ee()->functions->redirect( str_replace( "%%rating_id%%", $this->rating_id, $return));
			}
			
			$data	= array('title' 	=> ee()->lang->line('success'),
							'heading'	=> ee()->lang->line('success'),
							'content'	=> $data['message'],
							'redirect'	=> $return,
							'rate'		=> 10,
							'link'		=> array($return, ee()->lang->line('return'))
						 );

			return ee()->output->show_message($data);
		}
		else
		{
			return ee()->output->_display( $body );
		}
	}
	/* END insert_new_rating() */

	// --------------------------------------------------------------------

	/**
	 *	Insert Review of Rating - LEGACY
	 *
	 *	http://www.solspace.com/docs/detail/rating_insert_rating_review/
	 *
	 *	@access		public
	 *	@return		string
	 */
	 
	public function insert_rating_rev()
	{
		return $this->insert_rating_vote();
	}
	/* END insert_rating_rev() */

	// --------------------------------------------------------------------

	/**
	 *	Insert a Vote for a Rating
	 *
	 *	http://www.solspace.com/docs/detail/rating_insert_rating_review/
	 *
	 *	@access		public
	 *	@return		string
	 */
	 
	public function insert_rating_vote()
	{
		// ----------------------------------------
		// Fetch the rating language pack
		// ----------------------------------------

		ee()->lang->loadfile('rating');

		// ----------------------------------------
		// Is the user banned?
		// ----------------------------------------
		
		if (ee()->session->userdata['is_banned'] == TRUE)
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('banned') );
		}
		
		// ----------------------------------------
		//  Rating id?
		// ----------------------------------------

		if ( ! $rating_id = ee()->TMPL->fetch_param('rating_id') )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('no_rating_id') );
		}
		
		// ----------------------------------------
		// Is this a duplicate?
		// ----------------------------------------
		
		$conds = array();
		
		if (strstr(ee()->TMPL->tagdata, 'already_reviewed') OR strstr(ee()->TMPL->tagdata, 'prior_review_count') OR ee()->TMPL->fetch_param('allow_duplicates') == 'no')
		{
			$priors = $this->already_reviewed(FALSE, $rating_id);
						
			if (isset($priors[$rating_id]))
			{
				$conds['already_reviewed']		= $priors[$rating_id]['already_reviewed'];
				$conds['not_already_reviewed']	= $priors[$rating_id]['not_already_reviewed'];
				$conds['prior_review_count']	= $priors[$rating_id]['prior_review_count'];
			}
			else
			{
				$conds['already_reviewed']		= FALSE;
				$conds['not_already_reviewed']	= TRUE;
				$conds['prior_review_count']	= 0;
			}
			
			if ($conds['already_reviewed'] === TRUE && ee()->TMPL->fetch_param('allow_duplicates') == 'no' )
			{
				return $this->parse_tagdata_message( FALSE, ee()->lang->line('no_duplicates_rating_vote'), $conds );
			}
		}
		
		// ----------------------------------------
		//  Helpful?
		// ----------------------------------------
		
		$helpful = 'y';
		
		if (array_search("no", ee()->uri->segments) OR array_search( "down", ee()->uri->segments))
		{
			$helpful = 'n';
		}

		// ----------------------------------------
		//  Get rating data.
		// ----------------------------------------

		$query	= ee()->db->query( "SELECT r.entry_id, t.{$this->sc->db->channel_id}
									FROM exp_ratings AS r
									LEFT JOIN {$this->sc->db->channel_titles} AS t ON t.entry_id = r.entry_id
									LEFT JOIN {$this->sc->db->channels} AS w ON w.{$this->sc->db->channel_id} = t.{$this->sc->db->channel_id}
									WHERE r.rating_id = '".ee()->db->escape_str($rating_id)."'" );

		if ($query->num_rows() == 0 )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('no_rating_id'), $conds );
		}

		// ----------------------------------------
		//  Prep data
		// ----------------------------------------

		$data						= array();

		$data['rating_id']			= $rating_id;
		$data['entry_id']			= $query->row('entry_id');
		$data['channel_id']			= $query->row($this->sc->db->channel_id);
		$data['ip_address']			= ee()->input->ip_address();
		$data['author_id']			= ee()->session->userdata['member_id'];
		$data['rating_review']		= ( ee()->input->get_post('rating_rev') ) ? ee()->input->get_post('rating_rev'): '';
		$data['rating_helpful']		= $helpful;
		$data['review_date']		= ee()->localize->now;

		// ----------------------------------------
		//  Insert
		// ----------------------------------------

		ee()->db->query( ee()->db->insert_string('exp_rating_reviews', $data) );
		
		// ----------------------------------------
		//	Set a cookie
		// ----------------------------------------
		
		$id_str = '';
		
		$id_str = $data['rating_id'] . '|' . $data['rating_helpful'];
		
		if ($ids = ee()->input->cookie( 'rating_reviewed_'.ee()->session->userdata['member_id']))
		{
			$ids = explode( ',', $ids );
			$length = strlen($data['rating_id']);
			
			foreach ($ids as $k => $id )
			{
				if ( substr($id, 0, $length + 1 ) == $this->entry_id.'|' )
				{
					$ids[$k] = $data['rating_id'] . '|' . $data['rating_helpful'];
					$id_str = implode( ',', $ids );
					break;
				}
			}
			if ( ! $id_str )
			{
				$ids[] = $data['rating_id'] . '|' . $data['rating_helpful'];
				$id_str = implode( ',', $ids );
			}
		}

		ee()->functions->set_cookie( 'rating_reviewed_'.ee()->session->userdata['member_id'], $id_str, 60*60*24*365 );

		// ----------------------------------------
		//  Recount rating rev
		// ----------------------------------------

		$this->recount_rating_reviews($rating_id );

		return $this->parse_tagdata_message( TRUE, ee()->lang->line('rating_rev_successful'), $conds );
	}
	/* END insert_rating_vote() */


	// --------------------------------------------------------------------

	/**
	 *	Statistics for the Votes on a Rating - LEGACY
	 *
	 *	http://www.solspace.com/docs/detail/rating_rating_review_stats/
	 *
	 *	@access		public
	 *	@return		string
	 */

	public function rating_rev_stats()
	{
		return $this->rating_vote_stats();
	}
	/* END rating_rev_stats() */
	

	// --------------------------------------------------------------------

	/**
	 *	Statistics for the Votes on a Rating
	 *
	 *	http://www.solspace.com/docs/detail/rating_rating_review_stats/
	 *
	 *	@access		public
	 *	@return		string
	 */
	 
	public function rating_vote_stats()
	{
		$cond	= array(
						'y'				=> 0,
						'up_votes'		=> 0,
						'n'				=> 0,
						'down_votes'	=> 0,
						'percent_y'		=> 0,
						'up_percent'	=> 0,
						'percent_n'		=> 0,
						'down_percent'	=> 0,
						'total'			=> 0,
						'total_votes'	=> 0
						);

		$cond['already_voted'] = $cond['already_reviewed'] = FALSE;
		$cond['not_already_voted'] = $cond['not_already_reviewed'] = TRUE;
		$cond['prior_vote_count'] = $cond['prior_review_count'] = 0;

		if ( ($rating_id = ee()->TMPL->fetch_param('rating_id')) === FALSE )
		{
			return $this->no_results();
		}

		$tagdata	= ee()->TMPL->tagdata;

		// ----------------------------------------
		//  Get data
		// ----------------------------------------

		$query	= ee()->db->query( "SELECT rating_helpful, COUNT(rating_helpful) AS count
									FROM exp_rating_reviews
									WHERE rating_id = '".ee()->db->escape_str($rating_id)."'
									AND status = 'open'
									GROUP BY rating_helpful" );

		foreach ($query->result_array() as $row )
		{
			if ($row['rating_helpful'] == 'y' )
			{
				$cond['up_votes'] = $cond['y'] = $row['count'];
			}
			else
			{
				$cond['down_votes'] = $cond['n'] = $row['count'];
			}
		}
		
		if (strstr($tagdata, 'already_reviewed') OR strstr($tagdata, 'prior_review_count') OR
			strstr($tagdata, 'already_voted') OR strstr($tagdata, 'prior_vote_count') OR
			strstr($tagdata, 'previous_vote') OR 
			ee()->TMPL->fetch_param('allow_duplicates') == 'no')
		{
			$priors = $this->already_reviewed(FALSE, $rating_id);
						
			if (isset($priors[$rating_id]))
			{
				$cond['already_voted']		= $cond['already_reviewed']		= $priors[$rating_id]['already_reviewed'];
				$cond['not_already_voted']	= $cond['not_already_reviewed']	= $priors[$rating_id]['not_already_reviewed'];
				$cond['prior_vote_count']	= $cond['prior_review_count']	= $priors[$rating_id]['prior_review_count'];
				$cond['previous_vote']		= $priors[$rating_id]['previous_vote'];
			}
			
			if ($cond['already_reviewed'] === TRUE && ee()->TMPL->fetch_param('allow_duplicates') == 'no' )
			{
				return $this->parse_tagdata_message( FALSE, ee()->lang->line('no_duplicates'), $cond );
			}
		}
		
		// ----------------------------------------
		//  Total
		// ----------------------------------------

		$cond['total_votes'] = $cond['total'] = $cond['y'] + $cond['n'];

		// ----------------------------------------
		//  Percents
		// ----------------------------------------

		if ($cond['total_votes'] != 0 )
		{
			$cond['up_percent'] = $cond['percent_y'] = ceil( ($cond['y'] / $cond['total']) * 100 );
			$cond['down_percent'] = $cond['percent_n'] = 100 - $cond['percent_y'];
		}

		// ----------------------------------------
		//  Parse conditionals
		// ----------------------------------------

		$tagdata	= ee()->functions->prep_conditionals($tagdata, $cond );

		// ----------------------------------------
		//  Parse remaining
		// ----------------------------------------
		
		foreach ($cond as $key => $val )
		{
			$tagdata	= str_replace( LD.$key.RD, $val, $tagdata );
		}
		
		// ----------------------------------------
		//  Return
		// ----------------------------------------

		return $tagdata;
	}
	/* END rating_vote_stats() */


	// --------------------------------------------------------------------

	/**
	 *	Reporting Allowed
	 *
	 *	http://www.solspace.com/docs/detail/rating_reporting_allowed/
	 *
	 *	@access		public
	 *	@return		string
	 */
	 
	public function quarantine_allowed() { return $this->reporting_allowed(); } // Became an alias in Rating 3.0

	public function reporting_allowed()
	{
		// ----------------------------------------
		//  Logged in?
		// ----------------------------------------

		if ( ee()->session->userdata['member_id'] == 0 )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('not_logged_in') );
		}

		// ----------------------------------------
		//  Banned?
		// ----------------------------------------

		if ( ee()->session->userdata['is_banned'] == TRUE )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('banned') );
		}

		// ----------------------------------------
		//  Rating id?
		// ----------------------------------------

		if ($this->rating_id() === FALSE )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('no_rating_id') );
		}

		// ----------------------------------------
		//  User allowed to report?
		// ----------------------------------------
		
		if ( ! in_array(ee()->session->userdata['group_id'], $this->preference('can_report_ratings')))
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('not_allowed_to_report') );
		}

		// ----------------------------------------
		//  Rating already quarantined?
		// ----------------------------------------

		$query	= ee()->db->query( "SELECT COUNT(*) AS count FROM exp_ratings
									WHERE rating_id = '".ee()->db->escape_str($this->rating_id)."'
									AND quarantine = 'y'" );

		if ($query->row('count') != 0 )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('rating_already_quarantined') );
		}

		// ----------------------------------------
		// Rating already reported by this user
		// ----------------------------------------

		$query = ee()->db->query( "SELECT COUNT(*) AS count FROM exp_rating_quarantine
									WHERE rating_id = '".ee()->db->escape_str($this->rating_id)."'
									AND member_id = '" . ee()->db->escape_str(ee()->session->userdata['member_id']) . "'" );

		if ($query->row('count') != 0 )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('rating_already_reported') );
		}

		// ----------------------------------------
		//  It's allowed, return
		// ----------------------------------------

		return $this->parse_tagdata_message( TRUE, ee()->lang->line('reporting_successful') );
	}
	/* END reporting allowed */


	// --------------------------------------------------------------------

	/**
	 *	Report
	 *
	 *	http://www.solspace.com/docs/detail/rating_report/
	 *
	 *	@access		public
	 *	@return		string
	 */

	public function quarantine() { return $this->report(); } // Became an alias in Rating 3.0
	
	public function report()
	{
		// ----------------------------------------
		//  Fetch language file
		// ----------------------------------------

		ee()->lang->loadfile('rating');

		// ----------------------------------------
		//  Logged in?
		// ----------------------------------------

		if ( ee()->session->userdata['member_id'] == 0 )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('not_logged_in') );
		}

		// ----------------------------------------
		//  Banned?
		// ----------------------------------------

		if ( ee()->session->userdata['is_banned'] == TRUE )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('banned') );
		}

		// ----------------------------------------
		//  Rating id?
		// ----------------------------------------

		if ($this->rating_id() === FALSE )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('no_rating_id') );
		}

		// ----------------------------------------
		//  Allowed to report?
		// ----------------------------------------
		
		if ( ! in_array(ee()->session->userdata['group_id'], $this->preference('can_report_ratings')))
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('not_allowed_to_report') );
		}
		
		// ----------------------------------------
		//  Rating Exists? Quarantined?
		// ----------------------------------------
		
		$query	= ee()->db->query( "SELECT quarantine, entry_id, rating_author_id, channel_id FROM exp_ratings
									WHERE rating_id = '".ee()->db->escape_str($this->rating_id)."'" );
									
		if ($query->num_rows() == 0)
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('no_rating_id') );
		}

		if ($query->row('quarantine') == 'y')
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('rating_already_quarantined') );
		}
		
		$data = array();
		$data['rating_id']			= $this->rating_id;
		$data['entry_id']			= $query->row('entry_id');
		$data['channel_id']			= $query->row('channel_id');
		$data['rating_author_id']	= $query->row('rating_author_id');
		$data['member_id']			= ee()->session->userdata['member_id'];
		$data['entry_date']			= ee()->localize->now;

		// ----------------------------------------
		// Rating already reported by this user
		// ----------------------------------------

		$query = ee()->db->query( "SELECT COUNT(*) AS count FROM exp_rating_quarantine
									WHERE rating_id = '".ee()->db->escape_str($this->rating_id)."'
									AND member_id = '" . ee()->session->userdata['member_id'] . "'" );

		if ($query->row('count') != 0 )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('rating_already_reported') );
		}

		// ----------------------------------------
		//  Insert
		// ----------------------------------------

		ee()->db->query( ee()->db->insert_string( 'exp_rating_quarantine', $data));

		// ----------------------------------------
		//  Should we quarantine the sucker?
		// ----------------------------------------

		$query	= ee()->db->query( "SELECT COUNT(*) AS count FROM exp_rating_quarantine
									WHERE rating_id = '".ee()->db->escape_str($this->rating_id)."'
									AND status != 'closed'" );

		if ($query->row('count') >= $this->preference('quarantine_minimum'))
		{
			ee()->db->query( ee()->db->update_string( 'exp_ratings', array( 'quarantine' => 'y' ), array( 'rating_id' => $this->rating_id)) );

			$this->actions()->update_entry_stats($data['entry_id']);
		}

		// ----------------------------------------
		//  Return
		// ----------------------------------------

		return $this->parse_tagdata_message( TRUE, ee()->lang->line('reporting_successful') );
	}
	/* END report() */


	// --------------------------------------------------------------------

	/**
	 *	Delete Rating
	 *
	 *	http://www.solspace.com/docs/detail/rating_delete/
	 *
	 *	@access		public
	 *	@return		string
	 */

	public function delete()
	{
		// ----------------------------------------
		//  Fetch language file
		// ----------------------------------------

		ee()->lang->loadfile('rating');

		// ----------------------------------------
		//  Logged in?
		// ----------------------------------------

		if ( ee()->session->userdata['member_id'] == 0 )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('not_logged_in') );
		}

		// ----------------------------------------
		//  Banned?
		// ----------------------------------------

		if ( ee()->session->userdata['is_banned'] == TRUE )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('banned') );
		}
		
		// ----------------------------------------
		//  Rating id or entry_id?
		// ----------------------------------------

		$rating_id = ee()->TMPL->fetch_param('rating_id');
		$entry_id = ee()->TMPL->fetch_param('entry_id');
		
		if ($rating_id === FALSE && $entry_id === FALSE )
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('no_rating_entry_id') );
		}
		
		// ----------------------------------------
		//  Allowed to Delete?
		// ----------------------------------------

		if ( ! in_array(ee()->session->userdata['group_id'], $this->preference('can_delete_ratings')))
		{
			return $this->parse_tagdata_message( FALSE, ee()->lang->line('not_authorized_delete') );
		}
		
		// ----------------------------------------
		// 'delete_rating_start' hook.
		//  - Add aditional processing before deleting
		//
		//
			if (isset(ee()->extensions->extensions['delete_rating_start']))
			{
				$edata = ee()->extensions->call('delete_rating_start', $rating_id, $entry_id);
				if (ee()->extensions->end_script === TRUE) return $edata;
			}
		//
		// ----------------------------------------


		// ----------------------------------------
		//  Delete
		// ----------------------------------------
		
		$entry_ids		= array();
		$channel_ids	= array();

		if ( ! empty($rating_id))
		{
			$in_string = implode(',', array_map('ceil', explode('|', $rating_id)));

			$query	= ee()->db->query( "SELECT DISTINCT entry_id, channel_id FROM exp_ratings
										WHERE rating_id IN ('".ee()->db->escape_str($rating_id)."')" );

			if ($query->num_rows() == 0 )
			{
				return $this->parse_tagdata_message( FALSE, ee()->lang->line('rating_already_deleted') );
			}
										
			foreach($query->result_array() as $row )
			{
				$entry_ids[]	= $row['entry_id'];
				$channel_ids[]	= $row['channel_id'];
			}

			ee()->db->query("DELETE FROM exp_ratings WHERE rating_id IN (".$in_string.")" );
			ee()->db->query("DELETE FROM exp_rating_quarantine WHERE rating_id IN (".$in_string.")" );
		}
		elseif ( ! empty($entry_id) )
		{
			$in_string = implode(',', array_map('ceil', explode('|', $entry_id)));

			$query	= ee()->db->query( "SELECT entry_id, channel_id
										FROM exp_ratings
										WHERE entry_id IN (".$in_string.")" );

			if ($query->num_rows() == 0 )
			{
				return $this->parse_tagdata_message( FALSE, ee()->lang->line('entry_has_no_ratings') );
			}
										
			foreach($query->result_array() as $row )
			{
				$entry_ids[]	= $row['entry_id'];
				$channel_ids[]	= $row['channel_id'];
			}

			ee()->db->query("DELETE FROM exp_ratings WHERE entry_id IN (".$in_string.")");
			ee()->db->query("DELETE FROM exp_rating_quarantine WHERE entry_id IN (".$in_string.")" );
		}
		
		// --------------------------------------------
        //	Stats
        // --------------------------------------------

		foreach($entry_ids as $entry_id)
		{
			$this->actions()->update_entry_stats($entry_id);
		}
		
		foreach($channel_ids as $channel_id)
		{
			$this->actions()->update_entry_stats($entry_id);
		}

		// ----------------------------------------
		// 'delete_rating_end' hook.
		//  - Add aditional processing after deleting
		//
			if (isset(ee()->extensions->extensions['delete_rating_end']))
			{
				$edata = ee()->extensions->call('delete_rating_end', $rating_id, $entry_id);
				if (ee()->extensions->end_script === TRUE) return $edata;
			}
		//
		// ----------------------------------------

		// ----------------------------------------
		//  Return
		// ----------------------------------------

		return $this->parse_tagdata_message( TRUE, ee()->lang->line('rating_delete_successful') );
	}
	/* END delete() */
	
	// --------------------------------------------------------------------

	/**
	 *	Selected - LEGACY
	 *
	 *	This function takes a last name first string and switches it around.
	 *	Not in docs, so may be removed at a later date.-Paul
	 *
	 *	@access		public
	 *	@return		string
	 */

	public function selected()
	{
		$tagdata	= ee()->TMPL->tagdata;

		$item		= ( ee()->TMPL->fetch_param('item') ) ? str_replace( " ", "_", ee()->TMPL->fetch_param('item') ): '';
		$replace	= ( ee()->TMPL->fetch_param('replace') ) ? ee()->TMPL->fetch_param('replace'): 'class="selected"';

		if ( preg_match( "/".LD."selected_".$item.RD."/s", $tagdata, $match))
		{
			$tagdata	= str_replace($match[0], $replace, $tagdata );
		}
		else
		{
			$tagdata	= str_replace( LD.'selected_null'.RD, $replace, $tagdata );
		}

		$tagdata	= preg_replace( "/".LD."selected_.*?".RD."/s", "", $tagdata );

		return $tagdata;
	}
	/* END selected() */


	// --------------------------------------------------------------------

	/**
	 *	Image <img /> helper
	 *
	 *	@access		private
	 *	@param		string
	 *	@param		integer
	 *	@param		integer
	 *	@param		string
	 *	@param		string
	 *	@return		string
	 */

	private function img_tag($url, $w = 10, $h = 10, $alt = 'Rating', $type = 'rating' )
	{
		return '<img src="'.$url.'" width="'.ceil($w).'" height="'.ceil($h).'" alt="'.htmlspecialchars($alt).'" class="rating_'.htmlspecialchars($type).'" />';
	}
	/* END img_tag() */
	

	// --------------------------------------------------------------------

	/**
	 *	Multiple <img /> tag helper
	 *
	 *	@access		private
	 *	@param		array
	 *	@param		array
	 *	@return		string
	 */

	private function multiple_img_tags($num = array(), $data = array() )
	{
		$img	= '';

		for ($i = $num[0]; $i > 0; $i--)
		{
			$img	.= $this->img_tag($data['urlfull'], $data['w'], $data['h'], $i, $data['type']);
		}

		$img	.= ($num[1] == 0 ) ? '': $this->img_tag($data['urlrem'], $data['w'], $data['h'], $i, $data['type']);

		for ($i = $data['filler']; $i > 0; $i-- )
		{
			$img	.= $this->img_tag($data['urlfill'], $data['w'], $data['h'], $i, $data['type']);
		}

		return $img;
	}
	// END multuiple_img_tags()

	// --------------------------------------------------------------------

	/**
	 *	Parse Overall
	 *
	 *	Parses an {overall_avg} variable or similar.  Does URL and precision automatically
	 *
	 *	@access		private
	 *	@param		integer
	 *	@param		integer
	 *	@param		integer
	 *	@param		string
	 *	@param		string
	 *	@return		string
	 */

	private function parse_overall_avg($overall, $precision = '2', $scale = '5', $image_url = '', $type = 'stars' )
	{
		$img	= '';

		//	Get array
		$num_arr	= explode( ".", number_format($overall, $precision, '.', ''));

		//	Handle remainder
		if ( ($num = $this->quartile_rounding($num_arr)) === FALSE )
		{
			return $img;
		}

		//	Handle filler
		if ( ($filler = $this->empty_stars_filler($num, $scale)) === FALSE )
		{
			return $img;
		}

		// ----------------------------------------
		//	Prep data
		// ----------------------------------------
		
		$data['ext']	= $ext = '.gif';
		$data['filler'] = $filler;
		$data['w']		= '12';
		$data['h']		= '12';
		
		// --------------------------------------------
        //  Allow User to Change Width/Heigh of Images
        // --------------------------------------------
		
		if (isset(ee()->TMPL) && is_object(ee()->TMPL) &&
				ee()->TMPL->fetch_param('img_width') !== FALSE && 
					ctype_digit(ee()->TMPL->fetch_param('img_width'))
			)
		{
			$data['w'] = ee()->TMPL->fetch_param('img_width');
		}
		
		if (isset(ee()->TMPL) && is_object(ee()->TMPL) &&
				ee()->TMPL->fetch_param('img_height') !== FALSE && 
					ctype_digit(ee()->TMPL->fetch_param('img_height'))
			)
		{
			$data['h'] = ee()->TMPL->fetch_param('img_height');
		}

		if ($type == 'circles' )
		{
			$data['urlfull']	= $image_url.'circle-100'.$ext;
			$data['urlrem']		= $image_url.'circle-'.$num[1].$ext;
			$data['urlfill']	= $image_url.'circle-0'.$ext;
			$data['type']		= 'circle';
		}
		elseif ($type == 'bar' )
		{
			$data['urlfull']	= $image_url.'bar-100'.$ext;
			$data['urlrem']		= $image_url.'bar-'.$num[1].$ext;
			$data['urlfill']	= $image_url.'bar-0'.$ext;
			$data['type']		= 'bar';
		}
		else
		{
			$data['urlfull']	= $image_url.'star-100'.$ext;
			$data['urlrem']		= $image_url.'star-'.$num[1].$ext;
			$data['urlfill']	= $image_url.'star-0'.$ext;
			$data['type']		= 'star';
		}

		// ----------------------------------------
		//	Parse images
		// ----------------------------------------

		return $img		= $this->multiple_img_tags($num, $data );
	}
	// END parse overall

	// --------------------------------------------------------------------

	/**
	 *	Outputs Stars for a Rating Field
	 *
	 *	@access		private
	 *	@param		array
	 *	@return		string
	 */

	private function parse_stars($data = array() )
	{
		// ----------------------------------------
		//	Errors
		// ----------------------------------------

		if ( count($data ) == 0 )
		{
			return FALSE;
		}

		if ( ! isset($data['row'], $data['field']))
		{
			return FALSE;
		}
		
		if ( ! isset($data['row'][ $data['field'] ]))
		{
			return FALSE;
		}

		$data['field']	= str_replace( array(LD,RD), array('',''), $data['field']);

		// ----------------------------------------
		//	Set value
		// ----------------------------------------

		$data['value']	= $data['row'][ $data['field'] ];

		// ----------------------------------------
		//	Filler
		// ----------------------------------------

		$filler	= $data['scale'] - $data['value'];

		// ----------------------------------------
		//	Determine type
		// ----------------------------------------
		
		$img		= '';
		$ext		= '.gif';
		$w			= '12';
		$h			= '12';
		
		// --------------------------------------------
        //  Allow User to Change Width/Heigh of Images
        // --------------------------------------------
		
		if (isset(ee()->TMPL) && is_object(ee()->TMPL) &&
				ee()->TMPL->fetch_param('img_width') !== FALSE && 
					ctype_digit(ee()->TMPL->fetch_param('img_width'))
			)
		{
			$w = ee()->TMPL->fetch_param('img_width');
		}
		
		if (isset(ee()->TMPL) && is_object(ee()->TMPL) &&
				ee()->TMPL->fetch_param('img_height') !== FALSE && 
					ctype_digit(ee()->TMPL->fetch_param('img_height'))
			)
		{
			$h = ee()->TMPL->fetch_param('img_height');
		}

		if ($data['type'] == 'circles' )
		{
			$urlfull	= $data['image_url'].'circle-100'.$ext;
			$urlfill	= $data['image_url'].'circle-0'.$ext;
			$type		= 'circle';
		}
		elseif ($data['type'] == 'bar' )
		{
			$urlfull	= $data['image_url'].'bar-100'.$ext;
			$urlfill	= $data['image_url'].'bar-0'.$ext;
			$type		= 'bar';
		}
		else
		{
			$urlfull	= $data['image_url'].'star-100'.$ext;
			$urlfill	= $data['image_url'].'star-0'.$ext;
			$type		= 'star';
		}

		// ----------------------------------------
		//	Parse images
		// ----------------------------------------

		for ($i = $data['value']; $i > 0; $i-- )
		{
			$img	.= $this->img_tag($urlfull, $w, $h, $i, $type );
		}

		for ($i = $filler; $i > 0; $i-- )
		{
			$img	.= $this->img_tag($urlfill, $w, $h, $i, $type );
		}

		// ----------------------------------------
		//	return
		// ----------------------------------------

		return $img;
	}
	// END parse_stars()

	// --------------------------------------------------------------------

	/**
	 *	Quartile Rounding
	 *
	 *	Rounds a number with a decimal to have a 0, 25, 50, or 75 ending
	 *
	 *	@access		public
	 *	@param		integer
	 *	@return		string
	 */

	public function quartile_rounding($num )
	{
		if ( is_array($num) === FALSE ) return FALSE;

		if ( ! isset($num[1]) )
		{
			$num[1]	= 0;
		}

		if ($num[1] < 25 )
		{
			$num[1]	= 0;
		}
		elseif ($num[1] >= 25 AND $num[1] < 50 )
		{
			$num[1]	= 25;
		}
		elseif ($num[1] >= 50 AND $num[1] < 75 )
		{
			$num[1]	= 50;
		}
		else
		{
			$num[1]	= 75;
		}

		return $num;
	}
	// END quartile_rounding
	
	// --------------------------------------------------------------------

	/**
	 *	Empty Stars Filler
	 *
	 *	@access		public
	 *	@param		integer
	 *	@param		integer
	 *	@return		string
	 */

	private function empty_stars_filler($num, $scale )
	{
		$filler	= 0;

		if ( ! ctype_digit((string) $scale) OR ! is_array($num)) return $filler;

		return ($num[1] == 0 ) ? ($scale - $num[0]) : $scale - 1 - $num[0];
	}
	// END empty_stars_filler

	// --------------------------------------------------------------------

	/**
	 *	Fields Numeric
	 *
	 *	Returns an array of all the Rating fields with a field_type of 'number'
	 *
	 *	@access		public
	 *	@return		array
	 */

	public function fields_numeric()
	{
		// ----------------------------------------
		//  Should we execute?
		// ----------------------------------------

		if ( is_array($this->fields_numeric)) return $this->fields_numeric;

		// ----------------------------------------
		//  Find Numeric Fields
		// ----------------------------------------
		
		$this->fields_numeric = array();
		
		foreach($this->data->get_rating_fields_data() as $row)
		{
			if ($row['field_type'] == 'number')
			{
				$this->fields_numeric[]	= $row['field_name'];
			}
		}

		return $this->fields_numeric;
	}
	// END fields_numeric()


	// --------------------------------------------------------------------

	/**
	 *	Field Stats
	 *
	 *	Finds the stats for an entry for a particular form name for all fields.
	 *
	 *	@access		private
	 *	@param		integer
	 *	@param		string
	 *	@return		array
	 */

	private function fields_stats($entry_id = '', $collection = 'all')
	{
		$stats	= array();
		$fields	= array();

		// ----------------------------------------
		//	Should we execute?
		// ----------------------------------------

		if ($entry_id == '' ) return FALSE;

		if ( isset($this->fields_stats[$entry_id]) AND count($this->fields_stats[$entry_id]) > 0 )
		{
			return TRUE;
		}

		// ----------------------------------------
		// Run the DB query and fail out if empty.
		// ----------------------------------------

		$sql	= "SELECT rs.*, t.entry_date, rs.last_rating_date AS recent_rating_date,
				   rs.count AS rating_count,
				   rs.count AS timeframe_count, rs.sum AS timeframe_sum, rs.avg AS timeframe_avg";
		
		foreach ($this->fields_numeric() as $field )
		{
			$sql .= ", AVG(r.`{$field}`) AS {$field}_timeframe_avg, SUM(r.`{$field}`) AS {$field}_timeframe_sum ";
		}

		$sql .= "	FROM (exp_rating_stats rs, {$this->sc->db->channel_titles} AS t)
					LEFT JOIN exp_ratings AS r ON r.entry_id = rs.entry_id
					WHERE rs.entry_id = '".ee()->db->escape_str($entry_id)."'
					AND t.entry_id = '".ee()->db->escape_str($entry_id)."'
					AND rs.collection = '".ee()->db->escape_str($collection)."'";
			
		if ( ee()->TMPL->fetch_param('timeframe_start') || ee()->TMPL->fetch_param('timeframe_end') )
		{
			$start	= ( ee()->TMPL->fetch_param('timeframe_start') ) ? strtotime( ee()->TMPL->fetch_param('timeframe_start') ) : 0;
			$end	= ( ee()->TMPL->fetch_param('timeframe_end') ) ? strtotime( ee()->TMPL->fetch_param('timeframe_end') ) : 0;
			
			if (ctype_digit($start) && $start > 0 )
			{
				$sql .= $t_start_sql = " AND r.rating_date >= '$start'\n";
				
				if ($end > $start )
				{
					$sql .= $t_end_sql = " AND r.rating_date <= '$end'\n";
				}
			}
			elseif (ctype_digit($end) && $end > 0 )
			{
				$sql .= $t_end_sql = " AND r.rating_date <= '$end'\n";
			}
		}
		$sql .= "GROUP BY r.entry_id";
		
		$query	= ee()->db->query($sql );

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}
		else
		{
			$stats	= $query->row_array();
		}

		// ----------------------------------------
		//	Create array of fields
		// ----------------------------------------

		$this->fields_stats[$entry_id]	= array();

		foreach ($this->data->get_rating_fields_data() as $name => $val )
		{
			$fields[ 'count_'.$name ]	= ($stats[ 'count_'.$val['field_id'] ] != NULL ) ? $stats[ 'count_'.$val['field_id'] ]:	'';
			$fields[ 'sum_'.$name ]		= ($stats[ 'sum_'.$val['field_id'] ] != NULL )	 ? $stats[ 'sum_'.$val['field_id'] ]:	'';
			$fields[ 'avg_'.$name ]		= ($stats[ 'avg_'.$val['field_id'] ] != NULL )	 ? $stats[ 'avg_'.$val['field_id'] ]:	'';
		}

		$fields[ 'entry_date' ]			= $stats['entry_date'];
		$fields[ 'recent_rating_date']	= $stats['recent_rating_date'];
		
		$fields[ 'rating_count' ]		= $stats['rating_count'];
		$fields[ 'overall_count' ]		= ($stats[ 'count' ] != NULL)			? $stats[ 'count' ]:			'';
		$fields[ 'overall_sum' ]		= ($stats[ 'sum' ] != NULL)				? $stats[ 'sum' ]:				'';
		$fields[ 'overall_avg' ]		= ($stats[ 'avg' ] != NULL)				? $stats[ 'avg' ]:				'';
		$fields[ 'timeframe_count' ]	= ($stats['timeframe_count'] != NULL)	? $stats[ 'timeframe_count' ]: 	'';
		$fields[ 'timeframe_sum' ]		= ($stats['timeframe_sum'] != NULL)		? $stats[ 'timeframe_sum' ]	:	'';
		$fields[ 'timeframe_avg' ]		= ($stats['timeframe_avg'] != NULL)		? $stats[ 'timeframe_avg' ]	:	'';

		foreach ($this->fields_numeric() as $field )
		{
			$fields['avg_'.$field.'_timeframe'] = ($stats[$field.'_timeframe_avg'] != NULL ) ? $stats[ $field.'_timeframe_avg' ]	: '';
			$fields['sum_'.$field.'_timeframe'] = ($stats[$field.'_timeframe_sum'] != NULL ) ? $stats[ $field.'_timeframe_sum' ]	: '';
		}

		// ----------------------------------------
		//	Return
		// ----------------------------------------

		return $this->fields_stats[$entry_id] = $fields;
	}
	// END fields stats


	// --------------------------------------------------------------------

	/**
	 *	Recount Rating Reviews
	 *
	 *	@access		public
	 *	@param		integer	// Rating ID
	 *	@return		string
	 */

	public function recount_rating_reviews($rating_id = '')
	{
		if ( empty($rating_id))
		{
			return;
		}

		$query	= ee()->db->query( "SELECT COUNT(*) AS count FROM exp_rating_reviews
									WHERE rating_id = '".ee()->db->escape_str($rating_id )."'
									AND rating_helpful = 'y'" );
									
		$rating_helpful_y = $query->row('count');

		$query	= ee()->db->query( "SELECT COUNT(*) AS count FROM exp_rating_reviews
									WHERE rating_id = '".ee()->db->escape_str($rating_id )."'
									AND rating_helpful = 'n'" );		

		ee()->db->query( ee()->db->update_string('exp_ratings',
												 array( 'rating_helpful_y' => $rating_helpful_y,
												 		'rating_helpful_n' => $query->row('count') ),
												 array('rating_id' => $rating_id)));
	}
	// END recount_rating_reviews

	
	// --------------------------------------------------------------------

	/**
	 *	Find and Return Rating ID
	 *
	 *	Essentially looks for the rating_id="" parameter and insures it is numeric.
	 *
	 *	@access		private
	 *	@return		interger|boolean
	 */
	
	private function rating_id()
	{
		if ( ctype_digit( ee()->TMPL->fetch_param('rating_id')))
		{
			$this->rating_id = ee()->TMPL->fetch_param('rating_id');

			return $this->rating_id;
		}
		
		return FALSE;
	}
	/* END rating_id() */

	
	// --------------------------------------------------------------------

	/**
	 *	Find and Return Entry ID for a Channel/Weblog Entry
	 *
	 *	@access		private
	 *	@param		string
	 *	@return		integer|boolean
	 */

	private function entry_id($type = 'channel' )
	{
		if ($type == 'weblog')
		{
			$type = 'channel';
		}
	
		$cat_segment	= ee()->config->item("reserved_category_word");
		$this->dynamic	= ( $this->check_no(ee()->TMPL->fetch_param('dynamic')) === FALSE) ? TRUE : FALSE;

		// ----------------------------------------
		//  Fail out if not checking for the normal type
		// ----------------------------------------

		if ($type != 'channel' )
		{
			return FALSE;
		}

		// ----------------------------------------
		//  Regular entry id test
		// ----------------------------------------

		if ( ctype_digit(ee()->TMPL->fetch_param('entry_id')))
		{
			$this->entry_id	= ee()->TMPL->fetch_param('entry_id');

			return $this->entry_id;
		}
		elseif ( ee()->uri->query_string != '' AND $this->dynamic )
		{
			$qstring	= ee()->uri->query_string;

			// ----------------------------------------
			//  Do we have a pure ID number?
			// ----------------------------------------

			if ( ctype_digit($qstring) )
			{
				$this->entry_id	= $qstring;

				return $this->entry_id;
			}
			else
			{
				// ----------------------------------------
				//  Parse day
				// ----------------------------------------

				if (preg_match("#\d{4}/\d{2}/(\d{2})#", $qstring, $match))
				{
					$partial	= substr($match[0], 0, -3);

					$qstring	= trim_slashes(str_replace($match[0], $partial, $qstring));
				}

				// ----------------------------------------
				//  Parse /year/month/
				// ----------------------------------------

				if (preg_match("#(\d{4}/\d{2})#", $qstring, $match))
				{
					$qstring	= trim_slashes(str_replace($match[1], '', $qstring));
				}

				// ----------------------------------------
				//  Parse page number
				// ----------------------------------------

				if (preg_match("#^P(\d+)|/P(\d+)#", $qstring, $match))
				{
					$qstring = trim_slashes(str_replace($match[0], '', $qstring));
					
					// ----------------------------------------
					//  Do we have a pure ID number?
					// ----------------------------------------
					if ( ctype_digit($qstring) )
					{
						return $this->entry_id = $qstring;
					}
				}
				
				// ----------------------------------------
				//  Parse category indicator
				// ----------------------------------------

				// Text version of the category

				if (preg_match("#^".$cat_segment."/#", $qstring, $match) AND ee()->TMPL->fetch_param($this->sc->channel))
				{
					$qstring	= str_replace($cat_segment.'/', '', $qstring);

					$sql		= "SELECT DISTINCT cat_group FROM {$this->sc->db->channels} WHERE ";

					if (ee()->TMPL->fetch_param($this->sc->channel) !== FALSE)
					{
						$xsql	= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param($this->sc->channel), $this->sc->db->channel_name);

						if (substr($xsql, 0, 3) == 'AND') $xsql = substr($xsql, 3);

						$sql	.= ' '.$xsql;
					}

					$query	= ee()->db->query($sql);

					if ($query->num_rows() == 1)
					{
						$result	= ee()->db->query("SELECT cat_id FROM exp_categories
												   WHERE cat_name = '".ee()->db->escape_str($qstring)."'
												   AND group_id = '".ee()->db->escape_str($query->row('cat_group'))."'");

						if ($result->num_rows() == 1)
						{
							$qstring	= 'C'.$result->row('cat_id');
						}
					}
				}

				// Numeric version of the category

				if (preg_match("#^C(\d+)#", $qstring, $match))
				{
					$qstring	= trim_slashes(str_replace($match[0], '', $qstring));
				}

				// ----------------------------------------
				//  Remove "N"
				// ----------------------------------------

				// The recent comments feature uses "N" as the URL indicator
				// It needs to be removed if present

				if (preg_match("#^N(\d+)|/N(\d+)#", $qstring, $match))
				{
					$qstring = trim_slashes(str_replace($match[0], '', $qstring));
				}

				// ----------------------------------------
				//  Parse URL title
				// ----------------------------------------

				if (strstr($qstring, '/'))
				{
					$xe			= explode('/', $qstring);
					$qstring	= current($xe);
				}

				$sql	= "SELECT ct.entry_id, ct.{$this->sc->db->channel_id}
							FROM  {$this->sc->db->channel_titles} AS ct, {$this->sc->db->channels} AS c
							WHERE ct.{$this->sc->db->channel_id} = c.{$this->sc->db->channel_id}
							AND   ct.url_title = '".ee()->db->escape_str($qstring)."'";

				$query	= ee()->db->query($sql);

				if ($query->num_rows() > 0 )
				{
					$this->entry_id		= $query->row('entry_id');
					$this->channel_id	= $query->row('channel_id');

					return $this->entry_id;
				}
			}
		}

		return FALSE;
	}
	//	End entry id


	// --------------------------------------------------------------------

	/**
	 *	Return a Parameter for the Submitted Form
	 *
	 *	@access		public
	 *	@param		string
	 *	@return		string
	 */

	public function param($which = '', $next = '')
    {	
		// ----------------------------------------
		//	Which?
		// ----------------------------------------
		
		if ($which == '' ) return FALSE;
    	
		// ----------------------------------------
		//	Params set?
		// ----------------------------------------
		
		if ( count($this->params ) == 0 )
		{
			// ----------------------------------------
			//	Empty id?
			// ----------------------------------------
			
			if ( ! $params_id = ee()->input->get_post('params_id') )
			{
				return FALSE;
			}
			
			// ----------------------------------------
			//	Select from DB
			// ----------------------------------------
			
			$query	= ee()->db->query( "SELECT data FROM exp_rating_params 
								   		WHERE hash = '".ee()->db->escape_str($params_id )."'" );
			
			// ----------------------------------------
			//	Empty?
			// ----------------------------------------
			
			if ($query->num_rows() == 0 ) return FALSE;
			
			// ----------------------------------------
			//	Unserialize
			// ----------------------------------------
			
			$this->params = unserialize(base64_decode($query->row('data')));
		}
		
		// ----------------------------------------
		//	Fetch from params array
		// ----------------------------------------
		
		if ($next != '')
		{
			if ( isset($this->params[$which][$next]))
			{
				return str_replace( "&#47;", "/", $this->params[$which][$next]);
			}
		}
		elseif ( isset($this->params[$which]))
		{
			return str_replace( "&#47;", "/", $this->params[$which]);
		}
		
		// ----------------------------------------
		//	Fetch TMPL
		// ----------------------------------------
		
		if ( $next == '' && isset(ee()->TMPL) && is_object(ee()->TMPL) && ee()->TMPL->fetch_param($which) )
		{
			return ee()->TMPL->fetch_param($which);
		}
    	
		// ----------------------------------------
		//	Return
		// ----------------------------------------
		
		return FALSE;
    }
    /* END param */
    
	
	// --------------------------------------------------------------------

	/**
	 *	Insert Parameters for a Form
	 *
	 *	@access		private
	 *	@param		array
	 *	@return		bool
	 */

	private function insert_params($parameters = array() )
    {
		// ----------------------------------------
		//	Empty?
		// ----------------------------------------
    	
    	if ( empty($parameters))
    	{
    		return FALSE;
    	}
    	
		// ----------------------------------------
		//	Delete excess when older than 2 hours
		// ----------------------------------------
		
		ee()->db->query( "DELETE FROM exp_rating_params WHERE date < ". (ee()->localize->now-7200) );
    	
		// ----------------------------------------
		//	Insert
		// ----------------------------------------
		
		ee()->load->helper('string');
		$hash = random_string('alnum', 25);

		$insert = array('date'	=> ee()->localize->now,
						'data'	=> base64_encode(serialize($parameters)),
						'hash'	=> $hash);
						
		ee()->db->query(ee()->db->insert_string('exp_rating_params', $insert));
    	
		// ----------------------------------------
		//	Return
		// ----------------------------------------
		
		return $hash;
    }
    /* END insert params */

	// --------------------------------------------------------------------

	/**
	 *	Parameter Character Decode
	 *
	 *	The characters in an EE parameter might have to be modified in such a way that they
	 *	will not be entirely usable by us, so we convert them.
	 *
	 *	@access		public
	 *	@param		string
	 *	@return		string
	 */

	public function parameter_chars_decode($str = '' )
	{
		if ($str == '' ) return;

		if ( function_exists( 'htmlspecialchars_decode' ) === TRUE )
		{
			$str	= htmlspecialchars_decode($str );
		}

		if ( function_exists( 'html_entity_decode' ) === TRUE )
		{
			$str	= html_entity_decode($str );
		}

		$str	= str_replace( array( '&amp;', '&#47;', '&#39;', '\'' ), array( '&', '/', '', '' ), $str );

		$str	= stripslashes($str );

		return $str;
	}
	// END parameter_chars_decode

	// --------------------------------------------------------------------

	/**
	 *	Fetch Email Template for Notification of Rating Submission!
	 *
	 *	Now with caching power and a fall back of the default_template.
	 *
	 *	@access		private
	 *	@param		string		Template short name
	 *	@return		array
	 */

	private function fetch_email_template($template = 'default_template' )
	{
		if ( isset($this->cache['email_template'][$template]))
		{
			return $this->cache['email_template'][$template];
		}
	
		$query = ee()->db->query( "SELECT * FROM exp_rating_templates WHERE template_name = '".ee()->db->escape_str($template)."'" );
		
		if ($query->num_rows() == 0)
		{
			if ($template == 'default_template') return FALSE; // Uh oh...
		
			return $this->fetch_email_template();
		}
		else
		{
			return $this->cache['email_template'][$template] = $query->row_array();
		}
	}
	/* END fetch_email_template() */


	// --------------------------------------------------------------------

	/**
	 *	Parse Message in Template
	 *
	 *	Mitchell had Rating using tags as processing tools with the tagdata of the tag being
	 *	used to output a success or failure message.  This processes that tagdata and outputs the message.
	 *
	 *	@access		private
	 *	@param		bool
	 *	@param		string
	 *	@param		array
	 *	@return		string
	 */

	private function parse_tagdata_message($success, $message = '', $extra = array() )
	{
		$tagdata			= ee()->TMPL->tagdata;

		$cond['success']	= ($success)	? TRUE : FALSE;
		$cond['failure']	= (! $success )	? TRUE : FALSE;
		
		if ($extra)
		{
			$cond = array_merge($cond, $extra);
		}

		$tagdata			= ee()->functions->prep_conditionals($tagdata, $cond );
		
		if ($extra)
		{
			foreach ($extra as $k => $v)
			{
				if ($v)
				{
					$tagdata = ee()->TMPL->swap_var_single($k, $v, $tagdata);
				}
			}
		}

		return $tagdata		= str_replace( LD.'message'.RD, $message, $tagdata );
	}
	// END parse_tagdata_message
	
	// --------------------------------------------------------------------

	/**
	 *	Send Notification
	 *
	 *	Takes a data array and sends out a notification
	 *
	 *	@access		public
	 *	@param		array
	 *	@return		string
	 */

	public function send_notification($data = array() )
	{
		// ----------------------------------------
		//	Empty?
		// ----------------------------------------

		if ( empty($data))
		{
			return FALSE;
		}

		// ----------------------------------------
		//	Email?
		// ----------------------------------------

		if ( ! isset($data['email']))
		{
			$this->message[] = ee()->lang->line('no_email');
			return FALSE;
		}

		// ----------------------------------------
		//	No template in DB?
		// ----------------------------------------

		if ( ($message = $this->fetch_email_template($data['notification_template'])) === FALSE )
		{
			$this->message[]	= ee()->lang->line('no_notification_template_found');
			return FALSE;
		}

		// ----------------------------------------
		//	Prep extra vars
		// ----------------------------------------

		if ( isset($data['extra']) AND is_array($data['extra']) )
		{
			foreach ($data['extra'] as $key => $val )
			{
				$data[$key]	= $val;
			}
			
			unset($data['extra']);
		}
		
		// ----------------------------------------
		//	Prep main vars
		// ----------------------------------------

		$vars['recipient']	= $data['email'];
		$vars['from_email']	= ( isset ($data['from_email'])) ? $data['from_email']: ee()->config->item('webmaster_email');
		$vars['from_name']	= ( isset ($data['from_name'])) ? $data['from_name']: ee()->config->item('webmaster_name');
		$vars['subject']	= (isset($message['subject'])) ? $message['subject']: ee()->config->item('site_title');
		$vars['message']	= ( isset($message['message']) ) ? $message['message']: '';
		$vars['link']		= ( isset($data['link']) ) ? $data['link']: '';
		$vars['wordwrap']	= ( isset($message['wordwrap']) && $message['wordwrap'] == 'y' ) ? TRUE : FALSE;
		$vars['html']		= ( isset($data['html']) ) ? $data['html']: '';
		
		$data['entry_date']	= ee()->localize->set_human_time(ee()->localize->now);
		
		if (isset($data['unsubscribe']) && $data['unsubscribe'] == 'yes')
		{
			$data['unsubscribe_link'] = ee()->functions->fetch_site_index(0, 0).
										QUERY_MARKER.
										'ACT='.ee()->functions->fetch_action_id('Rating', 'unsubscribe').
										'&amp;rating_id='.$data['usubscribe_rating_id'].
										'&amp;hash='.md5($data['email']);
										
			if (APP_VER < 2.0)
			{
				$data['unsubscribe_link'] = ee()->functions->insert_action_ids( $data['unsubscribe_link'] );
			}						
		}
		else
		{
			$data['unsubscribe_link'] = '';
		}

		// ----------------------------------------
		//  Parse conditionals
		// ----------------------------------------

		require_once 'addon_builder/parser.addon_builder.php';

		$TMPL		= new Addon_builder_parser_rating();

		$cond		= $data;

		foreach($vars as $key => $val )
		{
			foreach ($data as $k => $v )
			{
				$vars[$key]	= str_replace( LD.$k.RD, $v, $vars[$key]);
			}
			
			$vars[$key]	= $TMPL->advanced_conditionals( ee()->functions->prep_conditionals($vars[$key], $cond));
		}

		unset($cond);

		// ----------------------------------------
		//  Parse individual fields
		// ----------------------------------------

		$exclude	= array('submit', 'subject', 'email');

		$all_fields	= '';

		foreach ($vars as $key => $val )
		{
			foreach ($this->data->get_rating_fields_list() as $name => $label )
			{
				if ( isset($data[$name]) AND ! in_array($name, $exclude))
				{
					$vars[$key]	= str_replace( LD.$name.RD, $data[$name], $vars[$key]);

					if ($key == 'message' )
					{
						$all_fields	.= $label.": ".$data[$name]."\n";
					}
				}
				else
				{
					$vars[$key]	= str_replace( LD.$name.RD, '', $vars[$key]);
				}
			}
		}

		// ----------------------------------------
		//  Parse all custom fields variable
		// ----------------------------------------

		if ( stristr($vars['message'], LD.'all_custom_fields'.RD))
		{
			$vars['message']	= str_replace( LD.'all_custom_fields'.RD, $all_fields, $vars['message']);
		}
		
		// --------------------------------------------
		//	Send Email
		// --------------------------------------------
		
		ee()->load->library('email');
		ee()->load->helper('text');
		
		ee()->email->wordwrap = ($vars['wordwrap'] == 'y' ) ? TRUE : FALSE;
		ee()->email->mailtype	= ($vars['html'] == 'yes' ) ? 'html': 'text';
		
		ee()->email->initialize();
		ee()->email->from($vars['from_email'], $vars['from_name']);	
		ee()->email->to($vars['recipient']); 
		ee()->email->subject($vars['subject']);	
		ee()->email->message(entities_to_ascii($vars['message']));		
		ee()->email->Send();
		ee()->email->clear(TRUE);

		// ----------------------------------------
		//	Cache the email
		// ----------------------------------------

		$data	= array(
						'entry_date' 	=> ee()->localize->now,
						'total_sent' 	=> 1,
						'from_name'		=> $vars['from_name'],
						'from_email'	=> $vars['from_email'],
						'recipient'		=> $vars['recipient'],
						'subject'		=> $vars['subject'],
						'message'		=> $vars['message'],
						'mailtype'		=> ee()->email->mailtype,
						'wordwrap'		=> $vars['wordwrap']
						);

		ee()->db->query( ee()->db->insert_string( 'exp_rating_notification_log', $data));
	}
	//	END send_notification()


	// --------------------------------------------------------------------

	/**
	 *	Build Error
	 *
	 *	Builds an error message to be displayed, possibly with a template.
	 *
	 *	@access		private
	 *	@param		string	Error message(s) to be displayed
	 *	@return		string
	 */

	private function fetch_error($error )
	{
		// --------------------------------------------
        //  AJAX Request == AJAX Error 
        // --------------------------------------------
        
		if ($this->param('output_json') == 'y' && $this->is_ajax_request())
		{
			// The $error variable may come in as an array or a string. The normal EE output
			// handles this by making an unordered list.  We could send a JSON array, but that 
			// requires extra work on the user side to determine what it is.  So, we concat to a string
			if (is_array($error))
			{
				$error = implode(', ', $error);
			}
		
			$this->send_ajax_response(array(
				'success' => FALSE,
				'heading' => lang('submission_error'),
				'message' => $error
			));
		}
		
		// --------------------------------------------
        //  HTML Error Page
        // --------------------------------------------
	
		$content  = '<ul>';

		if ( ! is_array($error))
		{
			$content	.= "<li>".$error."</li>\n";
		}
		else
		{
			foreach ($error as $val)
			{
				$content	.= "<li>".$val."</li>\n";
			}
		}

		$content .= "</ul>";

		$data	= array(
						'failure'	=> TRUE,
						'success'	=> FALSE,
						'message'	=> $content
						);
		
		$body = FALSE;
		
		if($this->param('error_template') !== FALSE)
		{
			$body = $this->fetch_template($this->param('error_template'), $data);
		}
		elseif($this->param('template') !== FALSE)
		{
			$body = $this->fetch_template($this->param('template'), $data);
		}
		elseif (ee()->input->get_post('error_template') !== FALSE)
		{
			$body = $this->fetch_template(ee()->input->get_post('error_template'), $data);
		}
		
		if ($body !== FALSE )
		{
			return ee()->output->_display($body);
		}
		
		return ee()->output->show_user_error('general', $error);
	}
	/* END fetch_error() */


	// --------------------------------------------------------------------

	/**
	 *	Fetch Template and Process
	 *
	 *	Fetches an EE Template and processes it for output...
	 *
	 *	@access		private
	 *	@param		string	Template_group/template
	 *	@param		array	Array of variables to parse
	 *	@return		string
	 */

	private function fetch_template($template = '', $data = array())
	{
		if ($template == '' )
		{
			if($this->param('template') !== FALSE)
			{
				$template = $this->param('template');
			}
			elseif(isset(ee()->TMPL) && ee()->TMPL->fetch_param('template') !== FALSE)
			{
				$template = ee()->TMPL->fetch_param('template');
			}
			elseif (ee()->input->get_post('template') !== FALSE)
			{
				$template = ee()->input->get_post('template');
			}
			else
			{
				return FALSE;
			}
		}

		$template = str_replace(T_SLASH, '/', $template);
		
		// --------------------------------------------
        //	Retrieve Template
        // --------------------------------------------
        
        $x = explode('/', $template);
        
        if ( ! isset($x[1])) $x[1] = 'index';
		
		$query = ee()->db->query(  "SELECT template_data, group_name, template_name, template_type
									FROM exp_templates AS t, exp_template_groups AS tg 
									WHERE t.site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'
									AND t.group_id = tg.group_id
									AND t.template_name = '".ee()->db->escape_str($x[1])."'
									AND tg.group_name = '".ee()->db->escape_str($x[0])."'
									LIMIT 1");
							 
		if ($query->num_rows() == 0)
		{
			return FALSE;
		}
		
		$template_data = stripslashes($query->row('template_data'));
		
		// --------------------------------------------
        //	Template as File?
        // --------------------------------------------
		
		if (ee()->config->item('save_tmpl_files') == 'y' AND ee()->config->item('tmpl_file_basepath') != '')
		{
			$basepath = ee()->config->slash_item('tmpl_file_basepath');
			
			if (APP_VER < 2.0)
			{
				$basepath .= $query->row('group_name').'/'.$query->row('template_name').'.php';
			}
			else
			{
				ee()->load->library('api');
				ee()->api->instantiate('template_structure');
				$basepath .= ee()->config->item('site_short_name').'/'.
							 $query->row('group_name').'.group/'.
							 $query->row('template_name').
							 ee()->api_template_structure->file_extensions($query->row('template_type'));
			}
			
			if (file_exists($basepath))
			{
				$template_data = file_get_contents($basepath);	
			}
		}
		
		// ----------------------------------------
		//	Prevent loop if this function is being called inside the template that we are calling
		// ----------------------------------------

		if ( stristr($template_data, "exp:rating"))
		{
			return ee()->output->show_user_error('general', array(ee()->lang->line('template_loop')));
		}
		
		// --------------------------------------------
        //	Parse as Template
        // --------------------------------------------
		
		require_once 'addon_builder/parser.addon_builder.php';
		
		$AO_Parser = new Addon_builder_parser_rating();
		$AO_Parser->global_vars = array_merge($AO_Parser->global_vars, $data);
		$out = $AO_Parser->process_string_as_template($template_data );
		
		// ----------------------------------------
		//	Parse typography
		// ----------------------------------------
		
		ee()->load->library('typography');
		
		if (APP_VER >= 2.0)
		{
			ee()->typography->initialize();
		}
		
		ee()->typography->convert_curly 	= FALSE;
 		ee()->typography->smileys			= FALSE;
 		ee()->typography->highlight_code	= TRUE;

		$formatting['html_format']		= 'all';
		$formatting['auto_links']		= 'n';
		$formatting['allow_img_url']	= 'y';

		if ( ee()->input->get_post('html') != 'yes'  )
		{
			$formatting['text_format']	= 'none';
		}
		elseif ( ee()->input->get_post('parse') )
		{
			$formatting['text_format']	= ee()->input->get_post('parse');
		}
		else
		{
			$formatting['text_format']	= 'xhtml';
		}

		return ee()->typography->parse_type(ee()->security->xss_clean($out), $formatting);
	}
	/* END _fetch_template() */


	// --------------------------------------------------------------------

	/**
	 *	Build SQL Query
	 *
	 *	For Entries tag. In no uncertain terms, this is a long, complicated, and difficult to follow piece of code
	 *
	 *	@access		public
	 *	@param		string
	 *	@return		string
	 */

	public function build_sql_query($qstring = '')
	{
		$entry_id		= '';
		$year			= '';
		$month			= '';
		$day			= '';
		$qtitle			= '';
		$cat_id			= '';
		$corder			= array();
		$offset			=  0;
		$page_marker	= FALSE;
		$dynamic		= TRUE;

		$this->channel->dynamic_sql = TRUE;

		// ----------------------------------------------
		//	Is dynamic="off" set?
		// ----------------------------------------------

		if ($this->check_no(ee()->TMPL->fetch_param('dynamic')) == TRUE)
		{
			$dynamic = FALSE;
		}

		if (ee()->TMPL->fetch_param('dynamic_parameters') !== FALSE AND isset($_POST) AND count($_POST) > 0)
		{
			foreach (explode('|', ee()->TMPL->fetch_param('dynamic_parameters')) as $var)
			{
				if (isset($_POST[$var]) AND in_array($var, array($this->sc->channel, 'entry_id', 'category', 'orderby', 'sort', 'sticky', 'show_future_entries', 'show_expired', 'entry_id_from', 'entry_id_to', 'not_entry_id', 'start_on', 'stop_before', 'year', 'month', 'day', 'display_by', 'limit', 'username', 'status', 'group_id', 'cat_limit', 'month_limit', 'offset', 'author_id')))
				{
					ee()->TMPL->tagparams[$var] = $_POST[$var];
				}

				if (isset($_POST[$var]) && strncmp($var, 'search:', 7) == 0)
				{
					ee()->TMPL->search_fields[substr($var, 7)] = $_POST[$var];
				}
			}
		}

		// ----------------------------------------------
		//	Parse the URL query string
		// ----------------------------------------------

		$this->channel->uristr = ee()->uri->uri_string;

		if ($qstring == '')
		{
			if (APP_VER < 2.0)
			{
				$qstring = $this->channel->QSTR;
			}
			else
			{
				$qstring = $this->channel->query_string;
			}
		}
		
		$this->channel->basepath = ee()->functions->create_url($this->channel->uristr, 1);

		if ($qstring == '')
		{
			if (ee()->TMPL->fetch_param('require_entry') == 'yes')
			{
				return '';
			}
		}
		else
		{
			// --------------------------------------
			//	Do we have a pure ID number?
			// --------------------------------------

			if (ctype_digit($qstring) AND $dynamic)
			{
				$entry_id = $qstring;
			}
			else
			{
				// --------------------------------------
				//	Parse day
				// --------------------------------------

				if (preg_match("#\d{4}/\d{2}/(\d{2})#", $qstring, $match) AND $dynamic)
				{
					$partial = substr($match[0], 0, -3);

					if (preg_match("#(\d{4}/\d{2})#", $partial, $pmatch))
					{
						$ex = explode('/', $pmatch[1]);

						$year =  $ex[0];
						$month = $ex[1];
					}

					$day = $match[1];

					$qstring = trim_slashes(str_replace($match[0], $partial, $qstring));
				}

				// --------------------------------------
				//	Parse /year/month/
				// --------------------------------------

				// added (^|\/) to make sure this doesn't trigger with url titles like big_party_2006
				if (preg_match("#(^|\/)(\d{4}/\d{2})#", $qstring, $match) AND $dynamic)
				{
					$ex = explode('/', $match[2]);

					$year	= $ex[0];
					$month	= $ex[1];

					$qstring = trim_slashes(str_replace($match[2], '', $qstring));

					// Removed this in order to allow archive pagination
					// $this->paginate = FALSE;
				}

				// --------------------------------------
				//	Parse ID indicator
				// --------------------------------------

				if (preg_match("#^(\d+)(.*)#", $qstring, $match) AND $dynamic)
				{
					$seg = ( ! isset($match[2])) ? '' : $match[2];

					if (substr($seg, 0, 1) == "/" OR $seg == '')
					{
						$entry_id = $match[1];
						$qstring = trim_slashes(preg_replace("#^".$match[1]."#", '', $qstring));
					}
				}

				// --------------------------------------
				//	Parse page number
				// --------------------------------------

				if (preg_match("#^P(\d+)|/P(\d+)#", $qstring, $match) AND $dynamic)
				{
					$this->channel->p_page = (isset($match[2])) ? $match[2] : $match[1];

					$this->channel->basepath = ee()->functions->remove_double_slashes(str_replace($match[0], '', $this->channel->basepath));

					$this->channel->uristr  = ee()->functions->remove_double_slashes(str_replace($match[0], '', $this->channel->uristr));

					$qstring = trim_slashes(str_replace($match[0], '', $qstring));

					$page_marker = TRUE;
				}

				// --------------------------------------
				//	Parse category indicator
				// --------------------------------------

				// Text version of the category

				if ($qstring != '' AND $this->reserved_cat_segment != '' AND in_array($this->channel->reserved_cat_segment, explode("/", $qstring)) AND $dynamic AND ee()->TMPL->fetch_param($this->sc->channel))
				{
					$qstring = preg_replace("/(.*?)".preg_quote($this->channel->reserved_cat_segment)."\//i", '', $qstring);

					$sql = "SELECT DISTINCT cat_group FROM {$this->sc->db->channels}
							WHERE site_id IN ('".implode("','", ee()->TMPL->site_ids)."') AND ";

					if (ee()->TMPL->fetch_param($this->sc->channel) !== FALSE)
					{
						$xsql = ee()->functions->sql_andor_string(ee()->TMPL->fetch_param($this->sc->channel), $this->sc->db->channel_name);

						if (substr($xsql, 0, 3) == 'AND') $xsql = substr($xsql, 3);

						$sql .= ' '.$xsql;
					}

					$query = ee()->db->query($sql);

					if ($query->num_rows() > 0)
					{
						$valid = 'y';
						$last  = explode('|', $query->row('cat_group'));
						$valid_cats = array();

						foreach($query->result_array() as $row)
						{
							if (ee()->TMPL->fetch_param('relaxed_categories') == 'yes')
							{
								$valid_cats = array_merge($valid_cats, explode('|', $row['cat_group']));
							}
							else
							{
								$valid_cats = array_intersect($last, explode('|', $row['cat_group']));
							}

							$valid_cats = array_unique($valid_cats);

							if (sizeof($valid_cats) == 0)
							{
								$valid = 'n';
								break;
							}
						}
					}
					else
					{
						$valid = 'n';
					}

					if ($valid == 'y')
					{
						// the category URL title should be the first segment left at this point in $qstring,
						// but because prior to this feature being added, category names were used in URLs,
						// and '/' is a valid character for category names.  If they have not updated their
						// category url titles since updating to 1.6, their category URL title could still
						// contain a '/'.  So we'll try to get the category the correct way first, and if
						// it fails, we'll try the whole $qstring

						$cut_qstring = array_shift(explode('/', $qstring));

						$result = ee()->db->query("SELECT cat_id FROM exp_categories
											  WHERE cat_url_title='".ee()->db->escape_str($cut_qstring)."'
											  AND group_id IN ('".implode("','", ee()->db->escape_str($valid_cats))."')");

						if ($result->num_rows() == 1)
						{
							$qstring = str_replace($cut_qstring, 'C'.$result->row('cat_id'), $qstring);
						}
						else
						{
							// give it one more try using the whole $qstring
							$result = ee()->db->query("SELECT cat_id FROM exp_categories
												  WHERE cat_url_title='".ee()->db->escape_str($qstring)."'
												  AND group_id IN ('".implode("','", ee()->db->escape_str($valid_cats))."')");

							if ($result->num_rows() == 1)
							{
								$qstring = 'C'.$result->row('cat_id');
							}
						}
					}
				}

				// Numeric version of the category

				if (preg_match("#(^|\/)C(\d+)#", $qstring, $match) AND $dynamic)
				{
					$this->channel->cat_request = TRUE;

					$cat_id = $match[2];

					$qstring = trim_slashes(str_replace($match[0], '', $qstring));
				}

				// --------------------------------------
				//	Remove "N"
				// --------------------------------------

				// The recent comments feature uses "N" as the URL indicator
				// It needs to be removed if presenst

				if (preg_match("#^N(\d+)|/N(\d+)#", $qstring, $match))
				{
					$this->channel->uristr  = ee()->functions->remove_double_slashes(str_replace($match[0], '', $this->channel->uristr));

					$qstring = trim_slashes(str_replace($match[0], '', $qstring));
				}

				// --------------------------------------
				//	Parse URL title
				// --------------------------------------

				if ($cat_id == '' AND $year == '')
				{
					if (strstr($qstring, '/'))
					{
						$xe = explode('/', $qstring);
						$qstring = current($xe);
					}

					if ($dynamic == TRUE)
					{
						$sql = "SELECT count(*) AS count
								FROM  {$this->sc->db->channel_titles}, {$this->sc->db->channels}
								WHERE {$this->sc->db->channel_titles}.{$this->sc->db->channel_id} = {$this->sc->db->channels}.{$this->sc->db->channel_id}
								AND   {$this->sc->db->channel_titles}.url_title = '".ee()->db->escape_str($qstring)."'
								AND {$this->sc->db->channels}.site_id IN ('".implode("','", ee()->TMPL->site_ids)."') ";

						$query = ee()->db->query($sql);

						if ($query->row('count') == 0)
						{
							if (ee()->TMPL->fetch_param('require_entry') == 'yes')
							{
								return '';
							}

							$qtitle = '';
						}
						else
						{
							$qtitle = $qstring;
						}
					}
				}
			}
		}

		// ----------------------------------------------
		//	Entry ID number
		// ----------------------------------------------

		// If the "entry ID" was hard-coded, use it instead of
		// using the dynamically set one above

		if (ee()->TMPL->fetch_param('entry_id'))
		{
			$entry_id = ee()->TMPL->fetch_param('entry_id');
		}

		// ----------------------------------------------
		//	Only Entries with Pages
		// ----------------------------------------------

		if (ee()->TMPL->fetch_param('show_pages') !== FALSE && in_array(ee()->TMPL->fetch_param('show_pages'), array('only', 'no')) && ($pages = ee()->config->item('site_pages')) !== FALSE)
		{
			$entry_id = ((ee()->TMPL->fetch_param('show_pages') == 'no') ? 'not ' : '').implode('|', array_flip($pages['uris']));
		}

		// ----------------------------------------------
		//	Assing the order variables
		// ----------------------------------------------

		$order  = ee()->TMPL->fetch_param('orderby');
		$sort   = ee()->TMPL->fetch_param('sort');
		$sticky = ee()->TMPL->fetch_param('sticky');

		// -------------------------------------
		//	Multiple Orders and Sorts...
		// -------------------------------------

		if ($order !== FALSE && stristr($order, '|'))
		{
			$order_array = explode('|', $order);

			if ($order_array[0] == 'random')
			{
				$order_array = array('random');
			}
		}
		else
		{
			$order_array = array($order);
		}

		if ($sort !== FALSE && stristr($sort, '|'))
		{
			$sort_array = explode('|', $sort);
		}
		else
		{
			$sort_array = array($sort);
		}

		// -------------------------------------
		//	Validate Results for Later Processing
		// -------------------------------------

		$base_orders = array('random', 'date', 'title', 'url_title', 'edit_date', 'comment_total', 'username', 'screen_name', 'most_recent_comment', 'expiration_date',
							 'view_count_one', 'view_count_two', 'view_count_three', 'view_count_four',
							 'timeframe_count');

		if ( $order !== FALSE)
		{
			$query	= ee()->db->query('SELECT field_id, field_name FROM exp_rating_fields');
			$rating_orders = array();
	
			foreach ($query->result_array() as $row)
			{
				$rating_orders['count_'.$row['field_name']]	= 'count_'.$row['field_id'];
				$rating_orders['sum_'.$row['field_name']]	= 'sum_'.$row['field_id'];
				$rating_orders['avg_'.$row['field_name']]	= 'avg_'.$row['field_id'];
				
				$rating_orders[$row['field_name']]			= 'avg_'.$row['field_id'];
			}
		}

		$orderby_ratings = FALSE;

		foreach($order_array as $key => $order)
		{
			if ($order !== FALSE && ! in_array($order, $base_orders))
			{
				if (array_key_exists($order, $rating_orders))
				{
					$corder[$key] = $rating_orders[$order];
					$order_array[$key] = 'rating_field';
					$orderby_ratings = TRUE;
				}
				else
				{
					$set = 'n';

					foreach($this->channel->cfields as $site_id => $cfields)
					{
						if (isset($cfields[$order]))
						{
							$corder[$key] = $cfields[$order];
							$order_array[$key] = 'custom_field';
							$set = 'y';
							break;
						}
					}

					if ($set == 'n')
					{
						$order_array[$key] = FALSE;
					}
				}
			}

			if ( ! isset($sort_array[$key]))
			{
				$sort_array[$key] = 'desc';
			}
		}

		foreach($sort_array as $key => $sort)
		{
			if ($sort == FALSE || ($sort != 'asc' AND $sort != 'desc'))
			{
				$sort_array[$key] = "desc";
			}
		}

		// ----------------------------------------------
		//	Build the master SQL query
		// ----------------------------------------------

		$sql_a = "SELECT ";

		$sql_b = (ee()->TMPL->fetch_param('category') || ee()->TMPL->fetch_param('category_group') || $cat_id != '' || $order_array[0] == 'random') ? "DISTINCT(t.entry_id) " : "t.entry_id ";

		if ($this->channel->field_pagination == TRUE)
		{
			$sql_b .= ",wd.* ";
		}
		
		$sql_c = "COUNT(t.entry_id) AS count ";

		$sql = "FROM {$this->sc->db->channel_titles} AS t
				LEFT JOIN {$this->sc->db->channels} ON t.{$this->sc->db->channel_id} = {$this->sc->db->channels}.{$this->sc->db->channel_id} ";

		if ($this->channel->field_pagination == TRUE)
		{
			$sql .= "LEFT JOIN {$this->sc->db->channel_data} AS wd ON t.entry_id = wd.entry_id ";
		}

		if (in_array('custom_field', $order_array))
		{
			$sql .= "LEFT JOIN {$this->sc->db->channel_data} AS wd ON t.entry_id = wd.entry_id ";
		}

		$sql .= "LEFT JOIN exp_members AS m ON m.member_id = t.author_id";

		if ($orderby_ratings || ee()->TMPL->fetch_param('timeframe_start') || ee()->TMPL->fetch_param('timeframe_end') )
		{
			$collection	= ( ee()->TMPL->fetch_param('collection') ) ? ee()->TMPL->fetch_param('collection'): 'all';
			
			$sql .=	" LEFT JOIN exp_ratings AS r ON r.entry_id = t.entry_id
					  LEFT JOIN exp_rating_stats as s ON (s.entry_id = t.entry_id AND s.collection = '".ee()->db->escape_str($collection)."') ";
		}
		else
		{
			$sql .= " LEFT JOIN exp_ratings AS r ON r.entry_id = t.entry_id
					  LEFT JOIN exp_rating_stats AS s ON (s.entry_id = r.entry_id AND s.collection = r.collection)";
		}

		if (ee()->TMPL->fetch_param('category') || ee()->TMPL->fetch_param('category_group') || $cat_id != '')
		{
			/* --------------------------------
			/*  We use LEFT JOIN when there is a 'not' so that we get
			/*  entries that are not assigned to a category.
			/* --------------------------------*/

			if ((substr(ee()->TMPL->fetch_param('category_group'), 0, 3) == 'not' OR substr(ee()->TMPL->fetch_param('category'), 0, 3) == 'not') && ee()->TMPL->fetch_param('uncategorized_entries') !== 'n')
			{
				$sql .= "LEFT JOIN exp_category_posts ON t.entry_id = exp_category_posts.entry_id
						 LEFT JOIN exp_categories ON exp_category_posts.cat_id = exp_categories.cat_id ";
			}
			else
			{
				$sql .= "INNER JOIN exp_category_posts ON t.entry_id = exp_category_posts.entry_id
						 INNER JOIN exp_categories ON exp_category_posts.cat_id = exp_categories.cat_id ";
			}
		}
		
		// --------------------------------------------
        //	Choose a Start/Stop Time for Ratings
        // --------------------------------------------
		
		$t_start_sql = '';
		$t_end_sql = '';

		if ( ee()->TMPL->fetch_param('timeframe_start') || ee()->TMPL->fetch_param('timeframe_end') )
		{
			$start = ( ee()->TMPL->fetch_param('timeframe_start') ) ? strtotime( ee()->TMPL->fetch_param('timeframe_start') ) : 0;
			$end = ( ee()->TMPL->fetch_param('timeframe_end') ) ? strtotime( ee()->TMPL->fetch_param('timeframe_end') ) : 0;
			
			if (ctype_digit($start) && $start > 0 )
			{
				$sql .= $t_start_sql = " AND r.rating_date >= '$start' ";
				if ($end > $start )
				{
					$sql .= $t_end_sql = " AND r.rating_date <= '$end' ";
				}
			}
			elseif (ctype_digit($end) && $end > 0 )
			{
				$sql .= $t_end_sql = " AND r.rating_date <= '$end' ";
			}
		}

		// join data table if we're searching fields
		if (! empty(ee()->TMPL->search_fields) && strpos($sql, '{$this->sc->db->channel_data} AS wd') === FALSE)
		{
			$sql .= " LEFT JOIN {$this->sc->db->channel_data} AS wd ON wd.entry_id = t.entry_id ";
		}

		$sql .= " WHERE t.entry_id !='' AND t.site_id IN ('".implode("','", ee()->TMPL->site_ids)."') ";

		// ----------------------------------------------
		//	We only select entries that have not expired
		// ----------------------------------------------

		$timestamp = (ee()->TMPL->cache_timestamp != '') ? ee()->TMPL->cache_timestamp : ee()->localize->now;

		if (ee()->TMPL->fetch_param('show_future_entries') != 'yes')
		{
			$sql .= " AND t.entry_date < ".$timestamp." ";
		}

		if (ee()->TMPL->fetch_param('show_expired') != 'yes')
		{
			$sql .= " AND (t.expiration_date = 0 || t.expiration_date > ".$timestamp.") ";
		}

		// ----------------------------------------------
		//	Limit query by form name
		// ----------------------------------------------

		if ($collection = ee()->TMPL->fetch_param('collection') )
		{
			$sql .= " AND rs.collection = '".ee()->db->escape_str($collection)."' ";
		}

		// ----------------------------------------------
		//	Limit query by post ID for individual entries
		// ----------------------------------------------

		if ($entry_id != '')
		{
			$sql .= ee()->functions->sql_andor_string($entry_id, 't.entry_id').' ';
		}

		// ----------------------------------------------
		//	Limit query by post url_title for individual entries
		// ----------------------------------------------

		if ($url_title = ee()->TMPL->fetch_param('url_title'))
		{
			$sql .= ee()->functions->sql_andor_string($url_title, 't.url_title').' ';
		}

		// ----------------------------------------------
		//	Limit query by entry_id range
		// ----------------------------------------------

		if ($entry_id_from = ee()->TMPL->fetch_param('entry_id_from') && ctype_digit($entry_id_from))
		{
			$sql .= "AND t.entry_id >= '$entry_id_from' ";
		}

		if ($entry_id_to = ee()->TMPL->fetch_param('entry_id_to') && ctype_digit($entry_id_to))
		{
			$sql .= "AND t.entry_id <= '$entry_id_to' ";
		}

		// ----------------------------------------------
		//	Exclude an individual entry
		// ----------------------------------------------

		if ($not_entry_id = ee()->TMPL->fetch_param('not_entry_id'))
		{
			$sql .= ( ! ctype_digit($not_entry_id))
					? "AND t.url_title != '".ee()->db->escape_str($not_entry_id)."' "
					: "AND t.entry_id  != '{$not_entry_id}' ";
		}

		// ----------------------------------------------
		//	Limit to/exclude specific channels/weblogs
		// ----------------------------------------------
		
		if ($channel = ee()->TMPL->fetch_param($this->sc->channel))
		{
			$xql = "SELECT {$this->sc->db->channel_id} FROM {$this->sc->db->channels} WHERE ";

			$str = ee()->functions->sql_andor_string($channel, $this->sc->db->channel_name);

			if (substr($str, 0, 3) == 'AND')
			{
				$str = substr($str, 3);
			}
			
			$xql .= $str;

			$query = ee()->db->query($xql);

			if ($query->num_rows() == 0)
			{
				return '';
			}
			else
			{
				$channel_ids = array();

				foreach ($query->result_array() as $row)
				{
					$channel_ids[] = $row[$this->sc->db->channel_id];
				}
				
				$sql .= "t.{$this->sc->db->channel_id} IN ('".implode("','", ee()->db->escape_str($channel_ids))."'";
			}
		}
		// ----------------------------------------------------
		//	Limit query by date range given in tag parameters
		// ----------------------------------------------------

		if (ee()->TMPL->fetch_param('start_on') !== FALSE)
		{
			$sql .= " AND t.entry_date >= '".ee()->db->escape_str(ee()->localize->convert_human_date_to_gmt(ee()->TMPL->fetch_param('start_on')))."' ";
		}
		
		if (ee()->TMPL->fetch_param('stop_before') !== FALSE)
		{
			$sql .= " AND t.entry_date < '".ee()->db->escape_str(ee()->localize->convert_human_date_to_gmt(ee()->TMPL->fetch_param('stop_before')))."' ";
		}

		// -----------------------------------------------------
		//	Limit query by date contained in tag parameters
		// -----------------------------------------------------

		if (ee()->TMPL->fetch_param('year') || ee()->TMPL->fetch_param('month') || ee()->TMPL->fetch_param('day'))
		{
			$year	= ( ! ee()->TMPL->fetch_param('year')) 	? date('Y') : ee()->TMPL->fetch_param('year');
			$smonth	= ( ! ee()->TMPL->fetch_param('month'))	? '01' : ee()->TMPL->fetch_param('month');
			$emonth	= ( ! ee()->TMPL->fetch_param('month'))	? '12':  ee()->TMPL->fetch_param('month');
			$day	= ( ! ee()->TMPL->fetch_param('day'))	? '' : ee()->TMPL->fetch_param('day');

			if ($day != '' AND ! ee()->TMPL->fetch_param('month'))
			{
				$smonth = date('m');
				$emonth = date('m');
			}

			if (strlen($smonth) == 1) $smonth = '0'.$smonth;
			if (strlen($emonth) == 1) $emonth = '0'.$emonth;

			if ($day == '')
			{
				$sday = 1;
				$eday = ee()->localize->fetch_days_in_month($emonth, $year);
			}
			else
			{
				$sday = $day;
				$eday = $day;
			}

			$stime = gmmktime(0, 0, 0, $smonth, $sday, $year);
			$etime = gmmktime(23, 59, 59, $emonth, $eday, $year);

			$sql .= " AND t.entry_date >= ".ceil($stime)." AND t.entry_date <= ".ceil($etime)." ";
		}
		else
		{
			// ------------------------------------------------
			//	Limit query by date in URI: /2003/12/14/
			// -------------------------------------------------

			if ($year != '' AND $month != '' AND $dynamic == TRUE)
			{
				if ($day == '')
				{
					$sday = 1;
					$eday = ee()->localize->fetch_days_in_month($month, $year);
				}
				else
				{
					$sday = $day;
					$eday = $day;
				}

				$stime = gmmktime(0, 0, 0, $month, $sday, $year);
				$etime = gmmktime(23, 59, 59, $month, $eday, $year);

				if (date("I", ee()->localize->now) AND ! date("I", $stime))
				{
					$stime -= 3600;
				}
				elseif ( ! date("I", ee()->localize->now) AND date("I", $stime))
				{
					$stime += 3600;
				}

				$stime += ee()->localize->set_localized_offset();

				if (date("I", ee()->localize->now) AND ! date("I", $etime))
				{
					$etime -= 3600;
				}
				elseif ( ! date("I", ee()->localize->now) AND date("I", $etime))
				{
					$etime += 3600;
				}

				$etime += ee()->localize->set_localized_offset();

				$sql .= " AND t.entry_date >= ".ceil($stime)." AND t.entry_date <= ".ceil($etime)." ";
			}
			else
			{
				$this->channel->display_by = ee()->TMPL->fetch_param('display_by');

				$lim = ( ! ee()->TMPL->fetch_param('limit')) ? '1' : ee()->TMPL->fetch_param('limit');

				// -------------------------------------------
				//	If display_by = "month"
				// -------------------------------------------

				if ($this->channel->display_by == 'month')
				{
					// We need to run a query and fetch the distinct months in which there are entries

					$dql = "SELECT t.year, t.month ".$sql;

					// ----------------------------------------------
					//	Add status declaration
					// ----------------------------------------------

					if ($status = ee()->TMPL->fetch_param('status'))
					{
						$status = str_replace('Open',   'open',   $status);
						$status = str_replace('Closed', 'closed', $status);

						$sstr = ee()->functions->sql_andor_string($status, 't.status');

						if ( ! preg_match("/'closed'/", $sstr))
						{
							$sstr .= " AND t.status != 'closed' ";
						}

						$dql .= $sstr;
					}
					else
					{
						$dql .= "AND t.status = 'open' ";
					}

					$query = ee()->db->query($dql);

					$distinct = array();

					if ($query->num_rows() > 0)
					{
						foreach ($query->result_array() as $row)
						{
							$distinct[] = $row['year'].$row['month'];
						}

						$distinct = array_unique($distinct);

						sort($distinct);

						if ($sort_array[0] == 'desc')
						{
							$distinct = array_reverse($distinct);
						}

						$this->channel->total_rows = count($distinct);

						$cur = ($this->channel->p_page == '') ? 0 : $this->channel->p_page;

						$distinct = array_slice($distinct, $cur, $lim);

						if ($distinct != FALSE)
						{
							$sql .= "AND (";

							foreach ($distinct as $val)
							{
								$sql .= "(t.year  = '".substr($val, 0, 4)."' AND t.month = '".substr($val, 4, 2)."') OR";
							}

							$sql = substr($sql, 0, -2).')';
						}
					}
				}


				// -------------------------------------------
				//	If display_by = "day"
				// -------------------------------------------

				elseif ($this->channel->display_by == 'day')
				{
					// We need to run a query and fetch the distinct days in which there are entries

					$dql = "SELECT t.year, t.month, t.day ".$sql;

					// ----------------------------------------------
					//	Add status declaration
					// ----------------------------------------------

					if ($status = ee()->TMPL->fetch_param('status'))
					{
						$status = str_replace('Open',   'open',   $status);
						$status = str_replace('Closed', 'closed', $status);

						$sstr = ee()->functions->sql_andor_string($status, 't.status');

						if ( ! preg_match("/'closed'/", $sstr))
						{
							$sstr .= " AND t.status != 'closed' ";
						}

						$dql .= $sstr;
					}
					else
					{
						$dql .= "AND t.status = 'open' ";
					}

					$query = ee()->db->query($dql);

					$distinct = array();

					if ($query->num_rows() > 0)
					{
						foreach ($query->result_array() as $row)
						{
							$distinct[] = $row['year'].$row['month'].$row['day'];
						}

						$distinct = array_unique($distinct);
						sort($distinct);

						if ($sort_array[0] == 'desc')
						{
							$distinct = array_reverse($distinct);
						}

						$this->channel->total_rows = count($distinct);

						$cur = ($this->channel->p_page == '') ? 0 : $this->channel->p_page;

						$distinct = array_slice($distinct, $cur, $lim);

						if ($distinct != FALSE)
						{
							$sql .= "AND (";

							foreach ($distinct as $val)
							{
								$sql .= "(t.year  = '".substr($val, 0, 4)."' AND t.month = '".substr($val, 4, 2)."' AND t.day   = '".substr($val, 6)."' ) OR";
							}

							$sql = substr($sql, 0, -2).')';
						}
					}
				}

				// -------------------------------------------
				//	If display_by = "week"
				// -------------------------------------------

				elseif ($this->channel->display_by == 'week')
				{
					/** ---------------------------------
					/*	 Run a Query to get a combined Year and Week value.  There is a downside
					/*	 to this approach and that is the lack of localization and use of DST for
					/*	 dates.  Unfortunately, without making a complex and ultimately fubar'ed
					/*   PHP script this is the best approach possible.
					/*  ---------------------------------*/

					if (ee()->TMPL->fetch_param('start_day') === 'Monday')
					{
						$dql = "SELECT DATE_FORMAT(FROM_UNIXTIME(entry_date), '%x%v') as yearweek ".$sql;
					}
					else
					{
						$dql = "SELECT DATE_FORMAT(FROM_UNIXTIME(entry_date), '%X%V') as yearweek ".$sql;
					}

					// ----------------------------------------------
					//	Add status declaration
					// ----------------------------------------------

					if ($status = ee()->TMPL->fetch_param('status'))
					{
						$status = str_replace('Open',   'open',   $status);
						$status = str_replace('Closed', 'closed', $status);

						$sstr = ee()->functions->sql_andor_string($status, 't.status');

						if ( ! preg_match("/'closed'/", $sstr))
						{
							$sstr .= " AND t.status != 'closed' ";
						}

						$dql .= $sstr;
					}
					else
					{
						$dql .= "AND t.status = 'open' ";
					}

					$query = ee()->db->query($dql);

					$distinct = array();

					if ($query->num_rows() > 0)
					{
						/** ---------------------------------
						/*	 Sort Default is ASC for Display By Week so that entries are displayed
						/*   oldest to newest in the week, which is how you would expect.
						/*  ---------------------------------*/

						if (ee()->TMPL->fetch_param('sort') === FALSE)
						{
							$sort_array[0] = 'asc';
						}

						foreach ($query->result_array() as $row)
						{
							$distinct[] = $row['yearweek'];
						}

						$distinct = array_unique($distinct);
						rsort($distinct);

						if (ee()->TMPL->fetch_param('week_sort') == 'desc')
						{
							$distinct = array_reverse($distinct);
						}

						$this->channel->total_rows = count($distinct);
						$cur = ($this->channel->p_page == '') ? 0 : $this->channel->p_page;

						/** ---------------------------------
						/*	 If no pagination, then the Current Week is shown by default with
						/*	 all pagination correctly set and ready to roll, if used.
						/*  ---------------------------------*/

						if (ee()->TMPL->fetch_param('show_current_week') === 'yes' && $this->channel->p_page == '')
						{
							if (ee()->TMPL->fetch_param('start_day') === 'Monday')
							{
								$query = ee()->db->query("SELECT DATE_FORMAT(CURDATE(), '%x%v') AS thisWeek");
							}
							else
							{
								$query = ee()->db->query("SELECT DATE_FORMAT(CURDATE(), '%X%V') AS thisWeek");
							}

							foreach($distinct as $key => $week)
							{
								if ($week == $query->row('thisWeek'))
								{
									$cur = $key;
									$this->channel->p_page = $key;
									break;
								}
							}
						}

						$distinct = array_slice($distinct, $cur, $lim);

						/** ---------------------------------
						/*	 Finally, we add the display by week SQL to the query
						/*  ---------------------------------*/

						if ($distinct != FALSE)
						{
							/**
								A Rough Attempt to Get the Localized Offset Added On
							*/

							$offset = ee()->localize->set_localized_offset();
							$dst_on = (date("I", ee()->localize->now) === 1) ? TRUE : FALSE;

							$sql .= "AND (";

							foreach ($distinct as $val)
							{
								if ($dst_on === TRUE AND (substr($val, 4) < 13 OR substr($val, 4) >= 43))
								{
									$offset -= 3600;
								}
								elseif ($dst_on === FALSE AND (substr($val, 4) >= 13 AND substr($val, 4) < 43))
								{
									$offset += 3600;
								}

								$sql_offset = ($offset < 0) ? "- ".abs($offset) : "+ ".$offset;

								if (ee()->TMPL->fetch_param('start_day') === 'Monday')
								{
									$sql .= " DATE_FORMAT(FROM_UNIXTIME(entry_date {$sql_offset}), '%x%v') = '".ee()->db->escape_str($val)."' OR";
								}
								else
								{
									$sql .= " DATE_FORMAT(FROM_UNIXTIME(entry_date {$sql_offset}), '%X%V') = '".ee()->db->escape_str($val)."' OR";
								}
							}

							$sql = substr($sql, 0, -2).')';
						}
					}
				}
			}
		}


		// ----------------------------------------------
		//	Limit query "URL title"
		// ----------------------------------------------

		if ($qtitle != '' AND $dynamic)
		{
			$sql .= "AND t.url_title = '".ee()->db->escape_str($qtitle)."' ";

			// We use this with hit tracking....

			$this->channel->hit_tracking_id = $qtitle;
		}


		// We set a global variable which we use with entry hit tracking

		if ($entry_id != '' AND $this->channel->entry_id !== FALSE)
		{
			$this->channel->hit_tracking_id = $entry_id;
		}

		// ----------------------------------------------
		//	Limit query by category
		// ----------------------------------------------

		if (ee()->TMPL->fetch_param('category'))
		{
			if (stristr(ee()->TMPL->fetch_param('category'), '&'))
			{
				// --------------------------------------
				//	First, we find all entries with these categories
				// --------------------------------------

				$for_sql = (substr(ee()->TMPL->fetch_param('category'), 0, 3) == 'not') ? trim(substr(ee()->TMPL->fetch_param('category'), 3)) : ee()->TMPL->fetch_param('category');

				$csql = "SELECT exp_category_posts.entry_id, exp_category_posts.cat_id ".
						$sql.
						ee()->functions->sql_andor_string(str_replace('&', '|', $for_sql), 'exp_categories.cat_id');

				//exit($csql);

				$results = ee()->db->query($csql);

				if ($results->num_rows() == 0)
				{
					return;
				}

				$type = 'IN';
				$categories	 = explode('&', ee()->TMPL->fetch_param('category'));
				$entry_array = array();

				if (substr($categories[0], 0, 3) == 'not')
				{
					$type = 'NOT IN';

					$categories[0] = trim(substr($categories[0], 3));
				}

				foreach($results->result_array() as $row)
				{
					$entry_array[$row['cat_id']][] = $row['entry_id'];
				}

				if (sizeof($entry_array) < 2 OR sizeof(array_diff($categories, array_keys($entry_array))) > 0)
				{
					return;
				}

				$chosen = call_user_func_array('array_intersect', $entry_array);

				if (sizeof($chosen) == 0)
				{
					return;
				}

				$sql .= "AND t.entry_id ".$type." ('".implode("','", $chosen)."') ";
			}
			else
			{
				if (substr(ee()->TMPL->fetch_param('category'), 0, 3) == 'not' && ee()->TMPL->fetch_param('uncategorized_entries') !== 'n')
				{
					$sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('category'), 'exp_categories.cat_id', '', TRUE)." ";
				}
				else
				{
					$sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('category'), 'exp_categories.cat_id')." ";
				}
			}
		}

		if (ee()->TMPL->fetch_param('category_group'))
		{
			if (substr(ee()->TMPL->fetch_param('category_group'), 0, 3) == 'not' && ee()->TMPL->fetch_param('uncategorized_entries') !== 'n')
			{
				$sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('category_group'), 'exp_categories.group_id', '', TRUE)." ";
			}
			else
			{
				$sql .= ee()->functions->sql_andor_string(ee()->TMPL->fetch_param('category_group'), 'exp_categories.group_id')." ";
			}
		}

		if (ee()->TMPL->fetch_param('category') === FALSE && ee()->TMPL->fetch_param('category_group') === FALSE)
		{
			if ($cat_id != '' AND $dynamic)
			{
				$sql .= " AND exp_categories.cat_id = '".ee()->db->escape_str($cat_id)."' ";
			}
		}

		// ----------------------------------------------
		//	Limit to (or exclude) specific users
		// ----------------------------------------------

		if ($username = ee()->TMPL->fetch_param('username'))
		{
			// Shows entries ONLY for currently logged in user

			if ($username == 'CURRENT_USER')
			{
				$sql .=  "AND m.member_id = '".ee()->db->escape_str(ee()->session->userdata['member_id'])."' ";
			}
			elseif ($username == 'NOT_CURRENT_USER')
			{
				$sql .=  "AND m.member_id != '".ee()->db->escape_str(ee()->session->userdata['member_id'])."' ";
			}
			else
			{
				$sql .= ee()->functions->sql_andor_string($username, 'm.username');
			}
		}

		// ----------------------------------------------
		//	Add status declaration
		// ----------------------------------------------

		if ($status = ee()->TMPL->fetch_param('status'))
		{
			$status = str_replace('Open',   'open',   $status);
			$status = str_replace('Closed', 'closed', $status);

			$sstr = ee()->functions->sql_andor_string($status, 't.status');

			if ( ! preg_match("/'closed'/", $sstr))
			{
				$sstr .= " AND t.status != 'closed' ";
			}

			$sql .= $sstr;
		}
		else
		{
			$sql .= "AND t.status = 'open' ";
		}

		// ----------------------------------------------
		//	Add Group ID clause
		// ----------------------------------------------

		if ($group_id = ee()->TMPL->fetch_param('group_id'))
		{
			$sql .= ee()->functions->sql_andor_string($group_id, 'm.group_id');
		}

		// ----------------------------------------
		//	Get average rating parameter - Not found in DOCS, so removed
		// ----------------------------------------
/*
		if (ee()->TMPL->fetch_param('average_rating'))
		{
			preg_match('#([\D^ ]{0,2} ?)?(\d{1,}(?:\.\d{1,})?)(?: ([\D^ ]{1,2}) ?(\d{1,}(?:\.\d{1,})?))?#', ee()->TMPL->fetch_param('average_rating'), $matches);
			
			if ((isset($matches[1]) && $matches[1]) && isset($matches[2]))
			{
				$sql .= ' AND s.avg ' . $matches[1] . '"' . $matches[2] . '"';
				
				if (isset($matches[3]) && $matches[3] && isset($matches[4]))
				{
					$sql .= ' AND s.avg ' . $matches[3] . ' "' . $matches[4] . '"';
				}
			}
			elseif (isset($matches[2]) && ctype_digit($matches[2]))
			{
				$sql .= ' AND s.avg = "' . $matches[2] . '"';
			}
		}
*/
		// ---------------------------------------
	    //	Field searching
    	// ---------------------------------------
		
		if (! empty(ee()->TMPL->search_fields))
		{
			foreach (ee()->TMPL->search_fields as $field_name => $terms)
			{
				if (isset($this->channel->cfields[ee()->config->item('site_id')][$field_name]))
				{
					if (strncmp($terms, '=', 1) ==  0)
					{
						// ---------------------------------------
						//	Exact Match e.g.: search:body="=pickle"
						// ---------------------------------------

						$terms = substr($terms, 1);

						// special handling for IS_EMPTY
						if (strpos($terms, 'IS_EMPTY') !== FALSE)
						{
							$terms = str_replace('IS_EMPTY', '', $terms);

							$add_search = ee()->functions->sql_andor_string($terms, 'wd.field_id_'.$this->channel->cfields[ee()->config->item('site_id')][$field_name]);

							// remove the first AND output by ee()->functions->sql_andor_string() so we can parenthesize this clause
							$add_search = substr($add_search, 3);

							$conj = ($add_search != '' && strncmp($terms, 'not ', 4) != 0) ? 'OR' : 'AND';

							if (strncmp($terms, 'not ', 4) == 0)
							{
								$sql .= 'AND ('.$add_search.' '.$conj.' wd.field_id_'.$this->channel->cfields[ee()->config->item('site_id')][$field_name].' != "") ';
							}
							else
							{
								$sql .= 'AND ('.$add_search.' '.$conj.' wd.field_id_'.$this->channel->cfields[ee()->config->item('site_id')][$field_name].' = "") ';
							}
						}
						else
						{
							$sql .= ee()->functions->sql_andor_string($terms, 'wd.field_id_'.$this->channel->cfields[ee()->config->item('site_id')][$field_name]).' ';
						}
					}
					else
					{
						// ---------------------------------------
						//	"Contains" e.g.: search:body="pickle"
						// ---------------------------------------

						if (strncmp($terms, 'not ', 4) == 0)
						{
							$terms = substr($terms, 4);
							$like = 'NOT LIKE';
						}
						else
						{
							$like = 'LIKE';
						}

						if (strpos($terms, '&&') !== FALSE)
						{
							$terms = explode('&&', $terms);
							$andor = (strncmp($like, 'NOT', 3) == 0) ? 'OR' : 'AND';
						}
						else
						{
							$terms = explode('|', $terms);
							$andor = (strncmp($like, 'NOT', 3) == 0) ? 'AND' : 'OR';
						}

						$sql .= ' AND (';

						foreach ($terms as $term)
						{
							if ($term == 'IS_EMPTY')
							{
								$sql .= ' wd.field_id_'.$this->channel->cfields[ee()->config->item('site_id')][$field_name].' '.$like.' "" '.$andor;
							}
							elseif (strpos($term, '\W') !== FALSE) // full word only, no partial matches
							{
								$not = ($like == 'LIKE') ? ' ' : ' NOT ';

								// Note: MySQL's nutty POSIX regex word boundary is [[:>:]]
								// we add slashes because ee()->db->escape_str() strips slashes before adding, and
								// we need them to remain intact or MySQL will not parse the regular expression properly
								$term = '([[:<:]]|^)'.addslashes(preg_quote(str_replace('\W', '', $term))).'([[:>:]]|$)';

								$sql .= ' wd.field_id_'.$this->channel->cfields[ee()->config->item('site_id')][$field_name].$not.'REGEXP "'.ee()->db->escape_str($term).'" '.$andor;
							}
							else
							{
								$sql .= ' wd.field_id_'.$this->channel->cfields[ee()->config->item('site_id')][$field_name].' '.$like.' "%'.ee()->db->escape_str($term).'%" '.$andor;
							}
						}

						$sql = substr($sql, 0, -strlen($andor)).') ';
					}
				}
			}
		}
		
		$sql .= ' GROUP BY r.entry_id ';

		// --------------------------------------------------
		//	Build sorting clause
		// --------------------------------------------------

		// We'll assign this to a different variable since we
		// need to use this in two places

		//	Note that right in this section is where Solspace
		//	changes the ordering schema.

		$end = '';

		if (FALSE === $order_array[0])
		{
			if ($this->check_no($sticky))
			{
				$end .= "ORDER BY s.avg";
			}
			else
			{
				$end .= "ORDER BY t.sticky desc, s.avg";
			}

			if ($sort_array[0] == 'asc' || $sort_array[0] == 'desc')
			{
				$end .= " ".$sort_array[0];
			}
		}
		else
		{
			if ($this->check_no($sticky))
			{
				$end .= "ORDER BY ";
			}
			else
			{
				$end .= "ORDER BY t.sticky desc, ";
			}

			//	Force the ranking order here as well
			if (! $order_array)
			{
				$end	.= " s.avg desc, ";
			}

			foreach($order_array as $key => $order)
			{
				if (in_array($order, array('view_count_one', 'view_count_two', 'view_count_three', 'view_count_four')))
				{
					$view_ct = substr($order, 10);
					$order	 = "view_count";
				}

				if ($key > 0) $end .= ", ";

				switch ($order)
				{
					case 'date' :
						$end .= "t.entry_date";
					break;

					case 'edit_date' :
						$end .= "t.edit_date";
					break;

					case 'expiration_date' :
						$end .= "t.expiration_date";
					break;

					case 'rating' :
					case 'rating_avg' :
						$end .= "s.avg";
					break;

					case 'title' :
						$end .= "t.title";
					break;

					case 'url_title' :
						$end .= "t.url_title";
					break;

					case 'view_count' :
						$vc = $order.$view_ct;

						$end .= " t.{$vc} ".$sort_array[$key].", t.entry_date ".$sort_array[$key];

						$sort_array[$key] = FALSE;
					break;

					case 'comment_total' :
						$end .= "t.comment_total ".$sort_array[$key].", t.entry_date ".$sort_array[$key];
						$sort_array[$key] = FALSE;
					break;

					case 'most_recent_comment' :
						$end .= "t.recent_comment_date ".$sort_array[$key].", t.entry_date ".$sort_array[$key];
						$sort_array[$key] = FALSE;
					break;

					case 'username' :
						$end .= "m.username";
					break;

					case 'screen_name' :
						$end .= "m.screen_name";
					break;

					case 'custom_field' :
						$end .= "wd.field_id_".$corder[$key];
					break;

					case 'random' :
							$end = "ORDER BY rand()";
							$sort_array[$key] = FALSE;
					break;

					case 'rating_field' :
						$end .= 's.'.$corder[$key];
					break;

					case 'total_ratings' :
						$end .= "t.rating_total";
						//@todo - Change this as this field is buh-bye
					break;

					case 'timeframe_rating' :
					case 'timeframe_avg':
						$end .= "rating_timeframe_avg";
					break;

					case 'timeframe_count' :
						$end .= "rating_timeframe_count";
					break;

					case 'timeframe_sum' :
						$end .= "rating_timeframe_sum";
					break;

					default	   :
						$end .= "t.entry_date";
					break;
				}

				if ($sort_array[$key] == 'asc' || $sort_array[$key] == 'desc')
				{
					$end .= " ".$sort_array[$key];
				}

			}
		}

		// ----------------------------------------
		//	Determine the row limits
		// ----------------------------------------
		// Even thouth we don't use the LIMIT clause until the end,
		// we need it to help create our pagination links so we'll
		// set it here

		if ($cat_id  != '' AND ee()->TMPL->fetch_param('cat_limit'))
		{
			$this->channel->p_limit = ee()->TMPL->fetch_param('cat_limit');
		}
		elseif ($month != '' AND ee()->TMPL->fetch_param('month_limit'))
		{
			$this->channel->p_limit = ee()->TMPL->fetch_param('month_limit');
		}
		else
		{
			$this->channel->p_limit  = ( ! ee()->TMPL->fetch_param('limit'))  ? $this->channel->limit : ee()->TMPL->fetch_param('limit');
		}

		// ----------------------------------------------
		//	Is there an offset?
		// ----------------------------------------------
		// We do this here so we can use the offset into next, then later one as well
		$offset = ( ! ee()->TMPL->fetch_param('offset') OR ! ctype_digit(ee()->TMPL->fetch_param('offset'))) ? '0' : ee()->TMPL->fetch_param('offset');

		// ----------------------------------------
		//	Do we need pagination?
		// ----------------------------------------

		// We'll run the query to find out

		if ($this->channel->paginate == TRUE)
		{
			if ($this->channel->field_pagination == FALSE)
			{
				$this->channel->pager_sql = $sql_a.$sql_b.$sql;
				$query = ee()->db->query($this->channel->pager_sql);
				$total = $query->num_rows();

				// Adjust for offset
				if ($total >= $offset)
					$total = $total - $offset;

				$this->channel->create_pagination($total);
			}
			else
			{
				$this->channel->pager_sql = $sql_a.$sql_b.$sql;

				$query = ee()->db->query($this->channel->pager_sql);

				$total = $query->num_rows();
				$this->channel->create_pagination($total, $query);

				if (ee()->config->item('enable_sql_caching') == 'y')
				{
					$this->channel->save_cache($this->channel->pager_sql, 'pagination_query');
					$this->channel->save_cache('1', 'field_pagination');
				}
			}

			if (ee()->config->item('enable_sql_caching') == 'y')
			{
				$this->channel->save_cache($total, 'pagination_count');
			}
		}

		// ----------------------------------------------
		//	Add Limits to query
		// ----------------------------------------------

		$sql .= $end;

		if ($this->channel->paginate == FALSE)
			$this->channel->p_page = 0;

		// Adjust for offset
		$this->channel->p_page += $offset;

		if ($this->channel->display_by == '')
		{
			if (($page_marker == FALSE AND $this->channel->p_limit != '') || ($page_marker == TRUE AND $this->channel->field_pagination != TRUE))
			{
				$sql .= ($this->channel->p_page == '') ? " LIMIT ".$offset.', '.$this->channel->p_limit : " LIMIT ".$this->channel->p_page.', '.$this->channel->p_limit;
			}
			elseif ($entry_id == '' AND $qtitle == '')
			{
				$sql .= ($this->channel->p_page == '') ? " LIMIT ".$this->channel->limit : " LIMIT ".$this->channel->p_page.', '.$this->channel->limit;
			}
		}
		else
		{
			if ($offset != 0)
			{
				$sql .= ($this->channel->p_page == '') ? " LIMIT ".$offset.', '.$this->channel->p_limit : " LIMIT ".$this->channel->p_page.', '.$this->channel->p_limit;
			}
		}

		// ----------------------------------------------
		//	Fetch the entry_id numbers
		// ----------------------------------------------

		$query = ee()->db->query($sql_a.$sql_b.$sql);
		
		//exit($sql_a.$sql_b.$sql);

		if ($query->num_rows() == 0)
		{
			$this->channel->sql = '';
			return;
		}

		// ----------------------------------------------
		//	Build the full SQL query
		// ----------------------------------------------

		$this->channel->sql = "SELECT ";

		if (ee()->TMPL->fetch_param('category') || ee()->TMPL->fetch_param('category_group') || $cat_id != '')
		{
			// Using DISTINCT like this is bogus but since
			// FULL OUTER JOINs are not supported in older versions
			// of MySQL it's our only choice

			$this->channel->sql .= " DISTINCT(t.entry_id), ";
		}

		// DO NOT CHANGE THE ORDER
		// The exp_member_data table needs to be called before the exp_members table.
		
		if (APP_VER < 2.0)
		{
			// Still need all of the trackback fields for EE 1.x.
			
			$this->channel->sql .= " t.entry_id, t.weblog_id, t.forum_topic_id, t.author_id, t.ip_address, t.title, t.url_title, t.status, t.dst_enabled, t.view_count_one, t.view_count_two, t.view_count_three, t.view_count_four, t.allow_comments, t.comment_expiration_date, t.allow_trackbacks, t.sticky, t.entry_date, t.year, t.month, t.day, t.edit_date, t.expiration_date, t.recent_comment_date, t.comment_total, t.trackback_total, t.sent_trackbacks, t.recent_trackback_date,
						w.blog_title, w.blog_name, w.blog_url, w.comment_url, w.tb_return_url, w.comment_moderate, w.weblog_html_formatting, w.weblog_allow_img_urls, w.weblog_auto_link_urls, w.enable_trackbacks, w.trackback_use_url_title, w.trackback_field, w.trackback_use_captcha, w.trackback_system_enabled,
						m.username, m.email, m.url, m.screen_name, m.location, m.occupation, m.interests, m.aol_im, m.yahoo_im, m.msn_im, m.icq, m.signature, m.sig_img_filename, m.sig_img_width, m.sig_img_height, m.avatar_filename, m.avatar_width, m.avatar_height, m.photo_filename, m.photo_width, m.photo_height, m.group_id, m.member_id, m.bday_d, m.bday_m, m.bday_y, m.bio,
						md.*,
						wd.*,
						COUNT(*) AS timeframe_count";
		}
		else
		{

			$this->channel->sql .= " t.entry_id, t.{$this->sc->db->channel_id}, t.forum_topic_id, t.author_id, t.ip_address, t.title, t.url_title, t.status, t.dst_enabled, t.view_count_one, t.view_count_two, t.view_count_three, t.view_count_four, t.allow_comments, t.comment_expiration_date, t.sticky, t.entry_date, t.year, t.month, t.day, t.edit_date, t.expiration_date, t.recent_comment_date, t.comment_total,
							w.{$this->sc->db->channel_title}, w.{$this->sc->db->channel_name}, w.{$this->sc->db->channel_url}, w.comment_url, w.comment_moderate, w.{$this->sc->channel}_html_formatting, w.{$this->sc->channel}_allow_img_urls, w.{$this->sc->channel}_auto_link_urls,
							m.username, m.email, m.url, m.screen_name, m.location, m.occupation, m.interests, m.aol_im, m.yahoo_im, m.msn_im, m.icq, m.signature, m.sig_img_filename, m.sig_img_width, m.sig_img_height, m.avatar_filename, m.avatar_width, m.avatar_height, m.photo_filename, m.photo_width, m.photo_height, m.group_id, m.member_id, m.bday_d, m.bday_m, m.bday_y, m.bio,
							md.*,
							wd.*,
							COUNT(*) AS timeframe_count";
		}
						//s.count AS timeframe_count, s.sum AS timeframe_sum, s.avg AS timeframe_avg";
		// We need to determine which fields to collect timeframe stats for

		$timeframe_avg = array();
		
		foreach ($this->fields_numeric() as $field )
		{
			$timeframe_avg[] = "AVG(r.{$field})";
			$this->channel->sql .= ", AVG(r.{$field}) AS {$field}_timeframe_avg, SUM(r.{$field}) AS {$field}_timeframe_sum, COUNT(r.{$field}) AS {$field}_timeframe_count \n";
		}
		
		$this->channel->sql .= ', ('. implode(' + ', $timeframe_avg) .') / '. count($timeframe_avg) .' AS timeframe_avg';

		$this->channel->sql .= " FROM exp_ratings AS r 
								 LEFT JOIN exp_rating_stats AS s ON s.entry_id = r.entry_id AND s.collection = '".ee()->db->escape_str($collection)."'
								 LEFT JOIN {$this->sc->db->channel_titles} AS t  ON r.entry_id = t.entry_id
								 LEFT JOIN {$this->sc->db->channels} AS w  ON t.{$this->sc->db->channel_id} = w.{$this->sc->db->channel_id}
								 LEFT JOIN {$this->sc->db->channel_data}	AS wd ON t.entry_id = wd.entry_id
								 LEFT JOIN exp_members		AS m  ON m.member_id = t.author_id
								 LEFT JOIN exp_member_data	AS md ON md.member_id = m.member_id ";

		$this->channel->sql .= "WHERE r.entry_id IN (";

		$entries = array();

		// Build ID numbers (checking for duplicates)

		foreach ($query->result_array() as $row)
		{
			if ( ! isset($entries[$row['entry_id']]))
			{
				$entries[$row['entry_id']] = 'y';
			}
			else
			{
				continue;
			}

			$this->channel->sql .= $row['entry_id'].',';
		}

		unset($query);
		unset($entries);

		$this->channel->sql = substr($this->channel->sql, 0, -1).') ';
		$this->channel->sql .= $t_start_sql.$t_end_sql.' GROUP BY r.entry_id ';

		// Change up the orderby if a collection was provided
		if ($collection)
		{
			if ($this->check_no($stick))
			{
				$end = str_replace('ORDER BY', 'ORDER BY s.avg DESC, ', $end);
			}
			else
			{
				$end = str_replace('ORDER BY t.sticky desc', 'ORDER BY t.sticky desc, s.avg DESC', $end);
			}
		}
		
		$this->channel->sql .= $end;
	}
	//	End build sql query rankings


	// --------------------------------------------------------------------

	/**
	 *	Already Rated
	 *
	 *	Sees if an Entry has already been rated by this Member ID3_BEST
	 *
	 *	@access		public
	 *	@param		integer
	 *	@param		boolean
	 *	@return		array
	 */

	public function already_rated($entry_id)
	{
		$duplicate		= FALSE;
		$first_rating	= '';
		$user_ratings	= array();
	
		// --------------------------------------------
        //	Search for Entry within Rating Member Cookie
        // --------------------------------------------
        
        // Used to be like this, but I think this cookie is only meant to be used when someone is
        // NOT logged in.  Perhaps to save a query too?  The problem is that if a rating is deleted
        // this is not updated so it may produce an invalid value.  So, we switched it to rating_rated_0
        
        // if ($ids = ee()->input->cookie( 'rating_rated_'.ee()->session->userdata['member_id']))
        
		if ($ids = ee()->input->cookie( 'rating_rated_0'))
		{
			if (preg_match("/,{$this->entry_id}\|([0-9]+),/", ','.$ids.',', $match))
			{
				$duplicate = TRUE;
				$first_rating = $match[1];
			}
		}
		
		// --------------------------------------------
        //	Search Ratings Table - Only do if duplicate not found or we have {user_rating_#} variable in tag's data
        // --------------------------------------------
        
        if ($duplicate !== TRUE OR (isset(ee()->TMPL) && is_object(ee()->TMPL) && stristr(ee()->TMPL->tagdata, LD.'user_rating_')))
        {
			if (ee()->session->userdata['member_id'] != 0 OR (isset(ee()->TMPL) && ee()->TMPL->fetch_param('duplicate_check_guest_ip') != 'no'))
			{
				$user_ratings = array( 1 => '' );
			
				$sql_extra = (ee()->session->userdata['member_id'] == 0) ? 
							 "ip_address = '".ee()->db->escape_str(ee()->session->userdata['ip_address'])."'" : 
							 "rating_author_id = '".ee()->db->escape_str(ee()->session->userdata['member_id'])."'";
				
				$sql = " SELECT rating FROM exp_ratings
						 WHERE entry_id = '".ee()->db->escape_str($entry_id)."' AND ". $sql_extra ."
						 AND rating > 0 AND quarantine != 'y' AND status != 'closed'
						 ORDER BY rating_date ASC ";
						 
				$prior	= ee()->db->query($sql );
				
				if ($prior->num_rows() > 0 )
				{
					$duplicate = TRUE;
					
					foreach ($prior->result_array() as $k => $row )
					{
						$user_ratings[$k+1] = $row['rating'];
					}
				}
			}
			
			// --------------------------------------------
			//	Create Information Array and Return
			// --------------------------------------------
			
			if ($first_rating == '')
			{
				foreach($user_ratings as $k => $v )
				{
					if ( ! empty($v))
					{
						$first_rating = $v;
						break;
					}
				}
			}
		}
		
		return array(	'duplicate'		=> $duplicate,
						'user_ratings'	=> $user_ratings,
						'first_rating'	=> $first_rating);
	}
	/* END already_rated() */
	
	
	// --------------------------------------------------------------------

	/**
	 *	Already Reviewed
	 *
	 *	Checks to see if this entry or rating has already been reviewed
	 *
	 *	@access		public
	 *	@param		integer|bool
	 *	@param		integer|bool
	 *	@return		string
	 */
	
	public function already_reviewed($entry_id = FALSE, $rating_id = FALSE)
	{
		$ids = '';
		$priors = array();

		if ( empty($entry_id) )
		{
			$entry_id = $this->entry_id();
		}

		if ( empty($rating_id) )
		{
			$rating_id = ee()->TMPL->fetch_param('rating_id');
		}

		if ($ids = ee()->input->cookie( 'rating_reviewed_'.ee()->session->userdata['member_id']))
		{
			if (preg_match("/,{$rating_id}\|(y|n),/", ','.$ids.',', $match))
			{
				$priors[$rating_id]['already_reviewed']		= TRUE;
				$priors[$rating_id]['not_already_reviewed']	= FALSE;
				$priors[$rating_id]['previous_vote']		= ($match[1] == 'y') ? 'up' : 'down';
			}
		}
	
		if (ee()->session->userdata['member_id'] != 0 OR ee()->TMPL->fetch_param('duplicate_check_guest_ip') != 'no')
		{
			$sql = "SELECT COUNT(*) AS count, rating_id, rating_helpful
					FROM exp_rating_reviews
					WHERE (
						author_id = '".ee()->db->escape_str(ee()->session->userdata['member_id'])."'
						OR ip_address = '".ee()->db->escape_str(ee()->session->userdata['ip_address'])."'
					)";
				
			if ( ! empty($rating_id))
			{
				$sql .= " AND rating_id = '".ee()->db->escape_str($rating_id)."'";
			}
			elseif ($entry_id)
			{
				$sql .= " AND entry_id = '".ee()->db->escape_str($entry_id)."'";
			}
			
			$sql .= " GROUP BY rating_id ORDER BY review_date DESC";
			
			$prior = ee()->db->query($sql);
			
			foreach ($prior->result_array() as $row)
			{
				if ($row['count'] > 0)
				{
					$priors[$row['rating_id']]['already_reviewed']		= TRUE;
					$priors[$row['rating_id']]['not_already_reviewed']	= FALSE;
					$priors[$row['rating_id']]['prior_review_count']	= $row['count'];
					$priors[$row['rating_id']]['previous_vote']			= ($row['rating_helpful'] == 'y') ? 'up' : 'down';
				}
				else
				{
					$priors[$row['rating_id']]['already_reviewed']		= FALSE;
					$priors[$row['rating_id']]['not_already_reviewed']	= TRUE;
					$priors[$row['rating_id']]['prior_review_count']	= 0;
					$priors[$row['rating_id']]['previous_vote']			= '';
				}
			}
		}

		return $priors;
	}
	// END already_reviewed	
	
	// --------------------------------------------------------------------

	/**
	 *	Unsubscribe to Entry Rating Notifications
	 *
	 *	@access		public
	 *	@return		string
	 */

	function unsubscribe( )
	{
		if ( ! isset($_GET['rating_id'], $_GET['hash']))
		{
			return FALSE;
		}
	
		// --------------------------------------------
        //	Valid Rating ID?
        // --------------------------------------------
        
        $query = ee()->db->query("	SELECT DISTINCT(email), name as screen_name, entry_id
									FROM exp_ratings AS r
									WHERE r.status = 'open' 
									AND r.notify = 'y'
									AND r.rating_id = '".ee()->db->escape_str($_GET['rating_id'])."'
									LIMIT 1");
						
		if ($query->num_rows() == 0)
		{
			return FALSE;
		}
		
		if ( md5($query->row('email')) != $_GET['hash'])
		{
			return FALSE;
		}
		
		// --------------------------------------------
        //	Remove Subscription
        // --------------------------------------------
        
        ee()->db->query("UPDATE exp_ratings SET notify = 'n' 
						 WHERE email = '".ee()->db->escape_str($query->row('email'))."'
						 AND entry_id = '".ee()->db->escape_str($query->row('entry_id'))."'");
						
		exit(ee()->lang->line('successful_rating_entry_unsubscribe'));
	}
	/**	END subscribe */
}
// END CLASS Rating

// --------------------------------------------------------------------

/*
Find/Replace:

Channel => Rating_channel
exp_channel_titles => {$this->sc->db->channel_titles}
exp_channels => {$this->sc->db->channels}
exp_channel_date => {$this->sc->db->channel_data}
channel_id => {$this->sc->db->channel_id}


Add to constructor:

$this->sc = Addon_builder_rating::generate_shortcuts();
Search for "$rating_sql" to find where code is added.

return $sql_a.$sql_b.$sql;



/**
 * Rating Channel Class
 */

class Rating_channel {

	var $limit	= '100';	// Default maximum query results if not specified.

	// These variable are all set dynamically

	var $query;
	var $TYPE;
	var $entry_id				= '';
	var	$uri					= '';
	var $uristr					= '';
	var $return_data			= '';	 	// Final data
	var $basepath				= '';
	var $hit_tracking_id		= FALSE;
	var	$sql					= FALSE;
	var $cfields				= array();
	var $dfields				= array();
	var $rfields				= array();
	var $mfields				= array();
	var $pfields				= array();
	var $categories				= array();
	var $catfields				= array();
	var $channel_name	 		= array();
	var $channels_array			= array();
	var $related_entries		= array();
	var $reverse_related_entries= array();
	var $reserved_cat_segment 	= '';
	var $use_category_names		= FALSE;
	var $dynamic_sql			= FALSE;
	var $cat_request			= FALSE;
	var $enable					= array();	// modified by various tags with disable= parameter
    var $absolute_results		= NULL;		// absolute total results returned by the tag, useful when paginating

	// These are used with the nested category trees

	var $category_list  		= array();
	var $cat_full_array			= array();
	var $cat_array				= array();
	var $temp_array				= array();
	var $category_count			= 0;

	// Pagination variables

	var $paginate				= FALSE;
	var $field_pagination		= FALSE;
	var $paginate_data			= '';
	var $pagination_links		= '';
	var $page_next				= '';
	var $page_previous			= '';
	var $current_page			= 1;
	var $total_pages			= 1;
	var $multi_fields			= array();
	var $display_by				= '';
	var $total_rows				=  0;
	var $pager_sql				= '';
	var $p_limit				= '';
	var $p_page					= '';


	// SQL Caching

	var $sql_cache_dir			= 'sql_cache/';

	// Misc. - Class variable usable by extensions
	var $misc					= FALSE;

	/**
	  * Constructor
	  */
	function Rating_channel()
	{
		// Make a local reference to the ExpressionEngine super object
		$this->EE =& get_instance();

		$this->p_limit = $this->limit;

		$this->query_string = ($this->EE->uri->page_query_string != '') ? $this->EE->uri->page_query_string : $this->EE->uri->query_string;

		if ($this->EE->config->item("use_category_name") == 'y' && $this->EE->config->item("reserved_category_word") != '')
		{
			$this->use_category_names	= $this->EE->config->item("use_category_name");
			$this->reserved_cat_segment	= $this->EE->config->item("reserved_category_word");
		}

		// a number tags utilize the disable= parameter, set it here
		if (isset($this->EE->TMPL) && is_object($this->EE->TMPL))
		{
			$this->_fetch_disable_param();
		}
		
	}

	// ------------------------------------------------------------------------

	/**
	  *  Initialize values
	  */
	function initialize()
	{
		$this->sql 			= '';
		$this->return_data	= '';
	}
	
	

	// ------------------------------------------------------------------------
	
	/**
	  *  Build SQL query
	  */
	function build_sql_query($qstring = '', $rating_sql = array())
	{
		$entry_id		= '';
		$year			= '';
		$month			= '';
		$day			= '';
		$qtitle			= '';
		$cat_id			= '';
		$corder			= array();
		$offset			=  0;
		$page_marker	= FALSE;
		$dynamic		= TRUE;

		$this->dynamic_sql = TRUE;

		// ------
		//	Is dynamic='off' set?
		// ------

		// If so, we'll override all dynamically set variables

		if ($this->EE->TMPL->fetch_param('dynamic') == 'no')
		{
			$dynamic = FALSE;
		}

		// ------
		//	Do we allow dynamic POST variables to set parameters?
		// ------
		if ($this->EE->TMPL->fetch_param('dynamic_parameters') !== FALSE AND isset($_POST) AND count($_POST) > 0)
		{
			foreach (explode('|', $this->EE->TMPL->fetch_param('dynamic_parameters')) as $var)
			{
				if (isset($_POST[$var]) AND in_array($var, array('channel', 'entry_id', 'category', 'orderby', 'sort', 'sticky', 'show_future_entries', 'show_expired', 'entry_id_from', 'entry_id_to', 'not_entry_id', 'start_on', 'stop_before', 'year', 'month', 'day', 'display_by', 'limit', 'username', 'status', 'group_id', 'cat_limit', 'month_limit', 'offset', 'author_id')))
				{
					$this->EE->TMPL->tagparams[$var] = $_POST[$var];
				}

				if (isset($_POST[$var]) && strncmp($var, 'search:', 7) == 0)
				{
					$this->EE->TMPL->search_fields[substr($var, 7)] = $_POST[$var];
				}
			}
		}

		// ------
		//	Parse the URL query string
		// ------

		$this->uristr = $this->EE->uri->uri_string;

		if ($qstring == '')
			$qstring = $this->query_string;

		$this->basepath = $this->EE->functions->create_url($this->uristr);

		if ($qstring == '')
		{
			if ($this->EE->TMPL->fetch_param('require_entry') == 'yes')
			{
				return '';
			}
		}
		else
		{
			// --------------------------------------
			//	Do we have a pure ID number?
			// --------------------------------------

			if (is_numeric($qstring) AND $dynamic)
			{
				$entry_id = $qstring;
			}
			else
			{
				// Load the string helper
				$this->EE->load->helper('string');

				// --------------------------------------
				//	Parse day
				// --------------------------------------

				if (preg_match("#(^|\/)(\d{4}/\d{2}/\d{2})#", $qstring, $match) AND $dynamic)
				{
					$ex = explode('/', $match[2]);

					$year  = $ex[0];
					$month = $ex[1];
					$day   = $ex[2];

					$qstring = trim_slashes(str_replace($match[0], '', $qstring));
				}

				// --------------------------------------
				//	Parse /year/month/
				// --------------------------------------

				// added (^|\/) to make sure this doesn't trigger with url titles like big_party_2006
				if (preg_match("#(^|\/)(\d{4}/\d{2})(\/|$)#", $qstring, $match) AND $dynamic)
				{
					$ex = explode('/', $match[2]);

					$year	= $ex[0];
					$month	= $ex[1];

					$qstring = trim_slashes(str_replace($match[2], '', $qstring));

					// Removed this in order to allow archive pagination
					// $this->paginate = FALSE;
				}

				// --------------------------------------
				//	Parse ID indicator
				// --------------------------------------
				if (preg_match("#^(\d+)(.*)#", $qstring, $match) AND $dynamic)
				{
					$seg = ( ! isset($match[2])) ? '' : $match[2];

					if (substr($seg, 0, 1) == "/" OR $seg == '')
					{
						$entry_id = $match[1];
						$qstring = trim_slashes(preg_replace("#^".$match[1]."#", '', $qstring));
					}
				}

				// --------------------------------------
				//	Parse page number
				// --------------------------------------

				if (preg_match("#^P(\d+)|/P(\d+)#", $qstring, $match) AND ($dynamic OR $this->EE->TMPL->fetch_param('paginate'))) 
				{
					$this->p_page = (isset($match[2])) ? $match[2] : $match[1];

					$this->basepath = $this->EE->functions->remove_double_slashes(str_replace($match[0], '', $this->basepath));

					$this->uristr  = $this->EE->functions->remove_double_slashes(str_replace($match[0], '', $this->uristr));

					$qstring = trim_slashes(str_replace($match[0], '', $qstring));

					$page_marker = TRUE;
				}

				// --------------------------------------
				//	Parse category indicator
				// --------------------------------------

				// Text version of the category

				if ($qstring != '' AND $this->reserved_cat_segment != '' AND in_array($this->reserved_cat_segment, explode("/", $qstring)) AND $dynamic AND $this->EE->TMPL->fetch_param('channel'))
				{
					$qstring = preg_replace("/(.*?)\/".preg_quote($this->reserved_cat_segment)."\//i", '', '/'.$qstring);

					$sql = "SELECT DISTINCT cat_group FROM {$this->sc->db->channels} WHERE site_id IN ('".implode("','", $this->EE->TMPL->site_ids)."') AND ";

					$xsql = $this->EE->functions->sql_andor_string($this->EE->TMPL->fetch_param('channel'), 'channel_name');

					if (substr($xsql, 0, 3) == 'AND') $xsql = substr($xsql, 3);

					$sql .= ' '.$xsql;

					$query = $this->EE->db->query($sql);

					if ($query->num_rows() > 0)
					{
						$valid = 'y';
						$last  = explode('|', $query->row('cat_group') );
						$valid_cats = array();

						foreach($query->result_array() as $row)
						{
							if ($this->EE->TMPL->fetch_param('relaxed_categories') == 'yes')
							{
								$valid_cats = array_merge($valid_cats, explode('|', $row['cat_group']));
							}
							else
							{
								$valid_cats = array_intersect($last, explode('|', $row['cat_group']));
							}

							$valid_cats = array_unique($valid_cats);

							if (count($valid_cats) == 0)
							{
								$valid = 'n';
								break;
							}
						}
					}
					else
					{
						$valid = 'n';
					}

					if ($valid == 'y')
					{
						// the category URL title should be the first segment left at this point in $qstring,
						// but because prior to this feature being added, category names were used in URLs,
						// and '/' is a valid character for category names.  If they have not updated their
						// category url titles since updating to 1.6, their category URL title could still
						// contain a '/'.  So we'll try to get the category the correct way first, and if
						// it fails, we'll try the whole $qstring

						// do this as separate commands to work around a PHP 5.0.x bug
						$arr = explode('/', $qstring);
						$cut_qstring = array_shift($arr);
						unset($arr);

						$result = $this->EE->db->query("SELECT cat_id FROM exp_categories
											  WHERE cat_url_title='".$this->EE->db->escape_str($cut_qstring)."'
											  AND group_id IN ('".implode("','", $valid_cats)."')");

						if ($result->num_rows() == 1)
						{
							$qstring = str_replace($cut_qstring, 'C'.$result->row('cat_id') , $qstring);
						}
						else
						{
							// give it one more try using the whole $qstring
							$result = $this->EE->db->query("SELECT cat_id FROM exp_categories
												  WHERE cat_url_title='".$this->EE->db->escape_str($qstring)."'
												  AND group_id IN ('".implode("','", $valid_cats)."')");

							if ($result->num_rows() == 1)
							{
								$qstring = 'C'.$result->row('cat_id') ;
							}
						}
					}
				}

				// Numeric version of the category

				if (preg_match("#(^|\/)C(\d+)#", $qstring, $match) AND $dynamic)
				{
					$this->cat_request = TRUE;

					$cat_id = $match[2];

					$qstring = trim_slashes(str_replace($match[0], '', $qstring));
				}

				// --------------------------------------
				//	Remove "N"
				// --------------------------------------

				// The recent comments feature uses "N" as the URL indicator
				// It needs to be removed if presenst

				if (preg_match("#^N(\d+)|/N(\d+)#", $qstring, $match))
				{
					$this->uristr  = $this->EE->functions->remove_double_slashes(str_replace($match[0], '', $this->uristr));

					$qstring = trim_slashes(str_replace($match[0], '', $qstring));
				}

				// --------------------------------------
				//	Parse URL title
				// --------------------------------------
				if (($cat_id == '' AND $year == '') OR $this->EE->TMPL->fetch_param('require_entry') == 'yes')
				{
					if (strpos($qstring, '/') !== FALSE)
					{
						$xe = explode('/', $qstring);
						$qstring = current($xe);
					}

					if ($dynamic == TRUE)
					{
						$sql = "SELECT count(*) AS count
								FROM  {$this->sc->db->channel_titles}, {$this->sc->db->channels}
								WHERE {$this->sc->db->channel_titles}.{$this->sc->db->channel_id} = {$this->sc->db->channels}.{$this->sc->db->channel_id}";

						if ($entry_id != '')
						{
							$sql .= " AND {$this->sc->db->channel_titles}.entry_id = '".$this->EE->db->escape_str($entry_id)."'";							
						}
						else
						{
							$sql .= " AND {$this->sc->db->channel_titles}.url_title = '".$this->EE->db->escape_str($qstring)."'";
						}

						$sql .= " AND {$this->sc->db->channels}.site_id IN ('".implode("','", $this->EE->TMPL->site_ids)."') ";

						$query = $this->EE->db->query($sql);

						if ($query->row('count')  == 0)
						{
							if ($this->EE->TMPL->fetch_param('require_entry') == 'yes')
							{
								return '';
							}

							$qtitle = '';
						}
						else
						{
							$qtitle = $qstring;
						}
					}
				}
			}
		}


		// ------
		//	Entry ID number
		// ------

		// If the "entry ID" was hard-coded, use it instead of
		// using the dynamically set one above

		if ($this->EE->TMPL->fetch_param('entry_id'))
		{
			$entry_id = $this->EE->TMPL->fetch_param('entry_id');
		}

		// ------
		//	Only Entries with Pages
		// ------

		if ($this->EE->TMPL->fetch_param('show_pages') !== FALSE && in_array($this->EE->TMPL->fetch_param('show_pages'), array('only', 'no')) && ($pages = $this->EE->config->item('site_pages')) !== FALSE)
		{
			$pages_uris = array();
			
			foreach ($pages as $data)
			{
				$pages_uris += $data['uris'];
			}
			
			if (count($pages_uris) > 0 OR $this->EE->TMPL->fetch_param('show_pages') == 'only')
			{
				// consider entry_id
				if ($this->EE->TMPL->fetch_param('entry_id') !== FALSE)
				{
					$not = FALSE;

					if (strncmp($entry_id, 'not', 3) == 0)
					{
						$not = TRUE;
						$entry_id = trim(substr($entry_id, 3));
					}

					$ids = explode('|', $entry_id);

					if ($this->EE->TMPL->fetch_param('show_pages') == 'only')
					{
						if ($not === TRUE)
						{
							$entry_id = implode('|', array_diff(array_flip($pages_uris), explode('|', $ids)));
						}
						else
						{
							$entry_id = implode('|',array_diff($ids, array_diff($ids, array_flip($pages_uris))));
						}
					}
					else
					{
						if ($not === TRUE)
						{
							$entry_id = "not {$entry_id}|".implode('|', array_flip($pages_uris));
						}
						else
						{
							$entry_id = implode('|',array_diff($ids, array_flip($pages_uris)));
						}
					}
				}
				else
				{
					$entry_id = (($this->EE->TMPL->fetch_param('show_pages') == 'no') ? 'not ' : '').implode('|', array_flip($pages_uris));
				}
			
				//  No pages and show_pages only
				if ($entry_id == '' && $this->EE->TMPL->fetch_param('show_pages') == 'only')
				{
					$this->sql = '';
					return;
				}
			}			
		}

		// ------
		//	Assing the order variables
		// ------

		$order  = $this->EE->TMPL->fetch_param('orderby');
		$sort	= $this->EE->TMPL->fetch_param('sort');
		$sticky = $this->EE->TMPL->fetch_param('sticky');

		// -------------------------------------
		//	Multiple Orders and Sorts...
		// -------------------------------------

		if ($order !== FALSE && stristr($order, '|'))
		{
			$order_array = explode('|', $order);

			if ($order_array[0] == 'random')
			{
				$order_array = array('random');
			}
		}
		else
		{
			$order_array = array($order);
		}

		if ($sort !== FALSE && stristr($sort, '|'))
		{
			$sort_array = explode('|', $sort);
		}
		else
		{
			$sort_array = array($sort);
		}

		// -------------------------------------
		//	Validate Results for Later Processing
		// -------------------------------------

		$base_orders = array('random', 'entry_id', 'date', 'title', 'url_title', 'edit_date', 'comment_total', 'username', 'screen_name', 'most_recent_comment', 'expiration_date',
							 'view_count_one', 'view_count_two', 'view_count_three', 'view_count_four');

		foreach($order_array as $key => $order)
		{
			if ( ! in_array($order, $base_orders))
			{
				if (FALSE !== $order)
				{
					$set = 'n';

					// -------------------------------------
					//	Site Namespace is Being Used, Parse Out
					// -------------------------------------

					if (strpos($order, ':') !== FALSE)
					{
						$order_parts = explode(':', $order, 2);

						if (isset($this->EE->TMPL->site_ids[$order_parts[0]]) && isset($this->cfields[$this->EE->TMPL->site_ids[$order_parts[0]]][$order_parts[1]]))
						{
							$corder[$key] = $this->cfields[$this->EE->TMPL->site_ids[$order_parts[0]]][$order_parts[1]];
							$order_array[$key] = 'custom_field';
							$set = 'y';
						}
					}

					/** -------------------------------------
					/**  Find the Custom Field, Cycle Through All Sites for Tag
					/**  - If multiple sites have the same short_name for a field, we do a CONCAT ORDERBY in query
					/** -------------------------------------*/

					if ($set == 'n')
					{
						foreach($this->cfields as $site_id => $cfields)
						{
							// Only those sites specified
							if ( ! in_array($site_id, $this->EE->TMPL->site_ids))
							{
								continue;
							}

							if (isset($cfields[$order]))
							{
								if ($set == 'y')
								{
									$corder[$key] .= '|'.$cfields[$order];
								}
								else
								{
									$corder[$key] = $cfields[$order];
									$order_array[$key] = 'custom_field';
									$set = 'y';
								}
							}
						}
					}

					if ($set == 'n')
					{
						$order_array[$key] = FALSE;
					}
				}
			}

			if ( ! isset($sort_array[$key]))
			{
				$sort_array[$key] = 'desc';
			}
		}

		foreach($sort_array as $key => $sort)
		{
			if ($sort == FALSE OR ($sort != 'asc' AND $sort != 'desc'))
			{
				$sort_array[$key] = "desc";
			}
		}

		// fixed entry id ordering
		if (($fixed_order = $this->EE->TMPL->fetch_param('fixed_order')) === FALSE OR preg_match('/[^0-9\|]/', $fixed_order))
		{
			$fixed_order = FALSE;
		}
		else
		{
			// MySQL will not order the entries correctly unless the results are constrained
			// to matching rows only, so we force the entry_id as well
			$entry_id = $fixed_order;
			$fixed_order = preg_split('/\|/', $fixed_order, -1, PREG_SPLIT_NO_EMPTY);

			// some peeps might want to be able to 'flip' it
			// the default sort order is 'desc' but in this context 'desc' has a stronger "reversing"
			// connotation, so we look not at the sort array, but the tag parameter itself, to see the user's intent
			if ($sort == 'desc')
			{
				$fixed_order = array_reverse($fixed_order);
			}
		}

		// ------
		//	Build the master SQL query
		// ------

		$sql_a = "SELECT ";

		$sql_b = " DISTINCT(t.entry_id) ";

		if ($this->field_pagination == TRUE)
		{
			$sql_b .= ",wd.* ";
		}

		$sql_c = "COUNT(t.entry_id) AS count ";

		$sql = "FROM {$this->sc->db->channel_titles} AS t
				LEFT JOIN {$this->sc->db->channels} ON t.{$this->sc->db->channel_id} = {$this->sc->db->channels}.{$this->sc->db->channel_id} ";

		if ($this->field_pagination == TRUE)
		{
			$sql .= "LEFT JOIN {$this->sc->db->channel_data} AS wd ON t.entry_id = wd.entry_id ";
		}
		elseif (in_array('custom_field', $order_array))
		{
			$sql .= "LEFT JOIN {$this->sc->db->channel_data} AS wd ON t.entry_id = wd.entry_id ";
		}
		elseif ( ! empty($this->EE->TMPL->search_fields))
		{
			$sql .= "LEFT JOIN {$this->sc->db->channel_data} AS wd ON wd.entry_id = t.entry_id ";
		}

		$sql .= "LEFT JOIN exp_members AS m ON m.member_id = t.author_id ";


		if ($this->EE->TMPL->fetch_param('category') OR $this->EE->TMPL->fetch_param('category_group') OR $cat_id != '')
		{
			/* --------------------------------
			/*  We use LEFT JOIN when there is a 'not' so that we get
			/*  entries that are not assigned to a category.
			/* --------------------------------*/

			if ((substr($this->EE->TMPL->fetch_param('category_group'), 0, 3) == 'not' OR substr($this->EE->TMPL->fetch_param('category'), 0, 3) == 'not') && $this->EE->TMPL->fetch_param('uncategorized_entries') !== 'n')
			{
				$sql .= "LEFT JOIN exp_category_posts ON t.entry_id = exp_category_posts.entry_id
						 LEFT JOIN exp_categories ON exp_category_posts.cat_id = exp_categories.cat_id ";
			}
			else
			{
				$sql .= "INNER JOIN exp_category_posts ON t.entry_id = exp_category_posts.entry_id
						 INNER JOIN exp_categories ON exp_category_posts.cat_id = exp_categories.cat_id ";
			}
		}
		
		if ( ! empty($rating_sql['join']))
		{
			$sql .= $rating_sql['join'];
		}

		$sql .= "WHERE t.entry_id !='' AND t.site_id IN ('".implode("','", $this->EE->TMPL->site_ids)."') ";
		
		if ( ! empty($rating_sql['where']))
		{
			$sql .= $rating_sql['where'];
		}

		// ------
		//	We only select entries that have not expired
		// ------

		$timestamp = ($this->EE->TMPL->cache_timestamp != '') ? $this->EE->TMPL->cache_timestamp : $this->EE->localize->now;

		if ($this->EE->TMPL->fetch_param('show_future_entries') != 'yes')
		{
			$sql .= " AND t.entry_date < ".$timestamp." ";
		}

		if ($this->EE->TMPL->fetch_param('show_expired') != 'yes')
		{
			$sql .= " AND (t.expiration_date = 0 OR t.expiration_date > ".$timestamp.") ";
		}

		// ------
		//	Limit query by post ID for individual entries
		// ------

		if ($entry_id != '')
		{
			$sql .= $this->EE->functions->sql_andor_string($entry_id, 't.entry_id').' ';
		}

		// ------
		//	Limit query by post url_title for individual entries
		// ------

		if ($url_title = $this->EE->TMPL->fetch_param('url_title'))
		{
			$sql .= $this->EE->functions->sql_andor_string($url_title, 't.url_title').' ';
		}

		// ------
		//	Limit query by entry_id range
		// ------

		if ($entry_id_from = $this->EE->TMPL->fetch_param('entry_id_from'))
		{
			$sql .= "AND t.entry_id >= '$entry_id_from' ";
		}

		if ($entry_id_to = $this->EE->TMPL->fetch_param('entry_id_to'))
		{
			$sql .= "AND t.entry_id <= '$entry_id_to' ";
		}

		// ------
		//	Exclude an individual entry
		// ------
		if ($not_entry_id = $this->EE->TMPL->fetch_param('not_entry_id'))
		{
			$sql .= ( ! is_numeric($not_entry_id))
					? "AND t.url_title != '{$not_entry_id}' "
					: "AND t.entry_id  != '{$not_entry_id}' ";
		}

		// ------
		//	Limit to/exclude specific channels
		// ------

		if ($channel = $this->EE->TMPL->fetch_param('channel'))
		{
			$xql = "SELECT {$this->sc->db->channel_id} FROM {$this->sc->db->channels} WHERE ";

			$str = $this->EE->functions->sql_andor_string($channel, 'channel_name');

			if (substr($str, 0, 3) == 'AND')
			{
				$str = substr($str, 3);				
			}

			$xql .= $str;

			$query = $this->EE->db->query($xql);

			if ($query->num_rows() == 0)
			{
				return '';
			}
			else
			{
				if ($query->num_rows() == 1)
				{
					$sql .= "AND t.{$this->sc->db->channel_id} = '".$query->row($this->sc->db->channel_id) ."' ";
				}
				else
				{
					$sql .= "AND (";

					foreach ($query->result_array() as $row)
					{
						$sql .= "t.{$this->sc->db->channel_id} = '".$row[$this->sc->db->channel_id]."' OR ";
					}

					$sql = substr($sql, 0, - 3);

					$sql .= ") ";
				}
			}
		}

		// ------------
		//	Limit query by date range given in tag parameters
		// ------------
		if ($this->EE->TMPL->fetch_param('start_on'))
		{
			$sql .= "AND t.entry_date >= '".$this->EE->localize->convert_human_date_to_gmt($this->EE->TMPL->fetch_param('start_on'))."' ";			
		}

		if ($this->EE->TMPL->fetch_param('stop_before'))
		{
			$sql .= "AND t.entry_date < '".$this->EE->localize->convert_human_date_to_gmt($this->EE->TMPL->fetch_param('stop_before'))."' ";	
		}

		// -------------
		//	Limit query by date contained in tag parameters
		// -------------

		if ($this->EE->TMPL->fetch_param('year') OR $this->EE->TMPL->fetch_param('month') OR $this->EE->TMPL->fetch_param('day'))
		{
			$year	= ( ! is_numeric($this->EE->TMPL->fetch_param('year'))) 	? date('Y') : $this->EE->TMPL->fetch_param('year');
			$smonth	= ( ! is_numeric($this->EE->TMPL->fetch_param('month')))	? '01' : $this->EE->TMPL->fetch_param('month');
			$emonth	= ( ! is_numeric($this->EE->TMPL->fetch_param('month')))	? '12':  $this->EE->TMPL->fetch_param('month');
			$day	= ( ! is_numeric($this->EE->TMPL->fetch_param('day')))		? '' : $this->EE->TMPL->fetch_param('day');

			if ($day != '' AND ! is_numeric($this->EE->TMPL->fetch_param('month')))
			{
				$smonth = date('m');
				$emonth = date('m');
			}

			if (strlen($smonth) == 1) 
			{
				$smonth = '0'.$smonth;
			}
			
			if (strlen($emonth) == 1) 
			{
				$emonth = '0'.$emonth;
			}
			
			if ($day == '')
			{
				$sday = 1;
				$eday = $this->EE->localize->fetch_days_in_month($emonth, $year);
			}
			else
			{
				$sday = $day;
				$eday = $day;
			}

			$stime = gmmktime(0, 0, 0, $smonth, $sday, $year);
			$etime = gmmktime(23, 59, 59, $emonth, $eday, $year);

			$sql .= " AND t.entry_date >= ".$stime." AND t.entry_date <= ".$etime." ";
		}
		else
		{
			// --------
			//	Limit query by date in URI: /2003/12/14/
			// ---------

			if ($year != '' AND $month != '' AND $dynamic == TRUE)
			{
				if ($day == '')
				{
					$sday = 1;
					$eday = $this->EE->localize->fetch_days_in_month($month, $year);
				}
				else
				{
					$sday = $day;
					$eday = $day;
				}

				$stime = gmmktime(0, 0, 0, $month, $sday, $year);
				$etime = gmmktime(23, 59, 59, $month, $eday, $year);

				if (date("I", $this->EE->localize->now) AND ! date("I", $stime))
				{
					$stime -= 3600;
				}
				elseif ( ! date("I", $this->EE->localize->now) AND date("I", $stime))
				{
					$stime += 3600;
				}

				$stime += $this->EE->localize->set_localized_offset();

				if (date("I", $this->EE->localize->now) AND ! date("I", $etime))
				{
					$etime -= 3600;
				}
				elseif ( ! date("I", $this->EE->localize->now) AND date("I", $etime))
				{
					$etime += 3600;
				}

				$etime += $this->EE->localize->set_localized_offset();

				$sql .= " AND t.entry_date >= ".$stime." AND t.entry_date <= ".$etime." ";
			}
			else
			{
				$this->display_by = $this->EE->TMPL->fetch_param('display_by');

				$lim = ( ! is_numeric($this->EE->TMPL->fetch_param('limit'))) ? '1' : $this->EE->TMPL->fetch_param('limit');

				// ---
				//	If display_by = "month"
				// ---

				if ($this->display_by == 'month')
				{
					// We need to run a query and fetch the distinct months in which there are entries

					$dql = "SELECT t.year, t.month ".$sql;

					// ------
					//	Add status declaration
					// ------

					if ($status = $this->EE->TMPL->fetch_param('status'))
					{
						$status = str_replace('Open',	'open',	$status);
						$status = str_replace('Closed', 'closed', $status);

						$sstr = $this->EE->functions->sql_andor_string($status, 't.status');

						if (stristr($sstr, "'closed'") === FALSE)
						{
							$sstr .= " AND t.status != 'closed' ";
						}

						$dql .= $sstr;
					}
					else
					{
						$dql .= "AND t.status = 'open' ";
					}

					$query = $this->EE->db->query($dql);

					$distinct = array();

					if ($query->num_rows() > 0)
					{
						foreach ($query->result_array() as $row)
						{
							$distinct[] = $row['year'].$row['month'];
						}

						$distinct = array_unique($distinct);

						sort($distinct);

						if ($sort_array[0] == 'desc')
						{
							$distinct = array_reverse($distinct);
						}

						$this->total_rows = count($distinct);

						$cur = ($this->p_page == '') ? 0 : $this->p_page;

						$distinct = array_slice($distinct, $cur, $lim);

						if ($distinct != FALSE)
						{
							$sql .= "AND (";

							foreach ($distinct as $val)
							{
								$sql .= "(t.year  = '".substr($val, 0, 4)."' AND t.month = '".substr($val, 4, 2)."') OR";
							}

							$sql = substr($sql, 0, -2).')';
						}
					}
				}


				// ---
				//	If display_by = "day"
				// ---

				elseif ($this->display_by == 'day')
				{
					// We need to run a query and fetch the distinct days in which there are entries

					$dql = "SELECT t.year, t.month, t.day ".$sql;

					// ------
					//	Add status declaration
					// ------

					if ($status = $this->EE->TMPL->fetch_param('status'))
					{
						$status = str_replace('Open',	'open',	$status);
						$status = str_replace('Closed', 'closed', $status);

						$sstr = $this->EE->functions->sql_andor_string($status, 't.status');

						if (stristr($sstr, "'closed'") === FALSE)
						{
							$sstr .= " AND t.status != 'closed' ";
						}

						$dql .= $sstr;
					}
					else
					{
						$dql .= "AND t.status = 'open' ";
					}

					$query = $this->EE->db->query($dql);

					$distinct = array();

					if ($query->num_rows() > 0)
					{
						foreach ($query->result_array() as $row)
						{
							$distinct[] = $row['year'].$row['month'].$row['day'];
						}

						$distinct = array_unique($distinct);
						sort($distinct);

						if ($sort_array[0] == 'desc')
						{
							$distinct = array_reverse($distinct);
						}

						$this->total_rows = count($distinct);

						$cur = ($this->p_page == '') ? 0 : $this->p_page;

						$distinct = array_slice($distinct, $cur, $lim);

						if ($distinct != FALSE)
						{
							$sql .= "AND (";

							foreach ($distinct as $val)
							{
								$sql .= "(t.year  = '".substr($val, 0, 4)."' AND t.month = '".substr($val, 4, 2)."' AND t.day	= '".substr($val, 6)."' ) OR";
							}

							$sql = substr($sql, 0, -2).')';
						}
					}
				}

				// ---
				//	If display_by = "week"
				// ---

				elseif ($this->display_by == 'week')
				{
					/** ---------------------------------
					/*	 Run a Query to get a combined Year and Week value.  There is a downside
					/*	 to this approach and that is the lack of localization and use of DST for
					/*	 dates.  Unfortunately, without making a complex and ultimately fubar'ed
					/*	PHP script this is the best approach possible.
					/*  ---------------------------------*/

					$loc_offset = $this->EE->localize->zones[$this->EE->config->item('server_timezone')] * 3600;

					if ($this->EE->TMPL->fetch_param('start_day') === 'Monday')
					{
						$yearweek = "DATE_FORMAT(FROM_UNIXTIME(entry_date + {$loc_offset}), '%x%v') AS yearweek ";
						$dql = 'SELECT '.$yearweek.$sql;
					}
					else
					{
						$yearweek = "DATE_FORMAT(FROM_UNIXTIME(entry_date + {$loc_offset}), '%X%V') AS yearweek ";
						$dql = 'SELECT '.$yearweek.$sql;
					}

					// ------
					//	Add status declaration
					// ------

					if ($status = $this->EE->TMPL->fetch_param('status'))
					{
						$status = str_replace('Open',	'open',	$status);
						$status = str_replace('Closed', 'closed', $status);

						$sstr = $this->EE->functions->sql_andor_string($status, 't.status');

						if (stristr($sstr, "'closed'") === FALSE)
						{
							$sstr .= " AND t.status != 'closed' ";
						}

						$dql .= $sstr;
					}
					else
					{
						$dql .= "AND t.status = 'open' ";
					}

					$query = $this->EE->db->query($dql);

					$distinct = array();

					if ($query->num_rows() > 0)
					{
						/** ---------------------------------
						/*	 Sort Default is ASC for Display By Week so that entries are displayed
						/*	oldest to newest in the week, which is how you would expect.
						/*  ---------------------------------*/

						if ($this->EE->TMPL->fetch_param('sort') === FALSE)
						{
							$sort_array[0] = 'asc';
						}

						foreach ($query->result_array() as $row)
						{
							$distinct[] = $row['yearweek'];
						}

						$distinct = array_unique($distinct);
						rsort($distinct);

						/* Old code, did nothing
						*
						if ($this->EE->TMPL->fetch_param('week_sort') == 'desc')
						{
							$distinct = array_reverse($distinct);
						}
						*
						*/

						$this->total_rows = count($distinct);
						$cur = ($this->p_page == '') ? 0 : $this->p_page;

						/** ---------------------------------
						/*	 If no pagination, then the Current Week is shown by default with
						/*	 all pagination correctly set and ready to roll, if used.
						/*  ---------------------------------*/

						if ($this->EE->TMPL->fetch_param('show_current_week') === 'yes' && $this->p_page == '')
						{
							if ($this->EE->TMPL->fetch_param('start_day') === 'Monday')
							{
								$query = $this->EE->db->query("SELECT DATE_FORMAT(CURDATE(), '%x%v') AS thisWeek");
							}
							else
							{
								$query = $this->EE->db->query("SELECT DATE_FORMAT(CURDATE(), '%X%V') AS thisWeek");
							}

							foreach($distinct as $key => $week)
							{
								if ($week == $query->row('thisWeek') )
								{
									$cur = $key;
									$this->p_page = $key;
									break;
								}
							}
						}

						$distinct = array_slice($distinct, $cur, $lim);

						/** ---------------------------------
						/*	 Finally, we add the display by week SQL to the query
						/*  ---------------------------------*/

						if ($distinct != FALSE)
						{
							// A Rough Attempt to Get the Localized Offset Added On

							$offset = $this->EE->localize->set_localized_offset();
							$dst_on = (date("I", $this->EE->localize->now) === 1) ? TRUE : FALSE;

							$sql .= "AND (";

							foreach ($distinct as $val)
							{
								if ($dst_on === TRUE AND (substr($val, 4) < 13 OR substr($val, 4) >= 43))
								{
									$offset -= 3600;
								}
								elseif ($dst_on === FALSE AND (substr($val, 4) >= 13 AND substr($val, 4) < 43))
								{
									$offset += 3600;
								}

								$sql_offset = ($offset < 0) ? "- ".abs($offset) : "+ ".$offset;

								if ($this->EE->TMPL->fetch_param('start_day') === 'Monday')
								{
									$sql .= " DATE_FORMAT(FROM_UNIXTIME(entry_date {$sql_offset}), '%x%v') = '".$val."' OR";
								}
								else
								{
									$sql .= " DATE_FORMAT(FROM_UNIXTIME(entry_date {$sql_offset}), '%X%V') = '".$val."' OR";
								}
							}

							$sql = substr($sql, 0, -2).')';
						}
					}
				}
			}
		}


		// ------
		//	Limit query "URL title"
		// ------

		if ($qtitle != '' AND $dynamic)
		{
			$sql .= "AND t.url_title = '".$this->EE->db->escape_str($qtitle)."' ";

			// We use this with hit tracking....

			$this->hit_tracking_id = $qtitle;
		}


		// We set a
		if ($entry_id != '' AND $this->entry_id !== FALSE)
		{
			$this->hit_tracking_id = $entry_id;
		}

		// ------
		//	Limit query by category
		// ------

		if ($this->EE->TMPL->fetch_param('category'))
		{
			if (stristr($this->EE->TMPL->fetch_param('category'), '&'))
			{
				// --------------------------------------
				//	First, we find all entries with these categories
				// --------------------------------------

				$for_sql = (substr($this->EE->TMPL->fetch_param('category'), 0, 3) == 'not') ? trim(substr($this->EE->TMPL->fetch_param('category'), 3)) : $this->EE->TMPL->fetch_param('category');

				$csql = "SELECT exp_category_posts.entry_id, exp_category_posts.cat_id ".
						$sql.
						$this->EE->functions->sql_andor_string(str_replace('&', '|', $for_sql), 'exp_categories.cat_id');

				//exit($csql);

				$results = $this->EE->db->query($csql);

				if ($results->num_rows() == 0)
				{
					return;
				}

				$type = 'IN';
				$categories	 = explode('&', $this->EE->TMPL->fetch_param('category'));
				$entry_array = array();

				if (substr($categories[0], 0, 3) == 'not')
				{
					$type = 'NOT IN';

					$categories[0] = trim(substr($categories[0], 3));
				}

				foreach($results->result_array() as $row)
				{
					$entry_array[$row['cat_id']][] = $row['entry_id'];
				}

				if (count($entry_array) < 2 OR count(array_diff($categories, array_keys($entry_array))) > 0)
				{
					return;
				}

				$chosen = call_user_func_array('array_intersect', $entry_array);

				if (count($chosen) == 0)
				{
					return;
				}

				$sql .= "AND t.entry_id ".$type." ('".implode("','", $chosen)."') ";
			}
			else
			{
				if (substr($this->EE->TMPL->fetch_param('category'), 0, 3) == 'not' && $this->EE->TMPL->fetch_param('uncategorized_entries') !== 'n')
				{
					$sql .= $this->EE->functions->sql_andor_string($this->EE->TMPL->fetch_param('category'), 'exp_categories.cat_id', '', TRUE)." ";
				}
				else
				{
					$sql .= $this->EE->functions->sql_andor_string($this->EE->TMPL->fetch_param('category'), 'exp_categories.cat_id')." ";
				}
			}
		}

		if ($this->EE->TMPL->fetch_param('category_group'))
		{
			if (substr($this->EE->TMPL->fetch_param('category_group'), 0, 3) == 'not' && $this->EE->TMPL->fetch_param('uncategorized_entries') !== 'n')
			{
				$sql .= $this->EE->functions->sql_andor_string($this->EE->TMPL->fetch_param('category_group'), 'exp_categories.group_id', '', TRUE)." ";
			}
			else
			{
				$sql .= $this->EE->functions->sql_andor_string($this->EE->TMPL->fetch_param('category_group'), 'exp_categories.group_id')." ";
			}
		}

		if ($this->EE->TMPL->fetch_param('category') === FALSE && $this->EE->TMPL->fetch_param('category_group') === FALSE)
		{
			if ($cat_id != '' AND $dynamic)
			{
				$sql .= " AND exp_categories.cat_id = '".$this->EE->db->escape_str($cat_id)."' ";
			}
		}

		// ------
		//	Limit to (or exclude) specific users
		// ------

		if ($username = $this->EE->TMPL->fetch_param('username'))
		{
			// Shows entries ONLY for currently logged in user

			if ($username == 'CURRENT_USER')
			{
				$sql .=  "AND m.member_id = '".$this->EE->session->userdata('member_id')."' ";
			}
			elseif ($username == 'NOT_CURRENT_USER')
			{
				$sql .=  "AND m.member_id != '".$this->EE->session->userdata('member_id')."' ";
			}
			else
			{
				$sql .= $this->EE->functions->sql_andor_string($username, 'm.username');
			}
		}

		// ------
        //	Limit to (or exclude) specific author id(s)
        // ------

        if ($author_id = $this->EE->TMPL->fetch_param('author_id'))
		{
			// Shows entries ONLY for currently logged in user

			if ($author_id == 'CURRENT_USER')
			{
				$sql .=  "AND m.member_id = '".$this->EE->session->userdata('member_id')."' ";
			}
			elseif ($author_id == 'NOT_CURRENT_USER')
			{
				$sql .=  "AND m.member_id != '".$this->EE->session->userdata('member_id')."' ";
			}
			else
			{
				$sql .= $this->EE->functions->sql_andor_string($author_id, 'm.member_id');
			}
		}

		// ------
		//	Add status declaration
		// ------

		if ($status = $this->EE->TMPL->fetch_param('status'))
		{
			$status = str_replace('Open',	'open',	$status);
			$status = str_replace('Closed', 'closed', $status);

			$sstr = $this->EE->functions->sql_andor_string($status, 't.status');

			if (stristr($sstr, "'closed'") === FALSE)
			{
				$sstr .= " AND t.status != 'closed' ";
			}

			$sql .= $sstr;
		}
		else
		{
			$sql .= "AND t.status = 'open' ";
		}

		// ------
		//	Add Group ID clause
		// ------

		if ($group_id = $this->EE->TMPL->fetch_param('group_id'))
		{
			$sql .= $this->EE->functions->sql_andor_string($group_id, 'm.group_id');
		}

    	// ---------------------------------------
    	//	Field searching
    	// ---------------------------------------

		if ( ! empty($this->EE->TMPL->search_fields))
		{
			foreach ($this->EE->TMPL->search_fields as $field_name => $terms)
			{
				if (isset($this->cfields[$this->EE->config->item('site_id')][$field_name]))
				{
					if (strncmp($terms, '=', 1) ==  0)
					{
						// ---------------------------------------
						//	Exact Match e.g.: search:body="=pickle"
						// ---------------------------------------

						$terms = substr($terms, 1);

						// special handling for IS_EMPTY
						if (strpos($terms, 'IS_EMPTY') !== FALSE)
						{
							$terms = str_replace('IS_EMPTY', '', $terms);

							$add_search = $this->EE->functions->sql_andor_string($terms, 'wd.field_id_'.$this->cfields[$this->EE->config->item('site_id')][$field_name]);

							// remove the first AND output by $this->EE->functions->sql_andor_string() so we can parenthesize this clause
							$add_search = substr($add_search, 3);

							$conj = ($add_search != '' && strncmp($terms, 'not ', 4) != 0) ? 'OR' : 'AND';

							if (strncmp($terms, 'not ', 4) == 0)
							{
								$sql .= 'AND ('.$add_search.' '.$conj.' wd.field_id_'.$this->cfields[$this->EE->config->item('site_id')][$field_name].' != "") ';
							}
							else
							{
								$sql .= 'AND ('.$add_search.' '.$conj.' wd.field_id_'.$this->cfields[$this->EE->config->item('site_id')][$field_name].' = "") ';
							}
						}
						else
						{
							$sql .= $this->EE->functions->sql_andor_string($terms, 'wd.field_id_'.$this->cfields[$this->EE->config->item('site_id')][$field_name]).' ';
						}
					}
					else
					{
						// ---------------------------------------
						//	"Contains" e.g.: search:body="pickle"
						// ---------------------------------------

						if (strncmp($terms, 'not ', 4) == 0)
						{
							$terms = substr($terms, 4);
							$like = 'NOT LIKE';
						}
						else
						{
							$like = 'LIKE';
						}

						if (strpos($terms, '&&') !== FALSE)
						{
							$terms = explode('&&', $terms);
							$andor = (strncmp($like, 'NOT', 3) == 0) ? 'OR' : 'AND';
						}
						else
						{
							$terms = explode('|', $terms);
							$andor = (strncmp($like, 'NOT', 3) == 0) ? 'AND' : 'OR';
						}

						$sql .= ' AND (';

						foreach ($terms as $term)
						{
							if ($term == 'IS_EMPTY')
							{
								$sql .= ' wd.field_id_'.$this->cfields[$this->EE->config->item('site_id')][$field_name].' '.$like.' "" '.$andor;
							}
							elseif (strpos($term, '\W') !== FALSE) // full word only, no partial matches
							{
								$not = ($like == 'LIKE') ? ' ' : ' NOT ';

								// Note: MySQL's nutty POSIX regex word boundary is [[:>:]]
								$term = '([[:<:]]|^)'.preg_quote(str_replace('\W', '', $term)).'([[:>:]]|$)';

								$sql .= ' wd.field_id_'.$this->cfields[$this->EE->config->item('site_id')][$field_name].$not.'REGEXP "'.$this->EE->db->escape_str($term).'" '.$andor;
							}
							else
							{
								$sql .= ' wd.field_id_'.$this->cfields[$this->EE->config->item('site_id')][$field_name].' '.$like.' "%'.$this->EE->db->escape_like_str($term).'%" '.$andor;
							}
						}

						$sql = substr($sql, 0, -strlen($andor)).') ';
					}
				}
			}
		}

		// ----------
		//	Build sorting clause
		// ----------

		// We'll assign this to a different variable since we
		// need to use this in two places

		$end = 'ORDER BY ';

		if ($fixed_order !== FALSE && ! empty($fixed_order))
		{
			$end .= 'FIELD(t.entry_id, '.implode(',', $fixed_order).') ';
		}
		else
		{
			// Used to eliminate sort issues with duplicated fields below
			$entry_id_sort = $sort_array[0];

			if (FALSE === $order_array[0])
			{
				if ($sticky == 'no')
				{
					$end .= "t.entry_date";
				}
				else
				{
					$end .= "t.sticky desc, t.entry_date";
				}

				if ($sort_array[0] == 'asc' OR $sort_array[0] == 'desc')
				{
					$end .= " ".$sort_array[0];
				}
			}
			else
			{
				if ($sticky != 'no')
				{
					$end .= "t.sticky desc, ";
				}

				foreach($order_array as $key => $order)
				{
					if (in_array($order, array('view_count_one', 'view_count_two', 'view_count_three', 'view_count_four')))
					{
						$view_ct = substr($order, 10);
						$order	 = "view_count";
					}

					if ($key > 0) $end .= ", ";

					switch ($order)
					{
						case 'entry_id' :
							$end .= "t.entry_id";
						break;

						case 'date' :
							$end .= "t.entry_date";
						break;

						case 'edit_date' :
							$end .= "t.edit_date";
						break;

						case 'expiration_date' :
							$end .= "t.expiration_date";
						break;

						case 'title' :
							$end .= "t.title";
						break;

						case 'url_title' :
							$end .= "t.url_title";
						break;

						case 'view_count' :
							$vc = $order.$view_ct;

							$end .= " t.{$vc} ".$sort_array[$key];

							if (count($order_array)-1 == $key)
							{
								$end .= ", t.entry_date ".$sort_array[$key];
							}

							$sort_array[$key] = FALSE;
						break;

						case 'comment_total' :
							$end .= "t.comment_total ".$sort_array[$key];

							if (count($order_array)-1 == $key)
							{
								$end .= ", t.entry_date ".$sort_array[$key];
							}

							$sort_array[$key] = FALSE;
						break;

						case 'most_recent_comment' :
							$end .= "t.recent_comment_date ".$sort_array[$key];

							if (count($order_array)-1 == $key)
							{
								$end .= ", t.entry_date ".$sort_array[$key];
							}

							$sort_array[$key] = FALSE;
						break;

						case 'username' :
							$end .= "m.username";
						break;

						case 'screen_name' :
							$end .= "m.screen_name";
						break;

						case 'custom_field' :
							if (strpos($corder[$key], '|') !== FALSE)
							{
								$end .= "CONCAT(wd.field_id_".implode(", wd.field_id_", explode('|', $corder[$key])).")";
							}
							else
							{
								$end .= "wd.field_id_".$corder[$key];
							}
						break;

						case 'random' :
								$end = "ORDER BY rand()";
								$sort_array[$key] = FALSE;
						break;

						default		:
							$end .= "t.entry_date";
						break;
					}

					if ($sort_array[$key] == 'asc' OR $sort_array[$key] == 'desc')
					{
						// keep entries with the same timestamp in the correct order
						$end .= " {$sort_array[$key]}";
					}
				}
			}

			// In the event of a sorted field containing identical information as another
			// entry (title, entry_date, etc), they will sort on the order they were entered
			// into ExpressionEngine, with the first "sort" parameter taking precedence.
			// If no sort parameter is set, entries will descend by entry id.
			if ( ! in_array('entry_id', $order_array))
			{
				$end .= ", t.entry_id ".$entry_id_sort;
			}
		}

		//  Determine the row limits
		// Even thouth we don't use the LIMIT clause until the end,
		// we need it to help create our pagination links so we'll
		// set it here

	/*
		RATING: Remove all of this, as we don't do pagination here.
		
		if ($cat_id  != '' AND is_numeric($this->EE->TMPL->fetch_param('cat_limit')))
		{
			$this->p_limit = $this->EE->TMPL->fetch_param('cat_limit');
		}
		elseif ($month != '' AND is_numeric($this->EE->TMPL->fetch_param('month_limit')))
		{
			$this->p_limit = $this->EE->TMPL->fetch_param('month_limit');
		}
		else
		{
			$this->p_limit  = ( ! is_numeric($this->EE->TMPL->fetch_param('limit')))  ? $this->limit : $this->EE->TMPL->fetch_param('limit');
		}

		// --------------------------------------------
        //  Is there an Offset?
        // --------------------------------------------
        
		// We do this hear so we can use the offset into next, then later one as well
		$offset = ( ! $this->EE->TMPL->fetch_param('offset') OR ! is_numeric($this->EE->TMPL->fetch_param('offset'))) ? '0' : $this->EE->TMPL->fetch_param('offset');

		//  Do we need pagination?
		// We'll run the query to find out

		if ($this->paginate == TRUE)
		{
			if ($this->field_pagination == FALSE)
			{
				$this->pager_sql = $sql_a.$sql_b.$sql;
				$query = $this->EE->db->query($this->pager_sql);
				$total = $query->num_rows;
				$this->absolute_results = $total;

				// Adjust for offset
				if ($total >= $offset)
					$total = $total - $offset;

				$this->create_pagination($total);
			}
			else
			{
				$this->pager_sql = $sql_a.$sql_b.$sql;

				$query = $this->EE->db->query($this->pager_sql);

				$total = $query->num_rows;
				$this->absolute_results = $total;

				$this->create_pagination($total, $query);

				if ($this->EE->config->item('enable_sql_caching') == 'y')
				{
					$this->save_cache($this->pager_sql, 'pagination_query');
					$this->save_cache('1', 'field_pagination');
				}
			}

			if ($this->EE->config->item('enable_sql_caching') == 'y')
			{
				$this->save_cache($total, 'pagination_count');
			}
		}
*/
		// ------
		//	Add Limits to query
		// ------
		
		// RATING

		if ( ! empty($rating_sql['order_by']))
		{
			$sql .= $rating_sql['order_by'];
		}
		else
		{
			$sql .= $end;
		}
		
		if ($this->paginate == FALSE)
			$this->p_page = 0;

		// Adjust for offset
		$this->p_page += $offset;

		if ($this->display_by == '')
		{
			if (($page_marker == FALSE AND $this->p_limit != '') OR ($page_marker == TRUE AND $this->field_pagination != TRUE))
			{
				$sql .= ($this->p_page == '') ? " LIMIT ".$offset.', '.$this->p_limit : " LIMIT ".$this->p_page.', '.$this->p_limit;
			}
			elseif ($entry_id == '' AND $qtitle == '')
			{
				$sql .= ($this->p_page == '') ? " LIMIT ".$this->limit : " LIMIT ".$this->p_page.', '.$this->limit;
			}
		}
		else
		{
			if ($offset != 0)
			{
				$sql .= ($this->p_page == '') ? " LIMIT ".$offset.', '.$this->p_limit : " LIMIT ".$this->p_page.', '.$this->p_limit;
			}
		}

		// ------
		//	Fetch the entry_id numbers
		// ------
		
		// RATING
		return $sql_a.$sql_b.$sql;

		$query = $this->EE->db->query($sql_a.$sql_b.$sql);

		//exit($sql_a.$sql_b.$sql);

		if ($query->num_rows() == 0)
		{
			$this->sql = '';
			return;
		}
		
		// ------
		//	Build the full SQL query
		// ------

		$this->sql = "SELECT ";

		if ($this->EE->TMPL->fetch_param('category') OR $this->EE->TMPL->fetch_param('category_group') OR $cat_id != '')
		{
			// Using DISTINCT like this is bogus but since
			// FULL OUTER JOINs are not supported in older versions
			// of MySQL it's our only choice

			$this->sql .= " DISTINCT(t.entry_id), ";
		}

		if ($this->display_by == 'week' && isset($yearweek))
		{
			$this->sql .= $yearweek.', ';
		}

		// DO NOT CHANGE THE ORDER
		// The exp_member_data table needs to be called before the exp_members table.

		$this->sql .= " t.entry_id, t.{$this->sc->db->channel_id}, t.forum_topic_id, t.author_id, t.ip_address, t.title, t.url_title, t.status, t.dst_enabled, t.view_count_one, t.view_count_two, t.view_count_three, t.view_count_four, t.allow_comments, t.comment_expiration_date, t.sticky, t.entry_date, t.year, t.month, t.day, t.edit_date, t.expiration_date, t.recent_comment_date, t.comment_total, t.site_id as entry_site_id,
						w.channel_title, w.channel_name, w.channel_url, w.comment_url, w.comment_moderate, w.channel_html_formatting, w.channel_allow_img_urls, w.channel_auto_link_urls, w.comment_system_enabled, 
						m.username, m.email, m.url, m.screen_name, m.location, m.occupation, m.interests, m.aol_im, m.yahoo_im, m.msn_im, m.icq, m.signature, m.sig_img_filename, m.sig_img_width, m.sig_img_height, m.avatar_filename, m.avatar_width, m.avatar_height, m.photo_filename, m.photo_width, m.photo_height, m.group_id, m.member_id, m.bday_d, m.bday_m, m.bday_y, m.bio,
						md.*,
						wd.*
				FROM {$this->sc->db->channel_titles}		AS t
				LEFT JOIN {$this->sc->db->channels} 		AS w  ON t.{$this->sc->db->channel_id} = w.{$this->sc->db->channel_id}
				LEFT JOIN {$this->sc->db->channel_data}	AS wd ON t.entry_id = wd.entry_id
				LEFT JOIN exp_members		AS m  ON m.member_id = t.author_id
				LEFT JOIN exp_member_data	AS md ON md.member_id = m.member_id ";

		$this->sql .= "WHERE t.entry_id IN (";

		$entries = array();

		// Build ID numbers (checking for duplicates)

		foreach ($query->result_array() as $row)
		{
			if ( ! isset($entries[$row['entry_id']]))
			{
				$entries[$row['entry_id']] = 'y';
			}
			else
			{
				continue;
			}

			$this->sql .= $row['entry_id'].',';
		}

		//cache the entry_id
		$this->EE->session->cache['channel']['entry_ids']	= array_keys($entries);
		
		unset($query);
		unset($entries);

		$this->sql = substr($this->sql, 0, -1).') ';

		// modify the ORDER BY if displaying by week
		if ($this->display_by == 'week' && isset($yearweek))
		{
			$weeksort = ($this->EE->TMPL->fetch_param('week_sort') == 'desc') ? 'DESC' : 'ASC';
			$end = str_replace('ORDER BY ', 'ORDER BY yearweek '.$weeksort.', ', $end);
		}

		$this->sql .= $end;
	}

	// ------------------------------------------------------------------------

	/**
	  *  Fetch Disable Parameter
	  */
	function _fetch_disable_param()
	{
		$this->enable = array(
							'categories' 		=> TRUE,
							'category_fields'	=> TRUE,
							'custom_fields'		=> TRUE,
							'member_data'		=> TRUE,
							'pagination' 		=> TRUE,
							);

		if ($disable = $this->EE->TMPL->fetch_param('disable'))
		{
			if (strpos($disable, '|') !== FALSE)
			{
				foreach (explode("|", $disable) as $val)
				{
					if (isset($this->enable[$val]))
					{
						$this->enable[$val] = FALSE;
					}
				}
			}
			elseif (isset($this->enable[$disable]))
			{
				$this->enable[$disable] = FALSE;
			}
		}
	}
}
// END CLASS