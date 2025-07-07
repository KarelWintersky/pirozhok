<?php

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Message\Message;

logRAW(__DIR__ . '/../logs');

$db = new PDO('sqlite:moderation_bot.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$TOKEN = $config['TOKEN'];
$ADMIN_CHANNEL_ID = $config['ADMIN_CHANNEL_ID']; // ID админ-канала (с минусом)
$PUBLIC_CHANNEL_ID = $config['PUBLIC_CHANNEL_ID']; // ID публичного канала

$bot = new Nutgram($TOKEN);

// Обработчик приватных сообщений
$bot->onMessage(function (Nutgram $bot, Message $msg) use ($ADMIN_CHANNEL_ID, &$messageStore) {
    if ($msg->chat->isPrivate()) {
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('✅ Approve', callback_data: "approve_{$msg->message_id}"),
                InlineKeyboardButton::make('❌ Reject', callback_data: "reject_{$msg->message_id}"),
                InlineKeyboardButton::make('⛔ Ban', callback_data: "ban_{$msg->message_id}"),
            );

        // Отправляем в админ-канал
        $adminMsg = $bot->sendMessage(
            text: "Новое сообщение от @{$msg->from->username} ({$msg->from->id}):\n\n{$msg->text}",
            chat_id: $ADMIN_CHANNEL_ID,
            reply_markup: $keyboard
        );

        // Сохраняем связь сообщений
        $messageStore[$adminMsg->message_id] = [
            'user_id' => $msg->from->id,
            'original_text' => $msg->text,
            'original_msg_id' => $msg->message_id
        ];
    }
})->description('Прием сообщений от пользователей');

// Обработчик callback-кнопок
$bot->onCallbackQueryData('approve_*', function (Nutgram $bot, string $cbData) use ($ADMIN_CHANNEL_ID, $PUBLIC_CHANNEL_ID, &$messageStore) {
    $msgId = (int)str_replace('approve_', '', $cbData);

    if (isset($messageStore[$msgId])) {
        // Публикуем в основном канале
        $bot->sendMessage(
            text: $messageStore[$msgId]['original_text'],
            chat_id: $PUBLIC_CHANNEL_ID
        );

        // Удаляем кнопки
        $bot->editMessageReplyMarkup(
            chat_id: $ADMIN_CHANNEL_ID,
            message_id: $msgId,
            reply_markup: null
        );

        $bot->answerCallbackQuery(text: 'Сообщение одобрено!');
    }
});

$bot->onCallbackQueryData('reject_*', function (Nutgram $bot, string $cbData) use ($ADMIN_CHANNEL_ID) {
    $msgId = (int)str_replace('reject_', '', $cbData);

    // Просто удаляем кнопки
    $bot->editMessageReplyMarkup(
        chat_id: $ADMIN_CHANNEL_ID,
        message_id: $msgId,
        reply_markup: null
    );

    $bot->answerCallbackQuery(text: 'Сообщение отклонено!');
});

$bot->onCallbackQueryData('ban_*', function (Nutgram $bot, string $cbData) use ($ADMIN_CHANNEL_ID, &$messageStore) {
    $msgId = (int)str_replace('ban_', '', $cbData);

    if (isset($messageStore[$msgId])) {
        // Баним пользователя
        banUser($messageStore[$msgId]['user_id']);

        // Меняем кнопки на Unban
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('🔓 Unban', callback_data: "unban_{$msgId}")
            );

        $bot->editMessageReplyMarkup(
            chat_id: $ADMIN_CHANNEL_ID,
            message_id: $msgId,
            reply_markup: $keyboard
        );

        $bot->answerCallbackQuery(text: 'Пользователь забанен!');
    }
});

$bot->onCallbackQueryData('unban_*', function (Nutgram $bot, string $cbData) use ($ADMIN_CHANNEL_ID) {
    $msgId = (int)str_replace('unban_', '', $cbData);

    // Удаляем кнопки
    $bot->editMessageReplyMarkup(
        chat_id: $ADMIN_CHANNEL_ID,
        message_id: $msgId,
        reply_markup: null
    );

    $bot->answerCallbackQuery(text: 'Пользователь разбанен!');
});

$bot->run();
