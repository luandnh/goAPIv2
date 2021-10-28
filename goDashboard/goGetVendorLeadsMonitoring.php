<?php
/**
 * @file 		goGetRealtimeAgentsMonitoring.php
 * @brief 		API for Dashboard
 * @copyright 	Copyright (c) 2018 GOautodial Inc.
 * @author		Jerico James Milo
 * @author     	Demian Lizandro A. Biscocho
 * @author     	Chris Lomuntad
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

$campaigns = allowed_campaigns($log_group, $goDB, $astDB);

// ERROR CHECKING
if (empty($goUser) || is_null($goUser))
{
    $apiresults = array(
        "result" => "Error: goAPI User Not Defined."
    );
}
elseif (empty($goPass) || is_null($goPass))
{
    $apiresults = array(
        "result" => "Error: goAPI Password Not Defined."
    );
}
elseif (empty($log_user) || is_null($log_user))
{
    $apiresults = array(
        "result" => "Error: Session User Not Defined."
    );
}
else
{
    $astDB->where('user_group', $log_group);
    $allowed_camps = $astDB->getOne('vicidial_user_groups', 'allowed_campaigns');
    $allowed_campaigns = $allowed_camps['allowed_campaigns'];
    $allowed_campaigns = explode(" ", trim($allowed_campaigns));

    // check if goUser and goPass are valid
    $fresults = $astDB->where("user", $goUser)->where("pass_hash", $goPass)->getOne("vicidial_users", "user,user_level");

    $goapiaccess = $astDB->getRowCount();
    $userlevel = $fresults["user_level"];

    if ($goapiaccess > 0 && $userlevel > 7)
    {
		$to_day = date('Y-m-d');
		$begin_date = $to_day . " 00:00:00";
		$end_date = $to_day . " 23:59:59";
		$SQLquery = "select COUNT(lead_id) as total, COUNT(if(`status` != 'NEW',1,NULL)) as called, COUNT(if(`status` = 'NEW',1,NULL)) as not_call, if (vendor_lead_code='','NONE',vendor_lead_code) as vendor_lead_code, list_id from vicidial_list WHERE entry_date  BETWEEN '$begin_date' and '$end_date' 
		GROUP BY vendor_lead_code ORDER BY total DESC";
		$vendor_summary = $astDB->rawQuery($SQLquery);
        	$apiresults = array(
			"result" => "success",
			"data" => $vendor_summary
		);

    }
    else
    {
        $err_msg = error_handle("10001");
        $apiresults = array(
            "code" => "10001",
            "result" => $err_msg
        );
    }
}

