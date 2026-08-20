<?php

namespace App;

class CaptchaHelper
{
    public static function validar(string $token, string $ip): bool
    {
        $secret = $_ENV['TURNSTILE_SECRET_KEY'];

        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ip
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // no dejar colgada la request si Cloudflare no responde

        $respuesta = curl_exec($ch);
        curl_close($ch);

        if ($respuesta === false) {
            return false;
        }

        $data = json_decode($respuesta, true);
        return $data['success'] ?? false;
    }
}