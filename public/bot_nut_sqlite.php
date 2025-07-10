<?php

use App\App;
use App\Bot\Commands;
use App\Engine;
use App\Logger;
use Arris\AppLogger;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Message\Message;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

require_once __DIR__ . '/../vendor/autoload.php';
$config =   require __DIR__ . '/../config.php';

try {
    App::init($config);
    App::initLogger();

    Engine::logRAW(__DIR__ . '/../logs');

    $db = App::$pdo;

    $ADMIN_CHANNEL_ID = $config['ADMIN_CHANNEL_ID']; // ID админ-канала (с минусом)
    $PUBLIC_CHANNEL_ID = $config['PUBLIC_CHANNEL_ID']; // ID публичного канала
    $TOKEN = config('BOT.TOKEN');

    $psr6Cache = new FilesystemAdapter();
    $psr16Cache = new Psr16Cache($psr6Cache);
    $bot_config = new \SergiX44\Nutgram\Configuration(
        clientTimeout: config('BOT.TIMEOUT') ?? 30,
        cache: $psr16Cache
    );

    App::$bot = $bot = new Nutgram($TOKEN, $bot_config);
    $bot->setRunningMode(\SergiX44\Nutgram\RunningMode\Webhook::class);

    $bot->onCommand('start', [ Commands::class, 'onStart']);

    $bot->onMessage([ Commands::class, 'onMessage']);

    $bot->onCallbackQueryData("approve_{message_id}", [ Commands::class, 'onApprove']);

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
} catch (Exception|Throwable $e) {
    if (!is_null(App::$bot) && App::$bot instanceof Nutgram) {
        App::$bot->sendMessage($e->getMessage());
    }

    Logger::logEvent("ERROR [" . $e->getMessage() . ']', (array)$e );

    AppLogger::scope('bot')->error($e->getMessage(), [
        $e
    ]);
}

