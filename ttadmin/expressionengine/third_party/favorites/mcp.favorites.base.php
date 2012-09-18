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
 * Favorites - Control Panel
 *
 * The Control Panel master class that handles all of the CP Requests and Displaying
 *
 * @package 	Solspace:Favorites
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/favorites/mcp.favorites.php
 */

require_once 'addon_builder/module_builder.php';

class Favorites_cp_base extends Module_builder_favorites 
{

	public $TYPE;

	public $return_data				= '';
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
	public $p_limit					= '';
	public $p_page					= '';
	public $basepath				= '';
	public $uristr					= '';

	public $messages				= array();
	public $mfields					= array();
	
	public $prefs					= array();
	
	public $clean_site_id			= 0;


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
		//$this->theme = 'flow_ui';
	
        parent::Module_builder_favorites('favorites');
        
        if ((bool) $switch === FALSE) return; // Install or Uninstall Request
        
		ee()->load->helper(array('text', 'form', 'url', 'security', 'string'));

		// --------------------------------------------
        //  Module Menu Items
        // --------------------------------------------
        
        $menu	= array(
			'module_home'			=> array(	
				'link'  => $this->base,
        		'title' => ee()->lang->line('homepage')
			),		
			'module_members'		=> array(	
				'link'  => $this->base . "&method=members",
        		'title' => ee()->lang->line('members')
			),
			'module_entries'		=> array(	
				'link'  => $this->base . "&method=entries",
        		'title' => ee()->lang->line('entries')
			),
			'module_preferences'	=> array(	
				'link'  => $this->base . "&method=preferences",
        		'title' => ee()->lang->line('preferences')
			),
			'module_documentation'	=> array(	
				'link'  => FAVORITES_DOCS_URL,
        		'title' => ee()->lang->line('online_documentation') . ((APP_VER < 2.0) ? ' (' . FAVORITES_VERSION . ')' : '')
			),
        );
        
        //$this->cached_vars['module_menu_highlight'] = 'module_home';
		$this->cached_vars['lang_module_version'] 	= ee()->lang->line('favorites_module_version');        
		$this->cached_vars['module_version'] 		= FAVORITES_VERSION;
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
		elseif($this->version_compare($this->database_version(), '<', FAVORITES_VERSION))
		{
			if (APP_VER < 2.0)
			{
				if ($this->favorites_module_update() === FALSE)
				{
					return;
				}
			}
			else
			{
				// For EE 2.x, we need to redirect the request to Update Routine
				$_GET['method'] = 'favorites_module_update';
			}
		}
		
		//saves a few function calls
		$this->clean_site_id = ee()->db->escape_str(ee()->config->item('site_id'));

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
        		$this->add_crumb(ee()->lang->line('invalid_request'));
        		$this->cached_vars['error_message'] = ee()->lang->line('invalid_request');
        		
        		return $this->ee_cp_view('error_page.html');
        	}
        	else
        	{
        		$this->{ee()->input->get('method')}();
        	}
        }
    }
    // END Favorites_cp_base()

	
	// --------------------------------------------------------------------
	// cp views
	// --------------------------------------------------------------------
	
	
	// --------------------------------------------------------------------

	/**
	 * Module's Main Homepage
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */
    
	public function index($message='')
    {
        if ($message == '' && isset($_GET['msg']))
        {
        	$message = ee()->lang->line($_GET['msg']);
        }
        
        $this->cached_vars['message'] = $message;
        
		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------
		
		$this->add_crumb(ee()->lang->line('homepage'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_home';
		//--------------------------------------
		//  lang		
		//--------------------------------------		

		$this->cached_vars['lang_no_entries_saved']			= ee()->lang->line('no_entries_saved');
		$this->cached_vars['lang_statistics']				= ee()->lang->line('statistics');
		$this->cached_vars['lang_total_favorites']			= str_replace(' ', '&nbsp;', ee()->lang->line('total_favorites'));
		$this->cached_vars['lang_total_entries_saved']		= str_replace(' ', '&nbsp;', ee()->lang->line('total_entries_saved'));
		$this->cached_vars['lang_percent_entries_saved']	= str_replace(' ', '&nbsp;', ee()->lang->line('percent_entries_saved'));
		$this->cached_vars['lang_top_5']					= ee()->lang->line('top_5');

		//--------------------------------------
		//  data		
		//--------------------------------------

		$favorites	= ee()->db->query( 
			"SELECT COUNT(*) AS count 
			 FROM 	exp_favorites 
			 WHERE 	site_id = '{$this->clean_site_id}'" 
		);

		$total_favorites = $favorites->row('count');

		$t_entries	= ee()->db->query( 
			"SELECT COUNT(*) AS count 
			 FROM 	{$this->sc->db->channel_titles} 
			 WHERE  site_id = '{$this->clean_site_id}' 
			 AND 	favorites_count != 0" 
		);

		$t_entries	= $t_entries->row('count');

		$entries	= ee()->db->query( 
			"SELECT COUNT(*) AS count 
			 FROM 	{$this->sc->db->channel_titles} 
			 WHERE 	site_id = '{$this->clean_site_id}'" 
		);

		$p_entries	= ( $entries->row('count') != 0 ) ? 
						round( $t_entries / $entries->row('count') * 100, 2) : 0;

		$top5		= ee()->db->query( 
			"SELECT 	t.title, COUNT(*) AS count 
			 FROM 		exp_favorites	AS f 
			 LEFT JOIN 	{$this->sc->db->channel_titles}	AS t 
			 ON 		f.entry_id 		= t.entry_id 
			 WHERE 		f.site_id 		= '{$this->clean_site_id}' 
			 GROUP BY 	f.entry_id 
			 ORDER BY 	count DESC 
			 LIMIT 5" 
		);

		$ranked		= array();

		if ( $top5->num_rows() > 0 )
		{
			foreach ( $top5->result_array() as $row )
			{
				$ranked[] = $row['title'];
			}
		}

		$this->cached_vars['version']				= FAVORITES_VERSION;
		$this->cached_vars['total_favorites']		= $total_favorites;
		$this->cached_vars['t_entries']				= $t_entries;
		$this->cached_vars['p_entries']				= $p_entries . NBS . '%';
		$this->cached_vars['ranked']				= $ranked;
		
		$this->cached_vars['current_page']			= $this->view('home.html', NULL, TRUE);
		
		// --------------------------------------------
        //  Load Homepage
        // --------------------------------------------
        
		return $this->ee_cp_view('index.html');
	}
	// END index()


	// --------------------------------------------------------------------

	/**
	 * Module's members page
	 *
	 * @access	public
	 * @param	string message
	 * @return	null
	 */
    
	public function members($message='')
    {
        if ($message == '' && isset($_GET['msg']))
        {
        	$message = ee()->lang->line($_GET['msg']);
        }
        
        $this->cached_vars['message'] = $message;
        
		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------
		
		$this->add_crumb(ee()->lang->line('members'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_members';

		//--------------------------------------
		//  lang		
		//--------------------------------------		

		$this->cached_vars['lang_members']					= ee()->lang->line('members');
		$this->cached_vars['lang_no_favorites']				= ee()->lang->line('no_favorites');
		$this->cached_vars['lang_member']					= ee()->lang->line('member');
		$this->cached_vars['lang_total_favorites_saved']	= ee()->lang->line('total_favorites_saved');

		//--------------------------------------
		//  data		
		//--------------------------------------

		$row_limit		= 50;
	    $paginate		= '';
	    $row_count		= 0;
		$member_data	= array();
		
		//get all members and favs associated with
		$sql	= "SELECT 		f.*, m.screen_name, COUNT(*) AS count 
				   FROM 		exp_favorites 	AS f 
				   LEFT JOIN 	exp_members 	AS m 
				   ON 			m.member_id 	=  f.member_id 
				   WHERE 		site_id 		=  '{$this->clean_site_id}' 
				   GROUP BY 	f.member_id 
				   ORDER BY 	count DESC";

		$query	= ee()->db->query($sql);

		//no entries? gtfo
		if ( $query->num_rows() == 0 )
		{
			$this->cached_vars['member_data']			= $member_data;
			$this->cached_vars['paginate']				= $paginate;
			$this->cached_vars['current_page']			= $this->view('members.html', NULL, TRUE);
			return $this->ee_cp_view('index.html');
		}

		//  Paginate?
		if ( $query->num_rows() > $row_limit )
		{
			$row_count		= ( ! ee()->input->get_post('row')) ? 
								0 : ee()->input->get_post('row');

			ee()->load->library('pagination');

			$config['base_url'] 			= $this->base . AMP . 'method=members';
			$config['total_rows'] 			= $query->num_rows();
			$config['per_page'] 			= $row_limit;
			$config['page_query_string'] 	= TRUE;
			$config['query_string_segment'] = 'row';

			ee()->pagination->initialize($config);

			$paginate 		= ee()->pagination->create_links();

			$sql			.= " LIMIT $row_count, $row_limit";

			$query			= ee()->db->query($sql);    
		}
		
		//load member data
		foreach ($query->result_array() as $row)
		{
			$item	= array();
			
			$item['row_count']	= ++$row_count;
			$item['name']		= $row['screen_name'];
			$item['url']		= ( $row['count'] == 0 ) ? FALSE : $this->base . 
																   AMP . 'method=member' . 
																   AMP . 'member_id=' . $row['member_id'];
			$item['fav_count']	= $row['count'];
			
			$member_data[]	= $item;
		}
		
		//PEW PEW PEW (cache data for view)
		$this->cached_vars['member_data']			= $member_data;
		$this->cached_vars['paginate']				= $paginate;
		
		$this->cached_vars['current_page']			= $this->view('members.html', NULL, TRUE);
		
		// --------------------------------------------
        //  Load Homepage
        // --------------------------------------------
        
		return $this->ee_cp_view('index.html');
	}
	// END members()


	// --------------------------------------------------------------------

	/**
	 * Module's individual member page
	 *
	 * @access	public
	 * @param	string message
	 * @return	null
	 */
   	public function member($message='')
    {
        // -------------------------------------------
		//  Member id?
		// -------------------------------------------

		if ( ! ee()->input->get_post('member_id') )
		{
			return $this->show_error('no_member_id');
		}

		// -------------------------------------------
		//  Message
		// -------------------------------------------
		
		if ($message == '' && isset($_GET['msg']))
        {
        	$message = ee()->lang->line($_GET['msg']);
        }
        
        $this->cached_vars['message'] = $message;
        
		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------
		
		$this->add_crumb(ee()->lang->line('members'), $this->base . AMP . 'method=members');
		$this->add_crumb(ee()->lang->line('member'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_members';

		//--------------------------------------
		//  lang		
		//--------------------------------------		

		$this->cached_vars['lang_members']					= ee()->lang->line('members');
		$this->cached_vars['lang_no_member']				= ee()->lang->line('no_member');
		$this->cached_vars['lang_member']					= ee()->lang->line('member');
		$this->cached_vars['lang_total_favorites_saved']	= ee()->lang->line('total_favorites_saved');
		$this->cached_vars['lang_title']					= ee()->lang->line('title');
		$this->cached_vars['lang_entry_date']				= ee()->lang->line('entry_date');
		$this->cached_vars['lang_favorites']				= ee()->lang->line('favorites');
		$this->cached_vars['lang_member_id']				= ee()->lang->line('member_id');
		$this->cached_vars['lang_name']						= ee()->lang->line('name');
		$this->cached_vars['lang_join_date']				= ee()->lang->line('join_date');

		//--------------------------------------
		//  data		
		//--------------------------------------

		$row_limit		= 50;
	    $paginate		= '';
	    $row_count		= 0;
		$member_data	= array();
		$member_id		= ee()->db->escape_str(ee()->input->get_post('member_id'));
		$member_stats 	= array();
		
		//individual member stats?
		$stat_query	= ee()->db->query( 
			"SELECT member_id, screen_name, join_date 
			 FROM 	exp_members
			 WHERE 	member_id = '$member_id'
			 LIMIT 	1" 
		);
		
		if ($stat_query->num_rows() > 0)
		{
			$stat_query_result 			= $stat_query->result_array();
			$member_stats 				= $stat_query_result[0];
			
			//make date readable
			$member_stats['join_date'] 	= ee()->localize->set_human_time( $member_stats['join_date'] );
		}
		
		//get all members and favs associated with
		$sql	= "SELECT 		f.*, t.title 
				   FROM 		exp_favorites AS f 
				   LEFT JOIN 	{$this->sc->db->channel_titles} AS t 
				   ON 			f.entry_id 	= t.entry_id 
				   WHERE 		f.site_id 	= '{$this->clean_site_id}' 
				   AND 			f.member_id	= '$member_id' 
				   GROUP BY 	f.entry_id 
				   ORDER BY 	f.entry_date DESC";

		$query	= ee()->db->query($sql);

		//no data? kill here
		if ( $query->num_rows() == 0 )
		{
			$this->cached_vars['member_stats']			= $member_stats;
			$this->cached_vars['member_data']			= $member_data;
			$this->cached_vars['paginate']				= $paginate;
			$this->cached_vars['current_page']			= $this->view('members.html', NULL, TRUE);
			return $this->ee_cp_view('index.html');
		}

		//  Paginate?
		if ( $query->num_rows() > $row_limit )
		{
			$row_count		= ( ! ee()->input->get_post('row')) ? 
								0 : ee()->input->get_post('row');

			ee()->load->library('pagination');

			$config['base_url'] 			= $this->base . AMP . 'method=member' . 
															AMP . 'member_id=' . $member_id;
			$config['total_rows'] 			= $query->num_rows();
			$config['per_page'] 			= $row_limit;
			$config['page_query_string'] 	= TRUE;
			$config['query_string_segment'] = 'row';

			ee()->pagination->initialize($config);

			$paginate 		= ee()->pagination->create_links();

			$sql			.= " LIMIT $row_count, $row_limit";

			$query			= ee()->db->query($sql);    
		}
		
		//load favorites data
		foreach ($query->result_array() as $row)
		{
			$item	= array();
			
			$item['row_count']	= ++$row_count;
			$item['url']		= $this->base . AMP .'method=entry' . AMP . 'entry_id=' . $row['entry_id'];
			$item['title']		= $row['title'];
			$item['entry_date']	= ee()->localize->set_human_time( $row['entry_date'] );
							
			$member_data[]	= $item;
		}
		
		//PEW PEW PEW (cache data for view)
		$this->cached_vars['member_stats']			= $member_stats;
		$this->cached_vars['member_data']			= $member_data;
		$this->cached_vars['paginate']				= $paginate;
		
		$this->cached_vars['current_page']			= $this->view('member.html', NULL, TRUE);
		
		// --------------------------------------------
        //  Load Homepage
        // --------------------------------------------
        
		return $this->ee_cp_view('index.html');
	}
	// END member()


	// --------------------------------------------------------------------

	/**
	 * favorites entries
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */
    
	public function entries($message='')
    {
        if ($message == '' && isset($_GET['msg']))
        {
        	$message = ee()->lang->line($_GET['msg']);
        }
        
        $this->cached_vars['message'] = $message;
        
		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------
		
		$this->add_crumb(ee()->lang->line('entries'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_entries';
		
		//--------------------------------------
		//  lang		
		//--------------------------------------		

		$this->cached_vars['lang_no_entries']				= ee()->lang->line('no_entries');
		$this->cached_vars['lang_no_favorites']				= ee()->lang->line('no_favorites');
		$this->cached_vars['lang_entries']					= ee()->lang->line('entries');
		$this->cached_vars['lang_title']					= ee()->lang->line('title');
		$this->cached_vars['lang_total_favorites']			= ee()->lang->line('total_favorites');

		//--------------------------------------
		//  data		
		//--------------------------------------
		
		$row_limit		= 50;
	    $paginate		= '';
	    $row_count		= 0;
		$entries   		= array();
	    
		// -------------------------------------------
		//  Query
		// -------------------------------------------

		$sql	= "SELECT 		f.*, t.title, COUNT(*) AS count 
				   FROM 		exp_favorites AS f 
				   LEFT JOIN 	{$this->sc->db->channel_titles} AS t 
				   ON 			t.entry_id 	= f.entry_id 
				   WHERE 		f.site_id 	= '{$this->clean_site_id}'
				   GROUP BY 	f.entry_id 
				   ORDER BY 	count DESC";
		
		$query	= ee()->db->query($sql);

		//no data? kill here
		if ( $query->num_rows() == 0 )
		{
			$this->cached_vars['entries']			= $entries;
			$this->cached_vars['paginate']			= $paginate;
			$this->cached_vars['current_page']		= $this->view('entries.html', NULL, TRUE);
			return $this->ee_cp_view('index.html');
		}
		
		//  Paginate?
		if ( $query->num_rows() > $row_limit )
		{
			$row_count		= ( ! ee()->input->get_post('row')) ? 
								0 : ee()->input->get_post('row');

			ee()->load->library('pagination');

			$config['base_url'] 			= $this->base . AMP . 'method=entries';
			$config['total_rows'] 			= $query->num_rows();
			$config['per_page'] 			= $row_limit;
			$config['page_query_string'] 	= TRUE;
			$config['query_string_segment'] = 'row';

			ee()->pagination->initialize($config);

			$paginate 		= ee()->pagination->create_links();

			$sql			.= " LIMIT $row_count, $row_limit";

			$query			= ee()->db->query($sql);    
		}

		foreach ( $query->result_array() as $row )
		{			
			// The Entry Got Deleted Somehow
			// Likely through an API or a Weblog Being Axed
			// So, we remove it from the Favorites table and move on.

			if ($row['title'] == NULL)
			{
				ee()->db->query( 
					"DELETE FROM 	exp_favorites 
					 WHERE 			entry_id = '" . ee()->db->escape_str( $row['entry_id'] ) . "'" 
				);
				continue;
			}

			$item				= array();

			$item['row_count']	= ++$row_count;
			$item['title']		= $row['title'];
			if ( $row['count'] != 0 )
			{
				$item['url']		= $this->base . AMP . 'method=entry' . 
													AMP . 'entry_id=' . $row['entry_id'];
			}
			$item['count']		= $row['count'];
			
			$entries[]			= $item;
		}
		
		$this->cached_vars['entries']				= $entries;
		$this->cached_vars['paginate']				= $paginate; 
		$this->cached_vars['current_page']			= $this->view('entries.html', NULL, TRUE);
		
		// --------------------------------------------
        //  Load Homepage
        // --------------------------------------------
        
		return $this->ee_cp_view('index.html');
	}
	// END entries()


	// --------------------------------------------------------------------

	/**
	 * favorites entry
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */
    
	public function entry($message='')
    {
        // -------------------------------------------
		//  entry id?
		// -------------------------------------------

		if ( ! ee()->input->get_post('entry_id') )
		{
			return $this->show_error('no_entry_id');
		}
		
		//message
        if ($message == '' && isset($_GET['msg']))
        {
        	$message = ee()->lang->line($_GET['msg']);
        }
        
        $this->cached_vars['message'] = $message;

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------
	
		$this->add_crumb(ee()->lang->line('entries'), $this->base . AMP . 'method=entries');		
		$this->add_crumb(ee()->lang->line('entry'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_entries';
		
		//--------------------------------------
		//  lang		
		//--------------------------------------		

		$this->cached_vars['lang_no_entries']			= ee()->lang->line('no_entries');
		$this->cached_vars['lang_entry']				= ee()->lang->line('entry');
		$this->cached_vars['lang_entry_id']				= ee()->lang->line('entry_id');
		$this->cached_vars['lang_title']				= ee()->lang->line('title');
		$this->cached_vars['lang_author']				= ee()->lang->line('author');
		$this->cached_vars['lang_entry_date']			= ee()->lang->line('entry_date');
		$this->cached_vars['lang_no_entry']				= ee()->lang->line('no_entry');
		$this->cached_vars['lang_favorites']			= ee()->lang->line('favorites');
		$this->cached_vars['lang_member']				= ee()->lang->line('member');
		$this->cached_vars['lang_entry_date']			= ee()->lang->line('entry_date');        
		
		//--------------------------------------
		//  data		
		//--------------------------------------
		
		$row_limit		= 50;
	    $paginate		= '';
	    $row_count		= 0;
		$entry_id		= ee()->db->escape_str(ee()->input->get_post('entry_id'));
		$entry_data		= array();
		$favorites		= array();
	    
		// -------------------------------------------
		//  Query
		// -------------------------------------------

		$entry_query = ee()->db->query(
			"SELECT DISTINCT	t.*, m.screen_name 
			 FROM 				{$this->sc->db->channel_titles} AS t 
			 LEFT JOIN 			exp_members AS m 
			 ON 				t.author_id = m.member_id 
			 WHERE 				t.entry_id 	= '$entry_id'"
		);
		
		if ( $entry_query->num_rows() > 0 )
		{
			$entry_result				= $entry_query->result_array();
			$entry_data 				= $entry_result[0];
			
			//make date readable
			$entry_data['entry_date'] 	= ee()->localize->set_human_time( $entry_data['entry_date'] );
		}

		// -------------------------------------------
		//  favorites
		// -------------------------------------------

		$sql	= "SELECT 		f.*, m.screen_name, COUNT(*) AS count 
				   FROM 		exp_favorites 	AS f 
				   LEFT JOIN 	exp_members 	AS m 
				   ON 			f.member_id		= m.member_id 
				   WHERE 		f.entry_id 		= '$entry_id' 
				   GROUP BY 	m.member_id 
				   ORDER BY 	f.entry_date 	DESC";

		$query	= ee()->db->query($sql);

		//no data? kill here
		if ( $query->num_rows() == 0 )
		{
			$this->cached_vars['favorites']			= $favorites;
			$this->cached_vars['entry_data']		= $entry_data;
			$this->cached_vars['current_page']		= $this->view('entry.html', NULL, TRUE);
			return $this->ee_cp_view('index.html');
		}
		
		//  Paginate?
		if ( $query->num_rows() > $row_limit )
		{
			$row_count		= ( ! ee()->input->get_post('row')) ? 
								0 : ee()->input->get_post('row');

			ee()->load->library('pagination');

			$config['base_url'] 			= $this->base . AMP . 'method=entry' . 
															AMP . 'entry_id=' . $entry_id;
			$config['total_rows'] 			= $query->num_rows();
			$config['per_page'] 			= $row_limit;
			$config['page_query_string'] 	= TRUE;
			$config['query_string_segment'] = 'row';

			ee()->pagination->initialize($config);

			$paginate 		= ee()->pagination->create_links();

			$sql			.= " LIMIT $row_count, $row_limit";

			$query			= ee()->db->query($sql);    
		}

		foreach ( $query->result_array() as $row )
		{
			$item					= array();
			
			$item['row_count']		= ++$row_count;
			$item['url'] 			= $this->base . AMP . 'method=member' . AMP . 'member_id=' . $row['member_id']; 			
			$item['screen_name']	= $row['screen_name'];
			$item['entry_date']		= ee()->localize->set_human_time( $row['entry_date'] );

			$favorites[]			= $item;
		}
		
		$this->cached_vars['favorites']			= $favorites;
		$this->cached_vars['entry_data']		= $entry_data;
		$this->cached_vars['paginate']			= $paginate;
		$this->cached_vars['current_page']		= $this->view('entry.html', NULL, TRUE);
		
		// --------------------------------------------
        //  Load Homepage
        // --------------------------------------------
        
		return $this->ee_cp_view('index.html');
	}
	// END entry()


	// --------------------------------------------------------------------

	/**
	 * favorites preferences
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */
    
	public function preferences($message='')
    {
		//message
        if ($message == '' && isset($_GET['msg']))
        {
        	$message = ee()->lang->line($_GET['msg']);
        }
        
        $this->cached_vars['message'] = $message;

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------
			
		$this->add_crumb(ee()->lang->line('preferences'));
		$this->build_crumbs();
		$this->cached_vars['module_menu_highlight']	= 'module_preferences';
		
		//--------------------------------------
		//  lang		
		//--------------------------------------		
		$site_label	= $this->check_yes(ee()->config->item('multiple_sites_enabled')) ? 
								ee()->config->item('site_label') . ' :: ' : '';
								
		$this->cached_vars['lang_header']				= $site_label . ee()->lang->line('preferences');
		$this->cached_vars['lang_preferences']			= ee()->lang->line('preferences');
		$this->cached_vars['lang_pref_name']			= ee()->lang->line('pref_name');
		$this->cached_vars['lang_pref_value']			= ee()->lang->line('pref_value');
		$this->cached_vars['lang_update']				= ee()->lang->line('update');     
		$this->cached_vars['lang_add_favorite']			= ee()->lang->line('add_favorite');     
		$this->cached_vars['lang_yes']					= ee()->lang->line('yes');     
		$this->cached_vars['lang_no']					= ee()->lang->line('no');
		$this->cached_vars['lang_general_preferences']	= ee()->lang->line('general_preferences');     
		
		//--------------------------------------
		//  data		
		//--------------------------------------

	    $row_count		= 0;
	    $prefs			= array();
		
		
		$query	= ee()->db->query(
			"SELECT * 
			 FROM 	exp_favorites_prefs 
			 WHERE 	site_id = '{$this->clean_site_id}'"
		);
		
		if ($query->num_rows() == 0)
		{
			$this->data->set_default_site_prefs($this->clean_site_id);
			
			$query	= ee()->db->query(
				"SELECT * 
				 FROM 	exp_favorites_prefs 
				 WHERE 	site_id = '{$this->clean_site_id}'"
			);
		}
	
		//don't want these shown
		$exclude	= array('pref_id','member_id', 'site_id', 'language', 'add_favorite');

		//everything else is ok, though
		foreach ( $query->row() as $key => $value )
		{
			if ( ! in_array( $key, $exclude ) )
			{
				$item					= array();
				
				$item['row_count']		= ++$row_count;
				$item['pref_lang']		= ee()->lang->line($key);
				$item['pref_name']		= $key;
				$item['pref_value']		= $value;

				$prefs[]				= $item;
			}
		}	
		
		$hidden_values	= array(
			'pref_id'	=> $query->row('pref_id'),
			'site_id'	=> $query->row('site_id'),
			'language'	=> $query->row('language'),
			'member_id' => ee()->session->userdata['member_id']
		);

		$selected 								= ' checked="checked" ';
		$this->cached_vars['add_favorite_yes'] 	= ($query->row('add_favorite') == 'y') ?
		 												$selected : '';
	    $this->cached_vars['add_favorite_no'] 	= ($query->row('add_favorite') != 'y') ?
														$selected : '';
		
		$this->cached_vars['prefs']				= $prefs;
		$this->cached_vars['hidden_values']		= $hidden_values;
		$this->cached_vars['form_url']			= $this->base . AMP . 'method=update_preferences';
		$this->cached_vars['current_page']		= $this->view('preferences.html', NULL, TRUE);
		
		// --------------------------------------------
        //  Load Homepage
        // --------------------------------------------
        
		return $this->ee_cp_view('index.html');
	}
	// END preferences()
	
	
	// --------------------------------------------------------------------
	// END cp views
	// --------------------------------------------------------------------
	

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

    public function favorites_module_install()
    {
       require_once $this->addon_path . 'upd.favorites.php';
    	
    	$U = new Favorites_updater_base();
    	return $U->install();
    }
	// END favorites_module_install()   
    

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

    public function favorites_module_deinstall()
    {
       require_once $this->addon_path . 'upd.favorites.php';
    	
    	$U = new Favorites_updater_base();
    	return $U->uninstall();
    }
    // END favorites_module_deinstall()


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
    
    public function favorites_module_update()
    {
    	if ( ! isset($_POST['run_update']) OR $_POST['run_update'] != 'y')
    	{
    		$this->add_crumb(ee()->lang->line('update_favorites_module'));
    		$this->build_crumbs();
			$this->cached_vars['form_url'] = $this->base . '&msg=update_successful';
			return $this->ee_cp_view('update_module.html');
		}
		
    	require_once $this->addon_path . 'upd.favorites.php';
    	
    	$U = new Favorites_updater_base();

    	if ($U->update() !== TRUE)
    	{
    		return $this->index(ee()->lang->line('update_failure'));
    	}
    	else
    	{
    		return $this->index(ee()->lang->line('update_successful'));
    	}
    }
    // END favorites_module_update()
	

	// --------------------------------------------------------------------

	/**
	 * update preferences
	 *
	 * @access	public
	 * @return	null
	 */   

    public function update_preferences()
    {
		// -------------------------------------------
		//	Validate
		// -------------------------------------------

		/*if ( ! ee()->input->get_post('pref_id') 		OR 
			 ! is_numeric( ee()->input->get_post('pref_id') ) )
        {
			return $this->show_error('field_required');
        }*/

		// -------------------------------------------
		//	Prep data
		// -------------------------------------------

		$query	= ee()->db->query(
			"SELECT * 
			 FROM 	exp_favorites_prefs 
			 WHERE 	site_id = '{$this->clean_site_id}'"
		);

		$post = array();

		foreach ( $query->row() as $key => $val)
		{			
			$post_key = ee()->input->post($key, TRUE);

			if ( $key != 'pref_id' AND 
				 ! in_array($post_key, array(FALSE, ''), TRUE))
			{
				//make sure that add_favorite is correct
				if ($key == 'add_favorite' AND 
					$post_key !== 'n')
				{
					$post[$key] = 'y';
					continue;
				}
				
				$post[$key]	= $post_key;
			}
		}

		// -------------------------------------------
		//	Update
		// -------------------------------------------

		ee()->db->query( 
			ee()->db->update_string(
				'exp_favorites_prefs', 
				$post, 
				array( 
					'site_id' => $this->clean_site_id 
				) 
			) 
		);

		$message	= ee()->lang->line('prefs_updated');

        return $this->preferences($message);
    }

    //	End update prefs

	
	//---------------------------------------------------------------------

	/**
	 * show_error
	 * @access	public
	 * @param	(string) error string
	 * @param	(bool) 	 is the string a lang pointer?
	 * @return	(string) returns html string of error page
	 */

	public function show_error($str, $do_lang = TRUE)
	{
		$this->cached_vars['error_message'] = $do_lang ? ee()->lang->line($str) : $str;
		return $this->ee_cp_view('error_page.html');
	}
	//END show_error
	

	//---------------------------------------------------------------------

	/**
	 * _build_right_link 
	 * @access	public
	 * @param	(string)	lang string
	 * @param	(string)	html link for right link
	 * @return	(null)
	 */
	
	function _build_right_link($lang_line, $link)
	{	
		$msgs 		= array();
		$links 		= array();
		$ee2_links 	= array();
		
		if (is_array($lang_line))
		{
			for ($i = 0, $l= count($lang_line); $i < $l; $i++)
			{
				if (APP_VER < 2.0)
				{
					$msgs[$i]	= ee()->lang->line($lang_line[$i]);
					$links[$i]	= $link[$i];
				}
				else
				{
					$ee2_links[$lang_line[$i]] = $link[$i];
				}
			} 
		}
		else
		{
			if (APP_VER < 2.0)
			{
				$msgs[]		= ee()->lang->line($lang_line);
				$links[]	= $link;
			}
			else
			{
				$ee2_links[$lang_line] = $link;
			}
		}
					
		if (APP_VER < 2.0)
		{
			$this->cached_vars['right_crumb_msg']		= $msgs;
			$this->cached_vars['right_crumb_link'] 		= $links;
		}
		else
		{
			ee()->cp->set_right_nav($ee2_links);
		}
	}
	// END _build_right_link()
	
	//---------------------------------------------------------------------	
}
// END CLASS Favorites