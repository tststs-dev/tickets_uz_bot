<?php
include "class.request.php";

class Parser_UZ {

    //Получаем ID станции
    public static function get_station_id ($station_name) {
        //Составляем запрос
        //В поле "term" передаем точное название станции
        $station_req = new Request("http://booking.uz.gov.ua/ru/purchase/station/", "?term=" . $station_name);
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
    }

    private static function set_lower_places_by_type($coach_type, &$trains_array, &$train) {
        $lower_places = 0; //количество нижних мест

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

        //перебираем в цикле все вагоны
        foreach ((array)$coaches_res['coaches'] as $coach) {
            //для каждого вагона - новый запрос
            $coach_req = new Request("http://booking.uz.gov.ua/ru/purchase/coach/",
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
        }
        //записываем количество нижних мест для каждого поезда
        if($coach_type == "К") {
            $train['lower_places_coupe'] = $lower_places;
        }
        if($coach_type == "П") {
            $train['lower_places_reserved_seat'] = $lower_places;
        }
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

        self::set_lower_places($trains);
        return $trains; //возвращаем массив поездов
    }


}

