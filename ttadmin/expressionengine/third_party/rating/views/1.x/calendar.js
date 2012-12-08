	
<script type="text/javascript"> 
<!--
	var format		= 'us';
	var days		= new Array('S','M','T','W','T','F','S','S','M','T','W','T','F','S');
	var months		= new Array('January','February','March','April','May','June','July','August','September','October','November','December','January','February','March','April','May','June','July','August','September','October','November','December');
	var last_click	= new Array();
	var current_month  = '';
	var current_year   = '';
	var last_date  = '';
		
	function calendar(id, d, highlight)
	{
		this.id			= id;
		this.highlight	= highlight;
		this.date_obj	= d;
		this.write		= build_calendar;
		this.total_days	= total_days;
		this.month		= d.getMonth();
		this.date		= d.getDate();
		this.day		= d.getDay();
		this.year		= d.getFullYear();
		this.hours		= d.getHours();
		this.minutes	= d.getMinutes();
		this.seconds	= d.getSeconds();
		this.date_str	= date_str;
					
		if (highlight == false)
		{
			this.selected_date = '';
		}
		else
		{
			this.selected_date = this.year + '' + this.month + '' + this.date; 
		}
				
				
		//	Set the "selected date"
		
		// As we toggle from month to month we need a way
		// to recall which date was originally highlighted
		// so when we return to that month the state will be
		// retained.  Well set a global variable containing 
		// a string representing the year/month/day
												
		//get the first day of the month's day
		d.setDate(1);
		
		this.firstDay = d.getDay();
		
		//then reset the date object to the correct date
		d.setDate(this.date);
	}
			
	//	Build the body of the calendar
	
	function build_calendar()
	{
		var str = '';
		
		//	Calendar Heading
		
		str += '<div id="cal' + this.id + '">';
		str += '<table class="calendar" cellspacing="0" cellpadding="0" border="0" align="center">';
		str += '<tr>';
		str += '<td class="calnavleft" onclick="change_month(-1, \'' + this.id + '\')">&lt;&lt;<\/td>';
		str += '<td colspan="5" class="calheading">' + months[this.month] + ' ' + this.year + '<\/td>';
		str += '<td class="calnavright" onclick="change_month(1, \'' + this.id + '\')">&gt;&gt;<\/td>';
		str += '<\/tr>';
		
		//	Day Names
		
		str += '<tr>';
		
		for (i = 0; i < 7; i++)
		{
			str += '<td class="caldayheading">' + days[i] + '<\/td>';
		}
		
		str += '<\/tr>';
		
		//	Day Cells
			
		str += '<tr>';
		
		selDate = (last_date != '') ? last_date : this.date;
		
		for (j = 0; j < 42; j++)
		{
			var displayNum = (j - this.firstDay + 1);
			
			if (j < this.firstDay) // leading empty cells
			{
				str += '<td class="calblanktop">&nbsp;<\/td>';
			}
			else if (displayNum == selDate && this.highlight == true) // Selected date
			{
				str += '<td id="' + this.id +'selected" class="caldayselected" onclick="set_date(this,\'' + this.id + '\')">' + displayNum + '<\/td>';
			}
			else if (displayNum > this.total_days())
			{
				str += '<td class="calblankbot">&nbsp;<\/td>'; // trailing empty cells
			}
			else  // Unselected days
			{
				str += '<td id="" class="caldaycells" onclick="set_date(this,\'' + this.id + '\'); return false;"  onmouseOver="javascript:cell_highlight(this,\'' + displayNum + '\',\'' + this.id + '\');" onmouseOut="javascript:cell_reset(this,\'' + displayNum + '\',\'' + this.id + '\');" >' + displayNum + '<\/td>';
			}
			
			if (j % 7 == 6)
			{
				str += '<\/tr><tr>';
			}
		}
	
		str += '<\/tr>';	
		str += '<\/table>';
		str += '<\/div>';
		
		return str;
	}
	/* END */
	
	//	Total number of days in a month
	
	function total_days()
	{	
		switch(this.month)
		{
			case 1: // Check for leap year
				if ((  this.date_obj.getFullYear() % 4 == 0
					&& this.date_obj.getFullYear() % 100 != 0)
					|| this.date_obj.getFullYear() % 400 == 0)
					return 29; 
				else
					return 28;
			case 3:
				return 30;
			case 5:
				return 30;
			case 8:
				return 30;
			case 10:
				return 30
			default:
				return 31;
		}
	}
	/* END */
	
	
	//	Highlight Cell on Mouseover
	
	function cell_highlight(td, num, cal)
	{
		cal = eval(cal);
	
		if (last_click[cal.id]  != num)
		{
			td.className = "caldaycellhover";
		}
	}		
	
	//	Reset Cell on MouseOut
	
	function cell_reset(td, num, cal)
	{	
		cal = eval(cal);
	
		if (last_click[cal.id] == num)
		{
			td.className = "caldayselected";
		}
		else
		{
			td.className = "caldaycells";
		}
	}
	
	//	Set date to now
	
	function set_to_now(id, now, raw)
	{
		jQuery('#'+id).val(now);
		
		if (document.getElementById(id + "selected"))
		{			
			document.getElementById(id + "selected").className = "caldaycells";
			document.getElementById(id + "selected").id = "";	
		}
		
		document.getElementById('cal' + id).innerHTML = '<div id="tempcal'+id+'">&nbsp;<'+'/div>';				
			
		var nowDate = new Date();
		nowDate.setTime = raw;
		
		current_month	= nowDate.getMonth();
		current_year	= nowDate.getFullYear();
		current_date	= nowDate.getDate();
		
		oldcal = eval(id);
		oldcal.selected_date = current_year + '' + current_month + '' + current_date;	
		
		oldcal.date_obj.setMonth(current_month);
		oldcal.date_obj.setYear(current_year);
			
		cal = new calendar(id, nowDate, true);		
		cal.selected_date = current_year + '' + current_month + '' + current_date;			
	
		last_date = cal.date;
	
		document.getElementById('tempcal'+id).innerHTML = cal.write();	
	}
	
	
	//	Set date to what is in the field
	var lastDates = new Array();
	
	function update_calendar(id, dateValue)
	{
		cal = eval(id);		
	
		if (lastDates[id] == dateValue) return;
		
		lastDates[id] = dateValue;
		
		var fieldString = dateValue.replace(/\s+/g, ' ');
		
		while (fieldString.substring(0,1) == ' ')
		{
			fieldString = fieldString.substring(1, fieldString.length);
		}
		
		var dateString = fieldString.split(' ');
		var dateParts = dateString[0].split('-')
	
		if (dateParts.length < 3) return;
		var newYear  = dateParts[0];
		var newMonth = dateParts[1];
		var newDay   = dateParts[2]; 
		
		if (isNaN(newDay)  || newDay < 1 || (newDay.length != 1 && newDay.length != 2)) return;
		if (isNaN(newYear) || newYear < 1 || newYear.length != 4) return;
		if (isNaN(newMonth) || newMonth < 1 || (newMonth.length != 1 && newMonth.length != 2)) return;
		
		if (newMonth > 12) newMonth = 12;
		
		if (newDay > 28)
		{
			switch(newMonth - 1)
			{
				case 1: // Check for leap year
					if ((newYear % 4 == 0 && newYear % 100 != 0) || newYear % 400 == 0)
					{
						if (newDay > 29) newDay = 29; 
					}
					else
					{
						if (newDay > 28) newDay = 28;
					}
				case 3:
					if (newDay > 30) newDay = 30;
				case 5:
					if (newDay > 30) newDay = 30;
				case 8:
					if (newDay > 30) newDay = 30;
				case 10:
					if (newDay > 30) newDay = 30;
				default:
					if (newDay > 31) newDay = 31;
			}
		}
		
		if (document.getElementById(id + "selected"))
		{			
			document.getElementById(id + "selected").className = "caldaycells";
			document.getElementById(id + "selected").id = "";	
		}
		
		document.getElementById('cal' + id).innerHTML = '<div id="tempcal'+id+'">&nbsp;<'+'/div>';				
			
		var nowDate = new Date();
		nowDate.setDate(newDay);
		nowDate.setMonth(newMonth - 1);
		nowDate.setYear(newYear);
		nowDate.setHours(12);
		
		cal.date_obj.setMonth(newMonth - 1);
		cal.date_obj.setYear(newYear);
		
		current_month	= nowDate.getMonth();
		current_year	= nowDate.getFullYear();
		last_date		= newDay;
	
		cal = new calendar(id, nowDate, true);						
		document.getElementById('tempcal'+id).innerHTML = cal.write();	
	}
			
	
	//	Set the date
	
	function set_date(td, cal)
	{					
		cal = eval(cal);
		
		// If the user is clicking a cell that is already
		// selected we'll de-select it and clear the form field
		
		if (last_click[cal.id] == td.firstChild.nodeValue)
		{
			td.className = "caldaycells";
			last_click[cal.id] = '';
			remove_date(cal);
			cal.selected_date =  '';
			return;
		}
					
		// Onward!
	
		if (document.getElementById(cal.id + "selected"))
		{
			document.getElementById(cal.id + "selected").className = "caldaycells";
			document.getElementById(cal.id + "selected").id = "";
		}
										
		td.className = "caldayselected";
		td.id = cal.id + "selected";
	
		cal.selected_date = cal.date_obj.getFullYear() + '' + cal.date_obj.getMonth() + '' + cal.date;			
		cal.date_obj.setDate(td.firstChild.nodeValue);
		cal = new calendar(cal.id, cal.date_obj, true);
		cal.selected_date = cal.date_obj.getFullYear() + '' + cal.date_obj.getMonth() + '' + cal.date;			
		
		last_date = cal.date;
	
		//cal.date
		
		last_click[cal.id] = cal.date;
					
		// Insert the date into the form
		
		insert_date(cal);
	}
	
	
	//	Insert the date into the form field
	
	function insert_date(cal)
	{
		cal = eval(cal);
		
		if (jQuery('#' + cal.id).val(''))
		{
			jQuery('#' + cal.id).val(cal.date_str('y'));
		}
		else
		{
			time = jQuery('#' + cal.id).val().substring(10);
					
			new_date = cal.date_str('n') + time;
	
			jQuery('#' + cal.id).val(new_date);
		}	
	}
			
	//	Remove the date from the form field
	
	function remove_date(cal)
	{
		cal = eval(cal);
		
		fjQuery('#' + cal.id).val('');
	}
	
	//	Change to a new month
	
	function change_month(mo, cal)
	{		
		cal = eval(cal);
	
		if (current_month != '')
		{
			cal.date_obj.setMonth(current_month);
			cal.date_obj.setYear(current_year);
		
			current_month	= '';
			current_year	= '';
		}
					
		var newMonth = cal.date_obj.getMonth() + mo;
		var newDate  = cal.date_obj.getDate();
		
		if (newMonth == 12) 
		{
			cal.date_obj.setYear(cal.date_obj.getFullYear() + 1)
			newMonth = 0;
		}
		else if (newMonth == -1)
		{
			cal.date_obj.setYear(cal.date_obj.getFullYear() - 1)
			newMonth = 11;
		}
		
		if (newDate > 28)
		{
			var newYear = cal.date_obj.getFullYear();
			
			switch(newMonth)
			{
				case 1: // Check for leap year
					if ((newYear % 4 == 0 && newYear % 100 != 0) || newYear % 400 == 0)
					{
						if (newDate > 29) newDate = 29; 
					}
					else
					{
						if (newDate > 28) newDate = 28;
					}
				case 3:
					if (newDate > 30) newDate = 30;
				case 5:
					if (newDate > 30) newDate = 30;
				case 8:
					if (newDate > 30) newDate = 30;
				case 10:
					if (newDate > 30) newDate = 30;
				default:
					if (newDate > 31) newDate = 31;
			}
		}
		
		cal.date_obj.setDate(newDate);
		cal.date_obj.setMonth(newMonth);
		new_mdy	= cal.date_obj.getFullYear() + '' + cal.date_obj.getMonth() + '' + cal.date;
		
		highlight = (cal.selected_date == new_mdy) ? true : false;
		
		// Changed the highlight to false until we can determine a way for
		// the month to keep the old date value when we switch the newDate value
		// because of more days in the prior month than the month being switched
		// to:  Jan 31st => March 3rd (3 days past end of Febrary)
		
		cal = new calendar(cal.id, cal.date_obj, highlight); 
		
		document.getElementById('cal' + cal.id).innerHTML = cal.write();	
	}
	
	
	//	Finalize the date string
	
	function date_str(time)
	{
		var month = this.month + 1;
		if (month < 10)
			month = '0' + month;
			
		var day		= (this.date  < 10) 	?  '0' + this.date		: this.date;
		var minutes	= (this.minutes  < 10)	?  '0' + this.minutes	: this.minutes;
			
		if (format == 'us')
		{
			var hours	= (this.hours > 12) ? this.hours - 12 : this.hours;
			var ampm	= (this.hours > 11) ? 'PM' : 'AM'
		}
		else
		{
			var hours	= this.hours;
			var ampm	= '';
		}
		
		if (time == 'y')
		{
			return this.year + '-' + month + '-' + day + '  ' + hours + ':' + minutes + ' ' + ampm;		
		}
		else
		{
			return this.year + '-' + month + '-' + day;
		}
	}

//-->
</script>