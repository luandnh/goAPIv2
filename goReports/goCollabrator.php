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
    if (empty($fromDate)) {
        $fromDate 									= "2021-01-01 00:00:00";
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
		$bonus_sql = "";
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
			SELECT * from vicidial_collaborator WHERE create_date BETWEEN '$fromDate' AND '$toDate' ORDER BY create_date  DESC";
		$query 										= $astDB->rawQuery($appstatus_report_query);
		$TOPsorted_output 							= "";
		$number 									= 1;
		foreach ($query as $row) {
			$TOPsorted_output[] 					.= '<tr>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['sale_code'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['partner_code'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['full_name'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['identity_card_id'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['issue_date'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['referral_code'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['phone_number'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['date_of_birth'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['collaborator_address'].'</td>';
			$TOPsorted_output[] .= '<td nowrap><a href="'.$row['img_id_card_front'].'">'.$row['img_id_card_front'].'</a></td>';
			$TOPsorted_output[] .= '<td nowrap><a href="'.$row['img_id_card_back'].'">'.$row['img_id_card_back'].'</td>';
			$TOPsorted_output[] .= '<td nowrap><a href="'.$row['img_selfie'].'">'.$row['img_selfie'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['create_date'].'</td>';
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
