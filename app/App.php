<?php

namespace App;

use Arris\AppLogger;
use Arris\Core\Dot;
use Arris\Database\Config;
use Arris\Database\Connector;
use Arris\Path;
use PDO;

class App extends \Arris\App
{
    public static $config;
    /**
     * @var array|mixed|null
     */
    public static mixed $raw_input;
    /**
     * @var array|Dot|mixed|null
     */
    public static mixed $dot_input;
    /**
     * @var array|Path|mixed|null
     */
    public static mixed $path_install;
    /**
     * @var array|bool|mixed|string|null
     */
    public static string $bot_token;

    /**
     * @var \PDO $pdo
     */
    public static mixed $pdo;
    /**
     * @var array|mixed|\SergiX44\Nutgram\Nutgram|null
     */
    public static mixed $bot;

    public static function init(array $config)
    {
        self::$config = App::factory($config)->getConfig();

        App::$raw_input = json_decode(file_get_contents('php://input'), true);
        App::$dot_input = new Dot(self::$raw_input);

        App::$path_install = new Path(config('PATH.INSTALL'));

        // self::$bot_name = config('BOT.NAME');
        self::$bot_token = config('BOT.TOKEN');

        /*$c = (new Config())
            ->setDriver(Config::DRIVER_SQLITE)
            ->setHost(App::$path_install->join('pirozhok.sqlite')->toString())
        ;

        self::$pdo = new Connector(
            $c
        );*/

        self::$pdo = new PDO('sqlite:' . App::$path_install->join('pirozhok.sqlite')->toString());
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        self::$pdo->exec("
    CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        message_id TEXT NOT NULL,
        time_added DATETIME DEFAULT CURRENT_TIMESTAMP,
        message TEXT NOT NULL,
        is_approved BOOLEAN DEFAULT 0,
        time_approved DATETIME
    )
");

        self::$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        user_id TEXT PRIMARY KEY,
        user_name TEXT,
        is_banned BOOLEAN DEFAULT 0,
        messages_count INTEGER DEFAULT 0
    )
");

        // $db = new PDO('sqlite:pirozhok.sqlite');
        // $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }


    public static function initLogger()
    {
        AppLogger::init('Pirozhok', options: [
            'default_logfile_path'  =>  config('PATH.LOGS')
        ]);
        AppLogger::addScope('bot');
    }

}