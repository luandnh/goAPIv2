<?php

/**
 * @file 		goGetAssignedUsers.php
 * @brief 		API to get assigned User Lists 
 * @copyright 	Copyright (c) 2021 TEL4VN.
 * @author     	Hoang Luan
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
 **/
include_once("goAPI.php");
include_once("../licensed-conf.php");

$campaign_id                                                 = $astDB->escape($_REQUEST['campaign_id']);

// Error Checking
if (empty($goUser) || is_null($goUser)) {
    $apiresults                                     = array(
        "result"                                         => "Error: goAPI User Not Defined."
    );
} elseif (empty($goPass) || is_null($goPass)) {
    $apiresults                                     = array(
        "result"                                         => "Error: goAPI Password Not Defined."
    );
} elseif (empty($log_user) || is_null($log_user)) {
    $apiresults                                     = array(
        "result"                                         => "Error: Session User Not Defined."
    );
} else {
    // check if goUser and goPass are valid
    $fresults                                        = $astDB
        ->where("user", $goUser)
        ->where("pass_hash", $goPass)
        ->getOne("vicidial_users", "user,user_level");

    $goapiaccess                                    = $astDB->getRowCount();
    $userlevel                                        = $fresults["user_level"];

    if ($goapiaccess > 0 && $userlevel > 7) {
        // set tenant value to 1 if tenant - saves on calling the checkIfTenantf function
        // every time we need to filter out requests
        $tenant                                        = (checkIfTenant($log_group, $goDB)) ? 1 : 0;

        if ($tenant) {
            $astDB->where("user_group", $log_group);
            $astDB->orWhere("user_group", "---ALL---");
        } else {
            if (strtoupper($log_group) != 'ADMIN') {
                //if ($userlevel > 8) {
                $astDB->where("user_group", $log_group);
                $astDB->orWhere("user_group", "---ALL---");
                //}
            }
        }

        // get users list
        $cols                                         = array(
            "vu.user_id",
            "vu.user",
            "vca.campaign_id",
            "vca.calls_today",
            "vu.full_name"
        );
        if (!empty($campaign_id)) {
            $astDB->where("vca.campaign_id", $campaign_id);
        }
        $assigned                                         = $astDB
            ->where("vu.user", DEFAULT_USERS, "NOT IN")
            ->where("vu.user_level", 4, "!=")
            ->join("vicidial_users vu", "vu.user = vca.user", "inner")
            ->orderBy("vu.user_id", "asc")
            ->get("vicidial_campaign_agents vca", NULL, $cols);

        $cols                                         = array(
            "user_id",
            "user",
            "full_name",
            "user_level",
            "user_group",
            "phone_login",
            "active"
        );

        $query                                         = $astDB
            ->where("user", DEFAULT_USERS, "NOT IN")
            ->where("user_level", 4, "!=")
            ->where("user_level", 8, "<")
            ->orderBy("user", "asc")
            ->get("vicidial_users", NULL, $cols);
        if ($astDB->count >= 0) {
            $data = array(
                "all" => [],
                "assigned" => [],
            );

            if ($astDB->count > 0) {
                $data = array(
                    "all" => $query,
                    "assigned" => $assigned,
                );
            }
            $apiresults                             = array(
                "result"                         => "success",
                "data"                            => $data
            );
        } else {
            $err_msg                                 = error_handle("10010");
            $apiresults                             = array(
                "code"                                     => "10010",
                "result"                                 => $err_msg
            );
        }
    } else {
        $err_msg                                     = error_handle("10001");
        $apiresults                                 = array(
            "code"                                         => "10001",
            "result"                                     => $err_msg
        );
    }
}
