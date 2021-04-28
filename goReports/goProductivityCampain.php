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
		$SELECTQuery = $astDB->rawQuery("Select sub_user_group from vicidial_sub_user_groups where user_group='".$log_group."'");
		foreach($SELECTQuery as $user_group){
			$user_groups[] = $user_group["sub_user_group"];
		}
		array_push($user_groups,$log_group);
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
	            // $astDB->where("user_group", $log_group);
				$astDB->where("vl.user_group", $user_groups,"IN");
				$bonus_sql = "AND vl.user_group IN('".$user_group_string."') ";
	    } else {
            if (strtoupper($log_group) != 'ADMIN') {
                // if ($user_level > 8) {
                //     $astDB->where("user_group", $log_group);
                // }
				$bonus_sql = "AND vl.user_group IN('".$user_group_string."') ";
				$astDB->where("vl.user_group", $user_groups,"IN");
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
		$agent_report_query= "
		SELECT
			vc.campaign_name,
			COUNT(vl.lead_id) as total_lead,
			COUNT(if(vl.status != 'NEW',vl.campaign_id,null)) as contacted,
			COUNT(if(vl.status = 'NEW',vl.campaign_id,null)) as not_contacted,
			COUNT(if(vli.app_status = 'NI',vl.campaign_id,null)) as not_interested,
			COUNT(if(vli.app_status = 'NE',vl.campaign_id,null)) as not_eligable,
			COUNT(if(vli.app_status = 'AC',vl.campaign_id,null)) as app_created,
			(COUNT(if(vl.status != 'NEW',vl.campaign_id,null))/COUNT(vl.lead_id)*100) as contacted_per,
			(COUNT(if(vli.app_status = 'AC',vl.campaign_id,null))/COUNT(vl.lead_id)*100) as app_created_per
		FROM vicidial_log as vl
		JOIN vicidial_list as vli on vli.lead_id = vl.lead_id
		RIGHT JOIN vicidial_campaigns as vc ON vl.campaign_id = vc.campaign_id
		WHERE ".$campaign_sql." vl.call_date BETWEEN '$fromDate' AND '$toDate'  ".$bonus_sql."
		GROUP BY vl.campaign_id";
		$query 										= $astDB->rawQuery($agent_report_query);
		$TOPsorted_output 							= "";
		$number 									= 1;
		foreach ($query as $row) {
			$TOPsorted_output[] 					.= '<tr>';
		    $TOPsorted_output[] 					.= '<td nowrap>'.$row['campaign_name'].'</td>';
		    $TOPsorted_output[] 					.= '<td nowrap>'.$row['total_lead'].'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.$row['not_contacted'].'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.$row['contacted'].'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.'0'.'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.$row['not_interested'].'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.$row['not_eligable'].'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.$row['app_created'].'</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.($row['contacted_per']+0).'%</td>';
			$TOPsorted_output[] 					.= '<td nowrap>'.($row['app_created_per']+0).'%</td>';
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
