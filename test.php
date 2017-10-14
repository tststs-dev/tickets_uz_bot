<?php

//include('ParserUZ.php');
require_once 'parser/ParserUZ.php';
$start = microtime(true);


$first_id = ParserUZ::get_station_id("Киев");
$second_id = ParserUZ::get_station_id("Харьков");

var_dump($first_id);


$search_req = new Request("http://booking.uz.gov.ua/ru/purchase/search/",
                     "station_id_from=" . $first_id . "&station_id_till=" . $second_id .
                              "&date_dep=16.10.2017",
                    true );

$search_res = $search_req->get_response_object();

$train_data = ParserUZ::get_trains_info($first_id, $second_id, "16.10.2017");
//$time = microtime(true) - $start;
print_r($train_data);

$date = date("H:i ", strtotime("00:22 10.20.2017"));
$date1 = date("H:i d.m.y", strtotime("2017-10-16 09:38:00"));
$date4 = date("H:i:s", (int)strtotime("2017-10-17 01:00:00") - (int)strtotime("2017-10-16 23:00:00") - 3600);
//$date3 = date("H", $t);
print_r($date1);
print_r("Main: " . $time);






