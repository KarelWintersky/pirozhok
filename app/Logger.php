<?php

namespace App;

use Arris\AppLogger;

class Logger
{
    /**
     * Логгирование в файл больших дампов данных
     *
     * @param $data
     * @param string $mode
     * @return void
     */
    public static function log($data, string $mode = 'debug'): void
    {
        $dir = config('PATH.RAWLOGS');
        $dt = date('Y-m-d-H-i-s-u');
        $fn = $dir . "/{$mode}-{$dt}.txt";
        file_put_contents($fn , var_export($data, true));
    }

    /**
     * Логгирование событий бота
     */
    public static function logEvent(string $message, array $context = []): void
    {
        AppLogger::scope('bot')->debug($message, $context);
    }

    public static function getTelegramId()
    {
        // из App::$raw_input
        return match(true) {
            array_key_exists('message', App::$raw_input)            =>  (int)App::$dot_input->get('message.from.id') ,
            array_key_exists('my_chat_member', App::$raw_input)     =>  (int)App::$dot_input->get('my_chat_member.chat.id'),
            array_key_exists('callback_query', App::$raw_input)     =>  (int)App::$dot_input->get('callback_query.from.id'),
            default         =>  0,
        };
    }

}