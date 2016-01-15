<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Update file for the update to 2.2
 * Add a new field for the search fields
 *
 * @package		webservice
 * @category	Modules
 * @author		Rein de Vries <info@reinos.nl>
 * @link		http://reinos.nl
 * @license  	http://reinos.nl/add-ons/commercial-license
 * @copyright 	Copyright (c) 2014 Reinos.nl Internet Media
 */
 
include(PATH_THIRD.'webservice/config.php');
 
class Webservice_upd_42
{
	private $EE;
	private $version = '4.2';
	
	/**
	 * Construct method
	 *
	 * @return      boolean         TRUE
	 */
	public function __construct()
	{
		//get a intance of the EE object
		$this->EE = get_instance();
		
		//load the classes
		ee()->load->dbforge();
	}
	
	/**
	 * Run the update
	 *
	 * @return      boolean         TRUE
	 */
	public function run_update()
	{
		$sql = array();
				
		//ADD KEYS table
		$sql[] = "INSERT INTO `".ee()->db->dbprefix."extensions` (`extension_id`, `class`, `method`, `hook`, `settings`, `priority`, `version`, `enabled`) VALUES (NULL, 'Webservice_ext', 'entry_submission_end', 'entry_submission_end', '', '10', '4.2', 'y');";

		foreach ($sql as $query)
		{
			ee()->db->query($query);
		}
	}
}