<?php
 /**
 * @file 		goChatHistory.php
 * @brief 		API for Manager Chat
 * @copyright 	Copyright (C) GOautodial Inc.
 * @author     	Chris Lomuntad  <chris@goautodial.com>
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
$file_name                                             = $astDB->escape($_REQUEST["file_name"]);
$file_path                                             = $astDB->escape($_REQUEST["path"]);
if (isset($file_name) && $file_name !== '' &&isset($file_path) && $file_path !== '') {
	try {
        unlink($file_path);
    } catch (\Throwable $th) {
        $apiresults = array( "result" => "error", "message" => $th->getMessage() );
    }
    $astDB->where('filename', $file_name);
    $rslt = $astDB->delete('vicidial_downloads');
    $apiresults = array( "result" => "success");
} else {
	$apiresults = array( "result" => "error", "message" => "Field 'file_name','path' should not be empty." );
}
?>