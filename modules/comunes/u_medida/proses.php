<?php
// Procesa altas, ediciones y anulaciones de unidades de medida (?act=).
require_once __DIR__ . '/../../../config/bootstrap.php';
session_start();
require_once PROJECT_ROOT . '/config/database.php';

if (empty($_SESSION['username'])) {
    header('Location: ' . loginUrl());
    exit();
}

$idUsuario = (int)($_SESSION['id_usuario'] ?? 0);

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($idUsuario <= 0) {
        $stUser = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1');
        $stUser->execute([':u' => $_SESSION['username']]);
        $idUsuario = (int)$stUser->fetchColumn();
    }

    if (!isset($_GET['act'])) {
        header('Location: view.php?alert=4');
        exit();
    }

    $action = $_GET['act'];

    if ($action === 'insert' && isset($_POST['Guardar'])) {
        $codigo = (int)($_POST['codigo'] ?? 0);
        $descripcion = trim($_POST['descrip_umedida'] ?? '');
        if ($codigo <= 0 || $descripcion === '' || $idUsuario <= 0) {
            header('Location: view.php?alert=4');
            exit();
        }

        $query = $pdo->prepare('INSERT INTO unidad_medida (id_unidad, unidad_descri, id_usuario) VALUES (:codigo, :descripcion, :usuario)');
        $query->execute([
            ':codigo' => $codigo,
            ':descripcion' => $descripcion,
            ':usuario' => $idUsuario,
        ]);
        header('Location: view.php?alert=1');
        exit();
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $codigo = (int)($_POST['codigo'] ?? 0);
        $descripcion = trim($_POST['unidad_descri'] ?? '');
        if ($codigo <= 0 || $descripcion === '') {
            header('Location: view.php?alert=4');
            exit();
        }

        $query = $pdo->prepare('UPDATE unidad_medida SET unidad_descri = :descripcion WHERE id_unidad = :codigo');
        $query->execute([
            ':codigo' => $codigo,
            ':descripcion' => $descripcion,
        ]);
        header('Location: view.php?alert=2');
        exit();
    }

    if ($action === 'delete' && isset($_GET['id'])) {
        $codigo = (int)$_GET['id'];
        if ($codigo <= 0) {
            header('Location: view.php?alert=4');
            exit();
        }

        $query = $pdo->prepare('DELETE FROM unidad_medida WHERE id_unidad = :codigo');
        $query->execute([':codigo' => $codigo]);
        header('Location: view.php?alert=3');
        exit();
    }

    header('Location: view.php?alert=4');
} catch (PDOException $e) {
    header('Location: view.php?alert=4');
    exit();
}
