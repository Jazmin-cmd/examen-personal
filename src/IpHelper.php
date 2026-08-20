<?php

namespace App;

class IpHelper
{
    public static function obtenerIpReal(): string
    {
        // Cloudflare Tunnel agrega esta cabecera con la IP real del visitante
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // Fallback: X-Forwarded-For (puede tener varias IPs separadas por coma; la primera es la del cliente original)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        // Último fallback: la IP directa de la conexión (útil en desarrollo local)
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}