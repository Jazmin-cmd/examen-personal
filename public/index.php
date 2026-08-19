<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Persona;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

header('Content-Type: application/json');

$metodo = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segmentos = explode('/', trim($uri, '/'));

// GET /personas?pagina=1
if ($uri === '/personas' && $metodo === 'GET') {
    $pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
    $personas = Persona::listar($pagina);
    $total = Persona::contarTotal();

    echo json_encode([
        'data' => $personas,
        'total' => $total,
        'pagina' => $pagina
    ]);

// GET /personas/5
} elseif ($segmentos[0] === 'personas' && isset($segmentos[1]) && $metodo === 'GET') {
    $persona = Persona::buscarPorId((int) $segmentos[1]);

    if ($persona) {
        echo json_encode($persona);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Persona no encontrada']);
    }

} elseif ($uri === '/personas' && $metodo === 'POST') {
    if (empty($_POST['nombres']) || empty($_POST['apellidos']) || empty($_POST['nro_documento']) || empty($_POST['fecha_nacimiento'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios']);
        exit;
    }

    if (strtotime($_POST['fecha_nacimiento']) > time()) {
        http_response_code(400);
        echo json_encode(['error' => 'La fecha de nacimiento no puede ser futura']);
        exit;
    }

    if (empty($_FILES['foto_frente']) || empty($_FILES['foto_dorso'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Debe subir ambas imágenes de la cédula']);
        exit;
    }

    try {
        $nombreFrente = \App\ImagenHelper::procesar($_FILES['foto_frente']);
        $nombreDorso = \App\ImagenHelper::procesar($_FILES['foto_dorso']);

        $id = Persona::crear([
            'nombres' => $_POST['nombres'],
            'apellidos' => $_POST['apellidos'],
            'nro_documento' => $_POST['nro_documento'],
            'fecha_nacimiento' => $_POST['fecha_nacimiento'],
            'foto_frente' => $nombreFrente,
            'foto_dorso' => $nombreDorso
        ]);
        http_response_code(201);
        echo json_encode(['id' => $id, 'mensaje' => 'Persona creada']);
    } catch (\PDOException $e) {
        http_response_code(409);
        echo json_encode(['error' => 'El número de documento ya existe']);
    } catch (\RuntimeException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    // PUT /personas/5
} elseif ($segmentos[0] === 'personas' && isset($segmentos[1]) && $metodo === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $actualizado = Persona::actualizar((int) $segmentos[1], $input);
    echo json_encode(['actualizado' => $actualizado]);

// DELETE /personas/5
} elseif ($segmentos[0] === 'personas' && isset($segmentos[1]) && $metodo === 'DELETE') {
    $eliminado = Persona::eliminar((int) $segmentos[1]);
    echo json_encode(['eliminado' => $eliminado]);

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Ruta no encontrada']);
}