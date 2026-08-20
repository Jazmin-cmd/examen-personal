<?php

namespace App;

class GeoHelper
{
    public static function obtenerInfo(string $ip): ?array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $ch = curl_init("http://ip-api.com/json/{$ip}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // no bloquear la búsqueda si la API tarda

        $respuesta = curl_exec($ch);
        curl_close($ch);

        if ($respuesta === false) {
            return null; // SI LA API FALLA NO SE ROMPE LA APLICACION
        }

        $data = json_decode($respuesta, true);

        if (($data['status'] ?? '') !== 'success') {
            return null;
        }

        return [
            'pais' => $data['country'] ?? null,
            'ciudad' => $data['city'] ?? null,
            'proveedor' => $data['isp'] ?? null,
            'lat' => $data['lat'] ?? null,
            'lon' => $data['lon'] ?? null,
        ];
    }
}