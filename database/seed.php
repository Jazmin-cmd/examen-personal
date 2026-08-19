<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$pdo = Database::getConnection();

$nombres = ['Juan', 'Maria', 'Carlos', 'Ana', 'Luis', 'Rosa', 'Pedro', 'Laura', 'Diego', 'Sofia', 'Miguel', 'Lucia', 'Jorge', 'Patricia', 'Ricardo', 'Elena', 'Fernando', 'Carmen', 'Ruben', 'Gloria'];
$apellidos = ['Gonzalez', 'Rodriguez', 'Benitez', 'Fernandez', 'Ramirez', 'Gimenez', 'Ortiz', 'Ayala', 'Cabrera', 'Rojas', 'Duarte', 'Villalba', 'Franco', 'Acosta', 'Ferreira', 'Torres', 'Britez', 'Melgarejo', 'Bogado', 'Coronel'];

$stmt = $pdo->prepare(
    "INSERT INTO personas (nombres, apellidos, nro_documento, fecha_nacimiento, foto_frente, foto_dorso)
     VALUES (:nombres, :apellidos, :nro_documento, :fecha_nacimiento, :foto_frente, :foto_dorso)"
);

$pdo->beginTransaction();

for ($i = 1; $i <= 500; $i++) {
    $nombre = $nombres[array_rand($nombres)];
    $apellido = $apellidos[array_rand($apellidos)];
    $documento = (string) (3000000 + $i); // documentos sintéticos, no reales

    $anio = rand(1960, 2006);
    $mes = rand(1, 12);
    $dia = rand(1, 28);
    $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);

    $stmt->execute([
        'nombres' => $nombre,
        'apellidos' => $apellido,
        'nro_documento' => $documento,
        'fecha_nacimiento' => $fecha,
        'foto_frente' => 'placeholder.png',
        'foto_dorso' => 'placeholder.png'
    ]);
}

$pdo->commit();

echo "500 personas sintéticas insertadas correctamente.\n";