<?php
// Cookie persistente: token aleatorio en navegador, únicamente su hash en PostgreSQL.
declare(strict_types=1);

function remember_cookie(?string $token, int $expires = 0): void
{
    setcookie('domipizzas_remember', $token ?? '', [
        'expires' => $token === null ? time() - 3600 : $expires,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if ($token === null) {
        unset($_COOKIE['domipizzas_remember']);
    }
}

function remember_revoke(PDO $pdo): void
{
    $token = $_COOKIE['domipizzas_remember'] ?? '';
    if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        $stmt = $pdo->prepare('DELETE FROM sesion_recordada WHERE token_hash = :hash');
        $stmt->execute([':hash' => hash('sha256', $token)]);
    }
    remember_cookie(null);
}

function remember_issue(PDO $pdo, int $usuarioId): void
{
    remember_revoke($pdo);
    $token = bin2hex(random_bytes(32));
    $expires = time() + 30 * 24 * 60 * 60;
    $stmt = $pdo->prepare("INSERT INTO sesion_recordada (usuario_id, token_hash, fecha_vencimiento)
        VALUES (:usuario, :hash, to_timestamp(:vence))");
    $stmt->execute([':usuario' => $usuarioId, ':hash' => hash('sha256', $token), ':vence' => $expires]);
    remember_cookie($token, $expires);
}

function remember_restore(): void
{
    if (!empty($_SESSION['username']) || empty($_COOKIE['domipizzas_remember'])) {
        return;
    }
    $token = $_COOKIE['domipizzas_remember'];
    if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        remember_cookie(null);
        return;
    }
    try {
        // La configuración crea $pdo dentro del ámbito de esta función.
        require __DIR__ . '/database.php';
        $stmt = $pdo->prepare("SELECT usuario.usuario_id, usuario.username, usuario.cargo_id, usuario.modulo_id
            FROM sesion_recordada
            JOIN usuario ON usuario.usuario_id = sesion_recordada.usuario_id
            WHERE sesion_recordada.token_hash = :hash
              AND sesion_recordada.fecha_vencimiento > CURRENT_TIMESTAMP
              AND usuario.usuario_estado = 'ACTIVO'");
        $stmt->execute([':hash' => hash('sha256', $token)]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario) {
            remember_revoke($pdo);
            return;
        }
        require_once __DIR__ . '/permissions.php';
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int)$usuario['usuario_id'];
        $_SESSION['usua_id'] = (int)$usuario['usuario_id'];
        $_SESSION['id_usuario'] = (int)$usuario['usuario_id'];
        $_SESSION['username'] = $usuario['username'];
        $_SESSION['id_cargo'] = (int)$usuario['cargo_id'];
        $_SESSION['modulo_id'] = (int)$usuario['modulo_id'];
        $_SESSION['cargo_nombre'] = obtenerNombreCargo($pdo, (int)$usuario['cargo_id']);
        require_once __DIR__ . '/bitacora_helper.php';
        bitacora_registrar($pdo, (int)$usuario['usuario_id'], 'LOGIN', 'ACCESO_EXITOSO',
            'Sesión restaurada mediante token. Usuario: ' . $usuario['username']);
    } catch (Throwable $e) {
        error_log('remember_restore: ' . $e->getMessage());
        remember_cookie(null);
    }
}
