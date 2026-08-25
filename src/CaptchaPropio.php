<?php

namespace App;

class CaptchaPropio
{
    private const ANCHO = 300;
    private const ALTO = 150;
    private const PIEZA = 50;
    private const TOLERANCIA = 3;
    private const VIGENCIA_SEGUNDOS = 120;
    private const MAX_INTENTOS = 3;

    public static function generar(): array
    {
        self::limpiarVencidos();
        $destinoX = random_int(self::PIEZA + 30, self::ANCHO - self::PIEZA - 10);
        $destinoY = random_int(10, self::ALTO - self::PIEZA - 10);

        $fondo = self::generarFondo();
        $pieza = self::recortarPieza($fondo, $destinoX, $destinoY);
        self::abrirHueco($fondo, $destinoX, $destinoY);

        $id = bin2hex(random_bytes(16));

        $_SESSION['captcha_desafios'][$id] = [
            'x' => $destinoX,
            'expira' => time() + self::VIGENCIA_SEGUNDOS,
            'intentos' => 0,
        ];

        return [
            'desafio_id' => $id,
            'fondo' => self::aBase64($fondo),
            'pieza' => self::aBase64($pieza),
            'pieza_y' => $destinoY,
            'ancho' => self::ANCHO,
            'alto' => self::ALTO,
            'lado_pieza' => self::PIEZA,
        ];
    }

    public static function validar(string $id, int $posicionX, array $traza = []): bool
    {
        $desafio = $_SESSION['captcha_desafios'][$id] ?? null;

        if ($desafio === null) {
            return false;
        }

        if (time() > $desafio['expira']) {
            unset($_SESSION['captcha_desafios'][$id]);
            return false;
        }

        $_SESSION['captcha_desafios'][$id]['intentos']++;

        if ($_SESSION['captcha_desafios'][$id]['intentos'] > self::MAX_INTENTOS) {
            unset($_SESSION['captcha_desafios'][$id]);
            return false;
        }

        $acerto = abs($posicionX - $desafio['x']) <= self::TOLERANCIA;

        // Un arrastre humano genera decenas de eventos de mov  imiento y toma
        // cientos de milisegundos. Un script que fija la posición de una vez
        // genera uno o dos puntos y termina al instante.
        if ($acerto && !self::pareceHumano($traza)) {
            unset($_SESSION['captcha_desafios'][$id]);
            return false;
        }

        if ($acerto) {
            unset($_SESSION['captcha_desafios'][$id]);
        }

        return $acerto;
    }

    private static function pareceHumano(array $traza): bool
    {
        if (count($traza) < 10) {
            return false;
        }

        $duracion = end($traza)['t'] ?? 0;

        return $duracion >= 300;
    }

    private static function limpiarVencidos(): void
    {
        $ahora = time();

        foreach ($_SESSION['captcha_desafios'] ?? [] as $id => $desafio) {
            if ($ahora > $desafio['expira']) {
                unset($_SESSION['captcha_desafios'][$id]);
            }
        }
    }


    private static function generarFondo(): \GdImage
    {
        $img = imagecreatetruecolor(self::ANCHO, self::ALTO);

        $matiz = random_int(0, 359);
        imagefilledrectangle(
            $img, 0, 0, self::ANCHO, self::ALTO,
            self::colorDesdeMatiz($img, $matiz, 0.30, 0.92)
        );

        for ($i = 0; $i < 45; $i++) {
            $color = self::colorDesdeMatiz(
                $img,
                ($matiz + random_int(0, 90)) % 360,
                0.60,
                random_int(60, 95) / 100
            );

            $x = random_int(0, self::ANCHO);
            $y = random_int(0, self::ALTO);
            $d = random_int(16, 56);

            if (random_int(0, 1) === 0) {
                imagefilledellipse($img, $x, $y, $d, $d, $color);
            } else {
                imagefilledrectangle($img, $x, $y, $x + $d, $y + intdiv($d, 2), $color);
            }
        }

        return $img;
    }

    private static function recortarPieza(\GdImage $fondo, int $x, int $y): \GdImage
    {
        $pieza = imagecreatetruecolor(self::PIEZA, self::PIEZA);
        imagecopy($pieza, $fondo, 0, 0, $x, $y, self::PIEZA, self::PIEZA);

        $borde = imagecolorallocate($pieza, 255, 255, 255);
        imagerectangle($pieza, 0, 0, self::PIEZA - 1, self::PIEZA - 1, $borde);

        return $pieza;
    }

    private static function abrirHueco(\GdImage $fondo, int $x, int $y): void
    {
        $oscuro = imagecolorallocatealpha($fondo, 0, 0, 0, 45);
        $borde = imagecolorallocate($fondo, 255, 255, 255);

        imagefilledrectangle($fondo, $x, $y, $x + self::PIEZA - 1, $y + self::PIEZA - 1, $oscuro);
        imagerectangle($fondo, $x, $y, $x + self::PIEZA - 1, $y + self::PIEZA - 1, $borde);
    }

    private static function colorDesdeMatiz(\GdImage $img, int $matiz, float $sat, float $val): int
    {
        $c = $val * $sat;
        $x = $c * (1 - abs(fmod($matiz / 60, 2) - 1));
        $m = $val - $c;

        [$r, $g, $b] = match (intdiv($matiz, 60)) {
            0 => [$c, $x, 0],
            1 => [$x, $c, 0],
            2 => [0, $c, $x],
            3 => [0, $x, $c],
            4 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return imagecolorallocate(
            $img,
            (int) (($r + $m) * 255),
            (int) (($g + $m) * 255),
            (int) (($b + $m) * 255)
        );
    }

    private static function aBase64(\GdImage $img): string
    {
        ob_start();
        imagepng($img);
        $binario = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($binario);
    }
}