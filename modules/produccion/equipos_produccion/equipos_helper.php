<?php
// Funciones compartidas del módulo equipos de trabajo.
require_once __DIR__ . '/../../../config/bootstrap.php';
/**
 * Helpers — equipos de trabajo por orden / etapa.
 */

function bitacoraEquipo(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'equipos produccion',
            ':id_registro' => $idRegistro,
            ':accion' => strtoupper($accion),
            ':descripcion' => $descripcion,
        ]);
    } catch (Throwable $e) {
        error_log('Bitácora falló: ' . $e->getMessage());
    }
}

function resolverUsuarioEquipo(PDO $pdo): array
{
    $usuarioId = (int)($_SESSION['usua_id'] ?? $_SESSION['id_usuario'] ?? 0);
    $sucursalId = (int)($_SESSION['sucursal_id'] ?? $_SESSION['id_sucursal'] ?? 0);

    if ($usuarioId > 0 && $sucursalId === 0) {
        $q = $pdo->prepare('SELECT id_sucursal FROM usuarios WHERE id_usuario = :id LIMIT 1');
        $q->execute([':id' => $usuarioId]);
        $sucursalId = (int)$q->fetchColumn();
    }

    if ($usuarioId === 0) {
        $q = $pdo->prepare('SELECT id_usuario, id_sucursal FROM usuarios WHERE username = :u LIMIT 1');
        $q->execute([':u' => $_SESSION['username'] ?? '']);
        $usr = $q->fetch(PDO::FETCH_ASSOC);
        if ($usr) {
            $usuarioId = (int)$usr['id_usuario'];
            $sucursalId = (int)$usr['id_sucursal'];
        }
    }

    return [$usuarioId, $sucursalId];
}

function columnaEquipoExiste(PDO $pdo, string $columna): bool
{
    static $cache = [];
    if (isset($cache[$columna])) {
        return $cache[$columna];
    }
    $st = $pdo->prepare("
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'equipos_produccion'
          AND column_name = :col
        LIMIT 1
    ");
    $st->execute([':col' => $columna]);
    $cache[$columna] = (bool)$st->fetchColumn();
    return $cache[$columna];
}

function opPermiteAltaEquipo(string $estadoOp): bool
{
    $e = strtoupper(trim($estadoOp));
    return in_array($e, ['PENDIENTE', 'EN_PROCESO'], true);
}

function opPermiteEdicionEquipo(string $estadoOp): bool
{
    $e = strtoupper(trim($estadoOp));
    return in_array($e, ['PENDIENTE', 'EN_PROCESO'], true);
}

function parseMiembrosEquipo(array $items): array
{
    $lineas = [];
    foreach ($items as $item) {
        $tid = (int)($item['trabajadores_id'] ?? 0);
        $rol = trim((string)($item['tarea_rol'] ?? ''));
        if ($tid <= 0 || $rol === '') {
            continue;
        }
        if (isset($lineas[$tid])) {
            continue;
        }
        $lineas[$tid] = [
            'trabajadores_id' => $tid,
            'tarea_rol' => substr($rol, 0, 100),
        ];
    }
    return array_values($lineas);
}

function validarTrabajadoresActivos(PDO $pdo, array $miembros): ?string
{
    foreach ($miembros as $m) {
        $st = $pdo->prepare("
            SELECT COUNT(*) FROM trabajadores
            WHERE trabajadores_id = :id AND UPPER(TRIM(trabajador_estado)) = 'ACTIVO'
        ");
        $st->execute([':id' => $m['trabajadores_id']]);
        if ((int)$st->fetchColumn() === 0) {
            return 'Uno o más trabajadores no están activos.';
        }
    }
    return null;
}

function validarTrabajadoresEtapa(PDO $pdo, array $miembros, int $etapaId, string $etapaNombre = ''): ?string
{
    if ($etapaId <= 0) {
        return 'Etapa no válida.';
    }
    foreach ($miembros as $m) {
        $st = $pdo->prepare("
            SELECT id_etapa FROM trabajadores
            WHERE trabajadores_id = :id AND UPPER(TRIM(trabajador_estado)) = 'ACTIVO'
        ");
        $st->execute([':id' => $m['trabajadores_id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)($row['id_etapa'] ?? 0) !== $etapaId) {
            $nom = $etapaNombre !== '' ? " «{$etapaNombre}»" : '';
            return "Cada equipo es para una sola etapa{$nom}. Quite integrantes de otras etapas o cree un equipo por etapa.";
        }
    }
    return null;
}

function cargarDetalleEquipo(PDO $pdo, int $equipoId): array
{
    $st = $pdo->prepare("
        SELECT ed.trabajadores_id, ed.tarea_rol,
               TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS nombre,
               t.trabajador_rol, t.trabajador_turno
        FROM equipo_detalle ed
        JOIN trabajadores t ON t.trabajadores_id = ed.trabajadores_id
        JOIN personal per ON per.id_personal = t.id_personal
        WHERE ed.equipo_id = :id
        ORDER BY ed.tarea_rol, per.personal_apellido
    ");
    $st->execute([':id' => $equipoId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function etapasDeOrden(PDO $pdo, int $ordenId): array
{
    $st = $pdo->prepare("
        SELECT DISTINCT ed.etapa_id, ed.etapa_nombre, ed.etapa_secuencia, ed.producto_id,
               pr.producto_descri AS producto_descripcion
        FROM orden_detalle_produccion od
        JOIN etapa_detalle_produccion ed ON ed.producto_id = od.producto_id
        JOIN etapa_produccion ep ON ep.etapa_id = ed.etapa_id AND ep.producto_id = ed.producto_id
        JOIN productos pr ON pr.producto_id = od.producto_id
        WHERE od.orden_id = :oid
          AND UPPER(TRIM(ep.etapa_estado)) = 'ACTIVA'
        ORDER BY ed.etapa_secuencia, ed.etapa_nombre
    ");
    $st->execute([':oid' => $ordenId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function resolverEtapaOrden(PDO $pdo, int $ordenId, int $etapaId): ?array
{
    foreach (etapasDeOrden($pdo, $ordenId) as $et) {
        if ((int)$et['etapa_id'] === $etapaId) {
            return $et;
        }
    }
    return null;
}

function cargarCabeceraEquipo(PDO $pdo, int $equipoId): ?array
{
    $cols = 'equipo_id, equipo_descri, equipo_estado, equipo_fecha, orden_id, id_usuario, id_sucursal';
    if (columnaEquipoExiste($pdo, 'etapa_id')) {
        $cols .= ', etapa_id, etapa_nombre';
    }
    $st = $pdo->prepare("SELECT {$cols} FROM equipos_produccion WHERE equipo_id = :id");
    $st->execute([':id' => $equipoId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Referencias que impiden anular el equipo.
 *
 * @return string[] mensajes para el usuario
 */
function vinculosEquipo(PDO $pdo, int $equipoId): array
{
    $cab = cargarCabeceraEquipo($pdo, $equipoId);
    if (!$cab) {
        return ['Equipo no encontrado.'];
    }

    $est = strtoupper(trim((string)$cab['equipo_estado']));
    if ($est === 'ACTIVO') {
        return ['El equipo está ACTIVO (en uso en producción) y no puede anularse.'];
    }

    $ordenId = (int)$cab['orden_id'];
    $etapaId = (int)($cab['etapa_id'] ?? 0);
    $etapaNombre = trim((string)($cab['etapa_nombre'] ?? ''));
    $vinculos = [];

    if ($etapaId > 0) {
        $st = $pdo->prepare("
            SELECT control_id FROM control_produccion
            WHERE orden_id = :oid AND etapa_id = :eid
              AND UPPER(TRIM(control_estado)) = 'REGISTRADO'
            LIMIT 1
        ");
        $st->execute([':oid' => $ordenId, ':eid' => $etapaId]);
        $ctrl = $st->fetchColumn();
        if ($ctrl) {
            $vinculos[] = "Control de producción #{$ctrl} registrado para esta etapa.";
        }
    } elseif ($etapaNombre !== '') {
        $st = $pdo->prepare("
            SELECT cp.control_id FROM control_produccion cp
            JOIN control_produccion_detalle cd ON cd.control_id = cp.control_id
            WHERE cp.orden_id = :oid
              AND UPPER(TRIM(cp.control_estado)) = 'REGISTRADO'
              AND UPPER(TRIM(cd.control_descri)) = UPPER(TRIM(:nom))
            LIMIT 1
        ");
        $st->execute([':oid' => $ordenId, ':nom' => $etapaNombre]);
        $ctrl = $st->fetchColumn();
        if ($ctrl) {
            $vinculos[] = "Control de producción #{$ctrl} registrado para esta etapa.";
        }
    }

    $stPt = $pdo->prepare("
        SELECT terminado_id FROM producto_terminado WHERE orden_id = :oid LIMIT 1
    ");
    $stPt->execute([':oid' => $ordenId]);
    $terminadoId = $stPt->fetchColumn();
    if ($terminadoId) {
        $vinculos[] = "Producto terminado (lote #{$terminadoId}) vinculado a la OP.";
    }

    $stCal = $pdo->prepare("
        SELECT cc.calidad_id FROM control_calidad_produccion cc
        JOIN producto_terminado pt ON pt.terminado_id = cc.terminado_id
        WHERE pt.orden_id = :oid
          AND UPPER(TRIM(cc.calidad_estado)) <> 'ANULADO'
        LIMIT 1
    ");
    $stCal->execute([':oid' => $ordenId]);
    $calId = $stCal->fetchColumn();
    if ($calId) {
        $vinculos[] = "Control de calidad #{$calId} vinculado a la OP.";
    }

    return $vinculos;
}

/**
 * Exige equipo PENDIENTE o ACTIVO con integrantes para la OP y etapa antes del control.
 */
function validarEquipoParaControl(PDO $pdo, int $ordenId, int $etapaId, string $etapaNombre = ''): ?string
{
    if ($ordenId <= 0 || $etapaId <= 0) {
        return 'Orden o etapa inválida.';
    }
    if (!columnaEquipoExiste($pdo, 'etapa_id')) {
        return null;
    }

    $st = $pdo->prepare("
        SELECT e.equipo_id,
               (SELECT COUNT(*)::int FROM equipo_detalle ed WHERE ed.equipo_id = e.equipo_id) AS num_trab
        FROM equipos_produccion e
        WHERE e.orden_id = :oid
          AND e.etapa_id = :eid
          AND UPPER(TRIM(e.equipo_estado)) IN ('PENDIENTE', 'ACTIVO')
        ORDER BY e.equipo_id DESC
        LIMIT 1
    ");
    $st->execute([':oid' => $ordenId, ':eid' => $etapaId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $etapaLabel = $etapaNombre !== '' ? " «{$etapaNombre}»" : '';
    if (!$row) {
        return "Debe registrar un equipo de trabajo para la OP #{$ordenId} en la etapa{$etapaLabel} antes de controlar la producción.";
    }
    if ((int)($row['num_trab'] ?? 0) < 1) {
        return "El equipo de trabajo de la etapa{$etapaLabel} no tiene integrantes. Complete el equipo antes de continuar.";
    }

    return null;
}

/**
 * Equipo PENDIENTE/ACTIVO con integrantes para OP + etapa (para mostrar en control de producción).
 */
function obtenerEquipoParaControl(PDO $pdo, int $ordenId, int $etapaId): ?array
{
    if ($ordenId <= 0 || $etapaId <= 0 || !columnaEquipoExiste($pdo, 'etapa_id')) {
        return null;
    }

    $st = $pdo->prepare("
        SELECT e.equipo_id, e.equipo_descri, e.equipo_estado, e.etapa_nombre,
               (SELECT COUNT(*)::int FROM equipo_detalle ed WHERE ed.equipo_id = e.equipo_id) AS num_trab
        FROM equipos_produccion e
        WHERE e.orden_id = :oid
          AND e.etapa_id = :eid
          AND UPPER(TRIM(e.equipo_estado)) IN ('PENDIENTE', 'ACTIVO')
        ORDER BY e.equipo_id DESC
        LIMIT 1
    ");
    $st->execute([':oid' => $ordenId, ':eid' => $etapaId]);
    $cab = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cab || (int)($cab['num_trab'] ?? 0) < 1) {
        return null;
    }

    $miembros = [];
    foreach (cargarDetalleEquipo($pdo, (int)$cab['equipo_id']) as $m) {
        $miembros[] = [
            'nombre' => $m['nombre'],
            'tarea_rol' => $m['tarea_rol'],
            'trabajador_rol' => $m['trabajador_rol'] ?? '',
        ];
    }

    return [
        'equipo_id' => (int)$cab['equipo_id'],
        'equipo_descri' => $cab['equipo_descri'],
        'equipo_estado' => $cab['equipo_estado'],
        'etapa_nombre' => $cab['etapa_nombre'] ?? '',
        'num_trab' => (int)$cab['num_trab'],
        'miembros' => $miembros,
    ];
}

/** Pasa equipos PENDIENTE de la OP/etapa a ACTIVO al registrar control de producción. */
function activarEquiposPorControl(PDO $pdo, int $ordenId, int $etapaId): void
{
    if ($etapaId <= 0) {
        return;
    }
    $sql = "
        UPDATE equipos_produccion
        SET equipo_estado = 'ACTIVO'
        WHERE orden_id = :oid
          AND UPPER(TRIM(equipo_estado)) = 'PENDIENTE'
    ";
    $params = [':oid' => $ordenId];
    if (columnaEquipoExiste($pdo, 'etapa_id')) {
        $sql .= ' AND etapa_id = :eid';
        $params[':eid'] = $etapaId;
    }
    $pdo->prepare($sql)->execute($params);
}
