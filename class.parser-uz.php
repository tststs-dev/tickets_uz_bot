<?php
include "class.request.php";


//Нужно получать id станции до поиска (из-за Харьков != Харьков-пасс и т.д)
class Parser_UZ {
    //const URL_UZ = "http://booking.uz.gov.ua/ru/";


    public static function get_stations ($response_json_data) {
        $decoded_data = json_decode($response_json_data["response_object"], true);
        $stations = array();

        foreach ($decoded_data as $value) {
            $stations[$value['title']] = $value['value']; // station id => station name
        }

        return $stations;
    }

    public static function get_trains($response_obj, &$request_obj, $first_city_id, $second_city_id) {

        if($response_obj['value'] == "По заданному Вами направлению мест нет") {
            return "По заданному Вами направлению мест нет";
        }
        elseif ($response_obj['value'] == "Введена неверная дата отправления") {
            return "Введена неверная дата отправления";
        }
        elseif ($response_obj['value'] == "5") {
            echo "\nPIDORASI SUKA BLEAT\n";
            $response_data = $request_obj->get_response_object();
            //$data = self::get_data($url,$post_data,true);
            self::get_trains($response_data,$request_obj, $first_city_id, $second_city_id);
        }
        else return self::train_handler($response_obj, $first_city_id, $second_city_id);

    }

    public static function get_lower_places ($trains_array) {

        /*
        foreach ($trains_array as $key => $value) {
            $post_data = "station_id_from=" . $value['station_from'] . "&"
            $test =  self::get_data("http://booking.uz.gov.ua/ru/purchase/coaches/",);
        }
      print_r($test);
        */
    }

    private static function train_handler ($response_data, $first_city_id, $second_city_id) {
        $trains = array();
        echo "=============================================================\n";
        foreach ($response_data['value'] as $value) {
            $coach_class = array();
            foreach ($value['types'] as $class_type) {
                $coach_class[$class_type['id']] = $class_type['places'];
            }

            $trains[] = array(
                "station_from" => $value['from']['station'],
                "station_till" => $value['till']['station'],
                "date_from"    => $value['from']['src_date'],
                "date_till"    => $value['till']['src_date'],
                "place_class"  => $coach_class,
                "train_number" => $value['num']
            );
        }
        $trains['station_from_id'] = $first_city_id;
        $trains['station_till_id'] = $second_city_id;
        // get_lower_places($trains);
        return $trains;
    }

    public static function get_station_id ($station_name) {
        //echo "123";
        $station_req = new Request("http://booking.uz.gov.ua/ru/purchase/station/", "?term=" . $station_name);
        $station_res = $station_req->get_response_object();
        // print_r($station_res);

        while ($station_req->get_response_status_code() == 400) {
           // echo "\nEBAT CHETIRESTA SUKA\n";
            $station_res = $station_req->get_response_object();
        }
        print_r($station_res);
        foreach ($station_res as $value) {
            //print_r(strtolower($value['title']));
            if(mb_strtolower($value['title'], 'UTF-8') == mb_strtolower($station_name,'UTF-8' )) {
                return $value['value'];
            }
            else return 0;
        }
    }


    //РАЗБИТЬ НА ФУНКЦИИ
    public static function set_station_id(&$trains_array) {
        $param = "?term=" . $trains_array[0]['station_from'];
        $station_id_req = self::get_data("http://booking.uz.gov.ua/ru/purchase/station/",$param);
        while ($station_id_req["response_code"] == 400) {
            echo "\n1345678\n";
            $station_id_req = self::get_data("http://booking.uz.gov.ua/ru/purchase/station/",$param);
        }
        $station_id_req = json_decode($station_id_req["response_object"],true);
        $station_id_req = $station_id_req[0]['value'];
        $trains_array['station_from_id'] = $station_id_req;
        $param = "?term=" . $trains_array[0]['station_till'];
        $station_id_req = self::get_data("http://booking.uz.gov.ua/ru/purchase/station/", $param);
        while ($station_id_req["response_code"] == 400) {
            echo "\nHUI PIZDA\n";
            $station_id_req = self::get_data("http://booking.uz.gov.ua/ru/purchase/station/",$param);
        }
        $station_id_req = json_decode($station_id_req["response_object"],true);
        $station_id_req = $station_id_req[0]['value'];
        $trains_array['station_id_till'] = $station_id_req;
        print_r($trains_array);
    }


}

