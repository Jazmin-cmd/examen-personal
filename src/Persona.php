<?php

namespace App;

use PDO;

class Persona
{
    public static function crear(array $datos): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO personas (nombres, apellidos, nro_documento, fecha_nacimiento, foto_frente, foto_dorso)
             VALUES (:nombres, :apellidos, :nro_documento, :fecha_nacimiento, :foto_frente, :foto_dorso)"
        );
        $stmt->execute($datos);
        return (int) $pdo->lastInsertId();
    }

    public static function buscarPorId(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM personas WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $persona = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($persona) {
            $persona['edad'] = self::calcularEdad($persona['fecha_nacimiento']);
        }

        return $persona ?: null;
    }

    public static function actualizar(int $id, array $datos): bool
    {
        $pdo = Database::getConnection();

        $campos = "nombres = :nombres, apellidos = :apellidos, fecha_nacimiento = :fecha_nacimiento";
        $datos['id'] = $id;

        if (!empty($datos['foto_frente'])) {
            $campos .= ", foto_frente = :foto_frente";
        }
        if (!empty($datos['foto_dorso'])) {
            $campos .= ", foto_dorso = :foto_dorso";
        }

        $stmt = $pdo->prepare("UPDATE personas SET $campos WHERE id = :id");
        return $stmt->execute($datos);
    }

    public static function eliminar(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM personas WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    private static function calcularEdad(string $fechaNacimiento): int
    {
        $nacimiento = new \DateTime($fechaNacimiento);
        $hoy = new \DateTime();
        return $hoy->diff($nacimiento)->y;
    }

    public static function buscarConFiltro(string $documento, string $filtro, string $nombre, string $apellido, int $pagina = 1, int $porPagina = 20): array
    {
        $pdo = Database::getConnection();
        $offset = ($pagina - 1) * $porPagina;

        [$where, $params] = self::armarWhere($filtro, $nombre, $apellido, $documento);

        $sql = "SELECT id, nombres, apellidos, nro_documento, fecha_nacimiento, foto_frente, foto_dorso FROM personas $where ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $personas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($personas as &$persona) {
            $persona['edad'] = self::calcularEdad($persona['fecha_nacimiento']);
        }
        return $personas;
    }

    public static function contarConFiltro(string $documento, string $nombre, string $apellido, string $filtro): int
    {
        $pdo = Database::getConnection();
        [$where, $params] = self::armarWhere($filtro, $nombre, $apellido, $documento);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM personas $where");
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private static function armarWhere(string $filtro, string $nombre, string $apellido, string $documento): array
    {
        if ($filtro === 'todos') {
            return ['', []];
        }

        if ($filtro === 'documento') {
            return ['WHERE nro_documento LIKE :doc', [':doc' => '%' . $documento . '%']];
        }

        // filtro === 'nombre': combina nombre y/o apellido, cualquiera de los dos que venga
        $condiciones = [];
        $params = [];

        if ($nombre !== '') {
            $condiciones[] = 'MATCH(nombres) AGAINST(:nombre IN BOOLEAN MODE)';
            $params[':nombre'] = $nombre . '*';
        }
        if ($apellido !== '') {
            $condiciones[] = 'MATCH(apellidos) AGAINST(:apellido IN BOOLEAN MODE)';
            $params[':apellido'] = $apellido . '*';
        }

        if (empty($condiciones)) {
            return ['', []];
        }

        return ['WHERE ' . implode(' AND ', $condiciones), $params];
    }
}