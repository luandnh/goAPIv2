<?php

/**
 * @file 		goGetCallRecordingList.php
 * @brief 		API for Call Recordings
 * @copyright 	Copyright (c) 2018 GOautodial Inc.
 * @author		Demian Lizandro A. Biscocho
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

include_once("goAPI.php");

$limit 	= (isset($_REQUEST['limit']) ? $astDB->escape($_REQUEST['limit']) : 500);

// POST or GET Variables
$requestDataPhone = $astDB->escape($_REQUEST['requestDataPhone']);
$start_filterdate = $astDB->escape($_REQUEST['start_filterdate']);
$end_filterdate = $astDB->escape($_REQUEST['end_filterdate']);
$agent_filter = $astDB->escape($_REQUEST['agent_filter']);
$list_filter = $astDB->escape($_REQUEST['list_filter']);
$phone_filter = $astDB->escape($_REQUEST['phone_filter']);
$identity_filter = $astDB->escape($_REQUEST['identity_filter']);
$leadcode_filter = $astDB->escape($_REQUEST['leadcode_filter']);
$leadsubid_filter = $astDB->escape($_REQUEST['leadsubid_filter']);
$direction_filter = $astDB->escape($_REQUEST['direction_filter']);
$limit = $astDB->escape($_REQUEST['limit']);
$offset = $astDB->escape($_REQUEST['offset']);
// ERROR CHECKING 
if (empty($goUser) || is_null($goUser)) {
	$apiresults = array(
		"result" => "Error: goAPI User Not Defined."
	);
} elseif (empty($goPass) || is_null($goPass)) {
	$apiresults = array(
		"result" => "Error: goAPI Password Not Defined."
	);
} elseif (empty($log_user) || is_null($log_user)) {
	$apiresults = array(
		"result" => "Error: Session User Not Defined."
	);
} else {
	// check if goUser and goPass are valid
	$fresults										= $astDB
		->where("user", $goUser)
		->where("pass_hash", $goPass)
		->getOne("vicidial_users", "user,user_level");

	$goapiaccess									= $astDB->getRowCount();
	$userlevel										= $fresults["user_level"];

	if ($goapiaccess > 0 && $userlevel > 7) {
		// set tenant value to 1 if tenant - saves on calling the checkIfTenantf function
		// every time we need to filter out requests
		$tenant										=  (checkIfTenant($log_group, $goDB)) ? 1 : 0;

		if ($tenant) {
			$astDB->where("vl.user_group", $log_group);
		} else {
			if (strtoupper($log_group) != 'ADMIN' and strtoupper($log_group) != 'GroupIT') {
				if ($userlevel > 8) {
					$astDB->where("vl.user_group", $log_group);
				} else {
					$stringv = go_getall_allowed_users_with_sub($log_group);
					$astDB->where("rl.user in ( $stringv )");
				}
			}
		}
		if (!empty($requestDataPhone)) {
			$astDB->where("vl.phone_number", "%$requestDataPhone%", "LIKE");
		}
		if ($start_filterdate != "" && $end_filterdate != "" && $start_filterdate != $end_filterdate) {
			$start_date = date("Y-m-d G:i:s", strtotime($start_filterdate));
			$end_date = date("Y-m-d G:i:s", strtotime($end_filterdate));
			$astDB->where("rl.end_time", array(date($start_date), date($end_date)), "BETWEEN");
		}
		if (!empty($agent_filter)) {
			$astDB->where("rl.user", $agent_filter);
		}
		if (!empty($list_filter)) {
			$astDB->where("vl.list_id", $list_filter);
		}
		if (!empty($phone_filter)) {
			$astDB->where("vl.phone_number", "%$phone_filter%", "LIKE");
		}
		if (!empty($identity_filter)) {
			$astDB->where("vl.identity_number", "%$identity_filter%", "LIKE");
		}
		if (!empty($leadcode_filter)) {
			$astDB->where("rl.lead_id", $leadcode_filter);
		}
		if (!empty($leadsubid_filter)) {
			$astDB->where("vl.lead_sub_id", $leadsubid_filter);
		}
		// if (!empty($direction_filter)) {
		// 	$filterdirection							= "AND rl.direction = '$direction_filter'";
		// } else {
		// 	$filterdirection 							= "";
		// }
		$astDB->join('vicidial_list as vl', 'rl.lead_id = vl.lead_id');
		$cols 										= array(
			"CONCAT(vl.first_name,' ',vl.last_name) AS full_name",
			"rl.vicidial_id",
			"vl.last_local_call_time",
			"vl.phone_number",
			"rl.length_in_sec",
			"rl.filename",
			"rl.location",
			"rl.lead_id",
			"rl.user",
			"rl.start_time",
			"rl.end_time",
			"rl.recording_id",
			"rl.b64encoded"
		);
		$astDB->orderBy('rl.start_time', 'DESC');
		// $rsltv = $astDB->get("recording_log rl", null, $cols, true);
		$rsltv = $astDB->get("recording_log rl", array($offset, $limit), $cols, true);
		$total = $astDB->unlimitedCount;
		$data = array();
		if ($astDB->count > 0) {
			foreach ($rsltv as $fresults) {
				$location 							= $fresults['location'];

				if (strlen($location) > 2) {
					$URLserver_ip 					= $location;
					$URLserver_ip 					= preg_replace('/http:\/\//i', '', $URLserver_ip);
					$URLserver_ip 					= preg_replace('/https:\/\//i', '', $URLserver_ip);
					$URLserver_ip 					= preg_replace('/\/.*/i', '', $URLserver_ip);
					//$stmt="SELECT count(*) FROM servers WHERE server_ip='$URLserver_ip';";
					$astDB->where('server_ip', $URLserver_ip);
					$astDB->get('servers');

					if ($astDB->count > 0) {
						$cols 						= array(
							"recording_web_link",
							"alt_server_ip",
							"external_server_ip"
						);

						$astDB->where('server_ip', $URLserver_ip);
						$rsltx 					= $astDB->getOne('servers', NULL, $cols);

						if (preg_match("/ALT_IP/i", $rsltx['recording_web_link'])) {
							$location 			= preg_replace("/$URLserver_ip/i", "{$rsltx['alt_server_ip']}", $location);
						}
						if (preg_match("/EXTERNAL_IP/i", $rsltx['recording_web_link'])) {
							$location 			= preg_replace("/$URLserver_ip/i", "{$rsltx['external_server_ip']}", $location);
						}
					}
				}

				$dataLeadId[] 					= $fresults['lead_id'];
				$dataUniqueid[] 				= $fresults['vicidial_id'];
				$dataStatus[] 					= $fresults['status'];
				$dataUser[] 					= $fresults['user'];
				$dataPhoneNumber[] 				= $fresults['phone_number'];
				$dataFullName[] 				= $fresults['full_name'];
				$dataLastLocalCallTime[] 		= $fresults['last_local_call_time'];
				$dataStartLastLocalCallTime[] 	= $fresults['start_time'];
				$dataEndLastLocalCallTime[] 	= $fresults['end_time'];
				$dataLocation[] 				= $location;
				$dataRecordingID[] 				= $fresults['recording_id'];
				$dataB64encoded[]				= $fresults['b64encoded'];
				$fresults["location"] = $location;
				array_push($data, $fresults);
			}

			//$query1 = "SELECT count(*) AS `cnt` FROM recording_log WHERE lead_id='{$fresults['lead_id']}';";
			$astDB->where('lead_id', $fresults['lead_id']);
			$astDB->get('recording_log');
			$dataCount	 						= $astDB->getRowCount();

			$log_id 							= log_action($goDB, 'VIEW', $log_user, $log_ip, "View the Call Recording List", $log_group);

			$apiresults 						= array(
				"result" 							=> "success",
				"query" 							=> $query,
				"cnt" 								=> $dataCount,
				"lead_id" 							=> $dataLeadId,
				"uniqueid" 							=> $dataUniqueid,
				"status" 							=> $dataStatus,
				"users" 							=> $dataUser,
				"phone_number"	 					=> $dataPhoneNumber,
				"full_name" 						=> $dataFullName,
				"last_local_call_time" 				=> $dataLastLocalCallTime,
				"start_last_local_call_time" 		=> $dataStartLastLocalCallTime,
				"end_last_local_call_time" 			=> $dataEndLastLocalCallTime,
				"location" 							=> $dataLocation,
				"recording_id" 						=> $dataRecordingID,
				"b64encoded" 						=> $dataB64encoded,
				"query" 							=> $query,
				"data"							=> $data,
				"total"							=> $total
			);
		} else {
			$apiresults 						= array(
				"result" 							=> "success",
				"query" => $query
			);
		}
	} else {
		$err_msg 									= error_handle("10001");
		$apiresults 								= array(
			"code" 										=> "10001",
			"result" 									=> $err_msg
		);
	}
}
