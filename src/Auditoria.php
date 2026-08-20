<?php

namespace App;

use PDO;

class Auditoria
{
    public static function registrar(array $datos): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO busquedas_auditoria
             (termino, cantidad_resultados, ip_origen, geo_pais, geo_ciudad, geo_proveedor, geo_lat, geo_lon, telegram_enviado)
             VALUES (:termino, :cantidad_resultados, :ip_origen, :geo_pais, :geo_ciudad, :geo_proveedor, :geo_lat, :geo_lon, :telegram_enviado)"
        );
        $stmt->execute($datos);
        return (int) $pdo->lastInsertId();
    }

    public static function listar(int $pagina = 1, int $porPagina = 20): array
    {
        $pdo = Database::getConnection();
        $offset = ($pagina - 1) * $porPagina;

        $stmt = $pdo->prepare("SELECT * FROM busquedas_auditoria ORDER BY id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}