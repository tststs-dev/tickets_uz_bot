<?php

class InlineKeyboardMarkup {
    private $inline_keyboard = array("inline_keyboard" => array());

    public function __construct(array $inline_keyboard_button) {
        foreach ($inline_keyboard_button as $button) {
            foreach ($button as $value) {
                if(is_array($value)) {
                    $this->inline_keyboard["inline_keyboard"][] = $button;
                    break;
                }
                else  {
                    $this->inline_keyboard["inline_keyboard"][] = array($button);
                    break;
                }

            }

        }

    }

    public function get_keyboard(){
        return $this->inline_keyboard;
    }
}