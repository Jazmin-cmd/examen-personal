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

    if (strtotime($_POST['fecha_nacimiento']) > time()) {
        http_response_code(400);
        echo json_encode(['error' => 'La fecha de nacimiento no puede ser futura']);
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

    if (!preg_match('/^[\p{L}\s]+$/u', $_POST['nombres']) || !preg_match('/^[\p{L}\s]+$/u', $_POST['apellidos'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Nombres y apellidos sólo pueden contener letras']);
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
    $input = json_decode(file_get_contents('php://input'), true);
    $actualizado = Persona::actualizar((int) $segmentos[1], $input);
    echo json_encode(['actualizado' => $actualizado]);

// DELETE /personas/5
} elseif ($segmentos[0] === 'personas' && isset($segmentos[1]) && $metodo === 'DELETE') {
    $eliminado = Persona::eliminar((int) $segmentos[1]);
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

    // Los tokens de Turnstile son de un solo uso: no se le puede volver a pedir a Cloudflare
    // que valide el mismo token al paginar o repaginar la misma búsqueda. Si la sesión ya fue
    // verificada recientemente (ver CAPTCHA_VIGENCIA_SEGUNDOS), no se exige un token nuevo.
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

    Auditoria::registrar([
        'termino' => $documento ?: '(listado completo)',
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


} else {
    http_response_code(404);
    echo json_encode(['error' => 'Ruta no encontrada']);
}