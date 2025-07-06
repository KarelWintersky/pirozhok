<?php

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';

use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;

$raw_input = json_decode(file_get_contents('php://input'), true);
$dir = __DIR__ . '/../logs';
$dt = date('Y-m-d-H-i-s-u');
$fn = $dir . "/raw-{$dt}.txt";
file_put_contents($fn , var_export($raw_input, true));

$TOKEN = $config['TOKEN'];
$telegram = new Api($TOKEN);

// Конфигурация
$ADMIN_CHANNEL_ID = $config['ADMIN_CHANNEL_ID']; // ID админ-канала (с минусом)
$PUBLIC_CHANNEL_ID = $config['PUBLIC_CHANNEL_ID']; // ID публичного канала

// Временное хранилище сообщений (в реальном проекте используйте БД)
$messageStore = [];

// Обработчик входящих сообщений
$update = $telegram->getWebhookUpdate();

if (isset($update['message'])) {
    $message = $update['message'];
    $chat = $message['chat'];

    // Обрабатываем только личные сообщения
    if ($chat['type'] === 'private') {
        $userId = $message['from']['id'];
        $text = $message['text'];

        // Создаем клавиатуру с кнопками
        $keyboard = Keyboard::make()
            ->inline()
            ->row([
                Keyboard::inlineButton(['text' => '✅ Approve', 'callback_data' => 'approve']),
                Keyboard::inlineButton(['text' => '❌ Reject', 'callback_data' => 'reject']),
                Keyboard::inlineButton(['text' => '⛔ Ban', 'callback_data' => 'ban']),
            ]);

        // Отправляем сообщение в админский канал
        $response = $telegram->sendMessage([
            'chat_id' => ADMIN_CHANNEL_ID,
            'text' => "Новое сообщение от пользователя {$userId}:\n\n{$text}",
            'reply_markup' => $keyboard
        ]);

        // Сохраняем информацию о сообщении
        $messageId = $response->getMessageId();
        $messageStore[$messageId] = [
            'original_message' => $message,
            'user_id' => $userId
        ];

        // В реальном проекте сохраняйте в БД вместо переменной
        file_put_contents('message_store.json', json_encode($messageStore));
    }
}

// Обработчик callback-запросов (нажатий на кнопки)
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $data = $callback['data'];
    $adminMessageId = $callback['message']['message_id'];

    // Загружаем сохраненные сообщения
    $messageStore = json_decode(file_get_contents('message_store.json'), true);
    $messageData = $messageStore[$adminMessageId] ?? null;

    if (!$messageData) {
        $telegram->answerCallbackQuery([
            'callback_query_id' => $callback['id'],
            'text' => 'Сообщение не найдено!'
        ]);
        exit;
    }

    $originalMessage = $messageData['original_message'];
    $userId = $messageData['user_id'];

    switch ($data) {
        case 'approve':
            // Одобряем сообщение - отправляем в публичный канал
            $telegram->sendMessage([
                'chat_id' => PUBLIC_CHANNEL_ID,
                'text' => $originalMessage['text']
            ]);

            // Удаляем кнопки
            $telegram->editMessageReplyMarkup([
                'chat_id' => ADMIN_CHANNEL_ID,
                'message_id' => $adminMessageId,
                'reply_markup' => Keyboard::remove()
            ]);

            $telegram->answerCallbackQuery([
                'callback_query_id' => $callback['id'],
                'text' => 'Сообщение одобрено!'
            ]);
            break;

        case 'reject':
            // Отклоняем сообщение - удаляем кнопки
            $telegram->editMessageReplyMarkup([
                'chat_id' => ADMIN_CHANNEL_ID,
                'message_id' => $adminMessageId,
                'reply_markup' => Keyboard::remove()
            ]);

            $telegram->answerCallbackQuery([
                'callback_query_id' => $callback['id'],
                'text' => 'Сообщение отклонено!'
            ]);
            break;

        case 'ban':
            // Баним пользователя
            banUserAccount($userId);

            // Меняем кнопки на Unban
            $keyboard = Keyboard::make()
                ->inline()
                ->row([
                    Keyboard::inlineButton(['text' => '🔓 Unban', 'callback_data' => 'unban']),
                ]);

            $telegram->editMessageReplyMarkup([
                'chat_id' => ADMIN_CHANNEL_ID,
                'message_id' => $adminMessageId,
                'reply_markup' => $keyboard
            ]);

            $telegram->answerCallbackQuery([
                'callback_query_id' => $callback['id'],
                'text' => 'Пользователь забанен!'
            ]);
            break;

        case 'unban':
            // Разбаниваем пользователя
            // Здесь можно добавить логику разбана

            // Удаляем кнопки
            $telegram->editMessageReplyMarkup([
                'chat_id' => ADMIN_CHANNEL_ID,
                'message_id' => $adminMessageId,
                'reply_markup' => Keyboard::remove()
            ]);

            $telegram->answerCallbackQuery([
                'callback_query_id' => $callback['id'],
                'text' => 'Пользователь разбанен!'
            ]);
            break;
    }
}

function banUserAccount($userId) {
    // Функция для бана пользователя
    file_put_contents('banned_users.txt', $userId.PHP_EOL, FILE_APPEND);
    // Здесь можно добавить реальную логику бана
    // Например, вызвать метод Telegram API для ограничения пользователя
}

echo 'OK';
