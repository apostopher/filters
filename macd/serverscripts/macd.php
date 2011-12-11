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

// Load filter functions.
require_once ('../../common/filter_functions.php');

function processMacdData($dataStore, $scrip, $sm = 12, $mid = 26, $sig = 9){
  /*Process MA data as follows
    1. Loop only once to calculate everything
    2. get 26 day EMA for today and yesterday
    3. get 12 day EMA for today and yesterday
    4. get 9 day EMA for today and yesterday
    5. get today's Volume
    6. get average of 5 day volume
  */

  /* Check whether we have enough data to calculate MA crossover */
  $datacount = count($dataStore);
  $required = $mid * 2;
  if($datacount < $required){
    /* Unable to proceed return zero */
    error_log("Not enough data(count = " . $datacount . ", required = " . $required . "): ". $scrip);
    return 0;
  }

  /* Initialize vars */

  $yEmaSm = 0;
  $yEmaMid = 0;
  $yEmaSig = 0;

  $emaSm = 0;
  $emaMid = 0;
  $emaSig = 0;
  
  /* Calculate EMA multipliers */
  $multSm = 2 / ($sm + 1);
  $multMid = 2 / ($mid + 1);
  $multSig = 2 / ($sig + 1);
  
  $totalSm = 0;
  $totalMid = 0;
  $totalSig = 0;
  $totalV = 0;
  
  $keySm = "e".$sm;
  $keyMid = "e".$sm;
  $keySig = "e".$sm;

  $results = array();
  $results["macd"] = array();
  $results["signal"] = array();

  /* MACD calculation must be done with single pass. Iterate once and calculate
     everything.
  */
  for($i = 0; $i < $datacount; $i++){

    $curr_data = $dataStore[$i];
    $curr_c = $curr_data["c"];
    $curr_v = $curr_data["v"];
    /**************************************************************************/
    if($i < $sm){
      $totalSm += $curr_c;
    }else if($i == $sm - 1){
      $emaSm = $totalSm / $sm;
    }else {
      $emaSm = (($curr_c - $emaSm) * $multSm) + $emaSm;
    }
    /**************************************************************************/
    if($i < $mid){
      $totalMid += $curr_c;
    }else if($i == $mid - 1){
      $emaMid = $totalMid / $mid;
    }else {
      $emaMid = (($curr_c - $emaMid) * $multMid) + $emaMid;
    }
    /**************************************************************************/
    if($emaSm && $emaMid){
      array_push($results["macd"], round($emaSm - $emaMid, 2));
    }
    /**************************************************************************/
    if($i == $datacount - 1){
      $today = $curr_data["d"];
      $close = &$curr_c;
      $volume = &$curr_v;
    }
    /**************************************************************************/
  }

  /* Now calculate signal line */
  $macdCount = count($results["macd"]);

  for($i = 0; $i < $macdCount; $i++){
    $curr_macd = $results["macd"][$i];
    if($i < $sig){
      $totalSig += $curr_macd;
    }else if($i == $sig - 1){
      $emaSig = $totalSig / $sig;
      array_push($results["signal"], $emaSig);
    }else {
      $emaSig = (($curr_macd - $emaSig) * $multSig) + $emaSig;
      array_push($results["signal"], round($emaSig, 2));
    }
  }
  
  return array("results" => $results, "v" => $volume, "c" => $close, "d" => $today);
}

function isCross($value){
  /* Check for MACD signal line crossover
  */
  $result = $value["results"];
  $macd = $result["macd"];
  $countMacd = count($macd);
  $signal = $result["signal"];
  $countSignal = count($signal);
  $todayMacd = $macd[$countMacd - 1];
  $yMacd = $macd[$countMacd - 2];
  $todaySignal = $signal[$countSignal - 1];
  $ySignal = $signal[$countSignal - 2];
  
  if($yMacd <= $ySignal && $todayMacd > $todaySignal && $todayMacd <= 0){
    /* Got a bullish crossover!
       Bear cross is MACD crosses Signal and goes down
       today's volume is more that average of last week's volume
    */
    return 1;
  }

  if($yMacd >= $ySignal && $todayMacd < $todaySignal && $todayMacd >= 0){
    /* Got a bearish crossover!
       Bear cross is MACD crosses Signal and goes down
       today's volume is more that average of last week's volume
    */
    return -1;
  }

  return 0;
}

function processMacdCrossOver($data){
  /*
    Get whether there are any bullish or bearish crossovers.
  */
  $result["B"] = array();
  $result["S"] = array();
  
  foreach($data as $scrip => $value){
    $gotCross = isCross($value);
    if($gotCross == 1){
      array_push($result["B"], array("name" => $scrip, "c" => $value["c"], "d" => $value["d"]));
    }else if($gotCross == -1){
      array_push($result["S"], array("name" => $scrip, "c" => $value["c"], "d" => $value["d"]));
    }
  }
  return $result;
}

function fetchDBData($dataSize = 200){
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

  $fetch_query = "SELECT niftylist.symbol as symbol, close, TOTTRDQTY as volume, timestamp FROM niftylist,cmbhav WHERE niftylist.symbol = cmbhav.symbol and timestamp > '" . $first_date . "' and cmbhav.series = 'EQ' order by timestamp asc";
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

function getStatus(){
  /* This function checks whether we need to calculate macd crossovers for today
    If we have already calculated crossovers then exit.
  */
  $status_query = "SELECT id from updatestatus WHERE tablename = 'macdcrossover' AND timestamp = CURDATE()";
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
  $update_query = "UPDATE updatestatus SET timestamp = CURDATE() WHERE tablename='macdcrossover'";
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

function addResultToDB($result){
  /* This function adds identified crossovers to macrossover table.
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
  
  $insert_query = "INSERT INTO macdcrossover (symbol, type, close, date) VALUES " . substr($query_values, 1);
  /* Insert the data in macrossover table */
  $insert_result = mysql_query($insert_query);
  if (!$insert_result) {
    echo 'Could not run query: ' . $insert_query;
    exit;
  }
  return true;
}

function todayMacdCrossOver(){
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
    $processed = processMacdData($value, $scrip);
    if($processed){
      /* processMaData returns zero if its unable to calculate */
      $data[$scrip] = $processed;
    }
  }

  $result = processMacdCrossOver($data);
  /* This script does not return anything. 
    It adds the crossover results to macrossover table.
  */
  addResultToDB($result);
  return true;
}

/* All required functions are defined so get to business */
todayMacdCrossOver();
/* Update status */
updateStatus();
mysql_close($con);
exit;
?>