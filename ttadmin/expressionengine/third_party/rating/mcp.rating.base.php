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
 * Rating - Control Panel
 *
 * The Control Panel master class that handles all of the CP Requests and Displaying
 *
 * @package 	Solspace:Rating
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/rating/mcp.rating.php
 */

require_once 'addon_builder/module_builder.php';

class Rating_cp_base extends Module_builder_rating 
{
	private	$field_limit		= 25;
	private $row_limit			= 50;
	private $locked_fields		= array('review', 'rating');
	private $locked_templates	= array('default_template');
	
	private $csv_separator		= ","; // Alternative: "\t"

    // --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	bool		Enable calling of methods based on URI string
	 * @return	string
	 */
    
	public function __construct( $switch = TRUE )
    {
        parent::Module_builder_rating('rating');
        
         if ((bool) $switch === FALSE) return; // Install or Uninstall Request
        
		// --------------------------------------------
        //  Module Menu Items
        // --------------------------------------------
        
        $menu = array(
						'module_home'	=> array(	
													'link'  => $this->base,
													'title' => lang('rated_entries')),
						'module_ratings' => array(	
													'link'  => $this->base . AMP . 'method=view_ratings',
													'title' => lang('ratings')),
						'module_fields' => array(	
													'link'  => $this->base . AMP . 'method=fields',
													'title' => lang('fields')),
						'module_templates' => array(	
													'link'  => $this->base . AMP . 'method=templates',
													'title' => lang('notification_templates')),
						'module_preferences' => array(	
													'link'  => $this->base . AMP . 'method=preferences',
													'title' => lang('rating_preferences')),
						'module_utilities' => array(	
													'link'  => $this->base . AMP . 'method=utilities',
													'title' => lang('utilities')),
						'module_documentation'	=> array(	
														'link'  => RATING_DOCS_URL,
														'new_window' => TRUE,
														'title' => lang('online_documentation') . 
															((APP_VER < 2.0) ? ' (' . RATING_VERSION . ')' : '')
						),
        );
        
		$this->cached_vars['lang_module_version'] 	= lang('rating_module_version');        
		$this->cached_vars['module_menu'] 			= $menu;
        
		//needed for header.html file views
		$this->cached_vars['js_magic_checkboxes']	= $this->js_magic_checkboxes();

		// -------------------------------------
		//  Module Installed and What Version?
		// -------------------------------------
			
		if ($this->database_version() == FALSE)
		{
			return;
		}
		elseif($this->version_compare($this->database_version(), '<', RATING_VERSION) 
			  OR ! $this->extensions_enabled())
		{
			if (APP_VER < 2.0)
			{
				if ($this->rating_module_update() === FALSE)
				{
					return;
				}
			}
			else
			{
				// For EE 2.x, we need to redirect the request to Update Routine
				$_GET['method'] = 'rating_module_update';
			}
		}
        
        // -------------------------------------
		//  Request and View Builder
		// -------------------------------------
        
        if (APP_VER < 2.0 && $switch !== FALSE)
        {	
        	if (ee()->input->get('method') === FALSE)
        	{
        		$this->index();
        	}
        	elseif( ! method_exists($this, ee()->input->get('method')))
        	{
        		$this->add_crumb(lang('invalid_request'));
        		$this->cached_vars['error_message'] = lang('invalid_request');
        		
        		return $this->ee_cp_view('error_page.html');
        	}
        	else
        	{
        		$this->{ee()->input->get('method')}();
        	}
        }
    }
    // END Rating_cp_base()
	
	// --------------------------------------------------------------------

	/**
	 * Module's Main Index
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */
    
	public function index($message='')
    {
        if (isset($_GET['msg']))
        {
        	$message = lang($_GET['msg']);
        }
        
        $this->cached_vars['message'] = $message;
        
		return $this->home();
	}
	// END home()


	// --------------------------------------------------------------------

	/**
	 * Module's Homepage, Listing Current Ratings
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */
	
    public function home()
    {
		//--------------------------------------------  
		//	Crumbs and tab highlight
		//--------------------------------------------
		
		$this->add_crumb( lang('rated_entries') );
		$this->cached_vars['module_menu_highlight']	= 'module_home';
		
		//$this->add_right_link(lang('view_all_ratings'), $this->base . AMP . 'method=view_ratings');
		
		if (APP_VER >= 2.0)
		{
			ee()->cp->add_js_script(array('ui' => 'datepicker'));
		}

		// --------------------------------------------
        //	The Groupings by collection="" value
        // --------------------------------------------
        
        $query = ee()->db->query("SELECT DISTINCT collection FROM exp_ratings WHERE collection != ''");
        
        $this->cached_vars['collections'] = array();
        
        foreach($query->result_array() as $row)
        {
        	$this->cached_vars['collections'][] = $row['collection'];
        }
        
        // --------------------------------------------
        //	Find All Rated Weblogs/Channels
        // --------------------------------------------
        
        $query = ee()->db->query("SELECT {$this->sc->db->channel_id} AS `id`, {$this->sc->db->channel_title} as `title`,
        								 exp_sites.site_label, exp_sites.site_id
        						  FROM {$this->sc->db->channels}, exp_sites
        						  WHERE {$this->sc->db->channel_id} IN (SELECT DISTINCT channel_id FROM exp_ratings)
        						  AND exp_sites.site_id = {$this->sc->db->channels}.site_id");
        
        $this->cached_vars['rated_channels'] = array();
        $this->cached_vars['rated_sites']	 = array();
        
        foreach($query->result_array() as $row)
        {
        	$this->cached_vars['rated_sites'][$row['site_id']] = $row['site_label'];
        	$this->cached_vars['rated_channels'][$row['site_id']][$row['id']] = $row['title'];
        }
        
        // --------------------------------------------
        //	Find All Rated Statuses
        // --------------------------------------------
        
        $this->cached_vars['rated_statuses'] = array('open' => lang('open'), 'closed' => lang('closed'));

		// --------------------------------------------
        //	Empty Form Names?
        // --------------------------------------------
        
        $query = ee()->db->query("SELECT COUNT(*) AS count FROM exp_ratings WHERE collection = ''");
        
        $this->cached_vars['empty_collections'] = ($query->row('count') > 1) ? 'y' : 'n';

		// --------------------------------------------
        //	List of Rated Entries - Separated for Future AJAX usage
        // --------------------------------------------
		
		$this->cached_vars['entry_stats']	= array();
		$this->cached_vars['rated_entries'] = $this->rated_entries();
		
		// --------------------------------------------
        //  Load page
        // --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('home.html', NULL, TRUE);        
		return $this->ee_cp_view('index.html');		
	}
	/* END home() */
	
	
	// --------------------------------------------------------------------

	/**
	 *	Rated Entries List
	 *
	 *	A list of Rated Entries Based off POST values
	 *
	 *	@access		public
	 *	@return		string
	 */
	
	public function rated_entries()
	{
		$page_url = $this->base;
	
		$defaults = array('channel_id'		=> array('all'),  // select list
						  'rating_status'	=> 'all',		  // select list open/closed
						  'collection'		=> 'all',
						  'date_range'		=> 'all',		  // today, this week, this month, last month, this year, choose date range
						  'start_date'		=> ee()->localize->now - (365 * 24 * 60 * 60),
						  'end_date'		=> ee()->localize->now,
						  'keywords'		=> '',
						  'keyword_search'	=> 'title', // title, title and content
						  );
						
		$params = array();
			
		// --------------------------------------------
        //	Any POST or GET variables to override our defaults?
        // --------------------------------------------
		
		foreach($defaults as $name => $value)
		{
			if ( ! empty($_POST[$name]) && gettype($_POST[$name]) == gettype($value))
			{
				$this->cached_vars['selected'][$name] = $params[$name] = $_POST[$name];
			}
			elseif ( ! empty($_GET[$name]))
			{
				if ( is_array($value) && strpos($_GET[$name], '|') !== FALSE)
				{
					$params[$name] = explode('|', $_GET[$name]);
				}
				else
				{
					$params[$name] = $_GET[$name];
				}
				
				$this->cached_vars['selected'][$name] = $params[$name];
			}
			elseif( $name == 'start_date' OR $name == 'end_date' )
			{
				$params[$name] = $value; // Default search value
				$this->cached_vars['selected'][$name] = $value;
			}
			else
			{
				$params[$name] = $value; // Default search value
				
				$this->cached_vars['selected'][$name] = (gettype($value) == 'array') ? array() : ''; // No default search, merely empty array or string
			}
			
			// --------------------------------------------
			//	Pagination for the ::yawn::
			// --------------------------------------------
			
			if ( is_array($params[$name]))
			{
				$page_url .= '&='.$name.'='.implode('|', $params[$name]);
			}
			else
			{
				$page_url .= '&='.$name.'='.$params[$name];
			}
		}
				
		// --------------------------------------------
        //	Scotty, I Need Full Power to the Ratings SQL Inducer!
        // --------------------------------------------
        
        $rsql = "SELECT entry_id FROM exp_ratings
				 WHERE rating_id != 0 ";
		
		// --------------------------------------------
        //	Status of Ratings
        // --------------------------------------------
		
		if ( in_array($params['rating_status'], array('open', 'closed')))
		{
			$rsql .= " AND status = '".ee()->db->escape_str($params['rating_status'])."'";
		}
		
		// --------------------------------------------
        //	Channels
        // --------------------------------------------
		
		if ( ! in_array('all', $params['channel_id']))
		{
			foreach($params['channel_id'] as $key => $value)
			{
				if (empty($value))
				{
					unset($params['channel_id'][$key]);
					continue;
				}
				
				$params['channel_id'][$key] = (integer) $value;
			}
			
			if ( ! empty($params['channel_id']))
			{
				$rsql .= " AND channel_id IN (".implode(',', $params['channel_id']).")";
			}
		}
		
		// --------------------------------------------
        //	Uhuru, Open Form Name Frequencies
        // --------------------------------------------
        
		if ( 'all' != $params['collection'])
		{
			if ('empty' == $params['collection'])
			{
				$rsql .= " AND collection = ''";
			}
			else
			{
				$rsql .= " AND collection = '".ee()->db->escape_str($params['collection'])."'";
			}
		}
		
		// --------------------------------------------
        //	Mr. Chekov, Charge the Channel Title Weapons Array!
        // --------------------------------------------
        
        $sql = "SELECT DISTINCT `{$this->sc->db->channel_titles}`.title, 
        						`{$this->sc->db->channel_titles}`.entry_id, 
        						`{$this->sc->db->channel_titles}`.{$this->sc->db->channel_id},
        						rs.*";
        
        if (ee()->input->post('keywords') !== FALSE &&
        	trim(ee()->input->post('keywords')) != '' &&
        	ee()->input->post('keyword_search') == 'title_and_content')
        {
        	$sql .= " FROM `{$this->sc->db->channel_titles}`, `{$this->sc->db->channel_data}`, `exp_rating_stats` AS rs 
				  	  WHERE rs.entry_id = `{$this->sc->db->channel_titles}`.entry_id
				  	  AND `{$this->sc->db->channel_data}`.entry_id = `{$this->sc->db->channel_titles}`.entry_id";
        }
        else
        {
			$sql .= " FROM `{$this->sc->db->channel_titles}`, `exp_rating_stats` AS rs 
				  	  WHERE rs.entry_id = `{$this->sc->db->channel_titles}`.entry_id ";
		}
		
		$sql .= " AND `{$this->sc->db->channel_titles}`.entry_id IN
				 (
				 	{$rsql}
				 )";
				 
		if ( 'all' == $params['collection'] OR 'empty' == $params['collection'])
		{
			$sql .= " AND collection = 'all'";	
		}
		else
		{
			$sql .= " AND collection = '".ee()->db->escape_str($params['collection'])."'";
		}
		
		// --------------------------------------------
        //	Search
        // --------------------------------------------
        
        $sql .= $this->entries_keywords_search(ee()->input->post('keywords'), $params['keyword_search'], $params['channel_id'], 'any');
		
		// --------------------------------------------
        //	Date Range - // today, this week, this month, last month, this year, choose date range
        // --------------------------------------------
        
   		if ( ! empty($params['date_range']))
		{
			if (ctype_digit($params['date_range']))
			{
				$since = time() - ($params['date_range'] * 60 * 60 * 24);
			}
			elseif ($params['date_range'] == 'date_range' && (ee()->input->post('start_date') !== FALSE OR ee()->input->post('end_date') !== FALSE))
			{
				if (ee()->input->post('start_date') !== FALSE)
				{
					$this->cached_vars['selected']['start_date'] = $start = strtotime(ee()->input->post('start_date') . ' 00:00:00');
					
					if (ctype_digit($start))
					{
						$sql		.= " AND {$this->sc->db->channel_titles}.entry_date >= '".$start."' ";
						$page_url	.= '&start_date='.ee()->input->post('start_date');
					}
				}
				
				if (ee()->input->post('end_date') !== FALSE)
				{
					$this->cached_vars['selected']['end_date'] = $end = strtotime(ee()->input->post('end_date') . ' 23:59:59');
					
					if (ctype_digit($end))
					{
						$sql		.= " AND {$this->sc->db->channel_titles}.entry_date <= '".$end."' ";
						$page_url	.= '&end_date='.ee()->input->post('end_date');
					}
				}
			}
			else
			{
				switch($params['date_range'])
				{
					case 'today'		: $since = mktime(0,0,0, date('m'), date('d'), date('Y'));
					break;
	
					case 'this_week'	: $since = mktime(0,0,0, date('m'), date('d') - ((date('N') == 7) ? 0 : date('N')), date('Y'));
					break;
	
					case 'this_month'	: $since = mktime(0,0,0, date('m'), 1, date('Y'));
					break;
					
					case 'last_month'	: $since = mktime(0,0,0, (date('m') - 1), 1, date('Y'));
										  $before = mktime(0,0,0, date('m'), 1, date('Y'));
					break;
					
					case 'this_year'	: $since = mktime(0,0,0, 1, 1, date('Y'));
					break;					
				}
			}
			
			if ( isset($since))
			{
				$this->cached_vars['selected']['start_date'] = $since;
				$sql .= " AND {$this->sc->db->channel_titles}.entry_date > $since";
			}
			
			if ( isset($before))
			{
				$this->cached_vars['selected']['end_date'] = $before;
				$sql .= " AND {$this->sc->db->channel_titles}.entry_date < $before";
			}
		}
		
        // --------------------------------------------
        //	Order by Entry Date - Hardcoded.
        // --------------------------------------------
				
		$sql .= " ORDER BY last_rating_date DESC";
				
		// --------------------------------------------
        //	Spock, Give me a sense of the Theoretical Limit
        // --------------------------------------------
        
        $query = ee()->db->query(preg_replace("/SELECT(.*?)\s+FROM\s+/is",
        									  'SELECT COUNT(*) AS count FROM ',
        									  $sql,
        									  1));
        
        $this->cached_vars['paginate'] = '';
        
		if ( $query->row('count') > $this->row_limit )
		{
			$pagination_data = $this->universal_pagination(
				array(
						'sql'					=> $sql, 
						'total_results'			=> $query->row('count'), 
						'limit'					=> $this->row_limit,
						'current_page'			=> ( ! ee()->input->get_post('row')) ? 0 : ee()->input->get_post('row'),
						'pagination_config'		=> array('base_url' => $page_url),
						'query_string_segment'	=> 'row'
			));

			$sql		= $pagination_data['sql'];
			$this->cached_vars['paginate'] = $pagination_data['pagination_links'];
		}
        
        // --------------------------------------------
        //	Sulu! Navigate us Out of Here!
        // --------------------------------------------
        
        //var_dump($sql);
        
        return ee()->db->query($sql)->result_array();
			
	}
	/* END rated_entries() */
	
	
	// --------------------------------------------------------------------

	/**
	 *	Keywords Search
	 *
	 *	Abstracted to Keep my Flow Flowing
	 *
	 *	@access		public
	 *	@param		string		// string of keywords
	 *	@param		string		// title or title_and_content
	 *	@param		array		// array of channel ids
	 *	@param		string		// any, all, exact
	 *	@return		string
	 */
	 
	public function entries_keywords_search($keywords, $keyword_search = '', $channel_ids = array(), $search_type = 'any')
	{
		$sql = '';
		$custom_fields  = '';
		
		if (empty($keywords)) return $sql;
	
		// ----------------------------------------------
        //	Keywords Parsing
        // ----------------------------------------------
        
        //var_dump($this->keywords_parsing($keywords));
        
        extract($this->keywords_parsing($keywords));
		
		// --------------------------------------------
		//	Custom Field Search Phrase
		// --------------------------------------------
		
		if ($keyword_search == 'title_and_content')
		{
			$custom_fields = array();
		
			$ssql = "SELECT field_id FROM {$this->sc->db->channel_fields}
					 WHERE field_search = 'y'
					 AND group_id IN
					 (
						SELECT DISTINCT field_group FROM {$this->sc->db->channels}";
	
			if ( ! empty($channel_ids) && ! in_array('all', $channel_ids))
			{
				$ssql .= " WHERE {$this->sc->db->channel_id} IN ('".implode("','", ee()->db->escape_str($channel_ids))."')";
			}
			
			$ssql .= ")";
			
			$query = ee()->db->query($ssql);
	
			foreach ($query->result_array() as $row)
			{
				// Insure that we only have integers
				$custom_fields[] = ceil($row['field_id']);
			}
			
			if (sizeof($custom_fields) > 0)
			{
				// force case insensitivity, but only on 4.0.2 or higher
				if (version_compare(mysql_get_server_info(), '4.0.2', '>=') !== FALSE)
				{
					$custom_fields = "CAST(CONCAT_WS(' ', `{$this->sc->db->channel_data}`.field_id_".implode(", `{$this->sc->db->channel_data}`.field_id_", $custom_fields).') AS CHAR)';						
				}
				else
				{
					$custom_fields = "CONCAT_WS(' ', `{$this->sc->db->channel_data}`.field_id_".implode(", `{$this->sc->db->channel_data}`.field_id_", $custom_fields).')';
				}
			}
		}
		else
		{
			$custom_fields = '';
		}
		
		// --------------------------------------------
		//	Check for Special Searches First - Legacy from EE
		// --------------------------------------------
		
		if (substr(strtolower($keywords_exact_phrase), 0, 3) == 'ip:' OR substr(strtolower($keywords_exact_phrase), 0, 4) == 'mid:')
		{
			trigger_error("IP and Member ID Searches for Comments are not Enabled in Rating");
		}
		else
		{
			switch($search_type)
			{
				case 'exact' :
					$sql .= $this->exact_phrase_search($keywords_exact_phrase, $non_keyword_terms, $custom_fields);
				break;
				case 'any' :
					$sql .= $this->any_word_search($keyword_terms, $non_keyword_terms, $custom_fields);
				break;
				case 'all' :
					$sql .= $this->all_words_search($keyword_terms, $non_keyword_terms, $custom_fields);
				break;
			}
		}
		
		return $sql;
	}
	/* END entries_keywords_search() */
	
	
	// --------------------------------------------------------------------

	/**
	 * Search for the Exact Keyword Phrase
	 *
	 *
	 * @access	public
	 * @param	string
	 * @param	array
	 * @param	string
	 * @return	string
	 */
    
	function exact_phrase_search($keyword_phrase, $non_keyword_terms, $custom_fields)
    {
    	$keyword_phrase = trim($keyword_phrase);
    	
    	$sql = '';
    	
    	// --------------------------------------------
        //	The Anti-Keywords
        // --------------------------------------------
    	
    	if (sizeof($non_keyword_terms) > 0)
		{
			$sql .= " AND (\n";
			
			foreach($non_keyword_terms as $count => $non_keywords)
			{
				if ($non_keywords == '') continue;
				
				if ($count != 0)
				{
					$sql .= "AND \n";
				}
			
				$sql .= " {$this->sc->db->channel_titles}.title != '".ee()->db->escape_str($non_keywords)."' 
						  AND
						  {$this->sc->db->channel_titles}.title NOT LIKE '".ee()->db->escape_str($non_keywords)." %'
						  AND
						  {$this->sc->db->channel_titles}.title NOT LIKE '% ".ee()->db->escape_str($non_keywords)."'
						  AND
						  {$this->sc->db->channel_titles}.title NOT LIKE '% ".ee()->db->escape_str($non_keywords)." %'\n";
			
				if ( ! empty($custom_fields) )
				{
					$sql .= " AND
							  ".$custom_fields." != '".ee()->db->escape_str($non_keywords)."' 
							  AND
							  ".$custom_fields." NOT LIKE '".ee()->db->escape_str($non_keywords)." %'
							  AND
							  ".$custom_fields." NOT LIKE '% ".ee()->db->escape_str($non_keywords)."'
							  AND
							  ".$custom_fields." NOT LIKE '% ".ee()->db->escape_str($non_keywords)." %'\n";
				}
			}
			
			$sql .= ')';
		}
		
		// --------------------------------------------
        //	Keywords
        // --------------------------------------------
		
		if($keyword_phrase != '')
		{
			$sql .= " AND (\n";
			
			$sql .= "{$this->sc->db->channel_titles}.title LIKE '%".ee()->db->escape_str($keyword_phrase)."%'\n";
		
			if ( ! empty($custom_fields) )
			{
				$sql .= "OR
						 ".$custom_fields." LIKE '%".ee()->db->escape_str($keyword_phrase)."%'\n";
			}
			
			$sql .= ")\n";
		}
		
		return $sql;
    }
    /* END */
    
    
	// --------------------------------------------------------------------

	/**
	 * Search for Any of the Keyword Terms
	 *
	 *
	 * @access	public
	 * @param	array
	 * @param	array
	 * @param	string
	 * @return	string
	 */
    
	function any_word_search($keyword_terms, $non_keyword_terms, $custom_fields)
    {
    	$sql = '';
    	
    	// --------------------------------------------
        //	The Anti-Keywords
        // --------------------------------------------
    	
    	if (sizeof($non_keyword_terms) > 0)
		{
			$sql .= " AND (\n";
			
			foreach($non_keyword_terms as $count => $non_keywords)
			{
				if ($non_keywords == '') continue;
				
				if ($count != 0)
				{
					$sql .= "AND \n";
				}
			
				$sql .= " {$this->sc->db->channel_titles}.title != '".ee()->db->escape_str($non_keywords)."' 
						  AND
						  {$this->sc->db->channel_titles}.title NOT LIKE '".ee()->db->escape_str($non_keywords)." %'
						  AND
						  {$this->sc->db->channel_titles}.title NOT LIKE '% ".ee()->db->escape_str($non_keywords)."'
						  AND
						  {$this->sc->db->channel_titles}.title NOT LIKE '% ".ee()->db->escape_str($non_keywords)." %'\n";
			
				if ( ! empty($custom_fields) )
				{
					$sql .= " AND
							  ".$custom_fields." != '".ee()->db->escape_str($non_keywords)."' 
							  AND
							  ".$custom_fields." NOT LIKE '".ee()->db->escape_str($non_keywords)." %'
							  AND
							  ".$custom_fields." NOT LIKE '% ".ee()->db->escape_str($non_keywords)."'
							  AND
							  ".$custom_fields." NOT LIKE '% ".ee()->db->escape_str($non_keywords)." %'\n";
				}
			}
			
			$sql .= ')';
		}
		
		// --------------------------------------------
        //	Keywords
        // --------------------------------------------
		
		if(sizeof($keyword_terms) > 0)
		{
			$sql .= " AND (\n";
			
			foreach($keyword_terms as $count => $keywords)
			{
				if ($keywords == '') continue;
				
				if ($count != 0)
				{
					$sql .= "OR ";
				}
			
				$sql .= "{$this->sc->db->channel_titles}.title LIKE '%".ee()->db->escape_str($keywords)."%'\n";
			
				if ($custom_fields != '')
				{
					$sql .= "OR
							 ".$custom_fields." LIKE '%".ee()->db->escape_str($keywords)."%'\n";
				}
			}
			
			$sql .= ")\n";
		}
		
		return $sql;
    }
    /* END */
    
    
	// --------------------------------------------------------------------

	/**
	 * Search for All the Keyword Terms
	 *
	 *
	 * @access	public
	 * @param	array
	 * @param	array
	 * @param	string
	 * @return	string
	 */
    
	function all_words_search($keyword_terms, $non_keyword_terms, $custom_fields)
    {
    	$sql = '';
    	
    	// --------------------------------------------
        //	The Anti-Keywords
        // --------------------------------------------
    	
    	if (sizeof($non_keyword_terms) > 0)
		{
			$sql .= " AND (\n";
			
			foreach($non_keyword_terms as $count => $non_keywords)
			{
				if ($non_keywords == '') continue;
			
				if ($count != 0)
				{
					$sql .= "AND \n";
				}
			
				$sql .= " {$this->sc->db->channel_titles}.title != '".ee()->db->escape_str($non_keywords)."' 
						  AND
						  {$this->sc->db->channel_titles}.title NOT LIKE '".ee()->db->escape_str($non_keywords)." %'
						  AND
						  {$this->sc->db->channel_titles}.title NOT LIKE '% ".ee()->db->escape_str($non_keywords)."'
						  AND
						  {$this->sc->db->channel_titles}.title NOT LIKE '% ".ee()->db->escape_str($non_keywords)." %'\n";
			
				if ( ! empty($custom_fields) )
				{
					$sql .= " AND
							  ".$custom_fields." != '".ee()->db->escape_str($non_keywords)."' 
							  AND
							  ".$custom_fields." NOT LIKE '".ee()->db->escape_str($non_keywords)." %'
							  AND
							  ".$custom_fields." NOT LIKE '% ".ee()->db->escape_str($non_keywords)."'
							  AND
							  ".$custom_fields." NOT LIKE '% ".ee()->db->escape_str($non_keywords)." %'\n";
				}
			}
			
			$sql .= ')';
		}
		
		// --------------------------------------------
        //	Keywords
        // --------------------------------------------
		
		if(sizeof($keyword_terms) > 0)
		{
			$sql .= " AND (\n";
			
			foreach($keyword_terms as $count => $keywords)
			{
				if ($keywords == '') continue;
			
				if ($count != 0)
				{
					$sql .= "AND ";
				}
			
				$sql .= "({$this->sc->db->channel_titles}.title = '".ee()->db->escape_str($keywords)."' 
						 OR
						 {$this->sc->db->channel_titles}.title LIKE '".ee()->db->escape_str($keywords)." %'
						 OR
						 {$this->sc->db->channel_titles}.title LIKE '% ".ee()->db->escape_str($keywords)."' 
						 OR
						 {$this->sc->db->channel_titles}.title LIKE '% ".ee()->db->escape_str($keywords)." %'\n";
			
				if ( ! empty($custom_fields) )
				{	
					$sql .= "OR
							 ".$custom_fields." = '".ee()->db->escape_str($keywords)."' 
							 OR
							 ".$custom_fields." LIKE '".ee()->db->escape_str($keywords)." %'
							 OR
							 ".$custom_fields." LIKE '% ".ee()->db->escape_str($keywords)."' 
							 OR
							 ".$custom_fields." LIKE '% ".ee()->db->escape_str($keywords)." %'\n";
				}
				
				$sql .= ")\n";
			}
			
			$sql .= ")\n";
		}
		
		return $sql;
    }
    /* END */
    
    
	/** ----------------------------------------
	/**  Clean Keywords Prior to Searching
	/**  - The EE one in the REGX object was not very pleasing to me...
	/** ----------------------------------------*/
	
	function keyword_clean($str, $remove = array())
	{
		// Remove all whitespace except single space
		$str = preg_replace("/(\r\n|\r|\n|\t|\s)+/", ' ', $str);
	
		// Characters that we do not want to allow...ever.
		// In the EE cleaner, we lost too many characters that might be useful in a Custom Field search, especially with Exact Keyword searches
		// The trick, security-wise, is to make sure any keywords output is converted to entities prior to any possible output
	
		$chars = array(
						'{'		,
						'}' 	,
						"^"	,
						"~"	,
						"*"	,
						"|"	,
						"["	,
						"]"	,
						'?'.'>'	,
						'<'.'?' ,  // Keep this one on the end
					  );
		
		// Keep as a space, helps prevent string removal security holes
		$str = str_replace(array_merge($chars, $remove), ' ', $str);
		
		// Only a single single space for spaces
		$str = preg_replace("/\s+/", ' ', $str);

		// Kill naughty stuff, trim, and return
		return trim(ee()->security->xss_clean($str));
	}
	/* END keyword_clean() */
	
	// --------------------------------------------------------------------

	/**
	 * View Ratings
	 *
	 * View all of the Ratings for an Weblog/Channel entry with a small search form to help
	 * narrow down the results
	 *
	 * @access	public
	 * @return	string
	 */
	
    public function view_ratings($message='')
    {
        if (isset($_GET['msg']))
        {
        	$message = lang($_GET['msg']);
        }
        
        $this->cached_vars['message'] = $message;
        
        if (APP_VER >= 2.0)
		{
			ee()->cp->add_js_script(array('ui' => 'datepicker'));
		}
        
        // --------------------------------------------
        //	In the Beginning, there were breadcrumbs based off the existence of an entry_id
        // --------------------------------------------
        
    	$this->cached_vars['entry_id'] = '';
    	$this->cached_vars['entry_title'] = '';
    
    	if (ee()->input->get_post('entry_id') !== FALSE)
    	{
    		$query = ee()->db->query("SELECT entry_id, title FROM {$this->sc->db->channel_titles}
    								  WHERE entry_id = '".ee()->db->escape_str(ee()->input->get_post('entry_id'))."'
    								  LIMIT 1");
    							  
    		if ($query->num_rows() == 0) return;
    		
    		$this->cached_vars['entry_id']		= $query->row('entry_id');
    		$this->cached_vars['entry_title']	= $query->row('title');
    		
    		$this->add_crumb( lang('ratings_for_entry') );
    	}
    	else
    	{
    		$this->add_crumb( lang('all_ratings') );
    	}
    	
    	$this->cached_vars['module_menu_highlight']	= 'module_ratings';
    	
    	// --------------------------------------------
        //	List of Rated Entries - Separated for Future AJAX usage
        // --------------------------------------------
        
        if ( isset($_POST['export']))
        {
        	$this->row_limit = 9999;
        }
		
		$this->cached_vars['ratings'] = $this->get_ratings($this->cached_vars['entry_id']);
		
		if ( isset($_POST['export']))
		{
			return $this->export_ratings($this->cached_vars['ratings']);
		}
		
		$entry_ids = array();
			
		foreach($this->cached_vars['ratings'] as $row)
		{
			$entry_ids[] = $row['entry_id'];
		}

		// --------------------------------------------
        //	The Groupings by collection="" value
        // --------------------------------------------
        
        $query = ee()->db->query("SELECT DISTINCT collection FROM exp_ratings WHERE collection != ''");
        
        $this->cached_vars['collections'] = array();
        
        foreach($query->result_array() as $row)
        {
        	$this->cached_vars['collections'][] = $row['collection'];
        }
        
		// --------------------------------------------
        //	Empty Form Names?
        // --------------------------------------------
        
        $query = ee()->db->query("SELECT COUNT(*) AS count FROM exp_ratings WHERE collection = ''");
        
        $this->cached_vars['empty_collections'] = ($query->row('count') > 1) ? 'y' : 'n';

		// --------------------------------------------
        //	Entry Titles for Non-Entry View Ratings Page
        // --------------------------------------------
		
		$this->cached_vars['entry_titles'] = array();

		if ($this->cached_vars['entry_id'] == '' && ! empty($this->cached_vars['ratings']))
		{
			$query = ee()->db->query("SELECT entry_id, title
									  FROM {$this->sc->db->channel_titles}
									  WHERE entry_id IN (".implode(',', array_map('ceil', $entry_ids)).")");

			foreach($query->result_array() as $row)
			{
				$this->cached_vars['entry_titles'][$row['entry_id']] = $row['title'];
			}
		}
		
		// --------------------------------------------
        //	Reported Rating Counts
        // --------------------------------------------
        
        $this->cached_vars['rating_reports'] = FALSE;
        
        if(isset($_POST['rating_status']) && $_POST['rating_status'] == 'reported')
		{
			$this->cached_vars['rating_reports'] = array();
		
			$query = ee()->db->query("SELECT COUNT(quarantine_id) AS report_count, rating_id
									  FROM exp_rating_quarantine
									  GROUP BY rating_id");
			
			foreach($query->result_array() as $row)
			{
				$this->cached_vars['rating_reports'][$row['rating_id']] = $row['report_count'];
			}
		}

		// --------------------------------------------
        //  Load page
        // --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('view_ratings.html', NULL, TRUE);        
		return $this->ee_cp_view('index.html');
    }
    // END view_ratings()
    
    
	// --------------------------------------------------------------------

	/**
	 *	Rated Entries List
	 *
	 *	A list of Rated Entries Based off POST values
	 *
	 *	@access		public
	 *	@return		string
	 */
	
	public function get_ratings($entry_id='')
	{
		if ( ! empty($entry_id))
		{
			if ( ! ctype_digit($entry_id)) return array();
		
			$page_url = $this->base.'&method=view_ratings&entry_id='.$entry_id;
		}
		else
		{
			$page_url = $this->base.'&method=view_ratings';
		}
		
		$defaults = array('rating_status'	=> 'all',		  // select list open/closed
						  'collection'		=> 'all',
						  'date_range'		=> 'all',		  // today, this week, this month, last month, this year, choose date range
						  'start_date'		=> time() - (30 * 24 * 60 * 60),
						  'end_date'		=> time(),
						  'keywords'		=> ''
						  );
						
		$params = array();
			
		// --------------------------------------------
        //	Any POST or GET variables to override our defaults?
        // --------------------------------------------
		
		foreach($defaults as $name => $value)
		{
			if ( ! empty($_POST[$name]) && gettype($_POST[$name]) == gettype($value))
			{
				$this->cached_vars['selected'][$name] = $params[$name] = $_POST[$name];
			}
			elseif ( ! empty($_GET[$name]))
			{
				if ( is_array($value) && strpos($_GET[$name], '|') !== FALSE)
				{
					$params[$name] = explode('|', $_GET[$name]);
				}
				else
				{
					$params[$name] = $_GET[$name];
				}
				
				$this->cached_vars['selected'][$name] = $params[$name];
			}
			elseif( $name == 'start_date' OR $name == 'end_date' )
			{
				$params[$name] = $value; // Default search value
				$this->cached_vars['selected'][$name] = $value;
			}
			else
			{
				$params[$name] = $value; // Default search value
				
				$this->cached_vars['selected'][$name] = (gettype($value) == 'array') ? array() : ''; // No default search, merely empty array or string
			}
			
			// --------------------------------------------
			//	Pagination for the ::yawn::
			// --------------------------------------------
			
			if ( is_array($params[$name]))
			{
				$page_url .= '&='.$name.'='.implode('|', $params[$name]);
			}
			else
			{
				$page_url .= '&='.$name.'='.$params[$name];
			}
		}
				
		// --------------------------------------------
        //	Chief, I Need Full Power to the Ratings SQL Inducer!
        // --------------------------------------------
        
        if ( ! empty($entry_id))
        {
			$sql = "SELECT * FROM exp_ratings WHERE entry_id = ".$entry_id; // $entry_id validated as digit above
        }
        else
        {
			$sql = "SELECT * FROM exp_ratings WHERE entry_id != 0";
        }
		
		// --------------------------------------------
        //	Status of Ratings
        // --------------------------------------------
		
		if ( in_array($params['rating_status'], array('open', 'closed')))
		{
			$sql .= " AND status = '".ee()->db->escape_str($params['rating_status'])."'";
		}
		elseif($params['rating_status'] == 'quarantined')
		{
			$sql .= " AND quarantine = 'y'";
		}
		elseif($params['rating_status'] == 'reported')
		{
			$result = ee()->db->query("SELECT DISTINCT rating_id FROM exp_rating_quarantine WHERE status != 'closed'");
			
			$naughty = array();
			
			foreach($result->result_array() as $row)
			{
				$naughty[] = $row['rating_id'];
			}
			
			if (sizeof($naughty) > 0)
			{
				$sql .= " AND rating_id IN (".implode(',', $naughty).")";
			}
		}
		
		// --------------------------------------------
        //	Kira, Open Form Name Frequencies
        // --------------------------------------------
        
		if ( 'all' != $params['collection'])
		{
			if ('empty' == $params['collection'])
			{
				$sql .= " AND collection = ''";
			}
			else
			{
				$sql .= " AND collection = '".ee()->db->escape_str($params['collection'])."'";
			}
		}
		
		// --------------------------------------------
        //	Search name, email, IP, and the text/textarea fields
        // --------------------------------------------
        
        $sql .= $this->ratings_keywords_search(ee()->input->get_post('keywords'), 'any');
		
		// --------------------------------------------
        //	Date Range - // today, this week, this month, last month, this year, choose date range
        // --------------------------------------------
        
   		if ( ! empty($params['date_range']))
		{
			if (ctype_digit($params['date_range']))
			{
				$since = time() - ($params['date_range'] * 60 * 60 * 24);
			}
			elseif ($params['date_range'] == 'date_range' && (ee()->input->post('start_date') !== FALSE OR ee()->input->post('end_date') !== FALSE))
			{
				if (ee()->input->post('start_date') !== FALSE)
				{
					$this->cached_vars['selected']['start_date'] = $start = strtotime(ee()->input->post('start_date') . ' 00:00:00');

					if (ctype_digit($start))
					{
						$sql		.= " AND rating_date >= '".$start."' ";
						$page_url	.= '&start_date='.ee()->input->post('start_date');
					}
				}
				
				if (ee()->input->post('end_date') !== FALSE)
				{
					$this->cached_vars['selected']['end_date'] = $end = strtotime(ee()->input->post('end_date'). ' 23:59:59');
					
					if (ctype_digit($end))
					{
						$sql		.= " AND rating_date <= '".$end."' ";
						$page_url	.= '&end_date='.ee()->input->post('end_date');
					}
				}
			}
			else
			{
				switch($params['date_range'])
				{
					case 'today'		: $since = mktime(0,0,0, date('m'), date('d'), date('Y'));
					break;
	
					case 'this_week'	: $since = mktime(0,0,0, date('m'), date('d') - ((date('N') == 7) ? 0 : date('N')), date('Y'));
					break;
	
					case 'this_month'	: $since = mktime(0,0,0, date('m'), 1, date('Y'));
					break;
					
					case 'last_month'	: $since = mktime(0,0,0, date('m') - 1, 1, date('Y'));
										  $before = mktime(0,0,0, date('m'), 1, date('Y'));
					break;
					
					case 'this_year'	: $since = mktime(0,0,0, 1, 1, date('Y'));
					break;					
				}
			}
			
			if ( isset($since))
			{
				$this->cached_vars['selected']['start_date'] = $since;
				$sql .= " AND rating_date > $since";
			}
			
			if ( isset($before))
			{
				$this->cached_vars['selected']['end_date'] = $before;
				$sql .= " AND rating_date < $before";
			}
		}
		
        // --------------------------------------------
        //	Order by Entry Date - Hardcoded.
        // --------------------------------------------
				
		$sql .= " ORDER BY rating_date DESC";
				
		// --------------------------------------------
        //	Dax, Give me a sense of the Theoretical Limit
        // --------------------------------------------
        
        $query = ee()->db->query(preg_replace("/SELECT(.*?)\s+FROM\s+/is",
        									  'SELECT COUNT(*) AS count FROM ',
        									  $sql,
        									  1));
        
        $this->cached_vars['paginate'] = '';
        
		if ( $query->row('count') > $this->row_limit )
		{
			$pagination_data = $this->universal_pagination(
				array(
						'sql'					=> $sql, 
						'total_results'			=> $query->row('count'), 
						'limit'					=> $this->row_limit,
						'current_page'			=> ( ! ee()->input->get_post('row')) ? 0 : ee()->input->get_post('row'),
						'pagination_config'		=> array('base_url' => $page_url),
						'query_string_segment'	=> 'row'
			));

			$sql		= $pagination_data['sql'];
			$this->cached_vars['paginate'] = $pagination_data['pagination_links'];
		}
        
        // --------------------------------------------
        //  NULL converted to empty string
        // --------------------------------------------
        
        $data = ee()->db->query($sql)->result_array();
        
        foreach($data as $i => $row)
        {
        	foreach($row as $key => $val)
        	{
        		$row[$key] = ($val == NULL) ? '' : $val;
        	}
        	
        	$data[$i] = $row;
        }
        
        // --------------------------------------------
        //	Ensign Nog! Get us Out of Here!
        // --------------------------------------------
                
        return $data;
			
	}
	/* END ratings_for_entries() */
	
	
	// --------------------------------------------------------------------

	/**
	 *	Keywords Search for Ratings from a given Weblog/Channel Entry
	 *
	 *	@access		public
	 *	@param		string		// string of keywords
	 *	@param		string		// title or title_and_content
	 *	@param		array		// array of channel ids
	 *	@param		string		// any, all, exact
	 *	@return		string
	 */
	 
	public function ratings_keywords_search($keywords, $search_type = 'any')
	{
		$sql = '';
		
		if (empty($keywords)) return $sql;
	
		// ----------------------------------------------
        //	Abstracted Keywords Parsing
        // ----------------------------------------------
        
        extract($this->keywords_parsing($keywords));
		
		// --------------------------------------------
		//	Field Search Phrase
		// --------------------------------------------
		
		$custom_fields = array('name', 'email', 'ip_address');
		
		$query = ee()->db->query("SELECT field_name FROM exp_rating_fields WHERE field_type IN ('textarea', 'text')");
		
		foreach($query->result_array() as $row)
		{
			$custom_fields[] = $row['field_name'];
		}
		
		$custom_fields = "CAST(CONCAT_WS(' ', `exp_ratings`.".implode(", `exp_ratings`.", $custom_fields).') AS CHAR)';
		
		// --------------------------------------------
		//	Check for Special Searches First - Legacy from EE
		// --------------------------------------------
		
		if (substr(strtolower($keywords_exact_phrase), 0, 3) == 'ip:')
		{
			$sql .= " AND `exp_ratings`.ip_address = '".ee()->db->escape_str(trim(substr(strtolower($keywords_exact_phrase), 3)))."'";
		}
		elseif(substr(strtolower($keywords_exact_phrase), 0, 4) == 'mid:')
		{
			$sql .= " AND `exp_ratings`.rating_author_id = '".ee()->db->escape_str(trim(substr(strtolower($keywords_exact_phrase), 4)))."'";
		}
		else
		{
			switch($search_type)
			{
				case 'exact' :
					$sql .= $this->ratings_exact_phrase_search($keywords_exact_phrase, $non_keyword_terms, $custom_fields);
				break;
				case 'any' :
					$sql .= $this->ratings_any_word_search($keyword_terms, $non_keyword_terms, $custom_fields);
				break;
				case 'all' :
					$sql .= $this->ratings_all_words_search($keyword_terms, $non_keyword_terms, $custom_fields);
				break;
			}
		}
		
		return $sql;
	}
	/* END entries_keywords_search() */
	
	// --------------------------------------------------------------------

	/**
	 * Search for the Exact Keyword Phrase
	 *
	 *
	 * @access	public
	 * @param	string
	 * @param	array
	 * @param	string
	 * @return	string
	 */
    
	function ratings_exact_phrase_search($keyword_phrase, $non_keyword_terms, $custom_fields)
    {
    	$keyword_phrase = trim($keyword_phrase);
    	
    	$sql = '';
    	
    	// --------------------------------------------
        //	The Anti-Keywords
        // --------------------------------------------
    	
    	if (sizeof($non_keyword_terms) > 0)
		{
			$sql .= " AND (\n";
			
			foreach($non_keyword_terms as $count => $non_keywords)
			{
				if ($non_keywords == '') continue;
				
				if ($count != 0)
				{
					$sql .= "AND \n";
				}
				
				$sql .=     $custom_fields." != '".ee()->db->escape_str($non_keywords)."' 
						  AND
						  ".$custom_fields." NOT LIKE '".ee()->db->escape_str($non_keywords)." %'
						  AND
						  ".$custom_fields." NOT LIKE '% ".ee()->db->escape_str($non_keywords)."'
						  AND
						  ".$custom_fields." NOT LIKE '% ".ee()->db->escape_str($non_keywords)." %'\n";
			}
			
			$sql .= ')';
		}
		
		// --------------------------------------------
        //	Keywords
        // --------------------------------------------
		
		if($keyword_phrase != '')
		{
			$sql .= " AND (\n".$custom_fields." LIKE '%".ee()->db->escape_str($keyword_phrase)."%'\n)\n";
		}
		
		return $sql;
    }
    /* END */
    
	// --------------------------------------------------------------------

	/**
	 * Search for Any of the Keyword Terms
	 *
	 *
	 * @access	public
	 * @param	array
	 * @param	array
	 * @param	string
	 * @return	string
	 */
    
	function ratings_any_word_search($keyword_terms, $non_keyword_terms, $custom_fields)
    {
    	$sql = '';
    	
    	// --------------------------------------------
        //	The Anti-Keywords
        // --------------------------------------------
    	
    	if (sizeof($non_keyword_terms) > 0)
		{
			$sql .= " AND (\n";
			
			foreach($non_keyword_terms as $count => $non_keywords)
			{
				if ($non_keywords == '') continue;
				
				if ($count != 0)
				{
					$sql .= "AND \n";
				}
			
				$sql .= 	$custom_fields." != '".ee()->db->escape_str($non_keywords)."' 
						  AND
						  ".$custom_fields." NOT LIKE '".ee()->db->escape_str($non_keywords)." %'
						  AND
						  ".$custom_fields." NOT LIKE '% ".ee()->db->escape_str($non_keywords)."'
						  AND
						  ".$custom_fields." NOT LIKE '% ".ee()->db->escape_str($non_keywords)." %'\n";
			}
			
			$sql .= ')';
		}
		
		// --------------------------------------------
        //	Keywords
        // --------------------------------------------
		
		if(sizeof($keyword_terms) > 0)
		{
			$sql .= " AND (\n";
			
			foreach($keyword_terms as $count => $keywords)
			{
				if ($keywords == '') continue;
				
				if ($count != 0)
				{
					$sql .= "OR ";
				}
			
				$sql .= $custom_fields." LIKE '%".ee()->db->escape_str($keywords)."%'\n";
			}
			
			$sql .= ")\n";
		}
		
		return $sql;
    }
    /* END */
    
	// --------------------------------------------------------------------

	/**
	 * Search for All the Keyword Terms
	 *
	 *
	 * @access	public
	 * @param	array
	 * @param	array
	 * @param	string
	 * @return	string
	 */
    
	function ratings_all_words_search($keyword_terms, $non_keyword_terms, $custom_fields)
    {
    	$sql = '';
    	
    	// --------------------------------------------
        //	The Anti-Keywords
        // --------------------------------------------
    	
    	if (sizeof($non_keyword_terms) > 0)
		{
			$sql .= " AND (\n";
			
			foreach($non_keyword_terms as $count => $non_keywords)
			{
				if ($non_keywords == '') continue;
			
				if ($count != 0)
				{
					$sql .= "AND \n";
				}
			
				$sql .=		$custom_fields." != '".ee()->db->escape_str($non_keywords)."' 
						  AND
						  ".$custom_fields." NOT LIKE '".ee()->db->escape_str($non_keywords)." %'
						  AND
						  ".$custom_fields." NOT LIKE '% ".ee()->db->escape_str($non_keywords)."'
						  AND
						  ".$custom_fields." NOT LIKE '% ".ee()->db->escape_str($non_keywords)." %'\n";
			}
			
			$sql .= ')';
		}
		
		// --------------------------------------------
        //	Keywords
        // --------------------------------------------
		
		if(sizeof($keyword_terms) > 0)
		{
			$sql .= " AND (\n";
			
			foreach($keyword_terms as $count => $keywords)
			{
				if ($keywords == '') continue;
			
				if ($count != 0)
				{
					$sql .= "AND ";
				}
			
				$sql .= "(".$custom_fields." = '".ee()->db->escape_str($keywords)."' 
						 OR
						 ".$custom_fields." LIKE '".ee()->db->escape_str($keywords)." %'
						 OR
						 ".$custom_fields." LIKE '% ".ee()->db->escape_str($keywords)."' 
						 OR
						 ".$custom_fields." LIKE '% ".ee()->db->escape_str($keywords)." %'\n)\n";
			}
			
			$sql .= ")\n";
		}
		
		return $sql;
    }
    /* END */
    
	// --------------------------------------------------------------------

	/**
	 *	Parses a string of keywords into keywords and not keywords
	 *
	 *	@access		public
	 *	@param		string		// string of keywords
	 *	@return		array
	 */
	 
	public function keywords_parsing($keywords)
	{
		$return = array('keywords_exact_phrase'	=> '',
						'keyword_terms'			=> array(),
						'non_keyword_terms'		=> array());
		
		if (empty($keywords)) return $return;
		
		ee()->load->helper('text');
	
		// ----------------------------------------------
        //	Keywords Search!  Yay!
        // ----------------------------------------------
        
        $keywords		= $this->keyword_clean(stripslashes($keywords));
		
		$keywords	  			= (ee()->config->item('auto_convert_high_ascii') == 'y') ? ascii_to_entities($keywords) : $keywords;
		$keywords_exact_phrase = $keywords;
		
		// --------------------------------------------
		//	Parse Out Non-Keyword Terms
		// --------------------------------------------
		
		$non_keyword_terms = array();
		
		if (preg_match_all("/-\"(.*?)\"/", $keywords, $matches))
		{
			for($m=0; $m < sizeof($matches[1]); $m++)
			{
				$non_keyword_terms[] = trim(str_replace('"','',$matches[0][$m]));
				$keywords = str_replace($matches[0][$m],'', $keywords);
			}    
		}
		
		if (preg_match_all("/-\w/", $keywords, $matches))
		{
			for($m=0; $m < sizeof($matches[1]); $m++)
			{
				$non_keyword_terms[] = trim(str_replace('"','',$matches[0][$m]));
			}    
		}
		
		// --------------------------------------------
		//	Parse Out Keyword Terms
		// --------------------------------------------
		
		$keyword_terms = array();
		
		if (preg_match_all("/\"(.*?)\"/", $keywords, $matches))
		{
			for($m=0; $m < sizeof($matches['1']); $m++)
			{
				$keyword_terms[] = trim(str_replace('"','',$matches[0][$m]));
				$keywords = str_replace($matches[0][$m],'', $keywords);
			}    
		}
		
		if (trim($keywords) != '')
		{
			$keyword_terms = array_merge($keyword_terms, preg_split("/\s+/", trim($keywords)));
		}
		
		return array('keywords_exact_phrase'	=> $keywords_exact_phrase,
					 'keyword_terms'			=> $keyword_terms,
					 'non_keyword_terms'		=> $non_keyword_terms);
	}
	// END keywords_parsing()
	
	
	// --------------------------------------------------------------------

	/**
	 *	Ratings Actions
	 *
	 *	Coming in from the View Ratings method.  Allows one to do actions on many Ratings at a time.
	 *
	 *	@access		public
	 *	@param		string		// string of keywords
	 *	@return		array
	 */
	 
	public function ratings_actions()
	{
		switch(ee()->input->post('action'))
		{
			case 'edit' : return $this->edit_ratings_form(); break;
			
			case 'delete' : return $this->delete_ratings_confirm_form(); break;
			
			case 'status_open'			:
			case 'status_closed'		:
			case 'status_quarantined'	:
				return $this->mass_status_switcher();
			break;
			
			default : return $this->view_ratings(); break;
		}
	}
	// END ratings_actions()
	
	
	// --------------------------------------------------------------------

	/**
	 * Switch Many Ratings to a new Status
	 *
	 * @access	public
	 * @return	string
	 */
	
    public function mass_status_switcher()
    {
    	// --------------------------------------------
        //	Remove 'status_' from the Action
        // --------------------------------------------
        
    	$status = substr(ee()->input->post('action'), 7);
    	
    	if ( ! in_array($status, array('open', 'closed', 'quarantined')))
    	{
    		$this->add_crumb(lang('invalid_request'));
			$this->cached_vars['error_message'] = lang('invalid_request');
			
			return $this->ee_cp_view('error_page.html');
    	}
    	
    	// --------------------------------------------
        //	What are we editing?
        // --------------------------------------------
    
    	if (ee()->input->get_post('rating_id') !== FALSE && ctype_digit(ee()->input->get_post('rating_id')))
    	{
    		$_POST['selected'][] = ee()->input->get_post('rating_id');
    	}
    
    	if (ee()->input->get_post('selected') === FALSE OR ! is_array(ee()->input->get_post('selected')))
    	{
    		return $this->view_ratings();
    	}
    	
    	$selected = ee()->input->get_post('selected');
    	
    	unset($_POST);
    	
    	foreach($selected as $rating_id)
    	{
    		$_POST['rating_status'][$rating_id] = $status;
    	}
    	
    	return $this->edit_ratings();
    }
    // END magic_status_switcher
	
	
	// --------------------------------------------------------------------

	/**
	 * Delete Ratings Confirmation Form 
	 *
	 * @access	public
	 * @return	string
	 */
	
    public function delete_ratings_confirm_form()
    {
    	// --------------------------------------------
        //	Allowed to Delete Ratings?
        // --------------------------------------------
        
    	if( ! in_array(ee()->session->userdata['group_id'], $this->preference('can_delete_ratings')))
		{
			$this->add_crumb(lang('invalid_request'));
			$this->cached_vars['error_message'] = lang('not_allowed_to_delete_ratings');
			
			return $this->ee_cp_view('error_page.html');
		}    
    
    	// --------------------------------------------
        //	What are we editing?
        // --------------------------------------------
    
    	if (ee()->input->get_post('rating_id') !== FALSE && ctype_digit(ee()->input->get_post('rating_id')))
    	{
    		$_POST['selected'][] = ee()->input->get_post('rating_id');
    	}
    
    	if (ee()->input->get_post('selected') === FALSE OR ! is_array(ee()->input->get_post('selected')))
    	{
    		return $this->view_ratings();
    	}
    	
    	// --------------------------------------------
        //	Retrieve Ratings Data
        // --------------------------------------------
    	
		$query = ee()->db->query("SELECT rating_id FROM exp_ratings
								  WHERE rating_id IN (".implode(',', array_map('ceil', ee()->input->get_post('selected'))).")");
    	
    	if ($query->num_rows() == 0)
    	{
    		return $this->view_ratings();
    	}
    	
    	foreach($query->result_array() as $row)
    	{
    		$this->cached_vars['delete'][] = $row['rating_id'];
    	}
    	
    	// --------------------------------------------
        //	Breadcrumbs and Variables
        // --------------------------------------------
    	
    	$this->add_crumb(lang('delete_ratings_confirm'));
    	$this->cached_vars['module_menu_highlight']	= 'module_ratings';
    	
    	$this->cached_vars['delete_question'] = str_replace(array('%i%', '%rating%'),
    														array(sizeof($this->cached_vars['delete']), 
    															 (sizeof($this->cached_vars['delete']) == 1) ? lang('rating') : lang('ratings')),
    														lang('ratings_delete_confirm'));
    	
    	$this->cached_vars['form_uri'] = $this->base.'&method=delete_ratings';
    	
		// --------------------------------------------
        //  Load page
        // --------------------------------------------

		$this->build_crumbs();

		$this->cached_vars['current_page'] = $this->view('delete_ratings_confirm.html', NULL, TRUE);     
		return $this->ee_cp_view('index.html');
    }
    // END delete_ratings_confirm_form()
    
    // --------------------------------------------------------------------

	/**
	 *	Delete Ratings
	 *
	 *	@access		public
	 *	@return		string
	 */

	function delete_ratings()
	{
		if (ee()->input->post('delete') === FALSE or ! is_array(ee()->input->post('delete')) OR sizeof(ee()->input->post('delete')) == 0)
		{ 
			return $this->view_ratings();
		}

		$query	= ee()->db->query("SELECT rating_id, entry_id, rating_author_id, channel_id FROM exp_ratings
								   WHERE rating_id IN (".implode(',', array_map('ceil', ee()->input->post('delete'))).")");
		
		if ($query->num_rows() == 0)
		{
			return $this->view_ratings();
		}
		
		foreach($query->result_array() as $row)
		{
			$channels[] = $row['channel_id'];
			$entries[]	= $row['entry_id'];
			$members[]	= $row['rating_author_id'];
			$ids[]		= $row['rating_id'];
		}
		
		// --------------------------------------------
        //	Delete the Ratings..GONE...buh-bye!
        // --------------------------------------------
		
		ee()->db->query("DELETE FROM exp_ratings WHERE rating_id IN (".implode(',', $ids).")");
		
		// --------------------------------------------
		//	Update Member's Statistics 
		// --------------------------------------------

		$this->actions()->update_member_stats($members);
		
		// --------------------------------------------
		//	Update Channel Statistics 
		// --------------------------------------------
		
		$this->actions()->update_channel_stats($channels);

		// ----------------------------------------
		//	Update rating stats
		// ----------------------------------------

		$this->actions()->update_entry_stats($entries);
		
		// --------------------------------------------
        //	We're Done, Let's Leave.
        // --------------------------------------------
		
		$message = ($query->num_rows() == 1) ? 
					str_replace( '%i%', 1, lang('rating_deleted') ) :
					str_replace( '%i%', $query->num_rows(), lang('ratings_deleted') );

		return $this->view_ratings($message);
	}
	// END delete_ratings()
	
	// --------------------------------------------------------------------

	/**
	 * Edit Ratings Form
	 *
	 * Form for editing one or multiple ratings at a time. 
	 *
	 * @access	public
	 * @return	string
	 */
	
    public function edit_ratings_form()
    {
    	// --------------------------------------------
        //	Allowed to Post/Edit Ratings?
        // --------------------------------------------
        
    	if( ! in_array(ee()->session->userdata['group_id'], $this->preference('can_post_ratings')))
		{
			$this->add_crumb(lang('invalid_request'));
			$this->cached_vars['error_message'] = lang('not_allowed_to_post_ratings');
			
			return $this->ee_cp_view('error_page.html');
		}    
    
    	// --------------------------------------------
        //	What are we editing?
        // --------------------------------------------
    
    	if (ee()->input->get_post('rating_id') !== FALSE && ctype_digit(ee()->input->get_post('rating_id')))
    	{
    		$_POST['selected'][] = ee()->input->get_post('rating_id');
    	}
    
    	if (ee()->input->get_post('selected') === FALSE OR ! is_array(ee()->input->get_post('selected')))
    	{
    		return $this->view_ratings();
    	}
    	
    	// --------------------------------------------
        //	Retrieve Ratings Data
        // --------------------------------------------
    	
		$query = ee()->db->query("SELECT * FROM exp_ratings
								  WHERE rating_id IN (".implode(',', array_map('ceil', ee()->input->get_post('selected'))).")");
    	
    	if ($query->num_rows() == 0)
    	{
    		return $this->view_ratings();
    	}
    	
    	$this->cached_vars['ratings'] = $query->result_array();
    	
    	// --------------------------------------------
        //	Prep Breadcrumbs and the like as we have results to display
        // --------------------------------------------
    	
    	$this->add_crumb(lang('edit_ratings'));
    	$this->cached_vars['module_menu_highlight']	= 'module_ratings';
    	
    	// --------------------------------------------
        //	Retrieve List of Rating Fields
        // --------------------------------------------
    	
    	$this->cached_vars['rating_fields'] = $this->data->get_rating_fields_data();
    	
    	if (sizeof($this->cached_vars['ratings']) == 1)
    	{
    		$this->cached_vars['selected']['rating_fields'] = array();
    		
    		foreach($this->cached_vars['rating_fields'] as $field_data)
    		{
    			$this->cached_vars['selected']['rating_fields'][] = $field_data['field_name'];
    		}
		}
		else
		{
			$this->cached_vars['selected']['rating_fields'] = array('rating', 'review');
		}
		
		// --------------------------------------------
        //  Load page
        // --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('edit_ratings_form.html', NULL, TRUE);        
		return $this->ee_cp_view('index.html');
    }
    // END edit_ratings_form()
    
    
	// --------------------------------------------------------------------

	/**
	 * Edit Ratings Submission
	 *
	 * Submit a ratings.  Right now we only change status or the value of a field, nothing else
	 *
	 * @access	public
	 * @return	string
	 */
	 
	function edit_ratings()
	{
		// --------------------------------------------
        //	Allowed to Post/Edit Ratings?
        // --------------------------------------------
        
    	if( ! in_array(ee()->session->userdata['group_id'], $this->preference('can_post_ratings')))
		{
			$this->add_crumb(lang('invalid_request'));
			$this->cached_vars['error_message'] = lang('not_allowed_to_post_ratings');
			
			return $this->ee_cp_view('error_page.html');
		}
		
		// --------------------------------------------
        //	IDs Taken from Rating Status field - So Check
        // --------------------------------------------
        
        if ( ee()->input->post('rating_status') === FALSE OR ! is_array(ee()->input->post('rating_status')))
        {
        	$this->add_crumb(lang('invalid_request'));
			$this->cached_vars['error_message'] = lang('invalid_request');
			
			return $this->ee_cp_view('error_page.html');
        }
        
        if ( in_array('quarantine', ee()->input->post('rating_status')) && 
        	! in_array(ee()->session->userdata['group_id'], $this->preference('can_report_ratings')))
        {
        	$this->add_crumb(lang('invalid_request'));
			$this->cached_vars['error_message'] = lang('not_allowed_to_quarantine_ratings');
			
			return $this->ee_cp_view('error_page.html');
        }
        
        $rating_ids = array_map('ceil', array_keys(ee()->input->get_post('rating_status')));
	
		// --------------------------------------------
        //	Validate Rating IDs and Grab Old Status/Quarantine 
        // --------------------------------------------
    	
		$query = ee()->db->query("SELECT rating_id, rating_author_id, quarantine, status, entry_id, channel_id
								  FROM exp_ratings WHERE rating_id IN (".implode(',', $rating_ids).")");
    	
    	if ($query->num_rows() == 0)
    	{
    		return $this->view_ratings();
    	}
    	
    	// --------------------------------------------
        //	Let's Process Our Data!
        // --------------------------------------------
        
        $entries	= array();
        $channels	= array();
        $members	= array();
        
        foreach($query->result_array() as $row)
        {
        	$entries[]	= $row['entry_id'];
        	$channels[] = $row['channel_id'];
        	$members[]	= $row['rating_author_id'];
        
        	$insert = array();
        
        	// --------------------------------------------
			//	Status and Quarantine Update
			// --------------------------------------------
			
			if ($_POST['rating_status'][$row['rating_id']] == 'open')
			{
				$insert['quarantine']	= '';
				$insert['status']		= 'open';
			}
			elseif ($_POST['rating_status'][$row['rating_id']] == 'closed')
			{
				$insert['status']		= 'closed';
			}
			elseif ($_POST['rating_status'][$row['rating_id']] == 'quarantined')
			{
				$insert['quarantine']	= 'y';
				$insert['status']		= 'closed';
			}
			else
			{
				continue; // ERROR!
			}
			
			if ( ! empty($insert['quarantine']) && $row['quarantine'] == 'y')
			{
				ee()->db->query(ee()->db->update_string('exp_rating_quarantine',
														array('status' => 'closed', 
															  'edit_date' => ee()->localize->now ),
														array( 'rating_id' => $row['rating_id'])));

			}
			
			// --------------------------------------------
			//	Rating Fields
			// --------------------------------------------
			
			foreach($this->data->get_rating_fields_data() as $field_name => $field_data)
			{
				if ( isset($_POST[$field_name][$row['rating_id']]))
				{
					if ($field_data['field_type'] == 'number')
					{
						if ($_POST[$field_name][$row['rating_id']] == '')
						{
							$insert[$field_name] = NULL;
						}
						elseif(is_numeric($_POST[$field_name][$row['rating_id']]))
						{
							$insert[$field_name] = ceil($_POST[$field_name][$row['rating_id']]);
						}
					}
					else
					{
						$insert[$field_name] = ee()->security->xss_clean($_POST[$field_name][$row['rating_id']]);
					}
				}
			}
			
			ee()->db->query(ee()->db->update_string('exp_ratings',
													$insert,
													array( 'rating_id' => $row['rating_id'])));
        }
        
        // --------------------------------------------
		//	Update Member's Statistics 
		// --------------------------------------------

		$this->actions()->update_member_stats(array_unique($members));
		
		// --------------------------------------------
		//	Update Channel Statistics 
		// --------------------------------------------
		
		$this->actions()->update_channel_stats(array_unique($channels));

		// ----------------------------------------
		//	Update rating stats
		// ----------------------------------------

		$this->actions()->update_entry_stats(array_unique($entries));
		
		
		// --------------------------------------------
        //	Success!  Congrats!  Have some Pie!
        // --------------------------------------------
        
        return $this->view_ratings( lang('success_ratings_saved') );
    }
	// END edit_ratings()
	
	
	// --------------------------------------------------------------------

	/**
	 * Manage Fields Control Panel Page
	 *
	 * @access	public
	 * @return	string
	 */
	
    public function fields($message = '')
    {
    	if (isset($_GET['msg']))
        {
        	$message = lang($_GET['msg']);
        }
        
        $this->cached_vars['message'] = $message;
    
    	//--------------------------------------------  
		//	Crumbs and tab highlight
		//--------------------------------------------
		
		$this->add_crumb( lang('fields') );
		$this->add_right_link(lang('edit_field_order'), $this->base . AMP . 'method=edit_field_order');
		
		$query = ee()->db->query("SELECT COUNT(*) AS count FROM exp_rating_fields");
        	
		if ($query->row('count') < $this->field_limit)
		{
			$this->add_right_link(lang('create_new_field'), $this->base . AMP . 'method=add_field');
		}
				
		$this->cached_vars['module_menu_highlight']	= 'module_fields';
		
		// --------------------------------------------
        //	Fetch Current Fields
        // --------------------------------------------
        
        $query = ee()->db->query("SELECT COUNT(*) AS count FROM exp_rating_fields ORDER BY field_order ASC");
        
        $this->cached_vars['paginate'] = '';
		
		$sql = "SELECT * FROM exp_rating_fields ORDER BY field_order ASC";
		
		if ( $query->row('count') > $this->row_limit )
		{
			$pagination_data = $this->universal_pagination(
				array(
						'sql'					=> $sql, 
						'total_results'			=> $query->row('count'), 
						'limit'					=> $this->row_limit,
						'current_page'			=> ( ! ee()->input->get_post('row')) ? 0 : ee()->input->get_post('row'),
						'pagination_config'		=> array('base_url' => $this->base.AMP.'method=fields'),
						'query_string_segment'	=> 'row'
			));

			$sql		= $pagination_data['sql'];
			$this->cached_vars['paginate'] = $pagination_data['pagination_links'];
		}
		
		$query = ee()->db->query($sql);	
		
		// --------------------------------------------
        //	Process Current Fields
        // --------------------------------------------
		
		$this->cached_vars['fields'] = array();
		
		foreach ( $query->result_array() as $count => $row )
		{
			$this->cached_vars['fields'][$count] = $row;
			
			$this->cached_vars['fields'][$count]['locked'] = (in_array($row['field_name'], $this->locked_fields)) ? 'y' : 'n';
		}
		
		unset($query);

		// --------------------------------------------
        //  Load page
        // --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('fields.html', NULL, TRUE);        
		return $this->ee_cp_view('index.html');		
	}
	/* END fields() */
	
		
	// --------------------------------------------------------------------

	/**
	 * Edit Field / Add New Field Form
	 *
	 * @access	public
	 * @param	message
	 * @return	string
	 */
	
	public function add_field() { return $this->edit_field(); } 	
    public function edit_field()
    {
    	//--------------------------------------------  
		//	Crumbs and tab highlight
		//--------------------------------------------
		
		$this->add_crumb( (ee()->input->get_post('field_id') === FALSE) ? lang('add_field') : lang('edit_field') );
		$this->cached_vars['module_menu_highlight']	= 'module_fields';
		
		// --------------------------------------------
        //	Default Values
        // --------------------------------------------
		
		$field_values = array(	'field_id'		=> (ee()->input->get_post('field_id') === FALSE) ? '' : ee()->input->get_post('field_id'),
								'field_label'	=> '',
								'field_name'	=> '',
								'field_type'	=> 'textarea',
								'field_fmt'		=> 'none',
								'field_order'	=> 1,
								'field_maxl'	=> 100,
								'field_locked'	=> 'no');
		
		// --------------------------------------------
        //	Fetch Current Field Data, If Editing
        // --------------------------------------------
        
        if (ee()->input->get_post('field_id') !== FALSE)
        {
        	$query = ee()->db->query("SELECT * FROM exp_rating_fields
        							  WHERE field_id = '".ee()->db->escape_str(ee()->input->get_post('field_id'))."'");
        	
        	if ($query->num_rows() == 0)
        	{
        		$this->add_crumb(lang('invalid_request'));
        		$this->cached_vars['error_message'] = lang('invalid_request');
        		
        		return $this->ee_cp_view('error_page.html');
        	}
        	
        	$field_values = $query->row_array();
        	
        	$field_values['field_locked'] = (in_array($field_values['field_name'], $this->locked_fields)) ? 'yes' : 'no';
        }
        else
        {
        	$query = ee()->db->query("SELECT COUNT(*) AS count FROM exp_rating_fields");
        	
        	if ($query->row('count') >= $this->field_limit)
        	{
        		$this->add_crumb(lang('invalid_request'));
        		$this->cached_vars['error_message'] = lang('rating_field_limit');
        		
        		return $this->ee_cp_view('error_page.html');
        	}
        }
        
        $this->cached_vars = array_merge($this->cached_vars, $field_values);
        
		// --------------------------------------------
        //  Load page
        // --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('edit_field.html', NULL, TRUE);        
		return $this->ee_cp_view('index.html');		
	}
	/* END fields() */
	
	// --------------------------------------------------------------------

	/**
	 *	Edit Field Order
	 *
	 *	@access		public
	 *	@return		string
	 */
	
	public function edit_field_order()
	{	
		//--------------------------------------------  
		//	Crumbs and tab highlight
		//--------------------------------------------
		
		$this->add_crumb(lang('edit_field_order'));
		
		$this->cached_vars['module_menu_highlight']	= 'module_fields';
		
		// --------------------------------------------
        //	Field Label, ID, and Order
        // --------------------------------------------
	
		$query = ee()->db->query("SELECT field_id, field_label, field_order
								  FROM exp_rating_fields ORDER BY field_order ASC");
		
		$this->cached_vars['fields'] = ($query->num_rows() == 0) ? array() : $query->result_array();
	
		// --------------------------------------------
        //  Load page
        // --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('edit_field_order.html', NULL, TRUE);        
		return $this->ee_cp_view('index.html');		
	}
	/* END edit_field_order()*/
	
	
	// --------------------------------------------------------------------

	/**
	 * Manage Notification Templates - CP Page
	 *
	 * @access	public
	 * @return	string
	 */
	
    public function templates($message = '')
    {
    	if (isset($_GET['msg']))
        {
        	$message = lang($_GET['msg']);
        }
        
        $this->cached_vars['message'] = $message;
    
    	//--------------------------------------------  
		//	Crumbs and tab highlight
		//--------------------------------------------
		
		$this->add_crumb( lang('notification_templates') );
		$this->add_right_link(lang('create_new_template'), $this->base . AMP . 'method=add_template');
		
		$this->cached_vars['module_menu_highlight']	= 'module_templates';
		
		// --------------------------------------------
        //	Fetch Current Fields
        // --------------------------------------------
        
        $query = ee()->db->query("SELECT COUNT(*) AS count FROM exp_rating_templates ORDER BY template_name ASC");
        
        $this->cached_vars['paginate'] = '';
		
		$sql = "SELECT * FROM exp_rating_templates ORDER BY template_name ASC";
		
		if ( $query->row('count') > $this->row_limit )
		{
			$pagination_data = $this->universal_pagination(
				array(
						'sql'					=> $sql, 
						'total_results'			=> $query->row('count'), 
						'limit'					=> $this->row_limit,
						'current_page'			=> ( ! ee()->input->get_post('row')) ? 0 : ee()->input->get_post('row'),
						'pagination_config'		=> array('base_url' => $this->base.AMP.'method=teplates'),
						'query_string_segment'	=> 'row'
			));

			$sql		= $pagination_data['sql'];
			$this->cached_vars['paginate'] = $pagination_data['pagination_links'];
		}
		
		$query = ee()->db->query($sql);	
		
		// --------------------------------------------
        //	Process Templates for Output
        // --------------------------------------------
		
		$this->cached_vars['templates'] = array();
		
		foreach ( $query->result_array() as $count => $row )
		{
			$this->cached_vars['templates'][$count] = $row;
			
			$this->cached_vars['templates'][$count]['locked'] = (in_array($row['template_name'], $this->locked_templates)) ? 'y' : 'n';
		}
		
		unset($query);

		// --------------------------------------------
        //  Load page
        // --------------------------------------------
        
        $this->build_crumbs();
		$this->build_right_links();

		$this->cached_vars['current_page'] = $this->view('notification_templates.html', NULL, TRUE);        
		return $this->ee_cp_view('index.html');		
	}
	/* END templates() */
	

	// --------------------------------------------------------------------

	/**
	 * Edit Template / Add New Template Form
	 *
	 * @access	public
	 * @return	string
	 */
	
	public function add_template() { return $this->edit_template(); } 	
    public function edit_template()
    {
    	//--------------------------------------------  
		//	Crumbs and tab highlight
		//--------------------------------------------
		
		$this->add_crumb( (ee()->input->get_post('template_id') === FALSE) ? lang('add_template') : lang('edit_template') );
		$this->cached_vars['module_menu_highlight']	= 'module_templates';
		
		// --------------------------------------------
        //	Default Values
        // --------------------------------------------
		
		$template_values = array(	'template_id'		=> (ee()->input->get_post('template_id') === FALSE) ? '' : ee()->input->get_post('template_id'),
									'template_label'	=> '',
									'template_name'		=> '',
									'subject'			=> '',
									'message'			=> '',
									'wordwrap'			=> 'y',
									'enable_template'	=> 'y',
									'template_locked'	=> 'no');
		
		// --------------------------------------------
        //	Fetch Current Field Data, If Editing
        // --------------------------------------------
        
        if (ee()->input->get_post('template_id') !== FALSE)
        {
        	$query = ee()->db->query("SELECT * FROM exp_rating_templates
        							  WHERE template_id = '".ee()->db->escape_str(ee()->input->get_post('template_id'))."'");
        	
        	if ($query->num_rows() == 0)
        	{
        		$this->add_crumb(lang('invalid_request'));
        		$this->cached_vars['error_message'] = lang('invalid_request');
        		
        		return $this->ee_cp_view('error_page.html');
        	}
        	
        	$template_values = $query->row_array();
        	
        	$template_values['template_locked'] = (in_array($template_values['template_name'], $this->locked_templates)) ? 'yes' : 'no';
        }
        
        $this->cached_vars['template_data'] = $template_values;
        
		// --------------------------------------------
        //  Load page
        // --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('edit_template.html', NULL, TRUE);        
		return $this->ee_cp_view('index.html');		
	}
	/* END fields() */
	
	// --------------------------------------------------------------------

	/**
	 *	Save a Notification Template
	 *
	 *	@access		public
	 *	@return		string
	 */
	
	function save_template()
	{
		// --------------------------------------------
        //	Check for Variables
        // --------------------------------------------
			
		if ( empty( $_POST['message']) OR empty( $_POST['subject']) OR ! isset($_POST['template_id']) OR empty($_POST['template_name']) OR empty($_POST['template_label']))
		{
			return $this->error_page(lang('required_field_was_empty'));
		}
		
		// --------------------------------------------
        //	Create Data for Input
        // --------------------------------------------
        
        $data = array(	'template_label'	=> '',
						'template_name'		=> '',
						'subject'			=> '',
						'message'			=> '',
						'wordwrap'			=> 'y',
						'enable_template'	=> 'y');
						
		$data = array_intersect_key($_POST, $data);
				
		// ----------------------------------------
		//  New or Edit?
		// ----------------------------------------
		
		if ( $_POST['template_id'] == '' )
		{	
			$query	= ee()->db->query("SELECT COUNT(*) AS count FROM exp_rating_templates
									   WHERE template_name = '".ee()->db->escape_str($_POST['template_name'])."' LIMIT 1");

			if ( $query->row('count') > 0 )
			{
				return $this->error_page(lang('template_name_exists'));
			}
			
			ee()->db->query( ee()->db->insert_string('exp_rating_templates', $data ) );

			$success = lang('template_created_successfully');
		}
		else
		{	
			$query	= ee()->db->query("SELECT COUNT(*) AS count FROM exp_rating_templates
									   WHERE template_name = '".ee()->db->escape_str($_POST['template_name'])."' 
									   AND template_id != '".ee()->db->escape_str($_POST['template_id'])."' LIMIT 1");

			if ( $query->row('count') > 0 )
			{
				return $this->error_page(lang('template_name_exists'));
			}
			
			if (in_array($_POST['template_name'], $this->locked_templates))
			{
				unset($data['template_label'], $data['template_name']);
			}
			
			ee()->db->query(ee()->db->update_string('exp_rating_templates', $data, array('template_id' => $_POST['template_id'])));

			$success = lang('template_update_successful');
		}

		return $this->templates( $success );
	}
	/* END save_template() */
	
	
	// --------------------------------------------------------------------

	/**
	 * Preferences for This Module
	 
	 * @access	public
	 * @param	string
	 * @return	null
	 */
    
	public function preferences($message = '')
    {
    	if (isset($_GET['msg']))
        {
        	$message = lang($_GET['msg']);
        }
        
        $this->cached_vars['message'] = $message;
    
		// -------------------------------------
		//	Title and Crumbs
		// -------------------------------------
		
		$this->add_crumb(lang('rating_preferences'));
		
		$this->cached_vars['module_menu_highlight'] = 'module_preferences';
		
		// --------------------------------------------
        //	Fetch Channels with Site Label
        // --------------------------------------------
        
        $this->cached_vars['channels'] = array();
        
        $query = ee()->db->query("SELECT {$this->sc->db->channel_id} AS channel_id,
        								 {$this->sc->db->channel_title} AS channel_title,
										 site_id
								FROM {$this->sc->db->channels}
								ORDER BY site_id, {$this->sc->db->channel_name}");
        
        foreach($query->result_array() as $row)
        {
        	$this->cached_vars['channels'][$row['site_id']][$row['channel_id']] = $row['channel_title'];
        }
        
        // --------------------------------------------
        //	Fetch Member Groups
        // --------------------------------------------
        
        $this->cached_vars['member_groups'] = array();
        
        $groups_query = ee()->db->query("SELECT group_id, group_title FROM exp_member_groups 
										WHERE group_id NOT IN (2,4)
										AND site_id = '".ee()->db->escape_str(ee()->config->item('site_id'))."'
										ORDER BY group_title");
        
        foreach($groups_query->result_array() as $row)
        {
        	$this->cached_vars['member_groups'][$row['group_id']] = $row['group_title'];
        }
		
		// ----------------------------------
        //	Fetch the Preferences
        // ----------------------------------
        
		$this->cached_vars['prefs'] = $this->actions()->module_preferences();
		
		// --------------------------------------------
        //	Load Homepage
        // --------------------------------------------
        
        $this->cached_vars['current_page'] = $this->view('preferences.html', NULL, TRUE);        
		return $this->ee_cp_view('index.html');
	}
	/* END preferences() */
	
	
	// --------------------------------------------------------------------

	/**
	 * Saves the Preferences
	 
	 * @access	public
	 * @return	null
	 */
    
	public function save_preferences()
    {
		// ----------------------------------
        //	Check for Preference Values
        // ----------------------------------
        
        $inserts = array();
        
        foreach(explode('|', RATING_PREFS) as $field)
        {
        	$value = '';
        	
        	if ( isset($_POST[$field]))
        	{
        		$value = (is_array($_POST[$field])) ? implode('|', $_POST[$field]) : $_POST[$field];
        	}
        	
			$inserts[] = array('preference_name'	=> $field,
							   'preference_value'	=> $value);
        }
        
        // ----------------------------------
        //	Clear and Re-Insert
        // ----------------------------------
        
        ee()->db->query("TRUNCATE exp_rating_preferences");
        
        foreach($inserts as $insert)
        {
        	ee()->db->query(ee()->db->insert_string('exp_rating_preferences', $insert));
        }
        
        // ----------------------------------
        //	Redirect to Homepage with Message
        // ----------------------------------
        
        ee()->functions->redirect($this->base.'&method=preferences&msg=rating_preferences_updated');
        exit;
	}
	/* END save_preferences() */

	// --------------------------------------------------------------------

	/**
	 *	Export Ratings
	 *
	 *	Exports a list of ratings from the current search parameters - View Ratings page only
	 *
	 *	@access		public
	 *	@return		string
	 */
	
	function export_ratings($data)
	{	
		if ( empty($data)) exit('');
	
		// ----------------------------------------
		//  Build the output header
		// ----------------------------------------
		
		ob_start();

		// ----------------------------------------
		//	Create header
		// ----------------------------------------
		
		echo $this->csv_output(lang('count')).$this->csv_separator;
		
		echo $this->csv_output(lang('rating_status')).$this->csv_separator;
		
		echo $this->csv_output(lang('quarantined')).$this->csv_separator;
		
		echo $this->csv_output(lang('date')).$this->csv_separator;
		
		echo $this->csv_output(lang('collection')).$this->csv_separator;
		
		echo $this->csv_output(lang('entry_id')).$this->csv_separator;
		
		echo $this->csv_output(lang('raters_name')).$this->csv_separator;
		
		echo $this->csv_output(lang('email')).$this->csv_separator;
		
		foreach($this->data->get_rating_fields_list() as $field_name => $field_label)
		{
			echo $this->csv_output($field_label).$this->csv_separator;
		}

		// ----------------------------------------
		//	Create body
		// ----------------------------------------
		
		$row_count = 0;
		
		foreach ( $data as $row )
		{			
			echo "\n";
			
			echo $this->csv_output($row_count).$this->csv_separator;
			
			echo $this->csv_output(ucfirst($row['status'])).$this->csv_separator;
			
			echo $this->csv_output(($row['quarantine'] == 'y') ? 'y' : 'n').$this->csv_separator;
			
			echo $this->csv_output(ee()->localize->set_human_time( $row['rating_date'])).$this->csv_separator;
			
			echo $this->csv_output(($row['collection'] == '') ? 'empty' : $row['collection']).$this->csv_separator;
			
			echo $this->csv_output($row['entry_id']).$this->csv_separator;
			
			echo $this->csv_output($row['name']).$this->csv_separator;
			
			echo $this->csv_output($row['email']).$this->csv_separator;
			
			foreach($this->data->get_rating_fields_list() as $field_name => $field_label)
			{
				echo $this->csv_output($row[$field_name]).$this->csv_separator;
			}
			
			$row_count++;
		}

		// ----------------------------------------
		//	Return the finalized output
		// ----------------------------------------
		
		$buffer = ob_get_contents();
		
		ob_end_clean();
		
		
		$now		= ee()->localize->set_localized_time();
		$name		= ( ee()->input->get_post('collection') ) ? ee()->input->get_post('collection'): 'Ratings_Export';
		$filename	= str_replace(" ","_",$name).'_'.date('Y', $now).date('m', $now).date('d', $now)."_".date('G', $now)."-".date('i', $now);
	
		ee()->load->library('zip');
		ee()->zip->add_data($filename.'.csv', $buffer);
		ee()->zip->download($filename.'.zip');
		ee()->zip->clear_data();
		 
		exit;
	}
	//	End export entries
	
	// --------------------------------------------------------------------

	/**
	 *	CSV Output Formatter
	 *
	 *	Preps any CSV for Output based on the delimiting character
	 *
	 *	@access		public
	 *	@param		string
	 *	@return		string
	 */
	function csv_output($str)
	{
		$str = trim($str);
		
		if (stristr($str, '"'))
		{
			$str = '"'.str_replace('"', '""', $str).'"';
		}
		elseif (stristr($str, "\n") OR stristr($str, "\r") OR stristr($str, ','))
		{
			$str = '"'.$str.'"';
		}
		
		return $str;
	}
	// END csv_output()


	// --------------------------------------------------------------------

	/**
	 *	Delete Template Confirmation Form
	 *
	 *	@access		public
	 *	@return		string
	 */

	function delete_templates_confirm()
	{
		if (ee()->input->post('delete') === FALSE or ! is_array(ee()->input->post('delete')) OR sizeof(ee()->input->post('delete')) == 0)
		{ 
			return $this->templates();
		}
		
		$this->cached_vars['delete'] = $_POST['delete'];
		
		// --------------------------------------------
        //	Crumbs and Page Title
        // --------------------------------------------
		
		$this->add_crumb( lang('template_delete_confirm') );
		$this->cached_vars['module_menu_highlight']	= 'module_templates';
		
		$replace[] = sizeof($_POST['delete']);
		$replace[] = ( sizeof($_POST['delete']) == 1 ) ? 'template' : 'templates';
		
		$search	= array( '%i%', '%templates%' );
		
		$this->cached_vars['delete_question'] = str_replace( $search, $replace, lang('template_delete_question') );
		
		$this->cached_vars['form_uri'] = $this->base.'&method=delete_templates';

		// --------------------------------------------
        //  Load page
        // --------------------------------------------
        
        $this->build_crumbs();
        $this->build_right_links();

		$this->cached_vars['current_page'] = $this->view('delete_field_confirm.html', NULL, TRUE);        
		return $this->ee_cp_view('index.html');	
	}
	/* END delete_template_confirm() */
  
	
	// --------------------------------------------------------------------

	/**
	 *	Delete Rating Notification Template
	 *
	 *	@access		public
	 *	@return		string
	 */

	function delete_templates()
	{
		if (ee()->input->post('delete') === FALSE or ! is_array(ee()->input->post('delete')) OR sizeof(ee()->input->post('delete')) == 0)
		{ 
			return $this->templates();
		}
		
		$query	= ee()->db->query("SELECT template_id FROM exp_rating_templates
								   WHERE template_id IN ('".implode("','", ee()->db->escape_str($_POST['delete']))."')
								   AND template_name NOT IN ('".implode("','", ee()->db->escape_str($this->locked_templates))."')");
		
		foreach ( $query->result_array() as $row )
		{
			ee()->db->query("DELETE FROM exp_rating_templates WHERE template_id = '".ee()->db->escape_str($row['template_id'])."'");
		}
	
		$message = ($query->num_rows() == 1) ? str_replace( '%i%', 
															$query->num_rows(), 
															lang('template_deleted') ) 
											 : str_replace( '%i%', 
											 				$query->num_rows(),
											 				lang('templates_deleted') );

		return $this->templates($message);
	}
	/* END delete_templates() */
    
	
	// --------------------------------------------------------------------

	/**
	 *	Delete Field Confirmation Form
	 *
	 *	@access		public
	 *	@return		string
	 */

	function delete_field_confirm()
	{
		if (ee()->input->post('delete') === FALSE or ! is_array(ee()->input->post('delete')) OR sizeof(ee()->input->post('delete')) == 0)
		{ 
			return $this->fields();
		}
		
		$this->cached_vars['delete'] = $_POST['delete'];
		
		// --------------------------------------------
        //	Crumbs and Page Title
        // --------------------------------------------
		
		$this->add_crumb( lang('field_delete_confirm') );
		$this->cached_vars['module_menu_highlight']	= 'module_fields';
		
		$replace[] = sizeof($_POST['delete']);
		$replace[] = ( sizeof($_POST['delete']) == 1 ) ? 'field' : 'fields';
		
		$search	= array( '%i%', '%fields%' );
		
		$this->cached_vars['delete_question'] = str_replace( $search, $replace, lang('field_delete_question') );
		
		$this->cached_vars['form_uri'] = $this->base.'&method=delete_field';

		// --------------------------------------------
        //  Load page
        // --------------------------------------------

		$this->cached_vars['current_page'] = $this->view('delete_field_confirm.html', NULL, TRUE);        
		return $this->ee_cp_view('index.html');	
	}
	/* END delete_field_confirm() */
  
	
	// --------------------------------------------------------------------

	/**
	 *	Delete Rating Field
	 *
	 *	@access		public
	 *	@return		string
	 */

	function delete_field()
	{
		if (ee()->input->post('delete') === FALSE or ! is_array(ee()->input->post('delete')) OR sizeof(ee()->input->post('delete')) == 0)
		{ 
			return $this->fields();
		}

		$query	= ee()->db->query("SELECT field_id, field_name FROM exp_rating_fields
								   WHERE field_id IN ('".implode("','", ee()->db->escape_str($_POST['delete']))."')");
		
		if ($query->num_rows() > 0)
		{
			// --------------------------------------------
			//	Ensure the fields exist before dropping
			// --------------------------------------------
			
			$q = ee()->db->query("SHOW COLUMNS FROM exp_rating_stats");
			$columns = array();
			
			foreach ($q->result_array() as $r)
			{
				$columns[] = $r['Field'];
			}
	
			foreach ( $query->result_array() as $row )
			{
				ee()->db->query("DELETE FROM exp_rating_fields WHERE field_id = '".ee()->db->escape_str($row['field_id'])."'");
				
				ee()->db->query("ALTER TABLE exp_ratings DROP `".$row['field_name']."`");
				
				if (in_array('sum_'.$row['field_id'], $columns))
				{
					ee()->db->query("ALTER TABLE exp_rating_stats DROP sum_".$row['field_id']);
				}
				
				if (in_array('total_'.$row['field_id'], $columns))
				{
					ee()->db->query("ALTER TABLE exp_rating_stats DROP total_".$row['field_id']);
				}
				
				if (in_array('count_'.$row['field_id'], $columns))
				{
					ee()->db->query("ALTER TABLE exp_rating_stats DROP count_".$row['field_id']);
				}
				
				if (in_array('avg_'.$row['field_id'], $columns))
				{
					ee()->db->query("ALTER TABLE exp_rating_stats DROP avg_".$row['field_id']);
				}
			}
		}
	
		$message = ($query->num_rows() == 1) ? str_replace( '%i%', $query->num_rows(), lang('field_deleted') ) : str_replace( '%i%', $query->num_rows(), lang('fields_deleted') );

		return $this->fields($message);
	}
	/* END delete_field() */
  

	// --------------------------------------------------------------------

	/**
	 *	Save Rating Field
	 *
	 *	Validation, Error Reporting, Et cetera
	 *
	 *	@access		public
	 *	@return		string
	 */

	function save_field()
	{
		//--------------------------------------------  
		//	Crumbs and tab highlight
		//--------------------------------------------
		
		$this->add_crumb( (ee()->input->get_post('field_id') === FALSE) ? lang('add_field') : lang('edit_field') );
		$this->cached_vars['module_menu_highlight']	= 'module_fields';
	
		// --------------------------------------------
        //	Edit?  Edit of Locked Field?
        // --------------------------------------------
	
		$edit	= FALSE;
		$locked	= FALSE;
	
		if ( ee()->input->post('field_id') !== FALSE && ee()->input->post('field_id') != '')
		{
			$query = ee()->db->query("SELECT field_name FROM exp_rating_fields
									  WHERE field_id = '".ee()->db->escape_str(ee()->input->post('field_id'))."'
									  LIMIT 1");
									  
			if ($query->num_rows() > 0)
			{
				$edit = TRUE;
				
				if (in_array($query->row('field_name'), $this->locked_fields))
				{
					$locked = TRUE;
				}
			}
		}
	
		// --------------------------------------------
        //	Validation of Fields
        // --------------------------------------------
		
		if ( ee()->input->post('field_name') === FALSE OR trim(ee()->input->post('field_name')) == '')
		{
			return $this->error_page(lang('field_name_required'));
		}
		
		if ( ! ctype_alnum( str_replace('_', '', ee()->input->post('field_name'))))
		{
			return $this->error_page(lang('field_name_invalid'));
		}
		
		if ( ee()->input->post('field_label') === FALSE OR trim(ee()->input->post('field_label')) == '')
		{
			return $this->error_page(lang('field_label_required'));
		}
		
		if ( ee()->input->post('field_maxl') > 255 AND ee()->input->post('field_type') != 'textarea' )
		{
			return $this->error_page(lang('field_too_long'));
		}
		
		// --------------------------------------------
        //	Check for Duplicate on New
        // --------------------------------------------
        
		if ( $edit == FALSE)
		{
			$query	= ee()->db->query("SELECT COUNT(*) AS count FROM exp_rating_fields
									   WHERE field_name = '".ee()->db->escape_str(ee()->input->post('field_name'))."'");

			if ( $query->row('count') > 0 )
			{
				return $this->error_page( str_replace( '%name%', $this->output(ee()->input->post('field_name')), lang('field_name_exists') ) );
			}
		}

		// ----------------------------------------
		//	Prohibited Names
		// ----------------------------------------
		
		$exclude	= array('rating_id', 'entry_id', 'entry_title', 'rating_author_id', 'ip_address', 
							'collection', 'name', 'rating_date', 'edit_date', 'url', 'status', 'email', 
							'location', 'rating_review', 'notify', 'rating', 'review');

		if ( $locked === FALSE && in_array( strtolower( ee()->input->post('field_name') ), $exclude ) )
		{
			return $this->error_page( str_replace( '%name%', $this->output(ee()->input->post('field_name')), lang('reserved_field_name') ) );
		}
		
		// ----------------------------------------
		//	Check for prohibited substrings
		// ----------------------------------------

		if ( stristr( ee()->input->get_post('field_name'), '_avg' ) )
		{
			return $this->error_page( str_replace( '%name%', $this->output(ee()->input->post('field_name')), lang('reserved_field_substring') ) );
		}
		
		// ----------------------------------------
		//	Check for numeric length
		// ----------------------------------------
		
		if ( ! in_array(ee()->input->get_post('field_type'), array('number', 'text', 'textarea')))
		{
			return $this->error_page(lang('invalid_field_type_submitted'));
		}
		
		if ( ! ctype_digit( ee()->input->post('field_maxl') ) AND ee()->input->post('field_type') != 'textarea' )
		{
			return $this->error_page(lang('numeric_field_length_required'));
		}
		
		if ( ! ctype_digit( ee()->input->post('field_order') ) )
		{
			return $this->error_page(lang('numeric_field_order_required'));
		}
		
		if ( ee()->input->get_post('field_type') == 'number' AND ee()->input->post('field_maxl') > 10 )
		{
			return $this->error_page(lang('numeric_field_length_exceeded'));
		}
		
		if ( ee()->input->post('field_maxl') == '0' AND ee()->input->post('field_type') != 'textarea' )
		{
			return $this->error_page(lang('field_length_0'));
		}

		// ----------------------------------------
		//	Set field type
		// ----------------------------------------
		
		if ( ee()->input->get_post('field_type') == 'text' )
		{
			$field_type	= "VARCHAR(".ceil(ee()->input->get_post('field_maxl')).") NOT NULL DEFAULT ''";
		}
		elseif ( ee()->input->get_post('field_type') == 'number' )
		{
			if ( ee()->input->get_post('field_maxl') < 3 )
			{
				$field_type	= "TINYINT UNSIGNED NULL DEFAULT NULL";
			}
			else
			{
				$field_type	= "INT UNSIGNED NULL DEFAULT NULL";
			}
		}
		else
		{
			$field_type	= "TEXT NULL DEFAULT NULL";
		}
		
		// --------------------------------------------
        //	Create Data for Input
        // --------------------------------------------
        
        $data = array(	'field_label'	=> '',
						'field_name'	=> '',
						'field_type'	=> 'textarea',
						'field_fmt'		=> 'none',
						'field_order'	=> 1,
						'field_maxl'	=> 100);
						
		$data = array_intersect_key($_POST, $data);
		
		if ($locked === TRUE)
		{
			unset($data['field_label'], $data['field_name'], $data['field_type']);
		}
		else
		{
			$data['field_name'] = strtolower($data['field_name']);
		}
		
		// ----------------------------------------
		//	Update or Create?
		// ----------------------------------------
		
		if ( $edit === TRUE )
		{
			$query	= ee()->db->query("SELECT field_name FROM exp_rating_fields
									   WHERE field_id = '".ee()->db->escape_str(ee()->input->get_post('field_id'))."'");
			
			ee()->db->query( ee()->db->update_string('exp_rating_fields', $data, 'field_id = '.ee()->db->escape_str(ee()->input->get_post('field_id'))));

			ee()->db->query("ALTER TABLE exp_ratings
							 CHANGE `".$query->row('field_name')."` 
							 `".ee()->db->escape_str( ee()->input->get_post('field_name') )."` 
							 ".$field_type);

			$message	= lang('field_updated');
		}
		else
		{			
			ee()->db->query( ee()->db->insert_string('exp_rating_fields', $data) );
			
			$insert_id	= ee()->db->insert_id();

			ee()->db->query( "ALTER TABLE exp_ratings ADD ".ee()->db->escape_str( ee()->input->get_post('field_name') )." ".
							  $field_type);

			ee()->db->query( "ALTER TABLE exp_rating_stats ADD `".ee()->db->escape_str( 'count_'.$insert_id )."` ".
							 "INT UNSIGNED NULL DEFAULT NULL" );

			ee()->db->query( "ALTER TABLE exp_rating_stats ADD `".ee()->db->escape_str( 'sum_'.$insert_id )."` ".
							 "INT UNSIGNED NULL DEFAULT NULL" );

			ee()->db->query( "ALTER TABLE exp_rating_stats ADD `".ee()->db->escape_str( 'avg_'.$insert_id )."` ".
							 "FLOAT UNSIGNED NULL DEFAULT NULL" );

			$message	= lang('field_created');
		}
		
		// ----------------------------------------
		//  Return
		// ----------------------------------------

		return $this->fields($message);
	}
	/* END save_field() */
	
	// --------------------------------------------------------------------

	/**
	 *	Save Field Order
	 *
	 *	@access		public
	 *	@return		string
	 */
	
	public function save_field_order()
	{
		if (ee()->input->post('field_order') === FALSE OR ! is_array(ee()->input->post('field_order')))
		{
			return $this->home();
		}

		foreach ($_POST['field_order'] as $key => $val)
		{
			ee()->db->query(ee()->db->update_string( 'exp_rating_fields', array('field_order' => ceil($val)), array( 'field_id' => $key ) ) );
		}
	
		return $this->fields(lang('fields_updated'));
	}
	/* END save_field_order() */
	
	
	// --------------------------------------------------------------------

	/**
	 *	Maintenance Page in CP
	 *
	 *	Not quite sure what this is going to do yet.  Rating has gone through many revisions and
	 *  I suspect I am going to keep on changing things in my Bridge conversion -Paul
	 *
	 *	@access		public
	 *	@return		string
	 */
	
	function utilities()
	{
    	//--------------------------------------------  
		//	Crumbs and tab highlight
		//--------------------------------------------
		
		$this->add_crumb( lang('utilities') );
		$this->cached_vars['module_menu_highlight']	= 'module_utilities';
		
		// --------------------------------------------
        //	Limits and Totals for Processing
        // --------------------------------------------
        
        $start			= ( ee()->input->get_post('row') === FALSE ) ? TRUE : FALSE;
        $limit			= 50; // Number of entries/channels/members to process at a time.
		$total			= 0;
        
        if ($start !== TRUE)
        {
			$row			= ceil(ee()->input->get_post('row'));
			
			// --------------------------------------------
			//	Remove Stats for Non-Rated Entries
			// --------------------------------------------
			
			if ($row == 0)
			{
				ee()->db->query("DELETE FROM exp_ratings WHERE entry_id != 0 AND entry_id NOT IN (SELECT entry_id FROM {$this->sc->db->channel_titles})");
			
				ee()->db->query("DELETE FROM exp_rating_stats WHERE entry_id != 0 AND entry_id NOT IN (SELECT entry_id FROM exp_ratings)");
				
				ee()->db->query("DELETE FROM exp_rating_stats WHERE channel_id != 0 AND channel_id NOT IN (SELECT channel_id FROM exp_ratings)");
			}
			
			// --------------------------------------------
			//	Entry Statistics
			// --------------------------------------------
			
			$query			= ee()->db->query("SELECT COUNT(DISTINCT entry_id) AS total_count FROM exp_ratings");
			$query_row		= $query->row_array();
			$total			= $query_row['total_count'];
			
			$query			= ee()->db->query("SELECT DISTINCT entry_id FROM exp_ratings ORDER BY entry_id LIMIT {$row}, {$limit}");
			
			foreach($query->result_array() AS $data_row)
			{
				// How damn! Look at that abstraction, baby!
				$this->actions()->update_entry_stats($data_row['entry_id']);
			}
			
			// --------------------------------------------
			//	Member Statistics
			// --------------------------------------------
			
			$query			= ee()->db->query("SELECT COUNT(DISTINCT rating_author_id) AS total_count FROM exp_ratings");
			$query_row		= $query->row_array();
			$total			= ($total < $query_row['total_count']) ? $query_row['total_count'] : $total;
			
			$query			= ee()->db->query("SELECT DISTINCT rating_author_id FROM exp_ratings ORDER BY rating_author_id LIMIT {$row}, {$limit}");
			
			foreach($query->result_array() AS $data_row)
			{
				$this->actions()->update_member_stats($data_row['rating_author_id']);
			}
			
			// --------------------------------------------
			//	Channel/Weblog Statistics
			// --------------------------------------------
			
			$query			= ee()->db->query("SELECT COUNT(DISTINCT channel_id) AS total_count FROM exp_ratings");
			$query_row		= $query->row_array();
			$total			= ($total < $query_row['total_count']) ? $query_row['total_count'] : $total;
			
			$query			= ee()->db->query("SELECT DISTINCT channel_id FROM exp_ratings ORDER BY channel_id LIMIT {$row}, {$limit}");
			
			foreach($query->result_array() AS $data_row)
			{
				$this->actions()->update_channel_stats($data_row['channel_id']);
			}
		}
		
		// --------------------------------------------
        //	Form Variables
        // --------------------------------------------
        
        $this->cached_vars['start']			= $start;
		
		$this->cached_vars['row']			= ($start === TRUE) ? 0 : $row + $limit;		// Next DB row to start from, used in URL
		$this->cached_vars['total_done']	= ($start === TRUE) ? 0 : $row;					// Total DB rows completed
		$this->cached_vars['total']			= $total;										// Total DB rows to do
		
		$this->cached_vars['next_batch']	= ($start === TRUE) ? 1 : ceil($this->cached_vars['row']/$limit) + 1;	// Which batch are we on?
		$this->cached_vars['total_batches']	= ceil($total/$limit);							// Total batches to do.
		
		$lines = array(
			'utility',
			'options',
			'recount_description'
		);
				
		foreach ($lines as $line)
		{
			$this->cached_vars['lang_' . $line]	= lang($line);	
		}		
				
		// --------------------------------------------
        //  Load page
        // --------------------------------------------
        
        $this->build_crumbs();
		$this->build_right_links();

		$this->cached_vars['current_page'] = $this->view('utilities.html', NULL, TRUE);        
		return $this->ee_cp_view('index.html');		
	}
	/* END utilities() */


	// --------------------------------------------------------------------

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

    public function rating_module_install()
    {
       require_once $this->addon_path . 'upd.rating.php';
    	
    	$U = new Rating_updater_base();
    	return $U->install();
    }
	// END rating_module_install()   
    

	// --------------------------------------------------------------------

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

    public function rating_module_deinstall()
    {
       require_once $this->addon_path . 'upd.rating.php';
    	
    	$U = new Rating_updater_base();
    	return $U->uninstall();
    }
    // END rating_module_deinstall()


	// --------------------------------------------------------------------

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
    
    public function rating_module_update()
    {
    	if ( ! isset($_POST['run_update']) OR $_POST['run_update'] != 'y')
    	{
    		$this->add_crumb(lang('update_rating_module'));
    		$this->build_crumbs();
			$this->cached_vars['form_url'] = $this->base.'&method=rating_module_update';
			return $this->ee_cp_view('update_module.html');
		}
		
    	require_once $this->addon_path . 'upd.rating.base.php';
    	
    	$U = new Rating_updater_base();

    	if ($U->update() !== TRUE)
    	{
    		return $this->index(lang('update_failure'));
    	}
    	else
    	{
    		return $this->index(lang('update_successful'));
    	}
    }
    // END rating_module_update()

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
		$this->add_crumb(lang('error'));
		$this->cached_vars['error_message'] = $error;
		
		// -------------------------------------
		//  Output
		// -------------------------------------
		
		return $this->ee_cp_view('error_page.html');
	}
	// END error_page()
	
}
// END CLASS Rating