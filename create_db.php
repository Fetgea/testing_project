<?php


require ("./functions.php");
$result = populateDatabase();
if(!isset($result["error"])) {
    echo "Success!!";
} else {
    print_r($result);
}