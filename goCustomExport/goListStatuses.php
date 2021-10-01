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
const BATCH = 50000;
const LIMIT = 50000;
$offset = 0;
include_once("goAPI.php");
const SAVE_FOLDER = "/var/www/html/downloads/";
$apiresults 								= array(
    "result" 									=> "Error: User Not Defined."
);
// TEST
$goUser = "AdminTel4vn";
$goPass = "hello";
$log_user = "admin";
$goapiaccess = 1;
$userlevel = 9;
// END TEST
$list_id 											= $astDB->escape($_REQUEST["list_id"]);
if (!isset($list_id) || $list_id == "") {
	exit();
}
$time_pre = 0;
$time_post = 0;
$date_time = date("Y_m_d-H_i_s");
$file_name = "List_Statuses_" . $list_id . "-" . $date_time . ".zip";
$file_name_sum = "Statuses_Summary_" . $list_id . "-" . $date_time . ".csv";
$file_name_det = "Statuses_Detail_" . $list_id . "-" . $date_time . ".csv";
// 
$file_path = SAVE_FOLDER . $file_name;
$file_path_sum = SAVE_FOLDER . $file_name_sum;
$file_path_det = SAVE_FOLDER . $file_name_det;
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
// $file_id = $astDB->insert('vicidial_downloads', $init_download);
file_put_contents("ExportList.log", "Insert list_status export : " . $file_id . "\n", FILE_APPEND | LOCK_EX);
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
		// $fresults										= $astDB
		// 	->where("user", $goUser)
		// 	->where("pass_hash", $goPass)
		// 	->getOne("vicidial_users", "user,user_level");
		// $goapiaccess									= $astDB->getRowCount();
		// $userlevel										= $fresults["user_level"];
		if ($goapiaccess > 0 && $userlevel > 7) {
			$query 										= "
				SELECT vicidial_list.status as stats,
					SUM(IF(substr(vicidial_list.called_since_last_reset,1,1)='Y',1,0)) AS 'is_called',
					SUM(IF(substr(vicidial_list.called_since_last_reset,1,1)='N',1,0)) AS 'not_called',
					count(*) as countvlists,
					vicidial_statuses.status_name
				FROM vicidial_list
				LEFT JOIN vicidial_statuses
				ON vicidial_list.status=vicidial_statuses.status
				WHERE vicidial_list.list_id='$list_id' 
				GROUP BY vicidial_list.status 
				ORDER BY vicidial_list.status,vicidial_list.called_since_last_reset;";
				
			$rsltv 										= $astDB->rawQuery($query);
			$summary_field = $astDB->getFieldNames();
			$file_sum = fopen($file_name_sum, "w");
			$total_call = 0;
			$total_ncall = 0;
			fputcsv($file_sum, $summary_field);
			foreach ($rsltv as $status) {
				$total_call += $status['is_called'];
				$total_ncall += $status['not_called'];
				fputcsv($file_sum, $status);
			}
			fputcsv($file_sum, array());
			fputcsv($file_sum, array("TOTAL CALL",$total_call,"","TOTAL NOT CALL",$total_ncall));
			fputcsv($file_sum, array("SUMMARY",$total_call+$total_ncall));
			fclose($file_sum);
			// END STATUS SUMMARY
			exit();	
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
