<?php

namespace App;

class ImagenHelper
{
    private const TAMANO_MAX = 2 * 1024 * 1024; // 2 MB
    private const TIPOS_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];

    public static function procesar(array $archivo): string
    {
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Error al subir el archivo');
        }

        if ($archivo['size'] > self::TAMANO_MAX) {
            throw new \RuntimeException('El archivo supera el tamaño máximo permitido (2MB)');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeReal, self::TIPOS_PERMITIDOS, true)) {
            throw new \RuntimeException('El archivo no es una imagen válida');
        }

        $info = @getimagesize($archivo['tmp_name']);
        if ($info === false) {
            throw new \RuntimeException('El archivo no es una imagen procesable');
        }

        $extension = match ($mimeReal) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };
        $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;

        $rutaDestino = __DIR__ . '/../storage/cedulas/' . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            throw new \RuntimeException('No se pudo guardar el archivo');
        }

        return $nombreArchivo;
    }
}