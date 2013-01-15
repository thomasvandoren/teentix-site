<?php

/**
 * Calendar - Language
 *
 * @package		Solspace:Calendar
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2013, Solspace, Inc.
 * @link		http://solspace.com/docs/calendar
 * @license		http://www.solspace.com/license_agreement
 * @version		1.8.0
 * @filesource	calendar/language/english/lang.calendar.php
 */

$L = $lang = array(

//----------------------------------------
// Required for MODULES page
//----------------------------------------

'calendar_module_name' =>
'Calendar',

'calendar_module_description' =>
'A full-featured calendar module for ExpressionEngine',

'calendar_module_version' =>
'Calendar',

//----------------------------------------
//  Installation
//----------------------------------------

'calendars_field_group_already_exists' =>
'The field group "Calendar: Calendars" already exists.',

'events_field_group_already_exists' =>
'The field group "Calendar: Events" already exists.',

'calendars_weblog_already_exists' =>
'The weblog "Calendar: Calendars" already exists.',

'events_weblog_already_exists' =>
'The weblog "Calendar: Events" already exists.',

'cannot_install' =>
'The Calendar Module cannot be installed until these errors are corrected:',

//----------------------------------------
//  Upgradeification
//----------------------------------------

'update_calendar' =>
'Update Calendar',

"update_successful"	=>
"The module was successfully updated.",

'calendar_update_message' =>
'A new version of Calendar is ready. Would you like to update?',

'update_failure' =>
'The Calendar module update was unsuccessful.',

'update_successful' =>
'The Calendar module update was successful.',

//----------------------------------------
//  Main Menu
//----------------------------------------

'calendars' =>
'Calendars',

'events' =>
'Events',

'occurrences' =>
'Occurrences',

'reminders' =>
'Reminders',

'permissions' =>
'Permissions',

'preferences' =>
'Preferences',

'documentation' =>
'Online Documentation',

'online_documentation' =>
'Online Documentation',

//----------------------------------------
//  Publish/Edit
//----------------------------------------

'filter' =>
'Filter',

'remove_edited_occurrences' =>
'Remove Edited Occurrences',

'remove_edited_occurrences_desc' =>
'Uncheck this if you are only editing details about  this event. It is recommended that you LEAVE THIS CHECKED if you are changing event TIMES, DATES, OR ENDING DATES, as not doing so will create orphan events.',

'create_calendar_first' =>
'You must first create a calendar before creating an event.',

'select_a_calendar' =>
'Select a calendar',

'new_date' =>
'New Date',

'add_rule' =>
'Add Rule',

'editing_occurrence' =>
'Editing the <strong>%date%</strong> occurrence of <strong>%title%</strong>',

'type' =>
'Type',

'include' =>
'Include',

'exclude' =>
'Exclude',

'repeat' =>
'Repeat',

'none' =>
'None',

'daily' =>
'Daily',

'weekly' =>
'Weekly',

'monthly' =>
'Monthly',

'yearly' =>
'Yearly',

'select_dates' =>
'Select Dates',

'all_day_event' =>
'All Day Event',

'from' =>
'From',

'to' =>
'To',

'every' =>
'Every',

'day_s' =>
'day(s)',

'week_s_on' =>
'week(s) on',

'at' =>
'at',

'month_s_by_day_of' =>
'month(s) by day of',

'1st' =>
'1st',

'2nd' =>
'2nd',

'3rd' =>
'3rd',

'4th' =>
'4th',

'5th' =>
'5th',

'only_on' =>
'Only on',

'year_s' =>
'year(s)',

'end' =>
'End',

'never' =>
'Never',

'by_date' =>
'by Date',

'after' =>
'After',

'time_s' =>
'time(s)',

//----------------------------------------
//  CP Calendars
//----------------------------------------

'calendar_id' =>
'Calendar ID',

'calendar_name' =>
'Calendar Name',

'status' =>
'Status',

'total_events' =>
'Total Events',

//----------------------------------------
//  CP Events
//----------------------------------------

'event_id' =>
'Event ID',

'event_name' =>
'Event Name',

'recurs' =>
'Recurs',

'first_date' =>
'First Date',

'last_date' =>
'Last Date',

'filter_events' =>
"Filter Events",

'filter_by_calendar' =>
'Filter by Calendar',

'filter_by_status' =>
'Filter by Status',

'filter_by_recurs' =>
'Filter by Recurs',

'order_by' =>
'Order by',

'date_is' =>
'Date is',

'event_id' =>
'Event ID',

'event_title' =>
'Event Title',

'calendar_name' =>
'Calendar Name',

'status' =>
'Status',

'recurs' =>
'Recurs',

'first_date_is' =>
'First Date Is',

'last_date' =>
'Last Date',

'ascending' =>
'Ascending',

'descending' =>
'Descending',

'or_later' =>
'Or Later',

'or_earlier' =>
'Or Earlier',

'this_date' =>
'This Date',

'time' =>
'Time',

'all_day' =>
'All Day',

//----------------------------------------
//  CP Events Delete
//----------------------------------------

'delete' =>
'Delete',

'delete_events' =>
'Delete Events',

'delete_events_title' =>
'Delete event(s)?',

'delete_events_question' =>
'Really delete {COUNT} event(s)?',

'events_deleted' =>
'Event(s) were deleted',

//----------------------------------------
//  CP Occurrences
//----------------------------------------

'occurrence_id' =>
'Occurrence ID',

'event_date' =>
'Event Date',

'limit' =>
'Limit',

'page_limit' =>
'Page Limit',

'occurrences_limit' =>
'Occurrences Limit',

// -------------------------------------
//	Permissions
// -------------------------------------

'calendar_permissions_desc' =>
"Permissions can be set at calendar creation time, or changed here. Super Admins and the groups selected in 'Allow All Full Access For Groups' have access to all calendars. Groups selected in 'Deny All Access For Groups' will not have access to any calendars.",

'allowed_groups' =>
"Allowed Groups",

'allow_full_access' =>
'Allow All Full Access For Groups',

'permissions_enabled' =>
'Permissions Enabled',

'save_permissions' =>
'Save Permissions',

'all_groups' =>
'All Groups',


'allow_all' =>
'Allow All',

'deny_all_access' =>
'Deny All Access For Groups',

'deny_takes_precedence' =>
"Takes precedence over calendar Allow All",

'permissions_saved' =>
"Permissions Saved",

'group_permissions' =>
'Group Permissions',

'permissions_instructions' =>
"Choose the groups that you want to have editing access to calendar and its events. (Super Admins always have access to all calendars). If a group is not shown, it is either in the Allow All or Deny All list in the permissions tab in the Calendar control panel.",

'disallowed_behavior_for_edit_page' =>
"Disallowed Behavior for Edit Page",

'none' =>
"None",

'search_filter' =>
"Search Filter",

'disable_link' =>
"Disable Link",

'permission_dialog_title' =>
"Permission Error",

'ok' => "OK",

//----------------------------------------
//  CP Preferences
//----------------------------------------

'preference' =>
'Preference',

'setting' =>
'Setting',

'description' =>
'Description',

'first_day_of_week' =>
'First Day of the Week',

'first_day_of_week_description' =>
'Sunday and Monday are the most likely choices.',

'clock_type' =>
'Clock Type',

'clock_type_description' =>
'Use 12-hour or 24-hour clock in control panel?',

'12_hour' =>
'12-hour',

'24_hour' =>
'24-hour',

'default_timezone' =>
'Default Timezone',

'default_timezone_description' =>
'Default timezone for new calendars.',

'preferences_updated' =>
'Preferences updated.',

'default_date_format' =>
'Datepicker Date Format',

'default_date_format_description' =>
'Date format for datepicker.',

'default_time_format' =>
'Default Time Format',

'default_time_format_description' =>
'Default time format for new calendars.',

'calendar_weblog' =>
'Calendar Weblog(s)',

'calendar_weblog_description' =>
'Weblog(s) to designate as Calendar weblog(s)',

'event_weblog' =>
'Event Weblog(s)',

'event_weblog_description' =>
'Weblog(s) to designate as  weblog(s)',

//----------------------------------------
//  Buttons
//----------------------------------------

'save' =>
'Save',

'delete_selected_items' =>
'Delete Selected Items',

//----------------------------------------
//  Errors
//----------------------------------------

'no_results' =>
'No results.',

'no_title' =>
'No Title',

'invalid_request' =>
"Invalid Request",

'calendar_module_disabled' =>
"The Calendar module is currently disabled. Please insure it is installed and up to date by going
to the module's control panel in the ExpressionEngine Control Panel",

'disable_module_to_disable_extension' =>
"To disable this extension, you must disable its corresponding <a href='%url%'>module</a>.",

'enable_module_to_enable_extension' =>
"To enable this extension, you must install its corresponding <a href='%url%'>module</a>.",

'cp_jquery_requred' =>
"The 'jQuery for the Control Panel' extension must be <a href='%extensions_url%'>enabled</a> to use this module.",

'invalid_weblog_id' =>
'Invalid weblog ID',

'invalid_entry_id' =>
'Invalid entry ID',

'invalid_site_id' =>
'Invalid site ID',

'invalid_calendar_id' =>
'Invalid calendar ID',

'invalid_ymd' =>
'Invalid date',

'invalid_start_date' =>
'Invalid start date',

'invalid_end_date' =>
'Invalid end date',

'invalid_year' =>
'Invalid year',

'invalid_month' =>
'Invalid month',

'invalid_day' =>
'Invalid day',

'invalid_date' =>
'Invalid date',

'invalid_time' =>
'Invalid time',

'invalid_start_time' =>
'Invalid start time',

'invalid_end_time' =>
'Invalid end time',

'invalid_hour' =>
'Invalid hour',

'invalid_minute' =>
'Invalid minute',

'invalid_repeat_dates' =>
'Invalid repeat dates',

'invalid_calendar_permissions' =>
'You are not permitted to edit or add events to this calendar',

'no_permissions_for_any_calendars' =>
"You do not have permission to add or edit events on any calendars",

'invalid_permissions_json_request' =>
"In valid JSON request. Requires group_id and EE 2.x+.",

'cannot_update_extensions_disabled' =>
'This module cannot update while extensions are disabled.',

//----------------------------------------
//  Days
//----------------------------------------

'day_1_full' =>
'Monday',

'day_2_full' =>
'Tuesday',

'day_3_full' =>
'Wednesday',

'day_4_full' =>
'Thursday',

'day_5_full' =>
'Friday',

'day_6_full' =>
'Saturday',

'day_0_full' =>
'Sunday',

//----------------------------------------
//  Days - 2 Letters
//----------------------------------------

'day_1_2' =>
'Mo',

'day_2_2' =>
'Tu',

'day_3_2' =>
'We',

'day_4_2' =>
'Th',

'day_5_2' =>
'Fr',

'day_6_2' =>
'Sa',

'day_0_2' =>
'Su',

//----------------------------------------
//  Days - 3 Letters
//----------------------------------------

'day_1_3' =>
'Mon',

'day_2_3' =>
'Tue',

'day_3_3' =>
'Wed',

'day_4_3' =>
'Thu',

'day_5_3' =>
'Fri',

'day_6_3' =>
'Sat',

'day_0_3' =>
'Sun',

//----------------------------------------
//  Days - Short
//----------------------------------------

'day_1_short' =>
'Mon',

'day_2_short' =>
'Tues',

'day_3_short' =>
'Weds',

'day_4_short' =>
'Thurs',

'day_5_short' =>
'Fri',

'day_6_short' =>
'Sat',

'day_0_short' =>
'Sun',

//----------------------------------------
//  Days - 1 letter
//----------------------------------------

'day_1_1' =>
'M',

'day_2_1' =>
'T',

'day_3_1' =>
'W',

'day_4_1' =>
'T',

'day_5_1' =>
'F',

'day_6_1' =>
'S',

'day_0_1' =>
'S',

//----------------------------------------
//  Ordinal suffixes
//----------------------------------------

'suffix_0' =>
'th',

'suffix_1' =>
'st',

'suffix_2' =>
'nd',

'suffix_3' =>
'rd',

'suffix_4' =>
'th',

'suffix_5' =>
'th',

'suffix_6' =>
'th',

'suffix_7' =>
'th',

'suffix_8' =>
'th',

'suffix_9' =>
'th',

'suffix_10' =>
'th',

'suffix_11' =>
'th',

'suffix_12' =>
'th',

'suffix_13' =>
'th',

'suffix_14' =>
'th',

'suffix_15' =>
'th',

'suffix_16' =>
'th',

'suffix_17' =>
'th',

'suffix_18' =>
'th',

'suffix_19' =>
'th',

//----------------------------------------
//  Months
//----------------------------------------

'month_1_full' =>
'January',

'month_2_full' =>
'February',

'month_3_full' =>
'March',

'month_4_full' =>
'April',

'month_5_full' =>
'May',

'month_6_full' =>
'June',

'month_7_full' =>
'July',

'month_8_full' =>
'August',

'month_9_full' =>
'September',

'month_10_full' =>
'October',

'month_11_full' =>
'November',

'month_12_full' =>
'December',

//----------------------------------------
//  Months - 3 letters
//----------------------------------------

'month_1_3' =>
'Jan',

'month_2_3' =>
'Feb',

'month_3_3' =>
'Mar',

'month_4_3' =>
'Apr',

'month_5_3' =>
'May',

'month_6_3' =>
'Jun',

'month_7_3' =>
'Jul',

'month_8_3' =>
'Aug',

'month_9_3' =>
'Sep',

'month_10_3' =>
'Oct',

'month_11_3' =>
'Nov',

'month_12_3' =>
'Dec',

//----------------------------------------
//  am/pm
//----------------------------------------

'am' =>
'am',

'pm' =>
'pm',

'AM' =>
'AM',

'PM' =>
'PM',

'am_dot' =>
'a.m.',

'pm_dot' =>
'p.m.',

//----------------------------------------
//  Date parameters
//----------------------------------------

'today' =>
'today',

'yesterday' =>
'yesterday',

'tomorrow' =>
'tomorrow',

'day' =>
'day',

'week' =>
'week',

'month' =>
'month',

'year' =>
'year',

'ago' =>
'ago',

'begin' =>
'begin',

'last' =>
'last',

//----------------------------------------
//  Time parameters
//----------------------------------------

'now' =>
'now',

'noon' =>
'noon',

'midnight' =>
'midnight',

//----------------------------------------
//  field verbage
//----------------------------------------

'summary' =>
'Summary',

'location' =>
'Location',

'dates_and_options' =>
'Dates & Options',

'ics_url_label' =>
'URL to iCalendar (.ics) file',

'ics_url_desc' =>
"Add one or more URLs to .ics files - separated by newlines - to import to this calendar. All imported times will be adjusted to this calendar's timezone settings.",

'ics_url_stub' =>
"All imported times will be adjusted to this calendar's timezone settings.",

'time_format_label' =>
'Time Format',

'time_format_desc' =>
'Default time format to use for this calendar.',

'timezone' =>
'Timezone',

/* END */
''=>''
);