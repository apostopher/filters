<?php
/*  This filter calculates the MACDs for all the scrips in NSE database.
  This script must be run after the CM database is updated. This script
  does the following:
  
  1) Calculate MACDs for all the stocks.
  2) Calculate the first EMA values for new scrips.
  
  Author : Rahul Devaskar
  THIS SCRIPT IS A PROPERTY OF BLACKBULL INVESTMENT COMPANY. COPYING, EDMTING
  OR DELETING THIS SCRIPT IS PROHIBITED WITHOUT THE PERMISSION FROM THE AUTHOR.
*/

// Load the database.
require_once('../../common/dba.php');

// get data upto 3 days old.
// today, yesterday and a day before yesterday

$date_query = "SELECT date FROM macdcrossover GROUP BY date ORDER BY date DESC LIMIT 3";
$date_result = mysql_query($date_query);
if($date_result){
	//Get number of rows
	$date_count = mysql_num_rows($date_result);
	//Get the oldest date
	if($date_count > 0){
		$latest_row = mysql_fetch_assoc($date_result);
		$latest_date = $latest_row['date'];
		mysql_data_seek($date_result, $date_count - 1);
		$date_row = mysql_fetch_assoc($date_result);
		$old_date = $date_row['date'];
		// Now fetch all the data upto this date
		$data_query = "SELECT niftylist.symbol, type, close, date, cname FROM macdcrossover, niftylist WHERE macdcrossover.symbol = niftylist.symbol AND date >= '".$old_date."' ORDER BY date DESC";
		$data_result = mysql_query($data_query);
		$scrips = "";
		if($data_result){
			$calls = array();
			$temp_calls = array();
			while($data_row = mysql_fetch_array($data_result)){
				$data_date = $data_row['date'];
				$scrips .= " symbol = '".$data_row['symbol']."' OR";
				$call = array("symbol" => $data_row['symbol'], "name" => $data_row['cname'], "close" => $data_row['close'], "type" => $data_row['type']);
				// Add to history array
				if(!$temp_calls[$data_date]){
					$temp_calls[$data_date] = array();
				}
				array_push($temp_calls[$data_date], $call);
			}
			// Get the latest cmdate
			$latest_cm = "SELECT timestamp FROM updatestatus WHERE tablename = 'cmbhav'";
			$cm_result = mysql_query($latest_cm);
			if($cm_result){
				if(mysql_num_rows($cm_result) == 1){
					$cm_row = mysql_fetch_assoc($cm_result);
					$cm_date = $cm_row['timestamp'];
				}
			}
			if($cm_date){
				//Get the latest close values from cmbhav
				$cm_close = "SELECT symbol, close from cmbhav WHERE".$scrips." 0";
				$close_result = mysql_query($cm_close);
				$close_array = array();
				if($close_result){
					while($row = mysql_fetch_array($close_result)){
						$close_array[$row['symbol']] = $row['close'];
					}
				}
			}
			// Fill the history array
			foreach ($temp_calls as $key => $value){
				array_push($calls, array("date" => $key, "calls" => $value));
			}
			$response = array("data" => $calls, "c" => $close_array);
		}else{
			$response = array("data" => array());
		}
	}else{
		$response = array("data" => array());
	}
	
}else{
	$response = array("data" => array());
}

// send the response
header('Content-type: application/json');
if(strstr($_SERVER["HTTP_USER_AGENT"],"MSIE")==false) {
  header("Cache-Control: no-cache");
  header("Pragma: no-cache");
}
echo json_encode($response);
mysql_close($con);
?>