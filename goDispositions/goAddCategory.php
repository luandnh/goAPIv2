<?php
 /**
 * @file 		goAddDisposition.php
 * @brief 		API for Dispositions
 * @copyright 	Copyright (c) 2018 GOautodial Inc.
 * @author		Demian Lizandro A. Biscocho
 * @author     	Chris Lomuntad
 * @author     	Jeremiah Sebastian Samatra 
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
 
	$campaigns 											= allowed_campaigns($log_group, $goDB, $astDB);
	$category_id 										= $astDB->escape($_REQUEST['category_id']);
	$category_name 										= $astDB->escape($_REQUEST['category_name']);
	$category_description 								= $astDB->escape($_REQUEST['category_description']);	
	$c_tovdad_display 								 	= $astDB->escape($_REQUEST['c_tovdad_display']);
	$dead_lead 											= $astDB->escape($_REQUEST['dead_lead']);
	$sale_category 										= $astDB->escape($_REQUEST['sale_category']);
	// ERROR CHECKING 
	if (empty($goUser) || is_null($goUser)) {
		$apiresults 									= array(
			"result" 										=> "Error: goAPI User Not Defined."
		);
	} elseif (empty($goPass) || is_null($goPass)) {
		$apiresults 									= array(
			"result" 										=> "Error: goAPI Password Not Defined."
		);
	} elseif (empty($log_user) || is_null($log_user)) {
		$apiresults 									= array(
			"result" 										=> "Error: Session User Not Defined."
		);
	} elseif (empty($category_id) || is_null($category_id)) {
		$err_msg 										= error_handle("40001");
        $apiresults 									= array(
			"code" 											=> "40001",
			"result" 										=> $err_msg
		);
    }  elseif (empty($category_name) || is_null($category_name)) {
		$apiresults 									= array(
			"result" 										=> "Error: Set a value for status."
		);
		//$apiresults = array("result" 			=> "Error: Default value for not_interested is Y or N only.");
	} else {
		// check if goUser and goPass are valid
		$fresults										= $astDB
			->where("user", $goUser)
			->where("pass_hash", $goPass)
			->getOne("vicidial_users", "user,user_level");
		
		$goapiaccess									= $astDB->getRowCount();
		$userlevel										= $fresults["user_level"];
		
		if ($goapiaccess > 0 && $userlevel > 7) {	
			// Check existed category
			$astDB->where('vsc_id', $category_id);
			$astDB->get('vicidial_status_categories', null, 'status');
			if ($astDB->count == 0) {
				// Add category
				$data						= array(
					"vsc_id"					=> $category_id, 	
					"vsc_name"					=> $category_name,
					"vsc_description"			=> $category_description, 
					"tovdad_display"			=> $c_tovdad_display,
					"dead_lead_category"		=> $dead_lead,
					"sale_category"				=> $sale_category
				);
				$astDB->insert("vicidial_status_categories", $data);
				$log_id 						= log_action($goDB, 'ADD', $log_user, $log_ip, "Added New Category", $log_group, $astDB->getLastQuery());
				$apiresults 				= array(
					"result" 					=> "success"
				);	
			} else {
				$err_msg 							= error_handle("41004", "status. Category already exists in the default category");
				$apiresults 						= array(
					"code" 								=> "41004", 
					"result" 							=> $err_msg
				);
			}
		} else {		
			$err_msg 								= error_handle("10108", "Permission deny");
			$apiresults								= array(
				"code" 									=> "10108", 
				"result" 								=> $err_msg
			);
		}
	}
	
?>
