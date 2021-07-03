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
    $campaignID 									= $astDB->escape($_REQUEST['campaignID']);
    
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
		$user_group_string = implode("','",$user_groups);
	    // set tenant value to 1 if tenant - saves on calling the checkIfTenantf function
	    // every time we need to filter out requests
	    $tenant 									=  (checkIfTenant ($log_group, $goDB)) ? 1 : 0;
		$SELECTQuery = $astDB->rawQuery("Select sub_user_group from vicidial_sub_user_groups where user_group='".$log_group."'");
		foreach($SELECTQuery as $user_group){
			$user_groups[] = $user_group["sub_user_group"];
		}
		array_push($user_groups,$log_group);
		$user_group_string = implode("','",$user_groups);
		$bonus_sql = "";
	    if ($tenant) {
			$astDB->where("vl.user_group", $user_groups,"IN");
			$bonus_sql = "AND vl.user_group IN('".$user_group_string."') ";
	    } else {
            if (strtoupper($log_group) != 'ADMIN') {
				$astDB->where("vl.user_group", $user_groups,"IN");
				$bonus_sql = "AND vl.user_group IN('".$user_group_string."') ";
            }
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
		$campaign_sql = "";
		if ($campaignID != "" && $campaignID != "ALL"){
			$campaign_sql = " vl.campaign_id ='".$campaignID."' AND";
		}
		$rp_sql = "";
		if (isset($fromDate) && isset($toDate)){
			$rp_sql = " AND vli.modify_date BETWEEN '$fromDate' AND '$toDate' ";
		}
		// $agent_report_query= "SELECT vug.user_group, sum(IF(vl.length_in_sec>=0, vl.length_in_sec, 0)) as total_talk, COUNT(vl.phone_number) as total_call,
		// (SELECT Count(vli.lead_id) FROM vicidial_list as vli LEFT JOIN vicidial_users vu ON  vu.user = vli.user WHERE vli.list_id = vl.list_id and  vli.app_status = 'NE' AND vu.user_group = vug.user_group $rp_sql) as not_eligable,
		// (SELECT Count(vli.lead_id) FROM vicidial_list as vli LEFT JOIN vicidial_users vu ON  vu.user = vli.user WHERE vli.list_id = vl.list_id and  vli.app_status = 'NI' AND vu.user_group = vug.user_group $rp_sql) as not_interested,
		// (SELECT Count(vli.lead_id) FROM vicidial_list as vli LEFT JOIN vicidial_users vu ON  vu.user = vli.user WHERE vli.list_id = vl.list_id and  vli.app_status = 'AC' AND vu.user_group = vug.user_group $rp_sql) as app_created,
		// (SELECT Count(vli.lead_id) FROM vicidial_list as vli LEFT JOIN vicidial_users vu ON  vu.user = vli.user WHERE vli.list_id = vl.list_id and  vli.app_status = 'AP' AND vu.user_group = vug.user_group $rp_sql) as app_approved,
		// (SELECT Count(vli.lead_id) FROM vicidial_list as vli LEFT JOIN vicidial_users vu ON  vu.user = vli.user WHERE vli.list_id = vl.list_id and  vli.STATUS != 'NEW' AND vu.user_group = vug.user_group $rp_sql) as total_contacted
		// FROM vicidial_user_groups vug left JOIN vicidial_log vl ON vug.user_group = vl.user_group
		// WHERE ".$campaign_sql." vl.call_date BETWEEN '$fromDate' AND '$toDate' ".$bonus_sql."
		// GROUP BY vug.user_group";
		$agent_report_query = "
		SELECT vl.user_group,
			COUNT(vl.lead_id) as total_call,
			(SELECT (SUM(val.talk_sec) - SUM(val.dead_sec)) as total_talk FROM vicidial_agent_log val WHERE val.event_time  BETWEEN '$fromDate' AND '$toDate' and val.`user` = vl.`user`) as total_talk,
			COUNT(vli.app_status = 'NE') as not_eligable,
			COUNT(vli.app_status = 'NI') as not_interested,
			COUNT(vli.app_status = 'AC') as app_created,
			COUNT(vli.app_status = 'AP') as app_approved,
			COUNT(vdl.sip_hangup_cause = 200) as total_contacted
			FROM vicidial_log vl INNER JOIN vicidial_dial_log vdl on  ((FLOOR(vl.uniqueid) = FLOOR(vdl.uniqueid)) or (FLOOR(vl.uniqueid) = FLOOR(vdl.uniqueid)-1)  or (FLOOR(vl.uniqueid) = FLOOR(vdl.uniqueid) + 1)) and vl.lead_id = vdl.lead_id
			LEFT JOIN  vicidial_agent_log val on  ((FLOOR(vl.uniqueid) = FLOOR(val.uniqueid)) or (FLOOR(vl.uniqueid) = FLOOR(val.uniqueid)-1)  or (FLOOR(vl.uniqueid) = FLOOR(val.uniqueid) + 1))  and vl.lead_id = val.lead_id
			LEFT JOIN vicidial_list vli on vli.lead_id = vl.lead_id
			LEFT JOIN vicidial_users  vu on vl.user = vu.user
			LEFT JOIN vicidial_user_groups vug on vu.user_group = vug.user_group
		WHERE $campaign_sql vl.call_date BETWEEN '$fromDate' AND '$toDate'  and (vl.user_group is not NULL or vl.user in ('VDAD')) 
		GROUP BY vl.user_group
		";
        // file_put_contents("QUANGBUG.log", $agent_report_query, FILE_APPEND | LOCK_EX);
		$query 										= $astDB->rawQuery($agent_report_query);
		$TOPsorted_output 							= "";
		$number 									= 1;
		foreach ($query as $row) {
			$TOPsorted_output[] 					.= '<tr>';
		    $TOPsorted_output[] 					.= '<td nowrap>'.$row['user_group'].'</td>';
		    $TOPsorted_output[] 					.= '<td nowrap>'.$row['total_call'].'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.$row['total_contacted'].'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.convert($row['total_talk']).'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.'0'.'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.$row['not_interested'].'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.$row['not_eligable'].'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.$row['app_created'].'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.$row['app_approved'].'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.'0'.'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.'0'.'</td>';
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
?>
