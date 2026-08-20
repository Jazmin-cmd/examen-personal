<?php

namespace App;

class TelegramHelper
{
    public static function notificar(string $mensaje): bool
    {
        $token = $_ENV['TELEGRAM_BOT_TOKEN'];
        $chatId = $_ENV['TELEGRAM_CHAT_ID'];

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'chat_id' => $chatId,
            'text' => $mensaje,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $respuesta = curl_exec($ch);
        curl_close($ch);

        if ($respuesta === false) {
            return false;
        }

        $data = json_decode($respuesta, true);
        return $data['ok'] ?? false;
    }
}