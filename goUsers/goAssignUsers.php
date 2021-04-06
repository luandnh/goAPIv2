<?php

/**
 * @file 		goAssignUsers.php
 * @brief 		API for Assigning Agents to Campaign
 * @copyright 	Copyright (C) TEL4VN
 * @author     	luandnh <luandnh98@gmail.com>
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

if (isset($_GET['campaign_id'])) {
    $campaign_id = $_GET['campaign_id'];
} else if (isset($_POST['campaign_id'])) {
    $campaign_id = $_POST['campaign_id'];
}
if (isset($_GET['users'])) {
    $users = $_GET['users'];
} else if (isset($_POST['users'])) {
    $users = $_POST['users'];
}
if (isset($_GET['usergroups'])) {
    $usergroups = $_GET['usergroups'];
} else if (isset($_POST['usergroups'])) {
    $usergroups = $_POST['usergroups'];
}
if (isset($_GET['is_usergroups'])) {
    $is_usergroups = $_GET['is_usergroups'];
} else if (isset($_POST['is_usergroups'])) {
    $is_usergroups = $_POST['is_usergroups'];
}
if (isset($_GET['is_users'])) {
    $is_users = $_GET['is_users'];
} else if (isset($_POST['is_users'])) {
    $is_users = $_POST['is_users'];
}
$assignees = [];
if ($is_usergroups == "true") {
    $astDB->where("user_group", $usergroups, "IN")
        ->where("user_level", 4, "!=")
        ->where("user_level", 8, "<")
        ->orderBy("user_id", "asc");
    $rslt = $astDB->get('vicidial_users', null, "user");
    foreach ($rslt as $user) {
        array_push($assignees, $user["user"]);
    }
} else {
    foreach ($users as $user_id) {
        $astDB->where("user_level", 4, "!=")
            ->where("user_level", 8, "<")
            ->where("user_id", $user_id)
            ->orderBy("user_id", "asc");
        $rslt = $astDB->get('vicidial_users', null, "user");
        foreach ($rslt as $user) {
            array_push($assignees, $user["user"]);
        }
    }
}
if (count($assignees) > 0) {
    $astDB->where("user", $assignees, "NOT IN")->where('campaign_id', $campaign_id);
}
$delete_status = $astDB->delete('vicidial_campaign_agents', null);
if (count($assignees) >= 0) {
    $total = 0;
    foreach ($assignees as $user) {
        $astDB->where('user', $user);
        $astDB->where('campaign_id', $campaign_id);
        $rslt = $astDB->get('vicidial_campaign_agents');
        $existingCampaignUser = $astDB->getRowCount();
        if ($existingCampaignUser < 1) {
            $insertData = array(
                'user' => $user,
                'campaign_id' => $campaign_id,
                'campaign_rank' => 0,
                'campaign_weight' => 0,
                'calls_today' => 0,
                'group_web_vars' => '',
                'campaign_grade' => 1
            );
            $astDB->insert('vicidial_campaign_agents', $insertData);
            $total += 1;
        }
    }
    $apiresults                         = array(
        "result"                     => "success",
        "created"                     => true,
        "total"                        => $total
    );
} else {
    $err_msg                                     = error_handle("10001");
    $apiresults                                 = array(
        "code"                                         => "10001",
        "result"                                     => $err_msg
    );
}
