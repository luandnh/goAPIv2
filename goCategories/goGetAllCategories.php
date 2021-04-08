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

	// $query = "SELECT * from vicidial_status_categories;";
	$query = "SELECT vsc.vsc_id, vsc.vsc_name,vsc.vsc_description,vsc.tovdad_display, vsc.sale_category, vsc.dead_lead_category,
	COUNT(vsc.vsc_id) as total_status
	From vicidial_status_categories as vsc left join vicidial_statuses vs on vsc.vsc_id = vs.category group by vsc.vsc_id  ORDER BY total_status desc";
	$rsltv = $astDB->rawQuery($query);
	$exist = $astDB->getRowCount();

	if($exist >= 1){
		foreach ($rsltv as $fresult){
			$data[]=$fresult;
		}
		$apiresults = array("result" => "success", "data"=> $data);
		
	}else {
		$err_msg = error_handle("41004", "No categories found!");
		$apiresults = array("code" => "41004", "result" => $err_msg);
	}
	
	
?>

