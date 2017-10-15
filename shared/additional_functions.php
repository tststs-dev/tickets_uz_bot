<?php

function get_stations_array(TelegramBot $bot, $update) {
    $stations_list = ParserUZ::get_stations_list($bot->get_message($update));
    if (count($stations_list) == 1) {
        return ParserUZ::get_station_id($stations_list[0]);
    }
    else if($stations_list == NULL) {
        return NULL;
    }
    elseif(count($stations_list) > 1) {
        $stations_button = array();
        foreach ($stations_list as $station) {
            $stations_button[] = array("text" => $station);
        }
        $stations_keyboard = new ReplyKeyboardMarkup($stations_button, true, true);
        $bot->send_message($bot->get_chat_id($update),
            RIGHT_ARROW_SMILE . " Выбери станцию из списка.\n\nЕсли станции в списке нет - повтори поиск  /search",
            $stations_keyboard);
        return "list";
    }
}

function get_full_date(TelegramBot $bot, $update) {
    $current_year = date("Y");
    $current_month = date("m");

    $time = strtotime($bot->get_message($update) . "." . $current_year);
    $month = date("m", $time);

    if((int)$current_month > (int)$month) {
        return $bot->get_message($update) . "." . (int)($current_year + 1);
    }
    else return $bot->get_message($update) . "." . $current_year;
}

function create_button($text, $calback_data = null) {
    if($calback_data) return array("text" => $text, "callback_data" => $calback_data);
    else return array("text" => $text);
}


function create_new_id($connect, $chat_id) {
    $id = rand(10000000,99999999);
    mysqli_query($connect, "INSERT INTO user_data (id, chat_id) VALUES ($id, $chat_id)");
}

function get_monitoring_list($connect, $chat_id) {
    return mysqli_query($connect, "SELECT id, station_from, station_till, date_dep,  	mon_train_time, mon_place_count, mon_coach_type 
                                          FROM  user_data
                                          WHERE state LIKE 15 AND chat_id=$chat_id")->fetch_all(MYSQLI_ASSOC);
}


function set_coach_class(array $coach_classes, TelegramBot $bot, $update, $db_monitoring, $connect)
{
    $coach_class_activate = $bot->get_command_param($update);
    $buttons = array();
    $seat_classes = array();
    $id = $db_monitoring['id'];
    $coach_types = array();

    //если в бд уже было что-то записано, создаем массив со значениями классов
    if ($db_monitoring['mon_coach_type']) {
        if (mb_strlen($db_monitoring['mon_coach_type']) == 1) {
            $coach_types[0] = $db_monitoring['mon_coach_type'];
        } else {
            $coach_types = explode(",", $db_monitoring['mon_coach_type']);
        }
    }

    foreach ($coach_classes as $coach_key => $coach_class) {
        if ($coach_class == $coach_class_activate) {
            //если кнопка еще не была нажата, ставим галочку
            if (!in_array($coach_key, $coach_types)) {

                $coach_types[] = $coach_key;
                if (count($coach_types) > 1) {
                    $coach_types_string = "'" . implode(",", $coach_types) . "'";
                } else $coach_types_string = "'" . $coach_key . "'";

                mysqli_query($connect, "UPDATE user_data SET mon_coach_type=$coach_types_string  WHERE id=$id");
                $button = create_button(CHECK_MARK_SMILE . $coach_class, "/monitoring_step1 " . $coach_class);
                $buttons[] = $button;
                //если кнопка уже была нажата, убираем галочку
            } else {
                unset($coach_types[array_search($coach_key, $coach_types)]);
                if (count($coach_types) > 1) {
                    $coach_types_string = "'" . implode(",", $coach_types) . "'";
                } else $coach_types_string = "'" . "" . "'";


                mysqli_query($connect, "UPDATE user_data SET mon_coach_type=$coach_types_string  WHERE id=$id");
                $button = create_button($coach_class, "/monitoring_step1 " . $coach_class);
                $buttons[] = $button;
            }

        } else {
            //проверяем предыдущие нажатия, ставим галочки
            if (in_array($coach_key, $coach_types)) {
                $button = create_button(CHECK_MARK_SMILE . $coach_class, "/monitoring_step1 " . $coach_class);
                $buttons[] = $button;
                //или создаем чистую кнопку
            } else {
                $button = create_button($coach_class, "/monitoring_step1 " . $coach_class);
                $buttons[] = $button;
            }
        }
    }

    foreach ($buttons as $key => $button) {
        if (substr($button['callback_data'], 18, 2) == "С") {
            $seat_classes[] = $button;
            unset($buttons[$key]);
        }
    }
    $buttons[] = $seat_classes;
    $buttons[] = create_button("Далее " . RIGHT_ARROW_SMILE, "/to_state10");
    $inline_keyboard = new InlineKeyboardMarkup($buttons);
    $bot->edit_keyboard_message($bot->get_chat_id($update), $bot->get_message_id($update), $inline_keyboard);
}

function get_final_message ($trains_info, $date) {
    if(array_key_exists("station_from_id", $trains_info)) {
        $count = count($trains_info) - 2;
    }
    else {
        $count = count($trains_info);
    }

    if ($trains_info  == "invalid date") {
        return "invalid date";
    }
    elseif($trains_info == "no places") {
        return NEGATIVE_GREEN_CROSS_MARK_SMILE . " На " . $date . " поездов не найдено!\nВозможно, все билеты раскуплены или еще не появились";
    }

    $final_message = CHECK_MARK_SMILE . " Найдено " . $count . " поездов на " . $date . "\n\n";

    foreach ($trains_info as $key => $train) {
        if ($key === "station_from_id" || $key === "station_till_id") continue;
        else {
            $final_message .= TRAIN_SMILE . " " . $train['train_number'] . " " . $train['station_from'] . " - " . $train['station_till'] . "\n";
            $final_message .= CLOCK_SMILE . " " . "Отправление в " . date("H:i d.m.Y", strtotime($train['date_from']))  . "\n";
            $final_message .= CLOCK_SMILE . " " ."Прибытие в " . date("H:i d.m.Y", strtotime($train['date_till'])) . "\n";
            $final_message .= ALARM_CLOCK_SMILE . " " ."Время в пути " . $train['travel_time'] . "\n\n";
            $final_message .= SQUARED_FREE_SMILE . " " ."Свободные места:\n";
            foreach ($train['place_class'] as $class) {
                $final_message .= SMALL_BLUE_DIAMOND_SMILE . "Тип: " . $class['type'] . " " . $class['places'] . " мест\n";
                if ($class['type'] == "К") {
                    $final_message .= "- " . (int)($class['places'] - $train['lower_places_coupe']) . " верхних\n";
                    $final_message .= "- " . $train['lower_places_coupe'] . " нижних\n";
                }
                if ($class['type'] == "П") {
                    $final_message .= "- " . (int)($class['places'] - $train['lower_places_reserved_seat']) . " верхних\n";
                    $final_message .= "- " . $train['lower_places_reserved_seat'] . " нижних\n";
                }

            }
            $final_message .= "--------------------------\n";
        }
    }
    return $final_message;
}

function split_str($str, $needle) :array {
    $result_array = array();
    if(strpos($str, $needle)){
        $result_array =  explode($needle, $str);
        return $result_array;
    }
    else {

        $result_array[] = $str;
        return $result_array;
    }
}


function filter_train_num(array $train_info, $needle_nums) {
    $needle_trains = array_filter($train_info, function ($train) use ($needle_nums) {
        $filtered = false;
        foreach ($needle_nums as $number_key => $value) {
            if ((int)$train['train_number'] == (int)$needle_nums[$number_key]) {
                $filtered = true;
                break;
            }
        }
        return $filtered;
    });
    return $needle_trains;
}

function filter_coach_type (array $train_info, $needle_type, $db_data) {
    $needle_trains = array_filter($train_info, function ($train) use ($needle_type, $db_data){
        $filtered = false;
        print_r($needle_type);
        foreach ($needle_type as $type) {
            foreach ($train['place_class'] as $place_class) {
                if($place_class['type'] == $type && $place_class['places'] >= $db_data['mon_place_count']) {
                    $filtered = true;
                    break;
                }
            }
        }
        return $filtered;
    });
    return $needle_trains;
}

function filter_lower_place(array $train_info, $is_lower_place, $coach_types)
{
    if ($is_lower_place) {
        $train_info = array_filter($train_info, function ($train) use ($coach_types) {
            $filtered = false;
            if (in_array("К", $coach_types)) {
                $filtered = ($train['lower_places_coupe'] > 0);
            }
            if (in_array("П", $coach_types)) {
                $filtered = ($train['lower_places_reserved_seat'] > 0);
            }
            if ($train['lower_places_reserved_seat'] == -1 && $train['lower_places_coupe'] == -1) {
                $filtered = true;
            }

            return $filtered;
        });
    }
    return $train_info;
}

function filter_train_time($train_info, $time_range) {

    if($time_range == "0-12") {
        $train_info = array_filter($train_info, function ($train) {
            //  var_dump((int)date("H", strtotime($train['date_from']) >= 0) && (int)date("H", strtotime($train['date_from'])) <=12);
            $filtered = ((int)date("H", strtotime($train['date_from']) >= 0) &&
                (int)date("H", strtotime($train['date_from'])) <=12);
            return $filtered;
        });
    }
    elseif ($time_range == "12-24") {
        $train_info = array_filter($train_info, function ($train) {
            $filtered = ((int)date("H", strtotime($train['date_from']) >= 12) &&
                (int)date("H", strtotime($train['date_from'])) <24);
            return $filtered;
        });
    }
    return $train_info;
}