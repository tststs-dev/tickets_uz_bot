<?php

include('class.parser-uz.php');

/*const URL_UZ = "http://booking.uz.gov.ua/ru/purchase/search/";
$post_data = "station_id_from=2204001&station_id_till=2200001&date_dep=14.09.2017&time_dep=00%3A00&time_dep_till=&another_ec=0&search=";
$data = Parser_UZ::get_data(URL_UZ, $post_data, true);

$trains_data = Parser_UZ::get_trains($data,URL_UZ,$post_data);*/
//Parser_uz::get_lower_places();
//print_r($trains_data);

$first_id = Parser_UZ::get_station_id("харьков");
$second_id = Parser_UZ::get_station_id("киев");

$search_req = new Request("http://booking.uz.gov.ua/ru/purchase/search/",
                     "station_id_from=" . $first_id . "&station_id_till=" . $second_id .
                              "&date_dep=14.09.2017",
                    true );

$search_res = $search_req->get_response_object();

$train_data = Parser_UZ::get_trains($search_res, $search_req, $first_id, $second_id);
print_r($train_data);



