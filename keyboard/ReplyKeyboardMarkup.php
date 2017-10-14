<?php

class ReplyKeyboardMarkup {
    private $reply_keyboard = array();

    public function __construct(array $keyboard_button, $resize_keyboard = false, $one_time_keyboard = false) {
        foreach ($keyboard_button as $button) {

            foreach ($button as $value) {
                if(is_array($value)) {
                    $this->reply_keyboard["inline_keyboard"][] = $button;
                    break;
                }
                else  {
                    $this->reply_keyboard["keyboard"][] = array($button);
                    break;
                }

            }
        }
        $this->reply_keyboard['resize_keyboard'] = $resize_keyboard;
        $this->reply_keyboard['one_time_keyboard'] = $one_time_keyboard;
    }

    public function get_keyboard() {
        return $this->reply_keyboard;
    }
}