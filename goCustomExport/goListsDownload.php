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
    if (empty($log_user) || is_null($log_user)) {
        $apiresults 								= array(
            "result" 									=> "Error: Session User Not Defined."
        );
    } elseif(empty($goUser) || is_null($goUser)) {
        $apiresults 								= array(
            "result" 									=> "Error: User Not Defined."
        );
    }else{
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
		// LEFT JOIN vicidial_vicidial_call_notes vcn pn vl.lead_id = vcn.lead_id
        $astDB->where("username", $goUser);
		$query = $astDB->get("vicidial_downloads");
		$TOPsorted_output 							= "";
		$number 									= 1;
		foreach ($query as $row) {
            $tmp = "";
            if ($row['status']=='DONE'){
                $tmp = "<li><a path='".$row['path']."' target='_blank' href='/downloads/".$row['filename']."'>Download</a></li>";
            }
			$TOPsorted_output[] 					.= '<tr>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['name'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['username'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['filename'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['modified_date'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['description'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['status'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['total'].'</td>';
			$TOPsorted_output[] .= '<td nowrap>'.$row['time'].'</td>';
			$TOPsorted_output[] .= "<td nowrap>
                <div class='btn-group'>
                <button type='button' class='btn btn-default dropdown-toggle' data-toggle='dropdown'>Choose Action
							<button type='button' class='btn btn-default dropdown-toggle' data-toggle='dropdown' style='height: 34px;'>
										<span class='caret'></span>
										<span class='sr-only'>Toggle Dropdown</span>
							</button>
							<ul class='dropdown-menu' role='menu'>
                            <li><a tag='remove-list' file-name='".$row['filename']."' file-path='".$row['path']."' href='#'>Delete (Not Ready)</a></li>".$tmp."
                            
							</ul>
						</div>
            </td>";
			$TOPsorted_output[] 					.= '</tr>';
		}

		$apiresults 								= array(
		    "result" 									=> "success",
		    "query" 									=> $query,
		    "TOPsorted_output" 							=> $TOPsorted_output
		);

		return $apiresults;
	}
