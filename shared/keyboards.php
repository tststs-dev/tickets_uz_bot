<?php
require_once dirname(__FILE__) . "/smiles.php";
require_once dirname(__DIR__) . "/keyboard/InlineKeyboardMarkup.php";

$simple_search_keyboard = new InlineKeyboardMarkup(
    [
        ["text" => LEFT_MAGNIFYING_GLASS_SMILE . "Искать билеты", "callback_data" => "/search"],
    ]
);

$search_mon_keyboard = new InlineKeyboardMarkup(
    [
        ["text" => LEFT_MAGNIFYING_GLASS_SMILE . "Искать билеты", "callback_data" => "/search"],
        ["text" => EYES_SMILE . "Список мониторингов", "callback_data" => "/monitoring_list"]
    ]
);

$coach_type_keyboard = new InlineKeyboardMarkup(
    [
        ["text" => "Купе", "callback_data" => "/monitoring_step1 Купе"],
        ["text" => "Плацкарт", "callback_data" => "/monitoring_step1 Плацкарт"],
        ["text" => "Люкс", "callback_data" => "/monitoring_step1 Люкс"],
        [
            ["text" => "С1", "callback_data" => "/monitoring_step1 С1"],
            ["text" => "С2", "callback_data" => "/monitoring_step1 С2"],
            ["text" => "С3", "callback_data" => "/monitoring_step1 С3"],
        ],
        ["text" => "Далее " . RIGHT_ARROW_SMILE, "callback_data" => "/to_state10"]
    ]
);

$after_search_keyboard = new InlineKeyboardMarkup(
    [

        ["text" => LEFT_MAGNIFYING_GLASS_SMILE . "Искать билеты", "callback_data" => "/search"],
        [
            ["text" => OPEN_CIRCLE_ARROW_SMILE . "Повторить поиск", "callback_data" => "/repeat"],
            ["text" => HOOK_ARROW_SMILE . "Обратный билет", "callback_data" => "/return_ticket"]
        ],
        [
            ["text" => EYES_SMILE . "Мониторинг", "callback_data" => "/monitoring"],
            ["text" => CALENDAR_SMILE ."Другой день", "callback_data" => "/change_day"]
        ]
    ]
);