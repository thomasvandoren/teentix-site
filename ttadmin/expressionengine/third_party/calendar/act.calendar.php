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
 * Calendar - Actions
 *
 * Handles All Form Submissions and Action Requests Used on both User and CP areas of EE
 *
 * @package	 Solspace:Calendar
 * @author		Solspace DevTeam
 * @filesource 	./system/expressionengine/third_party/calendar/act.calendar.php
 */

if ( ! class_exists('Addon_builder_calendar'))
{
	require_once 'addon_builder/addon_builder.php';	
}

class Calendar_actions extends Addon_builder_calendar {

	public $default_calendar_weblog_name	= 'calendar_calendars';
	public $default_event_weblog_name	 	= 'calendar_events';

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	null
	 */

	public function __construct()
	{						
		parent::Addon_builder_calendar('calendar');
				
		// -------------------------------------
		//	Module Installed and What Version?
		// -------------------------------------

		if ($this->database_version() == FALSE OR 
		    $this->version_compare($this->database_version(), '<', CALENDAR_VERSION))
		{
			return;
		}
		
		if ( ! defined('CALENDAR_EVENTS_CHANNEL_NAME_DEFAULT'))
		{
			require_once 'constants.calendar.php';
		}
		
		$this->default_event_weblog_name 	= CALENDAR_EVENTS_CHANNEL_NAME_DEFAULT;
		$this->default_calendar_weblog_name = CALENDAR_CALENDARS_CHANNEL_NAME_DEFAULT;
	}
	/* END */


	// --------------------------------------------------------------------

	/**
	 * Parse a text date and return a Calendar_datetime object
	 *
	 * @param text	$value	Text to parse
	 * @param object	$CDT	Calendar_datetime object
	 * @return	Calendar_datetime object
	 */

	public function parse_text_date($value, $CDT, $end = FALSE)
	{				
		// -------------------------------------
		//	Parseable formats:
		//	today, tomorrow, yesterday
		//	+5 days/weeks/months/years begin/end
		//	YYYYMMDD
		//	YYYY-MM-DD
		//	MM-DD-YYYY
		//	MM/DD/YYYY
		//	year-month-day
		//	Any of these may have a time at the end
		// -------------------------------------

		if ($value == '')
		{
			return FALSE;
		}

		$CDT->reset();

		// -------------------------------------
		//	Look for a time at the end
		// -------------------------------------

		if ($end === TRUE)
		{
			$time = array(	
				'hour'		=> '24',
				'minute'	=> '00',
				'time'		=> '2400'
			);
		}
		else
		{
			$time = array(	
				'hour'		=> '00',
				'minute'	=> '00',
				'time'		=> '0000'
			);
		}

		if (strstr($value, ' @ ') !== FALSE)
		{
			$t_value	= array_pop(explode(' @ ', $value));
			$time_temp	= self::parse_text_time($t_value);
			
			if ($time_temp != $time)
			{
				$time = $time_temp;
			}
			
			$value	= str_replace(' @ '.$t_value, '', $value);
		}

		$CDT->change_time($time['hour'], $time['minute']);

		$value = trim(strtolower($value));

		// -------------------------------------
		//	today, tomorrow, yesterday
		// -------------------------------------

		$words = array( 
			'today'	 	=> 0,
			'tomorrow'	=> 1,
			'yesterday' => -1
		);

		if (isset($words[$value]))
		{
			$CDT->add_day($words[$value]);
			return $CDT->datetime_array();
		}

		// -------------------------------------
		//	today, tomorrow, yesterday ... in some other language
		// -------------------------------------

		foreach ($words as $word => $days)
		{
			if ($value == strtolower(ee()->lang->line($word)))
			{
				$CDT->add_day($days);
				return $CDT->datetime_array();
			}
		}

		// -------------------------------------
		//	+5 days/weeks/months/years begin/end
		//	we are going this with english AND
		// 	the lang file so people can use day/dia
		// -------------------------------------

		$words	= array(
			preg_quote(ee()->lang->line('day'), '#'),
			preg_quote(ee()->lang->line('week'), '#'),
			preg_quote(ee()->lang->line('month'), '#'),
			preg_quote(ee()->lang->line('year'), '#'),
			'day',
			'week',
			'month',
			'year'
		);
		
		$ago	= array(
			preg_quote(ee()->lang->line('ago'), '#'), 
			'ago'
		);
		
		$adjust = array(	
			preg_quote(ee()->lang->line('begin'), '#'),
			preg_quote(ee()->lang->line('end'), '#'),
			'begin',
			'end'
		);

		if (preg_match('#([-+])?(\d+) ('. implode('|', $words). ')s?( (?:'. implode('|', $ago ) .'))?' . 
					  				 '(?: ('. implode('|', $adjust). '))?#', $value, $match))
		{
			//match 0 = whole string 	(-1 month ago begin)
			//match 1 = plus or minus 	(_-_1 month ago begin)
			//match 2 = number 			(-_1_ month ago begin)
			//match 3 = words 			(-1 _month_ ago begin)
			//match 4 = ago 			(-1 month _ago_ begin)
			//match 5 = adjust 			(-1 month ago _begin_)			

			$sign = ($match[1] == '-' OR 
					 (isset($match[4]) AND 
					  ($match[4] == ' ' . $ago[0] OR 
					   $match[4] == ' ' . $ago[1]))) ? '-' : '';
			
			if (strpos($match[3], ee()->lang->line('day')) !== FALSE OR
				strpos($match[3], 'day') !== FALSE)
			{
				$CDT->add_day($sign.$match[2]);
			}
			elseif (strpos($match[3], ee()->lang->line('week')) !== FALSE OR
					strpos($match[3], 'week') !== FALSE)
			{
				if ($match[2] > 0)
				{
					$match[2] *= 7;
					$CDT->add_day($sign.$match[2]);
				}
			}
			elseif (strpos($match[3], ee()->lang->line('month')) !== FALSE OR
					strpos($match[3], 'month') !== FALSE)
			{
				$CDT->add_month($sign.$match[2]);
			}
			elseif (strpos($match[3], ee()->lang->line('year')) !== FALSE OR
					strpos($match[3], 'year') !== FALSE)
			{
				$CDT->add_year($sign.$match[2]);
			}

			// -------------------------------------
			//	look for adjust (begin/end)
			// -------------------------------------

			if (isset($match[5]) AND in_array($match[5], $adjust))
			{
				if ($match[5] == ee()->lang->line('begin') OR
					$match[5] == 'begin')
				{
					if (strpos($match[3], ee()->lang->line('week')) !== FALSE OR
						strpos($match[3], 'week') !== FALSE)
					{
						if ($CDT->day_of_week != $CDT->first_day_of_week)
						{
							$offset = ($CDT->day_of_week > $CDT->first_day_of_week) ? 
											$CDT->day_of_week - $CDT->first_day_of_week : 
											7 + $CDT->day_of_week - $CDT->first_day_of_week;
							
							$CDT->add_day(-$offset);
						}
					}
					elseif (strpos($match[3], ee()->lang->line('month')) !== FALSE OR
							strpos($match[3], 'month') !== FALSE)
					{
						$CDT->change_date($CDT->year, $CDT->month, 1);
					}
				}
				elseif ($match[5] == ee()->lang->line('end') OR
						$match[5] == 'end')
				{
					if (strpos($match[3], ee()->lang->line('week')) !== FALSE OR
						strpos($match[3], 'week') !== FALSE)
					{
						$last_dow = ($CDT->first_day_of_week == 0) ? 
							6 : $CDT->first_day_of_week - 1;
						
						if ($CDT->day_of_week != $last_dow)
						{
							$offset = ($CDT->day_of_week < $last_dow) ? 
											$last_dow - $CDT->day_of_week : 
											7 + $last_dow - $CDT->day_of_week;
							
							$CDT->add_day($offset);
						}
					}
					elseif (strpos($match[3], ee()->lang->line('month')) !== FALSE OR
							strpos($match[3], 'month') !== FALSE)
					{
						$CDT->change_date($CDT->year, $CDT->month, $CDT->days_in_month());
					}
				}
			}

			return $CDT->datetime_array();
		}

		// -------------------------------------
		//	YYYYMMDD
		// -------------------------------------

		$length = strlen($value);

		if (self::is_integer($value) === TRUE AND ($length >= 5 AND $length <= 8))
		{
			if ($CDT->is_valid_ymd($value))
			{
				$CDT->change_date(
					substr($value, 0, $length-4), 
					substr($value, -4, 2), 
					substr($value, -2, 2)
				);

				return $CDT->datetime_array();
			}
		}

		// -------------------------------------
		//	YYYY-MM-DD OR MM-DD-YYYY OR year-month-day
		// -------------------------------------

		if (strpos($value, '-') !== FALSE)
		{
			if (stripos($value, 'year') !== FALSE 						OR 
				stripos($value, 'month') !== FALSE 						OR 
				stripos($value, 'day') !== FALSE 						OR 
				stripos($value, ee()->lang->line('year')) !== FALSE 	OR 
				stripos($value, ee()->lang->line('month')) !== FALSE 	OR 
				stripos($value, ee()->lang->line('day')) !== FALSE)
			{
				$value	= strtolower($value);
				$value	= str_replace(
					array(
						'year', 
						'month', 
						'day', 
						strtolower(ee()->lang->line('year')), 
						strtolower(ee()->lang->line('month')), 
						strtolower(ee()->lang->line('day'))
					), 
					array(
						date('Y', ee()->localize->now), 
						date('m', ee()->localize->now), 
						date('d', ee()->localize->now), 
						date('Y', ee()->localize->now), 
						date('m', ee()->localize->now), 
						date('d', ee()->localize->now)
					), 
					$value
				);
			}

			if (stripos($value, 'last') !== FALSE OR 
				stripos($value, ee()->lang->line('last')) !== FALSE)
			{
				list($year, $month) = explode('-', $value);
				if (self::is_integer($year) === TRUE AND 
					self::is_integer($month) === TRUE)
				{
					$value = str_replace(
						array(
							'last', 
							strtolower(ee()->lang->line('last'))
						), 
						$CDT->days_in_month($month, $year), 
						strtolower($value)
					);		
				}
			}

			if ($CDT->is_valid_ymd(str_replace('-', '', $value)))
			{
				$value	= str_replace('-', '', $value);
				$length = strlen($value);
				$CDT->change_date(
					substr($value, 0, $length-4), 
					substr($value, -4, 2), 
					substr($value, -2, 2)
				);

				return $CDT->datetime_array();
			}

			$parts = explode('-', $value);

			if (count($parts) == 3 AND 
				$CDT->is_valid_ymd($parts[2].$parts[0].$parts[1]))
			{
				$CDT->change_date($parts[2], $parts[0], $parts[1]);

				return $CDT->datetime_array();
			}
		}

		// -------------------------------------
		//	MM/DD/YYYY
		// -------------------------------------

		if (strpos($value, '/') !== FALSE)
		{
			$parts = explode('/', $value);
			if ($CDT->is_valid_ymd($parts[2].$parts[0].$parts[1]))
			{
				$CDT->change_date($parts[2], $parts[0], $parts[1]);

				return $CDT->datetime_array();
			}
		}

		return FALSE;
	}
	/* END parse_text_date() */


	// --------------------------------------------------------------------

	/**
	 * Value is an integer
	 *
	 * @param int $value	Value to test
	 * @return	bool
	 */

	public function is_integer($value)
	{
		return 1 === preg_match('/^[+-]?[0-9]+$/', $value);
	}
	/* END is_integer() */

	// --------------------------------------------------------------------

	/**
	 * Value is within a range, inclusive
	 *
	 * @param int $value	Value to test
	 * @param int $min	Minimum value
	 * @param int $max	Maximum value
	 * @return	bool
	 */

	public function is_in_range($value, $min = FALSE, $max = FALSE)
	{
		if ($min !== FALSE)
		{
			if ($value < $min)
			{
				return FALSE;
			}
		}

		if ($max !== FALSE)
		{
			if ($value > $max)
			{
				return FALSE;
			}
		}

		return TRUE;
	}
	/* END is_in_range() */

	// --------------------------------------------------------------------

	/**
	 * Value is among a collection of allowed values
	 *
	 * @param mixed $value		Value to test
	 * @param array $allowed	Collection of permissible values
	 * @param bool	$case_sensitive Case sensitive? [optional]
	 * @return	bool
	 */

	public function is_allowed_value($value, $allowed, $case_sensitive = FALSE)
	{
		if (! $allowed)
		{
			return TRUE;
		}

		return ($case_sensitive === TRUE) ?
				 in_array($value, $allowed) : 
				 in_array(strtolower($value), array_map('strtolower', $allowed));
	}
	/* END is_allowed_value() */

	// --------------------------------------------------------------------

	/**
	 * Parse text into a time
	 *
	 * @param mixed $value	Value to parse
	 * @return	array
	 */

	public function parse_text_time($value)
	{
		// -------------------------------------
		//	Parseable formats:
		//	 now, noon, midnight
		//	 +5 hour/minute
		//	 0800
		//	 8:00 am/pm
		// -------------------------------------

		$time = array(	
			'hour'		=> '00',
			'minute'	=> '00',
			'time'		=> '0000'
		);

		if ($value == '')
		{
			return $time;
		}

		$value = strtolower($value);

		// -------------------------------------
		//	now
		// -------------------------------------

		if ($value == ee()->lang->line('now') OR $value == 'now')
		{			
			$time['time'] = date('Hi', ee()->localize->now);
											
			if ($time['time'] > 0)
			{
				$time['hour'] = str_pad(floor($time['time']/100), 2, '0', STR_PAD_LEFT);
				$time['minute'] = $time['time'] - ($time['hour'] * 100);
			}
			else
			{
				$time['time'] = '0000';
			}

			return $time;
		}

		// -------------------------------------
		//	noon, midnight
		// -------------------------------------

		$words = array( 
			ee()->lang->line('noon')		=> 1200,
			ee()->lang->line('midnight')	=> 0000,
			'noon'							=> 1200,
			'midnight'						=> 0000
		);
		
		foreach ($words as $word => $val)
		{
			if ($value == $word)
			{
				$time['time'] = $val;
				$time['hour'] = str_pad(floor($val/100), 2, '0', STR_PAD_LEFT);
				$time['minute'] = $val - ($time['hour'] * 100);

				return $time;
			}
		}

		// -------------------------------------
		//	+5 hours/minutes
		// -------------------------------------

		// TODO

		// -------------------------------------
		//	0800
		// -------------------------------------

		if (strlen($value) == 4 AND self::is_integer($value))
		{
			$time['hour'] = str_pad(floor($value/100), 2, '0', STR_PAD_LEFT);
			$time['minute'] = str_pad($value - ($time['hour'] * 100), 2, '0', STR_PAD_LEFT);
			$time['time'] = $value;

			return $time;
		}

		// -------------------------------------
		//	8:00 am
		// -------------------------------------

		$ampm_string = implode('|', array(
			ee()->lang->line('am'),
			ee()->lang->line('am_dot'),
			ee()->lang->line('pm'),
			ee()->lang->line('pm_dot'),
			'am',
			'a.m.',
			'pm',
			'p.m.',
		));

		if (preg_match('#(\d{1,2})[:\.](\d{1,2})(?: ('.$ampm_string.'))#', $value, $match))
		{
			if (isset($match[3]) AND strpos($match[3], 'p') !== FALSE)
			{
				$match[1] = ($match[1] == 12) ? 0 : $match[1] + 12;
			}
			$time['hour'] = str_pad($match[1], 2, '0', STR_PAD_LEFT);
			$time['minute'] = $match[2];
			$time['time'] = $time['hour'] . $time['minute'];

			return $time;
		}

		return $time;
	}

	// End parse_text_time()

	// --------------------------------------------------------------------

	/**
	 * Update Preferences
	 *
	 * @access  public
	 * @param string
	 * @return  null
	 */

	public function update_preferences($message='')
	{
		$data = $this->data->get_module_preferences();

		// -------------------------------------
		// First day of week
		// -------------------------------------

		if (ee()->input->post('first_day_of_week') !== FALSE)
		{
			$data['first_day_of_week'] = ee()->input->post('first_day_of_week');
		}

		// -------------------------------------
		// Clock type
		// -------------------------------------

		if (ee()->input->post('clock_type') !== FALSE)
		{
			$data['clock_type'] = ee()->input->post('clock_type');
		}

		// -------------------------------------
		// Default calendar timezone
		// -------------------------------------

		if (ee()->input->post('server_timezone') !== FALSE)
		{
			$data['tz_offset'] = ee()->input->post('server_timezone');
		}

		// -------------------------------------
		// Default calendar date format
		// -------------------------------------

		if (ee()->input->post('date_format') !== FALSE)
		{
			$data['date_format'] = ee()->input->post('date_format');
		}

		// -------------------------------------
		// Default calendar time format
		// -------------------------------------

		if (ee()->input->post('time_format') !== FALSE)
		{
			$data['time_format'] = ee()->input->post('time_format');
		}

		// -------------------------------------
		// Calendar weblog(s)
		// -------------------------------------
		
		//$data['calendar_weblog'] 	= $this->data->preference('calendar_weblog');

		// -------------------------------------
		// Event weblog(s)
		// -------------------------------------

		//$data['event_weblog'] 		= $this->data->preference('event_weblog');

		// -------------------------------------
		// Update
		// -------------------------------------

		$this->data->update_preferences($data);
		return TRUE;
	}
	/* END update_preferences() */


	// --------------------------------------------------------------------

	public function import_ics_data($calendar_id)
	{
		// -------------------------------------
		//	Get some basic info to use later
		// -------------------------------------

		$cbasics	= $this->data->calendar_basics();
		$cbasics	= $cbasics[$calendar_id];
		$urls	 	= $cbasics['ics_url'];

		if ($urls == '')
		{
			return FALSE;
		}

		$tz_offset	= ($cbasics['tz_offset'] != '') ? $cbasics['tz_offset'] : '0000';
		
		if ($tz_offset != '0000' AND ee()->config->item('daylight_savings') == 'y')
		{
			$tz_offset += 100;
		}
		
		$channel_id	= $this->data->channel_is_events_channel();
		$author_id	= $cbasics['author_id'];

		// -------------------------------------
		//	Prepare the URLs
		// -------------------------------------

		if (! is_array($urls))
		{
			$urls = explode("\n", $urls);
		}

		foreach ($urls as $k => $url)
		{
			$urls[$k] = trim($url);
		}

		// -------------------------------------
		//	Load iCalCreator
		// -------------------------------------

		if ( ! class_exists('vcalendar'))
		{
			require_once CALENDAR_PATH_ASSETS.'icalcreator/iCalcreator.class.php';
		}

		// -------------------------------------
		//	Load Calendar_datetime
		// -------------------------------------

		if ( ! class_exists('Calendar_datetime'))
		{
			require_once CALENDAR_PATH.'calendar.datetime'.EXT;
		}
		$CDT		= new Calendar_datetime();
		$CDT_end	= new Calendar_datetime();

		// -------------------------------------
		//	Load Publish
		// -------------------------------------
		
		if (APP_VER < 2.0)
		{
			//need to set DSP if not present			
			if ( ! isset($GLOBALS['DSP']) OR ! is_object($GLOBALS['DSP']))
			{
				if ( ! class_exists('Display')) 
				{
					require_once PATH_CP . 'cp.display' . EXT;				
				}

	            $GLOBALS['DSP'] = new Display();  
			}
			
			if ( ! class_exists('Publish'))
			{
				require_once PATH_CP.'cp.publish'.EXT;
			}
			
			$PB = new Publish();
			$PB->assign_cat_parent = (
				ee()->config->item('auto_assign_cat_parents') == 'n'
			) ? FALSE : TRUE;
		}
		else
		{
			ee()->load->library('api');
			
			ee()->api->instantiate(array(
				'channel_entries', 
				'channel_categories', 
				'channel_fields'
			));
			
			ee()->api_channel_entries->assign_cat_parent = (
				ee()->config->item('auto_assign_cat_parents') == 'n'
			) ? FALSE : TRUE;
		}
		
		
		
		// -------------------------------------
		//	Tell our extensions that we're running the icalendar import
		// -------------------------------------

		$this->cache['ical'] = TRUE;

		// -------------------------------------
		//	Get already-imported events
		// -------------------------------------

		$imported = $this->data->get_imported_events($calendar_id);

		// -------------------------------------
		//	Don't let EXT drop us early
		// -------------------------------------

		ee()->extensions->in_progress = '';

		// -------------------------------------
		//	Cycle through the URLs
		// -------------------------------------

		foreach ($urls as $url)
		{
			$ICAL = new vcalendar();
			$ICAL->parse($this->fetch_url($url));

			// -------------------------------------
			//	Iterate among the events
			// -------------------------------------

			while ($event = $ICAL->getComponent('vevent'))
			{
				// -------------------------------------
				//	Times
				// -------------------------------------

				$hour	 	= (isset($event->dtstart['value']['hour'])) ? 
									$event->dtstart['value']['hour'] : 00;
									
				$minute	 	= (isset($event->dtstart['value']['min'])) ? 
									$event->dtstart['value']['min'] : 00;
									
				$end_hour 	= (isset($event->dtend['value']['hour'])) ? 
									$event->dtend['value']['hour'] : $hour;
									
				$end_minute = (isset($event->dtend['value']['min'])) ? 
									$event->dtend['value']['min'] : $minute;

				// -------------------------------------
				//	Last-modified date
				// -------------------------------------

				if (isset($event->lastmodified['value']))
				{
					$lm_date  = $event->lastmodified['value']['year'] 	. 
								$event->lastmodified['value']['month'] 	. 
								$event->lastmodified['value']['day'] 	. 
								$event->lastmodified['value']['hour'] 	. 
								$event->lastmodified['value']['min'];
				}
				elseif (isset($event->dtstamp['value']))
				{
					$lm_date  = $event->dtstamp['value']['year']	.
								$event->dtstamp['value']['month']	.
								$event->dtstamp['value']['day']		.
								$event->dtstamp['value']['hour']	.
								$event->dtstamp['value']['min'];
				}
				else
				{
					$lm_date  = $event->created['value']['year']	.
								$event->created['value']['month']	.
								$event->created['value']['day']		.
								$event->created['value']['hour']	.
								$event->created['value']['min'];
				}

				// -------------------------------------
				//	Does this event already exist? Do we need to update?
				// -------------------------------------

				if (isset($imported[$event->uid['value']]))
				{
					// -------------------------------------
					//	Has the event been updated? No reason 
					//	to do any work if it's the same old stuff.
					// -------------------------------------

					if ($lm_date == $imported[$event->uid['value']]['last_mod'])
					{
						continue;
					}
					elseif ($lm_date == $imported[$event->uid['value']]['last_mod'])
					{
						continue;
					}

					$entry_id = $imported[$event->uid['value']]['entry_id'];
				}
				else
				{
					$entry_id = '';
				}

				// -------------------------------------
				//	Adjust CDT
				// -------------------------------------

				$CDT->change_datetime(
					$event->dtstart['value']['year'], 
					$event->dtstart['value']['month'], 
					$event->dtstart['value']['day'], 
					$hour, 
					$minute
				);
				
				if (isset($event->dtend['value']))
				{
					$CDT_end->change_datetime(
						$event->dtend['value']['year'], 
						$event->dtend['value']['month'], 
						$event->dtend['value']['day'], 
						$end_hour, 
						$end_minute
					);
				}
				else
				{
					$CDT_end->change_ymd($CDT->ymd);
					$CDT_end->change_time($end_hour, $end_minute);
				}

				// -------------------------------------
				//	Adjust to the correct timezone for thie calendar
				// -------------------------------------

				if ( ! isset($event->dtstart['params']['TZID']) OR 
					 $event->dtstart['params']['TZID'] == '')
				{
					if (isset($event->dtstart['value']['hour']))
					{
						$CDT->add_time($tz_offset);
						$CDT_end->add_time($tz_offset);
					}
					else
					{
						$CDT_end->add_day(-1);
					}
				}

				// -------------------------------------
				//	Variableification
				// -------------------------------------

				$title		= (isset($event->summary['value'])) ? $event->summary['value'] : 'No Title';
				$summary	= (isset($event->description) AND is_array($event->description) AND
				 			    isset($event->description[0]['value'])) ? 
									$event->description[0]['value'] : '';
				$location 	= (isset($event->location['value'])) ? $event->location['value'] : '';
				$rules		= $this->ical_rule_to_calendar_rule($event->rrule);
				$exceptions = array('date' => array());
				
				if (is_array($event->exdate) AND ! empty($event->exdate))
				{
					$exceptions = $this->ical_exdate_to_calendar_exception($event->exdate);
				}
				$recurs	 = (is_array($event->rrule) AND ! empty($event->rrule)) ? 'y' : 'n';

				// -------------------------------------
				//	Fix some linebreak problems
				// -------------------------------------

				$summary	= str_replace(array("\r", "\n"), '', $summary);
				$summary	= str_replace('\n', "\n", $summary);

				// -------------------------------------
				//	Set up $_POST
				// -------------------------------------

				$_POST = $post_data = array( 
					'site_id'		 			=> $this->data->get_site_id(),
					'author_id'		 			=> $author_id,
					'entry_id'					=> $entry_id,
					'weblog_id'		 			=> $channel_id,
					'channel_id'	 			=> $channel_id,
					'status'					=> 'open',
													//subtracting 2 days here
													//because people are seeing things post in the future? :/
					'entry_date'				=> date('Y-m-d H:i a', (ee()->localize->now - ((3600 * 24) * 2))),
					'title'			 			=> $title,
					'calendar_id'	 			=> $calendar_id,
					'field_id_' . $this->data->get_field_id('event_dates_and_options')	=> $calendar_id,
					'field_id_' . $this->data->get_field_id('event_summary')	=> $summary,
					'field_id_' . $this->data->get_field_id('event_location')	=> $location,
					'rule_id'		 			=> array(),
					'start_date'				=> array($CDT->ymd),
					'start_time'				=> array($CDT->hour.$CDT->minute),
					'end_date'					=> array($CDT_end->ymd),
					'end_time'					=> array($CDT_end->hour.$CDT_end->minute),
					'all_day'		 			=> (! isset($event->dtstart['value']['hour'])) ? 'y' : 'n',
					'rule_type'		 			=> $rules['rule_type'],
					'repeat_years'				=> $rules['repeat_years'],
					'repeat_months'	 			=> $rules['repeat_months'],
					'repeat_weeks'				=> $rules['repeat_weeks'],
					'repeat_days'	 			=> $rules['repeat_days'],
					'days_of_week'				=> $rules['days_of_week'],
					'relative_dow'				=> $rules['relative_dow'],
					'days_of_month'	 			=> $rules['days_of_month'],
					'months_of_year'			=> $rules['months_of_year'],
					'end_by'					=> $rules['end_by'],
					'end_after'		 			=> $rules['end_after'],
					'occurrences'	 			=> $exceptions,
					'expiration_date'			=> '',
					'comment_expiration_date' 	=> '',
					'allow_comments'			=> 'n'
				);

				// -------------------------------------
				//	Let Publish do its things
				// -------------------------------------

				if (APP_VER < 2.0)
				{
					$PB->submit_new_entry(FALSE); //<- LOOK HOW EASY IT USED TO BE >:|
				}
				else 
				{
					//this worked pre EE 2.1.3, then stopped working? *sigh*
					//now we have to do all of this mess manually for field 
					//settings before inserting new entries via the API
					//ee()->api_channel_fields->fetch_custom_channel_fields();
					
					//--------------------------------------------  
					//	Check for custom field group
					//--------------------------------------------

					$fg_query = ee()->db->query(
						"SELECT field_group 
						 FROM 	exp_channels 
						 WHERE 	channel_id = '" . ee()->db->escape_str($channel_id) . "'"
					);

					if ($fg_query->num_rows() > 0)
					{
						$field_group =  $fg_query->row('field_group');

						ee()->lang->loadfile('channel');
						ee()->lang->loadfile('content');
						ee()->load->model('field_model');
						ee()->load->model('channel_model');

						// Rudimentary handling of custom fields
						$field_query = ee()->channel_model->get_channel_fields($field_group);

						$dst_enabled = ee()->session->userdata('daylight_savings');
						
						foreach ($field_query->result_array() as $row)
						{
							$field_data = '';
							$field_dt = '';
							$field_fmt	= $row['field_fmt'];

							// Settings that need to be prepped			
							$settings = array(
								'field_instructions'	=> trim($row['field_instructions']),
								'field_text_direction'	=> ($row['field_text_direction'] == 'rtl') ? 'rtl' : 'ltr',
								'field_fmt'				=> $field_fmt,
								'field_dt'				=> $field_dt,
								'field_data'			=> $field_data,
								'field_name'			=> 'field_id_'.$row['field_id'],
								'dst_enabled'			=> $dst_enabled
							);

							$ft_settings = array();

							if (isset($row['field_settings']) AND strlen($row['field_settings']))
							{
								$ft_settings = unserialize(base64_decode($row['field_settings']));
							}

							$settings = array_merge($row, $settings, $ft_settings);

							ee()->api_channel_fields->set_settings($row['field_id'], $settings);
						}
					} 

					//now we can do the new entry
					ee()->api_channel_entries->submit_new_entry($channel_id, $post_data);
				}

				// -------------------------------------
				//	Update the imports table
				// -------------------------------------

				$data = array(	
					'calendar_id'	=> $calendar_id,
					'event_id'		=> $this->cache['ical_event_id'],
					'entry_id'		=> $this->cache['ical_entry_id'],
					'uid'			=> $event->uid['value'],
					'last_mod'		=> $lm_date
				);

				if ($entry_id != '')
				{
					$data['import_id'] = $imported[$event->uid['value']]['import_id'];
					$this->data->update_imported_event($data);
				}
				else
				{
					$data['import_id'] = '';
					$this->data->add_imported_event($data);
				}
			}
		}

		$this->data->update_ics_updated($calendar_id);

		ee()->extensions->end_script 	= FALSE;
		ee()->extensions->in_progress 	= ((APP_VER < 2.0) ? 'submit_new_entry_end' : 'entry_submission_end');

		return TRUE;
	}
	/* END import_ics_data() */

	// --------------------------------------------------------------------

	/**
	 * Convert icalendar data from icalcreator into a format this module can utilize
	 *
	 * @param array $ical_rule	iCalendar-style rule from iCalCreator
	 * @return	array
	 */

	protected function ical_rule_to_calendar_rule($ical_rule)
	{
		$rule = array(	'rule_type'		 => array('+'),
						'repeat_years'		=> array(),
						'repeat_months'	 => array(),
						'repeat_weeks'		=> array(),
						'repeat_days'	 => array(),
						'days_of_week'		=> array(),
						'relative_dow'		=> array(),
						'days_of_month'	 => array(),
						'months_of_year'	=> array(),
						'end_by'			=> array(),
						'end_after'		 => array()
						);

		if (! is_array($ical_rule))
		{
			return $rule;
		}

		$keys = array_keys($rule);
		$rule = array();

		foreach ($ical_rule as $k => $value)
		{
			$irule = $value['value'];

			if (! isset($irule['FREQ']))
			{
				continue;
			}

			foreach ($keys as $key)
			{
				$rule[$key][$k] = '';
			}

			// -------------------------------------
			//	Rule type
			// -------------------------------------

			// TODO: Support exceptions
			$rule['rule_type'][$k] = '+';

			// -------------------------------------
			//	Intervals
			// -------------------------------------

			switch(trim($irule['FREQ']))
			{
				case 'YEARLY' :
					$rule['repeat_years'][$k] = (isset($irule['INTERVAL'])) ? trim($irule['INTERVAL']) : 1;
					break;
				case 'MONTHLY' :
					$rule['repeat_months'][$k] = (isset($irule['INTERVAL'])) ? trim($irule['INTERVAL']) : 1;
					break;
				case 'WEEKLY' :
					$rule['repeat_weeks'][$k] = (isset($irule['INTERVAL'])) ? trim($irule['INTERVAL']) : 1;
					break;
				case 'DAILY' :
					$rule['repeat_days'][$k] = (isset($irule['INTERVAL'])) ? trim($irule['INTERVAL']) : 1;
					break;
			}

			// -------------------------------------
			//	Days of the week
			// -------------------------------------

			if (isset($irule['BYDAY']))
			{
				$dow = '';
				$relative_dow = '';
				if (isset($irule['BYDAY']['DAY']))
				{
					$days = array('SU' => 'U', 'MO' => 'M', 'TU' => 'T', 'WE' => 'W', 'TH' => 'R', 'FR' => 'F', 'SA' => 'S');
					if (is_array($irule['BYDAY'][0]))
					{
						// TODO: This currently only supports a single relative dow
						if (isset($irule['BYDAY'][0]['DAY'][0]))
						{
							$relative_dow .= trim($irule['BYDAY'][0]['DAY'][0]);
						}

						foreach ($irule['BYDAY'] as $day)
						{
							$dow .= $days[trim($day['DAY'])];
						}
					}
					else
					{
						$dow .= $days[trim($irule['BYDAY']['DAY'])];
						if (isset($irule['BYDAY'][0]))
						{
							$relative_dow .= trim($irule['BYDAY'][0]);
						}
					}
					$rule['days_of_week'][$k] = $dow;
					$rule['relative_dow'][$k] = $relative_dow;
				}
			}

			// -------------------------------------
			//	Days of the month
			// -------------------------------------

			if (isset($irule['BYMONTHDAY']))
			{
				$dom = '';
				
				//using single digits instead of 31 numbers? Why? -gf
				$days = array_flip(array(
					1,2,3,4,5,6,7,8,9,
					'A','B','C','D','E','F',
					'G','H','I','J','K','L',
					'M','N','O','P','Q','R',
					'S','T','U','V'
				));
				
				if (is_array($irule['BYMONTHDAY']))
				{
					foreach ($irule['BYMONTHDAY'] as $day)
					{
						$dom .= $days[trim($day)-1];
					}
				}
				else
				{
					$dom .= $days[trim($irule['BYMONTHDAY']) - 1];
				}
				$rule['days_of_month'][$k] = $dom;
			}

			// -------------------------------------
			//	Months of the year
			// -------------------------------------

			if (isset($irule['BYMONTH']))
			{
				$moy = '';
				//using single digits instead of 12 numbers? Why? -gf
				$months = array(1,2,3,4,5,6,7,8,9,'A','B','C');
				
				if (is_array($irule['BYMONTH']))
				{
					foreach ($irule['BYMONTH'] as $month)
					{
						$moy .= $months[trim($month)-1];
					}
				}
				else
				{
					$moy .= $months[trim($irule['BYMONTH']) - 1];
				}
				$rule['months_of_year'][$k] = $moy;
			}

			// -------------------------------------
			//	End by
			// -------------------------------------

			if (isset($irule['UNTIL']))
			{
				$rule['end_by'][$k] = trim($irule['UNTIL']['year']).trim($irule['UNTIL']['month']).trim($irule['UNTIL']['day']);
			}

			// -------------------------------------
			//	End after
			// -------------------------------------

			if (isset($irule['COUNT']))
			{
				$rule['end_after'][$k] = trim($irule['COUNT']);
			}
		}

		return $rule;
	}
	/* END ical_rule_to_calendar_rule() */

	// --------------------------------------------------------------------

	/**
	 * Convert ICAL exception dates to calendar exception
	 *
	 * @param array $exdate Exception dates
	 * @return	array
	 */

	protected function ical_exdate_to_calendar_exception($exdata)
	{
		$exceptions = array();

		foreach ($exdata as $item)
		{
			foreach ($item['value'] as $date)
			{
				$exceptions['date'][]	 = str_pad($date['year'], 4, '0', STR_PAD_LEFT).str_pad($date['month'], 2, '0', STR_PAD_LEFT).str_pad($date['day'], 2, '0', STR_PAD_LEFT);
				$exceptions['start_time'][] = '';
				$exceptions['end_time'][] = '';
				$exceptions['rule_type'][]	= '-';
			}
		}

		return (! empty($exceptions)) ? $exceptions : array('date' => array());
	}
	/* END ical_exdate_to_calendar_exception() */

	// --------------------------------------------------------------------

	/**
	 * Timezone menu
	 *
	 * @return	string
	 */

	public function timezone_menu($timezone = FALSE)
	{
		$menu = ee()->localize->timezone_menu(ee()->config->item(ee()->config->item('site_short_name').'_timezone'));

		preg_match_all('#<option value=\'(.+?)\'(?:.+)?\>\(UTC ?(.*?)\).*?</option>#m', $menu, $matches, PREG_SET_ORDER);
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
					$array[0] = str_replace(array('+', '-'), array('+0', '-0'), $array[0]);
				}
				$val = $array[0].$array[1];
				$replace = str_replace("'". $match[1] ."'", "'".$val."'", $match[0]);
			}

			$menu = str_replace($match[0], $replace, $menu);
		}

		if ($timezone === FALSE)
		{
			$timezone = (isset($this->cached_data['tz_offset'])) ? 
							$this->cached_data['tz_offset'] : $this->data->preference('tz_offset');
		}

		$selected = $timezone;

		$menu = str_replace("selected='selected'", '', $menu);
		if ($selected !== FALSE AND substr($selected, 1) != '0000')
		{
			$menu = str_replace("value='{$selected}'", "value='{$selected}' selected='selected'", $menu);
		}
		else
		{
			$menu = str_replace("value='0000'", "value='0000' selected='selected'", $menu);
		}

		return $menu;
	}
	/* END timezone_menu() */

	// --------------------------------------------------------------------

	/**
	 * Date widget
	 *
	 * @return	string
	 */

	public function date_widget($data)
	{
		$view_data 					= array();
		$multiple_day_occurrences	= array();
		$output 					= '';
		$event_edit_has_occurrences = FALSE;

		if (( ! empty($data) AND $data['edit_occurrence'] === TRUE) OR 
			(ee()->input->get('event_id') AND ee()->input->get('ymd')))
		{
			$occurrence_data = $this->data->fetch_occurrence_data_by_entry_id(
				ee()->input->get('entry_id'), 
				ee()->input->get('start_date'), 
				ee()->input->get('end_date'), 
				ee()->input->get('start_time'), 
				ee()->input->get('end_time')
			);
			
			foreach ($occurrence_data as $k => $v)
			{
				$view_data[$k] = $v;
			}
			
			$vars = array(	
				'entry_id',
				'event_id',
				'new_occurrence',
				'occurrence_id',
				'calendar_id',
				'site_id',
				'start_date',
				'end_date',
				'start_time',
				'end_time',
				'ymd',
				'all_day'
			);
			
			foreach ($vars as $var)
			{
				if (! isset($view_data[$var]))
				{
					$view_data[$var] = ee()->input->get($var);
				}
			}

			if ($data['edit_occurrence'] === FALSE AND 
				ee()->input->get('event_id'))
			{
				$view_data['event_id']	= $view_data['entry_id'];
			}

			if ($view_data['start_time'] == '0000' AND 	
				$view_data['end_time'] == '2400')
			{
				$view_data['time_range']	= ee()->lang->line('all_day');
			}
			else
			{
				if ( ! class_exists('Calendar_datetime'))
				{
					require_once CALENDAR_PATH.'calendar.datetime'.EXT;
				}
				$CDT	= new Calendar_datetime();

				$CDT->change_time(
					substr(
						$view_data['start_time'], 
						0, 
						strlen($view_data['start_time']) - 2
					), 
					substr($view_data['start_time'], -2)
				);
				
				$start	= ($this->data->preference('clock_type') == '12') ? 
							$CDT->format_date_string('h:i a') : 
							$CDT->format_date_string('H:i');
				
				$CDT->change_time(
					substr(
						$view_data['end_time'], 
						0, 
						strlen($view_data['end_time']) - 2
					), 
					substr($view_data['end_time'], -2)
				);
				
				$end	= ($this->data->preference('clock_type') == '12') ? 
							$CDT->format_date_string('h:i a') : 
							$CDT->format_date_string('H:i');

				$view_data['time_range']	= $start.'&ndash;'.$end;
			}

			$output .= self::edit_occurrence($view_data);
		}
		else
		{
			//this test will show a checkbox on an events page if it has edited occurances
			//the checkbox is an option to delete those occurances if the dates are being changed
			if (isset($data['entry_id']))
			{
				$query = ee()->db->query(
					"SELECT entry_id, event_id 
					 FROM 	exp_calendar_events_occurrences 
					 WHERE 	event_id ='" . ee()->db->escape_str($data['entry_id']) . "'"
				);

				//its only true of the occurance is different than its parent
				if ($query->num_rows() > 0)
				{
					foreach($query->result_array() as $row)
					{
						if ($row['entry_id'] != $row['event_id'])
						{
							$event_edit_has_occurrences = TRUE;
						}
					}
				}
			}
			
			if (isset($data['calendar_id']))
			{
				$view_data['calendar_id'] = $data['calendar_id'];
			}
			else
			{
				$entry_id = $this->data->get_event_entry_id_by_channel_entry_id(ee()->input->get('entry_id'));
				$view_data['calendar_id'] = $this->data->get_calendar_id_by_event_entry_id($entry_id);
				$view_data['calendar_id'] = (isset($view_data['calendar_id'][$entry_id])) ?
				 								$view_data['calendar_id'][$entry_id] : '';
			}

			$output .= self::calendar_select($view_data);

			if ($this->data->calendars_exist() === TRUE)
			{
				$count = 0;
				
				//--------------------------------------------  
				//	Does this have main rules?
				//--------------------------------------------
				
				if ( ! empty($data['rules']))
				{
					foreach ($data['rules'] as $id => $rule)
					{						
						$count++;
						
						if ($count == 1 AND 
							$data['start_date'] != $rule['start_date'])
						{
							$view_data['rule'] = array( 
								'rule_id' 			=> '',
								'rule_type' 		=> '+',
								'repeat_years' 		=> '',
								'repeat_months' 	=> '',
								'repeat_days' 		=> '',
								'repeat_weeks' 		=> '',
								'days_of_week' 		=> '',
								'relative_dow' 		=> '',
								'days_of_month' 	=> '',
								'months_of_year' 	=> '',
								'repeat_dates' 		=> '',
								'end_by' 			=> '',
								'end_after' 		=> '',
								'start_date' 		=> $data['start_date'],
								'end_date' 			=> $data['end_date'],
								'start_time' 		=> $data['start_time'],
								'end_time' 			=> $data['end_time'],
								'all_day' 			=> $data['all_day'],
								'recurs' 			=> $data['recurs']
							);
							
							$view_data['first']		 = TRUE;
							$view_data['rule_id']	 = 0;
							$view_data['rule_number'] = $count;
							$output .= self::rule($view_data);
							$count++;
							
							if (! isset($rule['recurs']))
							{
								$rule['recurs'] = '';
							}
							
							$view_data = array( 
								'start_date'		=> $rule['start_date'],
								'all_day'	 		=> $rule['all_day'],
								'start_time'		=> (isset($rule['start_time'])) ? $rule['start_time'] : '',
								'end_date'			=> $rule['end_date'],
								'end_time'			=>(isset($rule['end_time'])) ? $rule['end_time'] : '',
								'recurs'			=> $rule['recurs'],
								'first'		 		=> TRUE,
								'rule_id'	 		=> 0,
								'rule_number' 		=> $count,
								'rule'				=> $rule
							);
							
							$output .= self::rule($view_data);
						}
						else
						{
							$view_data = array( 
								'first'		 	=> ($count == 1) ? TRUE : FALSE,
								'rule_id'		=> $id,
								'rule_number'	=> $count,
								'start_date'	=> (isset($rule['start_date'])) ? $rule['start_date'] 	: '',
								'all_day'	 	=> (isset($rule['all_day'])) 	? $rule['all_day'] 		: '',
								'start_time'	=> (isset($rule['start_time'])) ? $rule['start_time'] 	: '',
								'end_date'		=> (isset($rule['end_date'])) 	? $rule['end_date'] 	: '',
								'end_time'		=> (isset($rule['end_time'])) 	? $rule['end_time'] 	: '',
								'recurs'		=> (isset($data['recurs'])) 	? $data['recurs'] 		: ''
							);
							
							if (! isset($rule['recurs']))
							{
								$rule['recurs'] = '';
							}
							
							$rule['all_day'] = $rule['all_day'];
							$view_data['rule'] = $rule;
							$output .= self::rule($view_data);
						}
					}
				}
				
				//--------------------------------------------  
				//	IS this an occurrence set?
				//--------------------------------------------
				
				elseif ((! isset($data['start_date']) OR 
						 $data['start_date'] == $data['end_date'] 
						) AND 
						! empty($data['occurrences']) AND 
						isset($data['occurrences'][$data['start_time']][$data['end_time']])
					)
				{
					
					$count++;

					$view_data['occurrences']['dates']		= array();
					$view_data['occurrences']['dates'][]	= $data['start_date'];
					$view_data['start_date']				= $data['start_date'];
					$view_data['start_time']				= $data['start_time'];
					$view_data['end_date']					= $data['end_date'];
					$view_data['end_time']					= $data['end_time'];
					$view_data['all_day']			 		= $data['all_day'];
					
					$dates_added = FALSE;
					
					foreach ($data['occurrences'][$data['start_time']][$data['end_time']] as $item)
					{
						
						//if this is a single day item, lets put them all together
						if ($item['start_date'] == $item['end_date'])
						{
							$dates_added = TRUE;
							$view_data['occurrences']['dates'][] = $item['start_date'];
						}
						//otherwise it needs its own occurrence
						else
						{
							$multiple_day_occurrences[] = $item;
						}
					}
					
					$view_data['first']						= TRUE;
					$view_data['rule_number'] 				= $count;
					
					//we need to make sure any of the items actually got added
					if ($dates_added)
					{
						$output .= self::occurrence($view_data);
					}	
					
					//need to remove this so nothing else hits it				
					unset($data['occurrences'][$data['start_time']][$data['end_time']]);
				}
				
				//--------------------------------------------  
				//	just a rule then?
				//--------------------------------------------
				
				else
				{
					$count++;
					$rule = array(	
						'start_date'	=> (isset($data['start_date'])) ? $data['start_date'] : '',
						'all_day'	 	=> (isset($data['all_day'])) ? $data['all_day'] : '',
						'start_time'	=> (isset($data['start_time'])) ? $data['start_time'] : '',
						'end_date'		=> (isset($data['end_date'])) ? $data['end_date'] : '',
						'end_time'		=> (isset($data['end_time'])) ? $data['end_time'] : '',
						'recurs'		=> '', //(isset($data['recurs'])) ? $data['recurs'] : '',
						'rule'			=> array( 
							'rule_id' 			=> '',
							'rule_type' 		=> '',
							'repeat_years' 		=> '',
							'repeat_months' 	=> '',
							'repeat_days' 		=> '',
							'repeat_weeks' 		=> '',
							'days_of_week' 		=> '',
							'relative_dow' 		=> '',
							'days_of_month' 	=> '',
							'months_of_year' 	=> '',
							'repeat_dates'		=> '',
							'end_by' 			=> '',
							'end_after' 		=> '',
							'start_date' 		=> '',
							'end_date' 			=> '',
							'start_time' 		=> '',
							'end_time' 			=> '',
							'all_day' 			=> '',
							'recurs' 			=> ''
						)
					);
					
					foreach ($rule as $k => $v)
					{
						$view_data[$k] = $v;
					}
					
					$view_data['first']			= TRUE;
					$view_data['rule_id']		= 0;
					$view_data['rule_number']	= $count;
					$output 					.= self::rule($view_data);
				}

				//--------------------------------------------  
				//	Do we still have some possible occurrences left?
				//--------------------------------------------

				if ( ! empty($data['occurrences']))
				{										
					foreach ($data['occurrences'] as $start_time => $a)
					{
						foreach ($a as $end_time => $items)
						{
							$view_data = array();
							
							if (count($items) == 1)
							{
								$count++;
								$item = $items[0];
								$rule = array(	
									'start_date'	=> (isset($item['start_date'])) ? $item['start_date'] 	: '',
									'all_day'	 	=> (isset($item['all_day'])) 	? $item['all_day'] 		: '',
									'start_time'	=> (isset($item['start_time'])) ? $item['start_time'] 	: '',
									'end_date'		=> (isset($item['end_date'])) 	? $item['end_date']		: '',
									'end_time'		=> (isset($item['end_time'])) 	? $item['end_time'] 	: '',
									'recurs'		=> (isset($item['recurs'])) 	? $item['recurs'] 		: '',
									'rule'			=> array( 
										'rule_id' 			=> '',
										'rule_type' 		=> '',
										'repeat_years' 		=> '',
										'repeat_months' 	=> '',
										'repeat_days' 		=> '',
										'repeat_weeks' 		=> '',
										'days_of_week' 		=> '',
										'relative_dow' 		=> '',
										'days_of_month' 	=> '',
										'months_of_year' 	=> '',
										'repeat_dates' 		=> '',
										'end_by' 			=> '',
										'end_after' 		=> '',
										'start_date' 		=> '',
										'end_date' 			=> '',
										'start_time' 		=> '',
										'end_time' 			=> '',
										'all_day' 			=> '',
										'recurs' 			=> ''
									)
								);
								
								foreach ($rule as $k => $v)
								{
									$view_data[$k] = $v;
								}
								$view_data['first']		 	= FALSE;
								$view_data['rule_id']	 	= 0;
								$view_data['rule_number'] 	= $count;
								$output .= self::rule($view_data);
							}
							else
							{
								$view_data['occurrences']['dates']	= array();
								$view_data['start_time']			= $start_time;
								$view_data['end_time']				= $end_time;
								
								$dates_added = FALSE;
								
								foreach ($items as $k => $item)
								{
									//if this is a single day item, lets put them all together
									if ($item['start_date'] == $item['end_date'])
									{
										$dates_added = TRUE;
										$view_data['occurrences']['dates'][]	= $item['start_date'];
										$view_data['all_day']				 	= $item['all_day'];
									}
									//otherwise it needs its own occurrence
									else
									{
										$multiple_day_occurrences[] = $item;
									}
								}
																								
								//we need to make sure any of the items actually got added
								if ($dates_added)
								{
									$count++;
									
									$view_data['first']		 = FALSE;
									$view_data['rule_number'] = $count;
									
									$output .= self::occurrence($view_data);
								}
							}
						}
					}
				}

				//--------------------------------------------  
				//	do we have multi day occurrences?
				//--------------------------------------------
				
				if ( ! empty($multiple_day_occurrences))	
				{					
					foreach($multiple_day_occurrences as $item)
					{						
						$view_data = array();
						$count++;
						$rule = array(	
							'start_date'	=> (isset($item['start_date'])) ? $item['start_date'] 	: '',
							'all_day'	 	=> (isset($item['all_day'])) 	? $item['all_day'] 		: '',
							'start_time'	=> (isset($item['start_time'])) ? $item['start_time'] 	: '',
							'end_date'		=> (isset($item['end_date'])) 	? $item['end_date']		: '',
							'end_time'		=> (isset($item['end_time'])) 	? $item['end_time'] 	: '',
							'recurs'		=> (isset($item['recurs'])) 	? $item['recurs'] 		: '',
							'rule'			=> array( 
								'rule_id' 			=> '',
								'rule_type' 		=> '',
								'repeat_years' 		=> '',
								'repeat_months' 	=> '',
								'repeat_days' 		=> '',
								'repeat_weeks' 		=> '',
								'days_of_week' 		=> '',
								'relative_dow' 		=> '',
								'days_of_month' 	=> '',
								'months_of_year' 	=> '',
								'repeat_dates' 		=> '',
								'end_by' 			=> '',
								'end_after' 		=> '',
								'start_date' 		=> '',
								'end_date' 			=> '',
								'start_time' 		=> '',
								'end_time' 			=> '',
								'all_day' 			=> '',
								'recurs' 			=> ''
							)
						);
						
						foreach ($rule as $k => $v)
						{
							$view_data[$k] = $v;
						}
						
						$view_data['first']		 	= FALSE;
						$view_data['rule_id']	 	= 0;
						$view_data['rule_number'] 	= $count;
						$output .= self::rule($view_data);
					}
				}

				if (! empty($data['exceptions']))
				{
					$count++;
					$view_data = array();
					foreach ($data['exceptions'] as $k => $item)
					{
						$view_data['exceptions']['dates'][] = $item['start_date'];
					}
					$view_data['first']		 = FALSE;
					$view_data['rule_number'] = $count;
					$output .= self::cal_exception($view_data);
				}
			}
		}

		$view_data['start_time']	= (isset($view_data['start_time'])) ? str_pad($view_data['start_time'], 4, '0', STR_PAD_LEFT) : '0000';
		$view_data['end_time']		= (isset($view_data['end_time'])) ? str_pad($view_data['end_time'], 4, '0', STR_PAD_LEFT) : '0000';

		return $this->view('calendar_fields.html', array(
			'event_edit_has_occurrences' 			=> $event_edit_has_occurrences, 
			'output' 								=> $output,
			'lang_remove_edited_occurrences'		=> ee()->lang->line('remove_edited_occurrences'),
			'lang_remove_edited_occurrences_desc'	=> ee()->lang->line('remove_edited_occurrences_desc'),
		), TRUE);
	}
	/* END date_widget() */

	// --------------------------------------------------------------------

	/**
	 * Calendar select list
	 *
	 * @param array $data Array of data
	 * @return	string
	 */

	public function calendar_select($data)
	{
		$calendar_list = $this->data->get_calendar_list();

		// -------------------------------------
		//	calendar permissions
		// -------------------------------------

		$group_id = ee()->session->userdata['group_id'];

		//no 1.x and no admin
		if (APP_VER >= 2.0 AND $group_id != 1)
		{
			ee()->load->library('calendar_permissions');

			if (ee()->calendar_permissions->enabled())
			{
				$calendars_allowed 	= ee()->calendar_permissions->get_allowed_calendars_for_group($group_id);

				//if they are in the allowed all group, let them through
				//if they are in the deined all group, lets not do this work
				if ( ! empty($calendars_allowed))
				{
					foreach ($calendar_list as $calendar_id => $calendar_data)
					{
						if ( ! in_array($calendar_id, $calendars_allowed))
						{
							unset($calendar_list[$calendar_id]);
						}
					}
				}

				//if they are in the allowed all group, let cal act as normal
				//if they are in the deined all, then error
				//if they are not denied all, but no calendars can be chosen, error
				if (empty($calendars_allowed) OR empty($calendar_list))
				{
					return $this->EE->output->show_user_error(
						'general', array($this->EE->lang->line('no_permissions_for_any_calendars'))
					);
				}
			}
		}
		//END calendar permissions

		$data['calendars'] = $calendar_list;
		return $this->view('publish_form/calendar_select.html', $data, TRUE);
	}
	/* END calendar_select() */

	// --------------------------------------------------------------------

	/**
	 * Edit occurrence
	 *
	 * @param array $data Array of data
	 * @return	string
	 */

	protected function edit_occurrence($data)
	{
		$view_data = array();
		$view_data['entry_id']			= $data['entry_id'];
		$view_data['event_id']			= $data['event_id'];
		$view_data['is_new']			= ($data['new_occurrence']) ? TRUE : FALSE;
		$view_data['occurrence_id']	 	= $data['occurrence_id'];
		$view_data['calendar_id']	 	= $data['calendar_id'];
		$view_data['site_id']		 	= $data['site_id'];
		$view_data['end_date']			= $data['end_date'];
		$view_data['start_time']		= $data['start_time'];
		$view_data['end_time']			= $data['end_time'];
		$view_data['all_day']			= $data['all_day'];
		$entry_data					 	= $this->data->fetch_entry_details_by_entry_id(array($data['event_id']));

		$view_data['entry_data']		= $entry_data[$data['event_id']];

		if ( ! class_exists('Calendar_datetime'))
		{
			require_once CALENDAR_PATH.'calendar.datetime'.EXT;
		}
		
		$view_data['ymd'] 	= (empty($occurrence_data)) ? $data['start_date'] : $occurrence_data['start_date'];
		$date			 	= Calendar_datetime::ymd_to_array($view_data['ymd']);
		$view_data['date']	= $date['year'].'-'.$date['month'].'-'.$date['day'].' '.$data['time_range'];

		return $this->view('publish_form/edit_occurrence.html', $view_data, TRUE);
	}
	/* END edit_occurrence() */

	// --------------------------------------------------------------------

	/**
	 * Rule
	 *
	 * @param array $data Array of data
	 * @return	string
	 */

	protected function rule($data)
	{
		if (! isset($data['start_date']) OR $data['start_date'] == '')
		{
			$data['start_date'] = date('Ymd', ee()->localize->now);
			$data['start_time'] = date('Hi', ee()->localize->now);
			$data['end_date'] 	= $data['start_date'];
			$data['end_time'] 	= date('Hi', ee()->localize->now);
			$data['all_day']	= 'n';
		}

		$rule = $data['rule'];

		foreach ($rule as $k => $v)
		{
			if ($v == '' AND isset($data[$k]))
			{
				$rule[$k] = $data[$k];
			}
		}
		
		//rules in the rule table don't have the recursive flag
		if ($rule['rule_id'] == '0' AND ( ! isset($data['recurs']) OR $data['recurs'] != 'y'))
		{	
			$repeat_type = '';
		}
		elseif ($rule['repeat_years'] > 0)
		{	
			$repeat_type = 'yearly';
		}
		elseif ($rule['repeat_months'] > 0)
		{	
			$repeat_type = 'monthly';
		}
		elseif ($rule['days_of_month'] != '')
		{	
			$repeat_type = 'monthly';
		}
		elseif ($rule['relative_dow'] != '')
		{	
			$repeat_type = 'monthly';
		}
		elseif ($rule['repeat_weeks'] > 0)
		{
			$repeat_type = 'weekly';
		}
		elseif ($rule['repeat_days'] > 0)
		{	
			$repeat_type = 'daily';
		}
		else
		{	
			$repeat_type = 'select_dates';
		}

		$start_time 	= ($rule['start_time'] != '') ? $rule['start_time'] : $data['start_time'];
		$start_ampm 	= 'am';
		$end_time 		= ($rule['end_time'] != '') ? $rule['end_time'] : $data['end_time'];
		$end_ampm		= 'am';
		$time_format 	= ($this->data->preference('clock_type') == '24') ? '24' : '12';
		$hour 			= '';
		$minute 		= '';

		if ($start_time > 0)
		{
			$hour = str_pad(substr($start_time, 0, strlen($start_time) - 2), 2, '0', STR_PAD_LEFT);
			if ($time_format == '12')
			{
				if ($hour >= 12)
				{
					if ($hour > 12)
					{
						$hour -= 12;
					}
					$start_ampm = 'pm';
				}
				elseif ($hour == 0)
				{
					$hour = 12;
				}
			}
			$minute = str_pad(substr($start_time, -2, 2), 2, '0', STR_PAD_LEFT);
			$start_time = $hour.':'.$minute;
		}
		elseif ($time_format == '12')
		{
			$start_time = '12:00';
		}
		else
		{
			$start_time = '00:00';
		}

		if ($end_time > 0)
		{
			$hour = str_pad(substr($end_time, 0, strlen($end_time) - 2), 2, '0', STR_PAD_LEFT);
			if ($time_format == '12')
			{
				if ($hour >= 12)
				{
					if ($hour > 12)
					{
						$hour -= 12;
					}
					$end_ampm = 'pm';
				}
				elseif ($hour == 0)
				{
					$hour = 12;
				}
			}
			$minute = str_pad(substr($end_time, -2, 2), 2, '0', STR_PAD_LEFT);
			$end_time = $hour.':'.$minute;
		}
		elseif ($time_format == '12')
		{
			$end_time = '12:00';
		}
		else
		{
			$end_time = '00:00';
		}

		$view_data = array( 
			'rule'			=> $rule,
			'repeat_type' 	=> $repeat_type,
			'all_day'	 	=> $rule['all_day'],
			'start_date'	=> $rule['start_date'],
			'start_time'	=> $start_time,
			'start_ampm'	=> $start_ampm,
			'end_date'		=> $rule['end_date'],
			'end_time'		=> $end_time,
			'end_ampm'		=> $end_ampm,
			'time_format' 	=> $time_format,
			'hour'			=> $hour,
			'minute'		=> $minute,
			'recurs'		=> $rule['recurs'],
			'rule_number' 	=> $data['rule_number'],
			'first'		 	=> $data['first'],
			'rule_id'	 	=> $data['rule_id']
		);

		return $this->view('publish_form/rule.html', $view_data, TRUE);
	}
	/* END rule() */

	// --------------------------------------------------------------------

	/**
	 * Occurrence
	 *
	 * @param array $data Array of data
	 * @return	string
	 */

	protected function occurrence($data)
	{
		$dates = $data['occurrences']['dates'];

		$start_time	 = '';
		$start_ampm	 = 'am';
		$end_time	 = '';
		$end_ampm	 = 'am';
		$time_format	= ($this->data->preference('clock_type') == '24') ? '24' : '12';
		
		if ($data['start_time'] > 0)
		{
			$hour = substr($data['start_time'], 0, strlen($data['start_time']) - 2);
			if ($time_format == '12')
			{
				if ($hour >= 12)
				{
					if ($hour > 12)
					{
						$hour -= 12;
					}
					$start_ampm = 'pm';
				}
				elseif ($hour == 0)
				{
					$hour = 12;
				}
			}
			$minute	 = substr($data['start_time'], -2, 2);
			$start_time = $hour.':'.$minute;
		}
		elseif ($time_format == '12')
		{
			$start_time = '12:00';
		}
		else
		{
			$start_time = '00:00';
		}

		if ($data['end_time'] > 0)
		{
			$hour = substr($data['end_time'], 0, strlen($data['end_time']) - 2);
			if ($time_format == '12')
			{
				if ($hour >= 12)
				{
					if ($hour > 12)
					{
						$hour -= 12;
					}
					$end_ampm = 'pm';
				}
				elseif ($hour == 0)
				{
					$hour = 12;
				}
			}
			$minute	 = substr($data['end_time'], -2, 2);
			$end_time = $hour.':'.$minute;
		}
		elseif ($time_format == '12')
		{
			$end_time = '12:00';
		}
		else
		{
			$end_time = '00:00';
		}

		if ( ! array_key_exists('all_day', $data))
		{
			$data['all_day']	= 'n';
		}
		
		$data['time_format']	= $time_format;
		$data['start_time']	 	= $start_time;
		$data['start_ampm']	 	= $start_ampm;
		$data['end_time']	 	= $end_time;
		$data['end_ampm']	 	= $end_ampm;
		$data['dates']			= $dates;

		return $this->view('publish_form/occurrence.html', $data, TRUE);
	}
	/* END occurrence() */

	// --------------------------------------------------------------------

	/**
	 * Exception
	 *
	 * @param array $data Array of data
	 * @return	string
	 */

	protected function cal_exception($data)
	{
		$dates 					= $data['exceptions']['dates'];

		$start_time			 	= '12:00';
		$start_ampm			 	= 'am';
		$end_time			 	= '12:00';
		$end_ampm			 	= 'am';
		$data['time_format']	= ($this->data->preference('clock_type') == '24') ? '24' : '12';

		$data['start_time'] 	= $start_time;
		$data['start_ampm'] 	= $start_ampm;
		$data['end_time'] 		= $end_time;
		$data['end_ampm'] 		= $end_ampm;
		$data['dates']			= $dates;

		return $this->view('publish_form/exception.html', $data, TRUE);
	}
	/* END cal_exception() */

	// --------------------------------------------------------------------

	/**
	 * Datepicker Javascript
	 *
	 * @param 	bool 	allows the turning off of some options for 2.x
	 * @return	string
	 */

	public function datepicker_js($include_jqui = TRUE)
	{
		$output = '';

		$app_ver = substr((string) APP_VER, 0, 1);

		$output .= "
			<script type='text/javascript'>
				var SSCalendar					= {};
				SSCalendar.version 				= {$app_ver};
				SSCalendar.dateFormat 			= 'DATEFORMAT';
				SSCalendar.firstDay				= FIRSTDAY;
				SSCalendar.dateFormatSettings 	= {
					dayNamesMin		: [DAYNAMESMIN],
					dayNamesShort	: [DAYNAMESSHORT],
					dayNames		: [DAYNAMESLONG],
					monthNamesShort	: [MONTHNAMESSHORT],
					monthNames		: [MONTHNAMESLONG]
				};
				SSCalendar.lang 				= {
					[LANG]
				};
				// if there is not a default_entry_title set 
				// when you insert entries into exp_channels
				// it defaults to NULL instead of a blank string
				// we fixed it in an update, but this is just in case
				setTimeout(function(){
					if (typeof EE !== 'undefined' && 
						typeof EE.publish !== 'undefined' && 
						( typeof EE.publish.default_entry_title !== 'undefined' ||
						  ! EE.publish.default_entry_title )
					)
					{
						EE.publish.default_entry_title = '';
					}
				}, 0);
			</script>";

		if ($include_jqui AND (REQ == 'PAGE' OR APP_VER < 2.0))
		{		
			$output .= "\n<script type='text/javascript' src='" . 
							CALENDAR_URL_THEMES . 
							"js/ui.datepicker.js'></script>";
		}
		//have to make SURE that we are in 2.x and in the CP before we do this.
		else if (APP_VER >= 2.0 AND REQ == 'CP' AND isset(ee()->cp))
		{
			ee()->load->library('javascript');
			ee()->cp->add_js_script(array('ui' => 'datepicker'));
		}	
		
		$publish_js =  "\n<script type='text/javascript' src='" . 
							CALENDAR_URL_THEMES . 
							"js/calendar_publish.js'></script>";

		if (APP_VER >= 2.0 AND REQ == 'CP')
		{
			ee()->cp->add_to_head($publish_js); 
		}
		else
		{
			$output .= $publish_js;
		}

		$daynamesmin = $daynamesshort = $daynameslong = $monthnamesshort = $monthnameslong = array();
		
		for ($i = 0; $i < 7; $i++)
		{
			$daynamesmin[] = ee()->lang->line('day_'.$i.'_2');
			$daynamesshort[] = ee()->lang->line('day_'.$i.'_3');
			$daynameslong[] = ee()->lang->line('day_'.$i.'_full');
		}

		for ($i = 1; $i <= 12; $i++)
		{
			$monthnamesshort[] = ee()->lang->line('month_'.$i.'_3');
			$monthnameslong[] = ee()->lang->line('month_'.$i.'_full');
		}

		$find 	= array(
			'DATEFORMAT', 
			'DAYNAMESMIN', 
			'DAYNAMESSHORT', 
			'DAYNAMESLONG', 
			'MONTHNAMESSHORT', 
			'MONTHNAMESLONG', 
			'FIRSTDAY'
		);
		
		$replace = array( 
			$this->data->preference('date_format'),
			"'".implode("', '", $daynamesmin)."'",
			"'".implode("', '", $daynamesshort)."'",
			"'".implode("', '", $daynameslong)."'",
			"'".implode("', '", $monthnamesshort)."'",
			"'".implode("', '", $monthnameslong)."'",
			$this->data->preference('first_day_of_week')
		);
		
		$output = str_replace($find, $replace, $output);

		$find = array(
			'type' 				=> ee()->lang->line('type'),
			'include' 			=> ee()->lang->line('include'),
			'exclude' 			=> ee()->lang->line('exclude'),
			'repeat' 			=> ee()->lang->line('repeat'),
			'none' 				=> ee()->lang->line('none'),
			'daily' 			=> ee()->lang->line('daily'),
			'weekly' 			=> ee()->lang->line('weekly'),
			'monthly'			=> ee()->lang->line('monthly'),
			'yearly' 			=> ee()->lang->line('yearly'),
			'select_dates' 		=> ee()->lang->line('select_dates'),
			'all_day_event' 	=> ee()->lang->line('all_day_event'),
			'from' 				=> ee()->lang->line('from'),
			'to' 				=> ee()->lang->line('to'),
			'every' 			=> ee()->lang->line('every'),
			'day_s' 			=> ee()->lang->line('day_s'),
			'week_s_on' 		=> ee()->lang->line('week_s_on'),
			'at' 				=> ee()->lang->line('at'),
			'month_s_by_day_of' => ee()->lang->line('month_s_by_day_of'),
			'x1st' 				=> ee()->lang->line('1st'),
			'x2nd' 				=> ee()->lang->line('2nd'),
			'x3rd' 				=> ee()->lang->line('3rd'),
			'x4th' 				=> ee()->lang->line('4th'),
			'x5th' 				=> ee()->lang->line('5th'),
			'only_on' 			=> ee()->lang->line('only_on'),
			'year_s' 			=> ee()->lang->line('year_s'),
			'end' 				=> ee()->lang->line('end'),
			'never' 			=> ee()->lang->line('never'),
			'by_date' 			=> ee()->lang->line('by_date'),
			'after' 			=> ee()->lang->line('after'),
			'time_s' 			=> ee()->lang->line('time_s'),
			'am' 				=> ee()->lang->line('AM'),
			'pm' 				=> ee()->lang->line('PM'),
			'day_1_3'			=> ee()->lang->line('day_1_3'),
			'day_2_3'			=> ee()->lang->line('day_2_3'),
			'day_3_3'			=> ee()->lang->line('day_3_3'),
			'day_4_3'			=> ee()->lang->line('day_4_3'),
			'day_5_3'			=> ee()->lang->line('day_5_3'),
			'day_6_3'			=> ee()->lang->line('day_6_3'),
			'day_0_3'			=> ee()->lang->line('day_0_3'),
			'today' 			=> ee()->lang->line('today'),
			'yesterday' 		=> ee()->lang->line('yesterday'),
			'tomorrow' 			=> ee()->lang->line('tomorrow'),
			'day' 				=> ee()->lang->line('day'),
			'week'				=> ee()->lang->line('week'),
			'month' 			=> ee()->lang->line('month'),
			'year' 				=> ee()->lang->line('year'),
			'ago' 				=> ee()->lang->line('ago'),
			'begin' 			=> ee()->lang->line('begin'),
			'last'				=> ee()->lang->line('last')
		);
		
		$lang = '';
		
		foreach ($find as $k => $v)
		{
			$lang .= "'{$k}': '$v',\n";
		}

		$lang = substr($lang, 0, strlen($lang) - 2)."\n";
		
		$output = str_replace('[LANG]', $lang, $output);

		return $output;
	}
	/* END datepicker_js() */

	// --------------------------------------------------------------------

	/**
	 * Datepicker CSS
	 *
	 * @param 	bool 	allows the turning off of some options for 2.x
	 * @return	string
	 */
	public function datepicker_css($include_jqui = TRUE)
	{
		$output = '';

		if ($include_jqui AND (REQ == 'PAGE' OR APP_VER < 2.0))
		{
			$output .= "\n<link rel='stylesheet' href='" . 
							CALENDAR_URL_THEMES . 
							"css/ui-lightness/jquery-ui.custom.css' " . 
							"type='text/css' />";
		}

		$publish_css = "\n<link rel='stylesheet' href='" . 
							CALENDAR_URL_THEMES . 
							"css/calendar_publish.css' type='text/css' />";

		if (APP_VER < 2.0)
		{
			//1.x has its own CSS because poop
			$output .= str_replace(
				'calendar_publish.css', 
				'calendar_publish_1.x.css', 
				$publish_css
			);
		}
		//2.x CP
		else if (REQ == 'CP')
		{
			//we want to add the css to the head so 
			//it can async load faster
			ee()->cp->add_to_head($publish_css);
		}
		//2.x
		else
		{
			$output .= $publish_css;
		}

		return $output;
	}
	/* END datepicker_css() */

	// --------------------------------------------------------------------

	/**
	 * Calendar weblog shortname
	 *
	 * @return string
	 */
	public function calendar_channel_shortname()
	{
		// Fetch the weblog
		$channel 		= $this->data->preference('calendar_weblog');		
		$channel 		= ($channel !== FALSE AND is_string($channel)) ? 
								explode('|', $channel) : array();
		$channel_data 	= $this->data->get_channel_basics();
		$names 			= array();
	
		foreach ($channel_data as $w)
		{
			if (in_array($w['weblog_id'], $channel))
			{
				$names[] = $w['blog_name'];
			}
		}
	
		return implode('|', $names);
	}
	/* END calendar_channel_shortname() */

	// --------------------------------------------------------------------

	/**
	 * Event weblog shortname
	 *
	 * @return string
	 */
	public function event_channel_shortname()
	{
		 // Fetch the weblog
		$channel 		= $this->data->preference('event_weblog');
		$channel 		= ($channel !== FALSE AND is_string($channel)) ? 
							explode('|', $channel) : array();		
		$channel_data 	= $this->data->get_channel_basics();
		$names 			= array();
	
		foreach ($channel_data as $c)
		{
			if (in_array($c['weblog_id'], $channel))
			{
				$names[] = $c['blog_name'];
			}
		}
	
		return implode('|', $names);
	}
	/* END event_channel_shortname() */

	// --------------------------------------------------------------------

}
/* END Calendar_actions Class */

?>