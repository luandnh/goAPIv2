<?php
/**
 * @file        goGetInboundReport.php
 * @brief       API reports for inbound report
 * @copyright   Copyright (c) 2020 GOautodial Inc.
 * @author      Alexander Jim Abenoja
 * @author		John Ezra Gois
 * @author		Demian Lizandro A. Biscocho
 *
 * @par <b>License</b>:
 *  This program is free software: you can redistribute it AND/or modify
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
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

    include_once("goAPI.php");
	$updateUsergroup = $astDB->rawQuery("Update vicidial_log vl INNER JOIN vicidial_users vu on vl.user = vu.user set vl.user_group = vu.user_group where vl.user_group is NULL");
    $fromDate 										= $astDB->escape($_REQUEST['fromDate']);
    $toDate 										= $astDB->escape($_REQUEST['toDate']);
    $userId 									= $astDB->escape($_REQUEST['userId']);
    $userGroup 									= $astDB->escape($_REQUEST['userGroup']);
    $listID 									= $astDB->escape($_REQUEST['listID']);
    $leadCode 									= $astDB->escape($_REQUEST['leadCode']);
    
    if (empty($fromDate)) {
        $fromDate 									= date("Y-m-d")." 00:00:00";
    }
    if (empty($toDate)) {
        $toDate 									= date("Y-m-d")." 23:59:59";
    }
	
    if (empty($log_user) || is_null($log_user)) {
        $apiresults 								= array(
            "result" 									=> "Error: Session User Not Defined."
        );
    } elseif (empty($fromDate) && empty($toDate)) {
	    $fromDate 									= date("Y-m-d") . " 00:00:00";
	    $toDate 									= date("Y-m-d") . " 23:59:59";
    	//die($fromDate." - ".$toDate);                                                                 => $err_msg
    } else {
	    // set tenant value to 1 if tenant - saves on calling the checkIfTenantf function
	    // every time we need to filter out requests
	    // $tenant 									=  (checkIfTenant ($log_group, $goDB)) ? 1 : 0;
		// $SELECTQuery = $astDB->rawQuery("Select sub_user_group from vicidial_sub_user_groups where user_group='".$log_group."'");
		// foreach($SELECTQuery as $user_group){
		// 	$user_groups[] = $user_group["sub_user_group"];
		// }
		// array_push($user_groups,$log_group);
		// $user_group_string = implode("','",$user_groups);
		$bonus_sql = "";
		// 
	    // if ($tenant) {
		// 	$astDB->where("vl.user_group", $user_groups,"IN");
		// 	$bonus_sql = "AND vl.user_group IN('".$user_group_string."') ";
	    // } else {
        //     if (strtoupper($log_group) != 'ADMIN') {
        //         // if ($user_level > 8) {
		// 	$astDB->where("vl.user_group", $user_groups,"IN");
		// 	$bonus_sql = "AND vl.user_group IN('".$user_group_string."') ";
        //         // }
        //     }
	    // }
		// 
		
	    if (!empty($leadCode) && $leadCode != "ALL") {
			$bonus_sql .= " and vl.vendor_lead_code = '$leadCode' ";
		}
		
	    if (!empty($listID) && $listID != "ALL") {
			$bonus_sql .= " and vl.list_id = $listID ";
		}
	    if (!empty($userGroup) && $userGroup != "ALL") {
			$user_groups = array();
			$SELECTQuery = $astDB->rawQuery("Select sub_user_group from vicidial_sub_user_groups where user_group='".$userGroup."'");
			foreach($SELECTQuery as $user_group){
				$user_groups[] = $user_group["sub_user_group"];
			}
			array_push($user_groups,$userGroup);
			$user_group_string = implode("','",$user_groups);

			$astDB->where('user_group', $user_groups,"IN");
			$users_data = $astDB->get('vicidial_users',NULL,'user');
			$users = array();
			foreach($users_data as $user){
				$users[] = $user["user"];
			}
			$users_string  = implode("','",$users);
			$bonus_sql .= " and vl.user  IN ('$users_string') ";
	    }

		// check if MariaDB slave server available
		$rslt										= $goDB
			->where('setting', 'slave_db_ip')
			->where('context', 'creamy')
			->getOne('settings', 'value');
		$slaveDBip 									= $rslt['value'];
		
		if (!empty($slaveDBip)) {
			$astDB 									= new MySQLiDB($slaveDBip, $VARDB_user, $VARDB_pass, $VARDB_database);

			if (!$astDB) {
				echo "Error: Unable to connect to MariaDB slave server." . PHP_EOL;
				echo "Debugging Error: " . $astDB->getLastError() . PHP_EOL;
				exit;
				//die('MySQL connect ERROR: ' . mysqli_error('mysqli'));
			}			
		}
		$appstatus_report_query = "
			SELECT
			vl.list_id,
				vlf.partner_code, 
				vlf.request_id, 
				vlf.lead_id, 
				date_format(vlf.created_at, '%Y-%m-%d %H:%i:%s') as created_at, 
				date_format(vlf.updated_at, '%Y-%m-%d %H:%i:%s') as updated_at, 
				vlf.reject_reason,
				rj.description,
				vlf.app_status, 
				vlf.loan_amount, 
				vlf.product_name, 
				vlf.proposal_id,
				vl.vendor_lead_code, 
				vl.`user`, vu.user_group as usergroup,vl.identity_number, vl.phone_number
			FROM
				vicidial_list_full_loan AS vlf
				JOIN
				vicidial_list AS vl
				ON 
					vlf.lead_id = vl.lead_id
				JOIN vicidial_users vu ON vl.USER = vu.user
			LEFT JOIN  reject_reason rj on rj.reject_reason = vlf.reject_reason
			WHERE
				vlf.updated_at BETWEEN '$fromDate' AND '$toDate' and vlf.app_status NOT IN ('') $bonus_sql
				ORDER BY vlf.updated_at
				";
				
		// LEFT JOIN vicidial_vicidial_call_notes vcn pn vl.lead_id = vcn.lead_id
		$query 										= $astDB->rawQuery($appstatus_report_query);

		$TOPsorted_output 							= "";
		$number 									= 1;
		foreach ($query as $row) {
			$TOPsorted_output[] 					.= '<tr>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['lead_id'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['request_id'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['user'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['usergroup'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['vendor_lead_code'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['created_at'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['updated_at'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['phone_number'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['identity_number'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['app_status'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['reject_reason'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['description'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['product_name'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['loan_amount'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['proposal_id'].'</td>';
			$TOPsorted_output[] .= '<td nowrap><button type="button" tag="btn_detail" lead_id="'.$row['lead_id'].'" request_id="'.$row['request_id'].'" class="btn btn-block btn-info btn-sm">Chi tiết</button></td>';
			// $TOPsorted_output[] .= '<td nowrap>'.$row['document'].'</td>';
			$TOPsorted_output[] 					.= '</tr>';
		}

		$apiresults 								= array(
		    "result" 									=> "success",
		    "inbound_query" 							=> $inbound_report_query,
		    "query" 									=> $query,
		    "TOPsorted_output" 							=> $TOPsorted_output
		);

		return $apiresults;
	}
