<?php

namespace App\Bot;

use App\App;
use App\Engine;
use App\Logger;
use App\UserAccount;
use PDO;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class Commands
{
    /**
     * @var \PDO
     */
    protected mixed $pdo;

    public function __construct()
    {
        $this->pdo = App::$pdo;

    }

    /**
     * При старте бота
     *
     * @param Nutgram $bot
     * @return void
     */
    public function onStart(Nutgram $bot):void
    {
        $user_data = UserAccount::getUser($bot->user(), false);

        Logger::logEvent("onStart: показываем приветствие");

        $bot->sendMessage(
            text: Messages::onStart(),
            parse_mode: ParseMode::HTML,
            reply_markup: null
        );

        if (!empty($user_data)) {
            if ($user_data['is_banned']) {
                $bot->sendMessage(
                    text: Messages::onStartBanned()
                );
            } elseif ($user_data['messages_count'] > 0) {
                $bot->sendMessage(
                    text: Messages::onStartSomeMessages($user_data['messages_count'])
                );
            }
        }
    }

    public function onMessage(Nutgram $bot):void
    {
        $msg = $bot->message();
        $db = $this->pdo;

        if ($msg->chat->isPrivate()) {
            // Обновляем/добавляем пользователя
            $user = [
                'user_id' => $msg->from->id,
                'user_name' => $msg->from->username ?? "{$msg->from->first_name} {$msg->from->last_name}",
                'messages_count' => ($db->query("SELECT messages_count FROM users WHERE user_id = '{$msg->from->id}'")->fetchColumn() ?? 0) + 1
            ];

            UserAccount::updateUser($user);

            \App\Logger::logEvent("onPrivateMessage");

            // Сохраняем сообщение
            $messageId = UserAccount::addMessage([
                'user_id'       =>  $msg->from->id,
                'message'       =>  $msg->text,
                'message_id'    =>  $msg->message_id
            ]);

            // Создаем клавиатуру
            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('✅ Approve', callback_data: "approve_{$messageId}"),
                    InlineKeyboardButton::make('❌ Reject', callback_data: "reject_{$messageId}"),
                    InlineKeyboardButton::make('⛔ Ban', callback_data: "ban_{$msg->from->id}_{$messageId}")
                );

            // Отправляем в админ-канал
            $bot->sendMessage(
                text: "Новое сообщение от @{$user['user_name']} ({$user['user_id']}):\n\n{$msg->text}",
                chat_id: config('ADMIN_CHANNEL_ID'),
                reply_markup: $keyboard
            );
        }
    }

    public function onApprove(Nutgram $bot, $message_id):void
    {
        $db = $this->pdo;

        // $messageId = (int)str_replace('approve_', '', $bot->callbackQuery()->data);

        \App\Logger::logEvent("onCallbackQueryData Approve: {$message_id}");

        // Получаем сообщение из БД
        $stmt = $db->prepare("SELECT * FROM messages WHERE message_id = :message_id");
        $stmt->execute([':message_id' => $message_id]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($message) {
            // Публикуем в основном канале
            $bot->sendMessage(
                text: $message['message'],
                chat_id: config('PUBLIC_CHANNEL_ID')
            );

            // Обновляем статус в БД
            Engine::approveMessage($db, $message_id);

            // Удаляем кнопки
            $bot->editMessageReplyMarkup(
                chat_id: config('ADMIN_CHANNEL_ID'),
                message_id: $bot->callbackQuery()->message->message_id,
                reply_markup: null
            );

            $bot->answerCallbackQuery(text: 'Сообщение одобрено!');
        }
    }


}