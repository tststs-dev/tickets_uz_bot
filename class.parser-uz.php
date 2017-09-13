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
        $coaches_multi_req = new Multi_Request($coaches_array);  //мульти-запрос для получения списка доступных вагонов каждого поезда
        $coaches_multi_res = $coaches_multi_req->get_response_objects(); //получаем ответ

        foreach ($coaches_multi_res as $i => &$res) {
            //к каждому ответу допишем номер поезда и дату для отправки следующего запроса на /coach
            $res['train_number'] = $train_information[$coach_type]['train_numbers'][$i];
            $res['date_dep'] = $train_information[$coach_type]['date_dep'][$i];

            $coach_req = array(); //массив доступных вагонов определенного поезда
            foreach ($res['coaches'] as $j => $coach) {
                //формируем запросы для дальнейшей передачи в Multi_Request
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

            $coach_multi_req = new Multi_Request($coach_req);
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
        //формируем запросы для дальнейшей передачи в Multi_Request
        $coaches_req = new Request("http://booking.uz.gov.ua/ru/purchase/coaches/",
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

