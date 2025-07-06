<?php

$config = require __DIR__ . '/config.php';

$bot_api_key = $config['TOKEN'];
$domain = 'pirozhok.dev.wombatrpg.ru';
$target = 'bot_nut.php';
// $target = 'bot_legacy.php';

$url = "https://api.telegram.org/bot{$bot_api_key}/setWebhook?url={$domain}/{$target}";

var_dump(json_decode(file_get_contents($url), true));

exit;



