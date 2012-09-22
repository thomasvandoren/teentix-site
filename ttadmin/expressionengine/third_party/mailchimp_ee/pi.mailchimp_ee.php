<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
                        'pi_name'        => 'Mailchimp EE',
                        'pi_version'     => '0.4',
                        'pi_author'      => 'Milan Topalov',
                        'pi_description' => 'Mailchimp Subscribe, Unsubscribe, Campaign Archive',
                        'pi_usage'       => Mailchimp_ee::usage()
                    );

class Mailchimp_ee
{
	function Mailchimp_ee()
	{
		$this->EE =& get_instance();
		if (!class_exists('MCAPI'))
		{
			require PATH_THIRD.'mailchimp_ee/libraries/MCAPI.class.php';
		}
	}
	
	function subscribe()
	{
		$api_key = $this->EE->TMPL->fetch_param('api_key');
		$list_id = $this->EE->TMPL->fetch_param('list_id');
		$interest_groups = $this->EE->TMPL->fetch_param('interest_groups');
		$email_address = $this->EE->TMPL->fetch_param('email_address');
		$double_optin = !($this->EE->TMPL->fetch_param('double_optin') == 'no'); //unless parameter set to 'no' value defaults to TRUE
		
		if (!$api_key OR !$list_id OR !$email_address)
		{
			return;
		}
		
		$api = new MCAPI($api_key);
		
		// Record subscriber IP address
		$merge_vars = array ("OPTINIP" => $_SERVER['REMOTE_ADDR']);
		
		// Set interest groups
		if ($interest_groups)
		{
			$interest_groups = explode("|",$interest_groups);
			$interest_groups = implode(",",$interest_groups);
			$merge_vars['INTERESTS'] = $interest_groups;
		}
		else
		{
			$merge_vars['INTERESTS'] = '';
		}
		
		// Set merge vars (custom fields)
		foreach ($this->EE->TMPL->tagparams as $key => $value)
		{
			if (substr($key,0,6) == "merge_")
			{
				$merge_vars[substr($key,6)] = $value;
			}
		} 
		
		// Set email type
		$email_type = '';
		
		// Update profile if a subscriber signs up again
		$update_existing = TRUE;
		
		$result = $api->listSubscribe($list_id, $email_address, $merge_vars, $email_type, $double_optin, $update_existing);
		
		if ($api->errorCode)
		{
			$this->EE->TMPL->log_item('Mailchimp EE: Subscribe failed, Mailchimp API complained - '.$api->errorMessage.' ('.$api->errorCode.')');
		}
	}
	
	function unsubscribe()
	{
		$api_key = $this->EE->TMPL->fetch_param('api_key');
		$list_id = $this->EE->TMPL->fetch_param('list_id');
		$email_address = $this->EE->TMPL->fetch_param('email_address');
		
		if (!$api_key OR !$list_id OR !$email_address)
		{
			return;
		}
		
		$api = new MCAPI($api_key);
		
		$result = $api->listUnsubscribe($list_id, $email_address);
		
		if ($api->errorCode)
		{
			$this->EE->TMPL->log_item('Mailchimp EE: Unsubscribe failed, Mailchimp API complained - '.$api->errorMessage.' ('.$api->errorCode.')');
		}
	}
	
	function campaign_archive()
	{
		$api_key = $this->EE->TMPL->fetch_param('api_key');
		$list_id = $this->EE->TMPL->fetch_param('list_id');
		$folder_id = $this->EE->TMPL->fetch_param('folder_id');
		$status = $this->EE->TMPL->fetch_param('status');
		$limit = $this->EE->TMPL->fetch_param('limit');
		
		if (!$api_key)
		{
			return;
		}
		
		$api = new MCAPI($api_key);
		
		$filters = "";
		
		// Apply by list filter
		if ($list_id)
		{
			$filters['list_id'] = $list_id;
		}
		
		// Apply by folder filter
		if ($folder_id)
		{
			$filters['folder_id'] = $folder_id;
		}
		
		// Apply by status filter
		if ($status)
		{
			$filters['status'] = $status;
		}
		
		$result = $api->campaigns($filters);
	
		if ($api->errorCode)
		{
			$this->EE->TMPL->log_item('Mailchimp EE: Campaign Archive failed, Mailchimp API complained - '.$api->errorMessage.' ('.$api->errorCode.')');
		}
		
		// Make sure there is at least one campaign
		if (count($result) == 0)
		{
			return;
		}
		
		// Apply limit
		if ($limit)
		{
			$result = array_slice($result, 0, $limit);
		}
				
		$vars = $result;
		
		// Convert date values into timestamps
		foreach ($vars as $key => $value)
		{
			$vars[$key]['send_time'] = strtotime($value['send_time']);
			$vars[$key]['create_time'] = strtotime($value['create_time']);
		}
		
		return $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $vars);
	}
	
	function usage()
	{
		ob_start();
?>		
[ Subscribe ]

{exp:mailchimp_ee:subscribe api_key="" list_id="" email_address="somebody@email.com" interest_groups="group 1|group 2" merge_fname="john" double_optin="yes"}

- Disable double optin with double_option="no"
- Set custom fields with merge_<custom_field_name>="value" (ie merge_fname="John")
- Set list interest groups with interest_groups="group 1|group 2"


[ Unsubscribe ]

{exp:mailchimp_ee:unsubscribe api_key="" list_id="" email_address="somebody@email.com"}


[ Campaign Archive ]

{exp:mailchimp_ee:campaign_archive api_key="" list_id="" folder_id="" status="sent" limit="10"}
{send_time format="%d %F %Y"} - {archive_url} - {subject} ({emails_sent})
{/exp:mailchimp_ee:campaign_archive}

Variables:

{id} - Campaign Id
{list_id} - The List used for this campaign
{folder_id} - The Folder this campaign is in
{title} - Title of the campaign
{type} - The type of campaign this is (regular,plaintext,absplit,rss,inspection,trans,auto)
{create_time} - Creation time for the campaign
{send_time} - Send time for the campaign - also the scheduled time for scheduled campaigns.
{emails_sent} - Number of emails email was sent to
{status} - Status of the given campaign (save,paused,schedule,sending,sent)
{from_name} - From name of the given campaign
{from_email} - Reply-to email of the given campaign
{subject} - Subject of the given campaign
{archive_url} - Archive link for the given campaign

<?php
		$buffer = ob_get_contents();
		ob_end_clean(); 
		return $buffer;
	}
}
?>