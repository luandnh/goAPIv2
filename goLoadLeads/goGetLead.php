<?php
 /**
 * @file 		goGetListInfo.php
 * @brief 		API for Getting List Info
 * @copyright 	Copyright (C) GOautodial Inc.
 * @author		Jeremiah Sebastian Samatra  <jeremiah@goautodial.com>
 * @author     	Chris Lomuntad  <chris@goautodial.com>
 *
 * @par <b>License</b>:
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
	include_once ("goAPI.php");

    ### POST or GET Variables
    $lead_id = $astDB->escape($_REQUEST['goLeadID']);
    
	if($lead_id == null) { 
		$apiresults = array("result" => "Error: Set a value for Lead ID."); 
	} else {
		$astDB->where('lead_id', $lead_id);
		$lead = $astDB->getOne('vicidial_list');
		if(!$lead) {
			$apiresults = array("result" => "Error: List doesn't exist.");
		} else {
			$apiresults = array( "result" => "success", "data" => $lead);
		}
	}
?>