<?php

/**
 * @file 		goGetAgentTimeDetail.php
 * @brief 		API for Get Agent Time Detail
 * @copyright 	Copyright (C) GOautodial Inc.
 * @author     	Luan Duong Nguyen Hoang <luandnh1998@gmail.com>
 * @author     	Luan Duong Nguyen Hoang <luandnh1998@gmail.com>
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

$fromDate                                         = (empty($_REQUEST['fromDate']) ? date("Y-m-d") . " 00:00:00" : $astDB->escape($_REQUEST['fromDate']));
$toDate                                         = (empty($_REQUEST['toDate']) ? date("Y-m-d") . " 23:59:59" : $astDB->escape($_REQUEST['toDate']));
$campaign_id                                     = (empty($_REQUEST['campaignID']) ? "ALL": $astDB->escape($_REQUEST['campaignID']));
$limit                                            = 1000;
$defPage                                         = "agent_detail";

// Error Checking
if (empty($goUser) || is_null($goUser)) {
    $apiresults                                 = array(
        "result"                                     => "Error: goAPI User Not Defined."
    );
} elseif (empty($goPass) || is_null($goPass)) {
    $apiresults                                 = array(
        "result"                                     => "Error: goAPI Password Not Defined."
    );
} else {
    // check if goUser and goPass are valid
    $fresults                                     = $astDB
        ->where("user", $goUser)
        ->where("pass_hash", $goPass)
        ->getOne("vicidial_users", "user,user_level");

    $goapiaccess                                 = $astDB->getRowCount();
    $userlevel                                     = $fresults["user_level"];

    if ($goapiaccess > 0 && $userlevel > 0) {
        // Agent Time Detail

        $TOTtimeTC = array();

        $timeclock_ct = $astDB
            ->where("event", array("LOGIN", "START"), "IN")
            ->where("date_format(event_date, '%Y-%m-%d %H:%i:%s')", array($fromDate, $toDate), "BETWEEN")
            ->where("user",$goUser)
            ->groupBy("user")
            ->get("vicidial_timeclock_log", "user, SUM(login_sec) as login_sec");

        if ($astDB->count > 0) {
            foreach ($timeclock_ct as $row) {
                $TCuser                     = $row['user'];
                $TCtime                     = $row['login_sec'];

                array_push($TOTtimeTC, $TCtime);
            }
        }

        $sub_statuses                         = '-';
        $sub_statusesTXT                     = '';
        $sub_statusesHEAD                     = '';
        $sub_statusesHTML                     = '';
        $sub_statusesFILE                     = '';
        $sub_statusesTOP                     = array();
        $sub_statusesARY                     = array();

        $PCusers                             = '-';
        $PCuser_namesARY                    = array();
        $PCusersARY                         = array();
        $PCpause_secsARY                    = array();

        if ("ALL" === strtoupper($campaign_id)) {
            $SELECTQuery = $astDB->get("vicidial_campaigns", NULL, "campaign_id");

            foreach ($SELECTQuery as $camp_val) {
                $array_camp[] = $camp_val["campaign_id"];
            }
        } else {
            $array_camp[]         = $campaign_id;
        }

        $cols = array(
            "vu.full_name",
            "val.user",
            "SUM(pause_sec) as pause_sec",
            "sub_status"
        );

        $pcs_data = $astDB
            ->join("vicidial_users as vu", "val.user = vu.user", "LEFT")
            ->where("date_format(event_time, '%Y-%m-%d %H:%i:%s')", array($fromDate, $toDate), "BETWEEN")
            //->where("pause_sec", 0, ">")
            //->where("pause_sec", 65000, "<")
            ->where("pause_sec", array(0, 65000), "BETWEEN")
            ->where("campaign_id", $array_camp, "IN")
            ->where("sub_status", array("LAGGED", "LOGIN"), "NOT IN")
            ->where("vu.user",$goUser)
            ->groupBy("vu.user,sub_status")
            ->orderBy("vu.user,sub_status")
            ->get("vicidial_agent_log as val", $limit, $cols);

        if ($astDB->count > 0) {
            foreach ($pcs_data as $pc_data) {
                $PCfull_name[]    = $pc_data['full_name'];
                $PCuser[]     = $pc_data['user'];
                $PCpause_sec[]     = $pc_data['pause_sec'];
                $sub_status[]     = $pc_data['sub_status'];
            }

            $Boutput = array(
                "full_name"     => $PCfull_name,
                "user"         => $PCuser,
                "pause_sec"     => $PCpause_sec,
                "sub_status"    => $sub_status
            );

            $SUMstatuses = array_sum($PCpause_secsARY);

            $BoutputFile = array(
                "statuses" => $PCpause_secsARY
            );
        }

        $cols = array(
            "vu.full_name",
            "val.user",
            "wait_sec",
            "talk_sec",
            "dispo_sec",
            "pause_sec",
            "status",
            "dead_sec",
        );

        $agenttd = $astDB
            ->join("vicidial_users vu", "val.user = vu.user", "LEFT")
            ->where("date_format(event_time, '%Y-%m-%d %H:%i:%s')", array($fromDate, $toDate), "BETWEEN")
            ->where("campaign_id", $array_camp, "IN")
            ->where("status != 'NULL'")
            ->where("vu.user",$goUser)
            ->orderBy("user", "DESC")
            ->get("vicidial_agent_log val", 10000000, $cols);

        $query_td = $astDB->getLastQuery();
        $usercount = $astDB->getRowCount();

        $agenttotalcalls = $astDB
            ->where("date_format(vl.call_date, '%Y-%m-%d %H:%i:%s')", array($fromDate, $toDate), "BETWEEN")
            ->where("campaign_id", $array_camp, "IN")
            ->where("vu.user = vl.user")
            ->where("vu.user", $goUser)
            ->where("vu.user",$goUser)
            ->groupBy("vl.user")
            ->get("vicidial_users vu, vicidial_log vl", $limit, "vl.user, count(vl.lead_id) as calls");

        if($astDB->count >0){
            $total_calls = $agenttotalcalls[0]["calls"];
            $agent_user = $agenttd[0]['user'];
            $agent_name = $agenttd[0]['full_name'];
            foreach($agenttd as $row){
                $wait = $row['wait_sec'];
                $talk = $row['talk_sec'];
                $dispo = $row['dispo_sec'];
                $pause = $row['pause_sec'];
                $dead = $row['dead_sec'];
                $customer = $row['talk_sec'] - $row['dead_sec'];

                if ($wait > 65000) {
                    $wait = 0;
                }
                if ($talk > 65000) {
                    $talk = 0;
                }
                if ($dispo > 65000) {
                    $dispo = 0;
                }
                if ($pause > 65000) {
                    $pause = 0;
                }
                if ($dead > 65000) {
                    $dead = 0;
                }
                if ($customer < 1) {
                    $customer = 0;
                }

                $total_wait =      ($total_wait + $wait);
                $total_talk =      ($total_talk + $talk);
                $total_dispo =     ($total_dispo + $dispo);
                $total_pause =     ($total_pause + $pause);
                $total_dead =      ($total_dead + $dead);
                $total_customer =  ($total_customer + $customer);
                $total_time = ($total_time + $pause + $dispo + $talk + $wait);
            }
        }
        // // Check if the user had an AUTOLOGOUT timeclock event during the time period
        // $TCuserAUTOLOGOUT = ' ';

        // $timeclock_ct = $astDB
        //     ->where("event", "AUTOLOGOUT")
        //     ->where("user", $user)
        //     ->where("date_format(event_date, '%Y-%m-%d %H:%i:%s')", array($fromDate, $toDate), "BETWEEN")
        //     ->getValue("vicidial_timeclock_log", "count(*)");

        // if ($timeclock_ct > 0) {
        //     $TCuserAUTOLOGOUT = '*';
        // }

        $agent_result = array(
            "name"             => $agent_name,
            "user"             => $agent_user,
            "number_of_calls"     => $total_calls,
            "agent_time"         => convert($total_time),
            "wait_time"         => convert($total_wait),
            "talk_time"         => convert($total_talk),
            "dispo_time"         => convert($total_dispo),
            "pause_time"         => convert($total_pause),
            "wrap_up"         => convert($total_dead),
            "customer_time"     => convert($total_customer)
        );

        $APIResult = array(
            "result"         => "success",
            "data"     => $agent_result
        );

        return $APIResult;
    } else {
        $err_msg                                     = error_handle("10001");
        $APIResult                                 = array(
            "code"                                         => "10001",
            "result"                                     => $err_msg
        );
    }
}
