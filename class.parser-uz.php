<?php
include "class.request.php";
include "class.multi_request.php";

class Parser_UZ {

    //Получаем ID станции
    public static function get_station_id ($station_name) {
        //Составляем запрос
        //В поле "term" передаем точное название станции
        $station_req = new Request("http://booking.uz.gov.ua/ru/purchase/station/", "?term=" .  urlencode($station_name));
        $station_res = $station_req->get_response_object(); //получаем ответ

        //при ошибке повторяем запрос
        while ($station_req->get_response_status_code() == 400) {
            $station_res = $station_req->get_response_object();
        }


        foreach ($station_res as $station) {
            if(mb_strtolower($station['title'], 'UTF-8') == mb_strtolower($station_name,'UTF-8' )) {
                return $station['value']; //возвращаем ID
            }
            else return 0; //если станция не найдена, возвращаем 0
        }
    }

    public static function get_trains($response_obj, Request &$request_obj, $station_from_id, $station_till_id) {
        //обрабатываем ошибки
        if($response_obj['value'] == "По заданному Вами направлению мест нет") {
            return "По заданному Вами направлению мест нет";
        }
        elseif ($response_obj['value'] == "Введена неверная дата отправления") {
            return "Введена неверная дата отправления";
        }
        elseif ($response_obj['value'] == "5") {
            echo "\n5555\n";
            $response_data = $request_obj->get_response_object();
            self::get_trains($response_data,$request_obj, $station_from_id, $station_till_id);
        }
        else return self::train_handler($response_obj, $station_from_id, $station_till_id); //получаем массив поездов по заданному направлению

    }
    /*
        //Считаем количество нижних мест в вагонах типа Плацкарт и Купе
        private static function set_lower_places(&$trains_array) {
            foreach ($trains_array as &$train) {
                foreach ((array)$train['place_class'] as $coach) {
                    //если тип вагонов Купе
                    if ($coach['type'] == 'К') {
                        self::set_lower_places_by_type($coach['type'], $trains_array, $train);
                    }
                    //если тип вагонов Плацкарт
                    elseif ($coach['type'] == 'П') {
                        self::set_lower_places_by_type($coach['type'], $trains_array, $train);
                    }
                    else continue;
                }
            }
        }*/
    private static function set_lower_places(&$trains_array) {
        $coaches_coupe = array();
        $coaches_reserved_seat = array();
        $train_info[] = array(
            "К" => array(
                "train_numbers" => array(),
                "date_dep" => array()
            ),
            "П" => array(
                "train_numbers" => array(),
                "date_dep" => array()
            )
        );
        foreach ($trains_array as &$train) {
            foreach ((array)$train['place_class'] as $coach) {
                //если тип вагонов Купе
                if ($coach['type'] == 'К') {
                    $coaches_coupe[] = self::get_coaches($coach['type'], $trains_array, $train);
                    $train_info['К']['train_numbers'][] = $train['train_number'];
                    $train_info['К']['date_dep'][] = $train['date_dep'];
                }
                //если тип вагонов Плацкарт
                elseif ($coach['type'] == 'П') {
                    $coaches_reserved_seat[] = self::get_coaches($coach['type'], $trains_array, $train);
                    $train_info['П']['train_numbers'][] = $train['train_number'];
                    $train_info['П']['date_dep'][] = $train['date_dep'];
                }
                else continue;
            }

        }

        $coaches_multi_req = new Multi_Request($coaches_coupe);
        $coaches_multi_res = $coaches_multi_req->get_response_objects();

        foreach ($coaches_multi_res as $i => &$res) {
            $res['train_number'] = $train_info['К']['train_numbers'][$i];
            $res['date_dep'] = $train_info['К']['date_dep'][$i];

            $coach_req = array();
            foreach ($res['coaches'] as $j => $coach){

                $coach_req[$j] = new Request('http://booking.uz.gov.ua/ru/purchase/coach/',
                    "station_id_from=" . $trains_array['station_from_id'] .
                    "&station_id_till=" . $trains_array['station_till_id'] .
                    "&train=" . $res['train_number'] .
                    "&coach_num=" . $coach['num'] .
                    "&coach_type=" . $coach['type'] .
                    "&date_dep=" . $res['date_dep'],
                    true
                );

            }
            //print_r($coaches_res);
            $coach_multi_req = new Multi_Request($coach_req);
            $coach_multi_res = $coach_multi_req->get_response_objects();
            $coach_multi_res['train_number'] = $res['train_number'];
            //print_r($coach_multi_res);
            $lower_places = 0;
            foreach ($coach_multi_res as $coach) {
                foreach ($coach['value']['places'] as $place) {
                    foreach ($place as $value) {
                        if ($value % 2 == 1) $lower_places++;
                    }
                }
            }

            foreach ($trains_array as &$tr) {
                if($tr['train_number'] == $coach_multi_res['train_number']) {
                    $tr['lower_places_coupe'] = $lower_places;
                    break;
                }
            }

        }

        //print_r($coaches_multi_res);





        /* if($coach_type == "К") {
             $train['lower_places_coupe'] = $lower_places;
         }
         if($coach_type == "П") {
             $train['lower_places_reserved_seat'] = $lower_places;
         }*/




        // print_r($coaches_multi_res);
    }

    private static function get_coaches ($coach_type, &$trains_array, &$train) {
        $coaches_req = new Request("http://booking.uz.gov.ua/ru/purchase/coaches/",
            "station_id_from=" . $trains_array['station_from_id'] .
            "&station_id_till=" . $trains_array['station_till_id'] .
            "&train=" . $train['train_number'] .
            "&coach_type=" . $coach_type .
            "&date_dep=" . $train['date_dep'],
            true );
        return $coaches_req;
    }

    private static function set_lower_places_by_type($coach_type, &$trains_array, &$train) {
        $lower_places = 0; //количество нижних мест
        global $coach_req;


        //формируем запрос на получение доступных вагонов определенного типа в определенном поезде
        $coaches_req = new Request("http://booking.uz.gov.ua/ru/purchase/coaches/",
            "station_id_from=" . $trains_array['station_from_id'] .
            "&station_id_till=" . $trains_array['station_till_id'] .
            "&train=" . $train['train_number'] .
            "&coach_type=" . $coach_type .
            "&date_dep=" . $train['date_dep'],
            true );

        $coaches_res = $coaches_req->get_response_object(); //получаем в ответ массив доступных вагонов
        //если ошибка - повторяем запрос
        while (isset($coaches_res['error'])) {
            echo "coaches 555\n";
            $coaches_res = $coaches_req->get_response_object();
        }

        foreach ((array)$coaches_res['coaches'] as $i => $coach){
            //print_r($coaches_res);
            $coach_req[$i] = new Request('http://booking.uz.gov.ua/ru/purchase/coach/',
                "station_id_from=" . $trains_array['station_from_id'] .
                "&station_id_till=" . $trains_array['station_till_id'] .
                "&train=" . $train['train_number'] .
                "&coach_num=" . $coach['num'] .
                "&coach_type=" . $coach['type'] .
                "&date_dep=" . $train['date_dep'],
                true
            );
        }
        $coach_multi_req = new Multi_Request($coach_req);
        $coach_multi_res = $coach_multi_req->get_response_objects();
        //print_r($coach_multi_res);

        foreach ($coach_multi_res as $coach) {
            foreach ($coach['value']['places'] as $place) {
                foreach ($place as $value) {
                    if ($value % 2 == 1) $lower_places++;
                }
            }
        }

        if($coach_type == "К") {
            $train['lower_places_coupe'] = $lower_places;
        }
        if($coach_type == "П") {
            $train['lower_places_reserved_seat'] = $lower_places;
        }

        /*$curl[$i] = curl_init();
        curl_setopt($curl[$i],CURLOPT_URL, 'http://booking.uz.gov.ua/ru/purchase/coach/');
        curl_setopt($curl[$i], CURLOPT_POST, true);
        curl_setopt($curl[$i], CURLOPT_POSTFIELDS, "station_id_from=" . $trains_array['station_from_id'] .
            "&station_id_till=" . $trains_array['station_till_id'] .
            "&train=" . $train['train_number'] .
            "&coach_num=" . $coach['num'] .
            "&coach_type=" . $coach['type'] .
            "&date_dep=" . $train['date_dep']);
        curl_setopt($curl[$i], CURLOPT_RETURNTRANSFER, true);

        $multi_curl = curl_multi_init();
        curl_multi_add_handle($multi_curl, $curl[$i]);

    }
    $active = null;

    do {
        $mrc = curl_multi_exec( $multi_curl, $active);
        print_r("MRC1 ". $mrc . "\n");
       // print_r(CURLM_CALL_MULTI_PERFORM);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {

        if (curl_multi_select($multi_curl) == -1) usleep(100);
        do { $mrc = curl_multi_exec($multi_curl, $active);
            }
        while ($mrc == CURLM_CALL_MULTI_PERFORM);
    }
    $res = curl_multi_getcontent($curl[0]);
    $res = json_decode($res, true);
    print_r( $res );*/

        ////////////////////////////////////////////////////////////////////////////////////////////
        /*foreach((array)$coaches_res['coaches'] as $i => $coach)
        {
            // Check for errors
            $curlError = curl_error($curl[$i]);
            if($curlError == "") {
                $res[$i] = curl_multi_getcontent($curl[$i]);
                print_r(curl_multi_getcontent($curl[$i]));
            } else {
                print "Curl error on handle $i: $curlError\n";
            }
            // Remove and close the handle
            curl_multi_remove_handle($multi_curl, $curl[$i]);
            curl_close($curl[$i]);
        }*/
        //print_r($res);

        // print_r(json_decode($mrc), true);
        // curl_multi_close($multi_curl);
        //перебираем в цикле все вагоны
        /*foreach ((array)$coaches_res['coaches'] as $coach) {
            //для каждого вагона - новый запрос
            $coach_req = new Request(http://booking.uz.gov.ua/ru/purchase/coach/,
                "station_id_from=" . $trains_array['station_from_id'] .
                "&station_id_till=" . $trains_array['station_till_id'] .
                "&train=" . $train['train_number'] .
                "&coach_num=" . $coach['num'] .
                "&coach_type=" . $coach['type'] .
                "&date_dep=" . $train['date_dep'],
                true
            );
            $coach_res = $coach_req->get_response_object(); //получаем в ответ вагон
            //если ошибка - повторяем запрос
            while (isset($coach_res['error'])) {
                echo "coaches 555\n";
                $coach_res = $coach_req->get_response_object();
            }

            //считаем количество нижних мест
            foreach ($coach_res['value']['places'] as $place) {
                foreach ($place as $value) {
                    if ($value % 2 == 1) $lower_places++;
                }
            }
        }*/
        //записываем количество нижних мест для каждого поезда
        /* if($coach_type == "К") {
             $train['lower_places_coupe'] = $lower_places;
         }
         if($coach_type == "П") {
             $train['lower_places_reserved_seat'] = $lower_places;
         }*/
    }


    private static function train_handler ($response_data, $station_from_id, $station_till_id) {
        $trains = array();
        echo "=============================================================\n";
        foreach ($response_data['value'] as $value) {
            $coach_class = array();
            foreach ($value['types'] as $class_type) {
                $coach_class[] = array(
                    "type" => $class_type['id'],
                    "places" => $class_type['places']
                );
            }
            $trains[] = array(
                "station_from"  => $value['from']['station'],
                "station_till"  => $value['till']['station'],
                "date_from"     => $value['from']['src_date'],
                "date_till"     => $value['till']['src_date'],
                "place_class"   => $coach_class,
                "train_number"  => $value['num'],
                "date_dep"      => $value['from']['date'],
                "lower_places_coupe" => -1,
                "lower_places_reserved_seat" => -1
            );
        }
        $trains['station_from_id'] = $station_from_id;
        $trains['station_till_id'] = $station_till_id;
        // $start = microtime(true);
        self::set_lower_places($trains);
        //  $time = microtime(true) - $start;
        //print_r("Lower: " . $time);
        return $trains; //возвращаем массив поездов
    }


}

