<?php

/**
 * @file        goListExport.php
 * @brief       API to export list
 * @copyright   Copyright (c) 2018 GOautodial Inc.
 * @author      Alexander Jim Abenoja
 * @author		Demian Lizandro A. Biscocho 
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

ini_set('memory_limit', '2048M');
const SAVE_FOLDER = "/var/www/html/downloads/";
const BATCH = 50000;
const LIMIT = 50000;
$offset = 0;
include_once("goAPI.php");
$list_id 											= $astDB->escape($_REQUEST["list_id"]);
if (!isset($list_id) || $list_id == "") {
	exit();
}
$time_pre = 0;
$time_post = 0;
$date_time = date("Y_m_d-H_i_s");
$file_name = "List_" . $list_id . "-" . $date_time . ".csv";
$file_path = SAVE_FOLDER . $file_name;
$time_pre = microtime(true);
$init_download = array(
	'name' => 'LIST_' . $list_id,
	'filename' => $file_name,
	'username' => $goUser,
	'path' => $file_path,
	'modified_date' => date("Y-m-d H:i:s"),
	'status' => "PROCESSING",
);
file_put_contents("ExportList.log", "Starting " . $list_id . " : " . $date_time . "-" . $file_name . "\n", FILE_APPEND | LOCK_EX);
$update_data = array();
$file_id = $astDB->insert('vicidial_downloads', $init_download);
file_put_contents("ExportList.log", "Insert data export : " . $file_id . "\n", FILE_APPEND | LOCK_EX);
// Error Checking
if (empty($goUser) || is_null($goUser)) {
	$apiresults 									= array(
		"result" 										=> "Error: goAPI User Not Defined."
	);
} elseif (empty($goPass) || is_null($goPass)) {
	$update_data 									= array(
		"description" => "Error: goAPI Password Not Defined.",
		"status" => "FAIL",
		'modified_date' => date("Y-m-d H:i:s"),
	);
} elseif (empty($log_user) || is_null($log_user)) {
	$update_data 									= array(
		"description" => "Error: Session User Not Defined.",
		"status" => "FAIL",
		'modified_date' => date("Y-m-d H:i:s"),
	);
} elseif (empty($list_id) || is_null($list_id)) {
	$update_data 									= array(
		"description" => "Empty list_id",
		"status" => "FAIL",
		'modified_date' => date("Y-m-d H:i:s"),
	);
} else {
	try {
		// check if goUser and goPass are valid
		$fresults										= $astDB
			->where("user", $goUser)
			->where("pass_hash", $goPass)
			->getOne("vicidial_users", "user,user_level");

		$goapiaccess									= $astDB->getRowCount();
		$userlevel										= $fresults["user_level"];
		if ($goapiaccess > 0 && $userlevel > 7) {
			$fetch 										= $astDB->getOne('system_settings', 'custom_fields_enabled');
			$custom_fields_enabled 						= $fetch["custom_fields_enabled"];
			$added_custom_SQL  							= "";
			$added_custom_SQL2 							= "";
			$added_custom_SQL3  						= "";
			$added_custom_SQL4 							= "";

			if ($custom_fields_enabled > 0) {
				$custom_table 							= "custom_" . $list_id;
				$cllist_query 							= "SHOW COLUMNS FROM $custom_table;";
				$cllist 								= $astDB->rawQuery($cllist_query);
				$clcount 								= $astDB->getRowCount();
				$header_columns 						= "";

				foreach ($cllist as $clrow) {
					if ($clrow['Field'] != 'lead_id') {
						if ($clrow['Field'] != "province"){
							$header_columns 				.= "," . "ct." . $clrow['Field'];
						}else{
							$header_columns 				.= "," . "ct." . $clrow['Field'] ." as ct_province";
						}
					}
				}

				if ($clcount > 0) {
					$added_custom_SQL  					= ", $custom_table ct";
					$added_custom_SQL2 					= "AND vl.lead_id=ct.lead_id";
					$added_custom_SQL3  				= "$custom_table ct";
					$added_custom_SQL4 					= "vl.lead_id=ct.lead_id";
				}
			}
			$collabrator_SQL = "";
			$collabrator_col_SQL = "";
			if ($list_id == 1151){
				// ONLY FOR VTA COLLABRATOR
				$collabrator_col_SQL = " , 
				vc.sale_code, 
				vc.full_name as ctv_full_name, 
				vc.img_id_card_front as ctv_front, 
				vc.img_id_card_back as ctv_back, 
				vc.img_selfie as ctv_selfie, 
				vc.identity_card_id as ctv_identity_card_id,
				vc.phone_number  as ctv_phone_number ";
				$collabrator_SQL = "LEFT OUTER JOIN vicidial_collaborator AS vc ON vc.referral_code = ct.referral_code ";
			}

			if ($added_custom_SQL3 != "") {
				$stmt 									= "SELECT vl.lead_id AS lead_id,date_format(entry_date,'%Y-%m-%d %H:%i:%s') as entry_date,date_format(modify_date,'%Y-%m-%d %H:%i:%s') as modify_date,
				(SELECT val.event_time from vicidial_agent_log val WHERE val.lead_id = vl.lead_id limit 1) as first_call ,vl.status,vs.status_name,user,vendor_lead_code,source_id,list_id,gmt_offset_now,called_since_last_reset,phone_code,vl.phone_number,title,first_name,middle_initial,last_name,address1,address2,address3,city,state,vl.province,postal_code,country_code,gender,vl.date_of_birth,alt_phone,email,security_phrase,comments,called_count,date_format(last_local_call_time, '%Y-%m-%d %H:%i:%s') as last_local_call_time,rank,owner {$header_columns} $collabrator_col_SQL FROM vicidial_list vl LEFT OUTER JOIN {$added_custom_SQL3} ON {$added_custom_SQL4} $collabrator_SQL
				LEFT OUTER JOIN vicidial_statuses vs ON vl.status = vs.status
				WHERE vl.list_id='{$list_id}' ";
			} else {
				$stmt 									= "SELECT lead_id,date_format(entry_date,'%Y-%m-%d %H:%i:%s') as entry_date,date_format(modify_date,'%Y-%m-%d %H:%i:%s') as modify_date,
				(SELECT val.event_time from vicidial_agent_log val WHERE val.lead_id = vl.lead_id limit 1) as first_call,vl.status,vs.status_name, user,vendor_lead_code,source_id,list_id,gmt_offset_now,called_since_last_reset,phone_code,vl.phone_number,title,first_name,middle_initial,last_name,address1,address2,address3,city,state,vl.province,postal_code,country_code,gender,vl.date_of_birth,alt_phone,email,security_phrase,comments,called_count,date_format(last_local_call_time, '%Y-%m-%d %H:%i:%s') as last_local_call_time,rank,owner FROM vicidial_list vl 
				LEFT OUTER JOIN vicidial_statuses vs ON vl.status = vs.status
				WHERE list_id='$list_id' ";
			}
			file_put_contents("ExportList.log", "SQL : ".$stmt."\n", FILE_APPEND | LOCK_EX);
			$isRun = false;
			$tmp = -1;
			$running_time = 0;
			$counter = -0;
			$file = fopen($file_path, "w");
			while ($tmp != 0 || $running_time > 60 * 10) {
				$batch_stmt = $stmt  . " LIMIT " . LIMIT. ' OFFSET ' . $offset;
				$dllist 									= $astDB->rawQuery($batch_stmt);
				$tmp = count($dllist);
				if ($isRun == false){
					$header = $astDB->getFieldNames();
					fputcsv($file, $header);
					$isRun = true;
				}
				if ($tmp == 0){break;}
				foreach ($dllist as $fetch_row) {
					$counter++;
					$ars = implode("|", $fetch_row);
					$ars = str_replace("\n", "", $ars);
					fputcsv($file, explode("|",$ars));
					// $array_fetch 							= $fetch_row[$header[0]];
					// $u 										= $u + 1;

					// while ($u < $count_header) {
					// 	$array_fetch 						.= "|" . $fetch_row[$header[$u]];
					// 	$u++;
					// }

					// $explode_array 							= explode(",", $array_fetch);
					// $row[$x] 								= $explode_array;
					// $array_fetch 							= "";
					// $u 										= 0;
					// $x++;
				}
				$offset += BATCH;
				// END BATCH
			}
			fclose($file);
			$time_post = microtime(true);
			$update_data 								= array(
				"total" 									=>  $counter,
				"status" 									=> "DONE",
				"time" 									=> ($time_post - $time_pre),
				'modified_date' => date("Y-m-d H:i:s"),
			);
		} else {
			$update_data 									= array(
				"description" => "Not permission",
				"status" => "FAIL",
				'modified_date' => date("Y-m-d H:i:s"),
			);
		}
	} catch (Throwable  $e) {
		file_put_contents("ExportList.log", $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
		$update_data 									= array(
			"description" => $e->getMessage(),
			"status" => "FAIL",
			'modified_date' => date("Y-m-d H:i:s"),
		);
	}
	$astDB->where('id', $file_id);
	$rslt = $astDB->update('vicidial_downloads', $update_data);
	file_put_contents("ExportList.log", "Result: " . $rslt . "\n", FILE_APPEND | LOCK_EX);
}
