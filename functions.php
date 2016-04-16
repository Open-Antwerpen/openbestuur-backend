<?php

function writeLog($data) {
    $msg = date("d-m-Y H:i:s") . ": " . $data;
    $save_path = 'log.txt';
    if ($fp = @fopen($save_path, 'a')) {
        // open or create the file for writing and append info
        fputs($fp, "$msg\n"); // write the data in the opened file
        fclose($fp); // close the file
    }
}
?>