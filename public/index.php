<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Persona;
use App\Auditoria;
use App\CaptchaHelper;
use App\IpHelper;
use App\GeoHelper;
use App\TelegramHelper;
use App\ImagenHelper;
use App\CaptchaPropio;


$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json');

const CAPTCHA_VIGENCIA_SEGUNDOS = 900; // 15 minutos: alcanza para recargar la tabla tras un alta/edición/baja sin volver a pedir captcha

$metodo = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segmentos = explode('/', trim($uri, '/'));

// GET /personas/5
if ($segmentos[0] === 'personas' && isset($segmentos[1]) && $metodo === 'GET') {
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

        $fechaNacimiento = strtotime($_POST['fecha_nacimiento']);

    if ($fechaNacimiento === false) {
        http_response_code(400);
        echo json_encode(['error' => 'La fecha de nacimiento no es válida']);
        exit;
    }

    if ($fechaNacimiento > time()) {
        http_response_code(400);
        echo json_encode(['error' => 'La fecha de nacimiento no puede ser futura']);
        exit;
    }

    // Una fecha anterior a 1900 no corresponde a una persona viva: se descarta
    // para evitar datos incoherentes en la base.
    if ($fechaNacimiento < strtotime('1900-01-01')) {
        http_response_code(400);
        echo json_encode(['error' => 'La fecha de nacimiento no puede ser anterior a 1900']);
        exit;
    }

    if (!preg_match('/^[\p{L}\s]+$/u', $_POST['nombres']) || !preg_match('/^[\p{L}\s]+$/u', $_POST['apellidos'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Nombres y apellidos sólo pueden contener letras']);
        exit;
    }

    if (!ctype_digit($_POST['nro_documento'])) {
        http_response_code(400);
        echo json_encode(['error' => 'El número de documento sólo puede contener números']);
        exit;
    }

    if (mb_strlen($_POST['nro_documento']) < 5) {
        http_response_code(400);
        echo json_encode(['error' => 'El documento debe tener al menos 5 caracteres']);
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
} elseif ($segmentos[0] === 'personas' && isset($segmentos[1]) && $metodo === 'POST' && ($_POST['_method'] ?? '') === 'PUT') {
    $id = (int) $segmentos[1];

    if (empty($_POST['nombres']) || empty($_POST['apellidos']) || empty($_POST['fecha_nacimiento'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios']);
        exit;
    }

    if (!preg_match('/^[\p{L}\s]+$/u', $_POST['nombres']) || !preg_match('/^[\p{L}\s]+$/u', $_POST['apellidos'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Nombres y apellidos sólo pueden contener letras']);
        exit;
    }
        

    if (!Persona::buscarPorId($id)) {
        http_response_code(404);
        echo json_encode(['error' => 'Persona no encontrada']);
        exit;
    }

    $fechaEditada = strtotime($_POST['fecha_nacimiento']);
    if ($fechaEditada === false || $fechaEditada > time() || $fechaEditada < strtotime('1900-01-01')) {
        http_response_code(400);
        echo json_encode(['error' => 'La fecha de nacimiento no es válida']);
        exit;
    }

    $datos = [
        'nombres' => $_POST['nombres'],
        'apellidos' => $_POST['apellidos'],
        'fecha_nacimiento' => $_POST['fecha_nacimiento'],
    ];

    try {
        if (!empty($_FILES['foto_frente']['name'])) {
            $datos['foto_frente'] = ImagenHelper::procesar($_FILES['foto_frente']);
        }
        if (!empty($_FILES['foto_dorso']['name'])) {
            $datos['foto_dorso'] = ImagenHelper::procesar($_FILES['foto_dorso']);
        }

        $actualizado = Persona::actualizar($id, $datos);
        echo json_encode(['actualizado' => $actualizado]);
    } catch (\RuntimeException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    } 


} elseif ($segmentos[0] === 'personas' && isset($segmentos[1]) && $metodo === 'PUT') {
    $id = (int) $segmentos[1];
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Cuerpo de la petición inválido']);
        exit;
    }

    if (!Persona::buscarPorId($id)) {
        http_response_code(404);
        echo json_encode(['error' => 'Persona no encontrada']);
        exit;
    }

    if (empty($input['nombres']) || empty($input['apellidos']) || empty($input['fecha_nacimiento'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios']);
        exit;
    }

    if (!preg_match('/^[\p{L}\s]+$/u', $input['nombres']) || !preg_match('/^[\p{L}\s]+$/u', $input['apellidos'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Nombres y apellidos sólo pueden contener letras']);
        exit;
    }

    $fecha = strtotime($input['fecha_nacimiento']);
    if ($fecha === false || $fecha > time() || $fecha < strtotime('1900-01-01')) {
        http_response_code(400);
        echo json_encode(['error' => 'La fecha de nacimiento no es válida']);
        exit;
    }

    try {
        $actualizado = Persona::actualizar($id, [
            'nombres' => $input['nombres'],
            'apellidos' => $input['apellidos'],
            'fecha_nacimiento' => $input['fecha_nacimiento'],
        ]);
        echo json_encode(['actualizado' => $actualizado]);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo actualizar la persona']);
    }// DELETE /personas/5

} elseif ($segmentos[0] === 'personas' && isset($segmentos[1]) && $metodo === 'DELETE') {
    $id = (int) $segmentos[1];

    if (!Persona::buscarPorId($id)) {
        http_response_code(404);
        echo json_encode(['error' => 'Persona no encontrada']);
        exit;
    }

    $eliminado = Persona::eliminar($id);
    echo json_encode(['eliminado' => $eliminado]);

} elseif ($uri === '/buscar' && $metodo === 'GET') {
    //$termino = trim($_GET['q'] ?? '');
    $filtro = $_GET['filtro'] ?? 'todos';
    $nombre = trim($_GET['nombre'] ?? '');
    $apellido = trim($_GET['apellido'] ?? '');
    $documento = trim($_GET['documento'] ?? '');
    $token = $_GET['captcha_token'] ?? '';
    $pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;

    $ip = IpHelper::obtenerIpReal();
    $sesionVerificada = ($_SESSION['captcha_verificado_hasta'] ?? 0) >= time();

    if (!$sesionVerificada) {
        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta la verificación captcha']);
            exit;
        }

        if (!CaptchaHelper::validar($token, $ip)) {
            http_response_code(403);
            echo json_encode(['error' => 'Verificación captcha inválida']);
            exit;
        }
    }

    $_SESSION['captcha_verificado_hasta'] = time() + CAPTCHA_VIGENCIA_SEGUNDOS;

    if ($filtro === 'nombre' && mb_strlen($nombre) < 2 && mb_strlen($apellido) < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'El término de búsqueda debe tener al menos 2 caracteres']);
        exit;
    }

    if ($filtro === 'documento' && mb_strlen($documento) < 5) {
        http_response_code(400);
        echo json_encode(['error' => 'El documento debe tener al menos 5 caracteres']);
        exit;
    }

    if ($filtro === 'documento' && !ctype_digit($documento)) {
        http_response_code(400);
        echo json_encode(['error' => 'El documento sólo puede contener números']);
        exit;
    }

    $resultados = Persona::buscarConFiltro($documento, $filtro, $nombre, $apellido, $pagina);
    $totalResultados = Persona::contarConFiltro($documento, $nombre, $apellido, $filtro);

    $geo = GeoHelper::obtenerInfo($ip);
    $mensajeTelegram = sprintf(
        "-- Nueva búsqueda\nDominio: %s\nMétodo: %s\nEndpoint: %s\nFiltro usado: %s\nResultados: %d\nOrigen: %s, %s",
        $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? 'desconocido',
        $metodo,
        $uri,
        $filtro,
        $totalResultados,
        $geo['ciudad'] ?? 'desconocido',
        $geo['pais'] ?? 'desconocido'
    );
    $telegramOk = TelegramHelper::notificar($mensajeTelegram);

    if ($filtro === 'documento') {
        $terminoAuditado = $documento;
    } elseif($filtro === 'nombre') {
        $terminoAuditado = trim($nombre . ' ' . $apellido);
    } else {
        $terminoAuditado = '(listado completo)';
    }

    Auditoria::registrar([
        'termino' => $terminoAuditado,
        'cantidad_resultados' => $totalResultados,
        'ip_origen' => $ip,
        'geo_pais' => $geo['pais'] ?? null,
        'geo_ciudad' => $geo['ciudad'] ?? null,
        'geo_proveedor' => $geo['proveedor'] ?? null,
        'geo_lat' => $geo['lat'] ?? null,
        'geo_lon' => $geo['lon'] ?? null,
        'telegram_enviado' => $telegramOk ? 1 : 0,
    ]);

    echo json_encode(['data' => $resultados, 'total' => $totalResultados, 'pagina' => $pagina]); 
    
} elseif (preg_match('#^/cedulas/([a-zA-Z0-9_-]+\.(jpg|png|webp))$#', $uri, $matches) && $metodo === 'GET') {
    $archivo = __DIR__ . '/../storage/cedulas/' . $matches[1];

    if (!file_exists($archivo)) {
        http_response_code(404);
        exit;
    }

    $extension = pathinfo($archivo, PATHINFO_EXTENSION);
    $mime = match ($extension) {
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    };

    header('Content-Type: ' . $mime);
    readfile($archivo);

} elseif ($uri === '/auditoria' && $metodo === 'GET') {
    //verificar captcha antes de permitir ver la auditoría.
    if (($_SESSION['captcha_verificado_hasta'] ?? 0) < time()) {
        http_response_code(403);
        echo json_encode(['error' => 'Verificación captcha requerida o expirada']);
        exit;
    }

    $pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
    echo json_encode(['data' => Auditoria::listar($pagina)]);
} elseif ($uri === '/personas-refrescar' && $metodo === 'GET') {
    if (($_SESSION['captcha_verificado_hasta'] ?? 0) < time()) {
        http_response_code(403);
        echo json_encode(['error' => 'Verificación captcha requerida o expirada']);
        exit;
    }

    $filtro = $_GET['filtro'] ?? 'todos';
    $nombre = trim($_GET['nombre'] ?? '');
    $apellido = trim($_GET['apellido'] ?? '');
    $documento = trim($_GET['documento'] ?? '');
    $pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;

    $resultados = Persona::buscarConFiltro($documento, $filtro, $nombre, $apellido, $pagina);
    $totalResultados = Persona::contarConFiltro($documento, $nombre, $apellido, $filtro);

    echo json_encode(['data' => $resultados, 'total' => $totalResultados, 'pagina' => $pagina]);

} elseif ($uri === '/captcha/generar' && $metodo === 'GET') {
    echo json_encode(CaptchaPropio::generar());

} elseif ($uri === '/captcha/validar' && $metodo === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input) || empty($input['desafio_id']) || !isset($input['x'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos de verificación incompletos']);
        exit;
    }

    if (!CaptchaPropio::validar($input['desafio_id'], (int) $input['x'], $input['traza'] ?? [])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Verificación incorrecta']);
        exit;
    }

    $_SESSION['captcha_verificado_hasta'] = time() + CAPTCHA_VIGENCIA_SEGUNDOS;

    echo json_encode(['ok' => true]);

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Ruta no encontrada']);
}