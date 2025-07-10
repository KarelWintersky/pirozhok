<?php

namespace App;

use SergiX44\Nutgram\Telegram\Types\User\User;

class UserAccount
{

    /**
     * Возвращает список сообщений пользователя
     *
     * @param User $user
     * @param bool $create_new_user
     * @return array
     */
    public static function getUserMessages(User $user, bool $create_new_user = true):array
    {
        $pdo = App::$pdo;

        $sth = $pdo->prepare("SELECT * FROM messages WHERE user_id = ?");
        $sth->execute([ $user->id ]);

        return $sth->fetch() ?: [];
    }

    public static function getUser(User $user):array
    {
        $pdo = App::$pdo;

        $sth = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $sth->execute([ $user->id ]);

        return $sth->fetch() ?: [];
    }

    public static function updateUser(array $userData): void
    {
        $db = App::$pdo;

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

    public static function addMessage(array $messageData): int
    {
        $db = App::$pdo;
        $stmt = $db->prepare("
        INSERT INTO messages (user_id, message, message_id)
        VALUES (:user_id, :message, :message_id)
    ");

        $stmt->execute([
            ':user_id'      => $messageData['user_id'],
            ':message'      => $messageData['message'],
            ':message_id'   =>  $messageData['message_id']
        ]);

        return $messageData['message_id'];
    }




}