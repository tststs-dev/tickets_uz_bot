<?php
require_once "database/MonitoringDatabase.php";
require_once "configs/bot_config.php";
require_once "configs/db_config.php";
require_once "parser/ParserUZ.php";
require_once "telegram_bot/TelegramBot.php";
require_once "shared/smiles.php";
require_once "shared/keyboards.php";
require_once "shared/additional_functions.php";

ignore_user_abort(true);

$db = new MonitoringDatabase(DB_LOGIN, DB_PASSWORD, DB_NAME, DB_SERVER);
$connect = $db->connect();

$bot = new TelegramBot(BOT_TOKEN);
$start = microtime(true);
$monitoring_list = mysqli_query($connect, "SELECT * FROM user_data WHERE state LIKE 15")->fetch_all(MYSQLI_ASSOC);

$trains_info = ParserUZ::get_trains_info_by_multi_curl($monitoring_list);


foreach ($monitoring_list as $key => $monitoring) {
    $needle_trains = array();
    if(!is_array($trains_info[$key])) continue;
    print_r($monitoring['chat_id'] . "\n");
    $coach_types = split_str($monitoring['mon_coach_type'], ",");

    //фильтация по номерам поездов
    if($monitoring['mon_train_num']) {
        $train_nums = split_str($monitoring['mon_train_num'], ",");

        $needle_trains = filter_train_num($trains_info[$key], $train_nums);

        $needle_trains = filter_coach_type($needle_trains, $coach_types, $monitoring);
        $needle_trains = filter_lower_place($needle_trains, $monitoring['mon_lower_place'], $coach_types);

    }
    //фильтрация по времени
    elseif($monitoring['mon_train_time']) {
        $needle_trains = filter_train_time($trains_info[$key], $monitoring['mon_train_time']);
        //print_r($needle_trains);
        $needle_trains = filter_coach_type($needle_trains, $coach_types, $monitoring);

        $needle_trains = filter_lower_place($needle_trains, $monitoring['mon_lower_place'], $coach_types);


    }
    if(!empty($needle_trains)) {
        print_r("SUCC " . $monitoring['chat_id'] . "\n");
        $final_message = get_final_message($needle_trains, $monitoring['date_dep']);
        $bot->send_message($monitoring['chat_id'], $final_message, $search_mon_keyboard);
    }


}

$time = microtime(true) - $start;
print_r("TIME : " . $time);
