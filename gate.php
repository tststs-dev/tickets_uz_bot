<?php
require_once 'telegram_bot/TelegramBot.php' ;
require_once 'database/MonitoringDatabase.php';
require_once "keyboard/InlineKeyboardMarkup.php";
require_once "keyboard/ReplyKeyboardMarkup.php";
require_once "shared/keyboards.php";
require_once "shared/assoc_arrays.php";
require_once "shared/smiles.php";
require_once "configs/db_config.php";
require_once "configs/bot_config.php";
require_once "shared/additional_functions.php";


$db = new MonitoringDatabase(DB_LOGIN, DB_PASSWORD, DB_NAME, DB_SERVER);
$connect = $db->connect();


$bot = new TelegramBot(BOT_TOKEN);

$update = $bot->get_webhook_updates();
$chat_id = $bot->get_chat_id($update);

$db_response = mysqli_query($connect,"SELECT *
                                             FROM user_data
                                             WHERE chat_id LIKE $chat_id 
                                                   AND state !='15'")->fetch_array(MYSQLI_ASSOC);
$id = $db_response['id'];

if($db_response['chat_id'] == $chat_id) {
    $state = $db_response['state'];
}
else {
    create_new_id($connect, $chat_id);
}

/*************************************************************************/
/*
 ************************* COMMANDS ***************************************
*/
/*************************************************************************/
$bot->command("/start", $update, function () use (&$state){
    $state = 1;
});

$bot->command("/search", $update, function () use (&$state) {
    $state = 2;
});

$bot->command("/monitoring", $update, function() use ($bot, $update, &$state, $connect, $id) {
    if($state < 7 && $state != -1) {
        $bot->send_message($bot->get_chat_id($update), "Сначала нужно совершить поиск /search!");
        $state = -1;
    }
    elseif ($state !=7) {
        mysqli_query($connect,"UPDATE user_data SET mon_coach_type='' WHERE id=$id");
        $state = 8;
        $bot->state_update($state, $connect, $id);
    }
    else {
        $state = 8;
        $bot->state_update($state, $connect, $id);
    }
});

$bot->command("/change_day", $update, function () use ($bot, $update, &$state, $connect, $id) {
    if($state <7 && $state != -1) {
        $bot->send_message($bot->get_chat_id($update), "Сначала нужно совершить поиск /search!");
        $state = -1;
    }
    else {
        $bot->send_message($bot->get_chat_id($update), RIGHT_ARROW_SMILE . " Укажи дату отправления в формате: день.месяц.\n\nНапример: 01.09");
        $state = -1;
        $bot->state_update("7", $connect, $id);
    }
});

$bot->command("/repeat", $update, function () use (&$state, $db_response, $bot, $update, $after_search_keyboard, $connect){
    $bot->send_message($bot->get_chat_id($update), "Идет поиск" . SANDGLASS_SMILE . "\nПодожди, пожалуйста, несколько секунд");
    $trains_info = ParserUZ::get_trains_info($db_response['station_from_id'],$db_response['station_till_id'], $db_response['date_dep']);
    $final_message = get_final_message($trains_info, $db_response['date_dep']);
    $bot->send_message($bot->get_chat_id($update), $final_message, $after_search_keyboard);
    $state = -1;
    $bot->state_update("7", $connect, $db_response['id']);
});

$bot->command("/return_ticket", $update, function () use ($bot, &$state, $update, $connect, $db_response) {
    $id = $db_response["id"];
    $station_from = "'" . $db_response['station_from'] . "'";
    $station_till = "'" . $db_response['station_till'] . "'";
    $station_from_id = $db_response['station_from_id'];
    $station_till_id = $db_response['station_till_id'];
    $bot->send_message($bot->get_chat_id($update), "Поиск по направлению: " . $db_response['station_till'] . " - " . $db_response['station_from']);
    $bot->send_message($bot->get_chat_id($update), RIGHT_ARROW_SMILE . " Укажи дату отправления в формате: день.месяц.\n\nНапример: 01.09");
    $bot->state_update("7", $connect, $db_response["id"]);

    mysqli_query($connect, "UPDATE user_data 
                                   SET station_from_id=$station_till_id, station_till_id=$station_from_id, station_from=$station_till, station_till=$station_from
                                   WHERE id=$id");
    $state =-1;
});

$bot->command("/monitoring_list", $update, function () use ($connect, $bot, $update, $time_range, &$state, $simple_search_keyboard) {
    $monitoring_list = get_monitoring_list($connect, $bot->get_chat_id($update));


    if(!$monitoring_list) {
        $bot->send_message($bot->get_chat_id($update),EXCLAMATION_MARK_SMILE . "Вы пока не установили ни одного мониторинга", $simple_search_keyboard);
        return;
    }

    $message = EYES_SMILE . " Список активных мониторингов: \n\n";
    foreach ($monitoring_list as $monitoring) {
        if(!$monitoring['mon_train_time'])  $time = "0-24";
        else $time = $monitoring['mon_train_time'];

        $message.= RIGHT_ARROW_SMILE . " " . $monitoring['station_from'] . " - " . $monitoring['station_till'] . "\n";
        $message.= CALENDAR_SMILE . " " . $monitoring['date_dep'] . "\n";
        $message.= CLOCK_SMILE ." " . $time_range[$time] . "\n";
        $message.= TICKET_SMILE . " Билетов: " . $monitoring['mon_place_count'] . " | " . $monitoring['mon_coach_type'] . "\n";
        $message.= NEGATIVE_GREEN_CROSS_MARK_SMILE . " Отключить /m_del_" . $monitoring['id'] . "\n";
        $message.= "-----------------------\n";
    }
    $bot->send_message($bot->get_chat_id($update), $message, $simple_search_keyboard);
    $state = -1;
});

if(strstr($bot->get_message($update), "m_del_")) {
    $monitoring_list = get_monitoring_list($connect, $bot->get_chat_id($update));
    $monitoring_id = substr($bot->get_message($update), strpos($bot->get_message($update), "l_")+ 2);
}

$bot->command("/m_del_" . $monitoring_id, $update, function () use (&$state, $connect, $monitoring_id, $bot, $update){
    mysqli_query($connect, "DELETE FROM user_data WHERE id=$monitoring_id");

    $inline_keyboard = new InlineKeyboardMarkup([
        ["text" => LEFT_MAGNIFYING_GLASS_SMILE . "Искать билеты", "callback_data" => "/search"],
        ["text" => EYES_SMILE . "Список мониторингов", "callback_data" => "/monitoring_list"]
    ]);
    $bot->send_message($bot->get_chat_id($update), CHECK_MARK_SMILE . "Мониторинг удален!", $inline_keyboard);
    $state = -1;

});

$bot->command("/monitoring_step1", $update, function () use ($bot, $update, $connect, $coach_classes, $db_response){
    set_coach_class($coach_classes,  $bot, $update, $db_response, $connect);
    //$bot->send_message($bot->get_chat_id($update), "/monitoring_step1_" . mb_strtolower($coach_class_activate, "UTF-8") ."_activate");
});

$bot->command("/to_state10", $update, function () use ($bot, $update, $connect, $db_response, &$state, $coach_type_keyboard){

    if(!$db_response['mon_coach_type']) {
        $bot->edit_text_message($bot->get_chat_id($update),  $bot->get_message_id($update), EXCLAMATION_MARK_SMILE. " Сначала неохбодимо выбрать хотя бы один тип вагона", $coach_type_keyboard);
    }
    else  {
        $state = 10;
        $bot->state_update("10", $connect, $db_response['id']);
    }

});

$bot->command("/monitoring_step3", $update, function () use ($bot, $update, $connect, $id, &$state) {
    $is_lower_place = $bot->get_command_param($update);
    mysqli_query($connect,"UPDATE user_data SET mon_lower_place=$is_lower_place WHERE id=$id");
    $bot->state_update("12", $connect, $id);
    $state = 12;
});


$bot->command("/monitoring_step4", $update, function () use ($bot, $update, $connect,  $db_response, $time_range, &$state, $search_mon_keyboard) {
    $range = $bot->get_command_param($update);
    if($range != "train_num") {
        $train_time = "'" . $range . "'";
        $id = $db_response['id'];
        mysqli_query($connect,"UPDATE user_data SET mon_train_time=$train_time WHERE id=$id");
        $bot->edit_text_message($bot->get_chat_id($update), $bot->get_message_id($update),
            CHECK_MARK_SMILE . "Мониторинг успешно создан!\n\n" .
            RIGHT_ARROW_SMILE. " " . $db_response['station_from'] . " - " . $db_response['station_till'] . "\n" .
            CALENDAR_SMILE . " " . $db_response['date_dep'] . "\n" . CLOCK_SMILE . " " . $time_range[$range] . "\n" .
            TICKET_SMILE . " Билетов: " . $db_response['mon_place_count'] . "| " . $db_response['mon_coach_type']
        );
        $bot->edit_keyboard_message($bot->get_chat_id($update), $bot->get_message_id($update), "");

        $bot->send_message($bot->get_chat_id($update),"----------------------------- ", $search_mon_keyboard);
        $bot->state_update("15", $connect, $id);
        create_new_id($connect, $db_response['chat_id']);
    }
    else $state = 13;
});

/*************************************************************************/
/*
 ************************* STATES ***************************************
*/
/*************************************************************************/

$bot->state_handler("1", $state, function () use ($bot, $update, $connect, $id, $simple_search_keyboard){
    $bot->send_message($bot->get_chat_id($update), "Привет! Я найду для тебя билеты на любой украинский поезд" . WINKING_FACE_SMILE . "\nДля начала работы нажми кнопку \"Искать билеты\"", $simple_search_keyboard);
    $bot->state_update('2', $connect, $id);
});


$bot->state_handler("2", $state, function () use ($bot, $update, $connect, $id){
    $bot->send_message($bot->get_chat_id($update), RIGHT_ARROW_SMILE. " Введи станцию или город отправления.\n\nНапример: Харьков");
    $bot->state_update('3', $connect, $id);
});


$bot->state_handler("3", $state, function () use ($bot, $update, $connect, $id) {
    $station = get_stations_array($bot, $update);

    if($station === NULL) {
        $bot->send_message($bot->get_chat_id($update), NEGATIVE_RED_CROSS_MARK_SMILE. " Станция не найдена!\n\nВведи другое название или повтори поиск /search");
    }
    elseif($station == "list") {
        $bot->state_update('4', $connect, $id);
    }
    else {
        $station_name = "'" . $bot->get_message($update) . "'";
        mysqli_query($connect,"UPDATE user_data SET station_from=$station_name,station_from_id=$station  WHERE id=$id");
        $bot->state_update('5', $connect, $id);
        $bot->send_message($bot->get_chat_id($update), RIGHT_ARROW_SMILE. " Теперь введи станцию или город прибытия.\n\nНапример: Киев");
    }

});

$bot->state_handler("4", $state, function () use ($bot, $update, $connect, $id) {
    $station = ParserUZ::get_station_id($bot->get_message($update));
    if($station == NULL) {
        $bot->send_message($bot->get_chat_id($update),NEGATIVE_RED_CROSS_MARK_SMILE. " Станция не найдена!\n\nВведи другое название или повтори поиск /search" );
        $bot->state_update('3', $connect, $id);
    }
    else {
        $station_name = "'" . $bot->get_message($update) . "'";
        mysqli_query($connect,"UPDATE user_data SET station_from=$station_name,station_from_id=$station  WHERE id=$id");
        $bot->send_message($bot->get_chat_id($update), RIGHT_ARROW_SMILE. " Теперь введи станцию или город прибытия.\n\nНапример: Киев");
        $bot->state_update('5', $connect, $id);
    }
});



$bot->state_handler("5", $state, function () use ($bot, $update, $connect, $id) {
    $station = get_stations_array($bot, $update);

    if($station === NULL) {
        $bot->send_message($bot->get_chat_id($update), NEGATIVE_RED_CROSS_MARK_SMILE. " Станция не найдена!\n\nВведи другое название или повтори поиск /search");
    }
    elseif($station == "list") {
        $bot->state_update('6', $connect, $id);
    }
    else {
        $station_name = "'" . $bot->get_message($update) . "'";
        mysqli_query($connect,"UPDATE user_data SET station_till=$station_name,station_till_id=$station WHERE id=$id");
        $bot->state_update('7', $connect, $id);
        $bot->send_message($bot->get_chat_id($update), RIGHT_ARROW_SMILE . " Укажи дату отправления в формате: день.месяц.\n\nНапример: 01.09");
    }

});

$bot->state_handler("6", $state, function () use ($bot, $update, $connect, $id){
    $station = ParserUZ::get_station_id($bot->get_message($update));
    if($station == NULL) {
        $bot->send_message($bot->get_chat_id($update),NEGATIVE_RED_CROSS_MARK_SMILE. " Станция не найдена!\n\nВведи другое название или повтори поиск /search" );
        $bot->state_update('5', $connect, $id);
    }
    else {
        $station_name = "'" . $bot->get_message($update) . "'";
        mysqli_query($connect,"UPDATE user_data SET station_till=$station_name,station_till_id=$station  WHERE id=$id");
        $bot->send_message($bot->get_chat_id($update), RIGHT_ARROW_SMILE . " Укажи дату отправления в формате: день.месяц.\n\nНапример: 01.09");
        $bot->state_update('7', $connect, $id);
    }
});





$bot->state_handler("7", $state, function () use ($bot, $update, $connect, $db_response, $after_search_keyboard) {
    $bot->send_message($bot->get_chat_id($update), "Идет поиск" . SANDGLASS_SMILE . "\nПодожди, пожалуйста, несколько секунд");

    $date = get_full_date($bot, $update);

    $trains_info = ParserUZ::get_trains_info($db_response['station_from_id'],$db_response['station_till_id'], $date);
    $final_message = get_final_message($trains_info, $date);
    $date_dep = "'" . $date . "'";
    $id = $db_response['id'];
    mysqli_query($connect,"UPDATE user_data SET date_dep=$date_dep  WHERE id=$id");

    $bot->send_message($bot->get_chat_id($update), $final_message, $after_search_keyboard);

});


$bot->state_handler("8", $state, function () use ($bot, $update, $connect, $db_response, $coach_type_keyboard) {

    $bot->send_message($bot->get_chat_id($update), EYES_SMILE . "Создание мониторинга по направлению:\n" .
        $db_response['station_from'] . " - " . $db_response['station_till'] .
        " на " . $db_response['date_dep']);

    $bot->send_message($bot->get_chat_id($update), RIGHT_ARROW_SMILE . " Шаг №1:" .
        " выбери тип вагона (можно несколько)",
        $coach_type_keyboard);

    $bot->state_update("-1", $connect, $db_response['id']);
});


$bot->state_handler("10", $state, function () use ($bot, $update, $connect, $id) {
    $buttons = array();
    for($i = 1; $i < 11; $i++){
        $buttons[] = create_button((string)$i);
    }

    $reply_keyboard = new ReplyKeyboardMarkup($buttons,true, true);
    $bot->edit_text_message($bot->get_chat_id($update), $bot->get_message_id($update),  RIGHT_ARROW_SMILE . " Шаг №2: введи количество билетов от 1 до 10");

    $bot->edit_keyboard_message($bot->get_chat_id($update), $bot->get_message_id($update),
        "");
    $bot->send_message($bot->get_chat_id($update),"Например: 2", $reply_keyboard);
    $bot->state_update("11", $connect, $id);
});

$bot->state_handler("11", $state, function () use ($bot, $update, $connect, $id) {
    if(is_numeric($bot->get_message($update)) && $bot->get_message($update) >= 1 && $bot->get_message($update) <=10) {
        $place_count = $bot->get_message($update);
        mysqli_query($connect,"UPDATE user_data SET mon_place_count=$place_count  WHERE id=$id");

        $inline_keyboard = new InlineKeyboardMarkup(
            [
                ["text" => "Да", "callback_data" => "/monitoring_step3 1"],
                ["text" => "Все равно", "callback_data" => "/monitoring_step3 0"]
            ]
        );
        $bot->send_message($bot->get_chat_id($update), RIGHT_ARROW_SMILE . " Шаг №3: хочешь, чтоб я искал только нижние места?", $inline_keyboard);
        $bot->state_update("12", $connect, $id);
    }
    else $bot->send_message($bot->get_chat_id($update), EXCLAMATION_MARK_SMILE . " Введи количество билетов от 1 до 10\n\nНапример: 2");

});

$bot->state_handler("12", $state, function () use($bot, $update, $connect, $id) {
    $inline_keyboard = new InlineKeyboardMarkup(
        [
            [
                ["text" => "00:00 - 12:00", "callback_data" => "/monitoring_step4 0-12"],
                ["text" => "12:00 - 23:59", "callback_data" => "/monitoring_step4 12-24"]
            ],
            ["text" => "00:00 - 23:59", "callback_data" => "/monitoring_step4 0-24"],
            ["text" => "Искать по номеру поезда", "callback_data" => "/monitoring_step4 train_num"]

        ]
    );

    $bot->edit_text_message($bot->get_chat_id($update), $bot->get_message_id($update), RIGHT_ARROW_SMILE . " Шаг №4: укажи промежуток времени отправления поезда или выбери поиск по номеру поезда");
    $bot->edit_keyboard_message($bot->get_chat_id($update), $bot->get_message_id($update), $inline_keyboard);

    $bot->state_update("-1", $connect, $id);
});


$bot->state_handler("13", $state, function () use($bot, $update, $connect, $id, $state) {
    $bot->send_message($bot->get_chat_id($update), RIGHT_ARROW_SMILE . " Напиши номер поезда, в котором хочешь искать билеты (один или несколько через запятую).\n\n" .
        "Например: 159");
    $bot->state_update("14", $connect, $id);
});

$bot->state_handler("14", $state, function () use ($bot, $update, $connect, $db_response, $state, $search_mon_keyboard) {
    $id = $db_response['id'];
    $train_nums = "'" . $bot->get_message($update) . "'";
    $bot->send_message($bot->get_chat_id($update), CHECK_MARK_SMILE . "Мониторинг успешно создан!\n\n" .
        RIGHT_ARROW_SMILE . " " . $db_response['station_from'] . " - " . $db_response['station_till'] . "\n" .
        CALENDAR_SMILE . " " . $db_response['date_dep'] . "\n" . CLOCK_SMILE . " " . "00:00 - 23:59\n" .
        TICKET_SMILE . " Билетов: " . $db_response['mon_place_count'] . " | " . $db_response['mon_coach_type'] . "\n".
        TRAIN_SMILE . " Поезда: " . $bot->get_message($update));
    mysqli_query($connect,"UPDATE user_data SET mon_train_num=$train_nums  WHERE id=$id");

    $bot->send_message($bot->get_chat_id($update),"----------------------------- ", $search_mon_keyboard);
    $bot->state_update("15", $connect, $id);
    create_new_id($connect, $db_response['chat_id']);
});


/****************************************************************************/



