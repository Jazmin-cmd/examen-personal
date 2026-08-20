<?php

namespace App;

use PDO;

class Persona
{
    public static function listar(int $pagina = 1, int $porPagina = 20): array
    {
        $pdo = Database::getConnection();
        $offset = ($pagina - 1) * $porPagina;

        //$stmt = $pdo->prepare("SELECT id, nombres, apellidos, nro_documento, fecha_nacimiento FROM personas ORDER BY id DESC LIMIT :limit OFFSET :offset");
         $stmt = $pdo->prepare("SELECT id, nombres, apellidos, nro_documento, fecha_nacimiento, foto_frente, foto_dorso FROM personas ORDER BY id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $personas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($personas as &$persona) {
            $persona['edad'] = self::calcularEdad($persona['fecha_nacimiento']);
        }

        return $personas;
    }

    public static function contarTotal(): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM personas");
        return (int) $stmt->fetchColumn();
    }

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
        $stmt = $pdo->prepare(
            "UPDATE personas SET nombres = :nombres, apellidos = :apellidos,
             fecha_nacimiento = :fecha_nacimiento WHERE id = :id"
        );
        $datos['id'] = $id;
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

    public static function buscar(string $termino, int $pagina = 1, int $porPagina = 20): array
    {
        $pdo = Database::getConnection();
        $offset = ($pagina - 1) * $porPagina;
        $like = '%' . $termino . '%';

        $stmt = $pdo->prepare(
            "SELECT id, nombres, apellidos, nro_documento, fecha_nacimiento
             FROM personas
            WHERE CONCAT(nombres, ' ', apellidos) LIKE :like
                OR nombres LIKE :like
                OR apellidos LIKE :like
                OR nro_documento LIKE :like
             ORDER BY id DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':like', $like, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $personas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($personas as &$persona) {
            $persona['edad'] = self::calcularEdad($persona['fecha_nacimiento']);
        }

        return $personas;
    }

    public static function contarBusqueda(string $termino): int
    {
        $pdo = Database::getConnection();
        $like = '%' . $termino . '%';

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM personas
             WHERE CONCAT(nombres, ' ', apellidos) LIKE :like
                OR nombres LIKE :like
                OR apellidos LIKE :like
                OR nro_documento LIKE :like"
        );
        $stmt->bindValue(':like', $like, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    


}