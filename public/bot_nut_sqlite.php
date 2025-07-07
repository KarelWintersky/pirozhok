<?php

require_once __DIR__ . '/../vendor/autoload.php';

class Engine {
    public static function logRAW($dir) {
        $raw_input = json_decode(file_get_contents('php://input'), true);
        $dt = date('Y-m-d-H-i-s-u');
        $fn = $dir . "/raw-{$dt}.txt";
        file_put_contents($fn , var_export($raw_input, true));
    }


    public static function prepareDB($db) {
        $db->exec("
    CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        time_added DATETIME DEFAULT CURRENT_TIMESTAMP,
        message TEXT NOT NULL,
        is_approved BOOLEAN DEFAULT 0,
        time_approved DATETIME
    )
");

        $db->exec("
    CREATE TABLE IF NOT EXISTS users (
        user_id TEXT PRIMARY KEY,
        user_name TEXT,
        is_banned BOOLEAN DEFAULT 0,
        messages_count INTEGER DEFAULT 0
    )
");
    }

// Функция для работы с пользователями
    public static function getUser(PDO $db, string $userId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function updateUser(PDO $db, array $userData): void
    {
        $stmt = $db->prepare("
        INSERT INTO users (user_id, user_name, is_banned, messages_count)
        VALUES (:user_id, :user_name, :is_banned, :messages_count)
        ON CONFLICT(user_id) DO UPDATE SET
            user_name = excluded.user_name,
            is_banned = excluded.is_banned,
            messages_count = excluded.messages_count
    ");

        $stmt->execute([
            ':user_id' => $userData['user_id'],
            ':user_name' => $userData['user_name'],
            ':is_banned' => $userData['is_banned'] ?? 0,
            ':messages_count' => $userData['messages_count'] ?? 0
        ]);
    }

    public static function addMessage(PDO $db, array $messageData): int
    {
        $stmt = $db->prepare("
        INSERT INTO messages (user_id, message)
        VALUES (:user_id, :message)
    ");

        $stmt->execute([
            ':user_id' => $messageData['user_id'],
            ':message' => $messageData['message']
        ]);

        return $db->lastInsertId();
    }

    public static function approveMessage(PDO $db, int $messageId): void
    {
        $stmt = $db->prepare("
        UPDATE messages
        SET is_approved = 1, time_approved = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
        $stmt->execute([':id' => $messageId]);
    }

    public static function banUser(PDO $db, string $userId): void
    {
        $stmt = $db->prepare("
        UPDATE users
        SET is_banned = 1
        WHERE user_id = :user_id
    ");
        $stmt->execute([':user_id' => $userId]);
    }

    public static function unbanUser(PDO $db, string $userId): void
    {
        $stmt = $db->prepare("
        UPDATE users
        SET is_banned = 0
        WHERE user_id = :user_id
    ");
        $stmt->execute([':user_id' => $userId]);
    }




}

$config = require __DIR__ . '/../config.php';

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Message\Message;

Engine::logRAW(__DIR__ . '/../logs');

$db = new PDO('sqlite:pirozhok.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$TOKEN = $config['TOKEN'];
$ADMIN_CHANNEL_ID = $config['ADMIN_CHANNEL_ID']; // ID админ-канала (с минусом)
$PUBLIC_CHANNEL_ID = $config['PUBLIC_CHANNEL_ID']; // ID публичного канала

$bot = new Nutgram($TOKEN);

// Обработчик входящих сообщений
$bot->onMessage(function (Nutgram $bot) use ($ADMIN_CHANNEL_ID, $db) {
    $msg = $bot->message();

    if ($msg->chat->isPrivate()) {
        // Обновляем/добавляем пользователя
        $user = [
            'user_id' => $msg->from->id,
            'user_name' => $msg->from->username ?? "{$msg->from->first_name} {$msg->from->last_name}",
            'messages_count' => ($db->query("SELECT messages_count FROM users WHERE user_id = '{$msg->from->id}'")->fetchColumn() ?? 0) + 1
        ];

        Engine::updateUser($db, $user);

        // Сохраняем сообщение
        $messageId = Engine::addMessage($db, [
            'user_id' => $msg->from->id,
            'message' => $msg->text
        ]);

        // Создаем клавиатуру
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('✅ Approve', callback_data: "approve_$messageId"),
                InlineKeyboardButton::make('❌ Reject', callback_data: "reject_$messageId"),
                InlineKeyboardButton::make('⛔ Ban', callback_data: "ban_{$msg->from->id}_{$messageId}")
            );

        // Отправляем в админ-канал
        $bot->sendMessage(
            text: "Новое сообщение от @{$user['user_name']} ({$user['user_id']}):\n\n{$msg->text}",
            chat_id: $ADMIN_CHANNEL_ID,
            reply_markup: $keyboard
        );
    }
});

// Обработчики callback-кнопок
$bot->onCallbackQueryData('approve_*', function (Nutgram $bot) use ($PUBLIC_CHANNEL_ID, $ADMIN_CHANNEL_ID, $db) {
    $messageId = (int)str_replace('approve_', '', $bot->callbackQuery()->data);

    // Получаем сообщение из БД
    $stmt = $db->prepare("SELECT * FROM messages WHERE id = :id");
    $stmt->execute([':id' => $messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($message) {
        // Публикуем в основном канале
        $bot->sendMessage(
            text: $message['message'],
            chat_id: $PUBLIC_CHANNEL_ID
        );

        // Обновляем статус в БД
        Engine::approveMessage($db, $messageId);

        // Удаляем кнопки
        $bot->editMessageReplyMarkup(
            chat_id: $ADMIN_CHANNEL_ID,
            message_id: $bot->callbackQuery()->message->message_id,
            reply_markup: null
        );

        $bot->answerCallbackQuery(text: 'Сообщение одобрено!');
    }
});

$bot->onCallbackQueryData('reject_*', function (Nutgram $bot) use ($ADMIN_CHANNEL_ID) {
    // Просто удаляем кнопки
    $bot->editMessageReplyMarkup(
        chat_id: $ADMIN_CHANNEL_ID,
        message_id: $bot->callbackQuery()->message->message_id,
        reply_markup: null
    );

    $bot->answerCallbackQuery(text: 'Сообщение отклонено!');
});

$bot->onCallbackQueryData('ban_*', function (Nutgram $bot) use ($ADMIN_CHANNEL_ID, $db) {
    [$userId, $messageId] = explode('_', str_replace('ban_', '', $bot->callbackQuery()->data));

    // Баним пользователя
    Engine::banUser($db, $userId);

    // Меняем кнопки на Unban
    $keyboard = InlineKeyboardMarkup::make()
        ->addRow(
            InlineKeyboardButton::make('🔓 Unban', callback_data: "unban_$userId")
        );

    $bot->editMessageReplyMarkup(
        chat_id: $ADMIN_CHANNEL_ID,
        message_id: $bot->callbackQuery()->message->message_id,
        reply_markup: $keyboard
    );

    $bot->answerCallbackQuery(text: 'Пользователь забанен!');
});

$bot->onCallbackQueryData('unban_*', function (Nutgram $bot) use ($ADMIN_CHANNEL_ID, $db) {
    $userId = str_replace('unban_', '', $bot->callbackQuery()->data);

    // Разбаниваем пользователя
    Engine::unbanUser($db, $userId);

    // Удаляем кнопки
    $bot->editMessageReplyMarkup(
        chat_id: $ADMIN_CHANNEL_ID,
        message_id: $bot->callbackQuery()->message->message_id,
        reply_markup: null
    );

    $bot->answerCallbackQuery(text: 'Пользователь разбанен!');
});

$bot->run();