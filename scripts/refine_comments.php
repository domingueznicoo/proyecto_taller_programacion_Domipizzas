<?php
/**
 * Normaliza comentarios en modules/{compras,produccion,comunes}:
 * quita ruido obvio y agrega una línea de propósito por archivo.
 */
$root = realpath(__DIR__ . '/..');

$labels = [
    'pedido_compra' => 'pedidos de compra',
    'presupuesto' => 'presupuestos de compra',
    'orden_compra' => 'órdenes de compra',
    'gestionar_compras' => 'facturas de compra',
    'nota_credito' => 'notas de crédito/débito de compra',
    'nota_remision' => 'notas de remisión',
    'ajustes' => 'ajustes de stock',
    'libro_compras' => 'libro de compras',
    'proveedor' => 'proveedores',
    'materia_prima' => 'materias primas',
    'pedido_produccion' => 'pedidos de producción',
    'orden_produccion' => 'órdenes de producción',
    'control_produccion' => 'control de producción',
    'etapa_produccion' => 'etapas de producción',
    'equipos_produccion' => 'equipos de trabajo',
    'productos_terminados' => 'productos terminados',
    'control_calidad' => 'control de calidad',
    'perdidas' => 'pérdidas de producción',
    'pedido_materia_produccion' => 'pedidos de materia prima',
    'reposicion_materia' => 'reposición de materia prima',
    'costos_produccion' => 'costos de producción',
    'trabajadores' => 'trabajadores',
    'parametros_control' => 'parámetros de inspección',
    'inspectores' => 'inspectores',
    'reportes' => 'informes',
    'usuario' => 'usuarios',
    'reset_password' => 'cambio de contraseña',
    'perfil' => 'perfil de usuario',
    'stock' => 'stock',
    'producto' => 'productos',
    'clientes' => 'clientes',
    'deposito' => 'depósitos',
    'ciudad' => 'ciudades',
    'departamento' => 'departamentos',
    'tipo_producto' => 'marcas/tipos de producto',
    'u_medida' => 'unidades de medida',
];

$noise = [
    "// Iniciar la sesión\n",
    "// Iniciar la sesion\n",
    "// BASE_PATH desde bootstrap\n",
    "// Configuración para el layout común\n",
    "// Configuracion para el layout comun\n",
    "// Conexión a la base de datos\n",
    "// Conexion a la base de datos\n",
    "// Crear conexión con PostgreSQL usando PDO\n",
    "// Crear conexion con PostgreSQL usando PDO\n",
    "// Verificar si el usuario está autenticado\n",
    "// Verificar si la sesión es válida\n",
    "// Verificar si la sesion es valida\n",
    "// Detectar la acción\n",
    "// Detectar la accion\n",
    "// Conectar a la base de datos\n",
    "// Obtener el nombre de usuario de la sesión\n",
    "// Variables para el sidebar (necesarias para header.php)\n",
    "// Incluir header común\n",
    "// Incluir header comun\n",
    "// Contenido específico del módulo\n",
    "// Contenido especifico del modulo\n",
    "require_once PROJECT_ROOT . '/config/database.php'; // Asegúrate de tener la conexión a la base de datos\n",
    "require_once PROJECT_ROOT . '/config/database.php'; // Asegurate de tener la conexion a la base de datos\n",
];

function purposeLine(string $modulo, string $file, array $labels): ?string
{
    $name = $labels[$modulo] ?? str_replace('_', ' ', $modulo);
    $base = pathinfo($file, PATHINFO_FILENAME);

    if ($file === 'view.php') {
        return "// Vista: listado y formulario de {$name}.";
    }
    if ($file === 'proses.php') {
        return "// Procesa altas, ediciones y anulaciones de {$name} (?act=).";
    }
    if ($file === 'form.php') {
        return "// Formulario de {$name} (alta o edición).";
    }
    if ($file === 'reporte.php' || str_starts_with($file, 'generar_informe')) {
        return "// Genera informe PDF de {$name}.";
    }
    if (str_starts_with($file, 'exportar_')) {
        return "// Exporta datos de {$name} (" . pathinfo($file, PATHINFO_FILENAME) . ").";
    }
    if (str_starts_with($file, 'get_') || str_starts_with($file, 'ajax_')) {
        $accion = str_replace(['get_', 'ajax_', '_'], ['', '', ' '], $base);
        return "// AJAX: {$accion} de {$name}.";
    }
    if (str_starts_with($file, 'anular_')) {
        return "// Anula un registro de {$name} si cumple validaciones.";
    }
    if (str_ends_with($file, '_helper.php')) {
        return "// Funciones compartidas del módulo {$name}.";
    }
    if ($file === 'router_informes.php') {
        return '// Enruta la generación de informes por módulo.';
    }
    if ($file === 'informes_helper.php') {
        return '// Permisos y utilidades del módulo de informes.';
    }
    if ($file === 'generador_produccion.php') {
        return '// Genera informes PDF del área de producción.';
    }
    if ($file === 'require_auth.php') {
        return '// Verifica sesión antes de exportar libro de compras.';
    }
    if (in_array($file, ['delete_detalle.php', 'validar_timbrado.php', 'validar_stock_maximo.php'], true)) {
        return "// Operación auxiliar de {$name}.";
    }
    return null;
}

$domains = ['compras', 'produccion', 'comunes'];
$changed = 0;

foreach ($domains as $domain) {
    $dir = $root . '/modules/' . $domain;
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
        $parts = explode('/', $rel);
        if (count($parts) < 4) {
            continue;
        }
        $modulo = $parts[2];
        $filename = $parts[3];
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }
        $original = $content;

        foreach ($noise as $n) {
            $content = str_replace($n, '', $content);
            $content = str_replace(str_replace("\n", "\r\n", $n), '', $content);
        }
        $content = preg_replace('/\/\/ Validar permisos de acceso\r?\n/', '', $content);
        $content = preg_replace('/\/\/ Verificar si el usuario está autenticado\r?\n/', '', $content);
        $content = preg_replace('/\/\/ Verificar si la sesión es válida\r?\n/', '', $content);
        $content = preg_replace('/\/\/ Detectar la acción\r?\n/', '', $content);
        $content = preg_replace('/\/\/ Conectar a la base de datos\r?\n/', '', $content);
        $content = preg_replace('/require_once PROJECT_ROOT \. \'\/config\/database\.php\'; \/\/ Asegúrate de tener la conexión a la base de datos\r?\n/', "require_once PROJECT_ROOT . '/config/database.php';\n", $content);
        $content = preg_replace('/\/\/ Verificar si existe el parámetro \'form\' en la URL\r?\n/', "// Muestra el formulario según parámetros GET (form/add o form/edit).\n", $content);
        $content = preg_replace('/\/\/ Verificar si se encontraron datos del usuario\r?\n/', '', $content);

        $purpose = purposeLine($modulo, $filename, $labels);
        if ($purpose && !preg_match('/^<\?php\s*\n\/\/ (Vista|Procesa|Formulario|Genera|Exporta|AJAX|Anula|Funciones|Enruta|Permisos|Operación|Verifica)/m', $content)) {
            $content = preg_replace('/^<\?php\s*/', "<?php\n{$purpose}\n", $content, 1);
        }

        // Comentarios de sección en proses: más directos
        $content = str_replace('// Insertar un nuevo pedido con sus detalles', '// Alta: registra cabecera y detalle', $content);
        $content = str_replace('// Insertar unidad de medida', '// Alta de unidad de medida', $content);
        $content = str_replace('// Actualizar unidad de medida', '// Edición de unidad de medida', $content);
        $content = str_replace('// Eliminar unidad de medida', '// Baja de unidad de medida', $content);

        if ($content !== $original) {
            file_put_contents($path, $content);
            $changed++;
        }
    }
}

// Config y raíz
$extra = [
    $root . '/config/bootstrap.php' => '// Carga PROJECT_ROOT, rutas y registro de módulos.',
    $root . '/config/paths.php' => '// Calcula rutas relativas (BASE_PATH, login) según profundidad del script.',
    $root . '/config/permissions.php' => '// Permisos por cargo y función check_permission().',
    $root . '/config/modules_registry.php' => '// Mapa de carpetas de módulos y helper moduloUrl().',
    $root . '/config/database.php' => '// Credenciales y conexión PDO a PostgreSQL.',
    $root . '/auth/login.php' => '// Valida usuario y crea la sesión.',
    $root . '/auth/session_handler.php' => '// Mantiene activa la sesión del usuario.',
    $root . '/header.php' => '// Layout superior, menú lateral y control de acceso.',
    $root . '/footer.php' => '// Cierra layout y carga scripts del tema.',
];

foreach ($extra as $path => $comment) {
    if (!is_file($path)) {
        continue;
    }
    $content = file_get_contents($path);
    if ($content === false || preg_match('/^<\?php\s*\n\/\//m', $content)) {
        continue;
    }
    $content = preg_replace('/^<\?php\s*/', "<?php\n{$comment}\n", $content, 1);
    file_put_contents($path, $content);
    $changed++;
}

echo "Comentarios refinados en {$changed} archivos.\n";
