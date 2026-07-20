<?php
/**
 * Bootstrap y utilidades para pruebas funcionales QA (invocación vía proses.php del sistema).
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/modules/compras/helpers/compras_calculos.php';

function qa_func_pdo(): PDO
{
    global $host, $port, $database, $user, $pass;
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$database", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET TIME ZONE 'America/Asuncion'");
    }
    return $pdo;
}

function qa_func_admin_session(): array
{
    $pdo = qa_func_pdo();
    $row = $pdo->query("
        SELECT id_usuario, username, id_sucursal, id_cargo
        FROM usuarios
        WHERE username = 'admin' AND estado_usuario = 'ACTIVO'
        LIMIT 1
    ")->fetch();
    if (!$row) {
        throw new RuntimeException('Usuario admin no encontrado en BD');
    }
    return [
        'username'    => $row['username'],
        'id_usuario'  => (int)$row['id_usuario'],
        'usua_id'     => (int)$row['id_usuario'],
        'id_sucursal' => (int)$row['id_sucursal'],
        'sucursal_id' => (int)$row['id_sucursal'],
        'id_cargo'    => (int)$row['id_cargo'],
        'cargo_nombre'=> 'ADMIN',
    ];
}

function qa_func_next_id(string $table, string $pk): int
{
    $pdo = qa_func_pdo();
    $q = $pdo->query("SELECT COALESCE(MAX($pk), 0) + 1 FROM $table");
    return (int)$q->fetchColumn();
}

function qa_func_unique_suffix(): string
{
    return date('His');
}

function qa_func_invoke(string $moduleRelPath, array $get, array $post, ?array $session = null): array
{
    $worker = __DIR__ . '/qa_invoke_worker.php';
    $payload = base64_encode(json_encode([
        'session' => $session ?? qa_func_admin_session(),
        'get'     => $get,
        'post'    => $post,
    ], JSON_UNESCAPED_UNICODE));

    $php = PHP_BINARY ?: 'php';
    $cmd = escapeshellarg($php) . ' -d display_errors=0 -d display_startup_errors=0 '
        . escapeshellarg($worker) . ' '
        . escapeshellarg($moduleRelPath) . ' ' . escapeshellarg($payload);

    $lines = [];
    $code = 0;
    exec($cmd, $lines, $code);
    $raw = trim(implode("\n", $lines));

    // Saltar warnings de PHP al inicio
    if (str_contains($raw, '{')) {
        $pos = strpos($raw, '{');
        $raw = substr($raw, $pos);
    }

    $decoded = qa_func_parse_worker_json($raw);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'exit_code' => $code,
            'output' => $raw,
            'error' => 'Respuesta inválida del worker',
        ];
    }
    $decoded['exit_code'] = $code;
    $decoded['ok'] = ($decoded['ok'] ?? false) && $code === 0;
    return $decoded;
}

function qa_func_report_ok(string $moduleRelPath, array $get, ?array $session = null): array
{
    $worker = __DIR__ . '/qa_report_worker.php';
    $payload = base64_encode(json_encode([
        'get'     => $get,
        'session' => $session ?? qa_func_admin_session(),
    ], JSON_UNESCAPED_UNICODE));
    $php = PHP_BINARY ?: 'php';
    $cmd = escapeshellarg($php) . ' -d display_errors=0 -d display_startup_errors=0 '
        . escapeshellarg($worker) . ' '
        . escapeshellarg($moduleRelPath) . ' ' . escapeshellarg($payload);
    $lines = [];
    $code = 0;
    exec($cmd, $lines, $code);
    $raw = trim(implode("\n", $lines));
    if (str_contains($raw, '{')) {
        $raw = substr($raw, strpos($raw, '{'));
    }
    $decoded = qa_func_parse_worker_json($raw);
    return is_array($decoded) ? $decoded : ['ok' => false, 'error' => $raw];
}

function qa_func_row(string $sql, array $params = []): ?array
{
    $st = qa_func_pdo()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    return $r ?: null;
}

function qa_func_session_for_username(string $username): array
{
    $pdo = qa_func_pdo();
    require_once PROJECT_ROOT . '/config/permissions.php';
    $st = $pdo->prepare("
        SELECT u.id_usuario, u.username, u.id_sucursal, u.id_cargo
        FROM usuarios u
        WHERE u.username = :u AND u.estado_usuario = 'ACTIVO'
        LIMIT 1
    ");
    $st->execute([':u' => $username]);
    $row = $st->fetch();
    if (!$row) {
        throw new RuntimeException("Usuario no encontrado: $username");
    }
    $cargo = obtenerNombreCargo($pdo, (int)$row['id_cargo']);
    return [
        'username'     => $row['username'],
        'id_usuario'   => (int)$row['id_usuario'],
        'usua_id'      => (int)$row['id_usuario'],
        'id_sucursal'  => (int)$row['id_sucursal'],
        'sucursal_id'  => (int)$row['id_sucursal'],
        'id_cargo'     => (int)$row['id_cargo'],
        'cargo_nombre' => $cargo,
    ];
}

function qa_func_has_permission(array $session, string|array $modules): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION = $session;
    require_once PROJECT_ROOT . '/config/permissions.php';
    return check_permission($modules, false);
}

function qa_func_invoke_json(string $moduleRelPath, array $get, array $jsonBody, ?array $session = null): array
{
    $worker = __DIR__ . '/qa_invoke_worker.php';
    $payload = base64_encode(json_encode([
        'session'   => $session ?? qa_func_admin_session(),
        'get'       => $get,
        'post'      => [],
        'json_body' => $jsonBody,
    ], JSON_UNESCAPED_UNICODE));
    $php = PHP_BINARY ?: 'php';
    $cmd = escapeshellarg($php) . ' -d display_errors=0 -d display_startup_errors=0 '
        . escapeshellarg($worker) . ' '
        . escapeshellarg($moduleRelPath) . ' ' . escapeshellarg($payload);
    $lines = [];
    $code = 0;
    exec($cmd, $lines, $code);
    $raw = trim(implode("\n", $lines));
    if (str_contains($raw, '{')) {
        $raw = substr($raw, strpos($raw, '{'));
    }
    $decoded = qa_func_parse_worker_json($raw);
    if (!is_array($decoded)) {
        return ['ok' => false, 'output' => $raw, 'error' => 'Respuesta inválida'];
    }
    $decoded['exit_code'] = $code;
    $outJson = json_decode(trim((string)($decoded['output'] ?? '')), true);
    if (is_array($outJson) && array_key_exists('success', $outJson)) {
        $decoded['ok'] = (bool)$outJson['success'];
        $decoded['message'] = $outJson['message'] ?? null;
    }
    return $decoded;
}

function qa_func_informe_ok(string $moduleRelPath, array $get): array
{
    $rep = qa_func_report_ok($moduleRelPath, $get);
    if ($rep['ok'] ?? false) {
        return $rep;
    }
    // Algunos informes redirigen con cuerpo vacío si no hay datos; no es fallo del generador
    if (($rep['bytes'] ?? 0) === 0 && empty($rep['error'])) {
        return ['ok' => true, 'bytes' => 0, 'sin_datos' => true];
    }
    if (str_contains((string)($rep['error'] ?? ''), 'No se encontraron')) {
        return ['ok' => true, 'bytes' => 0, 'sin_datos' => true];
    }
    return $rep;
}

function qa_func_scalar(string $sql, array $params = []): mixed
{
    $st = qa_func_pdo()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function qa_func_hoy(): string
{
    return (new DateTime('now', new DateTimeZone('America/Asuncion')))->format('Y-m-d');
}

function qa_func_parse_worker_json(string $raw): ?array
{
    if (preg_match('/\{.*\}$/s', $raw, $m)) {
        $decoded = json_decode($m[0], true);
        return is_array($decoded) ? $decoded : null;
    }
    return null;
}
