<?php
require_once('dba.php');
if($_REQUEST['filedate']){
$filedate = $_REQUEST['filedate'];
$csvdate = date("d-m-Y", strtotime($filedate));
$sqldate = date("Y-m-d",strtotime($filedate));
}else{
$filedate = date("dMY");
$csvdate = date("d-m-Y");
$sqldate = date("Y-m-d");
}

$baseurl = 'http://www.nseindia.com/content/indices/histdata/';
$filename = "S&P%20CNX%20NIFTY".$csvdate."-".$csvdate.".csv";

$url = $baseurl.$filename;
echo $url;
if(url_exists($url)){
$file = fopen($url,"r");

$stocks = fgetcsv($file);
while($stocks = fgetcsv($file)) {
   $insertrecord = "Insert Into cmbhav values ('NIFTY', NULL,".$stocks[1].",".$stocks[2].",".$stocks[3].",".$stocks[4].", NULL, NULL, NULL, NULL,'" .$sqldate."')";
   mysql_query($insertrecord);
}

$filename = "CNX%20NIFTY%20JUNIOR".$csvdate."-".$csvdate.".csv";
$url = $baseurl.$filename;
$file = fopen($url,"r");
$stocks = fgetcsv($file);
while($stocks = fgetcsv($file)) {
   $insertrecord = "Insert Into cmbhav values ('MINIFTY', NULL,".$stocks[1].",".$stocks[2].",".$stocks[3].",".$stocks[4].", NULL, NULL, NULL, NULL,'" .$sqldate."')";
   mysql_query($insertrecord);
}

$filename = "BANK%20NIFTY".$csvdate."-".$csvdate.".csv";
$url = $baseurl.$filename;
$file = fopen($url,"r");

$stocks = fgetcsv($file);
while($stocks = fgetcsv($file)) {
   $insertrecord = "Insert Into cmbhav values ('BANKNIFTY', NULL,".$stocks[1].",".$stocks[2].",".$stocks[3].",".$stocks[4].", NULL, NULL, NULL, NULL,'" .$sqldate."')";
   mysql_query($insertrecord);
}
echo "INDEX update successful.\n";
}else{
echo "INDEX data is up to date.\n";
}

$baseurl = 'http://www.nseindia.com/content/historical/EQUITIES/';
$filename = 'cm'.strtoupper($filedate).'bhav.csv';

$url = $baseurl.date("Y",strtotime($filedate)).'/'.strtoupper(date("M",strtotime($filedate))).'/'.$filename.'.zip';
if(url_exists($url)){
$data = file_get_contents($url,FILE_BINARY);
$fp = fopen('test.zip', 'w');
fwrite($fp, $data);
fclose($fp);
$zip = new ZipArchive;
if ($zip->open('test.zip') === TRUE) {
    $zip->extractTo('.');
    $zip->close();
    unlink('test.zip');
    rename($filename, 'cmbhav.csv');
    $filecontents = file("cmbhav.csv");
    $fail = 0;
    $pass = 0;
    
    for($i=1; $i<sizeof($filecontents); $i++) {
        $data = substr($filecontents[$i], 0, -2);
        $dataarray = explode(',',$data);
        if(strcasecmp(trim($dataarray[1]), "EQ") == 0){
          $insertrecord = "Insert Into cmbhav values ('".$dataarray[0]."','".$dataarray[1]."',".$dataarray[2].",".$dataarray[3].",".$dataarray[4].",".$dataarray[5].",".$dataarray[6].",".$dataarray[7].",".$dataarray[8].",".$dataarray[9].",'".$sqldate."')";
          mysql_query($insertrecord);
          if(mysql_error()) {
            $fail += 1; # increments if there was an error importing the record
          }
           else
          {
            $pass += 1; # increments if the record was successfully imported
          }
        }
    }
    
    if($fail == 0){
       unlink('cmbhav.csv');
    }

    echo "EQ update successful.\n";
}else{
    echo "EQ update failed.\n";
}
}else{
    echo "EQ data is up to date.\n";
}

mysql_close($con);

function url_exists($url) {
    $hdrs = @get_headers($url);
    return is_array($hdrs) ? preg_match('/^HTTP\\/\\d+\\.\\d+\\s+2\\d\\d\\s+.*$/',$hdrs[0]) : false;
};

?>