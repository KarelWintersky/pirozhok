<?php

$config = require __DIR__ . '/config.php';

$bot_api_key = $config['TOKEN'];
$domain = $config['WEBHOOK_DOMAIN'];
$script = $config['WEBHOOK_SCRIPT'];

$url = "https://api.telegram.org/bot{$bot_api_key}/setWebhook?url={$domain}/{$script}";

var_dump(json_decode(file_get_contents($url), true));

exit;



