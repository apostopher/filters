<?php
/*	These are the common functions used by all the filters.
	
	Author : Rahul Devaskar
	THIS SCRIPT IS A PROPERTY OF BLACKBULL INVESTMENT COMPANY. COPYING, EDMTING
	OR DELETING THIS SCRIPT IS PROHIBITED WITHOUT THE PERMISSION FROM THE AUTHOR.	
*/
function sum($arr, $key = "c", $period = 154)
{
  $sum = 0;
  for($i = 0; $i < $period; $i++){
    $sum = $sum + $arr[$i][$key];
  }
  return $sum;
}

function avg($arr, $key = "c", $period = 154)
{
  $result = sum($arr, $key, $period) / $period;
  return $result;
}

?>