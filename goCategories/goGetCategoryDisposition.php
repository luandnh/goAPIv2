<?php
 /**
 * @file 		goGetAllCategories.php
 * @brief 		API for Categories
 * @copyright 	Copyright (c) 2018 GOautodial Inc.
 * @author		Huy Do
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

	$category_id	= (isset($_REQUEST['category_id']) ? $astDB->escape($_REQUEST['category_id']) : '');
	$query = "SELECT * from vicidial_statuses where  category='${category_id}';";
	$rsltv = $astDB->rawQuery($query);
	$exist = $astDB->getRowCount();

	if($exist >= 1){
		foreach ($rsltv as $fresult){
			$new_data = array();
			array_push($new_data,$fresult['status']);
			array_push($new_data ,$fresult['status_name']);
			array_push($new_data,$fresult['selectable']);
			array_push($new_data,$fresult['human_answered']);
			array_push($new_data,$fresult['sale']);
			array_push($new_data,$fresult['dnc']);
			array_push($new_data,$fresult['customer_contact']);
			array_push($new_data,$fresult['not_interested']);
			array_push($new_data,$fresult['unworkable']);
			array_push($new_data,$fresult['scheduled_callback']);
			array_push($new_data,'');
			$data[]=$new_data;
		}
		$apiresults = array("result" => "success", "data"=> $data);
		
	}else {
		$err_msg = error_handle("41004", "No categories found!");
		$apiresults = array("code" => "41004", "result" => $err_msg);
	}
	
	
?>

