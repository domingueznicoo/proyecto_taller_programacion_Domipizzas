<?php
// Arrancar sesión solo si no está activa
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/remember_session.php';
remember_restore();

// Validar sesión (username)
if (empty($_SESSION['username'])) {
    require_once __DIR__ . '/config/paths.php';
    header('Location: ' . loginUrl());
    exit;
}

// Incluir bootstrap (rutas dinámicas) y permisos
require_once __DIR__ . '/config/bootstrap.php';
require_once PROJECT_ROOT . '/config/permissions.php';
require_once PROJECT_ROOT . '/config/app_modules.php';

$BASE_PATH = $GLOBALS['BASE_PATH'] ?? computeBasePath();

// Datos para el topbar
$auth_user = [
    'id_usuario' => $_SESSION['usuario_id'] ?? null,
    'username'   => $_SESSION['username']   ?? '',
];

// Obtener cargo normalizado de la sesión
$cargoNombre = $_SESSION['cargo_nombre'] ?? '';
$idCargo = isset($_SESSION['id_cargo']) ? (int)$_SESSION['id_cargo'] : 0;

// Variables para mostrar/ocultar secciones del menú según permisos
$showCompras = has_permission('COMPRAS')
    || has_permission('PEDIDO_COMPRAS')
    || has_permission('PRESUPUESTO_COMPRAS')
    || has_permission('ORDEN_COMPRA')
    || has_permission('FACTURA_COMPRAS')
    || has_permission('NOTAS_COMPRAS')
    || has_permission('LIBRO_COMPRAS')
    || has_permission('AJUSTES');
$showVentas = UI_MODULO_VENTAS && (has_permission('VENTAS') || has_permission('PEDIDO_VENTAS'));
$showProduccion = UI_MODULO_PRODUCCION && (
    has_permission('PRODUCCION')
    || has_permission('PEDIDO_PRODUCCION')
    || has_permission('ORDEN_PRODUCCION')
    || has_permission('CONTROL_PRODUCCION')
    || has_permission('PEDIDO_MATERIA_PRIMA')
    || has_permission('REPOSICION_MATERIA')
);
$showReferenciales = has_permission('REFERENCIALES')
    || has_permission('COMPRAS');
$showInventario = has_permission('INVENTARIO');
$showReportes = has_permission('REPORTES_COMPRAS')
    || has_permission('REPORTES_PRODUCCION')
    || has_permission('REPORTES_GENERALES');
$showAdministracion = has_permission('ADMINISTRACION');
$showNotas = has_permission('NOTAS_COMPRAS');


?>



<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Inicio' ?></title>

    <!-- Custom fonts for this template-->
    <link href="<?= $BASE_PATH ?>vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="<?= $BASE_PATH ?>css/sb-admin-2.min.css" rel="stylesheet">
    
    <!-- Extra CSS files -->
    <?php if (isset($extra_css)): ?>
        <?php foreach ($extra_css as $css): ?>
            <link href="<?= $BASE_PATH ?><?= $css ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>

    

</head>
<style>
    #toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
    }
    .toast-message {
        margin-bottom: 10px;
        padding: 15px 20px;
        color: white; /* Texto blanco */
        background-color: green; /* Fondo verde */
        border-radius: 5px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Sombra para un mejor diseño */
        font-size: 16px;
        animation: fadeInOut 4s ease;
    }
    @keyframes fadeInOut {
        0% { opacity: 0; transform: translateY(-20px); }
        10%, 90% { opacity: 1; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(-20px); }
    }
    .sidebar-icon {
        width: 38px;
        height: 38px;
        margin-right: 8px;
        object-fit: contain;
    }
    .sidebar-brand-text {
        font-weight: 700;
        color: #fff;
        letter-spacing: 1px;
    }
    .sidebar-home-icon {
        width: 22px;
        height: 22px;
        margin-right: 8px;
    }
    .sidebar-manual-icon,
    .sidebar-group-icon,
    .sidebar-compras-icon,
    .sidebar-admin-icon {
        width: 22px;
        height: 22px;
        margin-right: 8px;
    }
    /* Sidebar rojo personalizado */
    .sidebar-red {
        background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%) !important;
    }
    .sidebar-red .sidebar-brand-text {
        color: #fff;
    }
    .sidebar-pizza-icon {
        font-size: 24px;
        color: white;
        margin-right: 10px;
    }
    
    /* Botones principales rojos (crear, guardar, generar, etc.) */
    /* Aplicar rojo a botones principales, excepto los que están en columnas de acciones de tablas */
    .btn-primary {
        background-color: #dc2626 !important;
        border-color: #dc2626 !important;
        color: #fff !important;
    }
    .btn-primary:hover, .btn-primary:focus, .btn-primary.focus {
        background-color: #991b1b !important;
        border-color: #991b1b !important;
        color: #fff !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.5) !important;
    }
    .btn-primary:not(:disabled):not(.disabled):active, 
    .btn-primary:not(:disabled):not(.disabled).active {
        background-color: #7f1d1d !important;
        border-color: #7f1d1d !important;
    }
    
    /* Excepciones: botones de acciones en tablas - mantener colores distintivos */
    /* Botones de editar que usan btn-primary - mantener amarillo */
    table tbody tr td .btn-primary[title="Editar"],
    table tbody tr td .btn-primary[title*="Editar"],
    table tbody tr td:last-child .btn-primary.btn-sm {
        background-color: #f59e0b !important;
        border-color: #f59e0b !important;
    }
    table tbody tr td .btn-primary[title="Editar"]:hover,
    table tbody tr td .btn-primary[title*="Editar"]:hover,
    table tbody tr td:last-child .btn-primary.btn-sm:hover {
        background-color: #d97706 !important;
        border-color: #d97706 !important;
    }
    
    .btn-success {
        background-color: #dc2626 !important;
        border-color: #dc2626 !important;
        color: #fff !important;
    }
    .btn-success:hover, .btn-success:focus, .btn-success.focus {
        background-color: #991b1b !important;
        border-color: #991b1b !important;
        color: #fff !important;
    }
    
    /* Botones de acciones - mantener colores distintivos */
    /* Editar - Amarillo */
    .btn-warning {
        background-color: #f59e0b !important;
        border-color: #f59e0b !important;
        color: #fff !important;
    }
    .btn-warning:hover, .btn-warning:focus, .btn-warning.focus {
        background-color: #d97706 !important;
        border-color: #d97706 !important;
        color: #fff !important;
    }
    
    /* Eliminar/Anular - Rojo oscuro */
    .btn-danger {
        background-color: #ef4444 !important;
        border-color: #ef4444 !important;
        color: #fff !important;
    }
    .btn-danger:hover, .btn-danger:focus, .btn-danger.focus {
        background-color: #dc2626 !important;
        border-color: #dc2626 !important;
        color: #fff !important;
    }
    
    /* Ver Detalle/Historial - Rojo suave */
    .btn-info {
        background-color: #f87171 !important;
        border-color: #f87171 !important;
        color: #fff !important;
    }
    .btn-info:hover, .btn-info:focus, .btn-info.focus {
        background-color: #ef4444 !important;
        border-color: #ef4444 !important;
        color: #fff !important;
    }
    
    /* Cancelar - Gris */
    .btn-secondary {
        background-color: #6b7280 !important;
        border-color: #6b7280 !important;
        color: #fff !important;
    }
    .btn-secondary:hover, .btn-secondary:focus, .btn-secondary.focus {
        background-color: #4b5563 !important;
        border-color: #4b5563 !important;
        color: #fff !important;
    }
    
    /* Paginación */
    .page-link {
        color: #dc2626 !important;
    }
    .page-link:hover {
        color: #991b1b !important;
        background-color: #fee2e2 !important;
        border-color: #dc2626 !important;
    }
    .page-item.active .page-link {
        background-color: #dc2626 !important;
        border-color: #dc2626 !important;
        color: #fff !important;
    }
    
    /* DataTables paginación */
    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: #dc2626 !important;
        border-color: #dc2626 !important;
        color: #fff !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #fee2e2 !important;
        border-color: #dc2626 !important;
        color: #991b1b !important;
    }
</style>



<body id="page-top">

    <div id="toast-container"></div>

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <ul class="navbar-nav sidebar-red sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center" href="<?= $BASE_PATH ?>index.php">
                <i class="fas fa-pizza-slice sidebar-pizza-icon"></i>
                <span class="sidebar-brand-text">DomiPizzas</span>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item active">
                <a class="nav-link d-flex align-items-center" href="<?= $BASE_PATH ?>index.php">
                    <img src="<?= $BASE_PATH ?>img/home.svg" alt="Inicio" class="sidebar-home-icon">
                    <span>Inicio</span>
                </a>
            </li>
            <li class="nav-item active">
                <a class="nav-link d-flex align-items-center" href="<?= $BASE_PATH ?>docs/manual/manual_usuario.pdf" target="_blank" title="Manual de Usuario (PDF)">
                    <img src="<?= $BASE_PATH ?>img/manual.svg" alt="Manual" class="sidebar-manual-icon">
                    <span>Manual de Usuario</span>
                </a>
            </li>

            

            <!-- Sidebar dinámico por cargo -->
            <?php if (!empty($cargoNombre)) { ?>
                
                <!-- COMPRAS -->
                <?php if ($showCompras) { ?>
                <hr class="sidebar-divider">
                <div class="sidebar-heading d-flex align-items-center">
                    <span>COMPRAS</span>
                </div>
                <li class="nav-item">
                    <a class="nav-link collapsed d-flex align-items-center" href="#" data-toggle="collapse" data-target="#collapseCompras"
                        aria-expanded="false" aria-controls="collapseCompras">
                        <img src="<?= $BASE_PATH ?>img/compras.svg" alt="Compras" class="sidebar-compras-icon">
                        <span>Compras</span>
                    </a>
                    <div id="collapseCompras" class="collapse" aria-labelledby="headingCompras" data-parent="#accordionSidebar">
                        <div class="bg-white py-2 collapse-inner rounded">
                            <?php if (has_permission('PEDIDO_COMPRAS')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('pedido_compra') ?>">Pedidos de compras</a>
                            <?php endif; ?>
                            <?php if (has_permission('PRESUPUESTO_COMPRAS')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('presupuesto') ?>">Presupuesto</a>
                            <?php endif; ?>
                            <?php if (has_permission('ORDEN_COMPRA')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('orden_compra') ?>">Orden de compra</a>
                            <?php endif; ?>
                            <?php if (has_permission('FACTURA_COMPRAS')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('gestionar_compras') ?>">Gestionar Compras</a>
                            <?php endif; ?>
                            <?php if (has_permission('NOTAS_COMPRAS')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('nota_credito') ?>">Nota Crédito/Débito</a>
                            <a class="collapse-item" href="<?= moduloUrl('nota_remision') ?>">Nota Remisión</a>
                            <?php endif; ?>
                            <?php if (has_permission('AJUSTES')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('ajustes') ?>">Ajustes de Stock</a>
                            <?php endif; ?>
                            <?php if (has_permission('LIBRO_COMPRAS')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('libro_compras') ?>">Libro de Compras</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <?php } ?>

                <!-- PRODUCCIÓN (alcance tesis; opciones se habilitan al implementar cada UC) -->
                <?php if ($showProduccion) { ?>
                <hr class="sidebar-divider">
                <div class="sidebar-heading d-flex align-items-center">
                    <span>PRODUCCIÓN</span>
                </div>
                <li class="nav-item">
                    <a class="nav-link collapsed d-flex align-items-center" href="#" data-toggle="collapse" data-target="#collapseProduccion"
                        aria-expanded="false" aria-controls="collapseProduccion">
                        <img src="<?= $BASE_PATH ?>img/notas.svg" alt="Producción" class="sidebar-group-icon">
                        <span>Producción</span>
                    </a>
                    <div id="collapseProduccion" class="collapse" aria-labelledby="headingProduccion" data-parent="#accordionSidebar">
                        <div class="bg-white py-2 collapse-inner rounded">
                            <h6 class="collapse-header">Operaciones</h6>
                            <?php if (has_permission('PEDIDO_PRODUCCION')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('pedido_produccion') ?>">Pedidos de producción</a>
                            <?php else: ?>
                            <span class="collapse-item text-muted small">Pedidos de producción</span>
                            <?php endif; ?>
                            <?php if (has_permission('PEDIDO_MATERIA_PRIMA')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('pedido_materia_produccion') ?>">Pedidos de materia prima</a>
                            <?php else: ?>
                            <span class="collapse-item text-muted small">Pedidos de materia prima</span>
                            <?php endif; ?>
                            <?php if (has_permission('ORDEN_PRODUCCION')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('orden_produccion') ?>">Órdenes de producción</a>
                            <?php else: ?>
                            <span class="collapse-item text-muted small">Órdenes de producción</span>
                            <?php endif; ?>
                            <?php if (has_permission('ETAPAS_PRODUCCION')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('etapa_produccion') ?>">Etapas de producción</a>
                            <?php else: ?>
                            <span class="collapse-item text-muted small">Etapas de producción</span>
                            <?php endif; ?>
                            <?php if (has_permission('EQUIPOS_PRODUCCION')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('equipos_produccion') ?>">Equipos de trabajo</a>
                            <?php else: ?>
                            <span class="collapse-item text-muted small">Equipos de trabajo</span>
                            <?php endif; ?>
                            <?php if (has_permission('CONTROL_PRODUCCION')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('control_produccion') ?>">Control de producción</a>
                            <?php else: ?>
                            <span class="collapse-item text-muted small">Control de producción</span>
                            <?php endif; ?>
                            <?php if (has_permission('PRODUCTOS_TERMINADOS')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('productos_terminados') ?>">Productos terminados</a>
                            <?php else: ?>
                            <span class="collapse-item text-muted small">Productos terminados</span>
                            <?php endif; ?>
                            <?php if (has_permission('CONTROL_CALIDAD')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('control_calidad') ?>">Control de calidad</a>
                            <?php else: ?>
                            <span class="collapse-item text-muted small">Control de calidad</span>
                            <?php endif; ?>
                            <?php if (has_permission('PERDIDAS')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('perdidas') ?>">Pérdidas</a>
                            <?php else: ?>
                            <span class="collapse-item text-muted small">Pérdidas</span>
                            <?php endif; ?>
                            <h6 class="collapse-header">Cierre</h6>
                            <?php if (has_permission('REPOSICION_MATERIA')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('reposicion_materia') ?>">Reposición de MP</a>
                            <?php else: ?>
                            <span class="collapse-item text-muted small">Reposición de MP</span>
                            <?php endif; ?>
                            <?php if (has_permission('COSTOS_PRODUCCION')): ?>
                            <a class="collapse-item" href="<?= moduloUrl('costos_produccion') ?>">Costos de producción</a>
                            <?php else: ?>
                            <span class="collapse-item text-muted small">Costos de producción</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <?php } ?>

                <!-- REFERENCIALES -->
                <?php if ($showReferenciales) { ?>
                <hr class="sidebar-divider">
                <div class="sidebar-heading d-flex align-items-center">
                    <span>REFERENCIALES</span>
                </div>
                <li class="nav-item">
                    <a class="nav-link collapsed d-flex align-items-center" href="#" data-toggle="collapse" data-target="#collapseReferenciales"
                        aria-expanded="false" aria-controls="collapseReferenciales">
                        <img src="<?= $BASE_PATH ?>img/manual.svg" alt="Referenciales" class="sidebar-group-icon">
                        <span>Referenciales</span>
                    </a>
                    <div id="collapseReferenciales" class="collapse" aria-labelledby="headingReferenciales" data-parent="#accordionSidebar">
                        <div class="bg-white py-2 collapse-inner rounded">
                            <?php if (has_permission('COMPRAS') || has_permission('REFERENCIALES')): ?>
                            <h6 class="collapse-header">Compras</h6>
                            <a class="collapse-item" href="<?= moduloUrl('materia_prima') ?>">Materia prima</a>
                            <a class="collapse-item" href="<?= moduloUrl('proveedor') ?>">Proveedores</a>
                            <a class="collapse-item" href="<?= moduloUrl('deposito') ?>">Depósitos</a>
                            <a class="collapse-item" href="<?= moduloUrl('marca') ?>">Marca</a>
                            <a class="collapse-item" href="<?= moduloUrl('u_medida') ?>">Unidad de medida</a>
                            <?php endif; ?>
                            <?php if (has_permission('REFERENCIALES')): ?>
                            <h6 class="collapse-header">Producción</h6>
                            <a class="collapse-item" href="<?= moduloUrl('trabajadores') ?>">Trabajadores</a>
                            <a class="collapse-item" href="<?= moduloUrl('inspeccion') ?>">Inspección</a>
                            <a class="collapse-item" href="<?= moduloUrl('inspectores') ?>">Inspectores</a>
                            <a class="collapse-item" href="<?= moduloUrl('etapa_produccion') ?>">Etapas de producción</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <?php } ?>

                <!-- INVENTARIO -->
                <!-- REPORTES GENERALES -->
                <?php if ($showReportes) { ?>
                <hr class="sidebar-divider">
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center" href="<?= moduloUrl('reportes') ?>">
                        <img src="<?= $BASE_PATH ?>img/manual.svg" alt="Reportes" class="sidebar-group-icon">
                        <span>Reportes Generales</span>
                    </a>
                </li>
                <?php } ?>

                <!-- ADMINISTRACIÓN -->
                <?php if ($showAdministracion) { ?>
                <hr class="sidebar-divider">
                <div class="sidebar-heading d-flex align-items-center">
                    <span>ADMINISTRACIÓN</span>
                </div>
                <li class="nav-item">
                    <a class="nav-link collapsed d-flex align-items-center" href="#" data-toggle="collapse" data-target="#collapseAdm"
                        aria-expanded="false" aria-controls="collapseAdm">
                        <img src="<?= $BASE_PATH ?>img/admin.svg" alt="Administración" class="sidebar-admin-icon">
                        <span>Administración</span>
                    </a>
                    <div id="collapseAdm" class="collapse" aria-labelledby="headingAdm" data-parent="#accordionSidebar">
                        <div class="bg-white py-2 collapse-inner rounded">
                            <a class="collapse-item d-flex align-items-center" href="<?= moduloUrl('usuario') ?>">
                                Usuarios
                            </a>
                            <a class="collapse-item d-flex align-items-center" href="<?= moduloUrl('reset_password', 'reset.php') ?>">
                                Cambiar contraseña
                            </a>
                        </div>
                    </div>
                </li>
                <?php } ?>

            <?php } ?>

           

            


            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            

            <!-- Sidebar Toggler (Sidebar) -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>

            

            <!-- Sidebar Message >
            <div class="sidebar-card d-none d-lg-flex">
                <img class="sidebar-card-illustration mb-2" src="img/undraw_rocket.svg" alt="...">
                <p class="text-center mb-2"><strong>SB Admin Pro</strong> is packed with premium features, components, and more!</p>
                <a class="btn btn-success btn-sm" href="https://startbootstrap.com/theme/sb-admin-pro">Upgrade to Pro!</a>
            </div-->

        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">
                        <div class="topbar-divider d-none d-sm-block"></div>

                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($auth_user['username']); ?></span>
                                <img class="img-profile rounded-circle"
                                    src="<?= $BASE_PATH ?>img/undraw_profile.svg">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="<?= moduloUrl('perfil') ?>">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Perfil
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Cerrar sesión
                                </a>
                            </div>
                        </li>

                    </ul>

                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
