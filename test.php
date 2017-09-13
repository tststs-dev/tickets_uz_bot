<?php

include('class.parser-uz.php');
$start = microtime(true);

$first_id = Parser_UZ::get_station_id("харьков");
$second_id = Parser_UZ::get_station_id("киев");




$search_req = new Request("http://booking.uz.gov.ua/ru/purchase/search/",
                     "station_id_from=" . $first_id . "&station_id_till=" . $second_id .
                              "&date_dep=30.09.2017",
                    true );

$search_res = $search_req->get_response_object();

$train_data = Parser_UZ::get_trains($search_res, $search_req, $first_id, $second_id);
$time = microtime(true) - $start;
print_r($train_data);
print_r("Main: " . $time);






