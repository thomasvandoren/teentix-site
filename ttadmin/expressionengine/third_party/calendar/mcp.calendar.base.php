<?php if ( ! defined('EXT')) exit('No direct script access allowed');

 /**
 * Solspace - Calendar
 *
 * @package		Solspace:Calendar
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2010-2012, Solspace, Inc.
 * @link		http://www.solspace.com/docs/addon/c/Calendar/
 * @version		1.7.0
 * @filesource 	./system/expressionengine/third_party/calendar/
 */

 /**
 * Calendar - Control Panel
 *
 * The Control Panel master class that handles all of the CP Requests and Displaying
 *
 * @package 	Solspace:Calendar
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/calendar/mcp.calendar.base.php
 */

if ( ! class_exists('Module_builder_calendar'))
{
	require_once 'addon_builder/module_builder.php';	
}

class Calendar_cp_base extends Module_builder_calendar
{
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
	    parent::Module_builder_calendar('calendar');
        
		if ((bool) $switch === FALSE) return; // Install or Uninstall Request

		// -------------------------------------
		//  We need our actions
		// -------------------------------------

		$this->actions();

		if (! defined('CALENDAR_CALENDARS_CHANNEL_NAME'))
		{
			define('CALENDAR_CALENDARS_CHANNEL_NAME', $this->actions->calendar_channel_shortname());
			define('CALENDAR_EVENTS_CHANNEL_NAME', $this->actions->event_channel_shortname());
		}

		// --------------------------------------------
		//  Module Menu Items
		// --------------------------------------------

		$menu	= array(
			'view_calendars'		=> array(	
				'link'  => $this->base . AMP . 'method=view_calendars',
				'title' => ee()->lang->line('calendars')
			),
			'view_events'			=> array(	
				'link'  => $this->base . AMP . 'method=view_events',
				'title' => ee()->lang->line('events')
			),
			/*
			'view_reminders'		=> array(	'link'  => $this->base.AMP.'method=view_reminders',
												'title' => ee()->lang->line('reminders')),
			*/
			'permissions'		=> array(	
				'link'  => $this->base.AMP.'method=permissions',
				'title' => ee()->lang->line('permissions')
			),
			'preferences'			=> array(	
				'link' 	=> $this->base . AMP . 'method=view_preferences',
				'title' => ee()->lang->line('preferences')
			),
			'module_documentation'	=> array(	
				'link'  => CALENDAR_DOCS_URL,
				'title' => ee()->lang->line('online_documentation') . 
					((APP_VER < 2.0) ? ' (' . CALENDAR_VERSION . ')' : ''),
				'new_window' => TRUE
			),
		);

		//no permissions for you!
		if ( APP_VER < 2.0)
		{
			unset($menu['permissions']);
		}

		$this->cached_vars['lang_module_version'] 	= ee()->lang->line('calendar_module_version');        
		$this->cached_vars['module_version'] 		= CALENDAR_VERSION;
		$this->cached_vars['module_menu_highlight']	= 'view_calendars';
		$this->cached_vars['module_menu']			= $menu;
		//needed for header.html file views
		$this->cached_vars['js_magic_checkboxes']	= $this->js_magic_checkboxes();

		// --------------------------------------------
		//  Sites
		// --------------------------------------------

		$this->cached_vars['sites']	= array();

		foreach($this->data->get_sites() as $site_id => $site_label)
		{
			$this->cached_vars['sites'][$site_id] = $site_label;
		}

		// -------------------------------------
		//  We need our actions
		// -------------------------------------

		$this->actions();

		// -------------------------------------
		//  Module Installed and What Version?
		//	added an extensions test
		// -------------------------------------

		$updated = FALSE;

		if ($this->database_version() == FALSE)
		{
			return;
		}
		elseif($this->version_compare(
				$this->database_version(), 
				'<', 
				CALENDAR_VERSION
			) OR 
			! $this->extensions_enabled()
		)
		{
			if (APP_VER < 2.0)
            {
                if ($this->calendar_module_update() === FALSE)
                {
                    return;
                }
            }
            else
            {
                // For EE 2.x, we need to redirect the request to Update Routine
                $_GET['method'] = 'calendar_module_update';
            }

			$updated = TRUE;
		}

		// -------------------------------------
		//  Grab the MOD file and related goodies
		// -------------------------------------

		if ( ! class_exists('Calendar'))
		{
			require_once CALENDAR_PATH.'mod.calendar'.EXT;
		}

		$this->CAL = new Calendar($updated);
		$this->CAL->load_calendar_datetime();

		// -------------------------------------
		//  Request and View Builder
		// -------------------------------------

        if (APP_VER < 2.0 AND $switch !== FALSE)
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
    // END Calendar_cp_base()

	// --------------------------------------------------------------------

	public function index($message='')
	{
		return $this->view_calendars($message);
	}
	/* END index() */

	// --------------------------------------------------------------------

	/**
	 * View Calendars
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */

	public function view_calendars($message='')
	{
		if ($message == '' AND isset($_GET['msg']))
		{
			$message = ee()->lang->line($_GET['msg']);
		}
        
		$this->cached_vars['message'] = $message;

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------

		$this->add_crumb(ee()->lang->line('calendars'));
		$this->build_crumbs();

		// -------------------------------------
		//  What should we show?
		// -------------------------------------

		$this->cached_vars['calendars'] = $this->data->calendar_basics();

		$this->cached_vars['current_page'] = $this->view('calendars.html', NULL, TRUE);

		// --------------------------------------------
		//  Load Homepage
		// --------------------------------------------
        
		return $this->ee_cp_view('index.html');
	}
	/* END view_calendars() */

	// --------------------------------------------------------------------

	/**
	 * View Events
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */

	public function view_events($message='')
	{
		// -------------------------------------
		//  Delete events?
		// -------------------------------------

		if (ee()->input->post('toggle') !== FALSE AND ee()->input->post('delete_confirm') != '')
		{
			return $this->delete_events_confirm();
		}
		elseif (ee()->input->post('delete') !== FALSE AND is_array(ee()->input->post('delete')) AND count(ee()->input->post('delete')) > 0)
		{
			$this->delete_events();
		}

		if ($message == '' AND isset($_GET['msg']))
		{
			$message = ee()->lang->line($_GET['msg']);
		}

		$this->cached_vars['message'] = $message;
		$this->cached_vars['module_menu_highlight'] = 'view_events';

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------

		$this->add_crumb(ee()->lang->line('events'));
		$this->build_crumbs();

		// -------------------------------------
		//  Data for the view(s)
		// -------------------------------------

		$this->cached_vars['calendar']	= (ee()->input->post('calendar')) ? ee()->input->post('calendar') : '';
		$this->cached_vars['status']	= (ee()->input->post('status')) ? ee()->input->post('status') : '';
		$this->cached_vars['recurs']	= (ee()->input->post('recurs')) ? ee()->input->post('recurs') : '';
		$this->cached_vars['date']		= (ee()->input->post('date')) ? ee()->input->post('date') : '';
		$this->cached_vars['direction']	= (ee()->input->post('date_direction')) ? ee()->input->post('date_direction') : '';
		$this->cached_vars['orderby']	= (ee()->input->post('orderby')) ? ee()->input->post('orderby') : 'title';
		$this->cached_vars['sort']		= (ee()->input->post('sort')) ? ee()->input->post('sort') : 'ASC';
		$this->cached_vars['offset']	= (ee()->input->get_post('offset')) ? ee()->input->get_post('offset') : 0;
		$this->cached_vars['limit']		= (ee()->input->get_post('limit')) ? ee()->input->get_post('limit') : 100;

		$this->cached_vars['calendars']	= $this->data->get_calendar_list();
		$this->cached_vars['statuses']	= $this->data->get_status_list();
		$this->cached_vars['recurses']	= array(	'y' => 'Yes',
													'n' => 'No'
													);
													
		$this->cached_vars['orderbys']	= array(	'event_id'		=> ee()->lang->line('event_id'),
													'title'			=> ee()->lang->line('event_title'),
													'calendar_name'	=> ee()->lang->line('calendar_name'),
													'status'		=> ee()->lang->line('status'),
													'recurs'		=> ee()->lang->line('recurs'),
													'start_date'	=> ee()->lang->line('first_date'),
													'last_date'		=> ee()->lang->line('last_date')
													);
													
		$this->cached_vars['sorts']		= array(	'ASC' => ee()->lang->line('ascending'),
													'DESC' => ee()->lang->line('descending')
													);
													
		$this->cached_vars['limits']	= array(	'10'	=> '10',
													'50'	=> '50',
													'100'	=> '100',
													'250'	=> '250',
													'500'	=> '500'
													);
													
		$this->cached_vars['directions']	= array(	'equal'		=> ee()->lang->line('this_date'),
														'greater'	=> ee()->lang->line('or_later'),
														'less'		=> ee()->lang->line('or_earlier')
														);
														
		$this->cached_vars['delete']	= ee()->lang->line('delete');

		$this->cached_vars['url']		= preg_replace('#&offset=\d+#', '', $_SERVER['REQUEST_URI']);
		
		// -------------------------------------
		//  form/link urls
		// -------------------------------------
		
		$this->cached_vars['form_url']	= $this->base . AMP . 'method=view_events' . AMP . 'limit=' . $this->cached_vars['limit'];
		
		// -------------------------------------
		//  Pagination
		// -------------------------------------

		ee()->load->library('pagination');
		$config['base_url'] 			= $this->cached_vars['form_url'];
		$config['total_rows'] 			= $this->data->event_basics(TRUE);
		$config['per_page'] 			= $this->cached_vars['limit'];
		$config['page_query_string'] 	= TRUE;
		$config['query_string_segment'] = 'offset';
		ee()->pagination->initialize($config);
		$this->cached_vars['paginate']	= ee()->pagination->create_links();

		$this->cached_vars['data']		= $this->data->event_basics();
		
		if (APP_VER >= 2.0)
		{
			ee()->cp->add_js_script(array('ui' => 'datepicker'));
		}

		// -------------------------------------
		//  Which View
		// -------------------------------------

		$this->cached_vars['current_page'] = $this->view('events.html', $this->cached_vars['data'], TRUE);

		// --------------------------------------------
		//  Load Homepage
		// --------------------------------------------

		return $this->ee_cp_view('index.html');
	}
	/* END view_events() */

	// --------------------------------------------------------------------

	public function delete_events_confirm($message = '')
	{
		$this->cached_vars['message'] = $message;
		$this->cached_vars['module_menu_highlight'] = 'view_events';

		$this->cached_vars['question']	= str_replace('{COUNT}', count(ee()->input->post('toggle')), ee()->lang->line('delete_events_question'));
		$this->cached_vars['delete']	= ee()->lang->line('delete');
		$this->cached_vars['items']		= ee()->input->post('toggle');
		$this->cached_vars['form_url']	= $this->base . AMP . 'method=view_events' . AMP . 'msg=events_deleted';

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------

		$this->cached_vars['page_title']	= ee()->lang->line('delete_events_title');
		$this->add_crumb(ee()->lang->line('events'));
		$this->add_crumb(ee()->lang->line('delete_events'));
		$this->build_crumbs();

		// -------------------------------------
		//  Which View
		// -------------------------------------

		$this->cached_vars['current_page'] = $this->view('delete_events.html', NULL, TRUE);

		// --------------------------------------------
		//  Load Homepage
		// --------------------------------------------

		return $this->ee_cp_view('index.html');
	}
	/* END delete_events_confirm() */

	// --------------------------------------------------------------------

	public function delete_events()
	{
		//--------------------------------------------  
		//	call cal delete events hook
		//--------------------------------------------
		
		if (ee()->extensions->active_hook('calendar_delete_events') === TRUE)
		{
			$edata = ee()->extensions->call('calendar_delete_events', $this);
			if (ee()->extensions->end_script === TRUE) return;
		}
		
		
		if (APP_VER < 2.0)
		{
			if ( ! class_exists('Publish'))
			{
				require_once PATH_CP.'cp.publish'.EXT;
		
				$PB = new Publish();

				$PB->delete_entries();
			}
		}
		else
		{
			if ( ! ee()->cp->allowed_group('can_access_content') )
			{
				show_error(ee()->lang->line('unauthorized_access'));
			}
	
			if ( ! ee()->cp->allowed_group('can_delete_self_entries') AND
				 ! ee()->cp->allowed_group('can_delete_all_entries'))
			{
				show_error(ee()->lang->line('unauthorized_access'));
			}
			
			// -------------------------------------------
			// 'delete_entries_start' hook.
			//  - Perform actions prior to entry deletion / take over deletion
			if (ee()->extensions->active_hook('delete_entries_start') === TRUE)
			{			
			  	$edata = ee()->extensions->call('delete_entries_start');
			   	if (ee()->extensions->end_script === TRUE) return;
			}
			//
			// -------------------------------------------
			
			ee()->api->instantiate('channel_entries');
			$res = ee()->api_channel_entries->delete_entry(ee()->input->post('delete'));
		}

		//sadly, if the entries were deleted somewhere else, they might not get properly deleted

		$delete = ee()->input->post('delete');

		//this should be an array coming from the delete_confirm, but JUUUST in case
		if ( ! is_array($delete) )
		{
			if ( ! is_numeric($delete))
			{
				return;
			}

			$delete = array($delete);
		}

		foreach($delete as $id)
		{
			$this->data->delete_event($id);
		}
	
	}
	/* ENd delete_events() */

	// --------------------------------------------------------------------

	/**
	 * Edit Occurrences
	 * 
	 * @access	public
	 * @param	string
	 * @return	null
	 */

	public function edit_occurrences($message='')
	{
		if ($message == '' AND isset($_GET['msg']))
		{
			$message = ee()->lang->line($_GET['msg']);
		}

		$this->cached_vars['message'] 				= $message;
		$this->cached_vars['module_menu_highlight'] = 'view_events';

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------

		$this->add_crumb(ee()->lang->line('occurrences'));
		
		//must have an event_id
		if ( ee()->input->get_post('event_id') === FALSE ) return FALSE;

		// -------------------------------------
		//  filtering input data
		// -------------------------------------

		$this->cached_vars['event_id']		= $event_id 	= ee()->input->get_post('event_id');
		$this->cached_vars['status']		= $status 		= (ee()->input->get_post('status')) ? 
																ee()->input->get_post('status') : '';
		$this->cached_vars['date']			= $input_date	= (ee()->input->get_post('date')) ? 
																ee()->input->get_post('date') : '';
		$this->cached_vars['direction']		= $direction 	= (ee()->input->get_post('date_direction')) ? 
																ee()->input->get_post('date_direction') : '';
		$this->cached_vars['orderby']		= $orderby		= (ee()->input->get_post('orderby')) ? 
																ee()->input->get_post('orderby') : 'start_date';
		$this->cached_vars['sort']			= $sort			= (ee()->input->get_post('sort')) ? 
																ee()->input->get_post('sort') : 'ASC';
		$this->cached_vars['offset']		= $offset 		= is_numeric(ee()->input->get_post('offset')) ?
		 														ee()->input->get_post('offset') : 0;
		$this->cached_vars['limit']			= $limit 		= (ee()->input->get_post('limit')) ? 
																ee()->input->get_post('limit') : 50;
		$this->cached_vars['occurrences_limit']	= $occurrences_limit = (
			ee()->input->get_post('occurrences_limit')
		) ? ee()->input->get_post('occurrences_limit') : 100;

		//--------------------------------------------  
		//	filtering options
		//--------------------------------------------

		// date filtering
		
		if ($input_date)
		{
			$formatted_date = $this->data->format_input_date($input_date);
			$formatted_date	= $formatted_date['ymd'];

			$dirs 			= array(	
				'greater'	=> '>=',
				'less'		=> '<=',
				'equal'		=> '='
			);

			$dir 			= ($direction AND array_key_exists($direction, $dirs)) ? 
								$dirs[$direction] : '=';
		}

		$this->cached_vars['statuses']		= $this->data->get_status_list();
		
		$this->cached_vars['orderbys']		= array(	
			'title'				=> ee()->lang->line('event_title'),
			'start_date'		=> ee()->lang->line('event_date'),
			'status'			=> ee()->lang->line('status')
		);
		
		$this->cached_vars['sorts']			= array(	
			'ASC' 				=> ee()->lang->line('ascending'),
			'DESC' 				=> ee()->lang->line('descending')
		);
		
		$this->cached_vars['limits']		= array(	
			'10'				=> '10',
			'50'				=> '50',
			'100'				=> '100',
			'250'				=> '250',
			'500'				=> '500'
		);
		
		$this->cached_vars['directions']	= array(	
			'greater'			=> ee()->lang->line('or_later'),
			'less'				=> ee()->lang->line('or_earlier')
		);

		if ($this->cached_vars['date'] != '' AND strpos($this->cached_vars['date'], '/') !== FALSE)
		{
			list($m, $d, $y)					= explode('/', $this->cached_vars['date']);
			$this->cached_vars['range_date']	= array('year' => $y, 'month' => $m, 'day' => $d);
		}
		else
		{
			$this->cached_vars['range_date']	= array();
		}

		$this->cached_vars['start_date']   		= ($this->cached_vars['direction'] != 'less') ? 
										   			$this->cached_vars['range_date'] : array();
		                                   		
		$this->cached_vars['end_date']	   		= ($this->cached_vars['direction'] == 'less') ? 
										   			$this->cached_vars['range_date'] : array();

		//--------------------------------------------
		//  Get time format
		//--------------------------------------------

		$this->cached_vars['clock_type'] 		= $clock_type = $this->data->preference('clock_type');

		//--------------------------------------------  
		//	event data
		//--------------------------------------------

		$event_data	= $this->data->fetch_all_event_data(array($event_id));
		
		$events 	= array();

		if ( ! class_exists('Calendar_event'))
		{
			require_once CALENDAR_PATH.'calendar.event'.EXT;
		}

		$start_ymd	= ($input_date ? 	
						$formatted_date : 
						((isset($this->P['date_range_start']->value->ymd)) ? 
							$this->P['date_range_start']->value->ymd : 
							''));
		$end_ymd	= (isset($this->P['date_range_end']->value->ymd)) ? $this->P['date_range_end']->value->ymd : '';

		foreach ($event_data as $k => $edata)
		{
			$temp		= new Calendar_event($edata, $start_ymd, $end_ymd, $occurrences_limit);
			
			if (! empty($temp->dates))
			{
				$temp->prepare_for_output();
				$events[$edata['entry_id']] = $temp;
			}
		}
		
		//if this event isnt present, bail
		if ( isset( $events[$event_id]->default_data['entry_id'] ) === FALSE ) return FALSE;

		//--------------------------------------------  
		//	Occurrence data
		//--------------------------------------------

		$entry_ids		= array();
		$entry_ids[]	= $events[$event_id]->default_data['entry_id'];
		
		if (isset($events[$event_id]->occurrences) AND ! empty($events[$event_id]->occurrences))
		{
			foreach ($events[$event_id]->occurrences as $ymd => $times)
			{
				foreach ($times as $time => $data)
				{
					if (! in_array($data['entry_id'], $entry_ids))
					{
						$entry_ids[] = $data['entry_id'];
					}
				}
			}
		}
				
		$odata = $this->data->fetch_occurrence_channel_data($entry_ids);

		//--------------------------------------------  
		//	vars
		//--------------------------------------------
		
		if ( ! empty($events))
		{
			$this->cached_vars['events']		= $events;			
			$this->cached_vars['odata']			= $odata;
			$this->cached_vars[$this->sc->db->channel_id]		= $channel_id = $odata[
																	$events[$event_id]->default_data['entry_id']
																   ][$this->sc->db->channel_id];
			$this->cached_vars['calendar_id']	= $calendar_id 	= $events[$event_id]->default_data['calendar_id'];
			$this->cached_vars['site_id']		= $site_id 		= $this->data->get_site_id();
			$this->cached_vars['start_time']	= $start_time	= $events[$event_id]->default_data['start_time'];
			$this->cached_vars['end_time']		= $end_time		= $events[$event_id]->default_data['end_time'];
			$this->cached_vars['all_day']		= $all_day		= ($events[$event_id]->default_data['all_day'] === TRUE) ? 
																	'y' : 'n';
		}

		//--------------------------------------------
		//  Sort by date
		//--------------------------------------------

		if ($this->cached_vars['orderby'] == 'start_date')
		{
			foreach ($events as $id => $event)
			{
				if ($this->cached_vars['sort'] == 'DESC')
				{
					krsort($events[$id]->dates);
				}
				else
				{
					ksort($events[$id]->dates);
				}
				foreach ($event->dates as $date => $times)
				{
					if ($this->cached_vars['sort'] == 'DESC')
					{
						krsort($events[$id]->dates[$date]);
					}
					else
					{
						ksort($events[$id]->dates[$date]);
					}
				}
			}
		}

		//--------------------------------------------  
		//	data and filtering
		//--------------------------------------------
		
		$event_views = array();
		
		$count = 0;
				
		foreach ($events[$event_id]->dates as $ymd => $times)
		{
			$this->CAL->CDT->change_ymd($ymd);
			
			//date filtering
			if ($input_date)
			{
				if ($dir == '>=' AND $formatted_date > $ymd)
				{
					continue;
				}
				
				if ($dir == '<=' AND $formatted_date < $ymd)
				{
					continue;
				}
				
				if ($dir == '=' AND $formatted_date != $ymd)
				{
					continue;
				}
			}
			
			foreach ($times as $time => $data)
			{				
				$event_view = array();
												
				//--------------------------------------------  
				//	status
				//--------------------------------------------
				
				$event_view['ostatus'] = (isset($events[$event_id]->occurrences[$ymd][$time]) AND 
						    isset($odata[$events[$event_id]->occurrences[$ymd][$time]['entry_id']]['status'])) ? 
									$odata[$events[$event_id]->occurrences[$ymd][$time]['entry_id']]['status'] : 
									$odata[$events[$event_id]->default_data['entry_id']]['status'];
							
				//--------------------------------------------  
				//	status filter
				//--------------------------------------------
				
				//if the input status is filtering, we need to skip
				if ( ! in_array(ee()->input->get_post('status'), array(FALSE, ''), TRUE) AND
					 $event_view['ostatus'] !== ee()->input->get_post('status'))
				{
					continue;
				}
				
				//--------------------------------------------  
				//	title
				//--------------------------------------------
				
				$event_view['title'] = (isset($events[$event_id]->occurrences[$ymd][$time])) ? 
										$odata[$events[$event_id]->occurrences[$ymd][$time]['entry_id']]['title'] : 
										$odata[$events[$event_id]->default_data['entry_id']]['title'];
		
				//--------------------------------------------  
				//	time range
				//--------------------------------------------
		
				if ($data['all_day'] OR ($start_time == '0000' AND $end_time == '2400'))
				{
					$event_view['time_range'] = ee()->lang->line('all_day');
				}
				else
				{
					$this->CAL->CDT->change_time(substr($time, 0, 2), substr($time, 2, 2));
					$start 		= ($clock_type == '12') ? 
									$this->CAL->CDT->format_date_string('h:i a') : 
									$this->CAL->CDT->format_date_string('H:i');
				
					$this->CAL->CDT->change_time(substr($time, 4, 2), substr($time, 6, 2));
					$end 		= ($clock_type == '12') ? 
									$this->CAL->CDT->format_date_string('h:i a') : 
									$this->CAL->CDT->format_date_string('H:i');
				
					$event_view['time_range'] = "{$start} &ndash; {$end}";
				}
		
				//--------------------------------------------  
				//	edit link
				//--------------------------------------------		
		
				$start_time	= (isset($data['start_time'])) ? $data['start_time'] : $data['date']['time'];
				$end_time	= (isset($data['end_time'])) ? $data['end_time'] : $data['end_date']['time'];
				$start_time	= str_pad($start_time, 4, '0', STR_PAD_LEFT);
				$end_time	= str_pad($end_time, 4, '0', STR_PAD_LEFT);
						
							
				if (APP_VER < 2.0) 
				{
					$edit_link =	BASE . 
									AMP . "C=edit" .
									AMP . "M=edit_entry";
				}
				else
				{
					$edit_link = 	BASE . 
									AMP . "C=content_publish" .
									AMP . "M=entry_form" .
									AMP . "use_autosave=n";
				}			
		
				if (isset($events[$event_id]->occurrences[$ymd][$time]) AND 
					isset($odata[$events[$event_id]->occurrences[$ymd][$time]['entry_id']]['entry_id']) AND
					 	$odata[
							$events[$event_id]->occurrences[$ymd][$time]['entry_id']
						]['entry_id'] != $events[$event_id]->default_data['entry_id'])
				{
					$edit_link .=	AMP . "{$this->sc->db->channel_id}={$channel_id}" . 
								 	AMP . "entry_id={$events[$event_id]->occurrences[$ymd][$time]['entry_id']}" . 
								 	AMP . "event_id={$events[$event_id]->default_data['event_id']}" . 
								 	AMP . "occurrence_id={$events[$event_id]->occurrences[$ymd][$time]['occurrence_id']}" . 
								 	AMP . "calendar_id={$calendar_id}" . 
								 	AMP . "site_id={$site_id}" . 
								 	AMP . "start_time={$start_time}" . 
								 	AMP . "end_time={$end_time}" . 
								 	AMP . "all_day={$events[$event_id]->occurrences[$ymd][$time]['all_day']}" . 
								 	AMP . "ymd={$ymd}";
				}                	
				else             	
				{                	
					$edit_link .=	AMP . "entry_id={$events[$event_id]->default_data['entry_id']}" . 
								 	AMP . "{$this->sc->db->channel_id}={$channel_id}" . 
								 	AMP . "event_id={$events[$event_id]->default_data['event_id']}" . 
								 	AMP . "calendar_id={$calendar_id}" . 
								 	AMP . "site_id={$site_id}" . 
								 	AMP . "start_time={$start_time}" . 
								 	AMP . "end_time={$end_time}" . 
								 	AMP . "all_day={$all_day}" . 
								 	AMP . "ymd={$ymd}" . 
								 	AMP . "start_date={$ymd}" . 
								 	AMP . "end_date={$data['end_date']['ymd']}" . 
								 	AMP . "new_occurrence=y";
				}
				
				$event_view['edit_link'] = $edit_link;
				
				//--------------------------------------------  
				//	time
				//--------------------------------------------
				
				$event_view['time'] = $this->CAL->CDT->format_date_string(
					$this->data->date_formats[$this->data->preference('date_format')]['cdt_format']
				);
									
				$event_view['count'] = ++$count;					
															
				//add to output array
				$event_views[] = $event_view;					
			}			
		}

		$total = count($event_views);

		//--------------------------------------------  
		//	Pagination
		//--------------------------------------------

		ee()->load->library('pagination');
		
		$config_base_url						= $this->base . AMP . 'method=edit_occurrences' . 
																AMP . 'limit=' . $this->cached_vars['limit'] . 	
																AMP . 'event_id=' . $event_id;
		
		//add filtering if present to base url
		if ($status) 
		{
			$config_base_url .= AMP . 'status=' . $status;
		}
		
		if ($sort) 
		{
			$config_base_url .= AMP . 'sort=' . $sort;
		}
		
		if ($limit) 
		{
			$config_base_url .= AMP . 'limit=' . $limit;
		}
		
		if ($occurrences_limit) 
		{
			$config_base_url .= AMP . 'occurrences_limit=' . $occurrences_limit;
		}
		
		if ($orderby) 
		{
			$config_base_url .= AMP . 'orderby=' . $orderby;
		}
		
		if ($input_date) 
		{
			$config_base_url .= AMP . 'date=' . $input_date;
		}
		
		if ($direction) 
		{
			$config_base_url .= AMP . 'date_direction=' . $direction;
		}
		
		$config['base_url']						= $config_base_url;																										
		$config['total_rows'] 					= $total;
		$config['per_page'] 					= $limit;
		$config['page_query_string'] 			= TRUE;
		$config['query_string_segment'] 		= 'offset';
		
		ee()->pagination->initialize($config);
		
		$this->cached_vars['paginate']			= ee()->pagination->create_links();

		//--------------------------------------------  
		//	clip if larger than limit
		//-------------------------------------------- 
		// 	due to the way we are filtering, this is how
		//	we have to limit our shown events instead of
		//	limiting the queries. 
		//	The data is just too complex.
		//--------------------------------------------

		if ($total > $limit)
		{
			$event_views = array_slice($event_views, $offset, $limit);
		}

		//--------------------------------------------  
		//	now we can finally add to vars
		//--------------------------------------------
		
		$this->cached_vars['event_views'] = $event_views;

		//--------------------------------------------  
		//	output
		//--------------------------------------------

		//need the jqui date picker for 2.x since we arent using our own jquidatepicker there
		if (APP_VER >= 2.0)
		{
			ee()->cp->add_js_script(array('ui' => 'datepicker'));
		}

		$this->cached_vars['form_url']		= $this->base . 
												AMP . 'method=edit_occurrences' . 
												AMP . 'event_id=' . $event_id;

		//--------------------------------------------
		//  Which View
		//--------------------------------------------

		$this->cached_vars['current_page'] 	= $this->view('occurrences_edit.html', NULL, TRUE);

		//--------------------------------------------
		//  Load Homepage
		//--------------------------------------------

		return $this->ee_cp_view('index.html');
	}
	/* END edit_occurrences() */


	// --------------------------------------------------------------------

	/**
	 * permissions
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */

	public function permissions ($message='')
	{
		//2.x only!
		if (APP_VER < 2.0)
		{
			ee()->functions->redirect($this->base);
		}

		if ($message == '' AND isset($_GET['msg']))
		{
			$message = ee()->lang->line($_GET['msg']);
		}

		$this->cached_vars['message'] 				= $message;
		$this->cached_vars['module_menu_highlight'] = 'permissions';

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------

		$this->add_crumb(ee()->lang->line('permissions'));

		ee()->load->library('calendar_permissions');

		// -------------------------------------
		//	get member groups
		// -------------------------------------

		$this->cached_vars['member_groups'] = $member_groups = $this->data->get_member_groups();

		// -------------------------------------
		//	allowed, permissions
		// -------------------------------------

		$this->cached_vars['groups_allowed_all'] 	= ee()->calendar_permissions->get_groups_allowed_all();
		$this->cached_vars['groups_denied_all']  	= ee()->calendar_permissions->get_groups_denied_all();	
		$this->cached_vars['permissions_enabled'] 	= ee()->calendar_permissions->enabled();
		$this->cached_vars['filter_on'] 			= ee()->calendar_permissions->filter_on();	

		$this->cached_vars['show_search_filter'] 	= (APP_VER >= '2.4.0');

		// -------------------------------------
		//	calendar list
		// -------------------------------------

		$calendar_list = $this->data->get_calendar_list();

		// -------------------------------------
		//	sort calendar permissions
		// -------------------------------------
		
		$calendar_permissions 		= ee()->calendar_permissions->get_group_permissions();

		$calendar_permission_data 	= array();

		foreach ($calendar_list as $calendar_id => $calendar_data)
		{
			$calendar_permission_data[$calendar_id] = array(
				'title' 	 	=> $calendar_data['title'],
				'permissions'	=> $calendar_permissions[$calendar_id]
			);
		}

		$this->cached_vars['calendar_permission_data'] = $calendar_permission_data;

		// -------------------------------------
		//	lang stuff
		// -------------------------------------

		$lang_items = array(
			'calendar_permissions_desc',
			'allowed_groups',
			'allow_full_access',
			'permissions_enabled',
			'save_permissions',
			'calendar_name',
			'allow_all',
			'deny_all_access',
			'deny_takes_precedence',
			'disallowed_behavior_for_edit_page',
			'none',
			'search_filter',
			'disable_link'
		);

		foreach ($lang_items as $item)
		{
			$this->cached_vars['lang_' . $item] = ee()->lang->line($item);
		}

		$this->cached_vars['form_url'] = $this->base . AMP . 'method=save_permissions';

		//--------------------------------------------
		//  Which View
		//--------------------------------------------

		$this->cached_vars['current_page'] 	= $this->view('permissions.html', NULL, TRUE);

		//--------------------------------------------
		//  Load Homepage
		//--------------------------------------------

		return $this->ee_cp_view('index.html');
	}
	//end permissions


	// --------------------------------------------------------------------

	/**
	 * save_permissions
	 *
	 * @access	public
	 * @return	null
	 */

	public function save_permissions ()
	{
		ee()->load->library('calendar_permissions');

		ee()->calendar_permissions->save_permissions(ee()->security->xss_clean($_POST));

		// -------------------------------------
		//	move out!
		// -------------------------------------

		ee()->functions->redirect(
			$this->base . 
				AMP . 'method=permissions' . 
				AMP . 'msg=permissions_saved'
		);
	}
	//END save_permissions


	// --------------------------------------------------------------------

	/**
	 * View Preferences
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */

	public function view_preferences($message='')
	{
		if ($message == '' AND isset($_GET['msg']))
		{
			$message = ee()->lang->line($_GET['msg']);
		}

		$this->cached_vars['message']				= $message;
		$this->cached_vars['module_menu_highlight']	= 'preferences';

		// -------------------------------------
		//  Title and Crumbs
		// -------------------------------------

		$this->add_crumb(ee()->lang->line('preferences'));
		$this->build_crumbs();

		// -------------------------------------
		//  Data for the view(s)
		// -------------------------------------

		$preferences = $this->data->get_module_preferences();

		foreach ($preferences as $k => $v)
		{
			$this->cached_vars[$k] = $v;
		}

		$menu = ee()->localize->timezone_menu(
			ee()->config->item(ee()->config->item('site_short_name') . '_timezone')
		);
		
		preg_match_all(
			'#<option value=\'(.+?)\'(?:.+)?\>\(UTC ?(.*?)\).*?</option>#m', 
			$menu, 
			$matches, 
			PREG_SET_ORDER
		);
		
		foreach ($matches as $match)
		{
			$replace = '';

			if ($match[1] == 'UTC')
			{
				$replace = str_replace("'UTC'", "'0000'", $match[0]);
			}
			else
			{
				$array = explode(':', $match[2]);

				if (abs($array[0]) < 10)
				{
					$array[0] = str_replace(
						array('+', '-'), 
						array('+0', '-0'), 
						$array[0]
					);
				}
				
				$val		= $array[0] . $array[1];

				$replace	= str_replace(
					"'" . $match[1] . "'", 
					"'" . $val . "'", 
					$match[0]
				);
			}

			$menu = str_replace($match[0], $replace, $menu);
		}
		
		$selected = (isset($this->cached_data['tz_offset'])) ? 
						$this->cached_data['tz_offset'] : 
						$this->data->preference('tz_offset');
		
		if ($selected !== FALSE)
		{
			$menu = str_replace("selected='selected'", '', $menu);
			
			$menu = str_replace(
				"value='{$selected}'", 
				"value='{$selected}' selected='selected'", 
				$menu
			);
		}
		
		$this->cached_vars['menu'] = $menu;

		// -------------------------------------
		//  Get installed weblogs
		// -------------------------------------

		$this->cached_vars['weblogs'] = $this->data->get_channel_basics();
		
		// -------------------------------------
		//  form/link urls
		// -------------------------------------
		
		$this->cached_vars['form_url']	= $this->base . AMP . 
											'method=update_preferences';

		// -------------------------------------
		//  Whatchulookinat?
		// -------------------------------------

		$this->cached_vars['current_page'] = $this->view('preferences.html', NULL, TRUE);

		// --------------------------------------------
		//  Load View
		// --------------------------------------------

		return $this->ee_cp_view('index.html');
	}
	/* END view_preferences() */


	// --------------------------------------------------------------------

	/**
	 * Update Preferences
	 *
	 * @access	public
	 * @param	string
	 * @return	null
	 */

	public function update_preferences($message='')
	{
		$this->actions->update_preferences();

		return $this->view_preferences(ee()->lang->line('preferences_updated'));
	}
	/* END update_preferences() */


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

	public function calendar_module_install()
	{
		if ( ! class_exists('Calendar_updater_base'))
		{
    		require_once $this->addon_path . 'upd.calendar.base.php';		
		}

		$U = new Calendar_updater_base();

		return $U->install();
	}
	/* END calendar_module_install() */

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

	public function calendar_module_deinstall()
	{
		if ( ! class_exists('Calendar_updater_base'))
		{
    		require_once $this->addon_path . 'upd.calendar.base.php';		
		}

		$U = new Calendar_updater_base();

		return $U->uninstall();
	}
	/* END calendar_module_deinstall() */


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

	public function calendar_module_update()
	{
    	if ( ! isset($_POST['run_update']) OR $_POST['run_update'] != 'y')
    	{
    		$this->add_crumb(ee()->lang->line('update_calendar'));
			$this->cached_vars['form_url'] = $this->base.'&method=calendar_module_update';
			
			$this->cached_vars['current_page'] = $this->view('update_module.html', NULL, TRUE);

			return $this->ee_cp_view('index.html');
		}
    
		if ( ! class_exists('Calendar_updater_base'))
		{
    		require_once $this->addon_path . 'upd.calendar.base.php';		
		}

		$U = new Calendar_updater_base();
    	
    	if ($U->update() !== TRUE)
    	{
    		return $this->index(ee()->lang->line('update_failure'));
    	}
    	else
    	{
    		return $this->index(ee()->lang->line('update_successful'));
    	}
	}
	/* END calendar_module_update() */

	// --------------------------------------------------------------------

}
// END CLASS Calendar