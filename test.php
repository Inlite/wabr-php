<?php
//  To support UTF-8 this file should be saved as UTF8 with BOM + Unix(LF)

include "wabr.php";
include "sample_code.php";

 
// Configure server
$authorization = "";
$serverUrl = "";

$reader = new WABarcodeReader($serverUrl, $authorization);
$reader->showDiag = true;
if ($reader->showDiag){
    $version = explode('.', PHP_VERSION);
    echo WAUtils::eol() . "SERVER: " .  $reader->_serverUrl  .  "   PHP VERSION: " . $version[0] . "." . $version[1] . "." . $version[2] . WAUtils::eol();
    }


// Configure Test
$test = new Test();

$test->Run($reader);
if (defined("STDIN"))
    {echo "Hit ENTER -> "; fgetc(STDIN);}

?>