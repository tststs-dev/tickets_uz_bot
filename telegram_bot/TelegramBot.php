<?php

require_once dirname(__DIR__) . "/parser/ParserUZ.php";

class TelegramBot {
    private $token;
    private $base_url;

    public function __construct(string $token) {
        $this->token = $token;
        $this->base_url = 'https://api.telegram.org/bot' . $this->token . "/";
    }

    public function get_webhook_updates()  {
        $body = json_decode(file_get_contents('php://input'), true);
        return $body;
    }

    public function edit_keyboard_message($chat_id, $message_id, $keyboard) {
        $params = http_build_query(compact('chat_id', 'message_id'));
        if($keyboard) {
            $keyboard = json_encode($keyboard->get_keyboard(), true);
        }
        print_r($this->base_url . "editMessageReplyMarkup". "?" . $params ."&reply_markup=" . $keyboard);
        $message_req = new Request($this->base_url . "editMessageReplyMarkup", "?" . $params ."&reply_markup=" . $keyboard);
        $message_req->execute_request();
    }

    public function edit_text_message($chat_id, $message_id, $text, $keyboard = null) {
        $params = http_build_query(compact('chat_id', 'message_id', 'text'));
        if($keyboard) {
            $keyboard = json_encode($keyboard->get_keyboard(), true);
        }
        print_r($this->base_url . "editMessageText". "?" . $params ."&reply_markup=" . $keyboard);
        $message_req = new Request($this->base_url . "editMessageText", "?" . $params ."&reply_markup=" . $keyboard);
        $message_req->execute_request();
    }

    public function send_message($chat_id, $text,  $keyboard = null) {
        $params = http_build_query(compact('chat_id', 'text'));
        if($keyboard) {
            $keyboard = json_encode($keyboard->get_keyboard(), true);
        }
        print_r($this->base_url . "sendmessage". "?" . $params ."&reply_markup=" . $keyboard);
        $message_req = new Request($this->base_url . "sendmessage", "?" . $params ."&reply_markup=" . $keyboard);
        $message_req->execute_request();
    }

    public function get_message($update){
        if (isset($update["callback_query"])) {
            return $update["callback_query"]['data'];
        }
        else return $update["message"]["text"];
    }

    public function get_message_id($update) {
        if (isset($update["callback_query"])) {
            return $update["callback_query"]['message']['message_id'];
        }
        return $update['message']['message_id'];
    }


    public function get_chat_id($update) {
        if (isset($update["callback_query"])) {
            return $update["callback_query"]['message']["chat"]["id"];
        }
        else return $update["message"]["chat"]["id"];
    }

    public function command($command_name, $update, callable $callback) {
        if (isset($update["callback_query"])) {
            if (!!(mb_stristr($update['callback_query']['data'], '/'))) {
                if ($this->get_message($update) == $command_name ||
                    substr($this->get_message($update),0,  strpos(trim($this->get_message($update)),' ')) == $command_name)
                {
                    $callback();
                }
            }
        } elseif ($this->get_message($update) == $command_name ||
            substr($this->get_message($update),0,  strpos(trim($this->get_message($update)),' ')) == $command_name)
        {
            $callback();
        }
    }

    public function get_command_param($update) {
        return substr($this->get_message($update), strpos(trim($this->get_message($update)),' ') + 1);
    }

    public function state_handler($expected_state, $real_state, callable $callback) {
        if($expected_state == $real_state) {
            $callback();
        }
    }

    public function state_update($state, $connect, $id) {
        mysqli_query($connect,"UPDATE user_data SET state=$state WHERE id=$id"); //???????
    }
}

