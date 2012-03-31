<?php
/*	This filter calculates the Volume Breakout for all the scrips in NSE midcap database.
	This script must be run after the CM database is updated. This script
	does the following:
	
	1) Calculate average volume for last 5 days.
	2) compare it with today's and yeterday's volume
	3) check whether volume has increased exceptionally.
	
	Author : Rahul Devaskar
	THIS SCRIPT IS A PROPERTY OF BLACKBULL INVESTMENT COMPANY. COPYING, EDMTING
	OR DELETING THIS SCRIPT IS PROHIBITED WITHOUT THE PERMISSION FROM THE AUTHOR.
*/

// Load the database.
require_once('../../common/dba.php');

// Load filter functions.
require_once ('../../common/filter_functions.php');

function processVolumeData($dataStore, $scrip, $avg = 5){
/*Process Volume data as follows
    1. Loop only once to calculate everything
    2. get $avg days MA of volume 
    3. get today's volume
    4. get yesterday's volume
    5. if today's volume & yesterday's volume > avg volume its a breakout.
  */
  
  /* Check whether we have enough data to calculate MA crossover */
  $datacount = count($dataStore);
  $required = $avg + 2;
  if($datacount < $required){
    /* Unable to proceed return zero */
    error_log("Not enough data(count = " . $datacount . ", required = " . $required . "): ". $scrip);
    return 0;
  }
  
  $total_volume = 0;
  
  $today = $dataStore[0]["d"];
  $today_v = $dataStore[0]["v"];
  $today_c = $dataStore[0]["c"];
  $yesterday_v = $dataStore[1]["v"];
  $yesterday_c = $dataStore[1]["c"];
  
  /* Add volumes of $avg days excluding today and yesterday */
  for($i = 2; $i < $required; $i++){
    $curr_data = $dataStore[$i];
    $curr_v = $curr_data["v"];
    $total_volume = $total_volume + $curr_v;
  }
  /* Calculate average of $avg days excluding today and yesterday */
  $avg_volume = $total_volume / $avg;
  if($avg_volume <= 0){
    /* Possible overflow with sign bit set to 1 */
    return 0;
  }else{
    /* Return an array of volume information */
    /* This function does not calcualte the breakouts. It just provides the information */
    /* Breakout is calculated by other function */
    return array("avg" => $avg_volume, "t" => $today_v, "y" => $yesterday_v, "c" => $today_c, "pc" => $yesterday_c, "d" => $today);
  }
}

function fetchDBData($dataSize = 10){
  /* This function fetches data from database
     Curently it makes two queries. one query is just to get the apropriate date in past.
     if possible manage with only one query. 
  */
  /* We need to get the date exactly $dataSize days in past. Thus this query */ 
  $nifty_query = "select niftylatest.timestamp as latest from (SELECT close, timestamp from cmbhav where symbol = 'NIFTY' order by timestamp desc limit " . $dataSize . ") as niftylatest order by niftylatest.timestamp asc limit 1";
  $result = mysql_query($nifty_query);
  if (!$result) {
    echo 'Could not run query1: ' . mysql_error();
    exit;
  }

  $row = mysql_fetch_row($result);
  $first_date = $row[0];
  // Free the result
  mysql_free_result($result);

  $fetch_query = "SELECT niftymidlist.symbol as symbol, close, TOTTRDQTY as volume, timestamp FROM niftymidlist, cmbhav WHERE niftymidlist.symbol = cmbhav.symbol and timestamp > '" . $first_date . "' and cmbhav.series = 'EQ' order by timestamp desc";

  $all_result = mysql_query($fetch_query);
  if (!$all_result) {
    echo 'Could not run query2: ' . mysql_error();
    exit;
  }

  while($row = mysql_fetch_array($all_result)){
    if(!$dataStore[$row['symbol']]){
      $dataStore[$row['symbol']] = array();
    }
    array_push($dataStore[$row['symbol']], array("c" => $row['close'], "v" => $row['volume'], "d" => $row['timestamp']));
  }

  // Free the result
  mysql_free_result($all_result);
  
  return $dataStore;
}

function isBullCross($value, $limit){
   $breakoutVol = (1 + ($limit / 100)) * $value["avg"];
   if($value["y"] >= $breakoutVol && $value["t"] >= $breakoutVol && $value["c"] > $value["pc"] && $value["t"] >= 0.85 * $value["y"]){
     /* We got a bullish breakout */
     return true;
   }else{
     return false;
   }
}

function isBearCross($value, $limit){
   $breakoutVol = (1 + ($limit / 100)) * $value["avg"];
   if($value["y"] >= $breakoutVol && $value["t"] >= $breakoutVol && $value["c"] < $value["pc"] && $value["t"] >= 0.85 * $value["y"]){
     /* We got a bearish breakout */
     return true;
   }else{
     return false;
   }
}

function processVolumeBreakout($data, $limit = 50){
  /*
    Get whether there are volume breakouts.
  */
  $result["B"] = array();
  $result["S"] = array();
  
  foreach($data as $scrip => $value){
    if(isBullCross($value, $limit)){
      array_push($result["B"], array("name" => $scrip, "c" => $value["c"], "d" => $value["d"]));
    }else if(isBearCross($value, $limit)){
      array_push($result["S"], array("name" => $scrip, "c" => $value["c"], "d" => $value["d"]));
    }
  }
  return $result;
}

function getStatus(){
  /* This function checks whether we need to calculate breakouts for today
    If we have already calculated breakouts then exit.
  */
  $status_query = "SELECT id from updatestatus WHERE tablename = 'vbreakout' AND timestamp = CURDATE()";
  $status = mysql_query($status_query);
  if (!$status) {
    echo 'Could not run query4: ' . mysql_error();
    return false;
  }
  if(mysql_num_rows($status)){
    /* The script has run. no need to run it again */
    return true;
  }
  return false;
}

function updateStatus(){
  /* Once VB calculations are done, update the status in updatestatus table
  */
  $update_query = "UPDATE updatestatus SET timestamp= CURDATE() WHERE tablename='vbreakout'";
  $update_status = mysql_query($update_query);
  if (!$update_status) {
    echo 'Could not run query5: ' . mysql_error();
    return false;
  }
  if(mysql_affected_rows()){
    /* Successfully updated status */
    return true;
  }
  return false;
}

function addResultToDB($result){
  /* This function adds identified breakouts to vbreakout table.
  */
  $bullCount = count($result["B"]);
  $bearCount = count($result["S"]);
  if($bullCount == 0 && $bearCount == 0){
    /* No records */
    return true;
  }
  $query_values = "";
  foreach($result as $type => $list){
    foreach($list as $details){
      $query_values = $query_values . ",('" . $details["name"] . "','" . $type . "'," . $details["c"] . ",'" . $details["d"] . "')"; 
    }
  }
  if(strcmp($query_values,"") == 0){
    /* No crossovers today. */
    return true;
  }
  $insert_query = "INSERT INTO vbreakout (symbol, type, close, date) VALUES " . substr($query_values, 1);
  /* Insert the data in macrossover table */
  $insert_result = mysql_query($insert_query);
  if (!$insert_result) {
    echo 'Could not run query3: ' . $insert_query;
    exit;
  }
  return true;
}

function aajVolumeBreakout(){
  /* Calculate today's possible breakouts.
     The result is then added to database.
     This file is run only once per working day.
     The web application does not talk to this file.
     There is another file that provides data to web front.
  */ 
  /* This script runs only once per day
    Check whether we have run it before for today's calculations
  */
  $isRun = getStatus();
  if($isRun){
    /* The script has run. no need to run it again*/
    return true;
  }
  
  /* Set the breakout limit in percentage. e.g. 300 means 300%*/
  $limit = 300;
  
  $dataStore = fetchDBData();
  foreach($dataStore as $scrip => $value){
    $processed = processVolumeData($value, $scrip);
    if($processed){
      /* processMaData returns zero if its unable to calculate */
      $data[$scrip] = $processed;
    }
  }

  $result = processVolumeBreakout($data, $limit);
  
  /* This script does not return anything. 
    It adds the crossover results to macrossover table.
  */
  addResultToDB($result);
  /* Update status */
  updateStatus();
  return true;
}

aajVolumeBreakout();