<?php

require_once dirname(__DIR__) . '/request/MultiRequest.php';
require_once dirname(__DIR__) . '/request/Request.php';

class ParserUZ {

    public static function get_stations_list($city_name) {
        $stations_req = new Request("https://booking.uz.gov.ua/ru/train_search/station/", "?term=" .  urlencode($city_name));
        $stations_res = $stations_req->get_response_object();
        while ($stations_req->get_response_status_code() == 400) {
            $station_res = $stations_req->get_response_object();
        }
        $stations_array = array();

        foreach ($stations_res as $station) {
            $stations_array[] = $station['title'];
        }
        return $stations_array;
    }

    //Получаем ID станции
    public static function get_station_id ($station_name) {
        //Составляем запрос
        //В поле "term" передаем точное название станции
        $station_req = new Request("https://booking.uz.gov.ua/ru/train_search/station/", "?term=" .  urlencode($station_name));
        $station_res = $station_req->get_response_object(); //получаем ответ
        print_r($station_req);
        //при ошибке повторяем запрос
        while ($station_req->get_response_status_code() == 400) {
            $station_res = $station_req->get_response_object();
        }


        foreach ($station_res as $station) {
            if(mb_strtolower($station['title'], 'UTF-8') == mb_strtolower($station_name,'UTF-8' )) {
                return $station['value']; //возвращаем ID
            }
            else return NULL; //если станция не найдена, возвращаем NULL
        }
    }

    private static function get_trains($response_obj, Request &$request_obj, $station_from_id, $station_till_id) {
        //обрабатываем ошибки
        if($response_obj['value'] == "По заданному Вами направлению поездов нет" ||
           $response_obj['value'] == "По заданному Вами направлению мест нет")
        {
            return "no places";
        }
        elseif ($response_obj['value'] == "Введена неверная дата отправления") {
            return "invalid date";
        }
        elseif ($response_obj['value'] == "5") {
            echo "\n5555\n";
            $response_data = $request_obj->get_response_object();
            self::get_trains($response_data,$request_obj, $station_from_id, $station_till_id);
        }
        else return self::train_handler($response_obj, $station_from_id, $station_till_id); //получаем массив поездов по заданному направлению

    }

    //посчитаем количество нижних мест
    private static function set_lower_places(&$trains_array) {
        $coaches_coupe = array(); //массив запросов на купе
        $coaches_reserved_seat = array(); //массив запросов на плацкарт
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
                    $coaches_coupe[] = self::get_coaches($coach['type'], $trains_array, $train); //добавляем каждый запрос в массив
                    $train_info['К']['train_numbers'][] = $train['train_number']; //сохраняем номер поезда и дату
                    $train_info['К']['date_dep'][] = $train['date_dep'];          //для запроса на /coach
                } //если тип вагонов Плацкарт
                elseif ($coach['type'] == 'П') {
                    $coaches_reserved_seat[] = self::get_coaches($coach['type'], $trains_array, $train);
                    $train_info['П']['train_numbers'][] = $train['train_number'];
                    $train_info['П']['date_dep'][] = $train['date_dep'];
                } else continue;
            }

        }
        //считаем нижние места отдельно для купе и плацкарта
        self::set_lower_places_by_type("К", $trains_array, $coaches_coupe, $train_info);
        self::set_lower_places_by_type("П", $trains_array, $coaches_reserved_seat, $train_info);
    }

    private static function set_lower_places_by_type($coach_type, &$trains_array, &$coaches_array, $train_information) {
        $coaches_multi_req = new MultiRequest($coaches_array);  //мульти-запрос для получения списка доступных вагонов каждого поезда
        $coaches_multi_res = $coaches_multi_req->get_response_objects(); //получаем ответ

        foreach ($coaches_multi_res as $i => &$res) {
            //к каждому ответу допишем номер поезда и дату для отправки следующего запроса на /coach
            $res['train_number'] = $train_information[$coach_type]['train_numbers'][$i];
            $res['date_dep'] = $train_information[$coach_type]['date_dep'][$i];

            $coach_req = array(); //массив доступных вагонов определенного поезда
            foreach ($res['coaches'] as $j => $coach) {
                //формируем запросы для дальнейшей передачи в MultiRequest
                $coach_req[$j] = new Request('https://booking.uz.gov.ua/ru/purchase/coach/',
                    "station_id_from=" . $trains_array['station_from_id'] .
                    "&station_id_till=" . $trains_array['station_till_id'] .
                    "&train=" . $res['train_number'] .
                    "&coach_num=" . $coach['num'] .
                    "&coach_type=" . $coach['type'] .
                    "&date_dep=" . $res['date_dep'],
                    true
                );

            }

            $coach_multi_req = new MultiRequest($coach_req);
            $coach_multi_res = $coach_multi_req->get_response_objects(); //получаем ответ
            $coach_multi_res['train_number'] = $res['train_number']; //дописываем номер поезда для ассоциации начальным  массивом поездов
            $lower_places = 0;

            //считаем количество нижних мест
            foreach ($coach_multi_res as $coach) {
                foreach ($coach['value']['places'] as $place) {
                    foreach ($place as $value) {
                        if ($value % 2 == 1) $lower_places++;
                    }
                }
            }

            //записываем количество нижних мест
            foreach ($trains_array as &$train) {
                if ($train['train_number'] == $coach_multi_res['train_number']) {
                    if ($coach_type == "К") {
                        $train['lower_places_coupe'] = $lower_places;
                    }
                    if ($coach_type == "П") {
                        $train['lower_places_reserved_seat'] = $lower_places;
                    }
                    break;
                }
            }
        }
    }

    private static function get_coaches ($coach_type, &$trains_array, &$train) {
        //формируем запросы для дальнейшей передачи в MultiRequest
        $coaches_req = new Request("https://booking.uz.gov.ua/ru/purchase/coaches/",
            "station_id_from=" . $trains_array['station_from_id'] .
            "&station_id_till=" . $trains_array['station_till_id'] .
            "&train=" . $train['train_number'] .
            "&coach_type=" . $coach_type .
            "&date_dep=" . $train['date_dep'],
            true );
        return $coaches_req;
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
                "travel_time"   => $value['travel_time'],
                "place_class"   => $coach_class,
                "train_number"  => $value['num'],
                "date_dep"      => $value['from']['date'],
                "lower_places_coupe" => -1,
                "lower_places_reserved_seat" => -1
            );
        }
        $trains['station_from_id'] = $station_from_id;
        $trains['station_till_id'] = $station_till_id;
        self::set_lower_places($trains);
        return $trains; //возвращаем массив поездов
    }

    public static function get_trains_info($station_from,$station_till,$date) {
        $search_req = new Request("https://booking.uz.gov.ua/ru/purchase/search/",
            "station_id_from=" . $station_from .
            "&station_id_till=" . $station_till .
            "&date_dep=" . $date,
            true);
        $search_res = $search_req->get_response_object();
        $trains_info = ParserUZ::get_trains($search_res, $search_req, $station_from, $station_till);

        return $trains_info;
    }

    public static function get_trains_info_by_multi_curl(array $requests) {
        $search_requests = array();
        $trains_info = array();
        foreach ($requests as $key_req => $req) {
            $search_requests[$key_req] = new Request("https://booking.uz.gov.ua/ru/purchase/search/",
                "station_id_from=" . $req['station_from_id'] .
                "&station_id_till=" . $req['station_till_id'] .
                "&date_dep=" . $req['date_dep'],
                true);
        }
        $search_multi_req = new MultiRequest($search_requests);
        $search_multi_res = $search_multi_req->get_response_objects();
        foreach ($search_multi_res as $key_res => $res) {
            $trains_info[] = ParserUZ::get_trains($res, $search_requests[$key_res], $requests[$key_res]['station_from_id'], $requests[$key_res]['station_till_id']);
        }
        return $trains_info;

    }
}

