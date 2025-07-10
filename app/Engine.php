<?php

namespace App;

use PDO;

class Engine
{
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
        message_id TEXT NOT NULL,
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

    public static function approveMessage(PDO $db, int $messageId): void
    {
        $stmt = $db->prepare("
        UPDATE messages
        SET is_approved = 1, time_approved = CURRENT_TIMESTAMP
        WHERE message_id = :message_id
    ");
        $stmt->execute([':message_id' => $messageId]);
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