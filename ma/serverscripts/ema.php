<?php
/*  This filter calculates the EMAs for all the scrips in NSE database.
  This script must be run after the CM database is updated. This script
  does the following:
  
  1) Calculate EMAs for all the stocks.
  2) Calculate the first EMA values for new scrips.
  3) Move changed/delisted scrips to obsolete data table.
  
  Author : Rahul Devaskar
  THIS SCRIPT IS A PROPERTY OF BLACKBULL INVESTMENT COMPANY. COPYING, EDMTING
  OR DELETING THIS SCRIPT IS PROHIBITED WITHOUT THE PERMISSION FROM THE AUTHOR.
*/

// Load the database.
require_once('../../common/dba.php');

// Load filter functions.
require_once ('../../common/filter_functions.php');

function processMaData($dataStore, $scrip, $sm = 12, $mid = 26, $long = 154){
  /*Process MA data as follows
    1. Loop only once to calculate everything
    2. get 154 days MA
    3. get 154 days MA 5 days back
    4. get 5 day MA for today and yesterday
    5. get 22 day MA for today and yesterday
    6. get 5 day MA of volume
    7. get today's Volume
  */
  /* Check whether we have enough data to calculate MA crossover */
  $datacount = count($dataStore);
  $required = $long + 5;
  if($datacount < $required){
    /* Unable to proceed return zero */
    error_log("Not enough data(count = " . $datacount . ", required = " . $required . "): ". $scrip);
    return 0;
  }
  $count_sm = $sm;
  $count_smy = $sm;
  $count_mid = $mid;
  $count_midy = $mid;
  $count_long = $long;
  $count_longy = $long;
  
  $total_sm = 0;
  $total_smy = 0;
  $total_mid = 0;
  $total_midy = 0;
  $total_long = 0;
  $total_longy = 0;

  for($i = 0; $i < $long + 5; $i++){
    $curr_data = $dataStore[$i];
    $curr_c = $curr_data["c"];
    $curr_v = $curr_data["v"];
    
    if($count_sm){
      $total_sm = $total_sm + $curr_c;
      $count_sm = $count_sm - 1;
    }
    
    if($count_smy && $i){
      /* Need to calculate small term average of volume too */
      $total_smy = $total_smy + $curr_c;
      $total_v = $total_v + $curr_v;
      $count_smy = $count_smy - 1;
    }
    
    if($count_mid){
      $total_mid = $total_mid + $curr_c;
      $count_mid = $count_mid - 1;
    }
    
    if($count_midy && $i){
      $total_midy = $total_midy + $curr_c;
      $count_midy = $count_midy - 1;
    }
    
    if($count_long){
      $total_long = $total_long + $curr_c;
      $count_long = $count_long - 1;
    }
    
    if($count_longy && $i >= 4){
      $total_longy = $total_longy + $curr_c;
      $count_longy = $count_longy - 1;
    }
    if($i == 0){
      $volume = $curr_v;
      $close = $curr_c;
      $db_today = $curr_data["d"];
      $today = date("Y-m-d");
      if(strtotime($today) > strtotime($db_today)){
        /* Database information is old. */
        echo "Database info is old";
        exit;
      }
    }
  }
  return array("sm" => $total_sm / $sm, "smy" => $total_smy / $sm, "mid" => $total_mid / $mid, "midy" => $total_midy / $mid, "long" => $total_long / $long, "longy" => $total_longy / $long, "v" => $total_v / $sm, "vc" => $volume, "c" => $close, "d" => $db_today);   
}

function isBullCross($value){
  /* If small ema crosses mid ema upwords
     and if long ema is upwords
     and if volume is more than average
     then its a bullish cross.
  */
  if(($value["sm"] > $value["mid"]) && ($value["smy"] < $value["midy"]) && ($value["long"] > $value["longy"]) && ($value["vc"] > $value["v"])){
    return true;
  }else{
    return false;
  }
}

function isBearCross($value){
  /* If small ema crosses mid ema downwords
     and if long ema is downwords
     and if volume is more than average
     then its a bearish cross.
  */
  if(($value["sm"] < $value["mid"]) && ($value["smy"] > $value["midy"]) && ($value["long"] < $value["longy"]) && ($value["vc"] > $value["v"])){
    return true;
  }else{
    return false;
  }
}

function processMaCrossOver($data){
  /*
    Get whether there are any bullish or bearish crossovers.
  */
  $result["B"] = array();
  $result["S"] = array();
  
  foreach($data as $scrip => $value){
    if(isBullCross($value)){
      array_push($result["B"], array("name" => $scrip, "c" => $value["c"], "d" => $value["d"]));
    }else if(isBearCross($value)){
      array_push($result["S"], array("name" => $scrip, "c" => $value["c"], "d" => $value["d"]));
    }
  }
  return $result;
}

function fetchDBData($dataSize = 160){
  /* This function fetches data from database
     Curently it makes two queries. one query is just to get the apropriate date in past.
     if possible manage with only one query. 
  */
  /* We need to get the date exactly $dataSize days in past. Thus this query */ 
  $nifty_query = "select niftylatest.timestamp as latest from (SELECT close, timestamp from cmbhav where symbol = 'NIFTY' order by timestamp desc limit " . $dataSize . ") as niftylatest order by niftylatest.timestamp asc limit 1";
  $result = mysql_query($nifty_query);
  if (!$result) {
    echo 'Could not run query: ' . mysql_error();
    exit;
  }

  $row = mysql_fetch_row($result);
  $first_date = $row[0];
  // Free the result
  mysql_free_result($result);

  $fetch_query = "SELECT niftylist.symbol as symbol, close, TOTTRDQTY as volume, timestamp FROM niftylist,cmbhav WHERE niftylist.symbol = cmbhav.symbol and timestamp > '" . $first_date . "' and cmbhav.series = 'EQ' order by timestamp desc";

  $all_result = mysql_query($fetch_query);
  if (!$all_result) {
    echo 'Could not run query: ' . mysql_error();
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

function addResultToDB($result){
  /* This function adds identified crossovers to macrossover table.
  */
  /* First check whether we have any results to add */
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
  $insert_query = "INSERT INTO macrossover (symbol, type, close, date) VALUES " . substr($query_values, 1);
  /* Insert the data in macrossover table */
  $insert_result = mysql_query($insert_query);
  if (!$insert_result) {
    echo 'Could not run query: ' . mysql_error();
    exit;
  }
  return true;
}

function getStatus(){
  /* This function checks whether we need to calculate crossovers for today
    If we have already calculated crossovers then exit.
  */
  $status_query = "SELECT id from updatestatus WHERE tablename = 'macrossover' AND timestamp = CURDATE()";
  $status = mysql_query($status_query);
  if (!$status) {
    echo 'Could not run query: ' . mysql_error();
    return false;
  }
  if(mysql_num_rows($status)){
    /* The script has run. no need to run it again */
    return true;
  }
  return false;
}

function updateStatus(){
  /* Once MA calculations are done, update the status in updatestatus table
  */
  $update_query = "UPDATE updatestatus SET timestamp = CURDATE() WHERE tablename='macrossover'";
  $update_status = mysql_query($update_query);
  if (!$update_status) {
    echo 'Could not run query: ' . mysql_error();
    return false;
  }
  if(mysql_affected_rows()){
    /* Successfully updated status */
    return true;
  }
  return false;
}

function aajCrossOver(){
  /* Calculate today's possible crossover.
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
    /* The script has run. no need to run it again */
    return true;
  }
  
  $dataStore = fetchDBData();
  foreach($dataStore as $scrip => $value){
    $processed = processMaData($value, $scrip);
    if($processed){
      /* processMaData returns zero if its unable to calculate */
      $data[$scrip] = $processed;
    }
  }

  $result = processMaCrossOver($data);
  /* This script does not return anything. 
    It adds the crossover results to macrossover table.
  */
  addResultToDB($result);
  return true;
}

/* All required functions are defined so get to business */
aajCrossOver();
updateStatus();
mysql_close($con);
exit;
?>